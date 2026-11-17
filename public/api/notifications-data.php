<?php
declare(strict_types=1);

require_once '../../includes/functions.php';
qflow_start_session();
qflow_require_login();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function notification_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $pdo = require '../../config/database.php';
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    if ($userId < 1) {
        notification_json(['success' => false, 'message' => 'Please log in again.'], 401);
    }

    $accountStatement = $pdo->prepare(
        "SELECT account_id FROM users
         WHERE user_id = :user_id AND account_status = 'active'
         LIMIT 1"
    );
    $accountStatement->execute(['user_id' => $userId]);
    $accountId = $accountStatement->fetchColumn();
    if ($accountId === false || $accountId === null || $accountId === '') {
        notification_json(['success' => false, 'message' => 'Your account is unavailable.'], 403);
    }
    $accountId = (string) $accountId;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!qflow_verify_csrf($_POST['csrf_token'] ?? '')) {
            notification_json(['success' => false, 'message' => 'Session expired. Refresh the page and try again.'], 419);
        }

        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'mark_all_read') {
            $update = $pdo->prepare(
                'UPDATE notifications SET is_read = 1 WHERE account_id = :account_id AND is_read = 0'
            );
            $update->execute(['account_id' => $accountId]);
            notification_json(['success' => true, 'message' => 'All notifications marked as read.']);
        }

        if ($action === 'mark_read') {
            $notificationId = filter_var($_POST['notification_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$notificationId || $notificationId < 1) {
                notification_json(['success' => false, 'message' => 'Invalid notification.'], 422);
            }
            $update = $pdo->prepare(
                'UPDATE notifications SET is_read = 1
                 WHERE notification_id = :notification_id AND account_id = :account_id'
            );
            $update->execute(['notification_id' => $notificationId, 'account_id' => $accountId]);
            notification_json(['success' => true, 'message' => 'Notification marked as read.']);
        }

        notification_json(['success' => false, 'message' => 'Unsupported action.'], 400);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        header('Allow: GET, POST');
        notification_json(['success' => false, 'message' => 'Method not allowed.'], 405);
    }

    $statement = $pdo->prepare(
        'SELECT notification_id, queue_id, notification_type, title, message, is_read, created_at
         FROM notifications
         WHERE account_id = :account_id
         ORDER BY created_at DESC, notification_id DESC
         LIMIT 100'
    );
    $statement->execute(['account_id' => $accountId]);
    $notifications = $statement->fetchAll(PDO::FETCH_ASSOC);

    $countStatement = $pdo->prepare(
        'SELECT COUNT(*) FROM notifications WHERE account_id = :account_id AND is_read = 0'
    );
    $countStatement->execute(['account_id' => $accountId]);

    notification_json([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => (int) $countStatement->fetchColumn(),
    ]);
} catch (Throwable $exception) {
    error_log('QueueFlow notifications-data error: ' . $exception->getMessage());
    notification_json(['success' => false, 'message' => 'Notification service is temporarily unavailable.'], 500);
}