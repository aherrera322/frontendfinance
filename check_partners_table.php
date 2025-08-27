<?php
require_once 'auth/config.php';

try {
    $partnersPdo = getPartnersDB();
    
    echo "<h2>Partners Table Structure</h2>\n";
    $stmt = $partnersPdo->query("DESCRIBE partners");
    echo "<table border='1' style='border-collapse: collapse;'>\n";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    echo "<h2>Existing Partners Data</h2>\n";
    $stmt = $partnersPdo->query("SELECT COUNT(*) as count FROM partners");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "<p>Total partners in database: " . $count . "</p>\n";
    
    if ($count > 0) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
        echo "<tr><th>ID</th><th>Name</th><th>Commission %</th><th>Status</th><th>Created</th></tr>\n";
        
        $stmt = $partnersPdo->query("SELECT id, name, commission_percent, status, created_at FROM partners ORDER BY name ASC");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . htmlspecialchars($row['name']) . "</td>";
            echo "<td>" . number_format($row['commission_percent'], 2) . "%</td>";
            echo "<td>" . htmlspecialchars($row['status']) . "</td>";
            echo "<td>" . $row['created_at'] . "</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>

