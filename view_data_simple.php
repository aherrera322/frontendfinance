<?php
require_once 'auth/config.php';

try {
    $pdo = getReservationsDB();
    
    // Get all data from zimpleb_reservations table
    $stmt = $pdo->query("SELECT * FROM zimpleb_reservations ORDER BY imported_at DESC");
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}

// Helper function to safely get array values
function safeGet($array, $key, $default = '') {
    return isset($array[$key]) ? $array[$key] : $default;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZimpleB Reservations Data - Simple View</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 100%;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #117372;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 12px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #117372;
            color: white;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        tr:hover {
            background-color: #f0f0f0;
        }
        .status-confirmed { background-color: #d4edda; }
        .status-pending { background-color: #fff3cd; }
        .status-cancelled { background-color: #f8d7da; }
        .money { text-align: right; }
        .error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .summary {
            background-color: #e7f3ff;
            border: 1px solid #b3d9ff;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .summary h3 {
            margin-top: 0;
            color: #117372;
        }
        .summary-stats {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        .stat {
            background: white;
            padding: 10px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        .stat-label {
            font-size: 12px;
            color: #666;
        }
        .stat-value {
            font-size: 18px;
            font-weight: bold;
            color: #117372;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>ZimpleB Reservations Data</h1>
        
        <?php if (isset($error)): ?>
            <div class="error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($records) && !empty($records)): ?>
            <!-- Summary Statistics -->
            <div class="summary">
                <h3>Summary</h3>
                <div class="summary-stats">
                    <div class="stat">
                        <div class="stat-label">Total Records</div>
                        <div class="stat-value"><?php echo number_format(count($records)); ?></div>
                    </div>
                    <div class="stat">
                        <div class="stat-label">Total API Value</div>
                        <div class="stat-value">$<?php echo number_format(array_sum(array_map(function($r) { return safeGet($r, 'api_value', 0); }, $records)), 2); ?></div>
                    </div>
                    <div class="stat">
                        <div class="stat-label">Total Credit</div>
                        <div class="stat-value">$<?php echo number_format(array_sum(array_map(function($r) { return safeGet($r, 'credit', 0); }, $records)), 2); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Data Table -->
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Res Date</th>
                        <th>Approve Date</th>
                        <th>Res Number</th>
                        <th>Res Type</th>
                        <th>Prepay</th>
                        <th>Voucher</th>
                        <th>AC No</th>
                        <th>Agency</th>
                        <th>Pay Mode</th>
                        <th>API Value</th>
                        <th>Credit</th>
                        <th>CPC</th>
                        <th>Status</th>
                        <th>Imported</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $record): ?>
                        <tr class="status-<?php echo strtolower(safeGet($record, 'status', 'unknown')); ?>">
                            <td><?php echo htmlspecialchars(safeGet($record, 'id', '')); ?></td>
                            <td><?php echo safeGet($record, 'res_date') ? htmlspecialchars(safeGet($record, 'res_date')) : '-'; ?></td>
                            <td><?php echo safeGet($record, 'approve_date') ? htmlspecialchars(safeGet($record, 'approve_date')) : '-'; ?></td>
                            <td><?php echo htmlspecialchars(safeGet($record, 'res_number', '')); ?></td>
                            <td><?php echo htmlspecialchars(safeGet($record, 'res_type', '')); ?></td>
                            <td><?php echo htmlspecialchars(safeGet($record, 'prepay', '')); ?></td>
                            <td><?php echo htmlspecialchars(safeGet($record, 'voucher', '')); ?></td>
                            <td><?php echo htmlspecialchars(safeGet($record, 'ac_no', '')); ?></td>
                            <td><?php echo htmlspecialchars(safeGet($record, 'agency', '')); ?></td>
                            <td><?php echo htmlspecialchars(safeGet($record, 'pay_mode', '')); ?></td>
                            <td class="money"><?php echo safeGet($record, 'api_value') ? '$' . number_format(safeGet($record, 'api_value'), 2) : '-'; ?></td>
                            <td class="money"><?php echo safeGet($record, 'credit') ? '$' . number_format(safeGet($record, 'credit'), 2) : '-'; ?></td>
                            <td><?php echo htmlspecialchars(safeGet($record, 'cpc', '')); ?></td>
                            <td><?php echo htmlspecialchars(safeGet($record, 'status', '')); ?></td>
                            <td><?php echo safeGet($record, 'imported_at') ? date('M j, Y g:i A', strtotime(safeGet($record, 'imported_at'))) : '-'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No records found in the database.</p>
        <?php endif; ?>
        
        <div style="margin-top: 20px; text-align: center;">
            <a href="index.html" style="color: #117372; text-decoration: none;">← Back to Home</a>
        </div>
    </div>
</body>
</html>
