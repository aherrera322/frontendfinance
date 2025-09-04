<?php
require_once 'auth/config.php';

// Get database connection
$pdo = getReservationsDB();

echo "<h2>Debug Advance Booking Calculation</h2>";

// Check sample data with res_day and pickup_date
echo "<h3>Sample Records with res_day and pickup_date:</h3>";
$stmt = $pdo->prepare("
    SELECT res_number, voucher, res_day, pickup_date, 
           CASE 
               WHEN res_day != '' AND pickup_date != '' 
               AND res_day != '0000-00-00' AND pickup_date != '0000-00-00'
               AND res_day REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'
               AND pickup_date REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'
               AND STR_TO_DATE(res_day, '%Y-%m-%d') IS NOT NULL
               AND STR_TO_DATE(pickup_date, '%Y-%m-%d') IS NOT NULL
               AND STR_TO_DATE(pickup_date, '%Y-%m-%d') >= STR_TO_DATE(res_day, '%Y-%m-%d')
               THEN DATEDIFF(STR_TO_DATE(pickup_date, '%Y-%m-%d'), STR_TO_DATE(res_day, '%Y-%m-%d'))
               ELSE 'INVALID'
           END as days_diff
    FROM aero_res_22 
    WHERE res_day IS NOT NULL AND pickup_date IS NOT NULL
    LIMIT 20
");
$stmt->execute();
$sampleData = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Res Number</th><th>Voucher</th><th>Res Day</th><th>Pickup Date</th><th>Days Diff</th></tr>";
foreach ($sampleData as $row) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['res_number']) . "</td>";
    echo "<td>" . htmlspecialchars($row['voucher']) . "</td>";
    echo "<td>" . htmlspecialchars($row['res_day']) . "</td>";
    echo "<td>" . htmlspecialchars($row['pickup_date']) . "</td>";
    echo "<td>" . htmlspecialchars($row['days_diff']) . "</td>";
    echo "</tr>";
}
echo "</table>";

// Check date format distribution
echo "<h3>Date Format Analysis:</h3>";
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_records,
        COUNT(CASE WHEN res_day != '' AND res_day IS NOT NULL THEN 1 END) as res_day_not_empty,
        COUNT(CASE WHEN pickup_date != '' AND pickup_date IS NOT NULL THEN 1 END) as pickup_date_not_empty,
        COUNT(CASE WHEN res_day = '' OR res_day IS NULL THEN 1 END) as res_day_empty,
        COUNT(CASE WHEN pickup_date = '' OR pickup_date IS NULL THEN 1 END) as pickup_date_empty,
        COUNT(CASE WHEN res_day = '0000-00-00' THEN 1 END) as res_day_zero,
        COUNT(CASE WHEN pickup_date = '0000-00-00' THEN 1 END) as pickup_date_zero,
        COUNT(CASE WHEN res_day REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$' THEN 1 END) as res_day_valid_format,
        COUNT(CASE WHEN pickup_date REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$' THEN 1 END) as pickup_date_valid_format,
        COUNT(CASE WHEN STR_TO_DATE(res_day, '%Y-%m-%d') IS NOT NULL THEN 1 END) as res_day_valid_date,
        COUNT(CASE WHEN STR_TO_DATE(pickup_date, '%Y-%m-%d') IS NOT NULL THEN 1 END) as pickup_date_valid_date
    FROM aero_res_22
");
$stmt->execute();
$formatAnalysis = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Metric</th><th>Count</th></tr>";
foreach ($formatAnalysis as $key => $value) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($key) . "</td>";
    echo "<td>" . number_format($value) . "</td>";
    echo "</tr>";
}
echo "</table>";

// Test the actual calculation
echo "<h3>Advance Booking Calculation Test:</h3>";
$stmt = $pdo->prepare("
    SELECT 
        AVG(CASE 
            WHEN res_day != '' AND pickup_date != '' 
            AND res_day != '0000-00-00' AND pickup_date != '0000-00-00'
            AND res_day REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'
            AND pickup_date REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'
            AND STR_TO_DATE(res_day, '%Y-%m-%d') IS NOT NULL
            AND STR_TO_DATE(pickup_date, '%Y-%m-%d') IS NOT NULL
            AND STR_TO_DATE(pickup_date, '%Y-%m-%d') >= STR_TO_DATE(res_day, '%Y-%m-%d')
            THEN DATEDIFF(STR_TO_DATE(pickup_date, '%Y-%m-%d'), STR_TO_DATE(res_day, '%Y-%m-%d'))
            ELSE NULL
        END) as avg_advance_days,
        COUNT(CASE 
            WHEN res_day != '' AND pickup_date != '' 
            AND res_day != '0000-00-00' AND pickup_date != '0000-00-00'
            AND res_day REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'
            AND pickup_date REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'
            AND STR_TO_DATE(res_day, '%Y-%m-%d') IS NOT NULL
            AND STR_TO_DATE(pickup_date, '%Y-%m-%d') IS NOT NULL
            AND STR_TO_DATE(pickup_date, '%Y-%m-%d') >= STR_TO_DATE(res_day, '%Y-%m-%d')
            THEN 1
            ELSE NULL
        END) as total_with_advance_booking
    FROM aero_res_22
");
$stmt->execute();
$calculationTest = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Metric</th><th>Value</th></tr>";
echo "<tr><td>Average Advance Days</td><td>" . round($calculationTest['avg_advance_days'] ?? 0, 1) . "</td></tr>";
echo "<tr><td>Total with Advance Booking</td><td>" . number_format($calculationTest['total_with_advance_booking'] ?? 0) . "</td></tr>";
echo "</table>";

// Check for invalid date formats
echo "<h3>Sample Invalid Date Formats:</h3>";
$stmt = $pdo->prepare("
    SELECT res_number, voucher, res_day, pickup_date
    FROM aero_res_22 
    WHERE (res_day IS NOT NULL AND res_day != '' AND res_day != '0000-00-00')
       OR (pickup_date IS NOT NULL AND pickup_date != '' AND pickup_date != '0000-00-00')
    AND (
        res_day NOT REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'
        OR pickup_date NOT REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'
        OR STR_TO_DATE(res_day, '%Y-%m-%d') IS NULL
        OR STR_TO_DATE(pickup_date, '%Y-%m-%d') IS NULL
    )
    LIMIT 10
");
$stmt->execute();
$invalidDates = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($invalidDates)) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Res Number</th><th>Voucher</th><th>Res Day</th><th>Pickup Date</th></tr>";
    foreach ($invalidDates as $row) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['res_number']) . "</td>";
        echo "<td>" . htmlspecialchars($row['voucher']) . "</td>";
        echo "<td>" . htmlspecialchars($row['res_day']) . "</td>";
        echo "<td>" . htmlspecialchars($row['pickup_date']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No invalid date formats found in sample.</p>";
}
?>



