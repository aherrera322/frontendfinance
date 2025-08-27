<?php
require_once 'auth/config.php';

try {
    $pdo = getReservationsDB();
    
    // Check if file column exists
    $stmt = $pdo->query("DESCRIBE aero_res_22");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Columns in aero_res_22 table:\n";
    foreach($columns as $col) {
        echo "- " . $col . "\n";
    }
    
    echo "\nChecking if 'file' column exists:\n";
    if (in_array('file', $columns)) {
        echo "✅ 'file' column EXISTS in the table\n";
        
        // Check if there's any data in the file column
        $stmt = $pdo->query("SELECT COUNT(*) as total, COUNT(file) as with_file FROM aero_res_22");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "Total records: " . $result['total'] . "\n";
        echo "Records with file data: " . $result['with_file'] . "\n";
        
        if ($result['with_file'] > 0) {
            echo "\nSample file data:\n";
            $stmt = $pdo->query("SELECT file FROM aero_res_22 WHERE file IS NOT NULL AND file != '' LIMIT 5");
            $files = $stmt->fetchAll(PDO::FETCH_COLUMN);
            foreach($files as $file) {
                echo "- " . $file . "\n";
            }
        } else {
            echo "❌ No data found in 'file' column\n";
        }
        
    } else {
        echo "❌ 'file' column does NOT exist in the table\n";
        echo "\nAvailable columns:\n";
        foreach($columns as $col) {
            echo "- " . $col . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>

