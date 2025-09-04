<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Validate required fields
    if (empty($input['email']) || empty($input['password'])) {
        throw new Exception('Email and password are required');
    }
    
    // Sanitize input
    $email = strtolower(trim($input['email']));
    $password = $input['password'];
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // Get database connection
    $pdo = getReservationsDB();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    // Check for too many failed login attempts
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as attempts 
        FROM login_attempts 
        WHERE email = ? AND ip_address = ? AND success = 0 
        AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    $stmt->execute([$email, $ipAddress]);
    $failedAttempts = $stmt->fetch()['attempts'];
    
    if ($failedAttempts >= 5) {
        throw new Exception('Too many failed login attempts. Please try again in 15 minutes.');
    }
    
    // Get user by email
    $stmt = $pdo->prepare("
        SELECT id, first_name, last_name, email, password_hash, is_active, email_verified, last_login, department
        FROM users 
        WHERE email = ?
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    // Log login attempt
    $stmt = $pdo->prepare("
        INSERT INTO login_attempts (email, ip_address, success) 
        VALUES (?, ?, ?)
    ");
    
    if (!$user) {
        // User not found
        $stmt->execute([$email, $ipAddress, false]);
        throw new Exception('Invalid email or password');
    }
    
    // Check if account is active
    if (!$user['is_active']) {
        $stmt->execute([$email, $ipAddress, false]);
        throw new Exception('Account is deactivated. Please contact support.');
    }
    
    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        $stmt->execute([$email, $ipAddress, false]);
        throw new Exception('Invalid email or password');
    }
    
    // Check if email is verified (optional - you can remove this check)
    if (!$user['email_verified']) {
        // For now, we'll allow login without email verification
        // You can uncomment the next line to require email verification
        // throw new Exception('Please verify your email address before logging in.');
    }
    
    // Login successful - log the attempt
    $stmt->execute([$email, $ipAddress, true]);
    
    // Generate session token
    $sessionToken = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    // Create session
    $stmt = $pdo->prepare("
        INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, expires_at)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $user['id'],
        $sessionToken,
        $ipAddress,
        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        $expiresAt
    ]);
    
    // Update last login time
    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);
    
    // Set session cookie
    setcookie('session_token', $sessionToken, time() + (24 * 60 * 60), '/', '', false, true);
    
    // Log successful login
    error_log("User logged in: $email (ID: {$user['id']})");
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'user' => [
            'id' => $user['id'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'email' => $user['email'],
            'department' => $user['department'],
            'email_verified' => $user['email_verified']
        ],
        'session_token' => $sessionToken
    ]);
    
} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

