<?php
require_once 'auth/config.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    try {
        $pdo = getReservationsDB();
        
        $file = $_FILES['csv_file'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload failed: ' . $file['error']);
        }
        
        if ($file['type'] !== 'text/csv' && !str_ends_with($file['name'], '.csv')) {
            throw new Exception('Please upload a CSV file');
        }
        
        // Read CSV file
        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            throw new Exception('Could not open uploaded file');
        }
        
        // Get headers
        $headers = fgetcsv($handle);
        if (!$headers) {
            throw new Exception('Could not read CSV headers');
        }
        
        // Clean headers
        $headers = array_map('trim', $headers);
        
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
        
        // Check if we have at least some key columns
        if (empty($columnMapping)) {
            throw new Exception('No recognizable columns found in CSV. Expected columns: ' . implode(', ', $expectedColumns));
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
                // Continue with next row
            }
        }
        
        fclose($handle);
        
        $message = "Import completed successfully! Imported: $imported records, Skipped: $skipped records";
        
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
    <title>Import Aerovision CSV - Zimple Rentals</title>
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
                        <h1 class="text-2xl font-bold">Import Aerovision CSV Data</h1>
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
            <div class="max-w-2xl mx-auto">
                <!-- Import Form -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Upload Aerovision CSV File</h2>
                    
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
                    
                    <form method="POST" enctype="multipart/form-data" class="space-y-4">
                        <div>
                            <label for="csv_file" class="block text-sm font-medium text-gray-700 mb-2">
                                Select CSV File
                            </label>
                            <input type="file" 
                                   id="csv_file" 
                                   name="csv_file" 
                                   accept=".csv"
                                   required
                                   class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-primary file:text-white hover:file:bg-primary/90">
                        </div>
                        
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <h3 class="text-sm font-medium text-blue-900 mb-2">Expected CSV Columns:</h3>
                            <p class="text-sm text-blue-700">
                                res_date, approve_date, res_number, res_type, prepay, voucher, ac_no, agency, pay_mode, api_value, credit, cpc, status
                            </p>
                            <p class="text-xs text-blue-600 mt-2">
                                Note: The import will automatically map similar column names. Data will be appended to the existing table.
                            </p>
                        </div>
                        
                        <button type="submit" 
                                class="w-full bg-primary hover:bg-primary/90 text-white font-medium py-2 px-4 rounded-md transition-colors">
                            <i class="fas fa-upload mr-2"></i>Import CSV Data
                        </button>
                    </form>
                </div>
                
                <!-- Quick Actions -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <a href="dashboard_aerovision.php" 
                       class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-md text-center font-medium transition-colors">
                        <i class="fas fa-chart-bar mr-2"></i>View Dashboard
                    </a>
                    <a href="view_aerovision_data.php" 
                       class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-md text-center font-medium transition-colors">
                        <i class="fas fa-table mr-2"></i>View Raw Data
                    </a>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

