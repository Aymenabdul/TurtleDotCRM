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
            $isAdmin = (strtolower(trim($user['role'])) === 'admin');

            if ($teamId) {
                $stmt = $pdo->prepare("SELECT * FROM channels WHERE team_id = ? ORDER BY name ASC");
                $stmt->execute([$teamId]);
            } else {
                if ($isAdmin) {
                    // Admins see all channels if no team context
                    $stmt = $pdo->query("SELECT * FROM channels ORDER BY team_id ASC, name ASC");
                } else {
                    // Regular users see channels for their assigned team
                    $stmt = $pdo->prepare("SELECT * FROM channels WHERE team_id = ? ORDER BY name ASC");
                    $stmt->execute([$user['team_id']]);
                }
            }
            $channels = $stmt->fetchAll();

            // For each channel, get unread count
            foreach ($channels as &$ch) {
                $lrStmt = $pdo->prepare("SELECT last_read_message_id FROM channel_members_last_read WHERE user_id = ? AND channel_id = ?");
                $lrStmt->execute([$userId, $ch['id']]);
                $lastReadId = $lrStmt->fetchColumn() ?: 0;

                $cStmt = $pdo->prepare("SELECT COUNT(*) FROM chat_messages WHERE channel = ? AND id > ? AND user_id != ?");
                $cStmt->execute([$ch['name'], $lastReadId, $userId]);
                $ch['unread_count'] = (int) $cStmt->fetchColumn();
            }

            echo json_encode(['success' => true, 'data' => $channels]);
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

                $uStmt = $pdo->prepare("SELECT id, username, full_name, role, is_active, presence_status FROM users WHERE id = ?");
                $uStmt->execute([$otherId]);
                $otherUser = $uStmt->fetch();

                if ($otherUser) {
                    // Get last message for preview
                    $lStmt = $pdo->prepare("SELECT message, user_id, is_read FROM chat_messages WHERE channel = ? ORDER BY id DESC LIMIT 1");
                    $lStmt->execute([$chan]);
                    $lastMsg = $lStmt->fetch();

                    // Get unread count for the current user (messages sent by the other user that I haven't read)
                    $cStmt = $pdo->prepare("SELECT COUNT(*) FROM chat_messages WHERE channel = ? AND user_id = ? AND is_read = 0");
                    $cStmt->execute([$chan, $otherId]);
                    $unreadCount = $cStmt->fetchColumn();

                    $dms[] = [
                        'partner' => $otherUser,
                        'last_message' => $lastMsg['message'] ?? '',
                        'last_message_mine' => ($lastMsg['user_id'] ?? 0) == $userId,
                        'last_message_read' => ($lastMsg['is_read'] ?? 0) == 1,
                        'unread_count' => (int) $unreadCount
                    ];
                }
            }
            echo json_encode(['success' => true, 'dms' => $dms]);
            exit;
        }

        if ($action === 'channel_members') {
            $channelId = $_GET['channel_id'] ?? null;
            if (!$channelId || $channelId === 'null') {
                $stmt = $pdo->prepare("SELECT id, username, full_name, role, presence_status FROM users WHERE team_id = ? AND is_active = 1");
                $stmt->execute([$teamId]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT u.id, u.username, u.full_name, u.role, u.presence_status 
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
        $isDm = (strpos($channel, 'dm-') === 0);
        $isGlobal = ($channel === 'General');

        $sql = "
            SELECT m.*, u.username, u.full_name, u.presence_status 
            FROM chat_messages m
            JOIN users u ON m.user_id = u.id
            WHERE m.channel = ? AND m.id > ?
        ";
        $params = [$channel, $lastId];

        // Only filter by team_id for regular team channels (not DMs or Global General)
        if ($teamId && !$isDm && !$isGlobal) {
            $sql .= " AND m.team_id = ?";
            $params[] = $teamId;
        }

        $sql .= " ORDER BY m.id ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $messages = $stmt->fetchAll();

        // Check if messages were deleted/cleared (tail truncation)
        $stmtMax = $pdo->prepare("SELECT MAX(id) FROM chat_messages WHERE channel = ?");
        $stmtMax->execute([$channel]);
        $maxDbId = (int) ($stmtMax->fetchColumn() ?: 0);

        $forceReset = false;
        if ($lastId > 0 && $maxDbId < $lastId) {
            $forceReset = true;
        }

        echo json_encode([
            'success' => true,
            'data' => $messages,
            'force_reset' => $forceReset,
            'max_id' => $maxDbId
        ]);
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
                $rawRole = strtolower(trim($user['role'] ?? ''));
                if ($rawRole !== 'admin' && $rawRole !== 'administrator') {
                    echo json_encode(['success' => false, 'message' => 'Only admins can delete channels.']);
                    exit;
                }
                $stmt = $pdo->prepare("DELETE FROM channels WHERE id = ? AND team_id = ?");
                $stmt->execute([$data['channel_id'], $data['team_id']]);
                echo json_encode(['success' => true]);
                exit;
            }

            if ($action === 'clear_messages') {
                $channel = $data['channel'] ?? '';
                $rawRole = strtolower(trim($user['role'] ?? ''));
                $isAdmin = ($rawRole === 'admin' || $rawRole === 'administrator');

                // Enforce: Only admins can clear General or DMs.
                if ($channel === 'General') {
                    if (!$isAdmin) {
                        echo json_encode(['success' => false, 'message' => 'Only admins can clear the General channel.']);
                        exit;
                    }
                } else if (strpos($channel, 'dm-') === 0) {
                    // Everyone can clear their own DMs
                } else {
                    // Regular custom channels
                    if (!$isAdmin) {
                        echo json_encode(['success' => false, 'message' => 'Only admins can clear channels.']);
                        exit;
                    }
                }

                // Find and delete all files in this channel before clearing messages
                $fStmt = $pdo->prepare(strpos($channel, 'dm-') === 0
                    ? "SELECT message FROM chat_messages WHERE channel = ?"
                    : "SELECT message FROM chat_messages WHERE team_id = ? AND channel = ?");

                if (strpos($channel, 'dm-') === 0) {
                    $fStmt->execute([$channel]);
                } else {
                    $fStmt->execute([$data['team_id'], $channel]);
                }

                while ($msg = $fStmt->fetchColumn()) {
                    deleteFilesFromMessage($msg);
                }

                if ($channel === 'General' || strpos($channel, 'dm-') === 0) {
                    $stmt = $pdo->prepare("DELETE FROM chat_messages WHERE channel = ?");
                    $stmt->execute([$channel]);
                } else {
                    $stmt = $pdo->prepare("DELETE FROM chat_messages WHERE team_id = ? AND channel = ?");
                    $stmt->execute([$data['team_id'] ?? null, $channel]);
                }
                echo json_encode(['success' => true, 'deleted' => $stmt->rowCount()]);
                exit;
            }

            if ($action === 'update_status') {
                $status = $data['status'] ?? 'online';
                $stmt = $pdo->prepare("UPDATE users SET presence_status = ? WHERE id = ?");
                $stmt->execute([$status, $userId]);
                echo json_encode(['success' => true]);
                exit;
            }

            if ($action === 'mark_as_read') {
                $channel = $data['channel'] ?? '';
                if (strpos($channel, 'dm-') === 0) {
                    $stmt = $pdo->prepare("UPDATE chat_messages SET is_read = 1, read_at = NOW() WHERE channel = ? AND user_id != ? AND is_read = 0");
                    $stmt->execute([$channel, $userId]);
                } else {
                    $channelId = $data['channel_id'] ?? 0;
                    if (!$channelId && $channel) {
                        // Resolve ID by name if missing
                        $cStmt = $pdo->prepare("SELECT id FROM channels WHERE name = ? LIMIT 1");
                        $cStmt->execute([$channel]);
                        $channelId = $cStmt->fetchColumn();

                        // Auto-create General if it somehow doesn't exist
                        if (!$channelId && $channel === 'General') {
                            $pdo->prepare("INSERT IGNORE INTO channels (name, team_id) VALUES ('General', 0)")->execute();
                            $cStmt->execute([$channel]);
                            $channelId = $cStmt->fetchColumn();
                        }
                    }

                    if ($channelId) {
                        // Get max message id
                        $mStmt = $pdo->prepare("SELECT MAX(id) FROM chat_messages WHERE channel = ?");
                        $mStmt->execute([$channel]);
                        $maxId = $mStmt->fetchColumn() ?: 0;

                        if ($maxId) {
                            $stmt = $pdo->prepare("INSERT INTO channel_members_last_read (user_id, channel_id, last_read_message_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE last_read_message_id = ?");
                            $stmt->execute([$userId, (int) $channelId, $maxId, $maxId]);
                        }
                    }
                }
                echo json_encode(['success' => true]);
                exit;
            }

            if ($action === 'edit_message') {
                $stmt = $pdo->prepare("UPDATE chat_messages SET message = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$data['message'], $data['message_id'], $userId]);
                echo json_encode(['success' => true]);
                exit;
            }

            if ($action === 'delete_message') {
                $rawRole = strtolower(trim($user['role'] ?? ''));
                $isAdmin = ($rawRole === 'admin' || $rawRole === 'administrator');

                // Fetch message content first to find any files
                if ($isAdmin) {
                    $fStmt = $pdo->prepare("SELECT message FROM chat_messages WHERE id = ?");
                    $fStmt->execute([$data['message_id']]);
                } else {
                    $fStmt = $pdo->prepare("SELECT message FROM chat_messages WHERE id = ? AND user_id = ?");
                    $fStmt->execute([$data['message_id'], $userId]);
                }
                $msgContent = $fStmt->fetchColumn();

                if ($msgContent) {
                    deleteFilesFromMessage($msgContent);
                }

                if ($isAdmin) {
                    $stmt = $pdo->prepare("DELETE FROM chat_messages WHERE id = ?");
                    $stmt->execute([$data['message_id']]);
                } else {
                    $stmt = $pdo->prepare("DELETE FROM chat_messages WHERE id = ? AND user_id = ?");
                    $stmt->execute([$data['message_id'], $userId]);
                }
                echo json_encode(['success' => true]);
                exit;
            }

            if ($action === 'delete_recent_dm') {
                $channel = $data['channel'] ?? '';
                if (strpos($channel, 'dm-') === 0) {
                    $parts = explode('-', $channel);
                    if (count($parts) === 3) {
                        $p1 = $parts[1];
                        $p2 = $parts[2];

                        if ($p1 == $userId) {
                            $stmt1 = $pdo->prepare("UPDATE dm_threads SET deleted_by_user1 = 1 WHERE channel = ?");
                            $stmt1->execute([$channel]);
                        }
                        if ($p2 == $userId) {
                            $stmt2 = $pdo->prepare("UPDATE dm_threads SET deleted_by_user2 = 1 WHERE channel = ?");
                            $stmt2->execute([$channel]);
                        }
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
        if ($teamId === 'null' || $teamId === 'undefined')
            $teamId = null;

        $channel = $_POST['channel'] ?? 'General';
        $message = $_POST['message'] ?? '';

        $isDm = (strpos($channel, 'dm-') === 0);
        $isGlobal = ($channel === 'General');

        if (!$teamId && !$isDm && !$isGlobal) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Team ID required for regular channels']);
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

                        $uploadDir = __DIR__ . '/../uploads/chat/';
                        if (!file_exists($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }

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

                    $uploadDir = __DIR__ . '/../uploads/chat/';
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }

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

        $isDm = (strpos($channel, 'dm-') === 0);
        $isGlobal = ($channel === 'General');
        $storeTeamId = ($isDm || $isGlobal) ? null : $teamId;

        $stmt = $pdo->prepare("INSERT INTO chat_messages (team_id, channel, user_id, message) VALUES (?, ?, ?, ?)");
        $stmt->execute([$storeTeamId, $channel, $userId, $message]);
        $newMsgId = $pdo->lastInsertId();

        // 🔔 Background Notification Trigger
        try {
            require_once __DIR__ . '/../lib/NotificationService.php';
            $senderName = $user['full_name'] ?: ($user['username'] ?: 'Someone');

            if ($isDm) {
                // Direct Message push
                $parts = explode('-', $channel);
                $targetUserId = ($parts[1] == $userId) ? $parts[2] : $parts[1];
                $tag = "chat-dm-" . min($userId, $targetUserId) . "-" . max($userId, $targetUserId);

                // Get real unread count for the target user in this DM
                $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM chat_messages WHERE channel = ? AND user_id != ? AND is_read = 0");
                $cntStmt->execute([$channel, $targetUserId]);
                $unreadCount = (int) $cntStmt->fetchColumn();

                NotificationService::sendPushToUser($targetUserId, "New Message from $senderName", $message, "/tools/chat.php?team_id=$teamId", $tag, [
                    'channel' => $channel,
                    'unread_count' => $unreadCount,
                    'type' => 'chat'
                ]);
            } else if ($isGlobal) {
                // System-wide Global channel push
                $tag = "chat-chan-General";
                $stmtM = $pdo->prepare("SELECT id as user_id FROM users WHERE id != ? AND is_active = 1");
                $stmtM->execute([$userId]);
                while ($row = $stmtM->fetch()) {
                    $uId = $row['user_id'];
                    // Get unread count for General (checking the last_read record)
                    $lrStmt = $pdo->prepare("SELECT last_read_message_id FROM channel_members_last_read WHERE user_id = ? AND channel_id = (SELECT id FROM channels WHERE name='General' LIMIT 1)");
                    $lrStmt->execute([$uId]);
                    $lastReadId = $lrStmt->fetchColumn() ?: 0;

                    $cStmt = $pdo->prepare("SELECT COUNT(*) FROM chat_messages WHERE channel = 'General' AND id > ? AND user_id != ?");
                    $cStmt->execute([$lastReadId, $uId]);
                    $unreadCount = (int) $cStmt->fetchColumn();

                    NotificationService::sendPushToUser($uId, "$senderName (Global)", $message, "/tools/chat.php?team_id=$teamId", $tag, [
                        'channel' => 'General',
                        'unread_count' => $unreadCount,
                        'type' => 'chat'
                    ]);
                }
            } else {
                // Regular Channel push
                $tag = "chat-chan-$channel";
                // Get channel_id
                $stmtC = $pdo->prepare("SELECT id FROM channels WHERE name = ? AND team_id = ?");
                $stmtC->execute([$channel, $teamId]);
                $chanInfo = $stmtC->fetch();
                $chanId = $chanInfo ? $chanInfo['id'] : 0;

                $stmtM = $pdo->prepare("
                    SELECT user_id FROM channel_members cm 
                    JOIN channels c ON cm.channel_id = c.id 
                    WHERE c.name = ? AND c.team_id = ? AND cm.user_id != ?
                ");
                $stmtM->execute([$channel, $teamId, $userId]);
                while ($row = $stmtM->fetch()) {
                    $uId = $row['user_id'];
                    // Get unread count for THIS channel for THIS user
                    $lrStmt = $pdo->prepare("SELECT last_read_message_id FROM channel_members_last_read WHERE user_id = ? AND channel_id = ?");
                    $lrStmt->execute([$uId, $chanId]);
                    $lastReadId = $lrStmt->fetchColumn() ?: 0;

                    $cStmt = $pdo->prepare("SELECT COUNT(*) FROM chat_messages WHERE channel = ? AND id > ? AND user_id != ?");
                    $cStmt->execute([$channel, $lastReadId, $uId]);
                    $unreadCount = (int) $cStmt->fetchColumn();

                    NotificationService::sendPushToUser($uId, "#$channel: $senderName", $message, "/tools/chat.php?team_id=$teamId", $tag, [
                        'channel' => $channel,
                        'channel_id' => $chanId,
                        'unread_count' => $unreadCount,
                        'type' => 'chat'
                    ]);
                }
            }
        } catch (Exception $pushNoteErr) {
            // Log push errors for debugging
            error_log("Chat Push Notification Error: " . $pushNoteErr->getMessage() . " in " . $pushNoteErr->getFile() . " on line " . $pushNoteErr->getLine());
        }

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

        echo json_encode(['success' => true, 'id' => (int) $newMsgId]);
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Helper to delete physical files from a message string
 */
function deleteFilesFromMessage($message)
{
    if (empty($message))
        return;

    // Find all [attachment:/path/to/file] tags
    preg_match_all('/\[attachment:(.*?)\]/', $message, $matches);

    if (!empty($matches[1])) {
        foreach ($matches[1] as $relativeVisiblePath) {
            // Convert /uploads/chat/... to physical absolute path
            // The path in message starts with / (e.g. /uploads/chat/...)
            $physicalPath = __DIR__ . '/..' . $relativeVisiblePath;

            if (file_exists($physicalPath)) {
                @unlink($physicalPath);
            }
        }
    }
}
