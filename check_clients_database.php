<?php
require_once 'auth/config.php';

try {
    $clientsPdo = getClientsDB();
    
    // Get all active clients
    $stmt = $clientsPdo->query("SELECT name, commission_percent_credit_card, commission_percent_credit_limit, status FROM clients WHERE status = 'active' ORDER BY name");
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Active Clients in Database:</h2>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Client/Agency Name</th><th>CC %</th><th>Credit Limit %</th><th>Status</th></tr>";
    foreach ($clients as $client) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($client['name']) . "</td>";
        echo "<td>" . ($client['commission_percent_credit_card'] ?? 'N/A') . "</td>";
        echo "<td>" . ($client['commission_percent_credit_limit'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($client['status']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Also check for any clients with 0% commission
    $stmt = $clientsPdo->query("SELECT name, commission_percent_credit_card, commission_percent_credit_limit FROM clients WHERE (commission_percent_credit_card = 0 OR commission_percent_credit_limit = 0) AND status = 'active'");
    $zeroCommissionClients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($zeroCommissionClients)) {
        echo "<h2>Clients with 0% Commission:</h2>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Client/Agency Name</th><th>CC %</th><th>Credit Limit %</th></tr>";
        foreach ($zeroCommissionClients as $client) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($client['name']) . "</td>";
            echo "<td>" . ($client['commission_percent_credit_card'] ?? 'N/A') . "</td>";
            echo "<td>" . ($client['commission_percent_credit_limit'] ?? 'N/A') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>

