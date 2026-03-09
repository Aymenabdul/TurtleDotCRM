<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth_middleware.php';

$user = AuthMiddleware::requireAuthAPI();
$userId = $user['user_id'];

$data = json_decode(file_get_contents('php://input'), true);
$token = $data['token'] ?? null;

if (!$token) {
    echo json_encode(['success' => false, 'message' => 'No token provided']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE users SET fcm_token = ? WHERE id = ?");
    $stmt->execute([$token, $userId]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
