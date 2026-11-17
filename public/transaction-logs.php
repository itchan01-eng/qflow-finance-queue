
<?php

require_once '../includes/functions.php';

qflow_start_session();
qflow_require_role(['Administrator']);

$pdo = require '../config/database.php';

$pageTitle = 'Transaction Logs';

$error = '';

/*
|--------------------------------------------------------------------------
| HELPER FUNCTIONS
|--------------------------------------------------------------------------
*/

function transaction_log_escape($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function transaction_log_valid_date(?string $date): ?string
{
    if (!$date) {
        return null;
    }

    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

    return $parsed && $parsed->format('Y-m-d') === $date
        ? $date
        : null;
}

function transaction_log_page_url(int $page): string
{
    $query = $_GET;
    $query['page'] = $page;

    unset($query['export']);

    return 'transaction-logs.php?' . http_build_query($query);
}

/*
|--------------------------------------------------------------------------
| FILTER VALUES
|--------------------------------------------------------------------------
*/

$dateFrom = transaction_log_valid_date($_GET['date_from'] ?? null);
$dateTo = transaction_log_valid_date($_GET['date_to'] ?? null);

$accountFilter = trim((string) ($_GET['account_id'] ?? ''));
$roleFilter = filter_var(
    $_GET['role_id'] ?? '',
    FILTER_VALIDATE_INT
);
$actionFilter = trim((string) ($_GET['action_type'] ?? ''));

$summaryPeriod = (string) ($_GET['period'] ?? 'daily');

if (!in_array($summaryPeriod, ['daily', 'weekly', 'monthly'], true)) {
    $summaryPeriod = 'daily';
}

if ($dateFrom && $dateTo && $dateFrom > $dateTo) {
    $error = 'The starting date cannot be later than the ending date.';
}

/*
|--------------------------------------------------------------------------
| LOAD USERS, ROLES, AND ACTIVITY TYPES
|--------------------------------------------------------------------------
*/

$usersQuery = $pdo->query(
    "SELECT DISTINCT
        u.account_id,
        u.first_name,
        u.last_name
     FROM transaction_logs tl
     INNER JOIN users u
        ON u.account_id = tl.account_id
     ORDER BY u.first_name, u.last_name"
);

$logUsers = $usersQuery->fetchAll(PDO::FETCH_ASSOC);

$rolesQuery = $pdo->query(
    "SELECT role_id, role_name
     FROM roles
     ORDER BY role_name"
);

$roles = $rolesQuery->fetchAll(PDO::FETCH_ASSOC);

$actionsQuery = $pdo->query(
    "SELECT DISTINCT action_type
     FROM transaction_logs
     ORDER BY action_type"
);

$actionTypes = $actionsQuery->fetchAll(PDO::FETCH_COLUMN);

/*
|--------------------------------------------------------------------------
| BUILD FILTER CONDITIONS
|--------------------------------------------------------------------------
*/

$where = [];
$params = [];

if ($dateFrom) {
    $where[] = 'tl.created_at >= :date_from';
    $params['date_from'] = $dateFrom . ' 00:00:00';
}

if ($dateTo) {
    $endDate = new DateTimeImmutable($dateTo);
    $endDate = $endDate->modify('+1 day');

    $where[] = 'tl.created_at < :date_to';
    $params['date_to'] = $endDate->format('Y-m-d') . ' 00:00:00';
}

if ($accountFilter !== '') {
    $where[] = 'tl.account_id = :account_id';
    $params['account_id'] = $accountFilter;
}

if ($roleFilter !== false && $roleFilter !== null && $roleFilter > 0) {
    $where[] = 'tl.role_id = :role_id';
    $params['role_id'] = $roleFilter;
}

if ($actionFilter !== '') {
    $where[] = 'tl.action_type = :action_type';
    $params['action_type'] = $actionFilter;
}

$whereSQL = $where
    ? 'WHERE ' . implode(' AND ', $where)
    : '';

/*
|--------------------------------------------------------------------------
| BASE QUERY
|--------------------------------------------------------------------------
*/

$baseSQL = "
    FROM transaction_logs tl
    LEFT JOIN users u
        ON u.account_id = tl.account_id
    LEFT JOIN roles r
        ON r.role_id = tl.role_id
    $whereSQL
";

/*
|--------------------------------------------------------------------------
| EXCEL EXPORT (.XLS)
|--------------------------------------------------------------------------
|
| Uses an Excel-compatible HTML spreadsheet.
| No Composer or external PHP library required.
|
*/

if (isset($_GET['export']) && $_GET['export'] === 'excel') {

    $exportQuery = $pdo->prepare(
        "SELECT
            tl.created_at,
            tl.account_id,
            CONCAT_WS(' ', u.first_name, u.last_name) AS full_name,
            COALESCE(r.role_name, 'Unknown') AS role_name,
            tl.action_type,
            tl.table_name,
            tl.record_id,
            tl.description,
            tl.ip_address
         $baseSQL
         ORDER BY tl.created_at DESC"
    );

    $exportQuery->execute($params);

    $exportRows = $exportQuery->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');

    header(
        'Content-Disposition: attachment; filename="QueueFlow-Transaction-Logs-' .
        date('Y-m-d') .
        '.xls"'
    );

    header('Cache-Control: max-age=0');

    echo "\xEF\xBB\xBF";

    ?>

    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">

        <style>
            body {
                font-family: Arial, sans-serif;
                color: #222222;
            }

            table {
                border-collapse: collapse;
                width: 100%;
            }

            th {
                background-color: #26384a;
                color: #ffffff;
                font-weight: bold;
                padding: 10px;
                border: 1px solid #b8c2cc;
                text-align: left;
            }

            td {
                padding: 8px;
                border: 1px solid #d5dce3;
                vertical-align: top;
            }

            .report-title {
                font-size: 20px;
                font-weight: bold;
                color: #26384a;
            }

            .report-subtitle {
                color: #666666;
                font-size: 11px;
            }

            .report-info {
                font-size: 11px;
            }
        </style>
    </head>

    <body>

        <table>

            <tr>
                <td colspan="9" class="report-title">
                    QUEUEFLOW - TRANSACTION LOGS REPORT
                </td>
            </tr>

            <tr>
                <td colspan="9" class="report-subtitle">
                    RMMC-MI Finance Queue Management System
                </td>
            </tr>

            <tr>
                <td colspan="9" class="report-info">
                    Generated:
                    <?= transaction_log_escape(date('F d, Y h:i A')) ?>
                </td>
            </tr>

            <tr>
                <td colspan="9" class="report-info">
                    Date From:
                    <?= transaction_log_escape($dateFrom ?? 'All dates') ?>
                    |
                    Date To:
                    <?= transaction_log_escape($dateTo ?? 'All dates') ?>
                </td>
            </tr>

            <tr>
                <td colspan="9" class="report-info">
                    Total Records: <?= count($exportRows) ?>
                </td>
            </tr>

            <tr>
                <td colspan="9"></td>
            </tr>

            <tr>
                <th>Date and Time</th>
                <th>Account ID</th>
                <th>Full Name</th>
                <th>Role</th>
                <th>Activity</th>
                <th>Table</th>
                <th>Record ID</th>
                <th>Description</th>
                <th>IP Address</th>
            </tr>

            <?php foreach ($exportRows as $row): ?>

                <tr>
                    <td>
                        <?= transaction_log_escape($row['created_at']) ?>
                    </td>

                    <td>
                        <?= transaction_log_escape($row['account_id']) ?>
                    </td>

                    <td>
                        <?= transaction_log_escape($row['full_name']) ?>
                    </td>

                    <td>
                        <?= transaction_log_escape($row['role_name']) ?>
                    </td>

                    <td>
                        <?= transaction_log_escape($row['action_type']) ?>
                    </td>

                    <td>
                        <?= transaction_log_escape($row['table_name']) ?>
                    </td>

                    <td>
                        <?= transaction_log_escape($row['record_id']) ?>
                    </td>

                    <td>
                        <?= transaction_log_escape($row['description']) ?>
                    </td>

                    <td>
                        <?= transaction_log_escape($row['ip_address']) ?>
                    </td>
                </tr>

            <?php endforeach; ?>

        </table>

    </body>
    </html>

    <?php

    exit;
}

/*
|--------------------------------------------------------------------------
| SUMMARY PERIOD
|--------------------------------------------------------------------------
*/

$groupExpression = match ($summaryPeriod) {

    'weekly' => "
        CONCAT(
            YEAR(tl.created_at),
            '-W',
            LPAD(WEEK(tl.created_at, 1), 2, '0')
        )
    ",

    'monthly' => "DATE_FORMAT(tl.created_at, '%Y-%m')",

    default => "DATE(tl.created_at)",
};

$summaryQuery = $pdo->prepare(
    "SELECT
        $groupExpression AS period_label,
        COUNT(*) AS total_activities,
        COUNT(DISTINCT tl.account_id) AS active_users
     $baseSQL
     GROUP BY period_label
     ORDER BY period_label DESC
     LIMIT 12"
);

$summaryQuery->execute($params);

$summaryRows = $summaryQuery->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| OVERALL STATISTICS
|--------------------------------------------------------------------------
*/

$statsQuery = $pdo->prepare(
    "SELECT
        COUNT(*) AS total_logs,
        COUNT(DISTINCT tl.account_id) AS total_users,
        COUNT(DISTINCT tl.action_type) AS total_action_types
     $baseSQL"
);

$statsQuery->execute($params);

$stats = $statsQuery->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| PAGINATION
|--------------------------------------------------------------------------
*/

$perPage = 50;

$currentPage = filter_var(
    $_GET['page'] ?? 1,
    FILTER_VALIDATE_INT
);

$currentPage = max(1, (int) ($currentPage ?: 1));

$totalLogs = (int) ($stats['total_logs'] ?? 0);

$totalPages = max(
    1,
    (int) ceil($totalLogs / $perPage)
);

$currentPage = min($currentPage, $totalPages);

$offset = ($currentPage - 1) * $perPage;

/*
|--------------------------------------------------------------------------
| LOAD TRANSACTION LOGS
|--------------------------------------------------------------------------
*/

$logsQuery = $pdo->prepare(
    "SELECT
        tl.log_id,
        tl.account_id,
        tl.role_id,
        tl.action_type,
        tl.table_name,
        tl.record_id,
        tl.description,
        tl.ip_address,
        tl.created_at,
        u.first_name,
        u.last_name,
        COALESCE(r.role_name, 'Unknown') AS role_name
     $baseSQL
     ORDER BY tl.created_at DESC
     LIMIT :limit OFFSET :offset"
);

foreach ($params as $key => $value) {
    $logsQuery->bindValue(':' . $key, $value);
}

$logsQuery->bindValue(':limit', $perPage, PDO::PARAM_INT);
$logsQuery->bindValue(':offset', $offset, PDO::PARAM_INT);

$logsQuery->execute();

$logs = $logsQuery->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| PAGE INCLUDES
|--------------------------------------------------------------------------
*/

require_once '../includes/header.php';
require_once '../includes/sidebar.php';

?>

<style>

/* GENERAL LAYOUT */

.transaction-log-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 24px;
}

.transaction-log-filter {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(175px, 1fr));
    gap: 14px;
    align-items: end;
}

.transaction-log-filter label {
    display: block;
    margin-bottom: 7px;
    font-size: .84rem;
    font-weight: 600;
}

.transaction-log-filter input,
.transaction-log-filter select,
.transaction-log-period-select {
    width: 100%;
    min-height: 42px;
    padding: 9px 11px;
    border: 1px solid var(--border-color, #dfe2e7);
    border-radius: 8px;
    background: var(--card-bg, #fff);
    color: var(--text-color, #202124);
}

.transaction-log-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 9px;
    align-items: center;
}

/* TABLE */

.transaction-log-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 950px;
}

.transaction-log-table th,
.transaction-log-table td {
    padding: 13px 14px;
    border-bottom: 1px solid var(--border-color, #e9e9ed);
    text-align: left;
    vertical-align: top;
    font-size: .86rem;
}

.transaction-log-table th {
    color: var(--text-muted, #667085);
    font-size: .75rem;
    text-transform: uppercase;
    letter-spacing: .04em;
    white-space: nowrap;
}

.log-action {
    display: inline-flex;
    padding: 5px 8px;
    border-radius: 6px;
    background: #eef2f7;
    color: #344054;
    font-size: .75rem;
    font-weight: 700;
    white-space: nowrap;
}

.log-role {
    color: var(--text-muted, #667085);
    font-size: .78rem;
}

.log-description {
    max-width: 300px;
    line-height: 1.5;
    overflow-wrap: anywhere;
}

.log-ip {
    color: var(--text-muted, #667085);
    font-size: .78rem;
}

/* SUMMARY */

.log-summary-list {
    display: grid;
    gap: 0;
}

.log-summary-row {
    display: flex;
    justify-content: space-between;
    gap: 15px;
    padding: 12px 0;
    border-bottom: 1px solid var(--border-color, #e9e9ed);
}

.log-summary-row:last-child {
    border-bottom: 0;
}

.log-summary-period {
    font-weight: 600;
}

.log-summary-count {
    text-align: right;
    white-space: nowrap;
}

/* PAGINATION */

.log-pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 18px;
}

.log-pagination-links {
    display: flex;
    gap: 7px;
}

.log-pagination-links a,
.log-pagination-links span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    min-height: 36px;
    padding: 6px 10px;
    border: 1px solid var(--border-color, #e9e9ed);
    border-radius: 7px;
    text-decoration: none;
    color: inherit;
}

.log-pagination-links .current-page {
    background: var(--primary-color, #c8102e);
    color: white;
    border-color: var(--primary-color, #c8102e);
}

.log-empty {
    padding: 35px 15px;
    text-align: center;
    color: var(--text-muted, #667085);
}

/* PRINT-ONLY HEADER */

.print-report-header {
    display: none;
}

/* RESPONSIVE */

@media (max-width: 650px) {

    .transaction-log-stats {
        grid-template-columns: 1fr;
    }

    .log-pagination {
        align-items: flex-start;
        flex-direction: column;
    }

}

/*
|--------------------------------------------------------------------------
| PDF / PRINT LAYOUT
|--------------------------------------------------------------------------
*/

@media print {

    @page {
        size: A4 landscape;
        margin: 12mm;
    }

    body {
        background: #ffffff !important;
        color: #222222 !important;
        font-family: Arial, sans-serif !important;
        font-size: 9px !important;
    }

    .sidebar,
    .page-header,
    .transaction-log-filter-section,
    .transaction-log-actions,
    .log-pagination,
    .page-footer,
    .no-print {
        display: none !important;
    }

    .main-content {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
        max-width: none !important;
    }

    .print-report-header {
        display: block !important;
        margin-bottom: 18px;
        padding-bottom: 12px;
        border-bottom: 2px solid #26384a;
    }

    .print-report-header h1 {
        font-size: 19px;
        color: #26384a;
        margin: 0 0 5px;
    }

    .print-report-header p {
        font-size: 9px;
        margin: 3px 0;
        color: #555555;
    }

    .transaction-log-stats {
        display: grid !important;
        grid-template-columns: repeat(3, 1fr) !important;
        gap: 10px !important;
        margin-bottom: 15px !important;
    }

    .stat-card {
        background: #ffffff !important;
        border: 1px solid #d5dce3 !important;
        box-shadow: none !important;
        padding: 10px !important;
        break-inside: avoid;
    }

    .stat-card strong {
        font-size: 16px !important;
    }

    .stat-card small {
        font-size: 8px !important;
    }

    .section-block {
        background: #ffffff !important;
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
        margin-bottom: 15px !important;
    }

    .section-heading {
        margin-bottom: 10px !important;
    }

    .section-heading h2 {
        font-size: 12px !important;
        color: #26384a !important;
    }

    .table-container {
        overflow: visible !important;
        width: 100% !important;
    }

    .transaction-log-table {
        width: 100% !important;
        min-width: 0 !important;
        border-collapse: collapse !important;
        table-layout: fixed !important;
    }

    .transaction-log-table thead {
        display: table-header-group;
    }

    .transaction-log-table th {
        background: #26384a !important;
        color: #ffffff !important;
        font-size: 8px !important;
        padding: 7px 5px !important;
        border: 1px solid #26384a !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .transaction-log-table td {
        font-size: 8px !important;
        padding: 6px 5px !important;
        border: 1px solid #d5dce3 !important;
        overflow-wrap: anywhere !important;
    }

    .transaction-log-table tr {
        break-inside: avoid;
    }

    .transaction-log-table th:nth-child(1),
    .transaction-log-table td:nth-child(1) {
        width: 13%;
    }

    .transaction-log-table th:nth-child(2),
    .transaction-log-table td:nth-child(2) {
        width: 14%;
    }

    .transaction-log-table th:nth-child(3),
    .transaction-log-table td:nth-child(3) {
        width: 10%;
    }

    .transaction-log-table th:nth-child(4),
    .transaction-log-table td:nth-child(4) {
        width: 12%;
    }

    .transaction-log-table th:nth-child(5),
    .transaction-log-table td:nth-child(5) {
        width: 11%;
    }

    .transaction-log-table th:nth-child(6),
    .transaction-log-table td:nth-child(6) {
        width: 8%;
    }

    .transaction-log-table th:nth-child(7),
    .transaction-log-table td:nth-child(7) {
        width: 5%;
    }

    .log-description {
        max-width: none !important;
    }

    .log-action {
        background: transparent !important;
        color: #222222 !important;
        padding: 0 !important;
    }

    .log-role,
    .log-ip {
        color: #444444 !important;
    }

    a {
        color: #222222 !important;
        text-decoration: none !important;
    }

}

</style>

<main class="main-content">

    <!-- PRINT REPORT HEADER -->

    <div class="print-report-header">

        <h1>QUEUEFLOW</h1>

        <p>
            <strong>TRANSACTION LOGS AND REPORTS</strong>
        </p>

        <p>
            RMMC-MI Finance Queue Management System
        </p>

        <p>
            Generated:
            <?= transaction_log_escape(date('F d, Y h:i A')) ?>
        </p>

        <p>
            Date Range:
            <?= transaction_log_escape($dateFrom ?? 'All dates') ?>
            to
            <?= transaction_log_escape($dateTo ?? 'All dates') ?>
        </p>

        <p>
            Total Matching Records:
            <?= number_format($totalLogs) ?>
        </p>

    </div>

    <!-- PAGE HEADER -->

    <header class="page-header">

        <div>
            <h1>Transaction Logs</h1>

            <p class="page-description">
                Monitor and review activities performed by Students,
                Staff, and Administrators.
            </p>
        </div>

        <div class="transaction-log-actions no-print">

            <button
                type="button"
                class="button button-secondary"
                onclick="window.print()"
            >
                Print / Save PDF
            </button>

            <a
                href="transaction-logs.php?<?= transaction_log_escape(
                    http_build_query(
                        array_merge($_GET, ['export' => 'excel'])
                    )
                ) ?>"
                class="button button-primary"
            >
                Export Excel
            </a>

        </div>

    </header>

    <?php if ($error !== ''): ?>

        <div class="alert alert-error">
            <?= transaction_log_escape($error) ?>
        </div>

    <?php endif; ?>

    <!-- SUMMARY CARDS -->

    <section class="transaction-log-stats">

        <article class="stat-card">

            <span class="stat-label">Total Activities</span>

            <strong>
                <?= number_format($totalLogs) ?>
            </strong>

            <small>Matching transaction logs</small>

        </article>

        <article class="stat-card">

            <span class="stat-label">Users Involved</span>

            <strong>
                <?= number_format((int) ($stats['total_users'] ?? 0)) ?>
            </strong>

            <small>Distinct accounts recorded</small>

        </article>

        <article class="stat-card">

            <span class="stat-label">Activity Types</span>

            <strong>
                <?= number_format(
                    (int) ($stats['total_action_types'] ?? 0)
                ) ?>
            </strong>

            <small>Different recorded actions</small>

        </article>

    </section>

    <!-- FILTERS -->

    <section class="section-block transaction-log-filter-section">

        <div class="section-heading">

            <div>
                <h2>Filter Transaction Logs</h2>
                <p>Choose the records you want to review.</p>
            </div>

        </div>

        <form method="GET" action="transaction-logs.php">

            <div class="transaction-log-filter">

                <div>
                    <label for="date_from">Date From</label>

                    <input
                        type="date"
                        id="date_from"
                        name="date_from"
                        value="<?= transaction_log_escape($dateFrom ?? '') ?>"
                    >
                </div>

                <div>
                    <label for="date_to">Date To</label>

                    <input
                        type="date"
                        id="date_to"
                        name="date_to"
                        value="<?= transaction_log_escape($dateTo ?? '') ?>"
                    >
                </div>

                <div>
                    <label for="account_id">User</label>

                    <select id="account_id" name="account_id">

                        <option value="">All Users</option>

                        <?php foreach ($logUsers as $logUser): ?>

                            <option
                                value="<?= transaction_log_escape(
                                    $logUser['account_id']
                                ) ?>"
                                <?= $accountFilter === $logUser['account_id']
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= transaction_log_escape(
                                    trim(
                                        $logUser['first_name'] . ' ' .
                                        $logUser['last_name']
                                    ) . ' (' . $logUser['account_id'] . ')'
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>

                <div>
                    <label for="role_id">Role</label>

                    <select id="role_id" name="role_id">

                        <option value="">All Roles</option>

                        <?php foreach ($roles as $role): ?>

                            <option
                                value="<?= (int) $role['role_id'] ?>"
                                <?= (string) $roleFilter ===
                                    (string) $role['role_id']
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= transaction_log_escape(
                                    $role['role_name']
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>

                <div>
                    <label for="action_type">Activity Type</label>

                    <select id="action_type" name="action_type">

                        <option value="">All Activities</option>

                        <?php foreach ($actionTypes as $actionType): ?>

                            <option
                                value="<?= transaction_log_escape(
                                    $actionType
                                ) ?>"
                                <?= $actionFilter === $actionType
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= transaction_log_escape($actionType) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="transaction-log-actions">

                    <button
                        type="submit"
                        class="button button-primary"
                    >
                        Apply Filters
                    </button>

                    <a
                        href="transaction-logs.php"
                        class="button button-secondary"
                    >
                        Reset
                    </a>

                </div>

            </div>

        </form>

    </section>

    <!-- ACTIVITY SUMMARY -->

    <section class="section-block">

        <div class="section-heading">

            <div>
                <h2>Activity Summary</h2>
                <p>View activity totals by reporting period.</p>
            </div>

            <form method="GET" action="transaction-logs.php">

                <?php foreach ($_GET as $key => $value): ?>

                    <?php if (
                        !in_array(
                            $key,
                            ['period', 'page', 'export'],
                            true
                        ) &&
                        is_scalar($value)
                    ): ?>

                        <input
                            type="hidden"
                            name="<?= transaction_log_escape($key) ?>"
                            value="<?= transaction_log_escape($value) ?>"
                        >

                    <?php endif; ?>

                <?php endforeach; ?>

                <select
                    name="period"
                    class="transaction-log-period-select"
                    onchange="this.form.submit()"
                    aria-label="Summary period"
                >

                    <option
                        value="daily"
                        <?= $summaryPeriod === 'daily' ? 'selected' : '' ?>
                    >
                        Daily
                    </option>

                    <option
                        value="weekly"
                        <?= $summaryPeriod === 'weekly' ? 'selected' : '' ?>
                    >
                        Weekly
                    </option>

                    <option
                        value="monthly"
                        <?= $summaryPeriod === 'monthly' ? 'selected' : '' ?>
                    >
                        Monthly
                    </option>

                </select>

            </form>

        </div>

        <?php if ($summaryRows): ?>

            <div class="log-summary-list">

                <?php foreach ($summaryRows as $summary): ?>

                    <div class="log-summary-row">

                        <div>

                            <div class="log-summary-period">
                                <?= transaction_log_escape(
                                    $summary['period_label']
                                ) ?>
                            </div>

                            <small class="log-role">
                                <?= number_format(
                                    (int) $summary['active_users']
                                ) ?>
                                distinct users
                            </small>

                        </div>

                        <div class="log-summary-count">

                            <strong>
                                <?= number_format(
                                    (int) $summary['total_activities']
                                ) ?>
                            </strong>

                            <small class="log-role">
                                activities
                            </small>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="log-empty">
                No summary data found for the selected filters.
            </div>

        <?php endif; ?>

    </section>

    <!-- TRANSACTION LOG TABLE -->

    <section class="section-block">

        <div class="section-heading">

            <div>

                <h2>Recorded Activities</h2>

                <p>
                    Showing <?= number_format(count($logs)) ?>
                    of <?= number_format($totalLogs) ?>
                    matching records.
                </p>

            </div>

        </div>

        <div class="table-container">

            <table class="transaction-log-table">

                <thead>

                    <tr>
                        <th>Date and Time</th>
                        <th>User</th>
                        <th>Role</th>
                        <th>Activity</th>
                        <th>Record</th>
                        <th>Description</th>
                        <th>IP Address</th>
                    </tr>

                </thead>

                <tbody>

                    <?php if (!$logs): ?>

                        <tr>
                            <td colspan="7" class="log-empty">
                                No transaction logs found.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($logs as $log): ?>

                            <tr>

                                <td>

                                    <strong>
                                        <?= transaction_log_escape(
                                            date(
                                                'M d, Y',
                                                strtotime($log['created_at'])
                                            )
                                        ) ?>
                                    </strong>

                                    <br>

                                    <small class="log-role">
                                        <?= transaction_log_escape(
                                            date(
                                                'h:i:s A',
                                                strtotime($log['created_at'])
                                            )
                                        ) ?>
                                    </small>

                                </td>

                                <td>

                                    <?php if ($log['account_id']): ?>

                                        <strong>
                                            <?= transaction_log_escape(
                                                trim(
                                                    ($log['first_name'] ?? '') .
                                                    ' ' .
                                                    ($log['last_name'] ?? '')
                                                ) ?: 'Unknown User'
                                            ) ?>
                                        </strong>

                                        <br>

                                        <small class="log-role">
                                            <?= transaction_log_escape(
                                                $log['account_id']
                                            ) ?>
                                        </small>

                                    <?php else: ?>

                                        <span class="log-role">
                                            Unknown / Deleted Account
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <span class="log-role">
                                        <?= transaction_log_escape(
                                            $log['role_name']
                                        ) ?>
                                    </span>

                                </td>

                                <td>

                                    <span class="log-action">
                                        <?= transaction_log_escape(
                                            $log['action_type']
                                        ) ?>
                                    </span>

                                </td>

                                <td>

                                    <strong>
                                        <?= transaction_log_escape(
                                            $log['table_name']
                                        ) ?>
                                    </strong>

                                    <br>

                                    <small class="log-role">
                                        ID:
                                        <?= $log['record_id'] !== null
                                            ? (int) $log['record_id']
                                            : 'N/A' ?>
                                    </small>

                                </td>

                                <td>

                                    <div class="log-description">
                                        <?= transaction_log_escape(
                                            $log['description'] ??
                                            'No description'
                                        ) ?>
                                    </div>

                                </td>

                                <td>

                                    <span class="log-ip">
                                        <?= transaction_log_escape(
                                            $log['ip_address'] ?? 'N/A'
                                        ) ?>
                                    </span>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

        <!-- PAGINATION -->

        <?php if ($totalPages > 1): ?>

            <div class="log-pagination">

                <small>
                    Page <?= $currentPage ?>
                    of <?= $totalPages ?>
                </small>

                <div class="log-pagination-links">

                    <?php if ($currentPage > 1): ?>

                        <a href="<?= transaction_log_escape(
                            transaction_log_page_url($currentPage - 1)
                        ) ?>">
                            Previous
                        </a>

                    <?php endif; ?>

                    <?php

                    $startPage = max(1, $currentPage - 2);
                    $endPage = min($totalPages, $currentPage + 2);

                    ?>

                    <?php for (
                        $page = $startPage;
                        $page <= $endPage;
                        $page++
                    ): ?>

                        <?php if ($page === $currentPage): ?>

                            <span class="current-page">
                                <?= $page ?>
                            </span>

                        <?php else: ?>

                            <a href="<?= transaction_log_escape(
                                transaction_log_page_url($page)
                            ) ?>">
                                <?= $page ?>
                            </a>

                        <?php endif; ?>

                    <?php endfor; ?>

                    <?php if ($currentPage < $totalPages): ?>

                        <a href="<?= transaction_log_escape(
                            transaction_log_page_url($currentPage + 1)
                        ) ?>">
                            Next
                        </a>

                    <?php endif; ?>

                </div>

            </div>

        <?php endif; ?>

    </section>

    <footer class="page-footer">
        <span>
            QueueFlow — RMMC-MI Finance Queue Management System
        </span>

        <span><?= date('Y') ?></span>
    </footer>

</main>

<?php require '../includes/footer.php'; ?>
