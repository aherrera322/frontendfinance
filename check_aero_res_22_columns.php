<?php
require_once 'auth/config.php';

try {
    $pdo = getReservationsDB();
    $stmt = $pdo->query('DESCRIBE aero_res_22');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Columns in aero_res_22 table:\n";
    foreach($columns as $col) {
        echo $col['Field'] . " (" . $col['Type'] . ")\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
