<?php
require_once 'auth/config.php';

// Set content type to plain text for better readability
header('Content-Type: text/plain; charset=utf-8');

try {
    // Get database connections
    $reservationsPdo = getReservationsDB();
    $clientsPdo = getClientsDB();
    
    if (!$reservationsPdo || !$clientsPdo) {
        die("Database connection failed\n");
    }
    
    echo "🔄 Importing agencies from aero_res_22 to clients database...\n\n";
    
    // Get unique agencies from aero_res_22 table
    $stmt = $reservationsPdo->prepare("
        SELECT DISTINCT agency 
        FROM aero_res_22 
        WHERE agency IS NOT NULL 
        AND agency != '' 
        AND agency != 'N/A'
        ORDER BY agency ASC
    ");
    $stmt->execute();
    $agencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📊 Found " . count($agencies) . " unique agencies in aero_res_22 table\n\n";
    
    if (empty($agencies)) {
        echo "❌ No agencies found to import\n";
        exit;
    }
    
    // Check which agencies already exist in clients table
    $existingStmt = $clientsPdo->prepare("SELECT name FROM clients");
    $existingStmt->execute();
    $existingClients = $existingStmt->fetchAll(PDO::FETCH_COLUMN);
    
    $newAgencies = [];
    $skippedAgencies = [];
    
    foreach ($agencies as $agency) {
        $agencyName = trim($agency['agency']);
        
        if (in_array($agencyName, $existingClients)) {
            $skippedAgencies[] = $agencyName;
        } else {
            $newAgencies[] = $agencyName;
        }
    }
    
    echo "📋 Summary:\n";
    echo "   - Total agencies found: " . count($agencies) . "\n";
    echo "   - Already exist in clients: " . count($skippedAgencies) . "\n";
    echo "   - New agencies to add: " . count($newAgencies) . "\n\n";
    
    if (!empty($skippedAgencies)) {
        echo "⏭️  Skipping existing agencies:\n";
        foreach ($skippedAgencies as $agency) {
            echo "   - " . $agency . "\n";
        }
        echo "\n";
    }
    
    if (empty($newAgencies)) {
        echo "✅ All agencies already exist in clients database\n";
        exit;
    }
    
    // Insert new agencies with 15% commission for both CC% and Credit Limit %
    $insertStmt = $clientsPdo->prepare("
        INSERT INTO clients (
            name, 
            commission_percent_credit_card, 
            commission_percent_credit_limit, 
            status, 
            created_at, 
            updated_at
        ) VALUES (?, 15.00, 15.00, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
    ");
    
    $successCount = 0;
    $errorCount = 0;
    
    echo "💾 Adding new agencies with 15% commission...\n\n";
    
    foreach ($newAgencies as $agencyName) {
        try {
            $insertStmt->execute([$agencyName]);
            echo "✅ Added: " . $agencyName . " (15% CC, 15% Credit Limit)\n";
            $successCount++;
        } catch (PDOException $e) {
            echo "❌ Error adding " . $agencyName . ": " . $e->getMessage() . "\n";
            $errorCount++;
        }
    }
    
    echo "\n📈 Import Summary:\n";
    echo "   - Successfully added: " . $successCount . " agencies\n";
    echo "   - Errors: " . $errorCount . " agencies\n";
    echo "   - Total processed: " . count($newAgencies) . " agencies\n\n";
    
    if ($successCount > 0) {
        echo "🎉 Successfully imported " . $successCount . " agencies to clients database!\n";
        echo "   - All new agencies have 15% commission for both Credit Card and Credit Limit\n";
        echo "   - Status set to 'active'\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>

