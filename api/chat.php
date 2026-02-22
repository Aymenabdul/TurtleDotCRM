<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth_middleware.php';

$user = AuthMiddleware::requireAuthAPI();
$userId = $user['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

// Increase upload limits for large files
ini_set('upload_max_filesize', '1024M');
ini_set('post_max_size', '1024M');
ini_set('memory_limit', '1024M');
ini_set('max_execution_time', '300');
ini_set('max_input_time', '300');

try {
    if ($method === 'GET') {
        $action = $_GET['action'] ?? null;
        $teamId = $_GET['team_id'] ?? null;

        if ($action === 'channels') {
            $stmt = $pdo->prepare("SELECT * FROM channels WHERE team_id = ? ORDER BY name ASC");
            $stmt->execute([$teamId]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            exit;
        }

        if ($action === 'recent_dms') {
            // Find DM threads involving this user that aren't marked as deleted by them
            $stmt = $pdo->prepare("
                SELECT * FROM dm_threads 
                WHERE (user1_id = ? AND deleted_by_user1 = 0)
                OR (user2_id = ? AND deleted_by_user2 = 0)
                ORDER BY updated_at DESC
            ");
            $stmt->execute([$userId, $userId]);
            $threads = $stmt->fetchAll();

            $dms = [];
            foreach ($threads as $thread) {
                $chan = $thread['channel'];
                $otherId = ($thread['user1_id'] == $userId) ? $thread['user2_id'] : $thread['user1_id'];

                $uStmt = $pdo->prepare("SELECT id, username, full_name, role, is_active FROM users WHERE id = ?");
                $uStmt->execute([$otherId]);
                $otherUser = $uStmt->fetch();

                if ($otherUser) {
                    // Get last message for preview
                    $lStmt = $pdo->prepare("SELECT message, user_id FROM chat_messages WHERE channel = ? ORDER BY id DESC LIMIT 1");
                    $lStmt->execute([$chan]);
                    $lastMsg = $lStmt->fetch();

                    $dms[] = [
                        'partner' => $otherUser,
                        'last_message' => $lastMsg['message'] ?? '',
                        'last_message_mine' => ($lastMsg['user_id'] ?? 0) == $userId
                    ];
                }
            }
            echo json_encode(['success' => true, 'dms' => $dms]);
            exit;
        }

        if ($action === 'channel_members') {
            $channelId = $_GET['channel_id'] ?? null;
            if (!$channelId || $channelId === 'null') {
                $stmt = $pdo->prepare("SELECT id, username, full_name, role FROM users WHERE team_id = ? AND is_active = 1");
                $stmt->execute([$teamId]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT u.id, u.username, u.full_name, u.role 
                    FROM users u
                    JOIN channel_members cm ON u.id = cm.user_id
                    WHERE cm.channel_id = ? AND u.is_active = 1
                ");
                $stmt->execute([$channelId]);
            }
            echo json_encode(['success' => true, 'members' => $stmt->fetchAll()]);
            exit;
        }

        // Default: Get messages
        $lastId = $_GET['last_id'] ?? 0;
        $channel = $_GET['channel'] ?? 'General';
        $teamId = $_GET['team_id'] ?? null;

        $sql = "
            SELECT m.*, u.username, u.full_name 
            FROM chat_messages m
            JOIN users u ON m.user_id = u.id
            WHERE m.channel = ? AND m.id > ?
        ";
        $params = [$channel, $lastId];

        if ($teamId) {
            $sql .= " AND m.team_id = ?";
            $params[] = $teamId;
        }

        $sql .= " ORDER BY m.id ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        exit;

    } elseif ($method === 'POST') {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);

        if ($data && isset($data['action'])) {
            $action = $data['action'];

            if ($action === 'create_channel') {
                $check = $pdo->prepare("SELECT id FROM channels WHERE team_id = ? AND name = ?");
                $check->execute([$data['team_id'], $data['name']]);
                if ($check->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'A channel with this name already exists.']);
                    exit;
                }

                $stmt = $pdo->prepare("INSERT INTO channels (team_id, name) VALUES (?, ?)");
                $stmt->execute([$data['team_id'], $data['name']]);
                $channelId = $pdo->lastInsertId();

                if (isset($data['member_ids']) && is_array($data['member_ids'])) {
                    $mStmt = $pdo->prepare("INSERT IGNORE INTO channel_members (channel_id, user_id) VALUES (?, ?)");
                    foreach ($data['member_ids'] as $mId) {
                        $mStmt->execute([$channelId, $mId]);
                    }
                }
                echo json_encode(['success' => true, 'id' => $channelId]);
                exit;
            }

            if ($action === 'delete_channel') {
                $stmt = $pdo->prepare("DELETE FROM channels WHERE id = ? AND team_id = ?");
                $stmt->execute([$data['channel_id'], $data['team_id']]);
                echo json_encode(['success' => true]);
                exit;
            }

            if ($action === 'clear_messages') {
                $channel = $data['channel'] ?? '';
                // Security: If DM, ensure current user is part of it
                if (strpos($channel, 'dm-') === 0) {
                    $parts = explode('-', $channel);
                    if (count($parts) === 3) {
                        if ($parts[1] != $userId && $parts[2] != $userId) {
                            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                            exit;
                        }
                    }
                }

                if (strpos($channel, 'dm-') === 0) {
                    $stmt = $pdo->prepare("DELETE FROM chat_messages WHERE channel = ?");
                    $stmt->execute([$channel]);
                } else {
                    $stmt = $pdo->prepare("DELETE FROM chat_messages WHERE team_id = ? AND channel = ?");
                    $stmt->execute([$data['team_id'], $channel]);
                }
                echo json_encode(['success' => true, 'deleted' => $stmt->rowCount()]);
                exit;
            }

            if ($action === 'edit_message') {
                $stmt = $pdo->prepare("UPDATE chat_messages SET message = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$data['message'], $data['message_id'], $userId]);
                echo json_encode(['success' => true]);
                exit;
            }

            if ($action === 'delete_message') {
                $stmt = $pdo->prepare("DELETE FROM chat_messages WHERE id = ? AND user_id = ?");
                $stmt->execute([$data['message_id'], $userId]);
                echo json_encode(['success' => true]);
                exit;
            }

            if ($action === 'delete_recent_dm') {
                $channel = $data['channel'] ?? '';
                if (strpos($channel, 'dm-') === 0) {
                    $parts = explode('-', $channel);
                    if (count($parts) === 3) {
                        if ($parts[1] == $userId) {
                            $stmt = $pdo->prepare("UPDATE dm_threads SET deleted_by_user1 = 1 WHERE channel = ?");
                        } else if ($parts[2] == $userId) {
                            $stmt = $pdo->prepare("UPDATE dm_threads SET deleted_by_user2 = 1 WHERE channel = ?");
                        }
                        if (isset($stmt))
                            $stmt->execute([$channel]);
                    }
                }
                echo json_encode(['success' => true]);
                exit;
            }

            if ($action === 'add_member') {
                $stmt = $pdo->prepare("INSERT IGNORE INTO channel_members (channel_id, user_id) VALUES (?, ?)");
                $stmt->execute([$data['channel_id'], $data['user_id']]);
                echo json_encode(['success' => true]);
                exit;
            }

            if ($action === 'remove_member') {
                $stmt = $pdo->prepare("DELETE FROM channel_members WHERE channel_id = ? AND user_id = ?");
                $stmt->execute([$data['channel_id'], $data['user_id']]);
                echo json_encode(['success' => true]);
                exit;
            }
        }

        // Handle File Upload & Send Message (Multipart)
        $teamId = $_POST['team_id'] ?? null;
        $channel = $_POST['channel'] ?? 'General';
        $message = $_POST['message'] ?? '';

        if (!$teamId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Team ID required']);
            exit;
        }

        $attachments = [];
        if (isset($_FILES['attachment'])) {
            $files = $_FILES['attachment'];
            if (is_array($files['name'])) {
                for ($i = 0; $i < count($files['name']); $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        $origName = basename($files['name'][$i]);
                        $cleanName = time() . '_' . preg_replace("/[^a-zA-Z0-9._-]/", "_", $origName);
                        $path = 'uploads/chat/' . $cleanName;
                        if (move_uploaded_file($files['tmp_name'][$i], __DIR__ . '/../' . $path)) {
                            $attachments[] = "[attachment:/$path]";
                        }
                    }
                }
            } else {
                if ($files['error'] === UPLOAD_ERR_OK) {
                    $origName = basename($files['name']);
                    $cleanName = time() . '_' . preg_replace("/[^a-zA-Z0-0._-]/", "_", $origName);
                    $path = 'uploads/chat/' . $cleanName;
                    if (move_uploaded_file($files['tmp_name'], __DIR__ . '/../' . $path)) {
                        $attachments[] = "[attachment:/$path]";
                    }
                }
            }
        }

        if (!empty($attachments)) {
            $message .= ($message ? " " : "") . implode(" ", $attachments);
        }

        $message = trim($message);
        if ($message === '') {
            echo json_encode(['success' => false, 'message' => 'Empty message']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO chat_messages (team_id, channel, user_id, message) VALUES (?, ?, ?, ?)");
        $stmt->execute([$teamId, $channel, $userId, $message]);

        // If DM, ensure thread is tracked and not marked as deleted
        if (strpos($channel, 'dm-') === 0) {
            $parts = explode('-', $channel);
            if (count($parts) === 3) {
                $u1 = $parts[1];
                $u2 = $parts[2];
                $stmtThread = $pdo->prepare("
                    INSERT INTO dm_threads (channel, user1_id, user2_id, deleted_by_user1, deleted_by_user2) 
                    VALUES (?, ?, ?, 0, 0)
                    ON DUPLICATE KEY UPDATE deleted_by_user1 = 0, deleted_by_user2 = 0, updated_at = NOW()
                ");
                $stmtThread->execute([$channel, $u1, $u2]);
            }
        }

        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
