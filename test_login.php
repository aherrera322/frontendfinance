<?php
/**
 * Test script to check database state and help debug login issues
 */

require_once 'auth/config.php';

echo "<h1>Database State Check</h1>\n";

try {
    $pdo = getReservationsDB();
    if (!$pdo) {
        throw new Exception("Database connection failed");
    }

    echo "<h2>1. Checking users table structure</h2>\n";
    
    // Check if department column exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'department'");
    $stmt->execute();
    $columnExists = $stmt->fetch();
    
    if ($columnExists) {
        echo "✅ Department column exists<br>\n";
    } else {
        echo "❌ Department column does NOT exist<br>\n";
        echo "You need to run the migration script first: <a href='update_users_with_roles.php'>update_users_with_roles.php</a><br>\n";
    }

    echo "<h2>2. Checking existing users</h2>\n";
    
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, department, is_active FROM users ORDER BY id");
    $stmt->execute();
    $users = $stmt->fetchAll();
    
    if (empty($users)) {
        echo "❌ No users found in database<br>\n";
        echo "You need to create users first or run the migration script<br>\n";
    } else {
        echo "Found " . count($users) . " users:<br>\n";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
        echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Department</th><th>Active</th></tr>\n";
        
        foreach ($users as $user) {
            $department = $user['department'] ?: 'NULL';
            $active = $user['is_active'] ? 'Yes' : 'No';
            echo "<tr>";
            echo "<td>{$user['id']}</td>";
            echo "<td>{$user['first_name']} {$user['last_name']}</td>";
            echo "<td>{$user['email']}</td>";
            echo "<td>{$department}</td>";
            echo "<td>{$active}</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
    }

    echo "<h2>3. Testing admin user creation</h2>\n";
    
    // Check if admin user exists
    $stmt = $pdo->prepare("SELECT id, email, department FROM users WHERE email = 'admin@zimple.com'");
    $stmt->execute();
    $adminUser = $stmt->fetch();
    
    if ($adminUser) {
        echo "✅ Admin user exists:<br>\n";
        echo "   Email: {$adminUser['email']}<br>\n";
        echo "   Department: {$adminUser['department']}<br>\n";
        echo "   Password: Admin123!<br>\n";
    } else {
        echo "❌ Admin user does not exist<br>\n";
        echo "Creating admin user now...<br>\n";
        
        $passwordHash = password_hash('Admin123!', PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password_hash, department, email_verified, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute(['System', 'Administrator', 'admin@zimple.com', $passwordHash, 'site_administrator', true, true]);
        
        echo "✅ Admin user created successfully!<br>\n";
        echo "   Email: admin@zimple.com<br>\n";
        echo "   Password: Admin123!<br>\n";
    }

    echo "<h2>4. Testing password verification</h2>\n";
    
    if ($adminUser) {
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE email = 'admin@zimple.com'");
        $stmt->execute();
        $user = $stmt->fetch();
        
        if (password_verify('Admin123!', $user['password_hash'])) {
            echo "✅ Password verification works correctly<br>\n";
        } else {
            echo "❌ Password verification failed<br>\n";
        }
    }

    echo "<h2>5. Next Steps</h2>\n";
    echo "1. Try logging in with: admin@zimple.com / Admin123!<br>\n";
    echo "2. If that doesn't work, check your browser's developer console for errors<br>\n";
    echo "3. Make sure you're accessing the login page correctly<br>\n";
    echo "4. Check that cookies are enabled in your browser<br>\n";

} catch (Exception $e) {
    echo "<h2>Error</h2>\n";
    echo "❌ Error: " . $e->getMessage() . "<br>\n";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h1 { color: #117372; }
h2 { color: #333; margin-top: 30px; }
table { font-size: 12px; }
th, td { padding: 5px; text-align: left; }
</style>

