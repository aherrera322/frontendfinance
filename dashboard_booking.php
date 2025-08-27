<?php
require_once 'auth/config.php';

// Get database connection
$pdo = getReservationsDB();

// Get current date for filtering
$currentDate = date('Y-m-d');
$currentMonth = date('Y-m');
$currentYear = date('Y');

// Get summary statistics
try {
    // Total reservations
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM zimpleb_reservations");
    $stmt->execute();
    $totalReservations = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Today's reservations
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM zimpleb_reservations WHERE DATE(imported_at) = ?");
    $stmt->execute([$currentDate]);
    $todayReservations = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total API Value
    $stmt = $pdo->prepare("SELECT SUM(api_value) as total FROM zimpleb_reservations WHERE api_value IS NOT NULL");
    $stmt->execute();
    $totalApiValue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Total Credit
    $stmt = $pdo->prepare("SELECT SUM(credit) as total FROM zimpleb_reservations WHERE credit IS NOT NULL");
    $stmt->execute();
    $totalCredit = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Approved reservations
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM zimpleb_reservations WHERE status = 'Approved'");
    $stmt->execute();
    $approvedReservations = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Pending reservations
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM zimpleb_reservations WHERE status = 'Pending'");
    $stmt->execute();
    $pendingReservations = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Top agencies by reservation count
    $stmt = $pdo->prepare("
        SELECT agency, COUNT(*) as count, SUM(api_value) as total_value 
        FROM zimpleb_reservations 
        WHERE agency IS NOT NULL 
        GROUP BY agency 
        ORDER BY count DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $topAgencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Monthly trends (last 12 months)
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(imported_at, '%Y-%m') as month,
            COUNT(*) as count,
            SUM(api_value) as total_value
        FROM zimpleb_reservations 
        WHERE imported_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(imported_at, '%Y-%m')
        ORDER BY month DESC
    ");
    $stmt->execute();
    $monthlyTrends = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Payment mode distribution
    $stmt = $pdo->prepare("
        SELECT pay_mode, COUNT(*) as count 
        FROM zimpleb_reservations 
        WHERE pay_mode IS NOT NULL 
        GROUP BY pay_mode 
        ORDER BY count DESC
    ");
    $stmt->execute();
    $paymentModes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Recent reservations
    $stmt = $pdo->prepare("
        SELECT * FROM zimpleb_reservations 
        ORDER BY imported_at DESC 
        LIMIT 20
    ");
    $stmt->execute();
    $recentReservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZimpleB Booking Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#117372'
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-primary text-white shadow-lg">
            <div class="container mx-auto px-6 py-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <i class="fas fa-chart-line text-2xl"></i>
                        <h1 class="text-2xl font-bold">ZimpleB Booking Dashboard</h1>
                    </div>
                    <nav class="flex items-center space-x-6">
                        <a href="index.html" class="hover:text-yellow-200 transition-colors">Home</a>
                        <a href="reports/index.html" class="hover:text-yellow-200 transition-colors">Reports</a>
                        <a href="dashboard.html" class="hover:text-yellow-200 transition-colors">User Dashboard</a>
                        <span class="text-sm text-yellow-200"><?php echo date('M j, Y'); ?></span>
                    </nav>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="container mx-auto px-6 py-8">
            <?php if (isset($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Key Metrics -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100">
                            <i class="fas fa-calendar-check text-blue-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Reservations</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($totalReservations); ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100">
                            <i class="fas fa-dollar-sign text-green-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total API Value</p>
                            <p class="text-2xl font-semibold text-gray-900">$<?php echo number_format($totalApiValue, 2); ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-100">
                            <i class="fas fa-credit-card text-yellow-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Credit</p>
                            <p class="text-2xl font-semibold text-gray-900">$<?php echo number_format($totalCredit, 2); ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100">
                            <i class="fas fa-clock text-purple-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Today's Reservations</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($todayReservations); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Overview -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Reservation Status</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Approved</span>
                            <span class="text-sm font-semibold text-green-600"><?php echo number_format($approvedReservations); ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Pending</span>
                            <span class="text-sm font-semibold text-yellow-600"><?php echo number_format($pendingReservations); ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Other</span>
                            <span class="text-sm font-semibold text-gray-600"><?php echo number_format($totalReservations - $approvedReservations - $pendingReservations); ?></span>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Payment Modes</h3>
                    <div class="space-y-3">
                        <?php foreach (array_slice($paymentModes, 0, 5) as $mode): ?>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600"><?php echo htmlspecialchars($mode['pay_mode']); ?></span>
                            <span class="text-sm font-semibold text-primary"><?php echo number_format($mode['count']); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Top Agencies</h3>
                    <div class="space-y-3">
                        <?php foreach (array_slice($topAgencies, 0, 5) as $agency): ?>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600 truncate"><?php echo htmlspecialchars($agency['agency']); ?></span>
                            <span class="text-sm font-semibold text-primary"><?php echo number_format($agency['count']); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Monthly Trends Chart -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Monthly Trends</h3>
                    <canvas id="monthlyChart" width="400" height="200"></canvas>
                </div>

                <!-- Payment Mode Chart -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Payment Mode Distribution</h3>
                    <canvas id="paymentChart" width="400" height="200"></canvas>
                </div>
            </div>

            <!-- Recent Reservations Table -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-900">Recent Reservations</h3>
                    <a href="reports/index.html" class="text-primary hover:text-primary/80 text-sm font-medium">View All</a>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Agency</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">API Value</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Credit</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Mode</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Imported</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($recentReservations as $reservation): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($reservation['agency'] ?? 'N/A'); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo $reservation['res_date'] ?? 'N/A'; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo $reservation['api_value'] ? '$' . number_format($reservation['api_value'], 2) : 'N/A'; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo $reservation['credit'] ? '$' . number_format($reservation['credit'], 2) : 'N/A'; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($reservation['pay_mode'] ?? 'N/A'); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo $reservation['status'] === 'Approved' ? 'bg-green-100 text-green-800' : 
                                              ($reservation['status'] === 'Pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800'); ?>">
                                        <?php echo htmlspecialchars($reservation['status'] ?? 'N/A'); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M j, Y H:i', strtotime($reservation['imported_at'])); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Monthly Trends Chart
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_reverse(array_column($monthlyTrends, 'month'))); ?>,
                datasets: [{
                    label: 'Reservations',
                    data: <?php echo json_encode(array_reverse(array_column($monthlyTrends, 'count'))); ?>,
                    borderColor: '#117372',
                    backgroundColor: 'rgba(17, 115, 114, 0.1)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Payment Mode Chart
        const paymentCtx = document.getElementById('paymentChart').getContext('2d');
        new Chart(paymentCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($paymentModes, 'pay_mode')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($paymentModes, 'count')); ?>,
                    backgroundColor: [
                        '#117372',
                        '#10B981',
                        '#F59E0B',
                        '#EF4444',
                        '#8B5CF6',
                        '#06B6D4',
                        '#84CC16',
                        '#F97316'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html>
