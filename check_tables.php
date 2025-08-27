<?php
require_once 'auth/config.php';

try {
    $pdo = getReservationsDB();
    
    echo "<h2>Database Tables Check</h2>";
    
    // Get all tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h3>Available Tables:</h3>";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Table Name</th><th>Record Count</th></tr>";
    
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM `$table`");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo "<tr>";
        echo "<td>" . htmlspecialchars($table) . "</td>";
        echo "<td>" . $count . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check for tables with 'aero' in the name
    echo "<h3>Tables with 'aero' in name:</h3>";
    foreach ($tables as $table) {
        if (stripos($table, 'aero') !== false) {
            echo "<p><strong>" . htmlspecialchars($table) . "</strong></p>";
            
            // Show structure
            $stmt = $pdo->query("DESCRIBE `$table`");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "<ul>";
            foreach ($columns as $column) {
                echo "<li>" . htmlspecialchars($column['Field']) . " - " . htmlspecialchars($column['Type']) . "</li>";
            }
            echo "</ul>";
            
            // Show sample data
            $stmt = $pdo->query("SELECT * FROM `$table` LIMIT 3");
            $sampleData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($sampleData)) {
                echo "<p><strong>Sample data:</strong></p>";
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
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

<p><a href="dashboard_aerovision.php">Back to Aerovision Dashboard</a></p>

