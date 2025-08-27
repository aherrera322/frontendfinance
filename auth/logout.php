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
    // Get session token from cookie
    $sessionToken = $_COOKIE['session_token'] ?? null;
    
    if (!$sessionToken) {
        // No session to logout
        echo json_encode([
            'success' => true,
            'message' => 'No active session found'
        ]);
        exit;
    }
    
    // Get database connection
    $pdo = getDBConnection();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    // Invalidate session
    $stmt = $pdo->prepare("
        UPDATE user_sessions 
        SET is_active = 0 
        WHERE session_token = ? AND is_active = 1
    ");
    $stmt->execute([$sessionToken]);
    
    // Clear session cookie
    setcookie('session_token', '', time() - 3600, '/', '', false, true);
    
    // Log logout
    error_log("User logged out: session token $sessionToken");
    
    echo json_encode([
        'success' => true,
        'message' => 'Logged out successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Logout error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Logout failed'
    ]);
}
?>

