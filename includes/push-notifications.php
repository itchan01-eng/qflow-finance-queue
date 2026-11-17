
<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

/**
 * Send a Firebase push notification to every registered device
 * belonging to a QueueFlow account.
 *
 * Call this function AFTER the queue database transaction commits.
 *
 * @return array{sent: int, failed: int}
 */
function qflow_send_push_notification(
    PDO $pdo,
    string $accountId,
    string $title,
    string $body,
    int $queueId
): array {
    $result = [
        'sent' => 0,
        'failed' => 0,
    ];

    if (
        trim($accountId) === '' ||
        trim($title) === '' ||
        trim($body) === ''
    ) {
        return $result;
    }

    $credentialPath = dirname(__DIR__)
        . '/public/api/secrets/firebase-service-account.json';

    if (!is_file($credentialPath) || !is_readable($credentialPath)) {
        error_log(
            'QueueFlow push error: Firebase service-account credentials '
            . 'are missing or unreadable.'
        );

        return $result;
    }

    $tokenStatement = $pdo->prepare(
        'SELECT subscription_id, token
         FROM push_subscriptions
         WHERE account_id = :account_id'
    );

    $tokenStatement->execute([
        'account_id' => $accountId,
    ]);

    $subscriptions = $tokenStatement->fetchAll(PDO::FETCH_ASSOC);

    if (!$subscriptions) {
        return $result;
    }

    try {
        $factory = (new Factory())
            ->withServiceAccount($credentialPath);

        $messaging = $factory->createMessaging();

        foreach ($subscriptions as $subscription) {
            $token = (string) $subscription['token'];

            if ($token === '') {
                $result['failed']++;
                continue;
            }

            $message = CloudMessage::new()
                ->withToken($token)
                ->withNotification(
                    Notification::create($title, $body)
                )
                ->withData([
                    'type' => 'queue_update',
                    'queue_id' => (string) $queueId,
                    'url' => '/notifications.php',
                ]);

            try {
                $messaging->send($message);
                $result['sent']++;
            } catch (Throwable $exception) {
                $result['failed']++;

                error_log(
                    'QueueFlow FCM delivery failed for subscription '
                    . (int) $subscription['subscription_id']
                    . ': ' . $exception->getMessage()
                );
            }
        }
    } catch (Throwable $exception) {
        error_log(
            'QueueFlow Firebase initialization failed: '
            . $exception->getMessage()
        );

        $result['failed'] += count($subscriptions);
    }

    return $result;
}
