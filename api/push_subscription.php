<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth_middleware.php';

$user = AuthMiddleware::requireAuthAPI();
$userId = $user['user_id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if ($data && (isset($data['subscription']) || isset($data['endpoint']))) {
        // Some libraries send direct sub without wrapping in 'subscription'
        $subData = isset($data['subscription']) ? $data['subscription'] : $data;
        $subscription = json_encode($subData);
        $endpoint = $subData['endpoint'] ?? '';

        if (!$endpoint) {
            echo json_encode(['success' => false, 'message' => 'No endpoint provided']);
            exit;
        }

        // Check if it exists for this user
        $stmt = $pdo->prepare("SELECT id FROM user_push_subscriptions WHERE user_id = ? AND subscription_json LIKE ?");
        $stmt->execute([$userId, "%" . $endpoint . "%"]);

        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO user_push_subscriptions (user_id, subscription_json) VALUES (?, ?)");
            $stmt->execute([$userId, $subscription]);
        }
        echo json_encode(['success' => true]);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
