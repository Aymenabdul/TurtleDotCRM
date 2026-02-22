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

} catch (PDOException $e) {
    // If database doesn't exist, try to create it
    if ($e->getCode() == 1049) {
        try {
            $pdo = new PDO("mysql:host=$db_host;charset=$db_charset", $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$db_name`");

            // Initialize schema
            $sql = file_get_contents(__DIR__ . '/database/schema.sql');
            $pdo->exec($sql);

        } catch (PDOException $e2) {
            die("Connection failed: " . $e2->getMessage());
        }
    } else {
        die("Connection failed: " . $e->getMessage());
    }
}
?>