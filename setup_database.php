<?php
/**
 * Database setup script - ensures all tables are created properly
 */

require_once 'auth/config.php';

echo "<h1>Database Setup</h1>\n";

try {
    echo "<h2>Step 1: Initializing databases</h2>\n";
    initializeDatabases();
    echo "✅ Databases initialized successfully<br>\n";

    echo "<h2>Step 2: Testing database connections</h2>\n";
    
    $reservationsDB = getReservationsDB();
    if ($reservationsDB) {
        echo "✅ Reservations database connection successful<br>\n";
    } else {
        echo "❌ Reservations database connection failed<br>\n";
        exit;
    }

    echo "<h2>Step 3: Checking users table</h2>\n";
    
    $stmt = $reservationsDB->prepare("SHOW TABLES LIKE 'users'");
    $stmt->execute();
    $tableExists = $stmt->fetch();
    
    if ($tableExists) {
        echo "✅ Users table exists<br>\n";
        
        // Check table structure
        $stmt = $reservationsDB->prepare("DESCRIBE users");
        $stmt->execute();
        $columns = $stmt->fetchAll();
        
        echo "Table structure:<br>\n";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>\n";
        
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>{$column['Field']}</td>";
            echo "<td>{$column['Type']}</td>";
            echo "<td>{$column['Null']}</td>";
            echo "<td>{$column['Key']}</td>";
            echo "<td>{$column['Default']}</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
        
    } else {
        echo "❌ Users table does not exist<br>\n";
        exit;
    }

    echo "<h2>Step 4: Creating admin user</h2>\n";
    
    // Check if admin user exists
    $stmt = $reservationsDB->prepare("SELECT id FROM users WHERE email = 'admin@zimple.com'");
    $stmt->execute();
    $adminExists = $stmt->fetch();
    
    if ($adminExists) {
        echo "✅ Admin user already exists<br>\n";
    } else {
        $passwordHash = password_hash('Admin123!', PASSWORD_DEFAULT);
        
        $stmt = $reservationsDB->prepare("INSERT INTO users (first_name, last_name, email, password_hash, department, email_verified, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute(['System', 'Administrator', 'admin@zimple.com', $passwordHash, 'site_administrator', true, true]);
        
        echo "✅ Admin user created successfully<br>\n";
    }

    echo "<h2>Step 5: Testing login credentials</h2>\n";
    
    $stmt = $reservationsDB->prepare("SELECT password_hash FROM users WHERE email = 'admin@zimple.com'");
    $stmt->execute();
    $user = $stmt->fetch();
    
    if ($user && password_verify('Admin123!', $user['password_hash'])) {
        echo "✅ Password verification works correctly<br>\n";
    } else {
        echo "❌ Password verification failed<br>\n";
    }

    echo "<h2>Setup Complete!</h2>\n";
    echo "You can now try logging in with:<br>\n";
    echo "<strong>Email:</strong> admin@zimple.com<br>\n";
    echo "<strong>Password:</strong> Admin123!<br>\n";
    echo "<br>\n";
    echo "<a href='login.html'>Go to Login Page</a><br>\n";

} catch (Exception $e) {
    echo "<h2>Error</h2>\n";
    echo "❌ Setup failed: " . $e->getMessage() . "<br>\n";
    echo "<pre>" . $e->getTraceAsString() . "</pre>\n";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h1 { color: #117372; }
h2 { color: #333; margin-top: 30px; }
table { font-size: 12px; }
th, td { padding: 5px; text-align: left; }
</style>

