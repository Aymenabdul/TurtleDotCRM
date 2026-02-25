<?php
// config.php
// MySQL Database Configuration

// Database credentials
$db_host = 'localhost';
$db_name = 'lead_maintenance';
$db_user = 'root';
$db_pass = 'Parkour@123';
$db_charset = 'utf8mb4';

$dsn = "mysql:host=$db_host;dbname=$db_name;charset=$db_charset";

try {
    $pdo = new PDO($dsn, $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("SET names utf8mb4 COLLATE utf8mb4_unicode_ci");

    // Simple Admin Check (only runs if users table is empty)
    try {
        $checkUser = $pdo->query("SELECT COUNT(*) FROM users");
        if ($checkUser && $checkUser->fetchColumn() == 0) {
            $adminPass = password_hash('admin123', PASSWORD_BCRYPT);
            $adminUid = 'admin_' . bin2hex(random_bytes(4));
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, role, unique_id, is_active) VALUES (?, ?, ?, ?, 'admin', ?, 1)");
            $stmt->execute(['admin', 'admin@turtledot.com', $adminPass, 'System Administrator', $adminUid]);
        }
    } catch (Exception $e) {
        // Table might not exist yet, which is fine
    }

} catch (PDOException $e) {
    die("Connection Failed: " . $e->getMessage() . " (Check your credentials in config.php)");
}

// VAPID Keys for Push Notifications
define('VAPID_PUBLIC_KEY', 'BOT34D3Wld3Hw7tnAThhk6XfrY3t-PZ1hMMr6BJJNC6oA0Yx9s6bw4NGF1J9AOvohWXt5y-BSOWXtK9LUftWj7E');
define('VAPID_PRIVATE_KEY', 'S7smjXkDZo0mrVmWKbPPmDg13Pze520lvW6gvhc5wJA');
define('VAPID_SUBJECT', 'mailto:admin@turtledot.com');
