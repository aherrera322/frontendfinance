<?php
require_once 'auth/config.php';

try {
    $pdo = getReservationsDB();
    
    // Get table structure
    $stmt = $pdo->query("SHOW COLUMNS FROM aero_res_22");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Columns in aero_res_22 table:\n";
    foreach ($columns as $column) {
        echo "- " . $column . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>



