<?php
require_once 'auth/config.php';

try {
    echo "Testing Dashboard Data:\n\n";
    
    // Test database connection
    $pdo = getReservationsDB();
    echo "✅ Database connection successful\n";
    
    // Check if reservations table has data
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM zb_reservations");
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "Total reservations: " . $total . "\n";
    
    // Check if CPC column exists and has data
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM zb_reservations WHERE cpc IS NOT NULL AND cpc != '$0.00' AND cpc != '0.00'");
    $cpcTotal = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "CPC bookings: " . $cpcTotal . "\n";
    
    // Test the dashboard API directly
    echo "\nTesting Dashboard API:\n";
    $apiUrl = 'http://localhost/zimplerentals/dashboard/api/booking_stats.php';
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => 'Content-Type: application/json'
        ]
    ]);
    
    $response = file_get_contents($apiUrl, false, $context);
    if ($response === false) {
        echo "❌ Failed to access dashboard API\n";
    } else {
        echo "✅ Dashboard API response received\n";
        $data = json_decode($response, true);
        if ($data && isset($data['success']) && $data['success']) {
            echo "✅ API returned success\n";
            echo "Total bookings: " . $data['data']['total_bookings'] . "\n";
            echo "CPC bookings: " . $data['data']['cpc_stats']['bookings'] . "\n";
        } else {
            echo "❌ API returned error: " . ($data['error'] ?? 'Unknown error') . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>




