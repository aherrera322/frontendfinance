<?php
require_once 'auth/config.php';

try {
    $pdo = getReservationsDB();
    
    echo "<h2>App Day Column Format Check</h2>";
    
    // Check if app_day column exists
    $stmt = $pdo->query("DESCRIBE aerovision_reservations");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $appDayExists = false;
    
    echo "<h3>Table Structure:</h3>";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Default']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Extra']) . "</td>";
        echo "</tr>";
        
        if ($column['Field'] === 'app_day') {
            $appDayExists = true;
        }
    }
    echo "</table>";
    
    if (!$appDayExists) {
        echo "<p style='color: red;'>❌ app_day column does not exist!</p>";
        
        // Show all columns that might contain dates
        echo "<h3>Potential Date Columns:</h3>";
        foreach ($columns as $column) {
            if (stripos($column['Field'], 'date') !== false || 
                stripos($column['Field'], 'day') !== false ||
                stripos($column['Field'], 'app') !== false) {
                echo "<p><strong>" . htmlspecialchars($column['Field']) . "</strong> - " . htmlspecialchars($column['Type']) . "</p>";
            }
        }
        exit;
    }
    
    // Check sample app_day values
    echo "<h3>Sample App Day Values (First 10 records):</h3>";
    $stmt = $pdo->query("SELECT app_day FROM aerovision_reservations WHERE app_day IS NOT NULL AND app_day != '' LIMIT 10");
    $sampleDates = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($sampleDates)) {
        echo "<p style='color: orange;'>⚠️ No app_day values found in the table</p>";
    } else {
        echo "<ul>";
        foreach ($sampleDates as $date) {
            echo "<li>" . htmlspecialchars($date) . " (Type: " . gettype($date) . ")</li>";
        }
        echo "</ul>";
    }
    
    // Check date range in the data
    echo "<h3>Date Range in App Day Column:</h3>";
    $stmt = $pdo->query("SELECT MIN(app_day) as min_date, MAX(app_day) as max_date FROM aerovision_reservations WHERE app_day IS NOT NULL AND app_day != ''");
    $dateRange = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p><strong>Earliest Date:</strong> " . htmlspecialchars($dateRange['min_date']) . "</p>";
    echo "<p><strong>Latest Date:</strong> " . htmlspecialchars($dateRange['max_date']) . "</p>";
    
    // Test different date formats
    echo "<h3>Testing Date Format Recognition:</h3>";
    
    // Test if it's already in YYYY-MM-DD format
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM aerovision_reservations WHERE app_day REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "<p>Records with YYYY-MM-DD format: " . $result . "</p>";
    
    // Test if it's in DD/MM/YYYY format
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM aerovision_reservations WHERE app_day REGEXP '^[0-9]{1,2}/[0-9]{1,2}/[0-9]{4}$'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "<p>Records with DD/MM/YYYY format: " . $result . "</p>";
    
    // Test if it's in MM/DD/YYYY format
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM aerovision_reservations WHERE app_day REGEXP '^[0-9]{1,2}/[0-9]{1,2}/[0-9]{4}$'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "<p>Records with MM/DD/YYYY format: " . $result . "</p>";
    
    // Test if it's a numeric date (Excel format)
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM aerovision_reservations WHERE app_day REGEXP '^[0-9]+\.?[0-9]*$'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "<p>Records with numeric format: " . $result . "</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

<p><a href="dashboard_aerovision.php">Back to Aerovision Dashboard</a></p>



