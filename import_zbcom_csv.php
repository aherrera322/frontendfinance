<?php
require_once 'auth/config.php';

// Function to convert Excel date to MySQL date
function excelToDate($excelDate) {
    if (empty($excelDate) || !is_numeric($excelDate)) {
        return null;
    }
    // Excel dates are number of days since 1900-01-01
    // But Excel incorrectly treats 1900 as a leap year, so we need to adjust
    $unixTimestamp = ($excelDate - 25569) * 86400;
    // Check if it's a valid date (not too far in the future)
    if ($unixTimestamp > time() + (365 * 24 * 60 * 60)) {
        return null; // Too far in the future, likely invalid
    }
    return date('Y-m-d', $unixTimestamp);
}

// Function to get all tables from the database
function getDatabaseTables($pdo) {
    $tables = [];
    try {
        $stmt = $pdo->query("SHOW TABLES");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }
    } catch (Exception $e) {
        error_log("Error getting tables: " . $e->getMessage());
    }
    return $tables;
}

// Function to get table structure
function getTableStructure($pdo, $tableName) {
    $columns = [];
    try {
        $stmt = $pdo->query("DESCRIBE `$tableName`");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $row;
        }
    } catch (Exception $e) {
        error_log("Error getting table structure: " . $e->getMessage());
    }
    return $columns;
}

$message = '';
$error = '';
$tables = [];
$selectedTable = '';
$tableStructure = [];

try {
    $pdo = getReservationsDB();
    $tables = getDatabaseTables($pdo);
} catch (Exception $e) {
    $error = "Database connection error: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $selectedTable = $_POST['selected_table'] ?? '';
        
        if (empty($selectedTable)) {
            throw new Exception('Please select a table to import data into.');
        }
        
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Please select a valid CSV file to upload.');
        }

        $file = $_FILES['csv_file'];
        $fileName = $file['name'];
        $fileTmpName = $file['tmp_name'];
        $fileSize = $file['size'];
        $fileError = $file['error'];

        // Validate file
        if ($fileError !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $fileError);
        }

        if ($fileSize === 0) {
            throw new Exception('The uploaded file is empty.');
        }

        if ($fileSize > 10 * 1024 * 1024) { // 10MB limit
            throw new Exception('File size too large. Maximum size is 10MB.');
        }

        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if ($fileExtension !== 'csv') {
            throw new Exception('Only CSV files are allowed.');
        }

        // Get table structure for the selected table
        $tableStructure = getTableStructure($pdo, $selectedTable);
        if (empty($tableStructure)) {
            throw new Exception('Could not get table structure for the selected table.');
        }

        // Read CSV file
        if (($handle = fopen($fileTmpName, "r")) === FALSE) {
            throw new Exception('Could not open the uploaded file.');
        }

        // Get headers (first row)
        $headers = fgetcsv($handle);
        if (!$headers) {
            throw new Exception('Could not read CSV headers.');
        }

        // Clean headers
        $headers = array_map('trim', $headers);
        $headers = array_map('strtolower', $headers);

        // Create column mapping based on table structure
        $columnMap = [];
        $insertColumns = [];
        
        foreach ($tableStructure as $column) {
            $columnName = $column['Field'];
            
            // Skip auto-increment and timestamp columns
            if ($column['Extra'] === 'auto_increment' || 
                $columnName === 'id' || 
                $columnName === 'imported_at' ||
                $columnName === 'created_at' ||
                $columnName === 'updated_at') {
                continue;
            }
            
            // Try to find matching CSV column
            $found = false;
            foreach ($headers as $index => $header) {
                if (stripos($header, $columnName) !== false || 
                    stripos($columnName, $header) !== false ||
                    $header === $columnName) {
                    $columnMap[$columnName] = $index;
                    $insertColumns[] = $columnName;
                    $found = true;
                    break;
                }
            }
            
            // If not found, try common variations
            if (!$found) {
                $variations = [
                    'res_date' => ['reservation_date', 'date', 'booking_date'],
                    'approve_date' => ['approval_date', 'approved_date'],
                    'res_number' => ['reservation_number', 'booking_number', 'confirmation_number'],
                    'res_type' => ['reservation_type', 'booking_type'],
                    'prepay' => ['pre_pay', 'prepayment'],
                    'voucher' => ['voucher_number', 'voucher_code'],
                    'ac_no' => ['account_number', 'account_no', 'ac_number'],
                    'agency' => ['agency_name', 'travel_agency'],
                    'pay_mode' => ['payment_mode', 'payment_method'],
                    'api_value' => ['value', 'amount', 'total_amount'],
                    'credit' => ['credit_amount', 'credit_value'],
                    'cpc' => ['commission'],
                    'status' => ['booking_status', 'reservation_status']
                ];
                
                if (isset($variations[$columnName])) {
                    foreach ($variations[$columnName] as $variation) {
                        $foundIndex = array_search($variation, $headers);
                        if ($foundIndex !== false) {
                            $columnMap[$columnName] = $foundIndex;
                            $insertColumns[] = $columnName;
                            $found = true;
                            break;
                        }
                    }
                }
            }
        }

        // Check if we have at least some columns to insert
        if (empty($columnMap)) {
            throw new Exception('No recognizable columns found in the CSV file that match the selected table structure.');
        }

        // Prepare insert statement
        $placeholders = array_fill(0, count($insertColumns), '?');
        $sql = "INSERT INTO `$selectedTable` (" . implode(', ', $insertColumns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $pdo->prepare($sql);

        $insertedCount = 0;
        $skippedCount = 0;
        $rowNumber = 1; // Start from 1 since we already read the header

        // Process data rows
        while (($row = fgetcsv($handle)) !== FALSE) {
            $rowNumber++;
            
            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            try {
                $insertValues = [];

                // Add values for each mapped column
                foreach ($insertColumns as $columnName) {
                    $csvIndex = $columnMap[$columnName];
                    $value = isset($row[$csvIndex]) ? trim($row[$csvIndex]) : '';
                    
                    // Handle date conversions for date columns
                    if (stripos($columnName, 'date') !== false && !empty($value)) {
                        $value = excelToDate($value);
                    }
                    
                    // Handle numeric values for numeric columns
                    $columnInfo = null;
                    foreach ($tableStructure as $col) {
                        if ($col['Field'] === $columnName) {
                            $columnInfo = $col;
                            break;
                        }
                    }
                    
                    if ($columnInfo && stripos($columnInfo['Type'], 'decimal') !== false && !empty($value)) {
                        $value = is_numeric($value) ? (float)$value : 0;
                    }
                    
                    $insertValues[] = $value;
                }

                $stmt->execute($insertValues);
                $insertedCount++;

            } catch (Exception $e) {
                $skippedCount++;
                error_log("Error inserting row $rowNumber into $selectedTable: " . $e->getMessage());
            }
        }

        fclose($handle);

        $message = "Import completed successfully!<br>";
        $message .= "✅ Inserted: " . number_format($insertedCount) . " records into `$selectedTable`<br>";
        if ($skippedCount > 0) {
            $message .= "⚠️ Skipped: " . number_format($skippedCount) . " records (check error logs)<br>";
        }
        $message .= "📊 Total records in `$selectedTable`: " . number_format($pdo->query("SELECT COUNT(*) FROM `$selectedTable`")->fetchColumn());

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import CSV to Database - Zimple Rentals</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#117372'
                    }
                }
            }
        }
        
        function updateTableInfo() {
            const selectedTable = document.getElementById('selected_table').value;
            if (selectedTable) {
                // You could add AJAX here to fetch table structure dynamically
                console.log('Selected table:', selectedTable);
            }
        }
    </script>
</head>
<body class="bg-gray-50">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Import CSV to Database</h1>
                    <p class="text-gray-600 mt-2">Upload a CSV file to update any table in the database</p>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="view_data_simple.php" class="bg-primary hover:bg-primary/90 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors">
                        <i class="fas fa-table mr-2"></i>View Data
                    </a>
                    <a href="reports/index.html" class="border border-gray-300 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-md text-sm font-medium transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Reports
                    </a>
                </div>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-md mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm"><?php echo htmlspecialchars($error); ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-md mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm"><?php echo $message; ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Upload Form -->
        <div class="bg-white rounded-lg shadow-sm border p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Upload CSV File</h2>
            
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <div>
                    <label for="selected_table" class="block text-sm font-medium text-gray-700 mb-2">
                        Select Target Table
                    </label>
                    <select id="selected_table" name="selected_table" required onchange="updateTableInfo()"
                            class="block w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        <option value="">-- Select a table --</option>
                        <?php foreach ($tables as $table): ?>
                            <option value="<?php echo htmlspecialchars($table); ?>" 
                                    <?php echo $selectedTable === $table ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($table); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="mt-1 text-sm text-gray-500">Choose which table to import the CSV data into.</p>
                </div>

                <div>
                    <label for="csv_file" class="block text-sm font-medium text-gray-700 mb-2">
                        Select CSV File
                    </label>
                    <input type="file" id="csv_file" name="csv_file" accept=".csv" required
                           class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-primary file:text-white hover:file:bg-primary/90">
                    <p class="mt-1 text-sm text-gray-500">Maximum file size: 10MB. Only CSV files are allowed.</p>
                </div>

                <button type="submit" 
                        class="w-full bg-primary hover:bg-primary/90 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors">
                    <i class="fas fa-upload mr-2"></i>Import CSV Data
                </button>
            </form>
        </div>

        <!-- Instructions -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-blue-900 mb-4">
                <i class="fas fa-info-circle mr-2"></i>CSV Import Guidelines
            </h3>
            <div class="text-sm text-blue-800 space-y-2">
                <p><strong>Column Mapping:</strong> The system will automatically try to match CSV headers with table columns:</p>
                <ul class="list-disc list-inside ml-4 space-y-1">
                    <li>Exact column name matches (case-insensitive)</li>
                    <li>Partial matches (e.g., "res_date" matches "reservation_date")</li>
                    <li>Common variations for reservation data</li>
                </ul>
                <p class="mt-4"><strong>Data Types:</strong></p>
                <ul class="list-disc list-inside ml-4 space-y-1">
                    <li>Date columns: Excel dates will be converted to MySQL format</li>
                    <li>Numeric columns: Values will be converted to appropriate numeric types</li>
                    <li>Text columns: Values will be inserted as-is</li>
                </ul>
                <p class="mt-4"><strong>Note:</strong> Auto-increment and timestamp columns (id, imported_at, created_at, updated_at) are automatically handled by the database.</p>
            </div>
        </div>

        <!-- Available Tables Info -->
        <div class="bg-white rounded-lg shadow-sm border p-6 mt-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Available Tables</h3>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($tables as $table): ?>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <div class="text-sm font-medium text-gray-600">Table Name</div>
                        <div class="text-lg font-bold text-gray-900"><?php echo htmlspecialchars($table); ?></div>
                        <div class="text-xs text-gray-500 mt-1">
                            <?php 
                            try {
                                $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
                                echo number_format($count) . " records";
                            } catch (Exception $e) {
                                echo "Error getting count";
                            }
                            ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>
