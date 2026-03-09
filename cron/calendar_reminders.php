<?php
/**
 * Cron Job: Calendar Meeting Reminders
 * This script should be set to run every 1 minute.
 * It checks for meetings starting in approximately 2 minutes and sends push notifications.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/NotificationService.php';

// Set timezone to match your database if needed (Defaulting to UTC or system local)
date_default_timezone_set('Asia/Kolkata'); // Matching your local time from metadata

try {
    // Expanded Trigger Window:
    // 1. Any meeting starting in the next 3 minutes (Standard reminder)
    // 2. Any meeting that started in the last 10 minutes (Catch-up reminder)
    // This handles cases where the cron missed the exact 2-min mark.
    $stmt = $pdo->prepare("
        SELECT id, team_id, created_by, title, start_time 
        FROM calendar_events 
        WHERE reminded = 0 
        AND start_time <= (NOW() + INTERVAL 3 MINUTE)
        AND start_time >= (NOW() - INTERVAL 10 MINUTE)
    ");
    $stmt->execute();
    $upcomingEvents = $stmt->fetchAll();

    if (empty($upcomingEvents)) {
        // No upcoming reminders
        exit;
    }

    foreach ($upcomingEvents as $event) {
        // Collect all target user IDs for this notification:
        // 1. All members of the designated team
        // 2. The creator of the event (especially if they aren't in the team)
        // 3. All System Administrators (who oversee everything)
        $userStmt = $pdo->prepare("
            SELECT DISTINCT id FROM users 
            WHERE team_id = ? 
            OR id = ? 
            OR role IN ('admin', 'administrator', 'superadmin')
        ");
        $userStmt->execute([$event['team_id'], $event['created_by']]);
        $userIds = $userStmt->fetchAll(PDO::FETCH_COLUMN);

        $readableTime = date('h:i A', strtotime($event['start_time']));

        foreach ($userIds as $userId) {
            NotificationService::sendPushToUser(
                $userId,
                "🗓️ Meeting Starting Soon",
                "\"{$event['title']}\" begins at {$readableTime}. Click to join.",
                "/tools/calendar.php?team_id={$event['team_id']}",
                "calendar-alert-{$event['id']}", // Unique tag per meeting
                ['type' => 'calendar', 'event_id' => $event['id']]
            );
        }

        // Mark as reminded so we don't send multiple alerts
        $updateStmt = $pdo->prepare("UPDATE calendar_events SET reminded = 1 WHERE id = ?");
        $updateStmt->execute([$event['id']]);

        echo "[" . date('Y-m-d H:i:s') . "] Sent 2-min reminders for event: {$event['title']} (ID: {$event['id']})\n";
    }

    NotificationService::flushPushQueue();

} catch (Exception $e) {
    error_log("Calendar Cron Error: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
}
