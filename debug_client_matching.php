<?php
require_once 'auth/config.php';

try {
    // Get client commissions from clients database
    $clientCommissions = [];
    try {
        $clientsPdo = getClientsDB();
        $stmt = $clientsPdo->query("SELECT name, commission_percent_credit_card, commission_percent_credit_limit FROM clients WHERE status = 'active'");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $clientCommissions[$row['name']] = [
                'cc_percent' => $row['commission_percent_credit_card'] ?? 15.0,
                'credit_limit_percent' => $row['commission_percent_credit_limit'] ?? 15.0
            ];
        }
    } catch (Exception $e) {
        echo "Error accessing clients database: " . $e->getMessage();
    }
    
    // Get some sample suppliers from aero_res_22
    $reservationsPdo = getReservationsDB();
    $supplierStmt = $reservationsPdo->query("SELECT DISTINCT supplier FROM aero_res_22 WHERE supplier IS NOT NULL AND supplier != '' ORDER BY supplier LIMIT 10");
    $suppliers = $supplierStmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h2>Client Commissions in Database:</h2>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Client Name</th><th>CC %</th><th>Credit Limit %</th></tr>";
    foreach ($clientCommissions as $clientName => $commissions) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($clientName) . "</td>";
        echo "<td>" . ($commissions['cc_percent'] ?? 'N/A') . "</td>";
        echo "<td>" . ($commissions['credit_limit_percent'] ?? 'N/A') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h2>Sample Suppliers from aero_res_22:</h2>";
    echo "<ul>";
    foreach ($suppliers as $supplier) {
        echo "<li>" . htmlspecialchars($supplier) . "</li>";
    }
    echo "</ul>";
    
    echo "<h2>Matching Test:</h2>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Supplier</th><th>Matched Client</th><th>CC %</th><th>Credit Limit %</th></tr>";
    
    foreach ($suppliers as $supplier) {
        $foundClient = false;
        $matchedClient = '';
        $ccPercent = 15.0;
        $creditLimitPercent = 15.0;
        
        foreach ($clientCommissions as $clientName => $commissions) {
            if (strcasecmp(trim($clientName), trim($supplier)) === 0 || 
                stripos(trim($clientName), trim($supplier)) !== false || 
                stripos(trim($supplier), trim($clientName)) !== false) {
                $foundClient = true;
                $matchedClient = $clientName;
                $ccPercent = $commissions['cc_percent'] ?? 15.0;
                $creditLimitPercent = $commissions['credit_limit_percent'] ?? 15.0;
                break;
            }
        }
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars($supplier) . "</td>";
        echo "<td>" . ($foundClient ? htmlspecialchars($matchedClient) : '<strong>NO MATCH</strong>') . "</td>";
        echo "<td>" . $ccPercent . "</td>";
        echo "<td>" . $creditLimitPercent . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>



