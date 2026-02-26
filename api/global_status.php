<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth_middleware.php';

$user = AuthMiddleware::requireAuthAPI();
$userId = $user['user_id'];
$teamId = $user['team_id'];

$response = [
    'success' => true,
    'unread_dms' => [],
    'unread_channels' => [],
    'upcoming_meetings' => 0
];

try {
    // 1. Get Unread DMs
    $stmt = $pdo->prepare("
        SELECT u.id as user_id, u.full_name, u.username,
               (SELECT COUNT(*) FROM chat_messages m 
                WHERE m.channel = CASE 
                    WHEN ? < u.id THEN CONCAT('dm-', ?, '-', u.id)
                    ELSE CONCAT('dm-', u.id, '-', ?)
                END 
                AND m.user_id = u.id 
                AND m.is_read = 0
               ) as unread_count
        FROM users u
        WHERE u.id != ? AND u.team_id = ?
        HAVING unread_count > 0
    ");
    $stmt->execute([$userId, $userId, $userId, $userId, $teamId]);
    $response['unread_dms'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Get Unread Channels (including Global #General)
    // We fetch #General for everyone, and other channels based on membership
    $stmt = $pdo->prepare("
        (SELECT 'General' as name,
               (SELECT COUNT(*) FROM chat_messages m 
                WHERE m.channel = 'General' 
                AND m.id > IFNULL((SELECT last_read_message_id FROM channel_members_last_read WHERE user_id = ? AND channel_id = (SELECT id FROM channels WHERE name='General' LIMIT 1)), 0)
               ) as unread_count)
        UNION
        (SELECT c.name as name,
               (SELECT COUNT(*) FROM chat_messages m 
                WHERE m.channel = c.name 
                AND m.id > IFNULL((SELECT last_read_message_id FROM channel_members_last_read WHERE user_id = ? AND channel_id = c.id), 0)
               ) as unread_count
        FROM channels c
        JOIN channel_members cm ON c.id = cm.channel_id
        WHERE cm.user_id = ? AND c.name != 'General')
    ");
    $stmt->execute([$userId, $userId, $userId]);

    // Process results to only include those with unread_count > 0
    $channelsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($channelsRaw as $c) {
        if ($c['unread_count'] > 0) {
            $response['unread_channels'][] = $c;
        }
    }

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;
