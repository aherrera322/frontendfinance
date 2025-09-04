<?php
require_once 'auth/config.php';

// Get database connection
$pdo = getReservationsDB();

echo "<h2>Check aero_res_22 Date Columns</h2>";

// Check sample data
echo "<h3>Sample Records:</h3>";
$stmt = $pdo->prepare("SELECT res_number, voucher, res_day, pickup_date FROM aero_res_22 LIMIT 10");
$stmt->execute();
$sampleData = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Res Number</th><th>Voucher</th><th>Res Day</th><th>Pickup Date</th></tr>";
foreach ($sampleData as $row) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['res_number']) . "</td>";
    echo "<td>" . htmlspecialchars($row['voucher']) . "</td>";
    echo "<td>" . htmlspecialchars($row['res_day']) . "</td>";
    echo "<td>" . htmlspecialchars($row['pickup_date']) . "</td>";
    echo "</tr>";
}
echo "</table>";

// Check if columns exist
echo "<h3>Table Structure:</h3>";
$stmt = $pdo->prepare("DESCRIBE aero_res_22");
$stmt->execute();
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
foreach ($columns as $column) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
    echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
    echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
    echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
    echo "<td>" . htmlspecialchars($column['Default']) . "</td>";
    echo "</tr>";
}
echo "</table>";

// Count records with date data
echo "<h3>Date Data Counts:</h3>";
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_records,
        COUNT(CASE WHEN res_day IS NOT NULL AND res_day != '' THEN 1 END) as res_day_count,
        COUNT(CASE WHEN pickup_date IS NOT NULL AND pickup_date != '' THEN 1 END) as pickup_date_count,
        COUNT(CASE WHEN res_day IS NOT NULL AND res_day != '' AND pickup_date IS NOT NULL AND pickup_date != '' THEN 1 END) as both_dates_count
    FROM aero_res_22
");
$stmt->execute();
$counts = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Metric</th><th>Count</th></tr>";
foreach ($counts as $key => $value) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($key) . "</td>";
    echo "<td>" . number_format($value) . "</td>";
    echo "</tr>";
}
echo "</table>";
?>



