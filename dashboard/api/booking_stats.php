<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../../auth/config.php';

try {
    $pdo = getReservationsDB();
    
    // Convert Excel date to MySQL date function
    // Excel dates are stored as decimal numbers, need to convert to MySQL date
    $excelToDate = "DATE(FROM_UNIXTIME((res_date - 25569) * 86400))";
    
    // Base condition to exclude cancelled reservations
    $baseCondition = "WHERE res_date IS NOT NULL AND (status IS NULL OR status != 'Cancelled')";
    
    // Get total bookings using res_date (excluding cancelled)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM zb_reservations {$baseCondition}");
    $totalBookings = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get yesterday's bookings using res_date (excluding cancelled)
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM zb_reservations WHERE {$excelToDate} = DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND (status IS NULL OR status != 'Cancelled')");
    $stmt->execute();
    $yesterdayBookings = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get last week's bookings using res_date (last 7 days, excluding cancelled)
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM zb_reservations WHERE {$excelToDate} >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND (status IS NULL OR status != 'Cancelled')");
    $stmt->execute();
    $lastWeekBookings = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get last month's bookings using res_date (previous calendar month, excluding cancelled)
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM zb_reservations WHERE {$excelToDate} >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01') AND {$excelToDate} < DATE_FORMAT(CURDATE(), '%Y-%m-01') AND (status IS NULL OR status != 'Cancelled')");
    $stmt->execute();
    $lastMonthBookings = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get YTD bookings using res_date (current year, excluding cancelled)
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM zb_reservations WHERE YEAR({$excelToDate}) = YEAR(CURDATE()) AND (status IS NULL OR status != 'Cancelled')");
    $stmt->execute();
    $ytdBookings = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Calculate total revenue using api_value column (excluding cancelled)
    $stmt = $pdo->query("SELECT SUM(api_value) as total FROM zb_reservations WHERE api_value IS NOT NULL AND (status IS NULL OR status != 'Cancelled')");
    $totalRevenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Calculate commission based on payment mode and client rates
    $clientsPdo = getClientsDB();
    $totalCommission = 0;
    
    // Get all reservations with payment info
    $stmt = $pdo->query("SELECT agency, pay_mode, api_value, credit FROM zb_reservations WHERE api_value IS NOT NULL AND credit IS NOT NULL AND (status IS NULL OR status != 'Cancelled')");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $commissionBase = $row['api_value'] - $row['credit'];
        $payMode = $row['pay_mode'];
        $agency = $row['agency'];
        
        // Get client commission rates
        $clientStmt = $clientsPdo->prepare("SELECT commission_percent_credit_card, commission_percent_credit_limit FROM clients WHERE agency_name = ?");
        $clientStmt->execute([$agency]);
        $client = $clientStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($client) {
            $commissionRate = 0;
            
            // Determine commission rate based on payment mode
            if ($payMode === 'CL') {
                $commissionRate = $client['commission_percent_credit_limit'] / 100;
            } elseif (strpos($payMode, 'CC') === 0) {
                $commissionRate = $client['commission_percent_credit_card'] / 100;
            }
            
            $totalCommission += $commissionBase * $commissionRate;
        }
    }
    
    // Get cancelled reservations statistics
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM zb_reservations WHERE {$excelToDate} = DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND status = 'Cancelled'");
    $stmt->execute();
    $yesterdayCancelled = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM zb_reservations WHERE {$excelToDate} >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND status = 'Cancelled'");
    $stmt->execute();
    $lastWeekCancelled = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM zb_reservations WHERE {$excelToDate} >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01') AND {$excelToDate} < DATE_FORMAT(CURDATE(), '%Y-%m-01') AND status = 'Cancelled'");
    $stmt->execute();
    $lastMonthCancelled = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM zb_reservations WHERE YEAR({$excelToDate}) = YEAR(CURDATE()) AND status = 'Cancelled'");
    $stmt->execute();
    $ytdCancelled = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get prepaid vs non-prepaid statistics
    $stmt = $pdo->query("SELECT 
        prepay,
        COUNT(*) as count,
        SUM(api_value) as revenue
        FROM zb_reservations 
        WHERE api_value IS NOT NULL AND (status IS NULL OR status != 'Cancelled')
        GROUP BY prepay");
    
    $prepaidStats = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $prepaidStats[$row['prepay']] = [
            'count' => (int)$row['count'],
            'revenue' => (float)$row['revenue']
        ];
    }
    
    // Calculate CPC statistics with time-based breakdowns
    // Total CPC
    $stmt = $pdo->query("SELECT COUNT(*) as count, SUM(CAST(REPLACE(REPLACE(cpc, '$', ''), ',', '') AS DECIMAL(10,2))) as revenue FROM zb_reservations WHERE cpc IS NOT NULL AND cpc != '$0.00' AND cpc != '0.00' AND (status IS NULL OR status != 'Cancelled')");
    $cpcStats = $stmt->fetch(PDO::FETCH_ASSOC);
    $cpcBookings = (int)$cpcStats['count'];
    $cpcRevenue = (float)$cpcStats['revenue'];
    $cpcPercentage = $totalBookings > 0 ? ($cpcBookings / $totalBookings) * 100 : 0;
    
    // Yesterday CPC
    $stmt = $pdo->prepare("SELECT COUNT(*) as count, SUM(CAST(REPLACE(REPLACE(cpc, '$', ''), ',', '') AS DECIMAL(10,2))) as revenue FROM zb_reservations WHERE {$excelToDate} = DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND cpc IS NOT NULL AND cpc != '$0.00' AND cpc != '0.00' AND (status IS NULL OR status != 'Cancelled')");
    $stmt->execute();
    $yesterdayCpc = $stmt->fetch(PDO::FETCH_ASSOC);
    $yesterdayCpcBookings = (int)$yesterdayCpc['count'];
    $yesterdayCpcRevenue = (float)$yesterdayCpc['revenue'];
    
    // Last Week CPC
    $stmt = $pdo->prepare("SELECT COUNT(*) as count, SUM(CAST(REPLACE(REPLACE(cpc, '$', ''), ',', '') AS DECIMAL(10,2))) as revenue FROM zb_reservations WHERE {$excelToDate} >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND cpc IS NOT NULL AND cpc != '$0.00' AND cpc != '0.00' AND (status IS NULL OR status != 'Cancelled')");
    $stmt->execute();
    $lastWeekCpc = $stmt->fetch(PDO::FETCH_ASSOC);
    $lastWeekCpcBookings = (int)$lastWeekCpc['count'];
    $lastWeekCpcRevenue = (float)$lastWeekCpc['revenue'];
    
    // Last Month CPC
    $stmt = $pdo->prepare("SELECT COUNT(*) as count, SUM(CAST(REPLACE(REPLACE(cpc, '$', ''), ',', '') AS DECIMAL(10,2))) as revenue FROM zb_reservations WHERE {$excelToDate} >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01') AND {$excelToDate} < DATE_FORMAT(CURDATE(), '%Y-%m-01') AND cpc IS NOT NULL AND cpc != '$0.00' AND cpc != '0.00' AND (status IS NULL OR status != 'Cancelled')");
    $stmt->execute();
    $lastMonthCpc = $stmt->fetch(PDO::FETCH_ASSOC);
    $lastMonthCpcBookings = (int)$lastMonthCpc['count'];
    $lastMonthCpcRevenue = (float)$lastMonthCpc['revenue'];
    
    // YTD CPC
    $stmt = $pdo->prepare("SELECT COUNT(*) as count, SUM(CAST(REPLACE(REPLACE(cpc, '$', ''), ',', '') AS DECIMAL(10,2))) as revenue FROM zb_reservations WHERE YEAR({$excelToDate}) = YEAR(CURDATE()) AND cpc IS NOT NULL AND cpc != '$0.00' AND cpc != '0.00' AND (status IS NULL OR status != 'Cancelled')");
    $stmt->execute();
    $ytdCpc = $stmt->fetch(PDO::FETCH_ASSOC);
    $ytdCpcBookings = (int)$ytdCpc['count'];
    $ytdCpcRevenue = (float)$ytdCpc['revenue'];
    
    // Calculate percentages after getting all data
    $totalAllBookings = $totalBookings + $ytdCancelled; // Total including cancelled
    $cancellationRate = $totalAllBookings > 0 ? ($ytdCancelled / $totalAllBookings) * 100 : 0;
    $prepaidPercentage = $totalBookings > 0 ? (($prepaidStats['Yes']['count'] ?? 0) / $totalBookings) * 100 : 0;
    $nonPrepaidPercentage = $totalBookings > 0 ? (($prepaidStats['No']['count'] ?? 0) / $totalBookings) * 100 : 0;
    
    $stats = [
        'total_bookings' => (int)$totalBookings,
        'yesterday_bookings' => (int)$yesterdayBookings,
        'last_week_bookings' => (int)$lastWeekBookings,
        'last_month_bookings' => (int)$lastMonthBookings,
        'ytd_bookings' => (int)$ytdBookings,
        'total_revenue' => (float)$totalRevenue,
        'total_commission' => (float)$totalCommission,
        'yesterday_cancelled' => (int)$yesterdayCancelled,
        'last_week_cancelled' => (int)$lastWeekCancelled,
        'last_month_cancelled' => (int)$lastMonthCancelled,
        'ytd_cancelled' => (int)$ytdCancelled,
        'prepaid_stats' => $prepaidStats,
        'cpc_stats' => [
            'bookings' => $cpcBookings,
            'revenue' => $cpcRevenue,
            'percentage' => round($cpcPercentage, 1),
            'yesterday_bookings' => $yesterdayCpcBookings,
            'yesterday_revenue' => $yesterdayCpcRevenue,
            'last_week_bookings' => $lastWeekCpcBookings,
            'last_week_revenue' => $lastWeekCpcRevenue,
            'last_month_bookings' => $lastMonthCpcBookings,
            'last_month_revenue' => $lastMonthCpcRevenue,
            'ytd_bookings' => $ytdCpcBookings,
            'ytd_revenue' => $ytdCpcRevenue
        ],
        'percentages' => [
            'cancellation_rate' => round($cancellationRate, 1),
            'prepaid_percentage' => round($prepaidPercentage, 1),
            'non_prepaid_percentage' => round($nonPrepaidPercentage, 1)
        ],
        'last_updated' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $stats
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
