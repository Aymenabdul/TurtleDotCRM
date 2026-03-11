<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
ob_start();
ini_set('display_errors', 0);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth_middleware.php';

$user = AuthMiddleware::requireAuthAPI();
$method = $_SERVER['REQUEST_METHOD'];

try {
    // Shared helper for write-permission check (POST/PUT/DELETE)
    $canWrite = function($fid, $pdo, $uid) use (&$canWrite, $user) {
        $role = strtolower($user['role'] ?? '');
        if ($role === 'admin' || strpos($role, 'admin') !== false) return true;
        if (!$fid) return true; 

        $stmt = $pdo->prepare("SELECT created_by, assigned_to, parent_id FROM team_folders WHERE id = ?");
        $stmt->execute([$fid]);
        $item = $stmt->fetch();
        if (!$item) return false;

        if ($item['created_by'] == $uid) return true;
        $assigned = json_decode($item['assigned_to'] ?? '{}', true);
        if (isset($assigned[$uid]) && ($assigned[$uid] === 'edit' || $assigned[$uid] === 'owner')) return true;

        // Check parent inheritance
        if (!empty($item['parent_id'])) {
            $checkRecursive = function($pid, $pdo, $uid) use (&$checkRecursive) {
                $s = $pdo->prepare("SELECT created_by, assigned_to, parent_id FROM team_folders WHERE id = ?");
                $s->execute([$pid]);
                $p = $s->fetch();
                if (!$p) return false;
                if ($p['created_by'] == $uid) return true;
                $ass = json_decode($p['assigned_to'] ?? '{}', true);
                if (isset($ass[$uid]) && ($ass[$uid] === 'edit' || $ass[$uid] === 'owner')) return true;
                return (!empty($p['parent_id'])) ? $checkRecursive($p['parent_id'], $pdo, $uid) : false;
            };
            return $checkRecursive($item['parent_id'], $pdo, $uid);
        }
        return false;
    };

    $isAdmin = isset($user['role']) && (strtolower($user['role']) === 'admin' || strpos(strtolower($user['role']), 'admin') !== false);
    $userId = $user['user_id'];

    if ($method === 'GET') {

        // Handle raw file reading for previews
        if (isset($_GET['read']) && isset($_GET['id'])) {
            $id = $_GET['id'];
            $stmt = $pdo->prepare("SELECT * FROM team_files WHERE id = ?");
            $stmt->execute([$id]);
            $file = $stmt->fetch();

            if (!$file) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'File not found']);
                exit;
            }

            // Simple permission check
            if (!$isAdmin && $file['team_id'] != $user['team_id']) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                exit;
            }

            $fullPath = __DIR__ . '/../' . $file['file_path'];
            if (file_exists($fullPath)) {
                header('Content-Type: text/plain');
                echo file_get_contents($fullPath);
                exit;
            } else {
                http_response_code(404);
                echo "File disk error";
                exit;
            }
        }

        // Handle file listing
        $teamId = $_GET['team_id'] ?? null;
        if (!$teamId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Team ID required']);
            exit;
        }

        // Environment Isolation Check
        if (!$isAdmin && $teamId != $user['team_id']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Security Violation: Access to external environment denied.']);
            exit;
        }

        if ($isAdmin) {
            $stmt = $pdo->prepare("SELECT f.*, tf.name as folder_name, u.full_name as uploader_name FROM team_files f LEFT JOIN team_folders tf ON f.folder_id = tf.id LEFT JOIN users u ON f.uploaded_by = u.id WHERE f.team_id = ? ORDER BY f.created_at DESC");
            $stmt->execute([$teamId]);
        } else {
            $stmt = $pdo->prepare("SELECT f.*, tf.parent_id as folder_parent_id, tf.created_by as folder_owner_id, tf.assigned_to as folder_assigned_to, u.full_name as uploader_name FROM team_files f LEFT JOIN team_folders tf ON f.folder_id = tf.id LEFT JOIN users u ON f.uploaded_by = u.id WHERE f.team_id = ? ORDER BY f.created_at DESC");
            $stmt->execute([$teamId]);
        }
        $allFiles = $stmt->fetchAll();

        if ($isAdmin) {
            $filtered = $allFiles;
            foreach ($filtered as &$f) $f['effective_role'] = 'admin';
        } else {
            // Need folders for recursive parent-access check
            $stmt = $pdo->prepare("SELECT id, parent_id, assigned_to, created_by FROM team_folders WHERE team_id = ?");
            $stmt->execute([$teamId]);
            $folders = $stmt->fetchAll();

            $getFolderRole = function($folderId, $folders, $uid) use (&$getFolderRole) {
                if (!$folderId) return null;
                $f = null;
                foreach($folders as $item) if($item['id'] == $folderId) { $f = $item; break; }
                if (!$f) return null;
                if ($f['created_by'] == $uid) return 'owner';
                $assigned = json_decode($f['assigned_to'] ?? '{}', true);
                if (isset($assigned[$uid])) return $assigned[$uid];
                return $getFolderRole($f['parent_id'], $folders, $uid);
            };

            $getFileRole = function($file, $folders, $uid) use ($getFolderRole) {
                if ($file['uploaded_by'] == $uid) return 'owner';
                $assigned = json_decode($file['assigned_to'] ?? '{}', true);
                if (isset($assigned[$uid])) return $assigned[$uid];

                // Check inherited from parent folders
                return $getFolderRole($file['folder_id'], $folders, $uid);
            };

            $filtered = [];
            foreach ($allFiles as $f) {
                $role = $getFileRole($f, $folders, $userId);
                if ($role) {
                    $f['effective_role'] = $role;
                    $filtered[] = $f;
                }
            }
        }

        echo json_encode(['success' => true, 'data' => array_values($filtered)]);

    } elseif ($method === 'POST') {
        if (!isset($_FILES['file']) || !isset($_POST['team_id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing file or team ID']);
            exit;
        }

        $teamId = $_POST['team_id'];
        $folderId = $_POST['folder_id'] ?? null;
        $file = $_FILES['file'];

        // Permission check for upload
        if ($folderId && !$canWrite($folderId, $pdo, $user['user_id'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No permission to upload content to this collection']);
            exit;
        }

        // Build physical path recursively (Admin isolation + User Unique ID)
        $getPhysicalPath = function($id, $pdo, $tid) use (&$getPhysicalPath, $user) {
            $getCreatorIdentifier = function($uid, $pdo) {
                $s = $pdo->prepare("SELECT role, unique_id FROM users WHERE id = ?");
                $s->execute([$uid]);
                $u = $s->fetch();
                if (!$u) return "Unknown";
                $r = strtolower($u['role'] ?? '');
                if ($r === 'admin' || strpos($r, 'admin') !== false) return "Admin";
                return $u['unique_id'];
            };

            if (!$id) {
                $role = strtolower($user['role'] ?? '');
                $root = ($role === 'admin' || strpos($role, 'admin') !== false) ? "Admin" : ($user['unique_id'] ?? $tid);
                return "uploads/files/{$root}/";
            }

            $stmt = $pdo->prepare("SELECT name, parent_id, created_by FROM team_folders WHERE id = ?");
            $stmt->execute([$id]);
            $f = $stmt->fetch();
            if (!$f) {
                $role = strtolower($user['role'] ?? '');
                $root = ($role === 'admin' || strpos($role, 'admin') !== false) ? "Admin" : ($user['unique_id'] ?? $tid);
                return "uploads/files/{$root}/";
            }

            $safe = str_replace(' ', '_', preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $f['name']));

            if (!$f['parent_id']) {
                $root = $getCreatorIdentifier($f['created_by'], $pdo);
                return "uploads/files/{$root}/" . $safe . '/';
            }

            return $getPhysicalPath($f['parent_id'], $pdo, $tid) . $safe . '/';
        };

        $relativePath = $getPhysicalPath($folderId, $pdo, $teamId);
        $uploadDir = __DIR__ . '/../' . $relativePath;
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = basename($file['name']);
        $filePath = $relativePath . time() . '_' . $fileName;
        $fullPath = __DIR__ . '/../' . $filePath;

        if (move_uploaded_file($file['tmp_name'], $fullPath)) {
            $stmt = $pdo->prepare("INSERT INTO team_files (team_id, folder_id, file_name, file_path, file_type, file_size, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $teamId,
                $folderId,
                $fileName,
                $filePath,
                $file['type'],
                $file['size'],
                $user['user_id']
            ]);

            $fileId = $pdo->lastInsertId();
            if ($teamId) triggerVaultSync($teamId, $pdo, $user['user_id']);
            finished_response(json_encode(['success' => true, 'id' => $fileId]));
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Upload failed']);
            exit;
        }

    } elseif ($method === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true);
        $fileId = $data['id'] ?? null;
        
        if (!$fileId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'File ID required']);
            exit;
        }

        // Check if we are updating content or permissions
        if (isset($data['content'])) {
            // Content Update
            $stmt = $pdo->prepare("SELECT file_path, folder_id, uploaded_by FROM team_files WHERE id = ?");
            $stmt->execute([$fileId]);
            $file = $stmt->fetch();
            
            if (!$file) {
                echo json_encode(['success' => false, 'message' => 'File not found']);
                exit;
            }

            // Permission check: uploader or folder-level edit access
            if ($file['uploaded_by'] != $user['user_id'] && !$canWrite($file['folder_id'], $pdo, $user['user_id'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'No permission to modify this asset']);
                exit;
            }

            $fullPath = __DIR__ . '/../' . $file['file_path'];
            if (file_put_contents($fullPath, $data['content']) !== false) {
                // Update size in DB
                $newSize = filesize($fullPath);
                $stmt = $pdo->prepare("UPDATE team_files SET file_size = ? WHERE id = ?");
                $stmt->execute([$newSize, $fileId]);

                // Get team_id for sync
                $tStmt = $pdo->prepare("SELECT team_id FROM team_files WHERE id = ?");
                $tStmt->execute([$fileId]);
                $teamId = $tStmt->fetchColumn();

                if ($teamId) triggerVaultSync($teamId, $pdo, $user['user_id']);
                finished_response(json_encode(['success' => true, 'message' => 'File content updated']));
                exit;
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to write content']);
                exit;
            }
        } else {
            // Permission Update
            $users = $data['assigned_to'] ?? [];
            $stmt = $pdo->prepare("UPDATE team_files SET assigned_to = ?, assigned_by = ? WHERE id = ?");
            $stmt->execute([json_encode($users), $user['user_id'], $fileId]);

            // Get team_id for sync
            $tStmt = $pdo->prepare("SELECT team_id FROM team_files WHERE id = ?");
            $tStmt->execute([$fileId]);
            $teamId = $tStmt->fetchColumn();

            if ($teamId) triggerVaultSync($teamId, $pdo, $user['user_id']);
            finished_response(json_encode(['success' => true]));
            exit;
        }

    } elseif ($method === 'DELETE') {
        $data = json_decode(file_get_contents('php://input'), true);
        $ids = $data['ids'] ?? (!empty($data['id']) ? [$data['id']] : []);

        if (empty($ids)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'IDs required']);
            exit;
        }

        // For sync, we need a team ID. Let's get it from the first ID before we delete.
        $teamId = null;
        if (!empty($ids)) {
            $tStmt = $pdo->prepare("SELECT team_id FROM team_files WHERE id = ?");
            $tStmt->execute([$ids[0]]);
            $teamId = $tStmt->fetchColumn();
        }

        // Batch Permission Check (Consistent with folders.php)
        foreach ($ids as $checkId) {
            $stmt = $pdo->prepare("SELECT folder_id, uploaded_by, team_id FROM team_files WHERE id = ?");
            $stmt->execute([$checkId]);
            $file = $stmt->fetch();
            if ($file) {
                // Security: Prevent cross-team mass wipe
                if (!$isAdmin && $file['team_id'] != $user['team_id']) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Security Violation: Cannot wipe assets belonging to another environment.']);
                    exit;
                }

                // Authority: Check uploader or shared folder edit rights
                if ($file['uploaded_by'] != $user['user_id'] && !$canWrite($file['folder_id'], $pdo, $user['user_id'])) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Hierarchy Restriction: One or more selected assets are protected.']);
                    exit;
                }
            }
        }

        foreach ($ids as $id) {
            $stmt = $pdo->prepare("SELECT file_path FROM team_files WHERE id = ?");
            $stmt->execute([$id]);
            $file = $stmt->fetch();

            if ($file) {
                $fullPath = __DIR__ . '/../' . $file['file_path'];
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }

                $stmt = $pdo->prepare("DELETE FROM team_files WHERE id = ?");
                $stmt->execute([$id]);
            }
        }
        
        if ($teamId) triggerVaultSync($teamId, $pdo, $user['user_id']);
        finished_response(json_encode(['success' => true]));
        exit;
    }

} catch (Exception $e) {
    if (ob_get_level() > 0) ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Vault Error: ' . $e->getMessage()]);
}

/**
 * Sends a background sync trigger to all team members via FCM
 */
function triggerVaultSync($teamId, $pdo, $currentUserId) {
    try {
        require_once __DIR__ . '/../lib/NotificationService.php';
        
        // Find all active team members (exclude ourselves)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE team_id = ? AND id != ? AND is_active = 1");
        $stmt->execute([$teamId, $currentUserId]);
        $members = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($members)) return;

        foreach ($members as $memberId) {
            NotificationService::sendPushToUser($memberId, "vault_sync", "vault_sync", null, "vault-sync", [
                'type' => 'vault_sync',
                'team_id' => $teamId
            ]);
        }
        NotificationService::flushPushQueue();
    } catch (Exception $e) {
        error_log("Vault Sync Error: " . $e->getMessage());
    }
}

/**
 * Flush response to client and continue execution in background
 */
function finished_response($response) {
    if (ob_get_level() > 0) ob_end_clean();
    ignore_user_abort(true);
    ob_start();
    echo $response;
    $size = ob_get_length();
    header("Content-Length: $size");
    header("Connection: close");
    header("Content-Type: application/json");
    ob_end_flush();
    flush();
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
}
