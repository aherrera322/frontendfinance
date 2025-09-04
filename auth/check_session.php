<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Get session token from cookie
    $sessionToken = $_COOKIE['session_token'] ?? null;
    
    if (!$sessionToken) {
        echo json_encode([
            'success' => false,
            'message' => 'No session found',
            'authenticated' => false
        ]);
        exit;
    }
    
    // Get database connection
    $pdo = getReservationsDB();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    // Check if session is valid
    $stmt = $pdo->prepare("
        SELECT s.session_token, s.expires_at, s.is_active,
               u.id, u.first_name, u.last_name, u.email, u.email_verified, u.department
        FROM user_sessions s
        JOIN users u ON s.user_id = u.id
        WHERE s.session_token = ? 
        AND s.is_active = 1 
        AND s.expires_at > NOW()
        AND u.is_active = 1
    ");
    $stmt->execute([$sessionToken]);
    $session = $stmt->fetch();
    
    if (!$session) {
        // Invalid or expired session
        echo json_encode([
            'success' => false,
            'message' => 'Session expired or invalid',
            'authenticated' => false
        ]);
        exit;
    }
    
    // Session is valid
    echo json_encode([
        'success' => true,
        'authenticated' => true,
        'user' => [
            'id' => $session['id'],
            'first_name' => $session['first_name'],
            'last_name' => $session['last_name'],
            'email' => $session['email'],
            'email_verified' => $session['email_verified'],
            'department' => $session['department']
        ],
        'session' => [
            'expires_at' => $session['expires_at']
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Session check error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Session check failed',
        'authenticated' => false
    ]);
}
?>

