<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
	http_response_code(204);
	exit;
}

require_once __DIR__ . '/../auth/config.php';

function respond($code, $payload) {
	http_response_code($code);
	echo json_encode($payload);
	exit;
}

function requireAuth() {
	$token = $_COOKIE['session_token'] ?? null;
	if (!$token) { respond(401, ['success' => false, 'message' => 'Unauthorized']); }
	$pdo = getReservationsDB();
	if (!$pdo) { respond(500, ['success' => false, 'message' => 'Auth database unavailable']); }
	$stmt = $pdo->prepare("SELECT s.session_token FROM user_sessions s JOIN users u ON s.user_id = u.id WHERE s.session_token = ? AND s.is_active = 1 AND s.expires_at > NOW() AND u.is_active = 1");
	$stmt->execute([$token]);
	if (!$stmt->fetch()) { respond(401, ['success' => false, 'message' => 'Unauthorized']); }
}

try {
	requireAuth();
	$pdo = getReservationsDB();
	if (!$pdo) { respond(500, ['success' => false, 'message' => 'Database connection failed']); }

	$table = 'zb_reservations';

	// Confirm table exists
	$stmt = $pdo->query("SHOW TABLES LIKE '" . str_replace('`','',$table) . "'");
	if ($stmt->rowCount() === 0) {
		respond(200, ['success' => true, 'message' => 'No reservations table found yet. Import first.', 'columns' => [], 'rows' => [], 'pagination' => ['page' => 1, 'page_size' => 25, 'total' => 0]]);
	}

	// Get columns
	$colsStmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
	$allCols = array_map(function($r){ return $r['Field']; }, $colsStmt->fetchAll());
	// Preferred ordering: id, then other columns, with imported_at last if present
	$displayCols = $allCols;
	// Keep all columns for now but many tables could be wide. Frontend will scroll.

	// Pagination and search
	$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
	$pageSize = isset($_GET['page_size']) ? max(1, min(100, (int)$_GET['page_size'])) : 25;
	$offset = ($page - 1) * $pageSize;
	$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

	$whereSql = '';
	$params = [];
	if ($q !== '') {
		// Build CONCAT_WS search over all columns
		$concat = 'CONCAT_WS(\' \',' . implode(',', array_map(function($c){ return "`".$c."`"; }, $displayCols)) . ')';
		$whereSql = "WHERE $concat LIKE ?";
		$params[] = '%' . $q . '%';
	}

	// Total count
	$countSql = "SELECT COUNT(*) AS cnt FROM `{$table}` $whereSql";
	$stmt = $pdo->prepare($countSql);
	$stmt->execute($params);
	$total = (int)($stmt->fetch()['cnt'] ?? 0);

	// Choose order by imported_at DESC if available else id DESC
	$orderCol = in_array('imported_at', $displayCols, true) ? 'imported_at' : (in_array('id', $displayCols, true) ? 'id' : $displayCols[0]);

	$selectColsSql = '`' . implode('`, `', $displayCols) . '`';
	$sql = "SELECT $selectColsSql FROM `{$table}` $whereSql ORDER BY `$orderCol` DESC LIMIT $pageSize OFFSET $offset";
	$stmt = $pdo->prepare($sql);
	$stmt->execute($params);
	$rows = $stmt->fetchAll();

	respond(200, [
		'success' => true,
		'columns' => $displayCols,
		'rows' => $rows,
		'pagination' => [
			'page' => $page,
			'page_size' => $pageSize,
			'total' => $total
		]
	]);
} catch (Throwable $e) {
	respond(500, ['success' => false, 'message' => 'Unexpected error: ' . $e->getMessage()]);
}
?>




