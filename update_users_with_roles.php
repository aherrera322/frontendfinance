<?php
/**
 * Migration script to add department/role support to existing users
 * Run this script once to update the database structure and existing users
 */

require_once 'auth/config.php';
require_once 'auth/permissions.php';

echo "<h1>User Role Migration Script</h1>\n";

try {
    $pdo = getReservationsDB();
    if (!$pdo) {
        throw new Exception("Database connection failed");
    }

    echo "<h2>Step 1: Adding department column to users table</h2>\n";
    
    // Check if department column exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'department'");
    $stmt->execute();
    $columnExists = $stmt->fetch();
    
    if (!$columnExists) {
        // Add department column
        $pdo->exec("ALTER TABLE users ADD COLUMN department ENUM('site_administrator', 'agent_aerovision', 'agent_zimple', 'accounting_aerovision', 'accounting_zimple', 'accounting_manager') DEFAULT 'agent_zimple' AFTER password_hash");
        echo "✅ Department column added successfully<br>\n";
    } else {
        echo "✅ Department column already exists<br>\n";
    }

    // Check if permissions column exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'permissions'");
    $stmt->execute();
    $permissionsColumnExists = $stmt->fetch();
    
    if (!$permissionsColumnExists) {
        // Add permissions column
        $pdo->exec("ALTER TABLE users ADD COLUMN permissions JSON AFTER department");
        echo "✅ Permissions column added successfully<br>\n";
    } else {
        echo "✅ Permissions column already exists<br>\n";
    }

    echo "<h2>Step 2: Updating existing users with default roles</h2>\n";
    
    // Get all users without department set
    $stmt = $pdo->prepare("SELECT id, email, first_name, last_name FROM users WHERE department IS NULL OR department = ''");
    $stmt->execute();
    $usersToUpdate = $stmt->fetchAll();
    
    echo "Found " . count($usersToUpdate) . " users to update<br>\n";
    
    $updateCount = 0;
    foreach ($usersToUpdate as $user) {
        // Set default role based on email or other criteria
        $defaultRole = 'agent_zimple'; // Default role
        
        // You can add logic here to assign specific roles based on email patterns
        if (strpos($user['email'], 'admin') !== false || strpos($user['email'], 'administrator') !== false) {
            $defaultRole = 'site_administrator';
        } elseif (strpos($user['email'], 'accounting') !== false || strpos($user['email'], 'finance') !== false) {
            $defaultRole = 'accounting_manager';
        } elseif (strpos($user['email'], 'aerovision') !== false) {
            $defaultRole = 'agent_aerovision';
        }
        
        // Update user with role
        $stmt = $pdo->prepare("UPDATE users SET department = ? WHERE id = ?");
        $stmt->execute([$defaultRole, $user['id']]);
        
        echo "✅ Updated user {$user['first_name']} {$user['last_name']} ({$user['email']}) with role: " . getRoleDisplayNames()[$defaultRole] . "<br>\n";
        $updateCount++;
    }
    
    echo "<h2>Step 3: Creating a default administrator user</h2>\n";
    
    // Check if any site administrator exists
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE department = 'site_administrator'");
    $stmt->execute();
    $adminCount = $stmt->fetch()['count'];
    
    if ($adminCount == 0) {
        // Create a default administrator
        $adminEmail = 'admin@zimple.com';
        $adminPassword = 'Admin123!'; // Change this to a secure password
        
        // Check if admin user already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$adminEmail]);
        $existingAdmin = $stmt->fetch();
        
        if (!$existingAdmin) {
            $passwordHash = password_hash($adminPassword, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password_hash, department, email_verified, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute(['System', 'Administrator', $adminEmail, $passwordHash, 'site_administrator', true, true]);
            
            echo "✅ Created default administrator user:<br>\n";
            echo "   Email: {$adminEmail}<br>\n";
            echo "   Password: {$adminPassword}<br>\n";
            echo "   <strong>IMPORTANT: Change this password immediately after first login!</strong><br>\n";
        } else {
            // Update existing admin user to have administrator role
            $stmt = $pdo->prepare("UPDATE users SET department = 'site_administrator' WHERE email = ?");
            $stmt->execute([$adminEmail]);
            echo "✅ Updated existing admin user with administrator role<br>\n";
        }
    } else {
        echo "✅ Site administrator already exists<br>\n";
    }

    echo "<h2>Migration Summary</h2>\n";
    echo "✅ Database structure updated<br>\n";
    echo "✅ {$updateCount} users updated with roles<br>\n";
    echo "✅ Default administrator configured<br>\n";
    
    echo "<h2>Available Roles</h2>\n";
    $roleNames = getRoleDisplayNames();
    foreach ($roleNames as $role => $displayName) {
        echo "• <strong>{$displayName}</strong>: " . getRoleDescription($role) . "<br>\n";
    }
    
    echo "<h2>Next Steps</h2>\n";
    echo "1. Login with the administrator account<br>\n";
    echo "2. Navigate to Admin Users to manage user roles<br>\n";
    echo "3. Update user roles as needed<br>\n";
    echo "4. Test permissions for different roles<br>\n";

} catch (Exception $e) {
    echo "<h2>Error</h2>\n";
    echo "❌ Migration failed: " . $e->getMessage() . "<br>\n";
    echo "Please check your database connection and try again.<br>\n";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h1 { color: #117372; }
h2 { color: #333; margin-top: 30px; }
</style>

