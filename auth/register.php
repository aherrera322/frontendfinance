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
    $required_fields = ['firstName', 'lastName', 'email', 'password'];
    foreach ($required_fields as $field) {
        if (empty($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    // Validate email format
    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }
    
    // Validate password length
    if (strlen($input['password']) < 8) {
        throw new Exception('Password must be at least 8 characters long');
    }
    
    // Sanitize input
    $firstName = trim($input['firstName']);
    $lastName = trim($input['lastName']);
    $email = strtolower(trim($input['email']));
    $phone = isset($input['phone']) ? trim($input['phone']) : null;
    $company = isset($input['company']) ? trim($input['company']) : null;
    $password = $input['password'];
    $newsletter = isset($input['newsletter']) ? (bool)$input['newsletter'] : false;
    
    // Validate name length
    if (strlen($firstName) > 50 || strlen($lastName) > 50) {
        throw new Exception('Name must be less than 50 characters');
    }
    
    // Validate email length
    if (strlen($email) > 100) {
        throw new Exception('Email must be less than 100 characters');
    }
    
    // Get database connection
    $pdo = getDBConnection();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->fetch()) {
        throw new Exception('Email address is already registered');
    }
    
    // Hash password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    // Generate verification token
    $verificationToken = bin2hex(random_bytes(32));
    
    // Insert new user
    $stmt = $pdo->prepare("
        INSERT INTO users (
            first_name, last_name, email, phone, company, 
            password_hash, newsletter_subscribed, verification_token
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $firstName,
        $lastName,
        $email,
        $phone,
        $company,
        $passwordHash,
        $newsletter,
        $verificationToken
    ]);
    
    $userId = $pdo->lastInsertId();
    
    // Log successful registration
    error_log("New user registered: $email (ID: $userId)");
    
    // Send verification email (optional - you can implement this later)
    // sendVerificationEmail($email, $verificationToken);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Account created successfully! Please check your email to verify your account.',
        'user_id' => $userId
    ]);
    
} catch (Exception $e) {
    error_log("Registration error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Function to send verification email (placeholder)
function sendVerificationEmail($email, $token) {
    // This is a placeholder - you can implement email sending later
    // For now, we'll just log the verification token
    error_log("Verification email would be sent to: $email with token: $token");
    
    // Example implementation with PHPMailer or similar:
    /*
    $to = $email;
    $subject = "Verify your Zimple Travel Group account";
    $message = "Please click the following link to verify your account: ";
    $message .= "http://yourdomain.com/verify.php?token=" . $token;
    
    mail($to, $subject, $message);
    */
}
?>

