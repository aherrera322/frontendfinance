<?php
require_once 'auth/config.php';

try {
    $pdo = getReservationsDB();
    
    echo "<h2>Aerovision Reservations Table Check</h2>";
    
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'aerovision_reservations'");
    $tableExists = $stmt->rowCount() > 0;
    
    if ($tableExists) {
        echo "<p style='color: green;'>✅ Table 'aerovision_reservations' exists</p>";
        
        // Get table structure
        echo "<h3>Table Structure:</h3>";
        $stmt = $pdo->query("DESCRIBE aerovision_reservations");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
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
        }
        echo "</table>";
        
        // Check record count
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM aerovision_reservations");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo "<p><strong>Total Records:</strong> " . number_format($count) . "</p>";
        
        if ($count > 0) {
            // Show sample data
            echo "<h3>Sample Data (First 5 records):</h3>";
            $stmt = $pdo->query("SELECT * FROM aerovision_reservations LIMIT 5");
            $sampleData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($sampleData)) {
                echo "<table border='1' style='border-collapse: collapse; margin: 10px 0; font-size: 12px;'>";
                echo "<tr>";
                foreach (array_keys($sampleData[0]) as $header) {
                    echo "<th>" . htmlspecialchars($header) . "</th>";
                }
                echo "</tr>";
                
                foreach ($sampleData as $row) {
                    echo "<tr>";
                    foreach ($row as $value) {
                        echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
                    }
                    echo "</tr>";
                }
                echo "</table>";
            }
        } else {
            echo "<p style='color: orange;'>⚠️ Table exists but has no data</p>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ Table 'aerovision_reservations' does not exist</p>";
        
        // Show available tables
        echo "<h3>Available Tables:</h3>";
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>" . htmlspecialchars($table) . "</li>";
        }
        echo "</ul>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

<p><a href="dashboard_aerovision.php">Go to Aerovision Dashboard</a></p>
<p><a href="index.html">Back to Home</a></p>
