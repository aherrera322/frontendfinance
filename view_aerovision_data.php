<?php
require_once 'auth/config.php';

$message = '';
$error = '';

try {
    $pdo = getReservationsDB();
    
    // Handle delete action
    if (isset($_POST['delete_id'])) {
        $deleteId = (int)$_POST['delete_id'];
        $stmt = $pdo->prepare("DELETE FROM aero_res_22 WHERE id = ?");
        if ($stmt->execute([$deleteId])) {
            $message = "Record deleted successfully!";
        } else {
            $error = "Failed to delete record.";
        }
    }
    
    // Handle add action
    if (isset($_POST['add_record'])) {
        $stmt = $pdo->prepare("
            INSERT INTO aero_res_22 (
                res_day, app_day, pickup_date, dropoff_date, res_number, 
                voucher, file_number, name, agency, supplier, car_class, days, 
                value, discount, payment, prepay
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $_POST['res_day'] ?? '',
            $_POST['app_day'] ?? '',
            $_POST['pickup_date'] ?? '',
            $_POST['dropoff_date'] ?? '',
            $_POST['res_number'] ?? '',
            $_POST['voucher'] ?? '',
            $_POST['file_number'] ?? '',
            $_POST['name'] ?? '',
            $_POST['agency'] ?? '',
            $_POST['supplier'] ?? '',
            $_POST['car_class'] ?? '',
            $_POST['days'] ?? 0,
            $_POST['value'] ?? 0,
            $_POST['discount'] ?? 0,
            $_POST['payment'] ?? '',
            $_POST['prepay'] ?? ''
        ]);
        
        if ($result) {
            $message = "Record added successfully!";
        } else {
            $error = "Failed to add record.";
        }
    }
    
    // Handle agency filter
    $agencyFilter = $_GET['agency'] ?? '';
    $sortBy = $_GET['sort'] ?? 'id';
    $sortOrder = $_GET['order'] ?? 'DESC';
    $whereClause = '';
    $params = [];
    
    if (!empty($agencyFilter)) {
        $whereClause = "WHERE agency = ?";
        $params[] = $agencyFilter;
    }
    
    // Validate sort column to prevent SQL injection
    $allowedSortColumns = ['id', 'file_number', 'value', 'days', 'res_number', 'voucher', 'name', 'agency', 'supplier'];
    if (!in_array($sortBy, $allowedSortColumns)) {
        $sortBy = 'id';
    }
    
    // Validate sort order
    $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';
    
    // Get all data from aero_res_22 table with optional agency filter and sorting
    // Treat N/A values as zero for numeric columns, handle text columns appropriately
    $numericColumns = ['id', 'file_number', 'value', 'days'];
    if (in_array($sortBy, $numericColumns)) {
        $sql = "SELECT * FROM aero_res_22 " . $whereClause . " ORDER BY CASE WHEN $sortBy = 'N/A' OR $sortBy = 'n/a' OR $sortBy IS NULL OR $sortBy = '' THEN 0 ELSE CAST($sortBy AS DECIMAL(10,2)) END $sortOrder";
    } else {
        $sql = "SELECT * FROM aero_res_22 " . $whereClause . " ORDER BY CASE WHEN $sortBy = 'N/A' OR $sortBy = 'n/a' OR $sortBy IS NULL OR $sortBy = '' THEN '' ELSE $sortBy END $sortOrder";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get unique agencies for filter dropdown
    $agencyStmt = $pdo->query("SELECT DISTINCT agency FROM aero_res_22 WHERE agency IS NOT NULL AND agency != '' ORDER BY agency");
    $agencies = $agencyStmt->fetchAll(PDO::FETCH_COLUMN);
    
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
    <title>Aerovision Reservations Data - Simple View</title>
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
        .action-buttons {
            margin: 20px 0;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
            font-weight: bold;
        }
        .btn-primary {
            background-color: #117372;
            color: white;
        }
        .btn-primary:hover {
            background-color: #0d5a5a;
        }
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background-color: #c82333;
        }
        .btn-success {
            background-color: #28a745;
            color: white;
        }
        .btn-success:hover {
            background-color: #218838;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            width: 80%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover {
            color: #000;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .form-row {
            display: flex;
            gap: 15px;
        }
        .form-row .form-group {
            flex: 1;
        }
        .success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .delete-btn {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }
        .delete-btn:hover {
            background-color: #c82333;
        }
        .filter-section {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .filter-section h3 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #117372;
            font-size: 16px;
        }
        .filter-form {
            display: flex;
            gap: 15px;
            align-items: end;
            flex-wrap: wrap;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .filter-group label {
            font-size: 12px;
            font-weight: bold;
            color: #333;
        }
        .filter-group select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            min-width: 200px;
        }
        .table-container {
            overflow-x: auto;
            margin-top: 20px;
        }
                 .table-container table {
             min-width: 3000px; /* Ensure all columns are visible */
             font-size: 11px; /* Smaller font to fit more columns */
         }
         .table-container th,
         .table-container td {
             padding: 6px 4px; /* Smaller padding */
             white-space: nowrap; /* Prevent text wrapping */
         }
    </style>
</head>
<body>
    <div class="container">
        <h1>Aerovision Reservations Data</h1>
        
        <?php if (!empty($message)): ?>
            <div class="success">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <!-- Action Buttons -->
        <div class="action-buttons">
            <button class="btn btn-success" onclick="openAddModal()">➕ Add New Record</button>
            <a href="index.html" class="btn btn-primary">← Back to Home</a>
        </div>
        
                         <!-- Filter Section -->
        <div class="filter-section">
            <h3>🔍 Filter & Sort Options <?php if (!empty($agencyFilter)): ?><span style="color: #dc3545;">(Filtered by: <?php echo htmlspecialchars($agencyFilter); ?>)</span><?php endif; ?></h3>
            <form method="GET" class="filter-form">
                <div class="filter-group">
                    <label for="agency">Filter by Agency:</label>
                    <select id="agency" name="agency">
                        <option value="">All Agencies</option>
                        <?php foreach ($agencies as $agency): ?>
                            <option value="<?php echo htmlspecialchars($agency); ?>" <?php echo $agencyFilter === $agency ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($agency); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="sort">Sort by:</label>
                    <select id="sort" name="sort">
                        <option value="id" <?php echo $sortBy === 'id' ? 'selected' : ''; ?>>ID</option>
                        <option value="file_number" <?php echo $sortBy === 'file_number' ? 'selected' : ''; ?>>File Number</option>
                        <option value="value" <?php echo $sortBy === 'value' ? 'selected' : ''; ?>>Value</option>
                        <option value="days" <?php echo $sortBy === 'days' ? 'selected' : ''; ?>>Days</option>
                        <option value="res_number" <?php echo $sortBy === 'res_number' ? 'selected' : ''; ?>>Res Number</option>
                        <option value="voucher" <?php echo $sortBy === 'voucher' ? 'selected' : ''; ?>>Voucher</option>
                        <option value="name" <?php echo $sortBy === 'name' ? 'selected' : ''; ?>>Name</option>
                        <option value="agency" <?php echo $sortBy === 'agency' ? 'selected' : ''; ?>>Agency</option>
                        <option value="supplier" <?php echo $sortBy === 'supplier' ? 'selected' : ''; ?>>Supplier</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="order">Order:</label>
                    <select id="order" name="order">
                        <option value="DESC" <?php echo $sortOrder === 'DESC' ? 'selected' : ''; ?>>Large to Small</option>
                        <option value="ASC" <?php echo $sortOrder === 'ASC' ? 'selected' : ''; ?>>Small to Large</option>
                    </select>
                </div>
                <div class="filter-group">
                    <button type="submit" class="btn btn-primary">Apply Filter & Sort</button>
                    <a href="view_aerovision_data.php" class="btn btn-danger">Clear All</a>
                </div>
            </form>
            
            <?php if (!empty($agencyFilter) || $sortBy !== 'id' || $sortOrder !== 'DESC'): ?>
                <div style="margin-top: 10px; font-size: 12px; color: #666;">
                    <strong>Current Settings:</strong>
                    <?php if (!empty($agencyFilter)): ?>
                        Agency: <?php echo htmlspecialchars($agencyFilter); ?> |
                    <?php endif; ?>
                    Sort: <?php echo htmlspecialchars(ucfirst($sortBy)); ?> 
                    (<?php echo $sortOrder === 'DESC' ? 'Large to Small' : 'Small to Large'; ?>)
                </div>
            <?php endif; ?>
        </div>
        
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
                        <div class="stat-label">Total Value</div>
                        <div class="stat-value">$<?php echo number_format(array_sum(array_map(function($r) { return safeGet($r, 'value', 0); }, $records)), 2); ?></div>
                    </div>
                    <div class="stat">
                        <div class="stat-label">Total Discount</div>
                        <div class="stat-value">$<?php echo number_format(array_sum(array_map(function($r) { return safeGet($r, 'discount', 0); }, $records)), 2); ?></div>
                    </div>
                </div>
            </div>
            
                         <!-- Data Table -->
             <div class="table-container">
                 <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Res Day</th>
                        <th>App Day</th>
                        <th>Pickup Date</th>
                        <th>Dropoff Date</th>
                        <th>Res Number</th>
                        <th>Voucher</th>
                        <th>File Number</th>
                        <th>Name</th>
                        <th>Agency</th>
                        <th>Supplier</th>
                        <th>Car Class</th>
                        <th>Days</th>
                        <th>Value</th>
                        <th>Discount</th>
                                                 <th>Payment</th>
                         <th>Prepay</th>
                         <th>Status</th>
                         <th>Notes</th>
                         <th>Created At</th>
                         <th>Updated At</th>
                         <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $record): ?>
                        <tr>
                            <td><?php echo htmlspecialchars(safeGet($record, 'id', '')); ?></td>
                            <td><?php echo safeGet($record, 'res_day') ? htmlspecialchars(safeGet($record, 'res_day')) : '-'; ?></td>
                            <td><?php echo safeGet($record, 'app_day') ? htmlspecialchars(safeGet($record, 'app_day')) : '-'; ?></td>
                            <td><?php echo safeGet($record, 'pickup_date') ? htmlspecialchars(safeGet($record, 'pickup_date')) : '-'; ?></td>
                            <td><?php echo safeGet($record, 'dropoff_date') ? htmlspecialchars(safeGet($record, 'dropoff_date')) : '-'; ?></td>
                            <td><?php echo htmlspecialchars(safeGet($record, 'res_number', '')); ?></td>
                            <td><?php echo htmlspecialchars(safeGet($record, 'voucher', '')); ?></td>
                            <td><?php echo htmlspecialchars(safeGet($record, 'file_number', '')); ?></td>
                            <td><?php echo htmlspecialchars(safeGet($record, 'name', '')); ?></td>
                            <td><?php echo htmlspecialchars(safeGet($record, 'agency', '')); ?></td>
                            <td><?php echo htmlspecialchars(safeGet($record, 'supplier', '')); ?></td>
                            <td><?php echo htmlspecialchars(safeGet($record, 'car_class', '')); ?></td>
                            <td><?php echo htmlspecialchars(safeGet($record, 'days', '')); ?></td>
                            <td class="money"><?php echo safeGet($record, 'value') ? '$' . number_format(safeGet($record, 'value'), 2) : '-'; ?></td>
                            <td class="money"><?php echo safeGet($record, 'discount') ? '$' . number_format(safeGet($record, 'discount'), 2) : '-'; ?></td>
                                                         <td><?php echo htmlspecialchars(safeGet($record, 'payment', '')); ?></td>
                             <td><?php echo htmlspecialchars(safeGet($record, 'prepay', '')); ?></td>
                             <td><?php echo htmlspecialchars(safeGet($record, 'status', '')); ?></td>
                             <td><?php echo htmlspecialchars(safeGet($record, 'notes', '')); ?></td>
                             <td><?php echo safeGet($record, 'created_at') ? date('M j, Y g:i A', strtotime(safeGet($record, 'created_at'))) : '-'; ?></td>
                             <td><?php echo safeGet($record, 'updated_at') ? date('M j, Y g:i A', strtotime(safeGet($record, 'updated_at'))) : '-'; ?></td>
                             <td>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this record?');">
                                    <input type="hidden" name="delete_id" value="<?php echo safeGet($record, 'id', ''); ?>">
                                    <button type="submit" class="delete-btn">🗑️ Delete</button>
                                </form>
                            </td>
                        </tr>
                                         <?php endforeach; ?>
                 </tbody>
             </table>
         </div>
        <?php else: ?>
            <p>No records found in the database.</p>
        <?php endif; ?>
        
        <div style="margin-top: 20px; text-align: center;">
            <a href="index.html" style="color: #117372; text-decoration: none;">← Back to Home</a>
        </div>
    </div>

    <!-- Add Record Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAddModal()">&times;</span>
            <h2>Add New Record</h2>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="res_day">Res Day:</label>
                        <input type="date" id="res_day" name="res_day">
                    </div>
                    <div class="form-group">
                        <label for="app_day">App Day:</label>
                        <input type="date" id="app_day" name="app_day">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="pickup_date">Pickup Date:</label>
                        <input type="date" id="pickup_date" name="pickup_date">
                    </div>
                    <div class="form-group">
                        <label for="dropoff_date">Dropoff Date:</label>
                        <input type="date" id="dropoff_date" name="dropoff_date">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="res_number">Res Number:</label>
                        <input type="text" id="res_number" name="res_number" required>
                    </div>
                    <div class="form-group">
                        <label for="voucher">Voucher:</label>
                        <input type="text" id="voucher" name="voucher">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="file_number">File Number:</label>
                        <input type="text" id="file_number" name="file_number">
                    </div>
                    <div class="form-group">
                        <label for="name">Name:</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="agency">Agency:</label>
                        <input type="text" id="agency" name="agency">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="supplier">Supplier:</label>
                        <input type="text" id="supplier" name="supplier">
                    </div>
                    <div class="form-group">
                        <label for="car_class">Car Class:</label>
                        <input type="text" id="car_class" name="car_class">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="days">Days:</label>
                        <input type="number" id="days" name="days" min="0" step="1">
                    </div>
                    <div class="form-group">
                        <label for="value">Value:</label>
                        <input type="number" id="value" name="value" min="0" step="0.01">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="discount">Discount:</label>
                        <input type="number" id="discount" name="discount" min="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label for="payment">Payment:</label>
                        <select id="payment" name="payment">
                            <option value="">Select Payment</option>
                            <option value="Credit Card">Credit Card</option>
                            <option value="Cash">Cash</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="n/a">Non-Prepaid</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="prepay">Prepay:</label>
                    <select id="prepay" name="prepay">
                        <option value="">Select Prepay</option>
                        <option value="yes">Yes</option>
                        <option value="no">No</option>
                    </select>
                </div>
                <div style="text-align: center; margin-top: 20px;">
                    <button type="submit" name="add_record" class="btn btn-success">Add Record</button>
                    <button type="button" onclick="closeAddModal()" class="btn btn-primary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addModal').style.display = 'block';
        }

        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }

        // Close modal when clicking outside of it
        window.onclick = function(event) {
            var modal = document.getElementById('addModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>
