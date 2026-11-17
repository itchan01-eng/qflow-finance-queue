<?php

require_once '../includes/functions.php';

qflow_start_session();
qflow_require_login();

$pdo = require '../config/database.php';

$userId = (int) $_SESSION['user_id'];
$firstName = $_SESSION['first_name'] ?? 'User';
$lastName = $_SESSION['last_name'] ?? '';
$roleName = $_SESSION['role_name'] ?? 'Student';

if (!in_array($roleName, ['Student', 'Staff', 'Administrator'], true)) {
    http_response_code(403);
    exit('Access denied.');
}


// Staff availability belongs on the dashboard, not the queue-management page.
$availabilityMessage = '';
$availabilityError = '';
if ($roleName === 'Staff' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_my_availability'])) {
    try {
        if (!qflow_verify_csrf($_POST['csrf_token'] ?? null)) throw new RuntimeException('Your session expired. Refresh and try again.');
        $newAvailability = (string)($_POST['availability_status'] ?? '');
        if (!in_array($newAvailability, ['ready', 'unavailable'], true)) throw new RuntimeException('Choose Available or Unavailable.');
        $pdo->beginTransaction();
        $assignedCounter = $pdo->prepare("SELECT cr.counter_id, cr.availability_status FROM counter_readiness cr JOIN service_counters sc ON sc.counter_id=cr.counter_id JOIN staff_service_assignments a ON a.user_id=:assigned_user AND a.service_id=sc.service_id WHERE cr.staff_user_id=:counter_owner AND sc.is_active=1 LIMIT 1 FOR UPDATE");
        $assignedCounter->execute(['assigned_user'=>$userId,'counter_owner'=>$userId]);
        $myCounter = $assignedCounter->fetch(PDO::FETCH_ASSOC);
        if (!$myCounter) throw new RuntimeException('No counter is assigned to your account. Please contact the administrator.');
        if ($myCounter['availability_status'] === 'busy') throw new RuntimeException('You are currently serving a ticket. Finish it before changing availability.');
        $saveAvailability = $pdo->prepare("UPDATE counter_readiness SET availability_status=:status WHERE counter_id=:counter AND staff_user_id=:staff");
        $saveAvailability->execute(['status'=>$newAvailability,'counter'=>$myCounter['counter_id'],'staff'=>$userId]);
        $pdo->commit();
        header('Location: dashboard.php?availability_saved=1'); exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $availabilityError = $e instanceof RuntimeException ? $e->getMessage() : 'Could not update availability. Make sure counter-readiness.sql has been imported.';
    }
}
if (isset($_GET['availability_saved'])) $availabilityMessage = 'Your availability has been updated.';

// Student-facing counter directory: show the counter, assigned staff first name,
// service, and current availability so students know where to go for help.
$studentCounters = [];
if ($roleName === 'Student') {
    $studentCounterQuery = $pdo->query(
        "SELECT sc.counter_id, sc.counter_name, s.service_name,
                cr.availability_status, u.first_name AS staff_first_name
         FROM service_counters sc
         INNER JOIN services s ON s.service_id = sc.service_id
         LEFT JOIN counter_readiness cr ON cr.counter_id = sc.counter_id
         LEFT JOIN users u ON u.user_id = cr.staff_user_id
         WHERE sc.is_active = 1
           AND s.is_active = 1
         ORDER BY s.service_name, sc.counter_name"
    );
    $studentCounters = $studentCounterQuery->fetchAll(PDO::FETCH_ASSOC);
}
$myCounterStatus = null;
$myCounterName = null;
if ($roleName === 'Staff') {
    $myCounterQuery = $pdo->prepare("SELECT sc.counter_name, s.service_name, cr.availability_status FROM counter_readiness cr JOIN service_counters sc ON sc.counter_id=cr.counter_id JOIN services s ON s.service_id=sc.service_id WHERE cr.staff_user_id=:staff LIMIT 1");
    $myCounterQuery->execute(['staff'=>$userId]);
    $myCounter = $myCounterQuery->fetch(PDO::FETCH_ASSOC);
    if ($myCounter) { $myCounterName = $myCounter['counter_name']; $myCounterStatus = $myCounter['availability_status']; $myCounterService = $myCounter['service_name']; }
    else { $myCounterService = null; }
}

require_once '../includes/dashboard_req.php';

$pageTitle = 'Dashboard';

require_once '../includes/header.php';
require_once '../includes/sidebar.php';

?>

<style>
.student-counter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 12px;
    align-items: stretch;
}
.student-counter-card {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 5px;
    padding: 14px;
    min-height: 0;
    border-radius: 12px;
}
.student-counter-card .service-card-icon {
    width: 34px;
    height: 34px;
    min-height: 34px;
    margin-bottom: 2px;
    font-size: 1rem;
}
.student-counter-service { margin: 0; color: var(--text-muted, #667085); font-size: .78rem; }
.student-counter-card h3 { margin: 0; font-size: 1.05rem; line-height: 1.3; }
.student-counter-staff { margin: 0; font-size: .88rem; }
.student-counter-status { display: inline-flex; align-items: center; gap: 6px; margin-top: 3px; padding: 5px 8px; border-radius: 999px; font-size: .73rem; line-height: 1.25; font-weight: 600; }
.student-counter-status-dot { width: 7px; height: 7px; flex: 0 0 7px; border-radius: 50%; background: currentColor; }
.counter-status-available { color: #167647; background: #e8f7ee; }
.counter-status-busy { color: #925d08; background: #fff4d6; }
.counter-status-unavailable { color: #667085; background: #eef0f3; }
@media (max-width: 600px) {
    .student-counter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
    .student-counter-card { padding: 11px; }
    .student-counter-status { font-size: .68rem; }
}
</style>

<main class="main-content">
    <header class="page-header">
        <div>
            <h1>Dashboard</h1>
            <p class="page-description">
                Welcome back, <?php echo $firstName; ?>.
                Here's your finance queue overview.
            </p>
        </div>

        <div class="header-user">
            <span class="avatar">
                <?php echo strtoupper(substr($firstName, 0, 1)); ?>
            </span>
            <div>
                <strong>
                    <?php echo $firstName . ' ' . $lastName; ?>
                </strong>
                <small>
                    <?php
                        if ($normalizedRole === 'administrator') {
                            echo 'Administrator';
                        } elseif ($normalizedRole === 'staff') {
                            if (!empty($staffAssignments)) {
                                echo "Staff";
                            } else {
                                echo 'Staff';
                            }
                        } else {
                            echo $roleName; // Student
                        }
                    ?>
                </small>
            </div>
        </div>
    </header>

    <?php if ($roleName === "Student"){ ?>
    <section class="welcome-panel">
        <div>
            <span class="welcome-label">STUDENT PORTAL</span>
            <h2>Your finance transactions, made easier.</h2>
            <p>
                Choose a finance service, get your queue ticket,
                and check your queue status without repeatedly
                lining up at the office.
            </p>
        </div>

        <div class="welcome-icon" aria-hidden="true">
            <img src="assets/img/icon.png" alt="">
        </div>
    </section>

    

    <section class="section-block">
        <div class="section-heading">
            <div>
                <h2>My Queue</h2>
                <p>Check your current ticket status.</p>
            </div>
            <a href="my-queue.php" class="text-link">View queue history</a>
        </div>
        
        <?php if ($activeTicket){ ?>
        <div class="active-ticket-card">
            <div>
                <span class="muted-label">YOUR TICKET NUMBER</span>
                    <h3>
                        <?php echo qflow_escape(strtoupper(preg_replace('/[^A-Za-z]/', '', $activeTicket['service_name']) ?: 'Q')); ?>-<?php echo str_pad((string) $activeTicket['queue_number'], 3, '0', STR_PAD_LEFT); ?>
                    </h3>
                        <p><?php echo $activeTicket['service_name']; ?></p>
                    </div>

                    <div class="ticket-status">
                        <span class="status-dot"></span>
                        <?php echo $activeTicket['queue_name']; ?>
                    </div>

                    <a href="my-queue.php" class="button button-primary">
                        View Ticket
                    </a>
                </div>
            <?php } else { ?>
                <div class="empty-state">
                    <div class="empty-state-icon">Q</div>
                    <h3>No active queue ticket</h3>
                    <p>
                        You don't have an active ticket today.
                        Select a finance service to get started.
                    </p>
                    <a href="services.php" class="button button-primary">
                        Get a Queue Number
                    </a>
                </div>
            <?php } ?>
        </section>

        <section class="section-block student-counter-section">
        <div class="section-heading">
            <div>
                <h2>Who's Available to Help?</h2>
                <p>Check the counter and staff member before heading to the finance office.</p>
            </div>
        </div>

        <?php if ($studentCounters) { ?>
            <div class="service-grid student-counter-grid">
                <?php foreach ($studentCounters as $counter) {
                    $counterStatus = $counter['availability_status'] ?? 'unavailable';
                    $counterStatusLabel = match ($counterStatus) {
                        'ready' => 'Available now',
                        'busy' => 'Helping a student',
                        default => 'Currently unavailable',
                    };
                    $counterStatusClass = match ($counterStatus) {
                        'ready' => 'counter-status-available',
                        'busy' => 'counter-status-busy',
                        default => 'counter-status-unavailable',
                    };
                ?>
                    <article class="service-card student-counter-card">
                        <div class="service-card-icon" aria-hidden="true">⌂</div>
                        <p class="student-counter-service"><?php echo htmlspecialchars($counter['service_name'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <h3><?php echo htmlspecialchars($counter['counter_name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p class="student-counter-staff">
                            <strong>Staff:</strong>
                            <?php echo !empty($counter['staff_first_name'])
                                ? htmlspecialchars($counter['staff_first_name'], ENT_QUOTES, 'UTF-8')
                                : 'Staff not assigned'; ?>
                        </p>
                        <span class="student-counter-status <?php echo $counterStatusClass; ?>">
                            <span class="student-counter-status-dot" aria-hidden="true"></span>
                            <?php echo $counterStatusLabel; ?>
                        </span>
                    </article>
                <?php } ?>
            </div>
        <?php } else { ?>
            <div class="empty-state">
                <h3>No counters available right now</h3>
                <p>Please check again later or ask the finance office for assistance.</p>
            </div>
        <?php } ?>
    </section>

        <section class="section-block">
            <div class="section-heading">
                <div>
                    <h2>Finance Services</h2>
                    <p>Select the service you need to visit.</p>
                </div>
                <a href="services.php" class="text-link">View all services</a>
            </div>

            <?php if ($services){ ?>
                <div class="service-grid">
                    <?php foreach ($services as $service){ ?>
                        <article class="service-card">
                            <div class="service-card-icon">
                                <span>
                                    <?php echo strtoupper(substr($service['service_name'], 0, 1)); ?>
                                </span>
                            </div>

                            <h3><?php echo $service['service_name']; ?></h3>

                            <p>
                                <?php echo $service['description'] ?: 'Manage your transaction through the finance queue.'; ?>
                            </p>

                            <a href="queue.php?service_id=<?php echo $service['service_id']; ?>" class="service-card-link" > Select service <span aria-hidden="true">→</span> </a>
                            
                        </article>
                    <?php } ?>
                </div>
            <?php } else { ?>
                <div class="empty-state">
                    <h3>No available services</h3>
                    <p>Please contact the finance office administrator.</p>
                </div>
            <?php } ?>
        </section>

        

        <section class="section-block">
            <div class="section-heading">
                <div>
                    <h2>Quick Access</h2>
                    <p>Go directly to the page you need.</p>
                </div>
            </div>

            <div class="quick-link-grid">
                <a href="services.php" class="quick-link-card">
                    <span class="quick-link-icon">＋</span>
                    <span>
                        <strong>Get a Queue Number</strong>
                        <small>Choose Cashier or Receivables.</small>
                    </span>
                    <span class="quick-arrow">→</span>
                </a>

                <a href="my-queue.php" class="quick-link-card">
                    <span class="quick-link-icon">≡</span>
                    <span>
                        <strong>My Queue</strong>
                        <small>View your ticket and previous queues.</small>
                    </span>
                    <span class="quick-arrow">→</span>
                </a>

                <a href="notifications.php" class="quick-link-card">
                    <span class="quick-link-icon">♧</span>
                    <span>
                        <strong>Notifications</strong>
                        <small>Check queue updates and announcements.</small>
                    </span>
                    <span class="quick-arrow">→</span>
                </a>

                <a href="profile.php" class="quick-link-card">
                    <span class="quick-link-icon">◎</span>
                    <span>
                        <strong>My Profile</strong>
                        <small>View your account information.</small>
                    </span>
                    <span class="quick-arrow">→</span>
                </a>
            </div>
        </section>

    <?php } else { ?>
        <section class="stats-grid">
            <article class="stat-card">
                <span class="stat-label">Waiting</span>
                <strong><?php echo $stats['waiting']; ?></strong>
                <small>Students waiting</small>
            </article>

            <article class="stat-card">
                <span class="stat-label">Currently Serving</span>
                <strong><?php echo $stats['serving']; ?></strong>
                <small>Active transactions</small>
            </article>

            <article class="stat-card">
                <span class="stat-label">Completed Today</span>
                <strong><?php echo $stats['completed']; ?></strong>
                <small>Finished transactions</small>
            </article>

            <article class="stat-card">
                <span class="stat-label">Active Services</span>
                <strong><?php echo $stats['services']; ?></strong>
                <small>Available finance services</small>
            </article>
        </section>

    <?php if ($normalizedRole === "staff") { ?>

    <?php if ($availabilityMessage !== ''): ?><div class="counter-assignment-alert success"><?= htmlspecialchars($availabilityMessage) ?></div><?php endif; ?>
    <?php if ($availabilityError !== ''): ?><div class="counter-assignment-alert error"><?= htmlspecialchars($availabilityError) ?></div><?php endif; ?>
    <link rel="stylesheet" href="assets/css/counter-assignments.css">
    <section class="staff-availability-panel">
        <div><h2>My Counter Availability</h2>
        <p><?php if ($myCounterName): ?>Assigned counter: <strong><?= htmlspecialchars($myCounterName) ?></strong> · Current status: <strong><?= htmlspecialchars(($myCounterStatus === 'ready' ? 'Available' : ($myCounterStatus === 'busy' ? 'Serving a ticket' : 'Unavailable'))) ?></strong><?php else: ?>No counter is assigned to you yet. Please contact the administrator.<?php endif; ?></p></div>
        <?php if ($myCounterStatus && $myCounterStatus !== 'busy'): ?><form method="post" action="dashboard.php" class="staff-availability-form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(qflow_csrf_token()) ?>"><input type="hidden" name="set_my_availability" value="1"><button class="available-btn" name="availability_status" value="ready" type="submit" <?= $myCounterStatus === 'ready' ? 'disabled' : '' ?>>Available</button><button class="unavailable-btn" name="availability_status" value="unavailable" type="submit" <?= $myCounterStatus === 'unavailable' ? 'disabled' : '' ?>>Unavailable</button></form><?php endif; ?>
    </section>

    <section class="staff-welcome-panel">
        <div class="staff-welcome-content">
            <span class="staff-welcome-label">
                <?php echo strtoupper($roleName) . ' PORTAL'; ?>
            </span>

            <h2>Finance Queue Overview</h2>

            <p>
                Monitor today's queue activity and access
                the tools available to your account.
            </p>

            <div class="staff-assignments">
                <?php if ($myCounterName && $myCounterService) { ?>
                    <div class="staff-assignment-card">
                        <h3><?php echo htmlspecialchars($myCounterService); ?> Staff</h3>
                        <p><?php echo htmlspecialchars($myCounterName); ?></p>
                    </div>
                <?php } else { ?>
                    <div class="staff-assignment-card">
                        <h3>No Counter Assigned</h3>
                        <p>Please contact your administrator.</p>
                    </div>
                <?php } ?>
            </div>
        </div>

        <div class="staff-welcome-icon" aria-hidden="true">
            <img src="assets/img/icon.png" alt="">
        </div>
    </section>

<?php } elseif ($normalizedRole === "administrator") { ?>

    <section class="admin-welcome-panel">
        <div class="admin-welcome-content">
            <span class="admin-welcome-label">
                ADMINISTRATOR PORTAL
            </span>

            <h2>System Administration</h2>
            <p>
                Manage accounts and oversee the Finance QueueFlow system.
            </p>
        </div>

        <div class="admin-welcome-icon" aria-hidden="true">
            <img src="assets/img/icon.png" alt="">
        </div>
    </section>
    <?php } ?>
    
    <section class="section-block" style="margin-top: 20px;">
            <div class="section-heading">
                <div>
                    <h2>Finance Services</h2>
                    <p>Services currently configured in QueueFlow.</p>
                </div>
            </div>

            <div class="service-grid">
                <?php foreach ($services as $service){ ?>
                    <article class="service-card">
                        <div class="service-card-icon">
                            <span>
                                <?php echo strtoupper(substr($service['service_name'], 0, 1)); ?>
                            </span>
                        </div>
                        <h3><?php echo $service['service_name']; ?></h3>
                        <p>
                            <?php echo $service['description'] ?: 'Finance office service.'; ?>
                        </p>
                    </article>
                <?php } ?>
            </div>
        </section>

        <section class="section-block">
            <div class="section-heading">
                <div>
                    <h2>Management</h2>
                    <p>Access your available management pages.</p>
                </div>
            </div>

            <div class="quick-link-grid">
                <?php if ($normalizedRole === 'administrator'){ ?>
                    <a href="accounts.php" class="quick-link-card">
                        <span class="quick-link-icon">◎</span>
                        <span>
                            <strong>Account Management</strong>
                            <small>Create and manage student and staff accounts.</small>
                        </span>
                        <span class="quick-arrow">→</span>
                    </a>
                    <a href="admin-staff.php" class="quick-link-card">
        <span class="quick-link-icon">👥</span>
        <span>
            <strong>Staff Management</strong>
            <small>Add staff and manage service assignments.</small>
        </span>
        <span class="quick-arrow">→</span>
    </a>
                    <a href="counter-assignments.php" class="quick-link-card">
                        <span class="quick-link-icon">▦</span>
                        <span><strong>Counter Assignments</strong><small>Assign staff to the six finance counters.</small></span>
                        <span class="quick-arrow">→</span>
                    </a>
                <?php } ?>

                <a href="my-queue.php" class="quick-link-card">
                    <span class="quick-link-icon">≡</span>
                    <span>
                        <strong>Queue Overview</strong>
                        <small>Open the queue page.</small>
                    </span>
                    <span class="quick-arrow">→</span>
                </a>

                <a href="profile.php" class="quick-link-card">
                    <span class="quick-link-icon">◎</span>
                    <span>
                        <strong>My Profile</strong>
                        <small>View your account information.</small>
                    </span>
                    <span class="quick-arrow">→</span>
                </a>
            </div>
        </section>

    
    <?php } ?>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>