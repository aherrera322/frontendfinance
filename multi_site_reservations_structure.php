<?php
require_once 'auth/config.php';

try {
    $pdo = getReservationsDB();
    
    echo "Setting up multi-site reservation structure:\n\n";
    
    // Create a table to track different data sources
    $pdo->exec("CREATE TABLE IF NOT EXISTS data_sources (
        id INT AUTO_INCREMENT PRIMARY KEY,
        source_name VARCHAR(255) NOT NULL UNIQUE,
        site_name VARCHAR(255) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ Created data_sources table\n";
    
    // Create a table for Site A reservations
    $pdo->exec("CREATE TABLE IF NOT EXISTS zbcom_reservations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        source_id INT NOT NULL,
        res_date DECIMAL(10,6),
        agency VARCHAR(255),
        pay_mode VARCHAR(255),
        api_value DECIMAL(10,2),
        credit DECIMAL(10,2),
        cpc VARCHAR(50),
        prepay VARCHAR(10),
        status VARCHAR(50),
        imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (source_id) REFERENCES data_sources(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ Created zbcom_reservations table\n";
    
    // Create a table for Site B reservations
    $pdo->exec("CREATE TABLE IF NOT EXISTS site_b_reservations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        source_id INT NOT NULL,
        res_date DECIMAL(10,6),
        agency VARCHAR(255),
        pay_mode VARCHAR(255),
        api_value DECIMAL(10,2),
        credit DECIMAL(10,2),
        cpc VARCHAR(50),
        prepay VARCHAR(10),
        status VARCHAR(50),
        imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (source_id) REFERENCES data_sources(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ Created site_b_reservations table\n";
    
    // Create a table for Site C reservations
    $pdo->exec("CREATE TABLE IF NOT EXISTS site_c_reservations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        source_id INT NOT NULL,
        res_date DECIMAL(10,6),
        agency VARCHAR(255),
        pay_mode VARCHAR(255),
        api_value DECIMAL(10,2),
        credit DECIMAL(10,2),
        cpc VARCHAR(50),
        prepay VARCHAR(10),
        status VARCHAR(50),
        imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (source_id) REFERENCES data_sources(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ Created site_c_reservations table\n";
    
    // Insert default data sources
    $sources = [
        ['zbcom', 'ZB.com', 'Main reservation site'],
        ['site_b', 'Site B', 'Secondary reservation site'],
        ['site_c', 'Site C', 'Third reservation site']
    ];
    
    foreach ($sources as $source) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO data_sources (source_name, site_name, description) VALUES (?, ?, ?)");
        $stmt->execute($source);
    }
    echo "✅ Inserted default data sources\n";
    
    // Create a view for combined data (optional)
    $pdo->exec("CREATE OR REPLACE VIEW all_reservations AS
        SELECT 'zbcom' as source, id, source_id, res_date, agency, pay_mode, api_value, credit, cpc, prepay, status, imported_at FROM zbcom_reservations
        UNION ALL
        SELECT 'site_b' as source, id, source_id, res_date, agency, pay_mode, api_value, credit, cpc, prepay, status, imported_at FROM site_b_reservations
        UNION ALL
        SELECT 'site_c' as source, id, source_id, res_date, agency, pay_mode, api_value, credit, cpc, prepay, status, imported_at FROM site_c_reservations");
    echo "✅ Created all_reservations view\n";
    
    echo "\nDatabase structure created successfully!\n";
    echo "\nTables created:\n";
    echo "- data_sources (tracks different data sources)\n";
    echo "- zbcom_reservations (for ZB.com data)\n";
    echo "- site_b_reservations (for Site B data)\n";
    echo "- site_c_reservations (for Site C data)\n";
    echo "- all_reservations view (combines all data)\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>

