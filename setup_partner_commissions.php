<?php
require_once 'auth/config.php';

try {
    $partnersPdo = getPartnersDB();
    
    // Define the partner groups and their commission rates
    $partnerGroups = [
        'Zimple Rentals' => 18.0,
        'Aerovision Inc' => 18.0,
        'Hertz Group' => 20.0,
        'Europcar' => 25.0
    ];
    
    echo "<h2>Setting up Partner Commission Rates</h2>\n";
    
    foreach ($partnerGroups as $partnerName => $commissionRate) {
        // Check if partner already exists
        $stmt = $partnersPdo->prepare("SELECT id, commission_percent FROM partners WHERE name = ?");
        $stmt->execute([$partnerName]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Update existing partner
            if ($existing['commission_percent'] != $commissionRate) {
                $stmt = $partnersPdo->prepare("UPDATE partners SET commission_percent = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$commissionRate, $existing['id']]);
                echo "<p>✅ Updated <strong>{$partnerName}</strong>: {$existing['commission_percent']}% → {$commissionRate}%</p>\n";
            } else {
                echo "<p>ℹ️ <strong>{$partnerName}</strong>: Already set to {$commissionRate}%</p>\n";
            }
        } else {
            // Create new partner
            $stmt = $partnersPdo->prepare("INSERT INTO partners (name, commission_percent, status) VALUES (?, ?, 'active')");
            $stmt->execute([$partnerName, $commissionRate]);
            echo "<p>✅ Created <strong>{$partnerName}</strong>: {$commissionRate}%</p>\n";
        }
    }
    
    echo "<h3>Current Partner Commission Rates:</h3>\n";
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
    echo "<li><strong>Zimple Rentals</strong> (18.0%): Alamo, National, Enterprise</li>\n";
    echo "<li><strong>Aerovision Inc</strong> (18.0%): Avis, Budget</li>\n";
    echo "<li><strong>Hertz Group</strong> (20.0%): Hertz, Dollar, Thrifty</li>\n";
    echo "<li><strong>Europcar</strong> (25.0%): Europcar</li>\n";
    echo "</ul>\n";
    
    echo "<p><strong>Note:</strong> The financial dashboard will now use these commission rates from the partners database instead of the hardcoded defaults.</p>\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
