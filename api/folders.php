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
    // Shared helper for physical path resolution (Admin isolation)
    // Shared helper for physical path resolution (Admin isolation + User Unique ID)
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
    
    // Shared helper for write-permission check (POST/PUT/DELETE)
    $canWrite = function($fid, $pdo, $uid) use (&$canWrite, $user) {
        $role = strtolower($user['role'] ?? '');
        if ($role === 'admin' || strpos($role, 'admin') !== false) return true;
        if (!$fid) return true; // Root is open for now

        $stmt = $pdo->prepare("SELECT created_by, assigned_to, parent_id FROM team_folders WHERE id = ?");
        $stmt->execute([$fid]);
        $item = $stmt->fetch();
        if (!$item) return false;

        if ($item['created_by'] == $uid) return true;
        $assigned = json_decode($item['assigned_to'] ?? '{}', true);
        if (isset($assigned[$uid]) && ($assigned[$uid] === 'edit' || $assigned[$uid] === 'owner')) return true;

        // Check parent inheritance for edit rights
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
            $stmt = $pdo->prepare("SELECT tf.*, u.full_name as creator_name FROM team_folders tf LEFT JOIN users u ON tf.created_by = u.id WHERE tf.team_id = ? ORDER BY tf.name ASC");
            $stmt->execute([$teamId]);
        } else {
            // Fetch all folders for the team so inheritance can be resolved in PHP
            $stmt = $pdo->prepare("
                SELECT tf.*, u.full_name as creator_name FROM team_folders tf
                LEFT JOIN users u ON tf.created_by = u.id
                WHERE tf.team_id = ?
            ");
            $stmt->execute([$teamId]);
        }
        $allFolders = $stmt->fetchAll();

        if ($isAdmin) {
            $filtered = $allFolders;
            foreach ($filtered as &$f) $f['effective_role'] = 'admin';
        } else {
            // Recursive check in PHP for inherited access and role
            $getEffectiveRole = function($folder, $all, $uid) use (&$getEffectiveRole) {
                if ($folder['created_by'] == $uid) return 'owner';
                $assigned = json_decode($folder['assigned_to'] ?? '{}', true);
                if (isset($assigned[$uid])) return $assigned[$uid];
                
                if (!empty($folder['parent_id'])) {
                    $parent = null;
                    foreach($all as $f) if($f['id'] == $folder['parent_id']) { $parent = $f; break; }
                    if ($parent) return $getEffectiveRole($parent, $all, $uid);
                }
                return null;
            };
            
            $filtered = [];
            foreach ($allFolders as $f) {
                $role = $getEffectiveRole($f, $allFolders, $userId);
                if ($role) {
                    $f['effective_role'] = $role;
                    $filtered[] = $f;
                }
            }
        }

        echo json_encode(['success' => true, 'data' => array_values($filtered)]);

    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $teamId = $data['team_id'] ?? null;
        $name = $data['name'] ?? null;
        $parentId = $data['parent_id'] ?? null;

        if (!$teamId || !$name) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Team ID and Name required']);
            exit;
        }

        // Permission Check for subfolder creation
        if ($parentId && !$canWrite($parentId, $pdo, $user['user_id'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No permission to create content here']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO team_folders (team_id, name, created_by, parent_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$teamId, $name, $user['user_id'], $parentId]);
        $folderId = $pdo->lastInsertId();

        $dirPath = __DIR__ . '/../' . $getPhysicalPath($folderId, $pdo, $teamId);
        if (!is_dir($dirPath)) {
            mkdir($dirPath, 0777, true);
        }

        if ($teamId) triggerVaultSync($teamId, $pdo, $user['user_id']);
        finished_response(json_encode(['success' => true, 'id' => $folderId]));
        exit;

    } elseif ($method === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true);
        $folderId = $data['id'] ?? null;
        $users = $data['assigned_to'] ?? [];

        if (!$folderId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Folder ID required']);
            exit;
        }

        if (!$canWrite($folderId, $pdo, $user['user_id'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No permission to update this collection']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE team_folders SET assigned_to = ?, assigned_by = ? WHERE id = ?");
        $stmt->execute([json_encode($users), $user['user_id'], $folderId]);

        // Need team_id for sync trigger
        $tStmt = $pdo->prepare("SELECT team_id FROM team_folders WHERE id = ?");
        $tStmt->execute([$folderId]);
        $teamId = $tStmt->fetchColumn();

        if ($teamId) triggerVaultSync($teamId, $pdo, $user['user_id']);
        finished_response(json_encode(['success' => true]));
        exit;

    } elseif ($method === 'DELETE') {
        $data = json_decode(file_get_contents('php://input'), true);
        $ids = $data['ids'] ?? (!empty($data['id']) ? [$data['id']] : []);

        if (empty($ids)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID required']);
            exit;
        }

        // Batch Permission Check
        foreach ($ids as $checkId) {
            $stmt = $pdo->prepare("SELECT team_id FROM team_folders WHERE id = ?");
            $stmt->execute([$checkId]);
            $item = $stmt->fetch();
            
            if ($item) {
                // Security: Prevent cross-team mass folder wipe
                if (!$isAdmin && $item['team_id'] != $user['team_id']) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Security Violation: Access denied for one or more collections.']);
                    exit;
                }
            }

            if (!$canWrite($checkId, $pdo, $user['user_id'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Hierarchy Restriction: You lack the authority to purge one or more selected items.']);
                exit;
            }
        }

        $deleteFolderRecursive = function($folderId, $pdo) use (&$deleteFolderRecursive, $getPhysicalPath) {
            // Get folder info before deletion for path resolution
            $stmt = $pdo->prepare("SELECT team_id FROM team_folders WHERE id = ?");
            $stmt->execute([$folderId]);
            $folderData = $stmt->fetch();
            $dirPath = null;
            if ($folderData) {
                $dirPath = __DIR__ . '/../' . $getPhysicalPath($folderId, $pdo, $folderData['team_id']);
            }

            // Find subfolders
            $stmt = $pdo->prepare("SELECT id FROM team_folders WHERE parent_id = ?");
            $stmt->execute([$folderId]);
            $subs = $stmt->fetchAll();
            foreach ($subs as $s) {
                $deleteFolderRecursive($s['id'], $pdo);
            }

            // Find and delete files in this folder
            $stmt = $pdo->prepare("SELECT id, file_path FROM team_files WHERE folder_id = ?");
            $stmt->execute([$folderId]);
            $files = $stmt->fetchAll();
            foreach ($files as $f) {
                $fullPath = __DIR__ . '/../' . $f['file_path'];
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }
                $del = $pdo->prepare("DELETE FROM team_files WHERE id = ?");
                $del->execute([$f['id']]);
            }

            // Finally delete the folder record itself
            $del = $pdo->prepare("DELETE FROM team_folders WHERE id = ?");
            $del->execute([$folderId]);

            // Remove physical directory
            if ($dirPath && is_dir($dirPath)) {
                @rmdir($dirPath);
            }
        };

        // For sync, we need a team ID. Let's get it from the first ID before we delete.
        $teamId = null;
        if (!empty($ids)) {
            $tStmt = $pdo->prepare("SELECT team_id FROM team_folders WHERE id = ?");
            $tStmt->execute([$ids[0]]);
            $teamId = $tStmt->fetchColumn();
        }

        foreach ($ids as $id) {
            $deleteFolderRecursive($id, $pdo);
        }

        if ($teamId) triggerVaultSync($teamId, $pdo, $user['user_id']);
        finished_response(json_encode(['success' => true]));
        exit;
    }
} catch (Exception $e) {
    if (ob_get_level() > 0) ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
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
