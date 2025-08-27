<?php
require_once 'auth/config.php';

// Get database connection
$pdo = getReservationsDB();

// Handle date range filtering
$fromDate = $_GET['from_date'] ?? '';
$untilDate = $_GET['until_date'] ?? '';

// Build WHERE clause for date filtering using app_day column with YYYY-MM-DD format only
$dateFilter = '';
$params = [];

if (!empty($fromDate) && !empty($untilDate)) {
    $dateFilter = "WHERE app_day BETWEEN ? AND ?";
    $params = [$fromDate, $untilDate];
} elseif (!empty($fromDate)) {
    $dateFilter = "WHERE app_day >= ?";
    $params = [$fromDate];
} elseif (!empty($untilDate)) {
    $dateFilter = "WHERE app_day <= ?";
    $params = [$untilDate];
}

try {
    // Get all data with date filter
    $stmt = $pdo->prepare("SELECT * FROM aero_res_22 " . $dateFilter . " ORDER BY app_day DESC");
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($data)) {
        die("No data found for the selected date range.");
    }
    
    // Set headers for CSV download
    $filename = 'aerovision_report_' . date('Y-m-d_H-i-s');
    if (!empty($fromDate) || !empty($untilDate)) {
        $filename .= '_filtered';
    }
    $filename .= '.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for proper Excel encoding
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Write headers
    if (!empty($data)) {
        fputcsv($output, array_keys($data[0]));
    }
    
    // Write data rows
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    
} catch (Exception $e) {
    die("Error generating report: " . $e->getMessage());
}
?>
