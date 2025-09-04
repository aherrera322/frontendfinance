<?php
require_once 'auth/config.php';

try {
    $pdo = getReservationsDB();
    
    echo "<h2>Aero Res 22 Table Check</h2>";
    
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'aero_res_22'");
    $tableExists = $stmt->rowCount() > 0;
    
    if (!$tableExists) {
        echo "<p style='color: red;'>❌ Table 'aero_res_22' does not exist!</p>";
        exit;
    }
    
    echo "<p style='color: green;'>✅ Table 'aero_res_22' exists</p>";
    
    // Get table structure
    $stmt = $pdo->query("DESCRIBE aero_res_22");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Table Structure:</h3>";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Default']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Get total records
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM aero_res_22");
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "<p><strong>Total records:</strong> " . $total . "</p>";
    
    if ($total == 0) {
        echo "<p style='color: orange;'>⚠️ No data found in aero_res_22 table!</p>";
        exit;
    }
    
    // Show sample data
    echo "<h3>Sample Data (First 3 records):</h3>";
    $stmt = $pdo->query("SELECT * FROM aero_res_22 LIMIT 3");
    $sampleData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($sampleData)) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr>";
        foreach (array_keys($sampleData[0]) as $column) {
            echo "<th>" . htmlspecialchars($column) . "</th>";
        }
        echo "</tr>";
        
        foreach ($sampleData as $row) {
            echo "<tr>";
            foreach ($row as $value) {
                echo "<td>" . htmlspecialchars($value) . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Check for specific columns we need
    $columnNames = array_column($columns, 'Field');
    $neededColumns = ['value', 'discount', 'prepay', 'payment', 'agency', 'car_class', 'days', 'app_day'];
    $dateColumns = ['pu_date', 'pickup_date', 'dropoff_date', 'app_day', 'reservation_date'];
    
    echo "<h3>Column Availability Check:</h3>";
    echo "<ul>";
    foreach ($neededColumns as $col) {
        if (in_array($col, $columnNames)) {
            echo "<li style='color: green;'>✅ $col - Available</li>";
        } else {
            echo "<li style='color: red;'>❌ $col - Missing</li>";
        }
    }
    echo "</ul>";
    
    echo "<h3>Date Columns Found:</h3>";
    echo "<ul>";
    foreach ($dateColumns as $col) {
        if (in_array($col, $columnNames)) {
            echo "<li style='color: green;'>✅ $col - Available</li>";
            
            // Show sample values for date columns
            $stmt = $pdo->prepare("SELECT $col FROM aero_res_22 WHERE $col IS NOT NULL AND $col != '' LIMIT 5");
            $stmt->execute();
            $sampleDates = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($sampleDates)) {
                echo "<ul>";
                foreach ($sampleDates as $date) {
                    echo "<li style='color: blue;'>Sample: " . htmlspecialchars($date) . "</li>";
                }
                echo "</ul>";
            }
        } else {
            echo "<li style='color: red;'>❌ $col - Missing</li>";
        }
    }
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

<p><a href="dashboard_aerovision.php">Back to Aerovision Dashboard</a></p>



