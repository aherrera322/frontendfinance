<?php
require_once 'auth/config.php';

// Get database connections
$reservationsPdo = getReservationsDB();
$clientsPdo = getClientsDB();
$partnersPdo = getPartnersDB();

// Define partner groupings
$partnerGroups = [
    'Zimple Rentals' => ['Alamo', 'National', 'Enterprise'],
    'Aerovision Inc' => ['Avis', 'Budget'],
    'Hertz Group' => ['Hertz', 'Dollar', 'Thrifty'],
    'Europcar' => ['Europcar']
];

// Get current year and month
$currentYear = date('Y');
$currentMonth = date('n'); // Current month (1-12)
$lastYear = $currentYear - 1;

// Handle month filter
$selectedMonth = $_GET['month'] ?? $currentMonth;
$selectedYear = $_GET['year'] ?? $currentYear;
$selectedDateColumn = $_GET['date_column'] ?? 'app_day'; // Default to app_day
$selectedPrepay = $_GET['prepay'] ?? ''; // Default to empty (all)

// Get month name
$monthNames = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

// Get supplier comparison data
try {
    // Get all unique suppliers from aero_res_22
    $stmt = $reservationsPdo->prepare("
        SELECT DISTINCT supplier 
        FROM aero_res_22 
        WHERE supplier IS NOT NULL 
        AND supplier != '' 
        AND supplier != 'N/A'
        ORDER BY supplier ASC
    ");
    $stmt->execute();
    $suppliers = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $supplierData = [];
    
    foreach ($suppliers as $supplier) {
        // Build prepay filter condition
        $prepayCondition = '';
        $prepayParams = [];
        if (!empty($selectedPrepay)) {
            $prepayCondition = 'AND prepay = ?';
            $prepayParams = [$selectedPrepay];
        }
        
        // Selected Month This Year
        $stmt = $reservationsPdo->prepare("
            SELECT 
                COUNT(*) as reservations,
                SUM(value) as revenue,
                SUM(days) as total_days
            FROM aero_res_22 
            WHERE supplier = ? 
            AND YEAR($selectedDateColumn) = ? 
            AND MONTH($selectedDateColumn) = ?
            $prepayCondition
        ");
        $stmt->execute(array_merge([$supplier, $selectedYear, $selectedMonth], $prepayParams));
        $selectedMonthThisYear = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Selected Month Last Year
        $stmt = $reservationsPdo->prepare("
            SELECT 
                COUNT(*) as reservations,
                SUM(value) as revenue,
                SUM(days) as total_days
            FROM aero_res_22 
            WHERE supplier = ? 
            AND YEAR($selectedDateColumn) = ? 
            AND MONTH($selectedDateColumn) = ?
            $prepayCondition
        ");
        $stmt->execute(array_merge([$supplier, $selectedYear - 1, $selectedMonth], $prepayParams));
        $selectedMonthLastYear = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // YTD This Year (through selected month)
        $stmt = $reservationsPdo->prepare("
            SELECT 
                COUNT(*) as reservations,
                SUM(value) as revenue,
                SUM(days) as total_days
            FROM aero_res_22 
            WHERE supplier = ? 
            AND YEAR($selectedDateColumn) = ?
            AND MONTH($selectedDateColumn) <= ?
            $prepayCondition
        ");
        $stmt->execute(array_merge([$supplier, $selectedYear, $selectedMonth], $prepayParams));
        $ytdThisYear = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // YTD Last Year (through selected month)
        $stmt = $reservationsPdo->prepare("
            SELECT 
                COUNT(*) as reservations,
                SUM(value) as revenue,
                SUM(days) as total_days
            FROM aero_res_22 
            WHERE supplier = ? 
            AND YEAR($selectedDateColumn) = ?
            AND MONTH($selectedDateColumn) <= ?
            $prepayCondition
        ");
        $stmt->execute(array_merge([$supplier, $selectedYear - 1, $selectedMonth], $prepayParams));
        $ytdLastYear = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculate percentage changes
        $supplierData[$supplier] = [
            'monthly' => [
                'this_year' => [
                    'reservations' => $selectedMonthThisYear['reservations'] ?? 0,
                    'revenue' => $selectedMonthThisYear['revenue'] ?? 0,
                    'days' => $selectedMonthThisYear['total_days'] ?? 0
                ],
                'last_year' => [
                    'reservations' => $selectedMonthLastYear['reservations'] ?? 0,
                    'revenue' => $selectedMonthLastYear['revenue'] ?? 0,
                    'days' => $selectedMonthLastYear['total_days'] ?? 0
                ]
            ],
            'ytd' => [
                'this_year' => [
                    'reservations' => $ytdThisYear['reservations'] ?? 0,
                    'revenue' => $ytdThisYear['revenue'] ?? 0,
                    'days' => $ytdThisYear['total_days'] ?? 0
                ],
                'last_year' => [
                    'reservations' => $ytdLastYear['reservations'] ?? 0,
                    'revenue' => $ytdLastYear['revenue'] ?? 0,
                    'days' => $ytdLastYear['total_days'] ?? 0
                ]
            ]
        ];
        
        // Calculate percentage changes
        foreach (['reservations', 'revenue', 'days'] as $metric) {
            // Monthly percentage change
            $currentVal = $supplierData[$supplier]['monthly']['this_year'][$metric];
            $lastVal = $supplierData[$supplier]['monthly']['last_year'][$metric];
            
            if ($lastVal > 0) {
                $supplierData[$supplier]['monthly']['percentage_change'][$metric] = 
                    (($currentVal - $lastVal) / $lastVal) * 100;
            } else {
                $supplierData[$supplier]['monthly']['percentage_change'][$metric] = 
                    $currentVal > 0 ? 100 : 0;
            }
            
            // YTD percentage change
            $currentVal = $supplierData[$supplier]['ytd']['this_year'][$metric];
            $lastVal = $supplierData[$supplier]['ytd']['last_year'][$metric];
            
            if ($lastVal > 0) {
                $supplierData[$supplier]['ytd']['percentage_change'][$metric] = 
                    (($currentVal - $lastVal) / $lastVal) * 100;
            } else {
                $supplierData[$supplier]['ytd']['percentage_change'][$metric] = 
                    $currentVal > 0 ? 100 : 0;
            }
        }
    }
    
    // Get partner commissions
    $partnerCommissions = [];
    try {
        $stmt = $partnersPdo->query("SELECT partner_name, commission FROM partners WHERE commission IS NOT NULL AND commission > 0");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $partnerCommissions[$row['partner_name']] = $row['commission'];
        }
    } catch (Exception $e) {
        // If partners table doesn't exist or has issues, use default commissions
        $partnerCommissions = [
            'Zimple Rentals' => 18.0,
            'Aerovision Inc' => 18.0,
            'Hertz Group' => 20.0,
            'Europcar' => 25.0
        ];
    }
    
    // Calculate net income data
    $netIncomeData = [];
    
    foreach ($partnerGroups as $groupName => $suppliers) {
        $groupData = [
            'monthly' => [
                'this_year' => ['revenue' => 0, 'net_income' => 0],
                'last_year' => ['revenue' => 0, 'net_income' => 0]
            ],
            'ytd' => [
                'this_year' => ['revenue' => 0, 'net_income' => 0],
                'last_year' => ['revenue' => 0, 'net_income' => 0]
            ]
        ];
        
        // Get commission for this group
        $commission = $partnerCommissions[$groupName] ?? 10.0; // Default 10% if not found
        
        foreach ($suppliers as $supplier) {
            if (isset($supplierData[$supplier])) {
                // Monthly data
                $groupData['monthly']['this_year']['revenue'] += $supplierData[$supplier]['monthly']['this_year']['revenue'];
                $groupData['monthly']['last_year']['revenue'] += $supplierData[$supplier]['monthly']['last_year']['revenue'];
                
                // YTD data
                $groupData['ytd']['this_year']['revenue'] += $supplierData[$supplier]['ytd']['this_year']['revenue'];
                $groupData['ytd']['last_year']['revenue'] += $supplierData[$supplier]['ytd']['last_year']['revenue'];
            }
        }
        
        // Calculate net income (revenue * commission percentage)
        $groupData['monthly']['this_year']['net_income'] = $groupData['monthly']['this_year']['revenue'] * ($commission / 100);
        $groupData['monthly']['last_year']['net_income'] = $groupData['monthly']['last_year']['revenue'] * ($commission / 100);
        $groupData['ytd']['this_year']['net_income'] = $groupData['ytd']['this_year']['revenue'] * ($commission / 100);
        $groupData['ytd']['last_year']['net_income'] = $groupData['ytd']['last_year']['revenue'] * ($commission / 100);
        
        $netIncomeData[$groupName] = [
            'data' => $groupData,
            'commission' => $commission
        ];
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
    <title>Aerovision Financials Dashboard</title>
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
</head>
<body class="bg-gray-100">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-primary text-white shadow-lg">
            <div class="container mx-auto px-6 py-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <i class="fas fa-chart-line text-2xl"></i>
                        <h1 class="text-2xl font-bold">Aerovision Financials Dashboard</h1>
                    </div>
                    <nav class="flex items-center space-x-6">
                        <a href="index.html" class="hover:text-yellow-200 transition-colors">Home</a>
                        <a href="dashboard_aerovision.php" class="hover:text-yellow-200 transition-colors">Aerovision Dashboard</a>
                        <a href="dashboard_booking.php" class="hover:text-yellow-200 transition-colors">ZimpleB Dashboard</a>
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

            <!-- Month Filter -->
            <div class="bg-white rounded-lg shadow-md p-4 mb-4">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Filter Options</h3>
                <form method="GET" class="flex flex-wrap gap-4 items-end">
                    <div>
                        <label for="month" class="block text-sm font-medium text-gray-700 mb-1">Month</label>
                        <select id="month" name="month" class="border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php echo $selectedMonth == $m ? 'selected' : ''; ?>>
                                    <?php echo $monthNames[$m]; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div>
                        <label for="year" class="block text-sm font-medium text-gray-700 mb-1">Year</label>
                        <select id="year" name="year" class="border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <?php for ($y = $currentYear; $y >= $currentYear - 5; $y--): ?>
                                <option value="<?php echo $y; ?>" <?php echo $selectedYear == $y ? 'selected' : ''; ?>>
                                    <?php echo $y; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                                         <div>
                         <label for="date_column" class="block text-sm font-medium text-gray-700 mb-1">Date Column</label>
                         <select id="date_column" name="date_column" class="border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                             <option value="res_day" <?php echo $selectedDateColumn == 'res_day' ? 'selected' : ''; ?>>Reservation Day</option>
                             <option value="app_day" <?php echo $selectedDateColumn == 'app_day' ? 'selected' : ''; ?>>Approval Day</option>
                             <option value="pickup_date" <?php echo $selectedDateColumn == 'pickup_date' ? 'selected' : ''; ?>>Pickup Date</option>
                         </select>
                     </div>
                     <div>
                         <label for="prepay" class="block text-sm font-medium text-gray-700 mb-1">Prepay Status</label>
                         <select id="prepay" name="prepay" class="border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                             <option value="" <?php echo $selectedPrepay == '' ? 'selected' : ''; ?>>All</option>
                             <option value="yes" <?php echo $selectedPrepay == 'yes' ? 'selected' : ''; ?>>Yes</option>
                             <option value="no" <?php echo $selectedPrepay == 'no' ? 'selected' : ''; ?>>No</option>
                         </select>
                     </div>
                     <div class="flex gap-2">
                        <button type="submit" class="bg-primary text-white px-4 py-2 rounded-md hover:bg-primary/90 transition-colors">
                            Apply Filter
                        </button>
                        <a href="dashboard_aerovision_financials.php" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition-colors">
                            Reset
                        </a>
                    </div>
                </form>
            </div>

            <!-- Monthly Comparison Section -->
            <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                <h3 class="text-xl font-semibold text-gray-900 mb-4">
                    Monthly Comparison: <?php echo $monthNames[$selectedMonth]; ?> <?php echo $selectedYear; ?> vs <?php echo $monthNames[$selectedMonth]; ?> <?php echo $selectedYear - 1; ?>
                    <span class="text-sm font-normal text-gray-600">(Based on <?php echo ucfirst(str_replace('_', ' ', $selectedDateColumn)); ?>)</span>
                    <?php if (!empty($selectedPrepay)): ?>
                        <span class="text-sm font-normal text-gray-600">| Prepay: <?php echo ucfirst($selectedPrepay); ?></span>
                    <?php endif; ?>
                </h3>
                
                <?php if (!empty($supplierData)): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                                    
                                    <!-- Reservations -->
                                    <th colspan="3" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider bg-blue-50">
                                        Reservations
                                    </th>
                                    
                                    <!-- Revenue -->
                                    <th colspan="3" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider bg-green-50">
                                        Revenue
                                    </th>
                                    
                                    <!-- Days -->
                                    <th colspan="3" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider bg-yellow-50">
                                        Days
                                    </th>
                                </tr>
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier Name</th>
                                    
                                    <!-- Reservations columns -->
                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">This Year</th>
                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Last Year</th>
                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">% Change</th>
                                    
                                    <!-- Revenue columns -->
                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">This Year</th>
                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Last Year</th>
                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">% Change</th>
                                    
                                    <!-- Days columns -->
                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">This Year</th>
                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Last Year</th>
                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">% Change</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($supplierData as $supplier => $data): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($supplier); ?>
                                        </td>
                                        
                                        <!-- Reservations -->
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-center text-gray-900">
                                            <?php echo number_format($data['monthly']['this_year']['reservations']); ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-center text-gray-600">
                                            <?php echo number_format($data['monthly']['last_year']['reservations']); ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-center">
                                            <?php 
                                            $resChange = $data['monthly']['percentage_change']['reservations'];
                                            $resColor = $resChange >= 0 ? 'text-green-600' : 'text-red-600';
                                            $resIcon = $resChange >= 0 ? '↗' : '↘';
                                            ?>
                                            <span class="<?php echo $resColor; ?> font-medium">
                                                <?php echo $resIcon; ?> <?php echo number_format($resChange, 1); ?>%
                                            </span>
                                        </td>
                                        
                                        <!-- Revenue -->
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-center text-gray-900">
                                            $<?php echo number_format($data['monthly']['this_year']['revenue'], 2); ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-center text-gray-600">
                                            $<?php echo number_format($data['monthly']['last_year']['revenue'], 2); ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-center">
                                            <?php 
                                            $revChange = $data['monthly']['percentage_change']['revenue'];
                                            $revColor = $revChange >= 0 ? 'text-green-600' : 'text-red-600';
                                            $revIcon = $revChange >= 0 ? '↗' : '↘';
                                            ?>
                                            <span class="<?php echo $revColor; ?> font-medium">
                                                <?php echo $revIcon; ?> <?php echo number_format($revChange, 1); ?>%
                                            </span>
                                        </td>
                                        
                                        <!-- Days -->
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-center text-gray-900">
                                            <?php echo number_format($data['monthly']['this_year']['days']); ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-center text-gray-600">
                                            <?php echo number_format($data['monthly']['last_year']['days']); ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-center">
                                            <?php 
                                            $daysChange = $data['monthly']['percentage_change']['days'];
                                            $daysColor = $daysChange >= 0 ? 'text-green-600' : 'text-red-600';
                                            $daysIcon = $daysChange >= 0 ? '↗' : '↘';
                                            ?>
                                            <span class="<?php echo $daysColor; ?> font-medium">
                                                <?php echo $daysIcon; ?> <?php echo number_format($daysChange, 1); ?>%
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                
                                <!-- Monthly Totals Row -->
                                <tr class="bg-gray-100 font-semibold border-t-2 border-gray-300">
                                    <td class="px-4 py-3 whitespace-nowrap text-sm font-bold text-gray-900">
                                        <strong>TOTALS</strong>
                                    </td>
                                    
                                    <!-- Reservations Totals -->
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-center font-bold text-gray-900">
                                        <?php 
                                        $totalMonthlyRes = array_sum(array_map(function($data) { return $data['monthly']['this_year']['reservations']; }, $supplierData));
                                        echo number_format($totalMonthlyRes);
                                        ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-center font-bold text-gray-600">
                                        <?php 
                                        $totalMonthlyResLast = array_sum(array_map(function($data) { return $data['monthly']['last_year']['reservations']; }, $supplierData));
                                        echo number_format($totalMonthlyResLast);
                                        ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-center font-bold">
                                        <?php 
                                        $totalResChange = $totalMonthlyResLast > 0 ? (($totalMonthlyRes - $totalMonthlyResLast) / $totalMonthlyResLast) * 100 : 0;
                                        $totalResColor = $totalResChange >= 0 ? 'text-green-600' : 'text-red-600';
                                        $totalResIcon = $totalResChange >= 0 ? '↗' : '↘';
                                        ?>
                                        <span class="<?php echo $totalResColor; ?>">
                                            <?php echo $totalResIcon; ?> <?php echo number_format($totalResChange, 1); ?>%
                                        </span>
                                    </td>
                                    
                                    <!-- Revenue Totals -->
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-center font-bold text-gray-900">
                                        $<?php 
                                        $totalMonthlyRev = array_sum(array_map(function($data) { return $data['monthly']['this_year']['revenue']; }, $supplierData));
                                        echo number_format($totalMonthlyRev, 2);
                                        ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-center font-bold text-gray-600">
                                        $<?php 
                                        $totalMonthlyRevLast = array_sum(array_map(function($data) { return $data['monthly']['last_year']['revenue']; }, $supplierData));
                                        echo number_format($totalMonthlyRevLast, 2);
                                        ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-center font-bold">
                                        <?php 
                                        $totalRevChange = $totalMonthlyRevLast > 0 ? (($totalMonthlyRev - $totalMonthlyRevLast) / $totalMonthlyRevLast) * 100 : 0;
                                        $totalRevColor = $totalRevChange >= 0 ? 'text-green-600' : 'text-red-600';
                                        $totalRevIcon = $totalRevChange >= 0 ? '↗' : '↘';
                                        ?>
                                        <span class="<?php echo $totalRevColor; ?>">
                                            <?php echo $totalRevIcon; ?> <?php echo number_format($totalRevChange, 1); ?>%
                                        </span>
                                    </td>
                                    
                                    <!-- Days Totals -->
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-center font-bold text-gray-900">
                                        <?php 
                                        $totalMonthlyDays = array_sum(array_map(function($data) { return $data['monthly']['this_year']['days']; }, $supplierData));
                                        echo number_format($totalMonthlyDays);
                                        ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-center font-bold text-gray-600">
                                        <?php 
                                        $totalMonthlyDaysLast = array_sum(array_map(function($data) { return $data['monthly']['last_year']['days']; }, $supplierData));
                                        echo number_format($totalMonthlyDaysLast);
                                        ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-center font-bold">
                                        <?php 
                                        $totalDaysChange = $totalMonthlyDaysLast > 0 ? (($totalMonthlyDays - $totalMonthlyDaysLast) / $totalMonthlyDaysLast) * 100 : 0;
                                        $totalDaysColor = $totalDaysChange >= 0 ? 'text-green-600' : 'text-red-600';
                                        $totalDaysIcon = $totalDaysChange >= 0 ? '↗' : '↘';
                                        ?>
                                        <span class="<?php echo $totalDaysColor; ?>">
                                            <?php echo $totalDaysIcon; ?> <?php echo number_format($totalDaysChange, 1); ?>%
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <i class="fas fa-chart-bar text-4xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500">No supplier data available for monthly comparison</p>
                    </div>
                <?php endif; ?>
                         </div>

             <!-- Monthly Net Income Section -->
             <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                 <h3 class="text-xl font-semibold text-gray-900 mb-4">
                     Monthly Net Income by Partner Group: <?php echo $monthNames[$selectedMonth]; ?> <?php echo $selectedYear; ?> vs <?php echo $monthNames[$selectedMonth]; ?> <?php echo $selectedYear - 1; ?>
                     <span class="text-sm font-normal text-gray-600">(Based on <?php echo ucfirst(str_replace('_', ' ', $selectedDateColumn)); ?>)</span>
                     <?php if (!empty($selectedPrepay)): ?>
                         <span class="text-sm font-normal text-gray-600">| Prepay: <?php echo ucfirst($selectedPrepay); ?></span>
                     <?php endif; ?>
                 </h3>
                 
                 <?php if (!empty($netIncomeData)): ?>
                     <div class="overflow-x-auto">
                         <table class="min-w-full divide-y divide-gray-200">
                             <thead class="bg-gray-50">
                                 <tr>
                                     <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Partner Group</th>
                                     <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Commission %</th>
                                     
                                     <!-- Revenue -->
                                     <th colspan="2" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider bg-green-50">
                                         Revenue
                                     </th>
                                     
                                     <!-- Net Income -->
                                     <th colspan="2" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider bg-blue-50">
                                         Net Income
                                     </th>
                                 </tr>
                                 <tr>
                                     <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Partner Name</th>
                                     <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Rate</th>
                                     
                                     <!-- Revenue columns -->
                                     <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">This Year</th>
                                     <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Last Year</th>
                                     
                                     <!-- Net Income columns -->
                                     <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">This Year</th>
                                     <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Last Year</th>
                                 </tr>
                             </thead>
                             <tbody class="bg-white divide-y divide-gray-200">
                                 <?php foreach ($netIncomeData as $groupName => $data): ?>
                                     <tr class="hover:bg-gray-50">
                                         <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                             <?php echo htmlspecialchars($groupName); ?>
                                         </td>
                                         <td class="px-4 py-3 whitespace-nowrap text-sm text-center text-gray-600">
                                             <?php echo number_format($data['commission'], 1); ?>%
                                         </td>
                                         
                                         <!-- Revenue -->
                                         <td class="px-4 py-3 whitespace-nowrap text-sm text-center text-gray-900">
                                             $<?php echo number_format($data['data']['monthly']['this_year']['revenue'], 2); ?>
                                         </td>
                                         <td class="px-4 py-3 whitespace-nowrap text-sm text-center text-gray-600">
                                             $<?php echo number_format($data['data']['monthly']['last_year']['revenue'], 2); ?>
                                         </td>
                                         
                                         <!-- Net Income -->
                                         <td class="px-4 py-3 whitespace-nowrap text-sm text-center font-semibold text-blue-900">
                                             $<?php echo number_format($data['data']['monthly']['this_year']['net_income'], 2); ?>
                                         </td>
                                         <td class="px-4 py-3 whitespace-nowrap text-sm text-center font-semibold text-blue-600">
                                             $<?php echo number_format($data['data']['monthly']['last_year']['net_income'], 2); ?>
                                         </td>
                                     </tr>
                                 <?php endforeach; ?>
                                 
                                 <!-- Monthly Net Income Totals Row -->
                                 <tr class="bg-gray-100 font-semibold border-t-2 border-gray-300">
                                     <td class="px-4 py-3 whitespace-nowrap text-sm font-bold text-gray-900">
                                         <strong>TOTALS</strong>
                                     </td>
                                     <td class="px-4 py-3 whitespace-nowrap text-sm text-center font-bold text-gray-600">
                                         -
                                     </td>
                                     
                                     <!-- Revenue Totals -->
                                     <td class="px-4 py-3 whitespace-nowrap text-sm text-center font-bold text-gray-900">
                                         $<?php 
                                         $totalMonthlyRevenue = array_sum(array_map(function($data) { return $data['data']['monthly']['this_year']['revenue']; }, $netIncomeData));
                                         echo number_format($totalMonthlyRevenue, 2);
                                         ?>
                                     </td>
                                     <td class="px-4 py-3 whitespace-nowrap text-sm text-center font-bold text-gray-600">
                                         $<?php 
                                         $totalMonthlyRevenueLast = array_sum(array_map(function($data) { return $data['data']['monthly']['last_year']['revenue']; }, $netIncomeData));
                                         echo number_format($totalMonthlyRevenueLast, 2);
                                         ?>
                                     </td>
                                     
                                     <!-- Net Income Totals -->
                                     <td class="px-4 py-3 whitespace-nowrap text-sm text-center font-bold text-blue-900">
                                         $<?php 
                                         $totalMonthlyNetIncome = array_sum(array_map(function($data) { return $data['data']['monthly']['this_year']['net_income']; }, $netIncomeData));
                                         echo number_format($totalMonthlyNetIncome, 2);
                                         ?>
                                     </td>
                                     <td class="px-4 py-3 whitespace-nowrap text-sm text-center font-bold text-blue-600">
                                         $<?php 
                                         $totalMonthlyNetIncomeLast = array_sum(array_map(function($data) { return $data['data']['monthly']['last_year']['net_income']; }, $netIncomeData));
                                         echo number_format($totalMonthlyNetIncomeLast, 2);
                                         ?>
                                     </td>
                                 </tr>
                             </tbody>
                         </table>
                     </div>
                 <?php else: ?>
                     <div class="text-center py-8">
                         <i class="fas fa-chart-line text-4xl text-gray-300 mb-4"></i>
                         <p class="text-gray-500">No net income data available for monthly comparison</p>
                     </div>
                 <?php endif; ?>
             </div>

                          <!-- YTD Comparison Section -->
             <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                                 <h3 class="text-xl font-semibold text-gray-900 mb-4">
                    Year-to-Date Comparison (through <?php echo $monthNames[$selectedMonth]; ?>): <?php echo $selectedYear; ?> vs <?php echo $selectedYear - 1; ?>
                    <span class="text-sm font-normal text-gray-600">(Based on <?php echo ucfirst(str_replace('_', ' ', $selectedDateColumn)); ?>)</span>
                    <?php if (!empty($selectedPrepay)): ?>
                        <span class="text-sm font-normal text-gray-600">| Prepay: <?php echo ucfirst($selectedPrepay); ?></span>
                    <?php endif; ?>
                </h3>
                
                <?php if (!empty($supplierData)): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                                    
                                    <!-- Reservations -->
                                    <th colspan="3" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider bg-blue-50">
                                        Reservations
                                    </th>
                                    
                                    <!-- Revenue -->
                                    <th colspan="3" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider bg-green-50">
                                        Revenue
                                    </th>
                                    
                                    <!-- Days -->
                                    <th colspan="3" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider bg-yellow-50">
                                        Days
                                    </th>
                                </tr>
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier Name</th>
                                    
                                    <!-- Reservations columns -->
                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">This Year</th>
                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Last Year</th>
                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">% Change</th>
                                    
                                    <!-- Revenue columns -->
                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">This Year</th>
                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Last Year</th>
                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">% Change</th>
                                    
                                    <!-- Days columns -->
                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">This Year</th>
                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Last Year</th>
                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">% Change</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($supplierData as $supplier => $data): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($supplier); ?>
                                        </td>
                                        
                                        <!-- Reservations -->
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-center text-gray-900">
                                            <?php echo number_format($data['ytd']['this_year']['reservations']); ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-center text-gray-600">
                                            <?php echo number_format($data['ytd']['last_year']['reservations']); ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-center">
                                            <?php 
                                            $ytdResChange = $data['ytd']['percentage_change']['reservations'];
                                            $ytdResColor = $ytdResChange >= 0 ? 'text-green-600' : 'text-red-600';
                                            $ytdResIcon = $ytdResChange >= 0 ? '↗' : '↘';
                                            ?>
                                            <span class="<?php echo $ytdResColor; ?> font-medium">
                                                <?php echo $ytdResIcon; ?> <?php echo number_format($ytdResChange, 1); ?>%
                                            </span>
                                        </td>
                                        
                                        <!-- Revenue -->
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-center text-gray-900">
                                            $<?php echo number_format($data['ytd']['this_year']['revenue'], 2); ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-center text-gray-600">
                                            $<?php echo number_format($data['ytd']['last_year']['revenue'], 2); ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-center">
                                            <?php 
                                            $ytdRevChange = $data['ytd']['percentage_change']['revenue'];
                                            $ytdRevColor = $ytdRevChange >= 0 ? 'text-green-600' : 'text-red-600';
                                            $ytdRevIcon = $ytdRevChange >= 0 ? '↗' : '↘';
                                            ?>
                                            <span class="<?php echo $ytdRevColor; ?> font-medium">
                                                <?php echo $ytdRevIcon; ?> <?php echo number_format($ytdRevChange, 1); ?>%
                                            </span>
                                        </td>
                                        
                                        <!-- Days -->
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-center text-gray-900">
                                            <?php echo number_format($data['ytd']['this_year']['days']); ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-center text-gray-600">
                                            <?php echo number_format($data['ytd']['last_year']['days']); ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-center">
                                            <?php 
                                            $ytdDaysChange = $data['ytd']['percentage_change']['days'];
                                            $ytdDaysColor = $ytdDaysChange >= 0 ? 'text-green-600' : 'text-red-600';
                                            $ytdDaysIcon = $ytdDaysChange >= 0 ? '↗' : '↘';
                                            ?>
                                            <span class="<?php echo $ytdDaysColor; ?> font-medium">
                                                <?php echo $ytdDaysIcon; ?> <?php echo number_format($ytdDaysChange, 1); ?>%
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                
                                <!-- YTD Totals Row -->
                                <tr class="bg-gray-100 font-semibold border-t-2 border-gray-300">
                                    <td class="px-4 py-3 whitespace-nowrap text-sm font-bold text-gray-900">
                                        <strong>TOTALS</strong>
                                    </td>
                                    
                                    <!-- Reservations Totals -->
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-center font-bold text-gray-900">
                                        <?php 
                                        $totalYTDRes = array_sum(array_map(function($data) { return $data['ytd']['this_year']['reservations']; }, $supplierData));
                                        echo number_format($totalYTDRes);
                                        ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-center font-bold text-gray-600">
                                        <?php 
                                        $totalYTDResLast = array_sum(array_map(function($data) { return $data['ytd']['last_year']['reservations']; }, $supplierData));
                                        echo number_format($totalYTDResLast);
                                        ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-center font-bold">
                                        <?php 
                                        $totalYTDResChange = $totalYTDResLast > 0 ? (($totalYTDRes - $totalYTDResLast) / $totalYTDResLast) * 100 : 0;
                                        $totalYTDResColor = $totalYTDResChange >= 0 ? 'text-green-600' : 'text-red-600';
                                        $totalYTDResIcon = $totalYTDResChange >= 0 ? '↗' : '↘';
                                        ?>
                                        <span class="<?php echo $totalYTDResColor; ?>">
                                            <?php echo $totalYTDResIcon; ?> <?php echo number_format($totalYTDResChange, 1); ?>%
                                        </span>
                                    </td>
                                    
                                    <!-- Revenue Totals -->
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-center font-bold text-gray-900">
                                        $<?php 
                                        $totalYTDRev = array_sum(array_map(function($data) { return $data['ytd']['this_year']['revenue']; }, $supplierData));
                                        echo number_format($totalYTDRev, 2);
                                        ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-center font-bold text-gray-600">
                                        $<?php 
                                        $totalYTDRevLast = array_sum(array_map(function($data) { return $data['ytd']['last_year']['revenue']; }, $supplierData));
                                        echo number_format($totalYTDRevLast, 2);
                                        ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-center font-bold">
                                        <?php 
                                        $totalYTDRevChange = $totalYTDRevLast > 0 ? (($totalYTDRev - $totalYTDRevLast) / $totalYTDRevLast) * 100 : 0;
                                        $totalYTDRevColor = $totalYTDRevChange >= 0 ? 'text-green-600' : 'text-red-600';
                                        $totalYTDRevIcon = $totalYTDRevChange >= 0 ? '↗' : '↘';
                                        ?>
                                        <span class="<?php echo $totalYTDRevColor; ?>">
                                            <?php echo $totalYTDRevIcon; ?> <?php echo number_format($totalYTDRevChange, 1); ?>%
                                        </span>
                                    </td>
                                    
                                    <!-- Days Totals -->
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-center font-bold text-gray-900">
                                        <?php 
                                        $totalYTDDays = array_sum(array_map(function($data) { return $data['ytd']['this_year']['days']; }, $supplierData));
                                        echo number_format($totalYTDDays);
                                        ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-center font-bold text-gray-600">
                                        <?php 
                                        $totalYTDDaysLast = array_sum(array_map(function($data) { return $data['ytd']['last_year']['days']; }, $supplierData));
                                        echo number_format($totalYTDDaysLast);
                                        ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-center font-bold">
                                        <?php 
                                        $totalYTDDaysChange = $totalYTDDaysLast > 0 ? (($totalYTDDays - $totalYTDDaysLast) / $totalYTDDaysLast) * 100 : 0;
                                        $totalYTDDaysColor = $totalYTDDaysChange >= 0 ? 'text-green-600' : 'text-red-600';
                                        $totalYTDDaysIcon = $totalYTDDaysChange >= 0 ? '↗' : '↘';
                                        ?>
                                        <span class="<?php echo $totalYTDDaysColor; ?>">
                                            <?php echo $totalYTDDaysIcon; ?> <?php echo number_format($totalYTDDaysChange, 1); ?>%
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <i class="fas fa-chart-bar text-4xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500">No supplier data available for YTD comparison</p>
                    </div>
                <?php endif; ?>
                         </div>

             <!-- YTD Net Income Section -->
             <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                 <h3 class="text-xl font-semibold text-gray-900 mb-4">
                     YTD Net Income by Partner Group (through <?php echo $monthNames[$selectedMonth]; ?>): <?php echo $selectedYear; ?> vs <?php echo $selectedYear - 1; ?>
                     <span class="text-sm font-normal text-gray-600">(Based on <?php echo ucfirst(str_replace('_', ' ', $selectedDateColumn)); ?>)</span>
                     <?php if (!empty($selectedPrepay)): ?>
                         <span class="text-sm font-normal text-gray-600">| Prepay: <?php echo ucfirst($selectedPrepay); ?></span>
                     <?php endif; ?>
                 </h3>
                 
                 <?php if (!empty($netIncomeData)): ?>
                     <div class="overflow-x-auto">
                         <table class="min-w-full divide-y divide-gray-200">
                             <thead class="bg-gray-50">
                                 <tr>
                                     <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Partner Group</th>
                                     <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Commission %</th>
                                     
                                     <!-- Revenue -->
                                     <th colspan="2" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider bg-green-50">
                                         Revenue
                                     </th>
                                     
                                     <!-- Net Income -->
                                     <th colspan="2" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider bg-blue-50">
                                         Net Income
                                     </th>
                                 </tr>
                                 <tr>
                                     <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Partner Name</th>
                                     <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Rate</th>
                                     
                                     <!-- Revenue columns -->
                                     <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">This Year</th>
                                     <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Last Year</th>
                                     
                                     <!-- Net Income columns -->
                                     <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">This Year</th>
                                     <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Last Year</th>
                                 </tr>
                             </thead>
                             <tbody class="bg-white divide-y divide-gray-200">
                                 <?php foreach ($netIncomeData as $groupName => $data): ?>
                                     <tr class="hover:bg-gray-50">
                                         <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                             <?php echo htmlspecialchars($groupName); ?>
                                         </td>
                                         <td class="px-4 py-3 whitespace-nowrap text-sm text-center text-gray-600">
                                             <?php echo number_format($data['commission'], 1); ?>%
                                         </td>
                                         
                                         <!-- Revenue -->
                                         <td class="px-4 py-3 whitespace-nowrap text-sm text-center text-gray-900">
                                             $<?php echo number_format($data['data']['ytd']['this_year']['revenue'], 2); ?>
                                         </td>
                                         <td class="px-4 py-3 whitespace-nowrap text-sm text-center text-gray-600">
                                             $<?php echo number_format($data['data']['ytd']['last_year']['revenue'], 2); ?>
                                         </td>
                                         
                                         <!-- Net Income -->
                                         <td class="px-4 py-3 whitespace-nowrap text-sm text-center font-semibold text-blue-900">
                                             $<?php echo number_format($data['data']['ytd']['this_year']['net_income'], 2); ?>
                                         </td>
                                         <td class="px-4 py-3 whitespace-nowrap text-sm text-center font-semibold text-blue-600">
                                             $<?php echo number_format($data['data']['ytd']['last_year']['net_income'], 2); ?>
                                         </td>
                                     </tr>
                                 <?php endforeach; ?>
                                 
                                 <!-- YTD Net Income Totals Row -->
                                 <tr class="bg-gray-100 font-semibold border-t-2 border-gray-300">
                                     <td class="px-4 py-3 whitespace-nowrap text-sm font-bold text-gray-900">
                                         <strong>TOTALS</strong>
                                     </td>
                                     <td class="px-4 py-3 whitespace-nowrap text-sm text-center font-bold text-gray-600">
                                         -
                                     </td>
                                     
                                     <!-- Revenue Totals -->
                                     <td class="px-4 py-3 whitespace-nowrap text-sm text-center font-bold text-gray-900">
                                         $<?php 
                                         $totalYTDRevenue = array_sum(array_map(function($data) { return $data['data']['ytd']['this_year']['revenue']; }, $netIncomeData));
                                         echo number_format($totalYTDRevenue, 2);
                                         ?>
                                     </td>
                                     <td class="px-4 py-3 whitespace-nowrap text-sm text-center font-bold text-gray-600">
                                         $<?php 
                                         $totalYTDRevenueLast = array_sum(array_map(function($data) { return $data['data']['ytd']['last_year']['revenue']; }, $netIncomeData));
                                         echo number_format($totalYTDRevenueLast, 2);
                                         ?>
                                     </td>
                                     
                                     <!-- Net Income Totals -->
                                     <td class="px-4 py-3 whitespace-nowrap text-sm text-center font-bold text-blue-900">
                                         $<?php 
                                         $totalYTDNetIncome = array_sum(array_map(function($data) { return $data['data']['ytd']['this_year']['net_income']; }, $netIncomeData));
                                         echo number_format($totalYTDNetIncome, 2);
                                         ?>
                                     </td>
                                     <td class="px-4 py-3 whitespace-nowrap text-sm text-center font-bold text-blue-600">
                                         $<?php 
                                         $totalYTDNetIncomeLast = array_sum(array_map(function($data) { return $data['data']['ytd']['last_year']['net_income']; }, $netIncomeData));
                                         echo number_format($totalYTDNetIncomeLast, 2);
                                         ?>
                                     </td>
                                 </tr>
                             </tbody>
                         </table>
                     </div>
                 <?php else: ?>
                     <div class="text-center py-8">
                         <i class="fas fa-chart-line text-4xl text-gray-300 mb-4"></i>
                         <p class="text-gray-500">No net income data available for YTD comparison</p>
                     </div>
                 <?php endif; ?>
             </div>

             <!-- Summary Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                <?php
                // Calculate totals
                $totalMonthlyReservations = 0;
                $totalMonthlyRevenue = 0;
                $totalMonthlyDays = 0;
                $totalLastMonthReservations = 0;
                $totalLastMonthRevenue = 0;
                $totalLastMonthDays = 0;
                $totalYTDReservations = 0;
                $totalYTDRevenue = 0;
                $totalYTDDays = 0;
                $totalLastYTDReservations = 0;
                $totalLastYTDRevenue = 0;
                $totalLastYTDDays = 0;

                foreach ($supplierData as $data) {
                    $totalMonthlyReservations += $data['monthly']['this_year']['reservations'];
                    $totalMonthlyRevenue += $data['monthly']['this_year']['revenue'];
                    $totalMonthlyDays += $data['monthly']['this_year']['days'];
                    $totalLastMonthReservations += $data['monthly']['last_year']['reservations'];
                    $totalLastMonthRevenue += $data['monthly']['last_year']['revenue'];
                    $totalLastMonthDays += $data['monthly']['last_year']['days'];
                    $totalYTDReservations += $data['ytd']['this_year']['reservations'];
                    $totalYTDRevenue += $data['ytd']['this_year']['revenue'];
                    $totalYTDDays += $data['ytd']['this_year']['days'];
                    $totalLastYTDReservations += $data['ytd']['last_year']['reservations'];
                    $totalLastYTDRevenue += $data['ytd']['last_year']['revenue'];
                    $totalLastYTDDays += $data['ytd']['last_year']['days'];
                }

                // Calculate overall percentage changes
                $monthResChange = $totalLastMonthReservations > 0 ? (($totalMonthlyReservations - $totalLastMonthReservations) / $totalLastMonthReservations) * 100 : 0;
                $monthRevChange = $totalLastMonthRevenue > 0 ? (($totalMonthlyRevenue - $totalLastMonthRevenue) / $totalLastMonthRevenue) * 100 : 0;
                $ytdResChange = $totalLastYTDReservations > 0 ? (($totalYTDReservations - $totalLastYTDReservations) / $totalLastYTDReservations) * 100 : 0;
                $ytdRevChange = $totalLastYTDRevenue > 0 ? (($totalYTDRevenue - $totalLastYTDRevenue) / $totalLastYTDRevenue) * 100 : 0;
                ?>
                
                <div class="bg-white rounded-lg shadow-md p-4">
                    <h4 class="text-sm font-medium text-gray-600 mb-2"><?php echo $monthNames[$selectedMonth]; ?> <?php echo $selectedYear; ?> Reservations</h4>
                    <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($totalMonthlyReservations); ?></p>
                    <p class="text-sm <?php echo $monthResChange >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                        <?php echo ($monthResChange >= 0 ? '↗' : '↘'); ?> <?php echo number_format($monthResChange, 1); ?>% vs last year
                    </p>
                </div>

                <div class="bg-white rounded-lg shadow-md p-4">
                    <h4 class="text-sm font-medium text-gray-600 mb-2"><?php echo $monthNames[$selectedMonth]; ?> <?php echo $selectedYear; ?> Revenue</h4>
                    <p class="text-2xl font-semibold text-gray-900">$<?php echo number_format($totalMonthlyRevenue, 2); ?></p>
                    <p class="text-sm <?php echo $monthRevChange >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                        <?php echo ($monthRevChange >= 0 ? '↗' : '↘'); ?> <?php echo number_format($monthRevChange, 1); ?>% vs last year
                    </p>
                </div>

                                 <div class="bg-white rounded-lg shadow-md p-4">
                     <h4 class="text-sm font-medium text-gray-600 mb-2">YTD <?php echo $selectedYear; ?> (through <?php echo $monthNames[$selectedMonth]; ?>) Reservations</h4>
                     <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($totalYTDReservations); ?></p>
                     <p class="text-sm <?php echo $ytdResChange >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                         <?php echo ($ytdResChange >= 0 ? '↗' : '↘'); ?> <?php echo number_format($ytdResChange, 1); ?>% vs last year
                     </p>
                 </div>

                 <div class="bg-white rounded-lg shadow-md p-4">
                     <h4 class="text-sm font-medium text-gray-600 mb-2">YTD <?php echo $selectedYear; ?> (through <?php echo $monthNames[$selectedMonth]; ?>) Revenue</h4>
                     <p class="text-2xl font-semibold text-gray-900">$<?php echo number_format($totalYTDRevenue, 2); ?></p>
                     <p class="text-sm <?php echo $ytdRevChange >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                         <?php echo ($ytdRevChange >= 0 ? '↗' : '↘'); ?> <?php echo number_format($ytdRevChange, 1); ?>% vs last year
                     </p>
                 </div>
            </div>
        </main>
    </div>
</body>
</html>
