<?php

$currentPage = basename($_SERVER['PHP_SELF'] ?? 'index.php');

$roleName = qflow_current_role();
$normalizedRole = strtolower(trim($roleName));

$roleLabels = [
    'student' => 'Student',
    'staff' => 'Staff',
    'administrator' => 'Administrator',
];

$roleLabel = $roleLabels[$normalizedRole] ?? 'User';

// Get staff service assignments separately from the account role.
$serviceAssignments = [];

if ($normalizedRole === 'staff' && !empty($staffAssignments)) {
    $serviceAssignments = array_unique(
        array_filter(
            array_column($staffAssignments, 'service_name')
        )
    );
}

$serviceLabel = !empty($serviceAssignments)
    ? implode(', ', $serviceAssignments)
    : '';

$fullName = trim(
    ($_SESSION['first_name'] ?? 'User') . ' ' .
    ($_SESSION['last_name'] ?? '')
);

$userInitial = strtoupper(
    substr($_SESSION['first_name'] ?? 'U', 0, 1)
);

$navigation = [

    [
        'label' => 'Dashboard',
        'url' => 'dashboard.php',
        'icon' => 'solar:home-2-linear',
        'roles' => ['Student', 'Staff', 'Administrator'],
    ],

    [
        'label' => 'My Queue',
        'url' => 'my-queue.php',
        'icon' => 'solar:ticket-linear',
        'roles' => ['Student'],
    ],

    [
        'label' => 'Queue List',
        'url' => '/admin/queue-handling.php',
        'icon' => 'solar:list-check-linear',
        'roles' => ['Staff'],
    ],

    [
        'label' => 'My Profile',
        'url' => 'profile.php',
        'icon' => 'solar:user-circle-linear',
        'roles' => ['Student', 'Staff', 'Administrator'],
    ],

    [
        'label' => 'Live Queue',
        'url' => 'https://jubilant-meme-r45vxw6g5xpjc57j4-8000.app.github.dev/live-queue.php',
        'icon' => 'solar:monitor-linear',
        'roles' => ['Student', 'Staff', 'Administrator'],
    ],

    [
        'label' => 'Notifications',
        'url' => 'notifications.php',
        'icon' => 'solar:bell-linear',
        'roles' => ['Student'],
    ],

    [
        'label' => 'Finance Services',
        'url' => 'services.php',
        'icon' => 'solar:banknote-2-linear',
        'roles' => ['Student'],
    ],

    [
        'label' => 'Guest Ticket',
        'url' => '/admin/generate-guest-ticket.php',
        'icon' => 'solar:ticket-linear',
        'roles' => ['Administrator'],
    ],

    [
        'label' => 'Transaction Logs',
        'url' => '/transaction-logs.php',
        'icon' => 'solar:clipboard-text-linear',
        'roles' => ['Administrator'],
    ],

    [
        'label' => 'Priority Requests',
        'url' => '/admin/priority-requests.php',
        'icon' => 'solar:shield-warning-linear',
        'roles' => ['Administrator'],
    ],

    [
        'label' => 'Staff Management',
        'url' => '/admin/staff-management.php',
        'icon' => 'solar:user-id-linear',
        'roles' => ['Administrator'],
    ],

    [
        'label' => 'User Management',
        'url' => '/admin/user-management.php',
        'icon' => 'solar:users-group-rounded-linear',
        'roles' => ['Administrator'],
    ],

    [
        'label' => 'Counter Management',
        'url' => '/admin/counter-assignments.php',
        'icon' => 'solar:monitor-smartphone-linear',
        'roles' => ['Administrator'],
    ],

];


/*
|--------------------------------------------------------------------------
| NAVIGATION RENDERER
|--------------------------------------------------------------------------
*/

function qflow_render_navigation(
    array $navigation,
    string $roleName,
    string $currentPage,
    string $linkClass = 'nav-link'
): void {

    foreach ($navigation as $item) {

        if (!in_array($roleName, $item['roles'], true)) {
            continue;
        }

        $active = qflow_page_active(
            $item['url'],
            $currentPage
        );

        ?>

        <a
            href="<?= qflow_escape($item['url']) ?>"
            class="<?= qflow_escape($linkClass) ?> <?= qflow_escape($active) ?>"
            <?= $active !== '' ? 'aria-current="page"' : '' ?>
        >

            <iconify-icon
                icon="<?= qflow_escape($item['icon']) ?>"
                class="nav-icon"
                aria-hidden="true"
            ></iconify-icon>

            <span>
                <?= qflow_escape($item['label']) ?>
            </span>

        </a>

        <?php
    }
}


/*
|--------------------------------------------------------------------------
| SIDEBAR USER INFORMATION
|--------------------------------------------------------------------------
*/

function qflow_render_sidebar_user(
    string $userInitial,
    string $fullName,
    string $roleLabel,
    string $serviceLabel
): void {

    ?>

    <div class="sidebar-user">

        <span class="avatar">
            <?= qflow_escape($userInitial) ?>
        </span>

        <div class="sidebar-user-info">

            <strong>
                <?= qflow_escape($fullName) ?>
            </strong>

            <span class="sidebar-role">
                <?= qflow_escape($roleLabel) ?>
            </span>

            <?php if ($serviceLabel !== '') { ?>

                <small class="sidebar-assignment">
                    Assigned: <?= qflow_escape($serviceLabel) ?>
                </small>

            <?php } ?>

        </div>

    </div>

    <?php
}


/*
|--------------------------------------------------------------------------
| LOGOUT BUTTON
|--------------------------------------------------------------------------
*/

function qflow_render_logout(): void {

    ?>

    <form action="logout.php" method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= qflow_escape(qflow_csrf_token()) ?>"
        >

        <button class="logout-button" type="submit">

            <iconify-icon
                icon="solar:logout-2-linear"
                aria-hidden="true"
            ></iconify-icon>

            <span>Sign Out</span>

        </button>

    </form>

    <?php
}

?>

<script src="assets/js/mobile-sidebar.js" defer></script>
<style>

/* =====================================================
   QUEUEFLOW SIDEBAR
   Responsive | Signature Red Theme
   Optimized for 11-inch laptops
===================================================== */

:root {
    --sidebar-width: 250px;
    --qf-red: #dc2626;
    --qf-red-dark: #b91c1c;
    --qf-red-light: #fee2e2;
    --qf-red-soft: #fff1f2;
}

/* =====================================================
   DESKTOP SIDEBAR
===================================================== */

@media (min-width: 769px) {

    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        bottom: 0;

        width: var(--sidebar-width);
        height: 100vh;
        height: 100dvh;

        box-sizing: border-box;

        display: flex;
        flex-direction: column;

        padding: 16px 13px;

        overflow: hidden;
        z-index: 100;
    }

    /* BRAND */

    .sidebar > .brand {
        display: flex;
        align-items: center;
        gap: 11px;

        padding: 4px 8px 15px;

        flex-shrink: 0;
        text-decoration: none;
    }

    .sidebar > .brand img {
        width: 38px;
        height: 38px;
        object-fit: contain;
        flex-shrink: 0;
    }

    .sidebar > .brand span {
        display: flex;
        flex-direction: column;
        min-width: 0;
    }

    .sidebar > .brand strong {
        font-size: 16px;
        font-weight: 750;
    }

    .sidebar > .brand small {
        font-size: 11px;
    }

    /* SECTION LABEL */

    .sidebar > .sidebar-section-label {
        flex-shrink: 0;

        padding: 11px 10px 8px;

        font-size: 10px;
        font-weight: 700;
        letter-spacing: 1px;
    }

    /* NAVIGATION */

    .sidebar > .sidebar-navigation {
        flex: 1 1 auto;
        min-height: 0;

        display: flex;
        flex-direction: column;
        gap: 4px;

        padding: 3px 0 12px;

        overflow-y: auto;
        overflow-x: hidden;

        scrollbar-width: thin;
        overscroll-behavior: contain;
    }

    .sidebar-navigation .nav-link {
        display: flex;
        align-items: center;
        gap: 11px;

        min-height: 41px;
        padding: 8px 11px;

        box-sizing: border-box;
        flex-shrink: 0;

        border-radius: 9px;

        font-size: 13px;
        font-weight: 500;
        text-decoration: none;

        transition:
            background 0.2s ease,
            color 0.2s ease;
    }

    .sidebar-navigation .nav-icon {
        width: 19px;
        height: 19px;
        font-size: 19px;
        flex-shrink: 0;
    }

    /* RED HOVER */

    .sidebar-navigation .nav-link:hover {
        background: var(--qf-red-soft);
        color: var(--qf-red);
    }

    /* RED ACTIVE PAGE */

    .sidebar-navigation .nav-link.active {
        background: var(--qf-red-light);
        color: var(--qf-red);
        font-weight: 650;
    }

    .sidebar-navigation .nav-link.active .nav-icon {
        color: var(--qf-red);
    }

    /* BOTTOM USER SECTION */

    .sidebar-bottom {
        flex: 0 0 auto;
        margin-top: auto;

        display: flex;
        flex-direction: column;
        gap: 9px;

        padding-top: 12px;

        border-top: 1px solid rgba(0, 0, 0, 0.08);
    }

    /* USER INFORMATION */

    .sidebar-user {
        display: flex;
        align-items: center;
        gap: 10px;

        padding: 3px;

        min-width: 0;
        flex-shrink: 0;
    }

    .sidebar-user .avatar {
        width: 36px;
        height: 36px;

        display: grid;
        place-items: center;

        flex-shrink: 0;

        border-radius: 50%;

        background: var(--qf-red-light);
        color: var(--qf-red);

        font-size: 14px;
        font-weight: 700;
    }

    .sidebar-user-info {
        display: flex;
        flex-direction: column;
        gap: 3px;

        min-width: 0;
    }

    .sidebar-user-info strong {
        font-size: 12px;
        font-weight: 650;

        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .sidebar-user-info .sidebar-role {
        font-size: 10px;
    }

    .sidebar-user-info .sidebar-assignment {
        font-size: 10px;
        overflow-wrap: anywhere;
    }

    /* SIGN OUT BUTTON */

    .sidebar-bottom form {
        margin: 0;
        flex-shrink: 0;
    }

    .sidebar-bottom .logout-button {
        width: 100%;
        min-height: 39px;

        display: flex;
        align-items: center;
        justify-content: flex-start;
        gap: 11px;

        padding: 9px 11px;

        box-sizing: border-box;

        border: none;
        border-radius: 9px;

        background: var(--qf-red-soft);
        color: var(--qf-red);

        font-size: 13px;
        font-weight: 600;

        cursor: pointer;

        transition:
            background 0.2s ease,
            color 0.2s ease;
    }

    .sidebar-bottom .logout-button:hover {
        background: var(--qf-red-light);
        color: var(--qf-red-dark);
    }

    .sidebar-bottom .logout-button iconify-icon {
        font-size: 19px;
        flex-shrink: 0;
    }

    /* MAIN CONTENT */

    .main-area {
        margin-left: var(--sidebar-width);
        min-width: 0;
    }
}

/* =====================================================
   SHORT LAPTOP SCREENS
   11-inch laptops and shorter displays
===================================================== */

@media (min-width: 769px) and (max-height: 800px) {

    .sidebar {
        padding-top: 10px;
        padding-bottom: 9px;
    }

    .sidebar > .brand {
        padding-bottom: 9px;
    }

    .sidebar > .sidebar-section-label {
        padding-top: 7px;
        padding-bottom: 6px;
    }

    .sidebar > .sidebar-navigation {
        gap: 2px;
    }

    .sidebar-navigation .nav-link {
        min-height: 35px;
        padding-top: 6px;
        padding-bottom: 6px;
        font-size: 12px;
    }

    .sidebar-navigation .nav-icon {
        width: 17px;
        height: 17px;
        font-size: 17px;
    }

    .sidebar-bottom {
        gap: 6px;
        padding-top: 8px;
    }

    .sidebar-user .avatar {
        width: 32px;
        height: 32px;
    }

    .sidebar-bottom .logout-button {
        min-height: 35px;
        padding: 7px 10px;
    }
}

/* =====================================================
   VERY SHORT SCREENS
===================================================== */

@media (min-width: 769px) and (max-height: 600px) {

    .sidebar {
        padding-top: 7px;
        padding-bottom: 7px;
    }

    .sidebar > .brand {
        padding-bottom: 5px;
    }

    .sidebar > .brand img {
        width: 30px;
        height: 30px;
    }

    .sidebar > .brand strong {
        font-size: 14px;
    }

    .sidebar > .brand small {
        font-size: 10px;
    }

    .sidebar > .sidebar-section-label {
        padding-top: 5px;
        padding-bottom: 4px;
    }

    .sidebar-navigation .nav-link {
        min-height: 32px;
        padding-top: 5px;
        padding-bottom: 5px;
    }

    .sidebar-bottom {
        padding-top: 6px;
        gap: 4px;
    }

    .sidebar-user .avatar {
        width: 29px;
        height: 29px;
    }

    .sidebar-bottom .logout-button {
        min-height: 32px;
        padding: 5px 9px;
    }
}

/* =====================================================
   MOBILE
   Preserve existing mobile drawer
===================================================== */

@media (max-width: 768px) {

    .sidebar {
        display: none;
    }

    .main-area {
        width: 100%;
        margin-left: 0;
    }
}

</style>
<!-- DESKTOP SIDEBAR -->
<aside class="sidebar" id="desktopSidebar">

    <a href="dashboard.php" class="brand">

        <img
            src="assets/img/icon.png"
            alt="QueueFlow logo"
        >

        <span>
            <strong>QueueFlow</strong>
            <small>RMMC-MI Finance</small>
        </span>

    </a>

    <div class="sidebar-section-label">
        MAIN MENU
    </div>

    <nav class="sidebar-navigation" aria-label="Main navigation">

        <?php
        qflow_render_navigation(
            $navigation,
            $roleName,
            $currentPage
        );
        ?>

    </nav>

    <div class="sidebar-bottom">

        <?php
        qflow_render_sidebar_user(
            $userInitial,
            $fullName,
            $roleLabel,
            $serviceLabel
        );

        qflow_render_logout();
        ?>

    </div>

</aside>


<!-- MOBILE OVERLAY -->
<div
    class="mobile-sidebar-overlay"
    id="mobileSidebarOverlay"
    hidden
></div>


<!-- MOBILE SIDEBAR -->
<aside
    class="mobile-sidebar"
    id="mobileSidebar"
    aria-label="Mobile navigation"
    aria-hidden="true"
    inert
>

    <div class="mobile-sidebar-header">

        <a href="dashboard.php" class="brand">

            <img
                src="assets/img/icon.png"
                alt="QueueFlow logo"
            >

            <span>
                <strong>QueueFlow</strong>
                <small>RMMC-MI Finance</small>
            </span>

        </a>

        <button
            type="button"
            class="mobile-sidebar-close"
            id="mobileSidebarClose"
            aria-label="Close navigation"
        >
            &times;
        </button>

    </div>

    <div class="sidebar-section-label">
        MAIN MENU
    </div>

    <nav class="sidebar-navigation" aria-label="Mobile navigation">

        <?php
        qflow_render_navigation(
            $navigation,
            $roleName,
            $currentPage,
            'nav-link mobile-drawer-link'
        );
        ?>

    </nav>

    <div class="sidebar-bottom">

        <?php
        qflow_render_sidebar_user(
            $userInitial,
            $fullName,
            $roleLabel,
            $serviceLabel
        );

        qflow_render_logout();
        ?>

    </div>

</aside>


<!-- MAIN CONTENT -->
<div class="main-area">

    <!-- MOBILE TOP BAR -->
    <header class="mobile-topbar">

        <button
            type="button"
            class="mobile-menu-toggle"
            id="mobileMenuToggle"
            aria-label="Open navigation"
            aria-controls="mobileSidebar"
            aria-expanded="false"
        >

            <span></span>
            <span></span>
            <span></span>

        </button>

        <a href="dashboard.php" class="mobile-topbar-title">
            QUEUEFLOW
        </a>

        <a
            href="profile.php"
            class="mobile-topbar-avatar"
            aria-label="My profile"
        >
            <?= qflow_escape($userInitial) ?>
        </a>

    </header>