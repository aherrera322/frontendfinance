<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../../auth/config.php';

try {
    $pdo = getReservationsDB();
    
    // Convert Excel date to MySQL date function
    $excelToDate = "DATE(FROM_UNIXTIME((res_date - 25569) * 86400))";
    
    // Get statistics from all sites combined
    $stats = [];
    
    // Get data source information
    $stmt = $pdo->query("SELECT * FROM data_sources ORDER BY source_name");
    $dataSources = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($dataSources as $source) {
        $sourceName = $source['source_name'];
        $tableName = $sourceName . '_reservations';
        
        // Check if table exists
        $stmt = $pdo->query("SHOW TABLES LIKE '$tableName'");
        if ($stmt->rowCount() > 0) {
            
            // Get total bookings for this site
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM $tableName WHERE res_date IS NOT NULL AND (status IS NULL OR status != 'Cancelled')");
            $totalBookings = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Get total revenue for this site
            $stmt = $pdo->query("SELECT SUM(api_value) as total FROM $tableName WHERE api_value IS NOT NULL AND (status IS NULL OR status != 'Cancelled')");
            $totalRevenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Get CPC statistics for this site
            $stmt = $pdo->query("SELECT COUNT(*) as count, SUM(CAST(REPLACE(REPLACE(cpc, '$', ''), ',', '') AS DECIMAL(10,2))) as revenue FROM $tableName WHERE cpc IS NOT NULL AND cpc != '$0.00' AND cpc != '0.00' AND (status IS NULL OR status != 'Cancelled')");
            $cpcStats = $stmt->fetch(PDO::FETCH_ASSOC);
            $cpcBookings = (int)$cpcStats['count'];
            $cpcRevenue = (float)$cpcStats['revenue'];
            
            // Get time-based statistics for this site
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM $tableName WHERE {$excelToDate} = DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND (status IS NULL OR status != 'Cancelled')");
            $stmt->execute();
            $yesterdayBookings = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM $tableName WHERE {$excelToDate} >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND (status IS NULL OR status != 'Cancelled')");
            $stmt->execute();
            $lastWeekBookings = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM $tableName WHERE {$excelToDate} >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01') AND {$excelToDate} < DATE_FORMAT(CURDATE(), '%Y-%m-01') AND (status IS NULL OR status != 'Cancelled')");
            $stmt->execute();
            $lastMonthBookings = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM $tableName WHERE YEAR({$excelToDate}) = YEAR(CURDATE()) AND (status IS NULL OR status != 'Cancelled')");
            $stmt->execute();
            $ytdBookings = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            // Get CPC time-based statistics
            $stmt = $pdo->prepare("SELECT COUNT(*) as count, SUM(CAST(REPLACE(REPLACE(cpc, '$', ''), ',', '') AS DECIMAL(10,2))) as revenue FROM $tableName WHERE {$excelToDate} = DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND cpc IS NOT NULL AND cpc != '$0.00' AND cpc != '0.00' AND (status IS NULL OR status != 'Cancelled')");
            $stmt->execute();
            $yesterdayCpc = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as count, SUM(CAST(REPLACE(REPLACE(cpc, '$', ''), ',', '') AS DECIMAL(10,2))) as revenue FROM $tableName WHERE {$excelToDate} >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND cpc IS NOT NULL AND cpc != '$0.00' AND cpc != '0.00' AND (status IS NULL OR status != 'Cancelled')");
            $stmt->execute();
            $lastWeekCpc = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as count, SUM(CAST(REPLACE(REPLACE(cpc, '$', ''), ',', '') AS DECIMAL(10,2))) as revenue FROM $tableName WHERE {$excelToDate} >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01') AND {$excelToDate} < DATE_FORMAT(CURDATE(), '%Y-%m-01') AND cpc IS NOT NULL AND cpc != '$0.00' AND cpc != '0.00' AND (status IS NULL OR status != 'Cancelled')");
            $stmt->execute();
            $lastMonthCpc = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as count, SUM(CAST(REPLACE(REPLACE(cpc, '$', ''), ',', '') AS DECIMAL(10,2))) as revenue FROM $tableName WHERE YEAR({$excelToDate}) = YEAR(CURDATE()) AND cpc IS NOT NULL AND cpc != '$0.00' AND cpc != '0.00' AND (status IS NULL OR status != 'Cancelled')");
            $stmt->execute();
            $ytdCpc = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stats[$sourceName] = [
                'site_name' => $source['site_name'],
                'description' => $source['description'],
                'total_bookings' => (int)$totalBookings,
                'total_revenue' => (float)$totalRevenue,
                'yesterday_bookings' => (int)$yesterdayBookings,
                'last_week_bookings' => (int)$lastWeekBookings,
                'last_month_bookings' => (int)$lastMonthBookings,
                'ytd_bookings' => (int)$ytdBookings,
                'cpc_stats' => [
                    'bookings' => $cpcBookings,
                    'revenue' => $cpcRevenue,
                    'yesterday_bookings' => (int)$yesterdayCpc['count'],
                    'yesterday_revenue' => (float)$yesterdayCpc['revenue'],
                    'last_week_bookings' => (int)$lastWeekCpc['count'],
                    'last_week_revenue' => (float)$lastWeekCpc['revenue'],
                    'last_month_bookings' => (int)$lastMonthCpc['count'],
                    'last_month_revenue' => (float)$lastMonthCpc['revenue'],
                    'ytd_bookings' => (int)$ytdCpc['count'],
                    'ytd_revenue' => (float)$ytdCpc['revenue']
                ]
            ];
        }
    }
    
    // Calculate combined totals
    $combinedStats = [
        'total_bookings' => 0,
        'total_revenue' => 0,
        'yesterday_bookings' => 0,
        'last_week_bookings' => 0,
        'last_month_bookings' => 0,
        'ytd_bookings' => 0,
        'cpc_stats' => [
            'bookings' => 0,
            'revenue' => 0,
            'yesterday_bookings' => 0,
            'yesterday_revenue' => 0,
            'last_week_bookings' => 0,
            'last_week_revenue' => 0,
            'last_month_bookings' => 0,
            'last_month_revenue' => 0,
            'ytd_bookings' => 0,
            'ytd_revenue' => 0
        ]
    ];
    
    foreach ($stats as $siteStats) {
        $combinedStats['total_bookings'] += $siteStats['total_bookings'];
        $combinedStats['total_revenue'] += $siteStats['total_revenue'];
        $combinedStats['yesterday_bookings'] += $siteStats['yesterday_bookings'];
        $combinedStats['last_week_bookings'] += $siteStats['last_week_bookings'];
        $combinedStats['last_month_bookings'] += $siteStats['last_month_bookings'];
        $combinedStats['ytd_bookings'] += $siteStats['ytd_bookings'];
        
        $combinedStats['cpc_stats']['bookings'] += $siteStats['cpc_stats']['bookings'];
        $combinedStats['cpc_stats']['revenue'] += $siteStats['cpc_stats']['revenue'];
        $combinedStats['cpc_stats']['yesterday_bookings'] += $siteStats['cpc_stats']['yesterday_bookings'];
        $combinedStats['cpc_stats']['yesterday_revenue'] += $siteStats['cpc_stats']['yesterday_revenue'];
        $combinedStats['cpc_stats']['last_week_bookings'] += $siteStats['cpc_stats']['last_week_bookings'];
        $combinedStats['cpc_stats']['last_week_revenue'] += $siteStats['cpc_stats']['last_week_revenue'];
        $combinedStats['cpc_stats']['last_month_bookings'] += $siteStats['cpc_stats']['last_month_bookings'];
        $combinedStats['cpc_stats']['last_month_revenue'] += $siteStats['cpc_stats']['last_month_revenue'];
        $combinedStats['cpc_stats']['ytd_bookings'] += $siteStats['cpc_stats']['ytd_bookings'];
        $combinedStats['cpc_stats']['ytd_revenue'] += $siteStats['cpc_stats']['ytd_revenue'];
    }
    
    $response = [
        'success' => true,
        'data' => [
            'sites' => $stats,
            'combined' => $combinedStats,
            'last_updated' => date('Y-m-d H:i:s')
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>




