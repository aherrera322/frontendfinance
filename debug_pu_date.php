<?php
require_once 'auth/config.php';

try {
    $pdo = getReservationsDB();
    
    echo "<h2>PU Date Column Diagnostic</h2>";
    
    // Check current date
    $currentDate = date('Y-m-d');
    $endDate = date('Y-m-d', strtotime('+6 days'));
    echo "<p><strong>Current Date:</strong> $currentDate</p>";
    echo "<p><strong>End Date (7 days):</strong> $endDate</p>";
    
    // Check if pu_date column exists
    $stmt = $pdo->query("DESCRIBE aerovision_reservations");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $puDateExists = false;
    
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
        
        if ($column['Field'] === 'pu_date') {
            $puDateExists = true;
        }
    }
    echo "</table>";
    
    if (!$puDateExists) {
        echo "<p style='color: red;'>❌ pu_date column does not exist!</p>";
        exit;
    }
    
    // Check sample pu_date values
    echo "<h3>Sample PU Date Values (First 10 records):</h3>";
    $stmt = $pdo->query("SELECT pu_date FROM aerovision_reservations WHERE pu_date IS NOT NULL LIMIT 10");
    $sampleDates = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($sampleDates)) {
        echo "<p style='color: orange;'>⚠️ No pu_date values found in the table</p>";
    } else {
        echo "<ul>";
        foreach ($sampleDates as $date) {
            echo "<li>" . htmlspecialchars($date) . " (Type: " . gettype($date) . ")</li>";
        }
        echo "</ul>";
    }
    
    // Test the actual query
    echo "<h3>Testing Next 7 Days Query:</h3>";
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM aerovision_reservations WHERE DATE(pu_date) BETWEEN ? AND ?");
    $stmt->execute([$currentDate, $endDate]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "<p><strong>Query Result:</strong> $result</p>";
    
    // Test without DATE() function
    echo "<h3>Testing Without DATE() Function:</h3>";
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM aerovision_reservations WHERE pu_date BETWEEN ? AND ?");
    $stmt->execute([$currentDate, $endDate]);
    $result2 = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "<p><strong>Query Result (no DATE()):</strong> $result2</p>";
    
    // Check for any future dates
    echo "<h3>Future Dates in PU Date:</h3>";
    $stmt = $pdo->query("SELECT pu_date FROM aerovision_reservations WHERE pu_date > '$currentDate' ORDER BY pu_date LIMIT 10");
    $futureDates = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($futureDates)) {
        echo "<p style='color: orange;'>⚠️ No future dates found in pu_date column</p>";
    } else {
        echo "<ul>";
        foreach ($futureDates as $date) {
            echo "<li>" . htmlspecialchars($date) . "</li>";
        }
        echo "</ul>";
    }
    
    // Check date range in the data
    echo "<h3>Date Range in PU Date Column:</h3>";
    $stmt = $pdo->query("SELECT MIN(pu_date) as min_date, MAX(pu_date) as max_date FROM aerovision_reservations WHERE pu_date IS NOT NULL");
    $dateRange = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p><strong>Earliest Date:</strong> " . htmlspecialchars($dateRange['min_date']) . "</p>";
    echo "<p><strong>Latest Date:</strong> " . htmlspecialchars($dateRange['max_date']) . "</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

<p><a href="dashboard_aerovision.php">Back to Aerovision Dashboard</a></p>



