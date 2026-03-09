<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth_middleware.php';

$user = AuthMiddleware::requireAuthAPI();
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $teamId = $_GET['team_id'] ?? null;
        if (!$teamId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Team ID required']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM team_files WHERE team_id = ? ORDER BY created_at DESC");
        $stmt->execute([$teamId]);
        $files = $stmt->fetchAll();

        echo json_encode(['success' => true, 'data' => $files]);

    } elseif ($method === 'POST') {
        if (!isset($_FILES['file']) || !isset($_POST['team_id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing file or team ID']);
            exit;
        }

        $teamId = $_POST['team_id'];
        $file = $_FILES['file'];

        $uploadDir = __DIR__ . '/../uploads/files/' . $teamId . '/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = basename($file['name']);
        $filePath = 'uploads/files/' . $teamId . '/' . time() . '_' . $fileName;
        $fullPath = __DIR__ . '/../' . $filePath;

        if (move_uploaded_file($file['tmp_name'], $fullPath)) {
            $stmt = $pdo->prepare("INSERT INTO team_files (team_id, file_name, file_path, file_type, file_size, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $teamId,
                $fileName,
                $filePath,
                $file['type'],
                $file['size'],
                $user['user_id']
            ]);

            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Upload failed']);
        }

    } elseif ($method === 'DELETE') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID required']);
            exit;
        }

        // Fetch file info to delete from disk
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

        echo json_encode(['success' => true]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
