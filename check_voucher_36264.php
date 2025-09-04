<?php
require_once 'auth/config.php';

try {
    $reservationsPdo = getReservationsDB();
    
    // Get the specific voucher 36264
    $stmt = $reservationsPdo->prepare("SELECT * FROM aero_res_22 WHERE voucher = ?");
    $stmt->execute(['36264']);
    $voucher = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($voucher) {
        echo "<h2>Voucher 36264 Details:</h2>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Field</th><th>Value</th></tr>";
        foreach ($voucher as $field => $value) {
            echo "<tr>";
            echo "<td><strong>" . htmlspecialchars($field) . "</strong></td>";
            echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Also get a few more vouchers to see patterns
        echo "<h2>Sample of Other Vouchers:</h2>";
        $stmt = $reservationsPdo->query("SELECT voucher, supplier, name, agency, company, client FROM aero_res_22 WHERE voucher != '36264' LIMIT 5");
        $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($samples)) {
            echo "<table border='1' style='border-collapse: collapse;'>";
            echo "<tr><th>Voucher</th><th>Supplier</th><th>Name</th><th>Agency</th><th>Company</th><th>Client</th></tr>";
            foreach ($samples as $sample) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($sample['voucher'] ?? '') . "</td>";
                echo "<td>" . htmlspecialchars($sample['supplier'] ?? '') . "</td>";
                echo "<td>" . htmlspecialchars($sample['name'] ?? '') . "</td>";
                echo "<td>" . htmlspecialchars($sample['agency'] ?? '') . "</td>";
                echo "<td>" . htmlspecialchars($sample['company'] ?? '') . "</td>";
                echo "<td>" . htmlspecialchars($sample['client'] ?? '') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } else {
        echo "<p>Voucher 36264 not found.</p>";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>



