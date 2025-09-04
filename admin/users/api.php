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
require_once __DIR__ . '/../../auth/permissions.php';

function respond($statusCode, $payload) {
	http_response_code($statusCode);
	echo json_encode($payload);
	exit;
}

function db() {
	$pdo = getReservationsDB();
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
	$stmt = $authPdo->prepare("SELECT s.session_token, u.* FROM user_sessions s JOIN users u ON s.user_id = u.id WHERE s.session_token = ? AND s.is_active = 1 AND s.expires_at > NOW() AND u.is_active = 1");
	$stmt->execute([$token]);
	$session = $stmt->fetch();
	if (!$session) {
		respond(401, ['success' => false, 'message' => 'Unauthorized']);
	}
	return $session;
}

function readJson() {
	$raw = file_get_contents('php://input');
	$data = json_decode($raw, true);
	return is_array($data) ? $data : [];
}

function validateEmail($email) {
	return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePassword($password) {
	return strlen($password) >= 8;
}

	try {
	$method = $_SERVER['REQUEST_METHOD'];
	$currentUser = requireAuth();
	$pdo = db();

	if ($method === 'GET') {
		$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
		$pageSize = isset($_GET['page_size']) ? max(1, min(100, (int)$_GET['page_size'])) : 20;
		$search = isset($_GET['search']) ? trim($_GET['search']) : '';
		$offset = ($page - 1) * $pageSize;

		$whereClause = '';
		$params = [];

		if ($search !== '') {
			$whereClause = "WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR company LIKE ?";
			$searchParam = "%$search%";
			$params = [$searchParam, $searchParam, $searchParam, $searchParam];
		}

		// Get total count
		$countSql = "SELECT COUNT(*) as total FROM users $whereClause";
		$stmt = $pdo->prepare($countSql);
		$stmt->execute($params);
		$total = (int)$stmt->fetch()['total'];

		// Get paginated results
		$sql = "SELECT id, first_name, last_name, email, phone, company, department, newsletter_subscribed, email_verified, is_active, created_at, last_login FROM users $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?";
		$stmt = $pdo->prepare($sql);
		$stmt->execute(array_merge($params, [$pageSize, $offset]));
		$users = $stmt->fetchAll();

		// Add role information to response
		$roleDisplayNames = getRoleDisplayNames();
		$usersWithRoles = array_map(function($user) use ($roleDisplayNames) {
			$user['department_display'] = $roleDisplayNames[$user['department']] ?? $user['department'];
			return $user;
		}, $users);

		respond(200, [
			'success' => true,
			'data' => $usersWithRoles,
			'pagination' => [
				'page' => $page,
				'page_size' => $pageSize,
				'total' => $total
			],
			'roles' => $roleDisplayNames,
			'current_user_permissions' => getRolePermissions($currentUser['department'])
		]);
	}

	if ($method === 'POST') {
		$input = readJson();
		$action = isset($input['action']) ? strtolower(trim((string)$input['action'])) : 'create';

		if ($action === 'create') {
			// Check permissions
			if (!hasPermission($currentUser, 'users_create')) {
				respond(403, ['success' => false, 'message' => 'Insufficient permissions to create users']);
			}

			$firstName = isset($input['first_name']) ? trim((string)$input['first_name']) : '';
			$lastName = isset($input['last_name']) ? trim((string)$input['last_name']) : '';
			$email = isset($input['email']) ? strtolower(trim((string)$input['email'])) : '';
			$password = $input['password'] ?? '';
			$department = isset($input['department']) ? trim((string)$input['department']) : 'agent_zimple';

			if ($firstName === '') { respond(400, ['success' => false, 'message' => 'First name is required']); }
			if ($lastName === '') { respond(400, ['success' => false, 'message' => 'Last name is required']); }
			if ($email === '') { respond(400, ['success' => false, 'message' => 'Email is required']); }
			if (!validateEmail($email)) { respond(400, ['success' => false, 'message' => 'Invalid email format']); }
			if ($password === '') { respond(400, ['success' => false, 'message' => 'Password is required']); }
			if (!validatePassword($password)) { respond(400, ['success' => false, 'message' => 'Password must be at least 8 characters long']); }

			// Validate department
			$validDepartments = getAvailableRoles();
			if (!in_array($department, $validDepartments)) {
				respond(400, ['success' => false, 'message' => 'Invalid department selected']);
			}

			// Check if email already exists
			$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
			$stmt->execute([$email]);
			if ($stmt->fetch()) {
				respond(400, ['success' => false, 'message' => 'Email address is already registered']);
			}

			// Hash password
			$passwordHash = password_hash($password, PASSWORD_DEFAULT);

			$stmt = $pdo->prepare("INSERT INTO users (
				first_name, last_name, email, phone, company, password_hash, department, newsletter_subscribed, email_verified, is_active
			) VALUES (?,?,?,?,?,?,?,?,?,?)");
			$stmt->execute([
				$firstName,
				$lastName,
				$email,
				$input['phone'] ?? null,
				$input['company'] ?? null,
				$passwordHash,
				$department,
				isset($input['newsletter_subscribed']) ? (bool)$input['newsletter_subscribed'] : false,
				isset($input['email_verified']) ? (bool)$input['email_verified'] : false,
				isset($input['is_active']) ? (bool)$input['is_active'] : true
			]);
			$id = (int)$pdo->lastInsertId();
			$stmt = $pdo->prepare("SELECT id, first_name, last_name, email, phone, company, department, newsletter_subscribed, email_verified, is_active, created_at, last_login FROM users WHERE id = ?");
			$stmt->execute([$id]);
			respond(201, ['success' => true, 'data' => $stmt->fetch()]);
		}

		if ($action === 'update') {
			// Check permissions
			if (!hasPermission($currentUser, 'users_edit')) {
				respond(403, ['success' => false, 'message' => 'Insufficient permissions to edit users']);
			}

			$id = isset($input['id']) ? (int)$input['id'] : 0;
			if ($id <= 0) { respond(400, ['success' => false, 'message' => 'Invalid user ID']); }

			$firstName = isset($input['first_name']) ? trim((string)$input['first_name']) : '';
			$lastName = isset($input['last_name']) ? trim((string)$input['last_name']) : '';
			$email = isset($input['email']) ? strtolower(trim((string)$input['email'])) : '';
			$department = isset($input['department']) ? trim((string)$input['department']) : 'agent_zimple';

			if ($firstName === '') { respond(400, ['success' => false, 'message' => 'First name is required']); }
			if ($lastName === '') { respond(400, ['success' => false, 'message' => 'Last name is required']); }
			if ($email === '') { respond(400, ['success' => false, 'message' => 'Email is required']); }
			if (!validateEmail($email)) { respond(400, ['success' => false, 'message' => 'Invalid email format']); }

			// Validate department
			$validDepartments = getAvailableRoles();
			if (!in_array($department, $validDepartments)) {
				respond(400, ['success' => false, 'message' => 'Invalid department selected']);
			}

			// Check if email already exists for other users
			$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
			$stmt->execute([$email, $id]);
			if ($stmt->fetch()) {
				respond(400, ['success' => false, 'message' => 'Email address is already registered by another user']);
			}

			$updateFields = [
				'first_name' => $firstName,
				'last_name' => $lastName,
				'email' => $email,
				'department' => $department,
				'phone' => $input['phone'] ?? null,
				'company' => $input['company'] ?? null,
				'newsletter_subscribed' => isset($input['newsletter_subscribed']) ? (bool)$input['newsletter_subscribed'] : false,
				'email_verified' => isset($input['email_verified']) ? (bool)$input['email_verified'] : false,
				'is_active' => isset($input['is_active']) ? (bool)$input['is_active'] : true
			];

			// Handle password update if provided
			if (!empty($input['password'])) {
				if (!validatePassword($input['password'])) {
					respond(400, ['success' => false, 'message' => 'Password must be at least 8 characters long']);
				}
				$updateFields['password_hash'] = password_hash($input['password'], PASSWORD_DEFAULT);
			}

			$setClause = implode(', ', array_map(fn($field) => "$field = ?", array_keys($updateFields)));
			$sql = "UPDATE users SET $setClause WHERE id = ?";
			$stmt = $pdo->prepare($sql);
			$stmt->execute(array_merge(array_values($updateFields), [$id]));

			$stmt = $pdo->prepare("SELECT id, first_name, last_name, email, phone, company, department, newsletter_subscribed, email_verified, is_active, created_at, last_login FROM users WHERE id = ?");
			$stmt->execute([$id]);
			respond(200, ['success' => true, 'data' => $stmt->fetch()]);
		}

		if ($action === 'delete') {
			// Check permissions
			if (!hasPermission($currentUser, 'users_delete')) {
				respond(403, ['success' => false, 'message' => 'Insufficient permissions to delete users']);
			}

			$id = isset($input['id']) ? (int)$input['id'] : 0;
			if ($id <= 0) { respond(400, ['success' => false, 'message' => 'Invalid user ID']); }

			// Check if user exists
			$stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
			$stmt->execute([$id]);
			if (!$stmt->fetch()) {
				respond(404, ['success' => false, 'message' => 'User not found']);
			}

			// Delete user sessions first (due to foreign key constraint)
			$stmt = $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ?");
			$stmt->execute([$id]);

			// Delete user
			$stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
			$stmt->execute([$id]);

			respond(200, ['success' => true, 'message' => 'User deleted successfully']);
		}

		respond(400, ['success' => false, 'message' => 'Invalid action']);
	}

	respond(405, ['success' => false, 'message' => 'Method not allowed']);

} catch (Exception $e) {
	error_log("Users API error: " . $e->getMessage());
	respond(500, ['success' => false, 'message' => 'Internal server error']);
}
?>
