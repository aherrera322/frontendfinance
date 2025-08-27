<?php
require_once 'auth/config.php';

try {
    $reservationsPdo = getReservationsDB();
    
    // Get filter parameters
    $dateFrom = $_GET['date_from'] ?? '';
    $dateTo = $_GET['date_to'] ?? '';
    $supplier = $_GET['supplier'] ?? '';
    $payment = $_GET['payment'] ?? '';
    $dateColumn = $_GET['date_column'] ?? 'app_day';
    
         // Build the query
     $query = "
         SELECT 
             res_number,
             voucher,
             supplier,
             agency,
             name,
             value,
             discount,
             admin_fee,
             cpc,
             payment,
             $dateColumn as report_date
         FROM aero_res_22 
         WHERE 1=1
     ";
    
    $params = [];
    
    // Add date filter
    if (!empty($dateFrom)) {
        $query .= " AND $dateColumn >= ?";
        $params[] = $dateFrom;
    }
    
    if (!empty($dateTo)) {
        $query .= " AND $dateColumn <= ?";
        $params[] = $dateTo;
    }
    
    // Add supplier filter
    if (!empty($supplier)) {
        $query .= " AND supplier = ?";
        $params[] = $supplier;
    }
    
    // Add payment filter
    if (!empty($payment)) {
        $query .= " AND payment = ?";
        $params[] = $payment;
    }
    
    $query .= " ORDER BY $dateColumn DESC, res_number ASC";
    
    $stmt = $reservationsPdo->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get unique suppliers for filter dropdown
    $supplierStmt = $reservationsPdo->query("SELECT DISTINCT supplier FROM aero_res_22 WHERE supplier IS NOT NULL AND supplier != '' ORDER BY supplier");
    $suppliers = $supplierStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get unique payment methods for filter dropdown
    $paymentStmt = $reservationsPdo->query("SELECT DISTINCT payment FROM aero_res_22 WHERE payment IS NOT NULL AND payment != '' ORDER BY payment");
    $payments = $paymentStmt->fetchAll(PDO::FETCH_COLUMN);
    
         // Get partner commissions for net calculation
     $partnerCommissions = [];
     try {
         $partnersPdo = getPartnersDB();
         $stmt = $partnersPdo->query("SELECT name, commission_percent FROM partners WHERE commission_percent IS NOT NULL AND commission_percent > 0");
         while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
             $partnerCommissions[$row['name']] = $row['commission_percent'];
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
     
     // Get client commissions from clients database
     $clientCommissions = [];
     try {
         $clientsPdo = getClientsDB();
         $stmt = $clientsPdo->query("SELECT name, commission_percent_credit_card, commission_percent_credit_limit FROM clients WHERE status = 'active'");
         while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
             $clientCommissions[$row['name']] = [
                 'cc_percent' => $row['commission_percent_credit_card'] ?? 15.0,
                 'credit_limit_percent' => $row['commission_percent_credit_limit'] ?? 15.0
             ];
         }
     } catch (Exception $e) {
         // If clients table doesn't exist or has issues, use default commissions
         $clientCommissions = [];
     }
    
    // Define partner groupings to match suppliers to partner commissions
    $partnerGroups = [
        'Zimple Rentals' => ['Alamo', 'National', 'Enterprise'],
        'Aerovision Inc' => ['Avis', 'Budget'],
        'Hertz Group' => ['Hertz', 'Dollar', 'Thrifty'],
        'Europcar' => ['Europcar']
    ];
    
    // Calculate totals
    $totalValue = 0;
    $totalDiscount = 0;
    $totalAdminFee = 0;
    $totalCpc = 0;
    $totalCalculated = 0;
    
         foreach ($results as $row) {
         $totalValue += $row['value'] ?? 0;
         $totalDiscount += $row['discount'] ?? 0;
         $totalAdminFee += $row['admin_fee'] ?? 0;
         $totalCpc += $row['cpc'] ?? 0;
         
         // Find the partner group for this supplier
         $supplier = $row['supplier'] ?? '';
         $partnerCommission = 10.0; // Default 10% if not found
         
         foreach ($partnerGroups as $partnerName => $suppliers) {
             if (in_array($supplier, $suppliers)) {
                 $partnerCommission = $partnerCommissions[$partnerName] ?? 10.0;
                 break;
             }
         }
         
         // Determine client commission based on payment method
         $paymentMethod = strtoupper(trim($row['payment'] ?? ''));
         $isCreditCard = $paymentMethod === 'CC' || stripos($paymentMethod, 'CREDIT') !== false || stripos($paymentMethod, 'CARD') !== false;
         $isNA = stripos($paymentMethod, 'N/A') !== false;
         
         // Use CC% for credit card, Credit Limit % for non-credit card or N/A
         $clientCommission = 15.0; // Default 15%
         
         // Try to find the specific agency/client in the clients database
         $foundClient = false;
         
         // Determine the agency name from available fields (priority: agency > name)
         $agencyName = $row['agency'] ?? $row['name'] ?? '';
         
         foreach ($clientCommissions as $clientName => $commissions) {
             // Check if this client name matches the agency (case-insensitive)
             if (strcasecmp(trim($clientName), trim($agencyName)) === 0 || 
                 stripos(trim($clientName), trim($agencyName)) !== false || 
                 stripos(trim($agencyName), trim($clientName)) !== false) {
                 if ($isCreditCard) {
                     $clientCommission = $commissions['cc_percent'] ?? 15.0;
                 } else {
                     $clientCommission = $commissions['credit_limit_percent'] ?? 15.0;
                 }
                 $foundClient = true;
                 break;
             }
         }
         
         // If no specific client found, use default
         if (!$foundClient) {
             $clientCommission = 15.0; // Default 15%
         }
         
         // Calculate A: Value × Partner Commission
         $partnerAmount = ($row['value'] ?? 0) * ($partnerCommission / 100);
         
         // Calculate B: Value × Client Commission (based on payment method)
         $clientAmount = ($row['value'] ?? 0) * ($clientCommission / 100);
         
         // Calculate total: A - B - discount + admin_fee + cpc
         $baseTotal = $partnerAmount - $clientAmount - ($row['discount'] ?? 0) + ($row['admin_fee'] ?? 0) + ($row['cpc'] ?? 0);
         
         // Use base total (no additional 5% since it's already in admin_fee)
         $calculatedTotal = $baseTotal;
         
         $totalCalculated += $calculatedTotal;
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
    <title>Aero Res 22 Report</title>
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
    <style>
        /* Sticky table headers */
        .sticky-table-container {
            max-height: 70vh;
            overflow-y: auto;
            overflow-x: auto;
        }
        
        .sticky-table-container thead th {
            position: sticky;
            top: 0;
            background-color: #f9fafb;
            z-index: 10;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        
        /* Ensure proper border rendering for sticky headers */
        .sticky-table-container thead th:not(:last-child) {
            border-right: 1px solid #e5e7eb;
        }
        
        /* Add shadow effect for better visual separation */
        .sticky-table-container thead::after {
            content: '';
            position: absolute;
            left: 0;
            right: 0;
            top: 100%;
            height: 2px;
            background: linear-gradient(to bottom, rgba(0,0,0,0.1), transparent);
            pointer-events: none;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-primary text-white shadow-lg">
            <div class="container mx-auto px-6 py-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <i class="fas fa-file-alt text-2xl"></i>
                        <h1 class="text-2xl font-bold">Aero Res 22 Report</h1>
                    </div>
                    <nav class="flex items-center space-x-6">
                        <a href="index.html" class="hover:text-yellow-200 transition-colors">Home</a>
                        <a href="dashboard_aerovision.php" class="hover:text-yellow-200 transition-colors">Aerovision Dashboard</a>
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

            <!-- Filter Section -->
            <div class="bg-white rounded-lg shadow-md p-4 mb-4">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Filter Options</h3>
                <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label for="date_column" class="block text-sm font-medium text-gray-700 mb-1">Date Column</label>
                        <select id="date_column" name="date_column" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="res_day" <?php echo $dateColumn == 'res_day' ? 'selected' : ''; ?>>Reservation Day</option>
                            <option value="app_day" <?php echo $dateColumn == 'app_day' ? 'selected' : ''; ?>>Approval Day</option>
                            <option value="pickup_date" <?php echo $dateColumn == 'pickup_date' ? 'selected' : ''; ?>>Pickup Date</option>
                        </select>
                    </div>
                    <div>
                        <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                        <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>" 
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div>
                        <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                        <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>" 
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div>
                        <label for="supplier" class="block text-sm font-medium text-gray-700 mb-1">Supplier</label>
                        <select id="supplier" name="supplier" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="">All Suppliers</option>
                            <?php foreach ($suppliers as $sup): ?>
                                <option value="<?php echo htmlspecialchars($sup); ?>" <?php echo $supplier == $sup ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($sup); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="payment" class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                        <select id="payment" name="payment" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="">All Payment Methods</option>
                            <?php foreach ($payments as $pay): ?>
                                <option value="<?php echo htmlspecialchars($pay); ?>" <?php echo $payment == $pay ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($pay); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex gap-2 items-end">
                        <button type="submit" class="bg-primary text-white px-4 py-2 rounded-md hover:bg-primary/90 transition-colors">
                            Apply Filter
                        </button>
                        <a href="aero_res_22_report.php" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition-colors">
                            Reset
                        </a>
                    </div>
                </form>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-4">
                <div class="bg-white rounded-lg shadow-md p-4">
                    <h4 class="text-sm font-medium text-gray-600 mb-2">Total Records</h4>
                    <p class="text-2xl font-semibold text-gray-900"><?php echo number_format(count($results)); ?></p>
                </div>
                <div class="bg-white rounded-lg shadow-md p-4">
                    <h4 class="text-sm font-medium text-gray-600 mb-2">Total Value</h4>
                    <p class="text-2xl font-semibold text-gray-900">$<?php echo number_format($totalValue, 2); ?></p>
                </div>
                <div class="bg-white rounded-lg shadow-md p-4">
                    <h4 class="text-sm font-medium text-gray-600 mb-2">Total Discount</h4>
                    <p class="text-2xl font-semibold text-gray-900">$<?php echo number_format($totalDiscount, 2); ?></p>
                </div>
                <div class="bg-white rounded-lg shadow-md p-4">
                    <h4 class="text-sm font-medium text-gray-600 mb-2">Total Admin Fee</h4>
                    <p class="text-2xl font-semibold text-gray-900">$<?php echo number_format($totalAdminFee, 2); ?></p>
                </div>
                <div class="bg-white rounded-lg shadow-md p-4">
                    <h4 class="text-sm font-medium text-gray-600 mb-2">Total CPC</h4>
                    <p class="text-2xl font-semibold text-gray-900">$<?php echo number_format($totalCpc, 2); ?></p>
                </div>
            </div>

                         <!-- Final Total Card -->
             <div class="bg-blue-50 border border-blue-200 rounded-lg shadow-md p-4 mb-4">
                 <h4 class="text-lg font-semibold text-blue-900 mb-2">Final Calculated Total</h4>
                 <p class="text-3xl font-bold text-blue-900">$<?php echo number_format($totalCalculated, 2); ?></p>
                                   <p class="text-sm text-blue-700 mt-1">
                      (A: Value × Partner %) - (B: Value × Commission %) - Discount + Admin Fee + CPC
                  </p>
             </div>

            <!-- Results Table -->
            <div class="bg-white rounded-lg shadow-md p-4">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                    Report Results (<?php echo number_format(count($results)); ?> records)
                </h3>
                
                <?php if (!empty($results)): ?>
                    <div class="sticky-table-container">
                        <table class="min-w-full divide-y divide-gray-200">
                                                         <thead class="bg-gray-50">
                                                                  <tr>
                                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Res Number</th>
                                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Voucher</th>
                                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Agency</th>
                                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                      <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Value</th>
                                      <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Discount</th>
                                      <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Admin Fee</th>
                                      <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">CPC</th>
                                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment</th>
                                      <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider bg-green-50">Partner %</th>
                                      <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider bg-yellow-50">Commission %</th>
                                      <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider bg-blue-50">Calculated Total</th>
                                  </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                                                 <?php foreach ($results as $row): ?>
                                     <?php
                                     // Find the partner group for this supplier
                                     $supplier = $row['supplier'] ?? '';
                                     $partnerCommission = 10.0; // Default 10% if not found
                                     
                                     foreach ($partnerGroups as $partnerName => $suppliers) {
                                         if (in_array($supplier, $suppliers)) {
                                             $partnerCommission = $partnerCommissions[$partnerName] ?? 10.0;
                                             break;
                                         }
                                     }
                                     
                                     // Determine client commission based on payment method
                                     $paymentMethod = strtoupper(trim($row['payment'] ?? ''));
                                     $isCreditCard = $paymentMethod === 'CC' || stripos($paymentMethod, 'CREDIT') !== false || stripos($paymentMethod, 'CARD') !== false;
                                     $isNA = stripos($paymentMethod, 'N/A') !== false;
                                     
                                     // Use CC% for credit card, Credit Limit % for non-credit card or N/A
                                     $clientCommission = 15.0; // Default 15%
                                     
                                     // Try to find the specific agency/client in the clients database
                                     $foundClient = false;
                                     
                                     // Determine the agency name from available fields (priority: agency > name)
                                     $agencyName = $row['agency'] ?? $row['name'] ?? '';
                                     
                                     foreach ($clientCommissions as $clientName => $commissions) {
                                         // Check if this client name matches the agency (case-insensitive)
                                         if (strcasecmp(trim($clientName), trim($agencyName)) === 0 || 
                                             stripos(trim($clientName), trim($agencyName)) !== false || 
                                             stripos(trim($agencyName), trim($clientName)) !== false) {
                                             if ($isCreditCard) {
                                                 $clientCommission = $commissions['cc_percent'] ?? 15.0;
                                             } else {
                                                 $clientCommission = $commissions['credit_limit_percent'] ?? 15.0;
                                             }
                                             $foundClient = true;
                                             break;
                                         }
                                     }
                                     
                                     // If no specific client found, use default
                                     if (!$foundClient) {
                                         $clientCommission = 15.0; // Default 15%
                                     }
                                     
                                     // Calculate A: Value × Partner Commission
                                     $partnerAmount = ($row['value'] ?? 0) * ($partnerCommission / 100);
                                     
                                     // Calculate B: Value × Client Commission (based on payment method)
                                     $clientAmount = ($row['value'] ?? 0) * ($clientCommission / 100);
                                     
                                     // Calculate total: A - B - discount + admin_fee + cpc
                                     $baseTotal = $partnerAmount - $clientAmount - ($row['discount'] ?? 0) + ($row['admin_fee'] ?? 0) + ($row['cpc'] ?? 0);
                                     
                                     // Use base total (no additional 5% since it's already in admin_fee)
                                     $calculatedTotal = $baseTotal;
                                     ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($row['res_number'] ?? ''); ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($row['voucher'] ?? ''); ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($row['supplier'] ?? ''); ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($agencyName); ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($row['report_date'] ?? ''); ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-right text-gray-900">
                                            $<?php echo number_format($row['value'] ?? 0, 2); ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-right text-gray-900">
                                            $<?php echo number_format($row['discount'] ?? 0, 2); ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-right text-gray-900">
                                            $<?php echo number_format($row['admin_fee'] ?? 0, 2); ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-right text-gray-900">
                                            $<?php echo number_format($row['cpc'] ?? 0, 2); ?>
                                        </td>
                                                                                 <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                             <?php echo htmlspecialchars($row['payment'] ?? ''); ?>
                                         </td>
                                         <td class="px-4 py-3 whitespace-nowrap text-sm text-center text-green-700 bg-green-50 font-medium">
                                             <?php echo number_format($partnerCommission, 1); ?>%
                                         </td>
                                         <td class="px-4 py-3 whitespace-nowrap text-sm text-center text-yellow-700 bg-yellow-50 font-medium">
                                             <?php echo number_format($clientCommission, 1); ?>%
                                             <span class="text-xs block">
                                                 <?php echo $isCreditCard ? 'CC' : 'CL'; ?>
                                             </span>
                                         </td>
                                         <td class="px-4 py-3 whitespace-nowrap text-sm text-right font-semibold text-blue-900 bg-blue-50">
                                             $<?php echo number_format($calculatedTotal, 2); ?>
                                         </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <i class="fas fa-file-alt text-4xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500">No data found matching the current filters</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
