<?php
/**
 * Web-based script to add the representative field to the clients table
 * Access this file in your browser: http://localhost/zimplerentals/update_clients_table.php
 */

require_once __DIR__ . '/auth/config.php';

echo "<h2>🔄 Updating Clients Table</h2>";

try {
    $pdo = getClientsDB();
    if (!$pdo) {
        echo "<p style='color: red;'>❌ Failed to connect to clients database</p>";
        exit;
    }

    // Check if the representative field already exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM clients LIKE 'representative'");
    $stmt->execute();
    $representativeExists = $stmt->fetch();

    if ($representativeExists) {
        echo "<p style='color: green;'>✅ Representative field already exists in clients table</p>";
    } else {
        // Add the representative field
        $sql = "ALTER TABLE clients ADD COLUMN representative VARCHAR(255) NULL AFTER contact_name";
        $pdo->exec($sql);
        echo "<p style='color: green;'>✅ Successfully added representative field to clients table</p>";
    }

    // Check if the account_manager field already exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM clients LIKE 'account_manager'");
    $stmt->execute();
    $accountManagerExists = $stmt->fetch();

    if ($accountManagerExists) {
        echo "<p style='color: green;'>✅ Account Manager field already exists in clients table</p>";
    } else {
        // Add the account_manager field
        $sql = "ALTER TABLE clients ADD COLUMN account_manager VARCHAR(255) NULL AFTER representative";
        $pdo->exec($sql);
        echo "<p style='color: green;'>✅ Successfully added account_manager field to clients table</p>";
    }

    // Show current table structure
    echo "<h3>📋 Current clients table structure:</h3>";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    $stmt = $pdo->prepare("DESCRIBE clients");
    $stmt->execute();
    $columns = $stmt->fetchAll();
    
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>{$column['Field']}</td>";
        echo "<td>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "<td>{$column['Key']}</td>";
        echo "<td>{$column['Default']}</td>";
        echo "<td>{$column['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";

    echo "<p style='color: green;'>🎉 Database update completed successfully!</p>";
    echo "<p><a href='admin/clients/index.html'>← Back to Clients Admin</a></p>";

} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Database error: " . $e->getMessage() . "</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Unexpected error: " . $e->getMessage() . "</p>";
}
?>
