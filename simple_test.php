<?php
echo "<h1>Simple Test</h1>";

try {
    require_once 'auth/config.php';
    echo "<p>✅ Config loaded successfully</p>";
    
    $reservationsPdo = getReservationsDB();
    echo "<p>✅ Reservations DB connected</p>";
    
    // Check if table exists
    $stmt = $reservationsPdo->query("SHOW TABLES LIKE 'aero_res_22'");
    $tableExists = $stmt->fetch();
    
    if ($tableExists) {
        echo "<p>✅ aero_res_22 table exists</p>";
        
        // Check table structure
        $stmt = $reservationsPdo->query("DESCRIBE aero_res_22");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Table Columns:</h3>";
        echo "<ul>";
        foreach ($columns as $column) {
            echo "<li>" . $column['Field'] . " (" . $column['Type'] . ")</li>";
        }
        echo "</ul>";
        
        // Check if voucher 36264 exists
        $stmt = $reservationsPdo->prepare("SELECT COUNT(*) FROM aero_res_22 WHERE voucher = ?");
        $stmt->execute(['36264']);
        $count = $stmt->fetchColumn();
        
        echo "<p>Voucher 36264 count: $count</p>";
        
        if ($count > 0) {
                         $stmt = $reservationsPdo->prepare("SELECT voucher, supplier, agency, name, payment FROM aero_res_22 WHERE voucher = ?");
            $stmt->execute(['36264']);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo "<h3>Voucher 36264 Data:</h3>";
            echo "<pre>";
            print_r($row);
            echo "</pre>";
        }
        
    } else {
        echo "<p>❌ aero_res_22 table does not exist</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?>
