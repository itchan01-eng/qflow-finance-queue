
<?php

date_default_timezone_set('Asia/Manila');

/**
 * Start a secure session.
 */
function qflow_start_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_set_cookie_params([
            'httponly' => true,
            'secure' => !empty($_SERVER['HTTPS'])
                && $_SERVER['HTTPS'] !== 'off',
            'samesite' => 'Lax',
            'path' => '/',
        ]);

        session_start();
    }
}

/**
 * Escape output.
 */
function qflow_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Generate CSRF token.
 */
function qflow_csrf_token(): string
{
    qflow_start_session();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token.
 */
function qflow_verify_csrf(mixed $token): bool
{
    qflow_start_session();

    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Require an authenticated user.
 */
function qflow_require_login(
    string $loginPath = '/login.php'
): void {

    qflow_start_session();

    if (empty($_SESSION['user_id'])) {
        header('Location: ' . $loginPath);
        exit;
    }

    // Force temporary-password users to change their password.
    if (!empty($_SESSION['must_change_password'])) {

        $currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');

        $allowedPages = [
            'change-password.php',
            'logout.php'
        ];

        if (!in_array($currentPage, $allowedPages, true)) {

            $role = qflow_current_role();

            $passwordPages = [
                'Student' => '/account/change-password.php',
                'Staff' => '/staff/change-password.php',
                'Administrator' => '/admin/change-password.php'
            ];

            $destination = $passwordPages[$role] ?? $loginPath;

            header('Location: ' . $destination);
            exit;
        }
    }
}

/**
 * Return authenticated user's role.
 */
function qflow_current_role(): string
{
    qflow_start_session();

    return (string) ($_SESSION['role_name'] ?? '');
}

/**
 * Restrict access based on role.
 */
function qflow_require_role(
    array $allowedRoles,
    string $loginPath = '/login.php'
): void {

    qflow_require_login($loginPath);

    if (!in_array(qflow_current_role(), $allowedRoles, true)) {
        http_response_code(403);
        exit('You do not have permission to access this page.');
    }
}

/**
 * Require access to a specific portal.
 */
function qflow_require_portal(string $portal): void
{
    $portals = [
        'student' => [
            'roles' => ['Student'],
            'login' => '/login.php',
        ],

        'staff' => [
            'roles' => ['Staff'],
            'login' => '/staff/login.php',
        ],

        'admin' => [
            'roles' => ['Administrator'],
            'login' => '/admin/login.php',
        ],
    ];

    if (!isset($portals[$portal])) {
        throw new InvalidArgumentException(
            'Invalid QueueFlow portal.'
        );
    }

    qflow_require_role(
        $portals[$portal]['roles'],
        $portals[$portal]['login']
    );
}

/**
 * Record an activity in transaction_logs.
 *
 * Call this after an action has been successfully performed.
 *
 * @param PDO         $pdo
 * @param string      $actionType
 * @param string      $tableName
 * @param int|null    $recordId
 * @param string|null $description
 */
function qflow_log_transaction(
    PDO $pdo,
    string $actionType,
    string $tableName,
    ?int $recordId = null,
    ?string $description = null
): void {

    qflow_start_session();

    if (empty($_SESSION['user_id'])) {
        throw new RuntimeException(
            'Cannot record transaction: user is not authenticated.'
        );
    }

    // Retrieve the actual account and role from the database.
    $userStatement = $pdo->prepare(
        "SELECT account_id, role_id
         FROM users
         WHERE user_id = :user_id
         LIMIT 1"
    );

    $userStatement->execute([
        'user_id' => (int) $_SESSION['user_id']
    ]);

    $user = $userStatement->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new RuntimeException(
            'Cannot record transaction: user account not found.'
        );
    }

    $accountId = (string) $user['account_id'];
    $roleId = (int) $user['role_id'];

    // Capture and validate the IP address.
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

    if (
        !is_string($ipAddress) ||
        filter_var($ipAddress, FILTER_VALIDATE_IP) === false
    ) {
        $ipAddress = null;
    }

    // Keep values within their database column limits.
    $actionType = substr(trim($actionType), 0, 100);
    $tableName = substr(trim($tableName), 0, 64);

    if ($actionType === '' || $tableName === '') {
        throw new InvalidArgumentException(
            'Action type and table name are required.'
        );
    }

    if ($description !== null) {
        $description = function_exists('mb_substr')
            ? mb_substr($description, 0, 255, 'UTF-8')
            : substr($description, 0, 255);
    }

    // Insert the activity.
    $logStatement = $pdo->prepare(
        "INSERT INTO transaction_logs
            (
                account_id,
                role_id,
                action_type,
                table_name,
                record_id,
                description,
                ip_address
            )
         VALUES
            (
                :account_id,
                :role_id,
                :action_type,
                :table_name,
                :record_id,
                :description,
                :ip_address
            )"
    );

    $logStatement->execute([
        'account_id' => $accountId,
        'role_id' => $roleId,
        'action_type' => $actionType,
        'table_name' => $tableName,
        'record_id' => $recordId,
        'description' => $description,
        'ip_address' => $ipAddress
    ]);
}

/**
 * Determine active page.
 */
function qflow_page_active(
    string $page,
    string $currentPage
): string {
    return $page === $currentPage ? 'active' : '';
}
