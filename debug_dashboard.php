<?php
require_once 'auth/config.php';

try {
    $pdo = getReservationsDB();
    
    // Quick checks
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM zbcom_reservations WHERE source_id = 4");
    $stmt->execute();
    $totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $pdo->prepare("SELECT SUM(api_value) as total FROM zbcom_reservations WHERE source_id = 4 AND api_value IS NOT NULL");
    $stmt->execute();
    $totalApiValue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    $stmt = $pdo->prepare("SELECT SUM(credit) as total FROM zbcom_reservations WHERE source_id = 4 AND credit IS NOT NULL");
    $stmt->execute();
    $totalCredit = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM zbcom_reservations WHERE source_id = 4 GROUP BY status ORDER BY count DESC");
    $stmt->execute();
    $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold mb-6">Database Debug Info</h1>
        
        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                Error: <?php echo htmlspecialchars($error); ?>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold mb-2">Total Records</h3>
                    <p class="text-3xl font-bold text-blue-600"><?php echo number_format($totalRecords); ?></p>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold mb-2">Total API Value</h3>
                    <p class="text-3xl font-bold text-green-600">$<?php echo number_format($totalApiValue, 2); ?></p>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold mb-2">Total Credit</h3>
                    <p class="text-3xl font-bold text-yellow-600">$<?php echo number_format($totalCredit, 2); ?></p>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4">Status Distribution</h3>
                <div class="space-y-2">
                    <?php foreach ($statuses as $status): ?>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600"><?php echo htmlspecialchars($status['status'] ?? 'NULL'); ?></span>
                        <span class="font-semibold"><?php echo number_format($status['count']); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="mt-6">
                <a href="dashboard_booking.php" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    Back to Booking Dashboard
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
