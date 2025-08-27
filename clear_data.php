<?php
require_once 'auth/config.php';

try {
    $pdo = getReservationsDB();
    $stmt = $pdo->prepare("DELETE FROM zbcom_reservations WHERE source_id = 4");
    $stmt->execute();
    $deletedCount = $stmt->rowCount();
    echo "✅ Cleared $deletedCount existing records from zbcom_reservations for source_id 4\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
