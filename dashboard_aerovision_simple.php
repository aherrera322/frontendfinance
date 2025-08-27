<?php
require_once 'auth/config.php';

// Get database connection
$pdo = getReservationsDB();

// Handle date range filtering
$fromDate = $_GET['from_date'] ?? '';
$untilDate = $_GET['until_date'] ?? '';

// Build WHERE clause for date filtering - try different date columns
$dateFilter = '';
$params = [];

if (!empty($fromDate) && !empty($untilDate)) {
    // Try app_day first, then other date columns
    $dateFilter = "WHERE (app_day BETWEEN ? AND ?) OR (pickup_date BETWEEN ? AND ?) OR (dropoff_date BETWEEN ? AND ?)";
    $params = [$fromDate, $untilDate, $fromDate, $untilDate, $fromDate, $untilDate];
} elseif (!empty($fromDate)) {
    $dateFilter = "WHERE (app_day >= ?) OR (pickup_date >= ?) OR (dropoff_date >= ?)";
    $params = [$fromDate, $fromDate, $fromDate];
} elseif (!empty($untilDate)) {
    $dateFilter = "WHERE (app_day <= ?) OR (pickup_date <= ?) OR (dropoff_date <= ?)";
    $params = [$untilDate, $untilDate, $untilDate];
}

// Get summary statistics with error handling
try {
    // Total reservations
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM aero_res_22 " . $dateFilter);
    $stmt->execute($params);
    $totalReservations = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total Value - try different column names
    $valueQuery = "SELECT SUM(value) as total FROM aero_res_22 " . $dateFilter;
    try {
        $stmt = $pdo->prepare($valueQuery);
        $stmt->execute($params);
        $totalValue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    } catch (Exception $e) {
        $totalValue = 0;
    }
    
    // Total Discount - try different column names
    $discountQuery = "SELECT SUM(discount) as total FROM aero_res_22 " . $dateFilter;
    try {
        $stmt = $pdo->prepare($discountQuery);
        $stmt->execute($params);
        $totalDiscount = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    } catch (Exception $e) {
        $totalDiscount = 0;
    }
    
    // Pick up - Next 7 days using pickup_date column
    $currentDate = date('Y-m-d');
    $endDate = date('Y-m-d', strtotime('+6 days'));
    $next7DaysBookings = 0;
    
    try {
        $puDateFilter = !empty($dateFilter) ? "AND pickup_date BETWEEN ? AND ?" : "WHERE pickup_date BETWEEN ? AND ?";
        $puParams = array_merge($params, [$currentDate, $endDate]);
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM aero_res_22 " . $dateFilter . " " . $puDateFilter);
        $stmt->execute($puParams);
        $next7DaysBookings = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    } catch (Exception $e) {
        $next7DaysBookings = 0;
    }
    
    // Prepay reservations
    $prepayReservations = 0;
    try {
        $prepayFilter = !empty($dateFilter) ? "AND prepay = 'yes'" : "WHERE prepay = 'yes'";
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM aero_res_22 " . $dateFilter . " " . $prepayFilter);
        $stmt->execute($params);
        $prepayReservations = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    } catch (Exception $e) {
        $prepayReservations = 0;
    }
    
    // POD reservations
    $podReservations = 0;
    try {
        $podFilter = !empty($dateFilter) ? "AND prepay = 'no'" : "WHERE prepay = 'no'";
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM aero_res_22 " . $dateFilter . " " . $podFilter);
        $stmt->execute($params);
        $podReservations = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    } catch (Exception $e) {
        $podReservations = 0;
    }
    
    // Top agencies
    $topAgencies = [];
    try {
        $agencyFilter = !empty($dateFilter) ? "AND agency IS NOT NULL" : "WHERE agency IS NOT NULL";
        $stmt = $pdo->prepare("
            SELECT agency, COUNT(*) as count 
            FROM aero_res_22 
            " . $dateFilter . " " . $agencyFilter . "
            GROUP BY agency 
            ORDER BY count DESC 
            LIMIT 10
        ");
        $stmt->execute($params);
        $topAgencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $topAgencies = [];
    }
    
    // Top car classes
    $topCarClasses = [];
    try {
        $carClassFilter = !empty($dateFilter) ? "AND car_class IS NOT NULL" : "WHERE car_class IS NOT NULL";
        $stmt = $pdo->prepare("
            SELECT car_class, COUNT(*) as count 
            FROM aero_res_22 
            " . $dateFilter . " " . $carClassFilter . "
            GROUP BY car_class 
            ORDER BY count DESC 
            LIMIT 10
        ");
        $stmt->execute($params);
        $topCarClasses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $topCarClasses = [];
    }
    
    // Payment modes
    $paymentModes = [];
    try {
        $paymentFilter = !empty($dateFilter) ? "AND payment IS NOT NULL" : "WHERE payment IS NOT NULL";
        $stmt = $pdo->prepare("
            SELECT payment, COUNT(*) as count 
            FROM aero_res_22 
            " . $dateFilter . " " . $paymentFilter . "
            GROUP BY payment 
            ORDER BY count DESC
        ");
        $stmt->execute($params);
        $paymentModes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $paymentModes = [];
    }
    
    // Average calculations
    $avgDays = 0;
    $avgRPD = 0;
    $avgRPR = 0;
    
    try {
        $daysFilter = !empty($dateFilter) ? "AND days IS NOT NULL AND days > 0" : "WHERE days IS NOT NULL AND days > 0";
        $stmt = $pdo->prepare("SELECT AVG(days) as avg_days FROM aero_res_22 " . $dateFilter . " " . $daysFilter);
        $stmt->execute($params);
        $avgDays = $stmt->fetch(PDO::FETCH_ASSOC)['avg_days'] ?? 0;
        
        $rpdFilter = !empty($dateFilter) ? "AND value IS NOT NULL AND days IS NOT NULL AND days > 0" : "WHERE value IS NOT NULL AND days IS NOT NULL AND days > 0";
        $stmt = $pdo->prepare("SELECT SUM(value) / SUM(days) as avg_rpd FROM aero_res_22 " . $dateFilter . " " . $rpdFilter);
        $stmt->execute($params);
        $avgRPD = $stmt->fetch(PDO::FETCH_ASSOC)['avg_rpd'] ?? 0;
        
        $rprFilter = !empty($dateFilter) ? "AND value IS NOT NULL" : "WHERE value IS NOT NULL";
        $stmt = $pdo->prepare("SELECT SUM(value) / COUNT(*) as avg_rpr FROM aero_res_22 " . $dateFilter . " " . $rprFilter);
        $stmt->execute($params);
        $avgRPR = $stmt->fetch(PDO::FETCH_ASSOC)['avg_rpr'] ?? 0;
    } catch (Exception $e) {
        // Keep default values
    }
    
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aerovision Booking Dashboard</title>
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
                        <i class="fas fa-plane text-2xl"></i>
                        <h1 class="text-2xl font-bold">Aerovision Booking Dashboard</h1>
                    </div>
                    <nav class="flex items-center space-x-6">
                        <a href="index.html" class="hover:text-yellow-200 transition-colors">Home</a>
                        <a href="reports/index.html" class="hover:text-yellow-200 transition-colors">Reports</a>
                        <a href="dashboard_booking.php" class="hover:text-yellow-200 transition-colors">ZimpleB Dashboard</a>
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

            <!-- Date Range Filter -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Filter by Date Range</h3>
                <form method="GET" class="flex flex-wrap gap-4 items-end">
                    <div>
                        <label for="from_date" class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                        <input type="date" id="from_date" name="from_date" value="<?php echo htmlspecialchars($fromDate); ?>" 
                               class="border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div>
                        <label for="until_date" class="block text-sm font-medium text-gray-700 mb-1">Until Date</label>
                        <input type="date" id="until_date" name="until_date" value="<?php echo htmlspecialchars($untilDate); ?>" 
                               class="border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="bg-primary text-white px-4 py-2 rounded-md hover:bg-primary/90 transition-colors">
                            Apply Filter
                        </button>
                        <a href="dashboard_aerovision_simple.php" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition-colors">
                            Clear Filter
                        </a>
                    </div>
                </form>
                <?php if (!empty($fromDate) || !empty($untilDate)): ?>
                    <div class="mt-3 text-sm text-gray-600">
                        <strong>Active Filter:</strong> 
                        <?php if (!empty($fromDate) && !empty($untilDate)): ?>
                            From <?php echo date('M j, Y', strtotime($fromDate)); ?> to <?php echo date('M j, Y', strtotime($untilDate)); ?>
                        <?php elseif (!empty($fromDate)): ?>
                            From <?php echo date('M j, Y', strtotime($fromDate)); ?>
                        <?php elseif (!empty($untilDate)): ?>
                            Until <?php echo date('M j, Y', strtotime($untilDate)); ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Key Metrics -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100">
                            <i class="fas fa-plane-departure text-blue-600 text-xl"></i>
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
                            <p class="text-sm font-medium text-gray-600">Total Value</p>
                            <p class="text-2xl font-semibold text-gray-900">$<?php echo number_format($totalValue, 2); ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-100">
                            <i class="fas fa-credit-card text-yellow-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Discount</p>
                            <p class="text-2xl font-semibold text-gray-900">$<?php echo number_format($totalDiscount, 2); ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100">
                            <i class="fas fa-calendar-alt text-purple-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Pick Up - Next 7 Days</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($next7DaysBookings); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Overview -->
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Payment Type</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Prepay</span>
                            <span class="text-sm font-semibold text-green-600"><?php echo number_format($prepayReservations); ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">POD</span>
                            <span class="text-sm font-semibold text-yellow-600"><?php echo number_format($podReservations); ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Other</span>
                            <span class="text-sm font-semibold text-gray-600"><?php echo number_format($totalReservations - $prepayReservations - $podReservations); ?></span>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Payment Modes</h3>
                    <div class="space-y-3">
                        <?php if (!empty($paymentModes)): ?>
                            <?php foreach (array_slice($paymentModes, 0, 5) as $mode): ?>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600"><?php echo htmlspecialchars($mode['payment']); ?></span>
                                    <span class="text-sm font-semibold text-primary"><?php echo number_format($mode['count']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-sm text-gray-500">No payment data available</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Top Car Classes</h3>
                    <div class="space-y-3">
                        <?php if (!empty($topCarClasses)): ?>
                            <?php foreach (array_slice($topCarClasses, 0, 5) as $carClass): ?>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600 truncate"><?php echo htmlspecialchars($carClass['car_class']); ?></span>
                                    <span class="text-sm font-semibold text-primary"><?php echo number_format($carClass['count']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-sm text-gray-500">No car class data available</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Revenue Metrics -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Revenue Metrics</h3>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                            <span class="text-sm font-medium text-gray-700">Average Days</span>
                            <span class="text-lg font-semibold text-primary"><?php echo number_format($avgDays, 1); ?></span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                            <span class="text-sm font-medium text-gray-700">Average RPD</span>
                            <span class="text-lg font-semibold text-green-600">$<?php echo number_format($avgRPD, 2); ?></span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                            <span class="text-sm font-medium text-gray-700">Average RPR</span>
                            <span class="text-lg font-semibold text-blue-600">$<?php echo number_format($avgRPR, 2); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Top Agencies -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Top Agencies</h3>
                    <div class="space-y-3 max-h-96 overflow-y-auto">
                        <?php if (!empty($topAgencies)): ?>
                            <?php foreach (array_slice($topAgencies, 0, 10) as $agency): ?>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600 truncate"><?php echo htmlspecialchars($agency['agency']); ?></span>
                                    <span class="text-sm font-semibold text-primary"><?php echo number_format($agency['count']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-sm text-gray-500">No agency data available</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Payment Mode Chart -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Payment Mode Distribution</h3>
                    <?php if (!empty($paymentModes)): ?>
                        <canvas id="paymentChart" width="400" height="200"></canvas>
                    <?php else: ?>
                        <p class="text-sm text-gray-500">No payment data available for chart</p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <?php if (!empty($paymentModes)): ?>
    <script>
        // Payment Mode Chart
        const paymentCtx = document.getElementById('paymentChart').getContext('2d');
        new Chart(paymentCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($paymentModes, 'payment')); ?>,
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
    <?php endif; ?>
</body>
</html>
