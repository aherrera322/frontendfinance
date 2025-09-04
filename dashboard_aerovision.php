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

// Initialize variables
$topCarClasses = [];
$monthlyReservations = [];

// Get summary statistics
try {
    // Enhanced Car Class Analytics
    $carClassFilter = !empty($dateFilter) ? "AND car_class IS NOT NULL" : "WHERE car_class IS NOT NULL";
    
    // Top car classes by reservation count with detailed stats
    $stmt = $pdo->prepare("
        SELECT 
            car_class, 
            COUNT(*) as count, 
            SUM(value) as total_value,
            AVG(value) as avg_value,
            SUM(days) as total_days,
            AVG(days) as avg_days,
            SUM(discount) as total_discount,
            AVG(discount) as avg_discount,
            COUNT(CASE WHEN prepay = 'yes' THEN 1 END) as prepay_count,
            COUNT(CASE WHEN prepay = 'no' THEN 1 END) as pod_count
        FROM aero_res_22 
        " . $dateFilter . " " . $carClassFilter . "
        GROUP BY car_class 
        ORDER BY count DESC 
        LIMIT 15
    ");
    $stmt->execute($params);
    $topCarClasses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Monthly reservation comparison (last 12 months vs previous year)
    $currentYear = date('Y');
    $lastYear = $currentYear - 1;
    
    // Get data for the last 24 months (rolling window) - ignore date filter completely
    $monthlyQuery = "
        SELECT 
            DATE_FORMAT($dateType, '%Y-%m') as month,
            YEAR($dateType) as year,
            COUNT(*) as reservations
        FROM aero_res_22 
        WHERE $dateType >= DATE_SUB(NOW(), INTERVAL 24 MONTH)
    ";
    
    $stmt = $pdo->prepare($monthlyQuery . " GROUP BY DATE_FORMAT($dateType, '%Y-%m'), YEAR($dateType) ORDER BY month ASC");
    $stmt->execute();
    $monthlyData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    

    
    // Process monthly data for chart - Show last 12 months (rolling window)
    $monthlyReservations = [
        'labels' => [],
        'this_year' => [],
        'last_year' => []
    ];
    
    // Get last 12 months (rolling window)
    for ($i = 11; $i >= 0; $i--) {
        $currentMonth = date('Y-m', strtotime("-$i months"));
        $monthLabel = date('M', strtotime("-$i months")); // Just month name, no year
        
        $monthlyReservations['labels'][] = $monthLabel;
        
        // Find data for this month
        $thisYearCount = 0;
        $lastYearCount = 0;
        
        // Get the month and year for comparison
        $currentMonthYear = date('Y', strtotime("-$i months"));
        $lastMonthYear = $currentMonthYear - 1;
        $monthOnly = date('m', strtotime("-$i months"));
        
        // Create the month strings for both years
        $currentYearMonth = $currentMonthYear . '-' . $monthOnly;
        $lastYearMonth = $lastMonthYear . '-' . $monthOnly;
        
        foreach ($monthlyData as $data) {
            if ($data['month'] === $currentYearMonth) {
                $thisYearCount = $data['reservations'];
            } elseif ($data['month'] === $lastYearMonth) {
                $lastYearCount = $data['reservations'];
            }
        }
        
        $monthlyReservations['this_year'][] = $thisYearCount;
        $monthlyReservations['last_year'][] = $lastYearCount;
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
    <title>Aerovision Car Class Dashboard</title>
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
                        <i class="fas fa-car text-2xl"></i>
                        <h1 class="text-2xl font-bold">Aerovision Car Class Dashboard</h1>
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

            <!-- Charts Row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
                <!-- Car Class Distribution Chart -->
                <div class="bg-white rounded-lg shadow-md p-4">
                    <h3 class="text-base font-semibold text-gray-900 mb-3">Car Class Distribution</h3>
                    <canvas id="carClassChart" width="400" height="300"></canvas>
                </div>

                <!-- Monthly Reservation Comparison Chart -->
                <div class="bg-white rounded-lg shadow-md p-4">
                    <h3 class="text-base font-semibold text-gray-900 mb-3">Monthly Reservation Comparison</h3>
                    <p class="text-xs text-gray-600 mb-2">Shows last 12 months vs previous year (ignores date filter)</p>
                    <canvas id="monthlyComparisonChart" width="400" height="300"></canvas>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Car Class Distribution Chart
        const carClassCtx = document.getElementById('carClassChart').getContext('2d');
        new Chart(carClassCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($topCarClasses, 'car_class')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($topCarClasses, 'count')); ?>,
                    backgroundColor: [
                        '#117372', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6',
                        '#06B6D4', '#84CC16', '#F97316', '#EC4899', '#6366F1',
                        '#14B8A6', '#F59E0B', '#EF4444', '#8B5CF6', '#06B6D4'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: {
                                size: 10
                            }
                        }
                    }
                }
            }
        });

        // Monthly Reservation Comparison Chart
        const monthlyComparisonCtx = document.getElementById('monthlyComparisonChart').getContext('2d');
        new Chart(monthlyComparisonCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($monthlyReservations['labels']); ?>,
                datasets: [
                    {
                        label: 'Current Year',
                        data: <?php echo json_encode($monthlyReservations['this_year']); ?>,
                        borderColor: '#117372',
                        backgroundColor: '#11737220',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.1
                    },
                    {
                        label: 'Last Year',
                        data: <?php echo json_encode($monthlyReservations['last_year']); ?>,
                        borderColor: '#10B981',
                        backgroundColor: '#10B98120',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.1
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Reservations'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Month'
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: {
                                size: 12
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
