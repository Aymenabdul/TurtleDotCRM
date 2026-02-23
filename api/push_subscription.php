<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth_middleware.php';

$user = AuthMiddleware::requireAuthAPI();
$userId = $user['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_key'])) {
    echo json_encode(['success' => true, 'publicKey' => VAPID_PUBLIC_KEY]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($data['subscription']) || isset($data['endpoint']))) {
    // Some libraries send direct sub without wrapping in 'subscription'
    $subData = isset($data['subscription']) ? $data['subscription'] : $data;
    $subscription = json_encode($subData);

    // Use user_id as identifying factor. We could store multiple per user (one per device).
    // For now, let's store one subscription per user per device unique endpoint.
    $endpoint = $data['subscription']['endpoint'] ?? '';

    // Check if it exists
    $stmt = $pdo->prepare("SELECT id FROM user_push_subscriptions WHERE user_id = ? AND subscription_json LIKE ?");
    $stmt->execute([$userId, "%$endpoint%"]);

    if (!$stmt->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO user_push_subscriptions (user_id, subscription_json) VALUES (?, ?)");
        $stmt->execute([$userId, $subscription]);
    }

    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
