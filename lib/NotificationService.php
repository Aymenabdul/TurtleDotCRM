<?php
require_once __DIR__ . '/../vendor/autoload.php';
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

class NotificationService
{
    public static function sendPushToUser($userId, $title, $body, $url = '/tools/chat.php', $tag = 'pulse-default', $extra = [])
    {
        global $pdo;

        // Fetch all subscriptions for this user
        $stmt = $pdo->prepare("SELECT subscription_json FROM user_push_subscriptions WHERE user_id = ?");
        $stmt->execute([$userId]);
        $subscriptions = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($subscriptions))
            return;

        $auth = [
            'VAPID' => [
                'subject' => VAPID_SUBJECT,
                'publicKey' => VAPID_PUBLIC_KEY,
                'privateKey' => VAPID_PRIVATE_KEY,
            ],
        ];

        $webPush = new WebPush($auth);

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
            if (!$subData)
                continue;

            $subscription = Subscription::create($subData);
            $webPush->queueNotification($subscription, $payload);
        }

        // Send all queued notifications
        foreach ($webPush->flush() as $report) {
            $endpoint = $report->getEndpoint();
            if (!$report->isSuccess()) {
                if ($report->isSubscriptionExpired()) {
                    // Clean up expired subscriptions
                    $stmt = $pdo->prepare("DELETE FROM user_push_subscriptions WHERE subscription_json LIKE ?");
                    $stmt->execute(["%$endpoint%"]);
                }
                error_log("Push failed for endpoint $endpoint: " . $report->getReason());
            }
        }
    }
}
