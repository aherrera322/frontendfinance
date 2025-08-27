<?php
require_once 'auth/config.php';

try {
    $reservationsPdo = getReservationsDB();
    
         // Simple query to get voucher 36264
     $stmt = $reservationsPdo->prepare("SELECT voucher, supplier, agency, name, value, discount, admin_fee, cpc, payment FROM aero_res_22 WHERE voucher = ?");
    $stmt->execute(['36264']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row) {
        echo "<h2>Voucher 36264 Test</h2>";
        echo "<pre>";
        print_r($row);
        echo "</pre>";
        
                 // Test agency matching
         $agencyName = $row['agency'] ?? $row['name'] ?? '';
        echo "<h3>Agency Name: '$agencyName'</h3>";
        
        // Test payment detection
        $paymentMethod = strtoupper(trim($row['payment'] ?? ''));
        $isCreditCard = $paymentMethod === 'CC' || stripos($paymentMethod, 'CREDIT') !== false || stripos($paymentMethod, 'CARD') !== false;
        echo "<h3>Payment Method: '$paymentMethod' (Is Credit Card: " . ($isCreditCard ? 'YES' : 'NO') . ")</h3>";
        
        // Test client commission lookup
        $clientsPdo = getClientsDB();
        $stmt = $clientsPdo->query("SELECT name, commission_percent_credit_card, commission_percent_credit_limit FROM clients WHERE status = 'active'");
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Available Clients:</h3>";
        foreach ($clients as $client) {
            echo "<p>" . htmlspecialchars($client['name']) . " - CC: " . ($client['commission_percent_credit_card'] ?? 'N/A') . "%, CL: " . ($client['commission_percent_credit_limit'] ?? 'N/A') . "%</p>";
        }
        
        // Test matching
        $foundClient = false;
        $clientCommission = 15.0;
        
        foreach ($clients as $client) {
            $clientName = $client['name'];
            if (strcasecmp(trim($clientName), trim($agencyName)) === 0 || 
                stripos(trim($clientName), trim($agencyName)) !== false || 
                stripos(trim($agencyName), trim($clientName)) !== false) {
                $foundClient = true;
                $clientCommission = $isCreditCard ? ($client['commission_percent_credit_card'] ?? 15.0) : ($client['commission_percent_credit_limit'] ?? 15.0);
                echo "<h3>✅ MATCH FOUND!</h3>";
                echo "<p>Client: " . htmlspecialchars($clientName) . "</p>";
                echo "<p>Commission: " . $clientCommission . "%</p>";
                break;
            }
        }
        
        if (!$foundClient) {
            echo "<h3>❌ NO MATCH FOUND - Using default 15%</h3>";
        }
        
        // Test calculation
        $value = $row['value'] ?? 0;
        $discount = $row['discount'] ?? 0;
        $adminFee = $row['admin_fee'] ?? 0;
        $cpc = $row['cpc'] ?? 0;
        
        // Partner commission (Alamo = Zimple Rentals = 18%)
        $partnerCommission = 18.0;
        $partnerAmount = $value * ($partnerCommission / 100);
        
        // Client commission
        $clientAmount = $value * ($clientCommission / 100);
        
        // Calculate total
        $baseTotal = $partnerAmount - $clientAmount - $discount + $adminFee + $cpc;
        $calculatedTotal = $isCreditCard ? $baseTotal * 1.05 : $baseTotal;
        
        echo "<h3>Calculation:</h3>";
        echo "<p>Value: $" . number_format($value, 2) . "</p>";
        echo "<p>Partner Amount (18%): $" . number_format($partnerAmount, 2) . "</p>";
        echo "<p>Client Amount (" . $clientCommission . "%): $" . number_format($clientAmount, 2) . "</p>";
        echo "<p>Discount: $" . number_format($discount, 2) . "</p>";
        echo "<p>Admin Fee: $" . number_format($adminFee, 2) . "</p>";
        echo "<p>CPC: $" . number_format($cpc, 2) . "</p>";
        echo "<p>Base Total: $" . number_format($baseTotal, 2) . "</p>";
        echo "<p>Final Total: $" . number_format($calculatedTotal, 2) . " " . ($isCreditCard ? "(+5% CC)" : "") . "</p>";
        
    } else {
        echo "<p>Voucher 36264 not found.</p>";
    }
    
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>
