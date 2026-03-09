<?php
require_once __DIR__ . '/../vendor/autoload.php';
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

// Pre-load essential classes to avoid autoloader failure during resource exhaustion
class_exists('GuzzleHttp\Exception\ConnectException');
class_exists('GuzzleHttp\Exception\RequestException');
class_exists('GuzzleHttp\Promise\Is');
class_exists('GuzzleHttp\Promise\Promise');
class_exists('Minishlink\WebPush\MessageSentReport');

class NotificationService
{
    private static $webPushInstance = null;

    public static function sendPushToUser($userId, $title, $body, $url = '/tools/chat.php', $tag = 'pulse-default', $extra = [])
    {
        global $pdo;
        error_log("Push: Queuing notification for UserID: $userId, Title: $title");

        // Fetch the 3 most recent subscriptions for this user to avoid spamming stale devices
        $stmt = $pdo->prepare("SELECT subscription_json FROM user_push_subscriptions WHERE user_id = ? ORDER BY id DESC LIMIT 3");
        $stmt->execute([$userId]);
        $subscriptions = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($subscriptions)) {
            error_log("Push: No subscriptions found for UserID: $userId");
            return;
        }

        error_log("Push: Found " . count($subscriptions) . " subscriptions for UserID: $userId");

        if (self::$webPushInstance === null) {
            $auth = [
                'VAPID' => [
                    'subject' => VAPID_SUBJECT,
                    'publicKey' => VAPID_PUBLIC_KEY,
                    'privateKey' => VAPID_PRIVATE_KEY,
                ],
            ];

            // Use lower concurrency to prevent 'Too many open files' on restricted systems like macOS
            $defaultOptions = [
                'requestConcurrency' => 8,
                'batchSize' => 100
            ];

            self::$webPushInstance = new WebPush($auth, $defaultOptions);
        }

        $webPush = self::$webPushInstance;

        $payloadData = [
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'tag' => $tag
        ];

        if (!empty($extra)) {
            $payloadData = array_merge($payloadData, $extra);
        }

        $payload = json_encode($payloadData);

        foreach ($subscriptions as $subJson) {
            $subData = json_decode($subJson, true);
            if (!$subData) {
                error_log("Push: Invalid subscription JSON for UserID: $userId");
                continue;
            }

            $subscription = Subscription::create($subData);
            $webPush->queueNotification($subscription, $payload);
        }
    }

    public static function flushPushQueue()
    {
        global $pdo;

        if (self::$webPushInstance === null) {
            return;
        }

        try {
            error_log("Push: Flushing notification queue...");
            // Send all queued notifications
            foreach (self::$webPushInstance->flush() as $report) {
                $endpoint = $report->getEndpoint();
                if ($report->isSuccess()) {
                    error_log("Push: Success for endpoint $endpoint");
                } else {
                    if ($report->isSubscriptionExpired()) {
                        error_log("Push: Subscription expired for $endpoint. Cleaning up.");
                        $stmt = $pdo->prepare("DELETE FROM user_push_subscriptions WHERE subscription_json LIKE ?");
                        $stmt->execute(["%$endpoint%"]);
                    } else {
                        error_log("Push: Failed for endpoint $endpoint: " . $report->getReason());
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log("Push: Flush Error: " . $e->getMessage());
        }
    }
}
