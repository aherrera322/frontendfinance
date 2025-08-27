<?php
// Database configuration
define('DB_HOST', 'localhost');
// Treat current database as the reservations database for backward compatibility
define('DB_NAME', 'zimple_travel'); // reservations database (legacy constant)
define('DB_USER', 'root');
define('DB_PASS', '');

// New logical databases
define('DB_RESERVATIONS', DB_NAME);
define('DB_PARTNERS', 'zimple_partners');
define('DB_CLIENTS', 'zimple_clients');

// Create database connection (optionally to a specific database)
function getDBConnection($dbName = DB_RESERVATIONS) {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . $dbName . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed (" . $dbName . "): " . $e->getMessage());
        return null;
    }
}

function getReservationsDB() {
    return getDBConnection(DB_RESERVATIONS);
}

function getPartnersDB() {
    return getDBConnection(DB_PARTNERS);
}

function getClientsDB() {
    return getDBConnection(DB_CLIENTS);
}

// Initialize all databases and create tables if they don't exist
function initializeDatabases() {
    try {
        // First connect without database name to create them if needed
        $rootPdo = new PDO(
            "mysql:host=" . DB_HOST . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        // Create databases if they do not exist
        $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_RESERVATIONS . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_PARTNERS . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_CLIENTS . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        // -----------------------------
        // Reservations DB: auth tables live here and reports import into tables within this DB
        // -----------------------------
        $rootPdo->exec("USE `" . DB_RESERVATIONS . "`");

        // users table
        $rootPdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                first_name VARCHAR(50) NOT NULL,
                last_name VARCHAR(50) NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                phone VARCHAR(20),
                company VARCHAR(100),
                password_hash VARCHAR(255) NOT NULL,
                newsletter_subscribed BOOLEAN DEFAULT FALSE,
                email_verified BOOLEAN DEFAULT FALSE,
                verification_token VARCHAR(255),
                reset_token VARCHAR(255),
                reset_token_expires DATETIME,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                last_login DATETIME,
                is_active BOOLEAN DEFAULT TRUE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // user_sessions table
        $rootPdo->exec("
            CREATE TABLE IF NOT EXISTS user_sessions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                session_token VARCHAR(255) UNIQUE NOT NULL,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at TIMESTAMP NOT NULL,
                is_active BOOLEAN DEFAULT TRUE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // login_attempts table
        $rootPdo->exec("
            CREATE TABLE IF NOT EXISTS login_attempts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(100) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                success BOOLEAN DEFAULT FALSE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // -----------------------------
        // Partners DB: store partner contact info and a commission percentage
        // -----------------------------
        $rootPdo->exec("USE `" . DB_PARTNERS . "`");
        $rootPdo->exec("
            CREATE TABLE IF NOT EXISTS partners (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(150) NOT NULL,
                contact_name VARCHAR(150) NULL,
                email VARCHAR(150) NULL,
                phone VARCHAR(25) NULL,
                address_line1 VARCHAR(200) NULL,
                address_line2 VARCHAR(200) NULL,
                city VARCHAR(100) NULL,
                state VARCHAR(100) NULL,
                postal_code VARCHAR(20) NULL,
                country VARCHAR(100) NULL,
                commission_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                status ENUM('active','inactive') NOT NULL DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_partner_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // -----------------------------
        // Clients DB: contact info and commission by payment type (credit card vs credit limit)
        // -----------------------------
        $rootPdo->exec("USE `" . DB_CLIENTS . "`");
        $rootPdo->exec("
            CREATE TABLE IF NOT EXISTS clients (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(150) NOT NULL,
                contact_name VARCHAR(150) NULL,
                email VARCHAR(150) NULL,
                phone VARCHAR(25) NULL,
                address_line1 VARCHAR(200) NULL,
                address_line2 VARCHAR(200) NULL,
                city VARCHAR(100) NULL,
                state VARCHAR(100) NULL,
                postal_code VARCHAR(20) NULL,
                country VARCHAR(100) NULL,
                commission_percent_credit_card DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                commission_percent_credit_limit DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                status ENUM('active','inactive') NOT NULL DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_client_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        return true;
    } catch (PDOException $e) {
        error_log("Database initialization failed: " . $e->getMessage());
        return false;
    }
}

// Backward-compatible function name (existing code may call this)
function initializeDatabase() {
    return initializeDatabases();
}

// Initialize databases on first load
initializeDatabases();
?>

