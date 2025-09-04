<?php
require_once 'auth/config.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Configuration
    $excelFilePath = 'C:\\Users\\alexh\\OneDrive - Zimple Rentals Inc\\2024\\2024-Reports\\ZB Reservations by App Date.xlsx';
    
    try {
        // Check if file exists
        if (!file_exists($excelFilePath)) {
            throw new Exception("Excel file not found at: $excelFilePath");
        }
        
        // Get database connection
        $pdo = getReservationsDB();
        
        // Get source_id for ZB.com
        $stmt = $pdo->prepare("SELECT id FROM data_sources WHERE source_name = 'zbcom'");
        $stmt->execute();
        $sourceId = $stmt->fetch(PDO::FETCH_ASSOC)['id'];
        
        if (!$sourceId) {
            // Create the data source if it doesn't exist
            $stmt = $pdo->prepare("INSERT INTO data_sources (source_name, site_name, description) VALUES ('zbcom', 'ZB.com', 'Main reservation site')");
            $stmt->execute();
            $sourceId = $pdo->lastInsertId();
        }
        
        // Use ZipArchive to read XLSX file
        $zip = new ZipArchive();
        if ($zip->open($excelFilePath) !== true) {
            throw new Exception("Unable to open XLSX file");
        }
        
        // Load shared strings
        $sharedStrings = [];
        $sharedStringsIndex = $zip->locateName('xl/sharedStrings.xml');
        if ($sharedStringsIndex !== false) {
            $sharedStringsXml = $zip->getFromIndex($sharedStringsIndex);
            $sharedStringsDoc = simplexml_load_string($sharedStringsXml);
            if ($sharedStringsDoc) {
                foreach ($sharedStringsDoc->si as $si) {
                    $sharedStrings[] = (string)$si->t;
                }
            }
        }
        
        // Read the first worksheet
        $worksheetIndex = $zip->locateName('xl/worksheets/sheet1.xml');
        if ($worksheetIndex === false) {
            throw new Exception("No worksheet found in XLSX file");
        }
        
        $worksheetXml = $zip->getFromIndex($worksheetIndex);
        $worksheetDoc = simplexml_load_string($worksheetXml);
        
        if (!$worksheetDoc) {
            throw new Exception("Unable to parse worksheet XML");
        }
        
        $zip->close();
        
        // Extract data from worksheet
        $rows = [];
        foreach ($worksheetDoc->sheetData->row as $row) {
            $rowData = [];
            foreach ($row->c as $cell) {
                $cellValue = '';
                $cellType = (string)$cell['t'];
                
                if (isset($cell->v)) {
                    $value = (string)$cell->v;
                    
                    if ($cellType === 's') {
                        // Shared string
                        $cellValue = $sharedStrings[(int)$value] ?? '';
                    } elseif ($cellType === 'b') {
                        // Boolean
                        $cellValue = $value === '1' ? 'TRUE' : 'FALSE';
                    } else {
                        // Number or inline string
                        $cellValue = $value;
                    }
                }
                
                $rowData[] = $cellValue;
            }
            
            if (!empty(array_filter($rowData))) {
                $rows[] = $rowData;
            }
        }
        
        if (empty($rows)) {
            throw new Exception("No data found in Excel file");
        }
        
        // Remove header row and process data
        $headers = array_shift($rows);
        
        // Prepare insert statement
        $insertStmt = $pdo->prepare("INSERT INTO zbcom_reservations (
            source_id, res_date, agency, pay_mode, api_value, credit, cpc, prepay, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $insertedCount = 0;
        $skippedCount = 0;
        
        foreach ($rows as $index => $row) {
            try {
                // Ensure we have enough columns, pad with empty strings if needed
                $row = array_pad($row, 9, '');
                
                // Clean and validate data
                $resDate = !empty($row[0]) ? $row[0] : null;
                $agency = !empty($row[1]) ? trim($row[1]) : null;
                $payMode = !empty($row[2]) ? trim($row[2]) : null;
                $apiValue = !empty($row[3]) && is_numeric($row[3]) ? (float)$row[3] : null;
                $credit = !empty($row[4]) && is_numeric($row[4]) ? (float)$row[4] : null;
                $cpc = !empty($row[5]) ? trim($row[5]) : null;
                $prepay = !empty($row[6]) ? trim($row[6]) : null;
                $status = !empty($row[7]) ? trim($row[7]) : null;
                
                // Skip rows with no meaningful data
                if (empty($agency) && empty($resDate)) {
                    $skippedCount++;
                    continue;
                }
                
                $insertStmt->execute([
                    $sourceId,
                    $resDate,
                    $agency,
                    $payMode,
                    $apiValue,
                    $credit,
                    $cpc,
                    $prepay,
                    $status
                ]);
                
                $insertedCount++;
                
            } catch (Exception $e) {
                $skippedCount++;
            }
        }
        
        $message = "Successfully imported $insertedCount records from ZB.com Excel file. Skipped: $skippedCount";
        $messageType = 'success';
        
    } catch (Exception $e) {
        $message = "Error importing data: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Get current statistics
try {
    $pdo = getReservationsDB();
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM zbcom_reservations");
    $stmt->execute();
    $totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM zbcom_reservations WHERE DATE(imported_at) = CURDATE()");
    $stmt->execute();
    $todayRecords = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
} catch (Exception $e) {
    $totalRecords = 0;
    $todayRecords = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import ZB.com Data - Zimple Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-primary text-white shadow-md">
            <div class="container mx-auto px-6 py-4">
                <div class="flex items-center justify-between">
                    <h1 class="text-2xl font-bold">Zimple Admin</h1>
                    <nav class="flex items-center space-x-6">
                        <a href="reports/index.html" class="hover:text-yellow-200 transition-colors">Reports</a>
                        <a href="admin/clients/index.html" class="hover:text-yellow-200 transition-colors">Clients</a>
                        <a href="admin/partners/index.html" class="hover:text-yellow-200 transition-colors">Partners</a>
                        <a href="admin/users/index.html" class="hover:text-yellow-200 transition-colors">Users</a>
                    </nav>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="container mx-auto px-6 py-8">
            <div class="max-w-4xl mx-auto">
                <div class="bg-white rounded-lg shadow-md p-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">Import ZB.com Reservations Data</h2>
                    
                    <!-- Statistics -->
                    <div class="grid md:grid-cols-2 gap-6 mb-8">
                        <div class="bg-blue-50 p-6 rounded-lg">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-blue-100">
                                    <i class="fas fa-database text-blue-600 text-xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-600">Total Records</p>
                                    <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($totalRecords); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-green-50 p-6 rounded-lg">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-green-100">
                                    <i class="fas fa-calendar-day text-green-600 text-xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-600">Today's Imports</p>
                                    <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($todayRecords); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (isset($message)): ?>
                        <div class="mb-6 p-4 rounded-md <?php echo $messageType === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                            <div class="flex items-center">
                                <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> mr-2"></i>
                                <?php echo htmlspecialchars($message); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- File Information -->
                    <div class="bg-gray-50 p-6 rounded-lg mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">File Information</h3>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">File Path:</span>
                                <span class="font-mono text-gray-800">C:\Users\alexh\OneDrive - Zimple Rentals Inc\2024\2024-Reports\ZB Reservations by App Date.xlsx</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Target Table:</span>
                                <span class="font-mono text-gray-800">zbcom_reservations</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Data Source:</span>
                                <span class="font-mono text-gray-800">ZB.com</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Import Form -->
                    <form method="POST" class="space-y-6">
                        <div class="bg-yellow-50 border border-yellow-200 p-4 rounded-lg">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-triangle text-yellow-600 mr-2"></i>
                                <span class="text-yellow-800 font-medium">Important:</span>
                            </div>
                            <p class="text-yellow-700 text-sm mt-2">
                                This will import data from the Excel file located at the specified path. 
                                The import will add new records to the zbcom_reservations table. 
                                Make sure the Excel file is up to date before proceeding.
                            </p>
                        </div>
                        
                        <div class="flex items-center space-x-4">
                            <button type="submit" class="bg-primary hover:bg-primary/90 text-white px-6 py-3 rounded-md font-medium transition-colors flex items-center">
                                <i class="fas fa-upload mr-2"></i>Import ZB.com Data
                            </button>
                            <a href="reports/index.html" class="border border-gray-300 text-gray-700 hover:bg-gray-50 px-6 py-3 rounded-md font-medium transition-colors">
                                Back to Reports
                            </a>
                        </div>
                    </form>
                    
                    <!-- Recent Imports -->
                    <div class="mt-8">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Recent Imports</h3>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <?php
                            try {
                                $stmt = $pdo->prepare("SELECT * FROM zbcom_reservations ORDER BY imported_at DESC LIMIT 10");
                                $stmt->execute();
                                $recentImports = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                if (!empty($recentImports)) {
                                    echo '<div class="space-y-2 text-sm">';
                                    foreach ($recentImports as $import) {
                                        echo '<div class="flex justify-between items-center py-2 border-b border-gray-200 last:border-b-0">';
                                        echo '<span class="text-gray-600">' . htmlspecialchars($import['agency'] ?? 'N/A') . '</span>';
                                        echo '<span class="text-gray-800">' . date('M j, Y H:i', strtotime($import['imported_at'])) . '</span>';
                                        echo '</div>';
                                    }
                                    echo '</div>';
                                } else {
                                    echo '<p class="text-gray-500 text-sm">No recent imports found.</p>';
                                }
                            } catch (Exception $e) {
                                echo '<p class="text-red-500 text-sm">Error loading recent imports.</p>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
