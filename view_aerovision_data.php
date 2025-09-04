<?php
require_once 'auth/config.php';

$message = '';
$error = '';

// Initialize filter variables
$agencyFilter = $_GET['agency'] ?? '';
$prepayFilter = $_GET['prepay'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$supplierFilter = $_GET['supplier'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$dateColumn = $_GET['date_column'] ?? 'pickup_date';
$sortBy = $_GET['sort'] ?? 'id';
$sortOrder = $_GET['order'] ?? 'DESC';

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
    

    
    // Handle specific record prepay fixes
    if (isset($_POST['fix_record_1151'])) {
        $stmt = $pdo->prepare("UPDATE aero_res_22 SET prepay = 'NO' WHERE id = 1151");
        $result = $stmt->execute();
        
        if ($result) {
            $message = "Record 1151 prepay value updated to 'NO' successfully!";
        } else {
            $error = "Failed to update record 1151.";
        }
    }
    
    if (isset($_POST['fix_record_1085'])) {
        $stmt = $pdo->prepare("UPDATE aero_res_22 SET prepay = 'NO' WHERE id = 1085");
        $result = $stmt->execute();
        
        if ($result) {
            $message = "Record 1085 prepay value updated to 'NO' successfully!";
        } else {
            $error = "Failed to update record 1085.";
        }
    }
    
    // Handle edit action
    if (isset($_POST['edit_record'])) {
        $stmt = $pdo->prepare("
            UPDATE aero_res_22 SET 
                res_day = ?, app_day = ?, pickup_date = ?, dropoff_date = ?, 
                res_number = ?, voucher = ?, file_number = ?, name = ?, 
                agency = ?, supplier = ?, car_class = ?, days = ?, 
                value = ?, discount = ?, payment = ?, prepay = ?, 
                status = ?
            WHERE id = ?
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
            $_POST['prepay'] ?? '',
            $_POST['status'] ?? '',
            $_POST['edit_id'] ?? 0
        ]);
        
        if ($result) {
            $message = "Record updated successfully!";
        } else {
            $error = "Failed to update record.";
        }
    }
    
    // Handle agency filter
    $whereClause = '';
    $params = [];
    
    if (!empty($agencyFilter)) {
        $whereClause = "WHERE agency = ?";
        $params[] = $agencyFilter;
    }
    
    if (!empty($prepayFilter)) {
        if (!empty($whereClause)) {
            $whereClause .= " AND prepay = ?";
        } else {
            $whereClause = "WHERE prepay = ?";
        }
        $params[] = $prepayFilter;
    }
    
    if (!empty($statusFilter)) {
        if (!empty($whereClause)) {
            $whereClause .= " AND status = ?";
        } else {
            $whereClause = "WHERE status = ?";
        }
        $params[] = $statusFilter;
    }
    
    if (!empty($supplierFilter)) {
        if (!empty($whereClause)) {
            $whereClause .= " AND supplier = ?";
        } else {
            $whereClause = "WHERE supplier = ?";
        }
        $params[] = $supplierFilter;
    }
    
    // Add date range filter
    if (!empty($dateFrom)) {
        if (!empty($whereClause)) {
            $whereClause .= " AND $dateColumn >= ?";
        } else {
            $whereClause = "WHERE $dateColumn >= ?";
        }
        $params[] = $dateFrom;
    }
    
    if (!empty($dateTo)) {
        if (!empty($whereClause)) {
            $whereClause .= " AND $dateColumn <= ?";
        } else {
            $whereClause = "WHERE $dateColumn <= ?";
        }
        $params[] = $dateTo;
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
    
    // Get unique prepay values for filter dropdown
    $prepayStmt = $pdo->query("SELECT DISTINCT prepay FROM aero_res_22 WHERE prepay IS NOT NULL AND prepay != '' ORDER BY prepay");
    $prepayValues = $prepayStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get unique status values for filter dropdown
    $statusStmt = $pdo->query("SELECT DISTINCT status FROM aero_res_22 WHERE status IS NOT NULL AND status != '' ORDER BY status");
    $statusValues = $statusStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get unique supplier values for filter dropdown
    $supplierStmt = $pdo->query("SELECT DISTINCT supplier FROM aero_res_22 WHERE supplier IS NOT NULL AND supplier != '' ORDER BY supplier");
    $supplierValues = $supplierStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Debug: Check for "Rented" status records
    $rentedStmt = $pdo->query("SELECT COUNT(*) as count FROM aero_res_22 WHERE status = 'Rented'");
    $rentedCount = $rentedStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Debug: Get total count
    $totalStmt = $pdo->query("SELECT COUNT(*) as count FROM aero_res_22");
    $totalCount = $totalStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Debug: Check specific records
    $record1151Stmt = $pdo->query("SELECT id, prepay, status FROM aero_res_22 WHERE id = 1151");
    $record1151 = $record1151Stmt->fetch(PDO::FETCH_ASSOC);
    
    $record1085Stmt = $pdo->query("SELECT id, prepay, status FROM aero_res_22 WHERE id = 1085");
    $record1085 = $record1085Stmt->fetch(PDO::FETCH_ASSOC);
    
    // Debug: Check for records with prepay='NO' AND status='Rented'
    $noRentedStmt = $pdo->query("SELECT COUNT(*) as count FROM aero_res_22 WHERE prepay = 'NO' AND status = 'Rented'");
    $noRentedCount = $noRentedStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
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
        .edit-btn {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
            margin-right: 5px;
        }
        .edit-btn:hover {
            background-color: #0056b3;
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
        
        <!-- Temporary Debug Info for Records -->
        <div style="background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 10px 0; border-radius: 4px;">
            <strong>Debug for Records:</strong><br>
            Record 1151 Data: <?php echo isset($record1151) ? "ID: {$record1151['id']}, Prepay: '{$record1151['prepay']}', Status: '{$record1151['status']}'" : 'Record not found'; ?><br>
            Record 1085 Data: <?php echo isset($record1085) ? "ID: {$record1085['id']}, Prepay: '{$record1085['prepay']}', Status: '{$record1085['status']}'" : 'Record not found'; ?><br>
            Records with prepay='NO' AND status='Rented': <?php echo isset($noRentedCount) ? $noRentedCount : 'N/A'; ?><br>
            <br>
            <strong>Available Prepay Values:</strong><br>
            <?php 
            if (isset($prepayValues)) {
                foreach ($prepayValues as $prepay) {
                    echo "'" . htmlspecialchars($prepay) . "' ";
                }
            }
            ?>
            <br><br>
            <?php if (isset($record1151) && empty($record1151['prepay'])): ?>
                <form method="POST" style="display: inline;" onsubmit="return confirm('Fix Record 1151 prepay value to \'NO\'?');">
                    <button type="submit" name="fix_record_1151" style="background-color: #28a745; color: white; padding: 5px 10px; border: none; border-radius: 3px; cursor: pointer; margin-right: 10px;">🔧 Fix Record 1151 Prepay to 'NO'</button>
                </form>
            <?php endif; ?>
            <?php if (isset($record1085) && empty($record1085['prepay'])): ?>
                <form method="POST" style="display: inline;" onsubmit="return confirm('Fix Record 1085 prepay value to \'NO\'?');">
                    <button type="submit" name="fix_record_1085" style="background-color: #28a745; color: white; padding: 5px 10px; border: none; border-radius: 3px; cursor: pointer;">🔧 Fix Record 1085 Prepay to 'NO'</button>
                </form>
            <?php endif; ?>
        </div>
        

        
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
                    <label for="prepay">Filter by Prepay:</label>
                    <select id="prepay" name="prepay">
                        <option value="">All Prepay Options</option>
                        <?php foreach ($prepayValues as $prepay): ?>
                            <option value="<?php echo htmlspecialchars($prepay); ?>" <?php echo $prepayFilter === $prepay ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($prepay); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="status">Filter by Status:</label>
                    <select id="status" name="status">
                        <option value="">All Status Options</option>
                        <?php foreach ($statusValues as $status): ?>
                            <option value="<?php echo htmlspecialchars($status); ?>" <?php echo $statusFilter === $status ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($status); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="supplier">Filter by Supplier:</label>
                    <select id="supplier" name="supplier">
                        <option value="">All Suppliers</option>
                        <?php foreach ($supplierValues as $supplier): ?>
                            <option value="<?php echo htmlspecialchars($supplier); ?>" <?php echo $supplierFilter === $supplier ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($supplier); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="date_column">Date Column:</label>
                    <select id="date_column" name="date_column">
                        <option value="pickup_date" <?php echo $dateColumn === 'pickup_date' ? 'selected' : ''; ?>>Pickup Date</option>
                        <option value="dropoff_date" <?php echo $dateColumn === 'dropoff_date' ? 'selected' : ''; ?>>Dropoff Date</option>
                        <option value="res_day" <?php echo $dateColumn === 'res_day' ? 'selected' : ''; ?>>Reservation Day</option>
                        <option value="app_day" <?php echo $dateColumn === 'app_day' ? 'selected' : ''; ?>>Approval Day</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="date_from">Date From:</label>
                    <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                </div>
                <div class="filter-group">
                    <label for="date_to">Date To:</label>
                    <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
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
            
            <?php if (!empty($agencyFilter) || !empty($prepayFilter) || !empty($dateFrom) || !empty($dateTo) || $sortBy !== 'id' || $sortOrder !== 'DESC'): ?>
                <div style="margin-top: 10px; font-size: 12px; color: #666;">
                    <strong>Current Settings:</strong>
                    <?php if (!empty($agencyFilter)): ?>
                        Agency: <?php echo htmlspecialchars($agencyFilter); ?> |
                    <?php endif; ?>
                    <?php if (!empty($prepayFilter)): ?>
                        Prepay: <?php echo htmlspecialchars($prepayFilter); ?> |
                    <?php endif; ?>
                    <?php if (!empty($dateFrom) || !empty($dateTo)): ?>
                        Date Range: <?php echo ucfirst(str_replace('_', ' ', $dateColumn)); ?> 
                        <?php if (!empty($dateFrom)): ?>from <?php echo htmlspecialchars($dateFrom); ?><?php endif; ?>
                        <?php if (!empty($dateTo)): ?> to <?php echo htmlspecialchars($dateTo); ?><?php endif; ?> |
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
                         <th>Created At</th>
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
                             <td><?php echo safeGet($record, 'created_at') ? date('M j, Y g:i A', strtotime(safeGet($record, 'created_at'))) : '-'; ?></td>
                             <td>
                                <button type="button" class="edit-btn" onclick="openEditModal(<?php echo htmlspecialchars(json_encode([
                                    'id' => $record['id'],
                                    'res_day' => isset($record['res_day']) ? date('Y-m-d', strtotime($record['res_day'])) : '',
                                    'app_day' => isset($record['app_day']) ? date('Y-m-d', strtotime($record['app_day'])) : '',
                                    'pickup_date' => isset($record['pickup_date']) ? date('Y-m-d', strtotime($record['pickup_date'])) : '',
                                    'dropoff_date' => isset($record['dropoff_date']) ? date('Y-m-d', strtotime($record['dropoff_date'])) : '',
                                    'res_number' => $record['res_number'] ?? '',
                                    'voucher' => $record['voucher'] ?? '',
                                    'file_number' => $record['file_number'] ?? '',
                                    'name' => $record['name'] ?? '',
                                    'agency' => $record['agency'] ?? '',
                                    'supplier' => $record['supplier'] ?? '',
                                    'car_class' => $record['car_class'] ?? '',
                                    'days' => $record['days'] ?? '',
                                    'value' => $record['value'] ?? '',
                                    'discount' => $record['discount'] ?? '',
                                    'payment' => $record['payment'] ?? '',
                                    'prepay' => $record['prepay'] ?? '',
                                    'status' => $record['status'] ?? ''
                                ])); ?>)">✏️ Edit</button>
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
                        <option value="YES">YES</option>
                        <option value="NO">NO</option>
                        <option value="yes">yes</option>
                        <option value="no">no</option>
                    </select>
                </div>
                <div style="text-align: center; margin-top: 20px;">
                    <button type="submit" name="add_record" class="btn btn-success">Add Record</button>
                    <button type="button" onclick="closeAddModal()" class="btn btn-primary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Record Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h2>Edit Record</h2>
            <form method="POST">
                <input type="hidden" id="edit_id" name="edit_id">
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_res_day">Res Day:</label>
                        <input type="date" id="edit_res_day" name="res_day">
                    </div>
                    <div class="form-group">
                        <label for="edit_app_day">App Day:</label>
                        <input type="date" id="edit_app_day" name="app_day">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_pickup_date">Pickup Date:</label>
                        <input type="date" id="edit_pickup_date" name="pickup_date">
                    </div>
                    <div class="form-group">
                        <label for="edit_dropoff_date">Dropoff Date:</label>
                        <input type="date" id="edit_dropoff_date" name="dropoff_date">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_res_number">Res Number:</label>
                        <input type="text" id="edit_res_number" name="res_number" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_voucher">Voucher:</label>
                        <input type="text" id="edit_voucher" name="voucher">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_file_number">File Number:</label>
                        <input type="text" id="edit_file_number" name="file_number">
                    </div>
                    <div class="form-group">
                        <label for="edit_name">Name:</label>
                        <input type="text" id="edit_name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_agency">Agency:</label>
                        <input type="text" id="edit_agency" name="agency">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_supplier">Supplier:</label>
                        <input type="text" id="edit_supplier" name="supplier">
                    </div>
                    <div class="form-group">
                        <label for="edit_car_class">Car Class:</label>
                        <input type="text" id="edit_car_class" name="car_class">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_days">Days:</label>
                        <input type="number" id="edit_days" name="days" min="0" step="1">
                    </div>
                    <div class="form-group">
                        <label for="edit_value">Value:</label>
                        <input type="number" id="edit_value" name="value" min="0" step="0.01">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_discount">Discount:</label>
                        <input type="number" id="edit_discount" name="discount" min="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label for="edit_payment">Payment:</label>
                        <select id="edit_payment" name="payment">
                            <option value="">Select Payment</option>
                            <option value="Credit Card">Credit Card</option>
                            <option value="Cash">Cash</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="n/a">Non-Prepaid</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_prepay">Prepay:</label>
                        <select id="edit_prepay" name="prepay">
                            <option value="">Select Prepay</option>
                            <option value="YES">YES</option>
                            <option value="NO">NO</option>
                            <option value="yes">yes</option>
                            <option value="no">no</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_status">Status:</label>
                        <input type="text" id="edit_status" name="status" placeholder="Enter status">
                    </div>
                </div>

                <div style="text-align: center; margin-top: 20px;">
                    <button type="submit" name="edit_record" class="btn btn-success">Update Record</button>
                    <button type="button" onclick="closeEditModal()" class="btn btn-primary">Cancel</button>
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

        function openEditModal(record) {
            // Populate the edit form with record data
            document.getElementById('edit_id').value = record.id;
            
            // Set date fields (already in YYYY-MM-DD format from PHP)
            document.getElementById('edit_res_day').value = record.res_day || '';
            document.getElementById('edit_app_day').value = record.app_day || '';
            document.getElementById('edit_pickup_date').value = record.pickup_date || '';
            document.getElementById('edit_dropoff_date').value = record.dropoff_date || '';
            
            document.getElementById('edit_res_number').value = record.res_number || '';
            document.getElementById('edit_voucher').value = record.voucher || '';
            document.getElementById('edit_file_number').value = record.file_number || '';
            document.getElementById('edit_name').value = record.name || '';
            document.getElementById('edit_agency').value = record.agency || '';
            document.getElementById('edit_supplier').value = record.supplier || '';
            document.getElementById('edit_car_class').value = record.car_class || '';
            document.getElementById('edit_days').value = record.days || '';
            document.getElementById('edit_value').value = record.value || '';
            document.getElementById('edit_discount').value = record.discount || '';
            document.getElementById('edit_payment').value = record.payment || '';
            document.getElementById('edit_prepay').value = record.prepay || '';
            document.getElementById('edit_status').value = record.status || '';
            
            // Show the modal
            document.getElementById('editModal').style.display = 'block';
        }
        


        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // Close modals when clicking outside of them
        window.onclick = function(event) {
            var addModal = document.getElementById('addModal');
            var editModal = document.getElementById('editModal');
            if (event.target == addModal) {
                addModal.style.display = 'none';
            }
            if (event.target == editModal) {
                editModal.style.display = 'none';
            }
        }
    </script>
</body>
</html>
