<?php
require_once 'auth/config.php';

try {
    $pdo = getDBConnection();
    
    // Check if user already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute(['admin@zimplerentals.com']);
    $existingUser = $stmt->fetch();
    
    if ($existingUser) {
        echo "User already exists with email: admin@zimplerentals.com\n";
        echo "Password: admin123\n";
    } else {
        // Create new user
        $passwordHash = password_hash('admin123', PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("
            INSERT INTO users (first_name, last_name, email, password_hash, is_active, email_verified, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            'Admin',
            'User',
            'admin@zimplerentals.com',
            $passwordHash,
            1, // is_active
            1  // email_verified
        ]);
        
        echo "✅ User created successfully!\n";
        echo "Email: admin@zimplerentals.com\n";
        echo "Password: admin123\n";
        echo "You can now log in at: http://localhost/zimplerentals/login.php\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
