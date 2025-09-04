<?php
require_once 'auth/config.php';

try {
    $pdo = getReservationsDB();
    
    echo "<h2>Quick Aero Res 22 Check</h2>";
    
    // Check if table exists and get columns
    $stmt = $pdo->query("DESCRIBE aero_res_22");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    
    echo "<p><strong>Available columns in aero_res_22:</strong></p>";
    echo "<ul>";
    foreach ($columns as $column) {
        echo "<li>" . htmlspecialchars($column) . "</li>";
    }
    echo "</ul>";
    
    // Check total records
    $stmt = $pdo->query("SELECT COUNT(*) FROM aero_res_22");
    $total = $stmt->fetchColumn();
    echo "<p><strong>Total records:</strong> $total</p>";
    
    // Show first record to see data structure
    if ($total > 0) {
        $stmt = $pdo->query("SELECT * FROM aero_res_22 LIMIT 1");
        $firstRecord = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<p><strong>First record sample:</strong></p>";
        echo "<pre>";
        print_r($firstRecord);
        echo "</pre>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

<p><a href="dashboard_aerovision.php">Back to Dashboard</a></p>



