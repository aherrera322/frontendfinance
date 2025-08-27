<?php
require_once 'auth/config.php';

try {
    $pdo = getReservationsDB();
    
    echo "=== ZB.com Reservations Data Check ===\n\n";
    
    // Check total records
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM zbcom_reservations WHERE source_id = 4");
    $stmt->execute();
    $totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Total records: " . number_format($totalRecords) . "\n";
    
    // Check API Value totals
    $stmt = $pdo->prepare("SELECT SUM(api_value) as total FROM zbcom_reservations WHERE source_id = 4 AND api_value IS NOT NULL");
    $stmt->execute();
    $totalApiValue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    echo "Total API Value: $" . number_format($totalApiValue, 2) . "\n";
    
    // Check Credit totals
    $stmt = $pdo->prepare("SELECT SUM(credit) as total FROM zbcom_reservations WHERE source_id = 4 AND credit IS NOT NULL");
    $stmt->execute();
    $totalCredit = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    echo "Total Credit: $" . number_format($totalCredit, 2) . "\n";
    
    // Check status distribution
    $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM zbcom_reservations WHERE source_id = 4 GROUP BY status ORDER BY count DESC");
    $stmt->execute();
    $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\nStatus Distribution:\n";
    foreach ($statuses as $status) {
        echo "  " . ($status['status'] ?? 'NULL') . ": " . number_format($status['count']) . "\n";
    }
    
    // Check sample data
    $stmt = $pdo->prepare("SELECT agency, api_value, credit, status FROM zbcom_reservations WHERE source_id = 4 ORDER BY imported_at DESC LIMIT 5");
    $stmt->execute();
    $sampleData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\nSample Data (latest 5 records):\n";
    foreach ($sampleData as $row) {
        echo "  Agency: " . ($row['agency'] ?? 'N/A') . 
             ", API Value: $" . number_format($row['api_value'] ?? 0, 2) . 
             ", Credit: $" . number_format($row['credit'] ?? 0, 2) . 
             ", Status: " . ($row['status'] ?? 'N/A') . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
