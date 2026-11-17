
<?php

$projectRoot = dirname(__DIR__, 2);

require_once $projectRoot . '/includes/functions.php';

qflow_start_session();
qflow_require_login();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function qflow_push_response(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');

    qflow_push_response(405, [
        'success' => false,
        'message' => 'Method not allowed.',
    ]);
}

try {
    // Load database configuration from the project root.
    $pdo = require $projectRoot . '/config/database.php';

    if (!$pdo instanceof PDO) {
        throw new RuntimeException(
            'Database configuration did not return a PDO connection.'
        );
    }

    // Verify CSRF token.
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

    if (!is_string($csrfToken) || !qflow_verify_csrf($csrfToken)) {
        qflow_push_response(403, [
            'success' => false,
            'message' => 'Invalid security token. Refresh the page and try again.',
        ]);
    }

    // Read JSON request.
    $input = json_decode(
        file_get_contents('php://input') ?: '',
        true
    );

    if (!is_array($input)) {
        qflow_push_response(400, [
            'success' => false,
            'message' => 'Invalid request data.',
        ]);
    }

    $token = $input['token'] ?? '';

    if (
        !is_string($token) ||
        trim($token) === '' ||
        strlen($token) > 4096
    ) {
        qflow_push_response(422, [
            'success' => false,
            'message' => 'Invalid Firebase registration token.',
        ]);
    }

    // Get the authenticated user's account.
    $userId = (int) ($_SESSION['user_id'] ?? 0);

    $accountStatement = $pdo->prepare(
        "SELECT account_id
         FROM users
         WHERE user_id = :user_id
           AND account_status = 'active'
         LIMIT 1"
    );

    $accountStatement->execute([
        'user_id' => $userId,
    ]);

    $accountId = $accountStatement->fetchColumn();

    if ($accountId === false) {
        qflow_push_response(403, [
            'success' => false,
            'message' => 'Your account is unavailable.',
        ]);
    }

    // Verify push subscriptions table exists.
    $tableStatement = $pdo->query(
        "SHOW TABLES LIKE 'push_subscriptions'"
    );

    if (!$tableStatement->fetchColumn()) {
        qflow_push_response(500, [
            'success' => false,
            'message' => 'The push_subscriptions table has not been created.',
        ]);
    }

    // Save or update the browser's Firebase token.
    $statement = $pdo->prepare(
        "INSERT INTO push_subscriptions (account_id, token) VALUES (:account_id, :token) ON DUPLICATE KEY UPDATE account_id = VALUES(account_id), token = VALUES(token), updated_at = CURRENT_TIMESTAMP"
    );

    $statement->execute([
        'account_id' => (string) $accountId,
        'token' => trim($token),
    ]);

    qflow_push_response(200, [
        'success' => true,
        'message' => 'Browser notification registration saved.',
    ]);

} catch (Throwable $exception) {

    error_log(
        'Saving push token failed: ' . $exception->getMessage()
    );

    qflow_push_response(500, [
        'success' => false,
        'message' => 'Unable to save the browser notification registration.',
    ]);
}
