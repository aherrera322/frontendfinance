<?php
/**
 * Script to add the representative field to the clients table
 * Run this script once to update the database schema
 */

require_once __DIR__ . '/auth/config.php';

echo "🔄 Adding representative field to clients table...\n\n";

try {
    $pdo = getClientsDB();
    if (!$pdo) {
        echo "❌ Failed to connect to clients database\n";
        exit(1);
    }

    // Check if the representative field already exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM clients LIKE 'representative'");
    $stmt->execute();
    $columnExists = $stmt->fetch();

    if ($columnExists) {
        echo "✅ Representative field already exists in clients table\n";
    } else {
        // Add the representative field
        $sql = "ALTER TABLE clients ADD COLUMN representative VARCHAR(255) NULL AFTER contact_name";
        $pdo->exec($sql);
        echo "✅ Successfully added representative field to clients table\n";
    }

    // Show current table structure
    echo "\n📋 Current clients table structure:\n";
    $stmt = $pdo->prepare("DESCRIBE clients");
    $stmt->execute();
    $columns = $stmt->fetchAll();
    
    foreach ($columns as $column) {
        echo "  - {$column['Field']} ({$column['Type']}) - {$column['Null']}\n";
    }

    echo "\n🎉 Database update completed successfully!\n";

} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Unexpected error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
