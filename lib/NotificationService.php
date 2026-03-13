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

        // Fetch the 10 most recent subscriptions for this user to avoid spamming stale devices
        $stmt = $pdo->prepare("SELECT subscription_json FROM user_push_subscriptions WHERE user_id = ? ORDER BY id DESC LIMIT 10");
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

        // Unified payload for both Mobile (OS-level) and Desktop (Data-driven).
        // The 'notification' key is essential for mobile background delivery.
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'] ?? 'turtledot.in';
        $baseUrl = $protocol . $host;

        // STRATEGY: Data-only Push
        // We do NOT send the 'notification' key. This prevents the OS/Browser from showing 
        // an automatic, generic notification that competes with our Service Worker.
        // The Service Worker will receive this 'data' payload and call showNotification() itself.
        $payloadData = [
            'data' => array_merge([
                'title' => $title,
                'body' => $body,
                'url' => $url,
                'tag' => $tag,
                'icon' => $baseUrl . '/assets/images/turtle_logo_512.png',
                'badge' => $baseUrl . '/assets/images/turtle_logo_192.png'
            ], $extra)
        ];

        $payload = json_encode($payloadData);

        // Deduplicate endpoints to prevent sending multiple pushes to the same device/session
        $uniqueEndpoints = [];
        
        foreach ($subscriptions as $subJson) {
            $subData = json_decode($subJson, true);
            if (!$subData || !isset($subData['endpoint'])) {
                continue;
            }

            $endpoint = $subData['endpoint'];
            if (in_array($endpoint, $uniqueEndpoints)) {
                continue;
            }
            $uniqueEndpoints[] = $endpoint;

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
