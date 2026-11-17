
<?php

require_once dirname(__DIR__) . '/includes/functions.php';

qflow_start_session();
qflow_require_login();

$pdo = require dirname(__DIR__) . '/config/database.php';

$userId = (int) $_SESSION['user_id'];

$userStatement = $pdo->prepare(
    'SELECT
        u.user_id,
        u.account_id,
        u.first_name,
        u.last_name,
        u.email,
        u.account_status,
        u.created_at,
        r.role_name
     FROM users u
     INNER JOIN roles r ON r.role_id = u.role_id
     WHERE u.user_id = :user_id
     LIMIT 1'
);

$userStatement->execute(['user_id' => $userId]);
$user = $userStatement->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['account_status'] !== 'active') {
    $_SESSION = [];
    session_destroy();

    header('Location: login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| PROFILE INFORMATION
|--------------------------------------------------------------------------
*/

$firstName = (string) ($user['first_name'] ?? '');
$lastName = (string) ($user['last_name'] ?? '');

$fullName = trim($firstName . ' ' . $lastName);

$initials = strtoupper(
    substr($firstName, 0, 1) .
    substr($lastName, 0, 1)
);

if ($initials === '') {
    $initials = 'U';
}

$role = strtolower(trim((string) $user['role_name']));

/*
|--------------------------------------------------------------------------
| ROLE DISPLAY
|--------------------------------------------------------------------------
*/

$roleConfig = [
    'student' => [
        'label' => 'Student',
        'description' => 'Student Account',
        'class' => 'student',
        'icon' => '🎓',
        'id_label' => 'Student ID',
    ],

    'staff' => [
        'label' => 'Staff',
        'description' => 'Finance Office Staff',
        'class' => 'staff',
        'icon' => '👤',
        'id_label' => 'Staff ID',
    ],

    'administrator' => [
        'label' => 'Administrator',
        'description' => 'System Administrator',
        'class' => 'administrator',
        'icon' => '🛡',
        'id_label' => 'Administrator ID',
    ],
];

$roleDisplay = $roleConfig[$role] ?? [
    'label' => ucfirst($role),
    'description' => 'QueueFlow Account',
    'class' => 'default',
    'icon' => '👤',
    'id_label' => 'Account ID',
];

$accountId = $user['account_id'] ?: 'Not assigned';
$email = $user['email'] ?: 'Not provided';

$createdAt = !empty($user['created_at'])
    ? date('F d, Y', strtotime($user['created_at']))
    : 'Not available';

$accountStatus = ucfirst((string) $user['account_status']);

$pageTitle = 'My Profile';

require dirname(__DIR__) . '/includes/header.php';
require dirname(__DIR__) . '/includes/sidebar.php';

?>

<style>

/* =========================================================
   PROFILE PAGE — MATCH FINANCE SERVICES LAYOUT
========================================================= */

.main-content {
    width: 100%;
    max-width: none;
    margin: 0;
    padding: 20px 24px 24px;
    box-sizing: border-box;
}

.profile-page,
.profile-page *,
.profile-page *::before,
.profile-page *::after {
    box-sizing: border-box;
}

.profile-page {
    width: 100%;
    max-width: none;
    margin: 0;
    padding-bottom: 30px;
}

/* =========================================================
   PAGE INTRODUCTION
========================================================= */

.profile-page .profile-intro {
    width: 100%;
    margin: 0 0 20px;
}

.profile-page .profile-intro .eyebrow {
    margin: 0 0 7px;
    color: #a71930;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 1.5px;
    text-transform: uppercase;
}

.profile-page .profile-intro h1 {
    margin: 0;
    color: #1c293b;
    font-size: clamp(26px, 3vw, 34px);
    font-weight: 800;
}

.profile-page .profile-intro p {
    margin: 7px 0 0;
    color: #737e8d;
    font-size: 14px;
    line-height: 1.6;
}

/* =========================================================
   PROFILE HERO
========================================================= */

.profile-page .profile-hero {
    position: relative;
    width: 100%;
    overflow: hidden;
    margin-bottom: 22px;
    background: #ffffff;
    border: 1px solid #e3e7ed;
    border-radius: 14px;
    box-shadow: 0 4px 18px rgba(25, 39, 60, 0.05);
}

.profile-page .profile-cover {
    position: relative;
    height: 145px;
    overflow: hidden;
    background: linear-gradient(
        115deg,
        #781225 0%,
        #a71930 55%,
        #c43b4f 100%
    );
}

.profile-page .profile-cover::before,
.profile-page .profile-cover::after {
    content: "";
    position: absolute;
    border: 1px solid rgba(255,255,255,.13);
    border-radius: 50%;
}

.profile-page .profile-cover::before {
    width: 280px;
    height: 280px;
    right: 9%;
    top: -180px;
}

.profile-page .profile-cover::after {
    width: 220px;
    height: 220px;
    right: -20px;
    top: 40px;
}

.profile-page .cover-label {
    position: absolute;
    top: 20px;
    right: 25px;
    color: rgba(255,255,255,.85);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 2px;
}

.profile-page .profile-hero-content {
    position: relative;
    display: flex;
    align-items: center;
    gap: 22px;
    width: 100%;
    padding: 0 30px 27px;
    margin-top: -48px;
}

.profile-page .profile-avatar {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 105px;
    height: 105px;
    border: 6px solid #ffffff;
    border-radius: 50%;
    background: #f2e7e9;
    color: #a71930;
    font-size: 31px;
    font-weight: 900;
    box-shadow: 0 3px 12px rgba(0,0,0,.12);
}

.profile-page .profile-identity {
    flex: 1;
    min-width: 0;
    padding-top: 48px;
}

.profile-page .profile-identity h2 {
    margin: 0;
    color: #1c293b;
    font-size: clamp(21px, 2.5vw, 27px);
    font-weight: 800;
    overflow-wrap: anywhere;
}

.profile-page .profile-subtitle {
    margin: 5px 0 11px;
    color: #737e8d;
    font-size: 13px;
}

.profile-page .profile-badges {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
}

.profile-page .role-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 12px;
    border-radius: 5px;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: .5px;
    text-transform: uppercase;
}

.profile-page .role-badge.student {
    background: #eaf2ff;
    color: #2458a2;
}

.profile-page .role-badge.staff {
    background: #e9f6ee;
    color: #187343;
}

.profile-page .role-badge.administrator {
    background: #f2eafa;
    color: #7040a0;
}

.profile-page .role-badge.default {
    background: #edf0f4;
    color: #596579;
}

.profile-page .active-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 11px;
    border-radius: 5px;
    background: #e9f6ee;
    color: #187343;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
}

.profile-page .active-badge::before {
    content: "";
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #26965a;
}

/* =========================================================
   PROFILE INFORMATION LAYOUT
========================================================= */

.profile-page .profile-content {
    display: grid;
    grid-template-columns: minmax(0, 1.6fr) minmax(260px, .8fr);
    align-items: start;
    gap: 20px;
    width: 100%;
}

.profile-page .profile-panel {
    width: 100%;
    min-width: 0;
    overflow: hidden;
    background: #ffffff;
    border: 1px solid #e3e7ed;
    border-radius: 12px;
    box-shadow: 0 3px 12px rgba(25, 39, 60, 0.04);
}

.profile-page .panel-header {
    padding: 19px 22px;
    border-bottom: 1px solid #edf0f4;
}

.profile-page .panel-header h2 {
    margin: 0;
    color: #1c293b;
    font-size: 17px;
    font-weight: 800;
}

.profile-page .panel-header p {
    margin: 5px 0 0;
    color: #7b8491;
    font-size: 12px;
    line-height: 1.5;
}

/* =========================================================
   PERSONAL INFORMATION
========================================================= */

.profile-page .profile-info-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0;
    width: 100%;
    padding: 8px 22px;
}

.profile-page .info-item {
    min-width: 0;
    padding: 17px 12px 17px 0;
    border-bottom: 1px solid #edf0f4;
}

.profile-page .info-item:nth-last-child(-n+2) {
    border-bottom: none;
}

.profile-page .info-label {
    display: block;
    margin-bottom: 7px;
    color: #7b8491;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .5px;
    text-transform: uppercase;
}

.profile-page .info-value {
    display: block;
    color: #263449;
    font-size: 14px;
    font-weight: 700;
    overflow-wrap: anywhere;
}

/* =========================================================
   ACCOUNT OVERVIEW
========================================================= */

.profile-page .account-overview {
    padding-bottom: 7px;
}

.profile-page .overview-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 16px 19px;
    border-bottom: 1px solid #edf0f4;
}

.profile-page .overview-item:last-child {
    border-bottom: none;
}

.profile-page .overview-icon {
    display: grid;
    flex-shrink: 0;
    width: 36px;
    height: 36px;
    place-items: center;
    border-radius: 8px;
    background: #f7e9ec;
    color: #a71930;
    font-size: 16px;
    font-weight: 800;
}

.profile-page .overview-text {
    min-width: 0;
}

.profile-page .overview-text span {
    display: block;
    margin-bottom: 5px;
    color: #7b8491;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
}

.profile-page .overview-text strong {
    display: block;
    color: #263449;
    font-size: 13px;
    overflow-wrap: anywhere;
}

/* =========================================================
   HELP SECTION
========================================================= */

.profile-page .profile-help {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    width: 100%;
    margin-top: 20px;
    padding: 19px 22px;
    border: 1px solid #e7d9dc;
    border-left: 4px solid #a71930;
    border-radius: 8px;
    background: #fffafb;
}

.profile-page .help-icon {
    display: grid;
    flex-shrink: 0;
    width: 34px;
    height: 34px;
    place-items: center;
    border-radius: 50%;
    background: #f7e9ec;
    color: #a71930;
    font-weight: 900;
}

.profile-page .profile-help h3 {
    margin: 0 0 5px;
    color: #263449;
    font-size: 14px;
    font-weight: 800;
}

.profile-page .profile-help p {
    margin: 0;
    color: #737e8d;
    font-size: 12px;
    line-height: 1.6;
}

/* =========================================================
   FOOTER
========================================================= */

.profile-page .profile-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    width: 100%;
    margin-top: 25px;
    color: #89919d;
    font-size: 11px;
}

/* =========================================================
   TABLET
========================================================= */

@media (max-width: 1050px) {

    .main-content {
        padding: 18px 20px 22px;
    }

    .profile-page .profile-content {
        grid-template-columns: minmax(0, 1fr);
    }

}

/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 640px) {

    .main-content {
        padding: 15px 14px 18px;
    }

    .profile-page {
        padding-bottom: 20px;
    }

    .profile-page .profile-intro {
        margin-bottom: 16px;
    }

    .profile-page .profile-intro h1 {
        font-size: 26px;
    }

    .profile-page .profile-intro p {
        font-size: 13px;
    }

    .profile-page .profile-cover {
        height: 110px;
    }

    .profile-page .cover-label {
        top: 16px;
        right: 16px;
        font-size: 9px;
    }

    .profile-page .profile-hero-content {
        align-items: flex-start;
        flex-direction: column;
        gap: 10px;
        padding: 0 20px 22px;
        margin-top: -42px;
    }

    .profile-page .profile-avatar {
        width: 88px;
        height: 88px;
        font-size: 26px;
    }

    .profile-page .profile-identity {
        width: 100%;
        padding-top: 0;
    }

    .profile-page .profile-identity h2 {
        font-size: 22px;
    }

    .profile-page .profile-info-grid {
        grid-template-columns: minmax(0, 1fr);
        padding: 8px 17px;
    }

    .profile-page .info-item {
        padding: 15px 0;
        border-bottom: 1px solid #edf0f4;
    }

    .profile-page .info-item:nth-last-child(-n+2) {
        border-bottom: 1px solid #edf0f4;
    }

    .profile-page .info-item:last-child {
        border-bottom: none;
    }

    .profile-page .panel-header {
        padding: 16px 17px;
    }

    .profile-page .overview-item {
        padding: 14px 16px;
    }

    .profile-page .profile-help {
        gap: 11px;
        padding: 15px;
    }

    .profile-page .profile-footer {
        align-items: flex-start;
        flex-direction: column;
    }

}

/* =========================================================
   REDUCED MOTION
========================================================= */

@media (prefers-reduced-motion: reduce) {

    .profile-page *,
    .profile-page *::before,
    .profile-page *::after {
        transition: none !important;
        animation: none !important;
    }

}

</style>

<main class="main-content">

    <div class="profile-page">

        <!-- PAGE HEADING -->

        <header class="profile-intro">

            <p class="eyebrow">ACCOUNT SETTINGS</p>

            <h1>My Profile</h1>

            <p>
                View your personal information and QueueFlow account details.
            </p>

        </header>

        <!-- PROFILE HERO -->

        <section class="profile-hero">

            <div class="profile-cover">

                <span class="cover-label">
                    QUEUEFLOW ACCOUNT
                </span>

            </div>

            <div class="profile-hero-content">

                <div class="profile-avatar">
                    <?= qflow_escape($initials) ?>
                </div>

                <div class="profile-identity">

                    <h2>
                        <?= qflow_escape($fullName ?: 'QueueFlow User') ?>
                    </h2>

                    <p class="profile-subtitle">
                        <?= qflow_escape($roleDisplay['description']) ?>
                    </p>

                    <div class="profile-badges">

                        <span class="role-badge <?= qflow_escape($roleDisplay['class']) ?>">

                            <?= qflow_escape($roleDisplay['icon']) ?>

                            <?= qflow_escape($roleDisplay['label']) ?>

                        </span>

                        <span class="active-badge">
                            <?= qflow_escape($accountStatus) ?> Account
                        </span>

                    </div>

                </div>

            </div>

        </section>

        <!-- PROFILE DETAILS -->

        <div class="profile-content">

            <!-- PERSONAL INFORMATION -->

            <section class="profile-panel">

                <div class="panel-header">

                    <h2>Personal Information</h2>

                    <p>
                        Your registered personal details.
                    </p>

                </div>

                <div class="profile-info-grid">

                    <div class="info-item">

                        <span class="info-label">First Name</span>

                        <strong class="info-value">
                            <?= qflow_escape($firstName ?: 'Not provided') ?>
                        </strong>

                    </div>

                    <div class="info-item">

                        <span class="info-label">Last Name</span>

                        <strong class="info-value">
                            <?= qflow_escape($lastName ?: 'Not provided') ?>
                        </strong>

                    </div>

                    <div class="info-item">

                        <span class="info-label">Email Address</span>

                        <strong class="info-value">
                            <?= qflow_escape($email) ?>
                        </strong>

                    </div>

                    <div class="info-item">

                        <span class="info-label">
                            <?= qflow_escape($roleDisplay['id_label']) ?>
                        </span>

                        <strong class="info-value">
                            <?= qflow_escape($accountId) ?>
                        </strong>

                    </div>

                    <div class="info-item">

                        <span class="info-label">Account Role</span>

                        <strong class="info-value">
                            <?= qflow_escape($roleDisplay['label']) ?>
                        </strong>

                    </div>

                    <div class="info-item">

                        <span class="info-label">Registered On</span>

                        <strong class="info-value">
                            <?= qflow_escape($createdAt) ?>
                        </strong>

                    </div>

                </div>

            </section>

            <!-- ACCOUNT OVERVIEW -->

            <aside class="profile-panel">

                <div class="panel-header">

                    <h2>Account Overview</h2>

                    <p>
                        Your QueueFlow account summary.
                    </p>

                </div>

                <div class="account-overview">

                    <div class="overview-item">

                        <div class="overview-icon">
                            ID
                        </div>

                        <div class="overview-text">

                            <span>Account Number</span>

                            <strong>
                                <?= qflow_escape($accountId) ?>
                            </strong>

                        </div>

                    </div>

                    <div class="overview-item">

                        <div class="overview-icon">
                            R
                        </div>

                        <div class="overview-text">

                            <span>Account Role</span>

                            <strong>
                                <?= qflow_escape($roleDisplay['label']) ?>
                            </strong>

                        </div>

                    </div>

                    <div class="overview-item">

                        <div class="overview-icon">
                            ✓
                        </div>

                        <div class="overview-text">

                            <span>Account Status</span>

                            <strong>
                                <?= qflow_escape($accountStatus) ?>
                            </strong>

                        </div>

                    </div>

                    <div class="overview-item">

                        <div class="overview-icon">
                            Q
                        </div>

                        <div class="overview-text">

                            <span>Platform</span>

                            <strong>QueueFlow</strong>

                        </div>

                    </div>

                </div>

            </aside>

        </div>

        <!-- HELP -->

        <section class="profile-help">

            <div class="help-icon">i</div>

            <div>

                <h3>Need to update your information?</h3>

                <p>
                    If your name, email address, or account details need correction,
                    please contact the Finance Office Administrator for assistance.
                </p>

            </div>

        </section>

        <!-- FOOTER -->

        <footer class="profile-footer">

            <span>
                QueueFlow — RMMC-MI Finance Queue Management System
            </span>

            <span>
                <?= date('Y') ?>
            </span>

        </footer>

    </div>

</main>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
