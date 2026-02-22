<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth_middleware.php';

$user = AuthMiddleware::requireAuthAPI();
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $id = $_GET['id'] ?? null;
        $teamId = $_GET['team_id'] ?? null;
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
        $offset = ($page - 1) * $limit;
        $search = $_GET['search'] ?? $_GET['q'] ?? '';

        // Individual document fetch
        if ($id) {
            $stmt = $pdo->prepare("
                SELECT d.*, 
                       u.full_name as author_name,
                       u2.full_name as updated_by_name,
                       u3.full_name as assigned_by_name
                FROM word_documents d
                LEFT JOIN users u ON d.created_by = u.id
                LEFT JOIN users u2 ON d.updated_by = u2.id
                LEFT JOIN users u3 ON d.assigned_by = u3.id
                WHERE d.id = ?
            ");
            $stmt->execute([$id]);
            $doc = $stmt->fetch();

            if (!$doc) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Document not found']);
                exit;
            }

            $rawIds = json_decode($doc['assigned_to'] ?? '[]', true);
            if (!is_array($rawIds)) {
                $rawIds = (!empty($rawIds) && (is_numeric($rawIds) || is_string($rawIds))) ? [$rawIds] : [];
            }
            $activeUserIds = [];
            if (!empty($rawIds)) {
                $placeholders = implode(',', array_fill(0, count($rawIds), '?'));
                $activeStmt = $pdo->prepare("SELECT id FROM users WHERE id IN ($placeholders) AND is_active = 1");
                $activeStmt->execute($rawIds);
                $activeUserIds = $activeStmt->fetchAll(PDO::FETCH_COLUMN);
            }
            $doc['assigned_users'] = array_values(array_intersect($rawIds, $activeUserIds));
            echo json_encode(['success' => true, 'data' => $doc]);
            exit;
        }

        if (!$teamId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Team ID required']);
            exit;
        }

        // Check if the requested team is the Tester team
        $teamStmt = $pdo->prepare("SELECT name FROM teams WHERE id = ?");
        $teamStmt->execute([$teamId]);
        $teamName = $teamStmt->fetchColumn();
        $isTesterTeam = ($teamName === 'Tester' || $teamName === 'Testers');

        // Search condition
        $where = "WHERE d.team_id = :team_id";
        $params = [':team_id' => $teamId];

        if ($isTesterTeam) {
            // For Tester team, also show files where any member is assigned
            $where = "WHERE (d.team_id = :team_id_1 OR EXISTS (
                SELECT 1 FROM users u 
                WHERE u.team_id = :team_id_2 
                AND (
                    (JSON_VALID(d.assigned_to) AND JSON_CONTAINS(d.assigned_to, CAST(u.id AS JSON)))
                    OR d.assigned_to = CAST(u.id AS CHAR)
                    OR d.assigned_to LIKE CONCAT('%\"', u.id, '\"%')
                )
            ))";
            unset($params[':team_id']);
            $params[':team_id_1'] = $teamId;
            $params[':team_id_2'] = $teamId;
        }

        if ($search) {
            $where .= " AND d.title LIKE :search";
            $params[':search'] = "%$search%";
        }

        // Count total
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM word_documents d $where");
        $countStmt->execute($params);
        $total = $countStmt->fetchColumn();

        // Fetch docs with author names
        $stmt = $pdo->prepare("
            SELECT d.*, 
                   u.full_name as author_name,
                   u2.full_name as updated_by_name,
                   u3.full_name as assigned_by_name
            FROM word_documents d
            LEFT JOIN users u ON d.created_by = u.id
            LEFT JOIN users u2 ON d.updated_by = u2.id
            LEFT JOIN users u3 ON d.assigned_by = u3.id
            $where
            ORDER BY d.updated_at DESC
            LIMIT :limit OFFSET :offset
        ");

        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $docs = $stmt->fetchAll();

        // Format docs (handle assigned_to JSON)
        // Correcting: Resolve active users to ensure counts are accurate
        $allAssignedIds = [];
        foreach ($docs as $doc) {
            $data = $doc['assigned_to'] ?? '[]';
            $ids = json_decode($data, true);
            if (is_array($ids)) {
                $allAssignedIds = array_merge($allAssignedIds, $ids);
            } elseif (!empty($ids) && (is_numeric($ids) || is_string($ids))) {
                $allAssignedIds[] = $ids;
            }
        }
        $allAssignedIds = array_unique(array_filter($allAssignedIds));
        $activeUserIds = [];
        if (!empty($allAssignedIds)) {
            $placeholders = implode(',', array_fill(0, count($allAssignedIds), '?'));
            $activeStmt = $pdo->prepare("SELECT id FROM users WHERE id IN ($placeholders) AND is_active = 1");
            $activeStmt->execute($allAssignedIds);
            $activeUserIds = $activeStmt->fetchAll(PDO::FETCH_COLUMN);
        }

        foreach ($docs as &$doc) {
            $data = $doc['assigned_to'] ?? '[]';
            $decoded = json_decode($data, true);
            $rawIds = [];
            if (is_array($decoded)) {
                $rawIds = $decoded;
            } elseif (!empty($decoded) && (is_numeric($decoded) || is_string($decoded))) {
                $rawIds = [$decoded];
            }

            // Only keep IDs that are in the active user list
            $doc['assigned_users'] = array_values(array_intersect($rawIds, $activeUserIds));
        }

        echo json_encode([
            'success' => true,
            'data' => $docs,
            'pagination' => [
                'current' => $page,
                'pages' => ceil($total / $limit),
                'total' => $total
            ]
        ]);
        exit;
    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['team_id']) || !isset($data['title'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }

        if (isset($data['title'])) {
            $data['title'] = trim($data['title']);
            if (empty($data['title']) || $data['title'] === 'undefined') {
                $data['title'] = 'Untitled Document';
            }
        }

        $stmt = $pdo->prepare("INSERT INTO word_documents (team_id, title, content, created_by, updated_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['team_id'],
            $data['title'] ?? 'Untitled Document',
            $data['content'] ?? '',
            $user['user_id'],
            $user['user_id']
        ]);

        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);

    } elseif ($method === 'PATCH') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $_GET['id'] ?? $data['id'] ?? null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID required']);
            exit;
        }

        $fields = [];
        $params = [];
        if (isset($data['title'])) {
            $data['title'] = trim($data['title']);
            if (empty($data['title']) || $data['title'] === 'undefined') {
                $data['title'] = 'Untitled Document';
            }
            $fields[] = "title = ?";
            $params[] = $data['title'];
        }
        if (isset($data['content'])) {
            $fields[] = "content = ?";
            $params[] = $data['content'];
        }
        if (isset($data['assigned_to'])) {
            $fields[] = "assigned_to = ?";
            $params[] = json_encode($data['assigned_to']);
        }
        if (isset($data['assigned_by'])) {
            $fields[] = "assigned_by = ?";
            $params[] = $data['assigned_by'];
        }

        $fields[] = "updated_by = ?";
        $params[] = $user['user_id'];
        $fields[] = "updated_at = NOW()";

        $stmt = $pdo->prepare("UPDATE word_documents SET " . implode(', ', $fields) . " WHERE id = ?");
        $params[] = $id;
        $stmt->execute($params);

        echo json_encode(['success' => true]);

    } elseif ($method === 'DELETE') {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? null;
        }

        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID required']);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM word_documents WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['success' => true]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
