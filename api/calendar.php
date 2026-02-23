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

        $stmt = $pdo->prepare("SELECT * FROM calendar_events WHERE team_id = ?");
        $stmt->execute([$teamId]);
        $events = $stmt->fetchAll();

        echo json_encode(['success' => true, 'data' => $events]);

    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['team_id']) || !isset($data['title']) || !isset($data['start_time'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO calendar_events (team_id, title, description, start_time, end_time, color, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['team_id'],
            $data['title'],
            $data['description'] ?? null,
            $data['start_time'],
            $data['end_time'] ?? $data['start_time'],
            $data['color'] ?? '#10b981',
            $user['user_id']
        ]);

        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);

        // Notify team members
        require_once __DIR__ . '/../lib/NotificationService.php';
        $stmt = $pdo->prepare("SELECT id FROM users WHERE team_id = ? AND id != ?");
        $stmt->execute([$data['team_id'], $user['user_id']]);
        $teamMembers = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($teamMembers as $memberId) {
            NotificationService::sendPushToUser(
                $memberId,
                "New Event: " . $data['title'],
                "Scheduled for: " . $data['start_time'],
                "/tools/calendar.php?team_id=" . $data['team_id']
            );
        }

    } elseif ($method === 'PATCH') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID required']);
            exit;
        }

        $fields = [];
        $params = [];
        foreach (['title', 'description', 'start_time', 'end_time', 'color', 'team_id'] as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($fields)) {
            echo json_encode(['success' => true, 'message' => 'No changes']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE calendar_events SET " . implode(', ', $fields) . " WHERE id = ?");
        $params[] = $id;
        $stmt->execute($params);

        echo json_encode(['success' => true]);

        // Notify team members if significant change or just to keep updated
        if (isset($data['team_id']) || isset($data['title']) || isset($data['start_time'])) {
            // Fetch team_id if not in data
            $tId = $data['team_id'] ?? null;
            if (!$tId) {
                $stmt = $pdo->prepare("SELECT team_id FROM calendar_events WHERE id = ?");
                $stmt->execute([$id]);
                $tId = $stmt->fetchColumn();
            }

            require_once __DIR__ . '/../lib/NotificationService.php';
            $stmt = $pdo->prepare("SELECT id FROM users WHERE team_id = ? AND id != ?");
            $stmt->execute([$tId, $user['user_id']]);
            $teamMembers = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($teamMembers as $memberId) {
                NotificationService::sendPushToUser(
                    $memberId,
                    "Event Updated: " . ($data['title'] ?? 'Calendar Event'),
                    "Check the calendar for new details.",
                    "/tools/calendar.php?team_id=" . $tId
                );
            }
        }

    } elseif ($method === 'DELETE') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? $_GET['id'] ?? null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID required']);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM calendar_events WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['success' => true]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
