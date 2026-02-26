<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth_middleware.php';

$user = AuthMiddleware::requireAuthAPI();

try {
    // Select only necessary fields for security
    $stmt = $pdo->query("SELECT id, username, email, full_name, role, team_id, is_active, presence_status FROM users ORDER BY full_name ASC, username ASC");
    $users = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'users' => $users
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
