<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
	http_response_code(204);
	exit;
}

require_once __DIR__ . '/../../auth/config.php';

function respond($statusCode, $payload) {
	http_response_code($statusCode);
	echo json_encode($payload);
	exit;
}

function db() {
	$pdo = getClientsDB();
	if (!$pdo) {
		respond(500, ['success' => false, 'message' => 'Database connection failed']);
	}
	return $pdo;
}

function requireAuth() {
	$token = $_COOKIE['session_token'] ?? null;
	if (!$token) {
		respond(401, ['success' => false, 'message' => 'Unauthorized']);
	}
	$authPdo = getReservationsDB();
	if (!$authPdo) {
		respond(500, ['success' => false, 'message' => 'Auth database unavailable']);
	}
	$stmt = $authPdo->prepare("SELECT s.session_token FROM user_sessions s JOIN users u ON s.user_id = u.id WHERE s.session_token = ? AND s.is_active = 1 AND s.expires_at > NOW() AND u.is_active = 1");
	$stmt->execute([$token]);
	$session = $stmt->fetch();
	if (!$session) {
		respond(401, ['success' => false, 'message' => 'Unauthorized']);
	}
}

function readJson() {
	$raw = file_get_contents('php://input');
	$data = json_decode($raw, true);
	return is_array($data) ? $data : [];
}

function validateCommission($value) {
	if ($value === null || $value === '') return 0.00;
	if (!is_numeric($value)) return null;
	$float = (float)$value;
	if ($float < 0 || $float > 100) return null;
	return $float;
}

try {
	$method = $_SERVER['REQUEST_METHOD'];
	requireAuth();
	$pdo = db();

	if ($method === 'GET') {
		// List or get single
		$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
		if ($id > 0) {
			$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
			$stmt->execute([$id]);
			$row = $stmt->fetch();
			if (!$row) { respond(404, ['success' => false, 'message' => 'Client not found']); }
			respond(200, ['success' => true, 'data' => $row]);
		}

		$search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
		$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
		$pageSize = isset($_GET['page_size']) ? max(1, min(100, (int)$_GET['page_size'])) : 25;
		$offset = ($page - 1) * $pageSize;

		$where = '';
		$params = [];
		if ($search !== '') {
			// Check if representative and account_manager columns exist for search
			$checkRep = $pdo->prepare("SHOW COLUMNS FROM clients LIKE 'representative'");
			$checkRep->execute();
			$hasRepresentative = $checkRep->fetch();
			
			$checkAcc = $pdo->prepare("SHOW COLUMNS FROM clients LIKE 'account_manager'");
			$checkAcc->execute();
			$hasAccountManager = $checkAcc->fetch();
			
			$like = '%' . $search . '%';
			$params = [$like, $like, $like];
			
			if ($hasRepresentative && $hasAccountManager) {
				$where = "WHERE name LIKE ? OR email LIKE ? OR contact_name LIKE ? OR representative LIKE ? OR account_manager LIKE ?";
				$params = [$like, $like, $like, $like, $like];
			} elseif ($hasRepresentative) {
				$where = "WHERE name LIKE ? OR email LIKE ? OR contact_name LIKE ? OR representative LIKE ?";
				$params = [$like, $like, $like, $like];
			} else {
				$where = "WHERE name LIKE ? OR email LIKE ? OR contact_name LIKE ?";
			}
		}

		$countSql = "SELECT COUNT(*) AS cnt FROM clients $where";
		$stmt = $pdo->prepare($countSql);
		$stmt->execute($params);
		$total = (int)$stmt->fetch()['cnt'];

		$sql = "SELECT * FROM clients $where ORDER BY id DESC LIMIT $pageSize OFFSET $offset";
		$stmt = $pdo->prepare($sql);
		$stmt->execute($params);
		$rows = $stmt->fetchAll();

		respond(200, [
			'success' => true,
			'data' => $rows,
			'pagination' => [
				'page' => $page,
				'page_size' => $pageSize,
				'total' => $total
			]
		]);
	}

	if ($method === 'POST') {
		$input = readJson();
		$action = isset($input['action']) ? strtolower(trim((string)$input['action'])) : 'create';

		if ($action === 'create') {
			$name = isset($input['name']) ? trim((string)$input['name']) : '';
			if ($name === '') { respond(400, ['success' => false, 'message' => 'Name is required']); }
			$cc = validateCommission($input['commission_percent_credit_card'] ?? 0);
			$cl = validateCommission($input['commission_percent_credit_limit'] ?? 0);
			if ($cc === null || $cl === null) { respond(400, ['success' => false, 'message' => 'Commission percents must be numbers between 0 and 100']); }

			// Check if representative column exists
			$checkStmt = $pdo->prepare("SHOW COLUMNS FROM clients LIKE 'representative'");
			$checkStmt->execute();
			$hasRepresentative = $checkStmt->fetch();

			// Check if account_manager column exists
			$checkStmt2 = $pdo->prepare("SHOW COLUMNS FROM clients LIKE 'account_manager'");
			$checkStmt2->execute();
			$hasAccountManager = $checkStmt2->fetch();

			if ($hasRepresentative && $hasAccountManager) {
				// Include both representative and account_manager fields in insert
				$stmt = $pdo->prepare("INSERT INTO clients (
					name, contact_name, representative, account_manager, email, phone, address_line1, address_line2, city, state, postal_code, country, commission_percent_credit_card, commission_percent_credit_limit, status
				) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
				$stmt->execute([
					$name,
					$input['contact_name'] ?? null,
					$input['representative'] ?? null,
					$input['account_manager'] ?? null,
					$input['email'] ?? null,
					$input['phone'] ?? null,
					$input['address_line1'] ?? null,
					$input['address_line2'] ?? null,
					$input['city'] ?? null,
					$input['state'] ?? null,
					$input['postal_code'] ?? null,
					$input['country'] ?? null,
					$cc,
					$cl,
					(isset($input['status']) && in_array($input['status'], ['active','inactive'])) ? $input['status'] : 'active'
				]);
			} elseif ($hasRepresentative) {
				// Include only representative field in insert
				$stmt = $pdo->prepare("INSERT INTO clients (
					name, contact_name, representative, email, phone, address_line1, address_line2, city, state, postal_code, country, commission_percent_credit_card, commission_percent_credit_limit, status
				) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
				$stmt->execute([
					$name,
					$input['contact_name'] ?? null,
					$input['representative'] ?? null,
					$input['email'] ?? null,
					$input['phone'] ?? null,
					$input['address_line1'] ?? null,
					$input['address_line2'] ?? null,
					$input['city'] ?? null,
					$input['state'] ?? null,
					$input['postal_code'] ?? null,
					$input['country'] ?? null,
					$cc,
					$cl,
					(isset($input['status']) && in_array($input['status'], ['active','inactive'])) ? $input['status'] : 'active'
				]);
			} else {
				// Insert without representative or account_manager fields
				$stmt = $pdo->prepare("INSERT INTO clients (
					name, contact_name, email, phone, address_line1, address_line2, city, state, postal_code, country, commission_percent_credit_card, commission_percent_credit_limit, status
				) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
				$stmt->execute([
					$name,
					$input['contact_name'] ?? null,
					$input['email'] ?? null,
					$input['phone'] ?? null,
					$input['address_line1'] ?? null,
					$input['address_line2'] ?? null,
					$input['city'] ?? null,
					$input['state'] ?? null,
					$input['postal_code'] ?? null,
					$input['country'] ?? null,
					$cc,
					$cl,
					(isset($input['status']) && in_array($input['status'], ['active','inactive'])) ? $input['status'] : 'active'
				]);
			}
			
			$id = (int)$pdo->lastInsertId();
			$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
			$stmt->execute([$id]);
			respond(201, ['success' => true, 'data' => $stmt->fetch()]);
		}

		if ($action === 'update') {
			$id = isset($input['id']) ? (int)$input['id'] : 0;
			if ($id <= 0) { respond(400, ['success' => false, 'message' => 'Valid id is required']); }
			$cc = validateCommission($input['commission_percent_credit_card'] ?? 0);
			$cl = validateCommission($input['commission_percent_credit_limit'] ?? 0);
			if ($cc === null || $cl === null) { respond(400, ['success' => false, 'message' => 'Commission percents must be numbers between 0 and 100']); }

			// Check if representative column exists
			$checkStmt = $pdo->prepare("SHOW COLUMNS FROM clients LIKE 'representative'");
			$checkStmt->execute();
			$hasRepresentative = $checkStmt->fetch();

			// Check if account_manager column exists
			$checkStmt2 = $pdo->prepare("SHOW COLUMNS FROM clients LIKE 'account_manager'");
			$checkStmt2->execute();
			$hasAccountManager = $checkStmt2->fetch();

			if ($hasRepresentative && $hasAccountManager) {
				// Include both representative and account_manager fields in update
				$stmt = $pdo->prepare("UPDATE clients SET
					name = ?, contact_name = ?, representative = ?, account_manager = ?, email = ?, phone = ?, address_line1 = ?, address_line2 = ?, city = ?, state = ?, postal_code = ?, country = ?, commission_percent_credit_card = ?, commission_percent_credit_limit = ?, status = ?, updated_at = CURRENT_TIMESTAMP
					WHERE id = ?");
				$stmt->execute([
					trim((string)($input['name'] ?? '')),
					$input['contact_name'] ?? null,
					$input['representative'] ?? null,
					$input['account_manager'] ?? null,
					$input['email'] ?? null,
					$input['phone'] ?? null,
					$input['address_line1'] ?? null,
					$input['address_line2'] ?? null,
					$input['city'] ?? null,
					$input['state'] ?? null,
					$input['postal_code'] ?? null,
					$input['country'] ?? null,
					$cc,
					$cl,
					(isset($input['status']) && in_array($input['status'], ['active','inactive'])) ? $input['status'] : 'active',
					$id
				]);
			} elseif ($hasRepresentative) {
				// Include only representative field in update
				$stmt = $pdo->prepare("UPDATE clients SET
					name = ?, contact_name = ?, representative = ?, email = ?, phone = ?, address_line1 = ?, address_line2 = ?, city = ?, state = ?, postal_code = ?, country = ?, commission_percent_credit_card = ?, commission_percent_credit_limit = ?, status = ?, updated_at = CURRENT_TIMESTAMP
					WHERE id = ?");
				$stmt->execute([
					trim((string)($input['name'] ?? '')),
					$input['contact_name'] ?? null,
					$input['representative'] ?? null,
					$input['email'] ?? null,
					$input['phone'] ?? null,
					$input['address_line1'] ?? null,
					$input['address_line2'] ?? null,
					$input['city'] ?? null,
					$input['state'] ?? null,
					$input['postal_code'] ?? null,
					$input['country'] ?? null,
					$cc,
					$cl,
					(isset($input['status']) && in_array($input['status'], ['active','inactive'])) ? $input['status'] : 'active',
					$id
				]);
			} else {
				// Update without representative or account_manager fields
				$stmt = $pdo->prepare("UPDATE clients SET
					name = ?, contact_name = ?, email = ?, phone = ?, address_line1 = ?, address_line2 = ?, city = ?, state = ?, postal_code = ?, country = ?, commission_percent_credit_card = ?, commission_percent_credit_limit = ?, status = ?, updated_at = CURRENT_TIMESTAMP
					WHERE id = ?");
				$stmt->execute([
					trim((string)($input['name'] ?? '')),
					$input['contact_name'] ?? null,
					$input['email'] ?? null,
					$input['phone'] ?? null,
					$input['address_line1'] ?? null,
					$input['address_line2'] ?? null,
					$input['city'] ?? null,
					$input['state'] ?? null,
					$input['postal_code'] ?? null,
					$input['country'] ?? null,
					$cc,
					$cl,
					(isset($input['status']) && in_array($input['status'], ['active','inactive'])) ? $input['status'] : 'active',
					$id
				]);
			}
			
			$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
			$stmt->execute([$id]);
			respond(200, ['success' => true, 'data' => $stmt->fetch()]);
		}

		if ($action === 'delete') {
			$id = isset($input['id']) ? (int)$input['id'] : 0;
			if ($id <= 0) { respond(400, ['success' => false, 'message' => 'Valid id is required']); }
			$stmt = $pdo->prepare("DELETE FROM clients WHERE id = ?");
			$stmt->execute([$id]);
			respond(200, ['success' => true, 'message' => 'Deleted']);
		}

		respond(400, ['success' => false, 'message' => 'Unknown action']);
	}

	respond(405, ['success' => false, 'message' => 'Method not allowed']);
} catch (Throwable $e) {
	respond(500, ['success' => false, 'message' => 'Unexpected error: ' . $e->getMessage()]);
}
?>


