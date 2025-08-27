<?php
require_once 'auth/config.php';

// Configuration
$excelFilePath = 'C:\\Users\\alexh\\OneDrive - Zimple Rentals Inc\\2024\\2024-Reports\\ZB Reservations by App Date.xlsx';

try {
    echo "Starting import of ZB.com reservations data...\n\n";
    
    // Check if file exists
    if (!file_exists($excelFilePath)) {
        throw new Exception("Excel file not found at: $excelFilePath");
    }
    
    echo "✅ Found Excel file: " . basename($excelFilePath) . "\n";
    
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
        echo "✅ Created ZB.com data source (ID: $sourceId)\n";
    } else {
        echo "✅ Found ZB.com data source (ID: $sourceId)\n";
    }
    
    // Use ZipArchive to read XLSX file (no Composer required)
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
            $cellRef = (string)$cell['r'];
            
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
    
    echo "✅ Found " . count($rows) . " rows in Excel file\n";
    
    // Remove header row and process data
    $headers = array_shift($rows);
    echo "✅ Headers: " . implode(', ', array_slice($headers, 0, 5)) . "...\n";
    
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
            
            // Show progress every 100 rows
            if ($insertedCount % 100 === 0) {
                echo "Processed $insertedCount rows...\n";
            }
            
        } catch (Exception $e) {
            echo "⚠️  Error processing row " . ($index + 2) . ": " . $e->getMessage() . "\n";
            $skippedCount++;
        }
    }
    
    echo "\n✅ Import completed successfully!\n";
    echo "📊 Summary:\n";
    echo "   - Total rows processed: " . count($rows) . "\n";
    echo "   - Successfully inserted: $insertedCount\n";
    echo "   - Skipped: $skippedCount\n";
    echo "   - Data source ID: $sourceId\n";
    
    // Show sample of imported data
    $sampleStmt = $pdo->prepare("SELECT * FROM zbcom_reservations WHERE source_id = ? ORDER BY imported_at DESC LIMIT 5");
    $sampleStmt->execute([$sourceId]);
    $sampleData = $sampleStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($sampleData)) {
        echo "\n📋 Sample of imported data:\n";
        foreach ($sampleData as $row) {
            echo "   - Agency: " . ($row['agency'] ?? 'N/A') . 
                 ", Date: " . ($row['res_date'] ?? 'N/A') . 
                 ", Status: " . ($row['status'] ?? 'N/A') . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
