<?php
require_once 'auth/config.php';

try {
    $pdo = getReservationsDB();
    
    echo "<h2>Aerovision Data Test</h2>";
    
    // Check total records
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM aerovision_reservations");
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "<p><strong>Total records in table:</strong> " . $total . "</p>";
    
    if ($total == 0) {
        echo "<p style='color: red;'>❌ No data found in aerovision_reservations table!</p>";
        exit;
    }
    
    // Check if app_day column exists
    $stmt = $pdo->query("DESCRIBE aerovision_reservations");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $appDayExists = false;
    
    echo "<h3>Table Columns:</h3>";
    echo "<ul>";
    foreach ($columns as $column) {
        echo "<li>" . htmlspecialchars($column['Field']) . " - " . htmlspecialchars($column['Type']) . "</li>";
        if ($column['Field'] === 'app_day') {
            $appDayExists = true;
        }
    }
    echo "</ul>";
    
    if (!$appDayExists) {
        echo "<p style='color: red;'>❌ app_day column does not exist!</p>";
        
        // Show sample data to see what columns we have
        echo "<h3>Sample Data (First 3 records):</h3>";
        $stmt = $pdo->query("SELECT * FROM aerovision_reservations LIMIT 3");
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
    
    // Test the date filter logic
    echo "<h3>Testing Date Filter Logic:</h3>";
    
    $fromDate = '2025-07-01';
    $untilDate = '2025-07-31';
    
    // Test the complex WHERE clause
    $dateFilter = "WHERE (
        (app_day REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$' AND app_day BETWEEN ? AND ?) OR
        (app_day REGEXP '^[0-9]{1,2}/[0-9]{1,2}/[0-9]{4}$' AND STR_TO_DATE(app_day, '%d/%m/%Y') BETWEEN ? AND ?) OR
        (app_day REGEXP '^[0-9]{1,2}/[0-9]{1,2}/[0-9]{4}$' AND STR_TO_DATE(app_day, '%m/%d/%Y') BETWEEN ? AND ?) OR
        (app_day REGEXP '^[0-9]+\.?[0-9]*$' AND DATE(FROM_UNIXTIME((app_day - 25569) * 86400)) BETWEEN ? AND ?)
    )";
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM aerovision_reservations " . $dateFilter);
    $params = [$fromDate, $untilDate, $fromDate, $untilDate, $fromDate, $untilDate, $fromDate, $untilDate];
    $stmt->execute($params);
    $filteredCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo "<p>Records matching date filter (July 1-31, 2025): " . $filteredCount . "</p>";
    
    // Test without any filter
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM aerovision_reservations");
    $totalCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "<p>Total records without filter: " . $totalCount . "</p>";
    
    // Test simple value > 0 filter
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM aerovision_reservations WHERE value > 0");
    $valueCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "<p>Records with value > 0: " . $valueCount . "</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

<p><a href="dashboard_aerovision.php">Back to Aerovision Dashboard</a></p>



