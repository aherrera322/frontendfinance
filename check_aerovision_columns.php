<?php
require_once 'auth/config.php';

try {
    $pdo = getReservationsDB();
    
    echo "<h2>Aerovision Reservations Table Columns</h2>";
    
    // Get all columns
    $stmt = $pdo->query("DESCRIBE aerovision_reservations");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>All Columns:</h3>";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Column Name</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    $dateColumns = [];
    
    foreach ($columns as $column) {
        $columnName = $column['Field'];
        $columnType = $column['Type'];
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars($columnName) . "</td>";
        echo "<td>" . htmlspecialchars($columnType) . "</td>";
        echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Default']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Extra']) . "</td>";
        echo "</tr>";
        
        // Check if this looks like a date column
        if (stripos($columnType, 'date') !== false || 
            stripos($columnName, 'date') !== false ||
            stripos($columnName, 'pu') !== false ||
            stripos($columnName, 'pickup') !== false) {
            $dateColumns[] = $columnName;
        }
    }
    echo "</table>";
    
    if (!empty($dateColumns)) {
        echo "<h3>Potential Date Columns:</h3>";
        echo "<ul>";
        foreach ($dateColumns as $dateCol) {
            echo "<li><strong>" . htmlspecialchars($dateCol) . "</strong></li>";
        }
        echo "</ul>";
        
        // Check sample data for each date column
        foreach ($dateColumns as $dateCol) {
            echo "<h4>Sample data for column: " . htmlspecialchars($dateCol) . "</h4>";
            $stmt = $pdo->prepare("SELECT $dateCol FROM aerovision_reservations WHERE $dateCol IS NOT NULL AND $dateCol != '' LIMIT 5");
            $stmt->execute();
            $sampleData = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (empty($sampleData)) {
                echo "<p style='color: orange;'>No data found</p>";
            } else {
                echo "<ul>";
                foreach ($sampleData as $value) {
                    echo "<li>" . htmlspecialchars($value) . "</li>";
                }
                echo "</ul>";
            }
        }
    }
    
    // Check total records
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM aerovision_reservations");
    $totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "<h3>Total Records: " . number_format($totalRecords) . "</h3>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

<p><a href="dashboard_aerovision.php">Back to Aerovision Dashboard</a></p>

