<?php
require_once 'auth/config.php';

// Get database connection
$pdo = getReservationsDB();

// Get current date for filtering
$currentDate = date('Y-m-d');
$currentMonth = date('Y-m');
$currentYear = date('Y');

// Handle date range filtering
$fromDate = $_GET['from_date'] ?? '';
$untilDate = $_GET['until_date'] ?? '';

// Build WHERE clause for date filtering using selected date column
$dateFilter = '';
$params = [];
$dateType = $_GET['date_type'] ?? 'app_day';

if (!empty($fromDate) && !empty($untilDate)) {
    $dateFilter = "WHERE $dateType BETWEEN ? AND ?";
    $params = [$fromDate, $untilDate];
} elseif (!empty($fromDate)) {
    $dateFilter = "WHERE $dateType >= ?";
    $params = [$fromDate];
} elseif (!empty($untilDate)) {
    $dateFilter = "WHERE $dateType <= ?";
    $params = [$untilDate];
}

// Initialize variables to prevent undefined variable errors
$totalReservations = 0;
$next7DaysBookings = 0;
$next7DaysDetails = [];
$totalValue = 0;
$totalDiscount = 0;
$prepayReservations = 0;
$podReservations = 0;
$topAgencies = [];
$topCarClasses = [];
$avgDays = 0;
$avgRPD = 0;
$avgRPR = 0;
$paymentModes = [];
$supplierProduction = [];
$avgAdvanceDays = 0;
$totalWithAdvanceBooking = 0;

// Get summary statistics
try {
    // Total reservations
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM aero_res_22 " . $dateFilter);
    $stmt->execute($params);
    $totalReservations = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Pick up - Next 7 days using pickup_date column (INDEPENDENT of date filter)
    $currentDate = date('Y-m-d');
    $endDate = date('Y-m-d', strtotime('+6 days'));
    
    // This query should NOT be affected by the app_day date filter
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM aero_res_22 WHERE pickup_date BETWEEN ? AND ?");
    $stmt->execute([$currentDate, $endDate]);
    $next7DaysBookings = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get the actual booking details for the next 7 days
    $stmt = $pdo->prepare("SELECT res_number, voucher, name, pickup_date FROM aero_res_22 WHERE pickup_date BETWEEN ? AND ? ORDER BY pickup_date ASC");
    $stmt->execute([$currentDate, $endDate]);
    $next7DaysDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Total Value
    $valueFilter = !empty($dateFilter) ? "AND value IS NOT NULL" : "WHERE value IS NOT NULL";
    $stmt = $pdo->prepare("SELECT SUM(value) as total FROM aero_res_22 " . $dateFilter . " " . $valueFilter);
    $stmt->execute($params);
    $totalValue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Total Discount
    $discountFilter = !empty($dateFilter) ? "AND discount IS NOT NULL" : "WHERE discount IS NOT NULL";
    $stmt = $pdo->prepare("SELECT SUM(discount) as total FROM aero_res_22 " . $dateFilter . " " . $discountFilter);
    $stmt->execute($params);
    $totalDiscount = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Prepay reservations
    $prepayFilter = !empty($dateFilter) ? "AND prepay = 'yes'" : "WHERE prepay = 'yes'";
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM aero_res_22 " . $dateFilter . " " . $prepayFilter);
    $stmt->execute($params);
    $prepayReservations = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // POD reservations
    $podFilter = !empty($dateFilter) ? "AND prepay = 'no'" : "WHERE prepay = 'no'";
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM aero_res_22 " . $dateFilter . " " . $podFilter);
    $stmt->execute($params);
    $podReservations = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Top agencies by reservation count
    $agencyFilter = !empty($dateFilter) ? "AND agency IS NOT NULL" : "WHERE agency IS NOT NULL";
    $stmt = $pdo->prepare("
        SELECT agency, COUNT(*) as count, SUM(value) as total_value 
        FROM aero_res_22 
        " . $dateFilter . " " . $agencyFilter . "
        GROUP BY agency 
        ORDER BY count DESC 
        LIMIT 10
    ");
    $stmt->execute($params);
    $topAgencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top car classes by reservation count
    $carClassFilter = !empty($dateFilter) ? "AND car_class IS NOT NULL" : "WHERE car_class IS NOT NULL";
    $stmt = $pdo->prepare("
        SELECT car_class, COUNT(*) as count, SUM(value) as total_value 
        FROM aero_res_22 
        " . $dateFilter . " " . $carClassFilter . "
        GROUP BY car_class 
        ORDER BY count DESC 
        LIMIT 10
    ");
    $stmt->execute($params);
    $topCarClasses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Average days calculation
    $daysFilter = !empty($dateFilter) ? "AND days IS NOT NULL AND days > 0" : "WHERE days IS NOT NULL AND days > 0";
    $stmt = $pdo->prepare("SELECT AVG(days) as avg_days FROM aero_res_22 " . $dateFilter . " " . $daysFilter);
    $stmt->execute($params);
    $avgDays = $stmt->fetch(PDO::FETCH_ASSOC)['avg_days'] ?? 0;
    
    // Average RPD (Revenue Per Day) calculation
    $rpdFilter = !empty($dateFilter) ? "AND value IS NOT NULL AND days IS NOT NULL AND days > 0" : "WHERE value IS NOT NULL AND days IS NOT NULL AND days > 0";
    $stmt = $pdo->prepare("SELECT SUM(value) / SUM(days) as avg_rpd FROM aero_res_22 " . $dateFilter . " " . $rpdFilter);
    $stmt->execute($params);
    $avgRPD = $stmt->fetch(PDO::FETCH_ASSOC)['avg_rpd'] ?? 0;
    
    // Average RPR (Revenue Per Reservation) calculation
    $rprFilter = !empty($dateFilter) ? "AND value IS NOT NULL" : "WHERE value IS NOT NULL";
    $stmt = $pdo->prepare("SELECT SUM(value) / COUNT(*) as avg_rpr FROM aero_res_22 " . $dateFilter . " " . $rprFilter);
    $stmt->execute($params);
    $avgRPR = $stmt->fetch(PDO::FETCH_ASSOC)['avg_rpr'] ?? 0;
    
    // Payment mode distribution
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
    
    // Production by Supplier
    $supplierFilter = !empty($dateFilter) ? "AND supplier IS NOT NULL" : "WHERE supplier IS NOT NULL";
    $stmt = $pdo->prepare("
        SELECT supplier, 
               COUNT(*) as total_bookings,
               SUM(value) as total_value,
               SUM(days) as total_days,
               SUM(cpc) as total_cpc
        FROM aero_res_22 
        " . $dateFilter . " " . $supplierFilter . "
        GROUP BY supplier 
        ORDER BY total_bookings DESC
    ");
    $stmt->execute($params);
    $supplierProduction = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate days between res_day and pickup_date - Direct DATETIME handling
    $advanceBookingFilter = !empty($dateFilter) ? "AND res_day IS NOT NULL AND pickup_date IS NOT NULL" : "WHERE res_day IS NOT NULL AND pickup_date IS NOT NULL";
    $stmt = $pdo->prepare("
        SELECT 
            AVG(
                GREATEST(
                    DATEDIFF(pickup_date, res_day),
                    0
                )
            ) as avg_advance_days,
            COUNT(
                CASE 
                    WHEN res_day IS NOT NULL
                    AND pickup_date IS NOT NULL
                    AND pickup_date >= res_day
                    THEN 1
                    ELSE NULL
                END
            ) as total_with_advance_booking
        FROM aero_res_22 
        " . $dateFilter . " " . $advanceBookingFilter . "
        AND res_day IS NOT NULL
        AND pickup_date IS NOT NULL
        AND pickup_date >= res_day
    ");
    $stmt->execute($params);
    $advanceBookingData = $stmt->fetch(PDO::FETCH_ASSOC);
    $avgAdvanceDays = round($advanceBookingData['avg_advance_days'] ?? 0, 1);
    $totalWithAdvanceBooking = $advanceBookingData['total_with_advance_booking'] ?? 0;
    
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
                         <a href="dashboard_aerovision_financials.php" class="hover:text-yellow-200 transition-colors">Financials</a>
                         <span class="text-sm text-yellow-200"><?php echo date('M j, Y'); ?></span>
                     </nav>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="container mx-auto px-4 py-4">
            <?php if (isset($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-3 py-2 rounded mb-4">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Date Range Filter -->
            <div class="bg-white rounded-lg shadow-md p-4 mb-4">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Filter by Date Range</h3>
                <form method="GET" class="flex flex-wrap gap-4 items-end">
                    <div>
                        <label for="date_type" class="block text-sm font-medium text-gray-700 mb-1">Date Type</label>
                        <select id="date_type" name="date_type" class="border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="app_day" <?php echo ($_GET['date_type'] ?? 'app_day') === 'app_day' ? 'selected' : ''; ?>>App Day</option>
                            <option value="res_day" <?php echo ($_GET['date_type'] ?? 'app_day') === 'res_day' ? 'selected' : ''; ?>>Res Day</option>
                            <option value="pickup_date" <?php echo ($_GET['date_type'] ?? 'app_day') === 'pickup_date' ? 'selected' : ''; ?>>Pickup Date</option>
                        </select>
                    </div>
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
                        <a href="dashboard_aerovision.php" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition-colors">
                            Clear Filter
                        </a>
                        <a href="download_aerovision_report.php?from_date=<?php echo urlencode($fromDate); ?>&until_date=<?php echo urlencode($untilDate); ?>" 
                           class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 transition-colors">
                            <i class="fas fa-download mr-1"></i>Download Report
                        </a>
                    </div>
                </form>
                <?php if (!empty($fromDate) || !empty($untilDate)): ?>
                    <div class="mt-3 text-sm text-gray-600">
                        <strong>Active Filter:</strong> 
                        <?php 
                        $dateType = $_GET['date_type'] ?? 'app_day';
                        $dateTypeLabel = ucfirst(str_replace('_', ' ', $dateType));
                        ?>
                        <?php if (!empty($fromDate) && !empty($untilDate)): ?>
                            <?php echo $dateTypeLabel; ?>: From <?php echo date('M j, Y', strtotime($fromDate)); ?> to <?php echo date('M j, Y', strtotime($untilDate)); ?>
                        <?php elseif (!empty($fromDate)): ?>
                            <?php echo $dateTypeLabel; ?>: From <?php echo date('M j, Y', strtotime($fromDate)); ?>
                        <?php elseif (!empty($untilDate)): ?>
                            <?php echo $dateTypeLabel; ?>: Until <?php echo date('M j, Y', strtotime($untilDate)); ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Key Metrics -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                <div class="bg-white rounded-lg shadow-md p-4">
                    <div class="flex items-center">
                        <div class="p-2 rounded-full bg-blue-100">
                            <i class="fas fa-plane-departure text-blue-600 text-lg"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-xs font-medium text-gray-600">Total Reservations</p>
                            <p class="text-xl font-semibold text-gray-900"><?php echo number_format($totalReservations); ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-4">
                    <div class="flex items-center">
                        <div class="p-2 rounded-full bg-green-100">
                            <i class="fas fa-dollar-sign text-green-600 text-lg"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-xs font-medium text-gray-600">Total Value</p>
                            <p class="text-xl font-semibold text-gray-900">$<?php echo number_format($totalValue, 2); ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-4">
                    <div class="flex items-center">
                        <div class="p-2 rounded-full bg-yellow-100">
                            <i class="fas fa-credit-card text-yellow-600 text-lg"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-xs font-medium text-gray-600">Total Discount</p>
                            <p class="text-xl font-semibold text-gray-900">$<?php echo number_format($totalDiscount, 2); ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="p-2 rounded-full bg-purple-100">
                                <i class="fas fa-calendar-alt text-purple-600 text-lg"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-xs font-medium text-gray-600">Pick Up - Next 7 Days</p>
                                <p class="text-xl font-semibold text-gray-900"><?php echo number_format($next7DaysBookings); ?></p>
                            </div>
                        </div>
                        <?php if ($next7DaysBookings > 0): ?>
                            <button onclick="openNext7DaysModal()" class="bg-purple-600 text-white px-2 py-1 rounded-md hover:bg-purple-700 transition-colors text-xs">
                                <i class="fas fa-eye mr-1"></i>View Details
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Status Overview -->
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 mb-4">
                <div class="bg-white rounded-lg shadow-md p-4">
                    <h3 class="text-base font-semibold text-gray-900 mb-3">Payment Type</h3>
                    <div class="space-y-2">
                        <div class="flex justify-between items-center">
                            <span class="text-xs text-gray-600">Prepay</span>
                            <span class="text-xs font-semibold text-green-600"><?php echo number_format($prepayReservations); ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-xs text-gray-600">POD</span>
                            <span class="text-xs font-semibold text-yellow-600"><?php echo number_format($podReservations); ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-xs text-gray-600">Other</span>
                            <span class="text-xs font-semibold text-gray-600"><?php echo number_format($totalReservations - $prepayReservations - $podReservations); ?></span>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-4">
                    <h3 class="text-base font-semibold text-gray-900 mb-3">Payment Modes</h3>
                    <div class="space-y-2">
                        <?php foreach (array_slice($paymentModes, 0, 5) as $mode): ?>
                            <div class="flex justify-between items-center">
                                <span class="text-xs text-gray-600">
                                    <?php 
                                    $paymentLabel = $mode['payment'];
                                    if (strtolower($paymentLabel) === 'n/a' || empty($paymentLabel)) {
                                        echo 'Non-Prepaid';
                                    } else {
                                        echo htmlspecialchars($paymentLabel);
                                    }
                                    ?>
                                </span>
                                <span class="text-xs font-semibold text-primary"><?php echo number_format($mode['count']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-4">
                    <h3 class="text-base font-semibold text-gray-900 mb-3">Top Car Classes</h3>
                    <div class="space-y-2">
                        <?php foreach (array_slice($topCarClasses, 0, 5) as $carClass): ?>
                            <div class="flex justify-between items-center">
                                <span class="text-xs text-gray-600 truncate"><?php echo htmlspecialchars($carClass['car_class']); ?></span>
                                <span class="text-xs font-semibold text-primary"><?php echo number_format($carClass['count']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Revenue Metrics -->
                <div class="bg-white rounded-lg shadow-md p-4">
                    <h3 class="text-base font-semibold text-gray-900 mb-3">Revenue Metrics</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center p-2 bg-gray-50 rounded">
                            <span class="text-xs font-medium text-gray-700">Average Days</span>
                            <span class="text-base font-semibold text-primary"><?php echo number_format($avgDays, 1); ?></span>
                        </div>
                        <div class="flex justify-between items-center p-2 bg-gray-50 rounded">
                            <span class="text-xs font-medium text-gray-700">Average RPD</span>
                            <span class="text-base font-semibold text-green-600">$<?php echo number_format($avgRPD, 2); ?></span>
                        </div>
                        <div class="flex justify-between items-center p-2 bg-gray-50 rounded">
                            <span class="text-xs font-medium text-gray-700">Average RPR</span>
                            <span class="text-base font-semibold text-blue-600">$<?php echo number_format($avgRPR, 2); ?></span>
                        </div>
                        <div class="flex justify-between items-center p-2 bg-gray-50 rounded">
                            <span class="text-xs font-medium text-gray-700">Avg Advance Booking (Days)</span>
                            <span class="text-base font-semibold text-purple-600"><?php echo number_format($avgAdvanceDays, 1); ?></span>
                        </div>
                        <div class="flex justify-between items-center p-2 bg-gray-50 rounded">
                            <span class="text-xs font-medium text-gray-700">Bookings with Advance Data</span>
                            <span class="text-base font-semibold text-orange-600"><?php echo number_format($totalWithAdvanceBooking); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
                <!-- Top Agencies -->
                <div class="bg-white rounded-lg shadow-md p-4">
                    <h3 class="text-base font-semibold text-gray-900 mb-3">Top Agencies</h3>
                    <div class="space-y-2 max-h-80 overflow-y-auto">
                        <?php foreach (array_slice($topAgencies, 0, 10) as $agency): ?>
                            <div class="flex justify-between items-center">
                                <span class="text-xs text-gray-600 truncate"><?php echo htmlspecialchars($agency['agency']); ?></span>
                                <span class="text-xs font-semibold text-primary"><?php echo number_format($agency['count']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Payment Mode Chart -->
                <div class="bg-white rounded-lg shadow-md p-4">
                    <h3 class="text-base font-semibold text-gray-900 mb-3">Payment Mode Distribution</h3>
                    <canvas id="paymentChart" width="400" height="200"></canvas>
                </div>
            </div>

            <!-- Production by Supplier Section -->
            <div class="bg-white rounded-lg shadow-md p-4 mb-4">
                <h3 class="text-base font-semibold text-gray-900 mb-3">Production by Supplier</h3>
                <?php if (!empty($supplierProduction)): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Bookings</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Value</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Days</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total CPC</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($supplierProduction as $supplier): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-2 whitespace-nowrap text-xs font-medium text-gray-900">
                                            <?php echo htmlspecialchars($supplier['supplier'] ?? 'N/A'); ?>
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-xs text-gray-500">
                                            <?php echo number_format($supplier['total_bookings'] ?? 0); ?>
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-xs text-gray-500">
                                            $<?php echo number_format($supplier['total_value'] ?? 0, 2); ?>
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-xs text-gray-500">
                                            <?php echo number_format($supplier['total_days'] ?? 0); ?>
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-xs text-gray-500">
                                            $<?php echo number_format($supplier['total_cpc'] ?? 0, 2); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-industry text-2xl text-gray-300 mb-2"></i>
                        <p class="text-gray-500 text-sm">No supplier data available</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Next 7 Days Modal -->
    <div id="next7DaysModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[80vh] overflow-hidden">
                <div class="flex items-center justify-between p-6 border-b">
                    <h3 class="text-lg font-semibold text-gray-900">Upcoming Pickups - Next 7 Days</h3>
                    <button onclick="closeNext7DaysModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div class="p-6 overflow-y-auto max-h-[60vh]">
                    <?php if (!empty($next7DaysDetails)): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reservation #</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Voucher</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pickup Date</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($next7DaysDetails as $booking): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($booking['res_number'] ?? 'N/A'); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($booking['voucher'] ?? 'N/A'); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($booking['name'] ?? 'N/A'); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($booking['pickup_date'] ?? 'N/A'); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-calendar-times text-4xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500">No upcoming pickups in the next 7 days</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="flex justify-end p-6 border-t">
                    <button onclick="closeNext7DaysModal()" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition-colors">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

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

        // Modal functions
        function openNext7DaysModal() {
            document.getElementById('next7DaysModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeNext7DaysModal() {
            document.getElementById('next7DaysModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside
        document.getElementById('next7DaysModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeNext7DaysModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeNext7DaysModal();
            }
        });
    </script>
</body>
</html>
