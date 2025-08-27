<?php
require_once 'auth/config.php';

echo "=== Aerovision Reservations Import Script ===\n\n";

// Path to the CSV file in Downloads
$csvPath = $_SERVER['USERPROFILE'] . '/Downloads/aerovision reservations.csv';

echo "Looking for CSV file at: $csvPath\n\n";

try {
    $pdo = getReservationsDB();
    
    // Check if file exists
    if (!file_exists($csvPath)) {
        throw new Exception("CSV file not found at: $csvPath");
    }
    
    echo "✅ CSV file found!\n\n";
    
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
    
    echo "CSV Headers Found:\n";
    foreach ($headers as $index => $header) {
        echo "  Column $index: $header\n";
    }
    echo "\n";
    
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
    
    echo "Column Mapping:\n";
    foreach ($columnMapping as $dbColumn => $csvIndex) {
        echo "  $dbColumn → {$headers[$csvIndex]}\n";
    }
    echo "\n";
    
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
        echo "✅ Created aerovision_reservations table\n\n";
    } else {
        echo "✅ Table aerovision_reservations already exists\n\n";
        
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
                echo "✅ Added column: $column\n";
            }
        }
        echo "\n";
    }
    
    // Ask user if they want to clear existing data
    echo "Do you want to clear existing data before import? (y/n): ";
    $handle_stdin = fopen("php://stdin", "r");
    $clearExisting = trim(fgets($handle_stdin));
    fclose($handle_stdin);
    
    if (strtolower($clearExisting) === 'y' || strtolower($clearExisting) === 'yes') {
        $pdo->exec("TRUNCATE TABLE aerovision_reservations");
        echo "🗑️ Cleared existing data from table\n\n";
    }
    
    // Prepare insert statement
    $columns = array_keys($columnMapping);
    $placeholders = str_repeat('?,', count($columns) - 1) . '?';
    $sql = "INSERT INTO aerovision_reservations (" . implode(', ', $columns) . ", imported_at) VALUES ($placeholders, NOW())";
    $stmt = $pdo->prepare($sql);
    
    $imported = 0;
    $skipped = 0;
    $rowNumber = 1; // Start from 1 since we already read headers
    
    echo "Starting import...\n";
    
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
            
            // Show progress every 100 records
            if ($imported % 100 === 0) {
                echo "Imported $imported records...\n";
            }
            
        } catch (Exception $e) {
            $skipped++;
            echo "❌ Error on row $rowNumber: " . $e->getMessage() . "\n";
        }
    }
    
    fclose($handle);
    
    echo "\n=== Import Summary ===\n";
    echo "✅ Imported: $imported records\n";
    echo "❌ Skipped: $skipped records\n";
    echo "📊 Total processed: " . ($imported + $skipped) . " records\n\n";
    
    // Show final record count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM aerovision_reservations");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "📈 Total records in table: " . number_format($count) . "\n\n";
    
    echo "=== Next Steps ===\n";
    echo "1. View Dashboard: http://localhost/zimplerentals/dashboard_aerovision.php\n";
    echo "2. View Raw Data: http://localhost/zimplerentals/view_aerovision_data.php\n";
    echo "3. Check Table: http://localhost/zimplerentals/check_aerovision_table.php\n\n";
    
    echo "Import completed successfully! 🎉\n";
    
} catch (Exception $e) {
    echo "❌ Import failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>

