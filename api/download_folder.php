<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth_middleware.php';

$user = AuthMiddleware::requireAuth();
$folderId = $_GET['id'] ?? null;

if (!$folderId) {
    die("Folder ID required.");
}

try {
    // Check if folder exists and user has access
    $stmt = $pdo->prepare("SELECT * FROM team_folders WHERE id = ?");
    $stmt->execute([$folderId]);
    $folder = $stmt->fetch();

    if (!$folder) {
        die("Folder not found.");
    }

    // Get all files in this folder
    $stmt = $pdo->prepare("SELECT * FROM team_files WHERE folder_id = ?");
    $stmt->execute([$folderId]);
    $files = $stmt->fetchAll();

    if (empty($files)) {
        die("Folder is empty.");
    }

    $zip = new ZipArchive();
    $zipName = sys_get_temp_dir() . '/' . preg_replace('/[^a-zA-Z0-9]/', '_', $folder['name']) . '.zip';

    if ($zip->open($zipName, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        die("Cannot create ZIP file.");
    }

    foreach ($files as $file) {
        $filePath = __DIR__ . '/../' . $file['file_path'];
        if (file_exists($filePath)) {
            $zip->addFile($filePath, $file['file_name']);
        }
    }

    $zip->close();

    header('Content-Type: application/zip');
    header('Content-disposition: attachment; filename=' . basename($zipName));
    header('Content-Length: ' . filesize($zipName));
    readfile($zipName);

    unlink($zipName);
    exit;

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
