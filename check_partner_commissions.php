<?php
require_once 'auth/config.php';

try {
    $partnersPdo = getPartnersDB();
    
    echo "<h2>Current Partner Commission Rates</h2>\n";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr><th>Partner Name</th><th>Commission %</th><th>Status</th></tr>\n";
    
    $stmt = $partnersPdo->query("SELECT name, commission_percent, status FROM partners ORDER BY name ASC");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['name']) . "</td>";
        echo "<td>" . number_format($row['commission_percent'], 2) . "%</td>";
        echo "<td>" . htmlspecialchars($row['status']) . "</td>";
        echo "</tr>\n";
    }
    
    echo "</table>\n";
    
    echo "<h3>Partner Groups in Financial Dashboard:</h3>\n";
    echo "<ul>\n";
    echo "<li><strong>Zimple Rentals</strong>: Alamo, National, Enterprise</li>\n";
    echo "<li><strong>Aerovision Inc</strong>: Avis, Budget</li>\n";
    echo "<li><strong>Hertz Group</strong>: Hertz, Dollar, Thrifty</li>\n";
    echo "<li><strong>Europcar</strong>: Europcar</li>\n";
    echo "</ul>\n";
    
    echo "<h3>Current Default Commission Rates in Financial Dashboard:</h3>\n";
    echo "<ul>\n";
    echo "<li><strong>Zimple Rentals</strong>: 18.0%</li>\n";
    echo "<li><strong>Aerovision Inc</strong>: 18.0%</li>\n";
    echo "<li><strong>Hertz Group</strong>: 20.0%</li>\n";
    echo "<li><strong>Europcar</strong>: 25.0%</li>\n";
    echo "</ul>\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
