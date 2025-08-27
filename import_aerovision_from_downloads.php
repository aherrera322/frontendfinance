<?php
require_once 'auth/config.php';

$message = '';
$error = '';

// Path to the CSV file in Downloads
$csvPath = $_SERVER['USERPROFILE'] . '/Downloads/aerovision reservations.csv';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getReservationsDB();
        
        // Check if file exists
        if (!file_exists($csvPath)) {
            throw new Exception("CSV file not found at: $csvPath");
        }
        
        // Read CSV file
        $handle = fopen($csvPath, 'r');
        if (!$handle) {
            throw new Exception('Could not open CSV file');
        }
        
        // Get headers
        $headers = fgetcsv($handle);
        if (!$headers) {
            throw new Exception('Could not read CSV headers');
        }
        
        // Clean headers
        $headers = array_map('trim', $headers);
        
        echo "<h3>CSV Headers Found:</h3>";
        echo "<ul>";
        foreach ($headers as $index => $header) {
            echo "<li>Column $index: " . htmlspecialchars($header) . "</li>";
        }
        echo "</ul>";
        
        // Define expected columns for Aerovision
        $expectedColumns = [
            'res_date', 'approve_date', 'res_number', 'res_type', 'prepay', 
            'voucher', 'ac_no', 'agency', 'pay_mode', 'api_value', 'credit', 'cpc', 'status'
        ];
        
        // Map CSV headers to database columns
        $columnMapping = [];
        foreach ($expectedColumns as $expectedCol) {
            foreach ($headers as $index => $header) {
                if (stripos($header, $expectedCol) !== false || 
                    stripos($expectedCol, $header) !== false) {
                    $columnMapping[$expectedCol] = $index;
                    break;
                }
            }
        }
        
        echo "<h3>Column Mapping:</h3>";
        echo "<ul>";
        foreach ($columnMapping as $dbColumn => $csvIndex) {
            echo "<li>" . htmlspecialchars($dbColumn) . " → " . htmlspecialchars($headers[$csvIndex]) . "</li>";
        }
        echo "</ul>";
        
        // Check if we have at least some key columns
        if (empty($columnMapping)) {
            throw new Exception('No recognizable columns found in CSV. Expected columns: ' . implode(', ', $expectedColumns));
        }
        
        // First, let's check if table exists and get its structure
        $stmt = $pdo->query("SHOW TABLES LIKE 'aerovision_reservations'");
        $tableExists = $stmt->rowCount() > 0;
        
        if (!$tableExists) {
            // Create table if it doesn't exist
            $createTableSQL = "CREATE TABLE aerovision_reservations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                res_date DATE,
                approve_date DATE,
                res_number VARCHAR(255),
                res_type VARCHAR(255),
                prepay VARCHAR(255),
                voucher VARCHAR(255),
                ac_no VARCHAR(255),
                agency VARCHAR(255),
                pay_mode VARCHAR(255),
                api_value DECIMAL(10,2),
                credit DECIMAL(10,2),
                cpc VARCHAR(255),
                status VARCHAR(255),
                imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            $pdo->exec($createTableSQL);
            echo "<p style='color: green;'>✅ Created aerovision_reservations table</p>";
        } else {
            // Check if we need to add missing columns
            $stmt = $pdo->query("DESCRIBE aerovision_reservations");
            $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($columnMapping as $column => $index) {
                if (!in_array($column, $existingColumns)) {
                    $alterSQL = "ALTER TABLE aerovision_reservations ADD COLUMN $column ";
                    if ($column === 'api_value' || $column === 'credit') {
                        $alterSQL .= "DECIMAL(10,2)";
                    } elseif ($column === 'res_date' || $column === 'approve_date') {
                        $alterSQL .= "DATE";
                    } else {
                        $alterSQL .= "VARCHAR(255)";
                    }
                    $pdo->exec($alterSQL);
                    echo "<p style='color: blue;'>✅ Added column: $column</p>";
                }
            }
        }
        
        // Clear existing data if requested
        if (isset($_POST['clear_existing']) && $_POST['clear_existing'] === 'yes') {
            $pdo->exec("TRUNCATE TABLE aerovision_reservations");
            echo "<p style='color: orange;'>🗑️ Cleared existing data from table</p>";
        }
        
        // Prepare insert statement
        $columns = array_keys($columnMapping);
        $placeholders = str_repeat('?,', count($columns) - 1) . '?';
        $sql = "INSERT INTO aerovision_reservations (" . implode(', ', $columns) . ", imported_at) VALUES ($placeholders, NOW())";
        $stmt = $pdo->prepare($sql);
        
        $imported = 0;
        $skipped = 0;
        $rowNumber = 1; // Start from 1 since we already read headers
        
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            
            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }
            
            try {
                // Prepare data for insertion
                $data = [];
                foreach ($columns as $column) {
                    $value = $row[$columnMapping[$column]] ?? '';
                    
                    // Clean and validate data
                    $value = trim($value);
                    
                    // Handle specific data types
                    if ($column === 'api_value' || $column === 'credit') {
                        $value = is_numeric($value) ? floatval($value) : null;
                    } elseif ($column === 'res_date' || $column === 'approve_date') {
                        // Convert Excel date if needed
                        if (is_numeric($value) && $value > 1000) {
                            $value = date('Y-m-d', ($value - 25569) * 86400);
                        }
                    }
                    
                    $data[] = $value;
                }
                
                $stmt->execute($data);
                $imported++;
                
            } catch (Exception $e) {
                $skipped++;
                echo "<p style='color: red;'>❌ Error on row $rowNumber: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        }
        
        fclose($handle);
        
        $message = "Import completed successfully! Imported: $imported records, Skipped: $skipped records";
        
        // Show final table structure
        $stmt = $pdo->query("DESCRIBE aerovision_reservations");
        $finalColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Final Table Structure:</h3>";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        foreach ($finalColumns as $column) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Default']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Extra']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Show record count
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM aerovision_reservations");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo "<p><strong>Total Records in Table:</strong> " . number_format($count) . "</p>";
        
    } catch (Exception $e) {
        $error = 'Import failed: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Aerovision from Downloads - Zimple Rentals</title>
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
    </script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-primary text-white shadow-lg">
            <div class="container mx-auto px-6 py-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <i class="fas fa-plane text-2xl"></i>
                        <h1 class="text-2xl font-bold">Import Aerovision from Downloads</h1>
                    </div>
                    <nav class="flex items-center space-x-6">
                        <a href="index.html" class="hover:text-yellow-200 transition-colors">Home</a>
                        <a href="dashboard_aerovision.php" class="hover:text-yellow-200 transition-colors">Aerovision Dashboard</a>
                        <a href="reports/index.html" class="hover:text-yellow-200 transition-colors">Reports</a>
                    </nav>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="container mx-auto px-6 py-8">
            <div class="max-w-4xl mx-auto">
                <!-- Import Form -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Import from Downloads Folder</h2>
                    
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                        <h3 class="text-sm font-medium text-blue-900 mb-2">File Location:</h3>
                        <p class="text-sm text-blue-700 font-mono"><?php echo htmlspecialchars($csvPath); ?></p>
                        <p class="text-xs text-blue-600 mt-2">
                            This script will automatically read the CSV file from your Downloads folder and update the aerovision_reservations table.
                        </p>
                    </div>
                    
                    <?php if ($message): ?>
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" class="space-y-4">
                        <div class="flex items-center space-x-4">
                            <label class="flex items-center">
                                <input type="checkbox" name="clear_existing" value="yes" class="mr-2">
                                <span class="text-sm text-gray-700">Clear existing data before import</span>
                            </label>
                        </div>
                        
                        <button type="submit" 
                                class="w-full bg-primary hover:bg-primary/90 text-white font-medium py-2 px-4 rounded-md transition-colors">
                            <i class="fas fa-upload mr-2"></i>Import from Downloads
                        </button>
                    </form>
                </div>
                
                <!-- Quick Actions -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <a href="dashboard_aerovision.php" 
                       class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-md text-center font-medium transition-colors">
                        <i class="fas fa-chart-bar mr-2"></i>View Dashboard
                    </a>
                    <a href="view_aerovision_data.php" 
                       class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-md text-center font-medium transition-colors">
                        <i class="fas fa-table mr-2"></i>View Raw Data
                    </a>
                    <a href="check_aerovision_table.php" 
                       class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded-md text-center font-medium transition-colors">
                        <i class="fas fa-database mr-2"></i>Check Table
                    </a>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

