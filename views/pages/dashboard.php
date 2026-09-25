<?php
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}
if ($_SESSION['role'] !== 'admin') {
    header('Location: /login');
    exit;
}

require_once __DIR__ . '/../../config/database.php';

// ============================================
// USERS
// ============================================
$totalUsers = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$activeUsers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
$archivedUsers = max(0, $totalUsers - $activeUsers);

$facultyCount = (int) $pdo->query("SELECT COUNT(*) FROM employees WHERE category = 'faculty'")->fetchColumn();
$staffCount = (int) $pdo->query("SELECT COUNT(*) FROM employees WHERE category = 'staff'")->fetchColumn();

// ============================================
// LEAVES
// ============================================
$leaveStats = $pdo->query("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected,
        SUM(CASE WHEN status = 'pending' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS pending_new
    FROM leave_requests
")->fetch();

// ============================================
// PAYROLL
// ============================================
$workingDaysPerMonth = 22;
$savedTotal = 0;
$convertibleTotal = 0;

// ============================================
// CHART: Leave decisions by week (last 4 weeks)
// ============================================
$weekLabels = [];
$weekPending = [];
$weekApproved = [];
$weekRejected = [];

for ($i = 3; $i >= 0; $i--) {
    $start = date('Y-m-d', strtotime("-$i weeks monday this week"));
    $end = date('Y-m-d', strtotime("$start +6 days"));

    $weekLabels[] = 'W' . date('W', strtotime($start));

    $stmt = $pdo->prepare("
        SELECT 
            SUM(status = 'pending')  AS p,
            SUM(status = 'approved') AS a,
            SUM(status = 'rejected') AS r
        FROM leave_requests
        WHERE DATE(created_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$start, $end]);
    $row = $stmt->fetch() ?: ['p' => 0, 'a' => 0, 'r' => 0];

    $weekPending[] = (int) ($row['p'] ?? 0);
    $weekApproved[] = (int) ($row['a'] ?? 0);
    $weekRejected[] = (int) ($row['r'] ?? 0);
}

$stmt = $pdo->query("
    SELECT 
        e.basic_salary, e.leave_credits,
        COALESCE(lr.credits_used, 0) AS credits_used
    FROM employees e
    LEFT JOIN (
        SELECT employee_id, SUM(days_count) AS credits_used
        FROM leave_requests
        WHERE status = 'approved'
        GROUP BY employee_id
    ) lr ON lr.employee_id = e.id
    WHERE e.category IN ('faculty', 'staff')
");
foreach ($stmt->fetchAll() as $row) {
    $monthly = (float) $row['basic_salary'];
    $daily = $monthly > 0 ? $monthly / $workingDaysPerMonth : 0;
    $used = (float) $row['credits_used'];
    $rem = max(0, (float) $row['leave_credits'] - $used);

    $savedTotal += $daily * $used;
    $convertibleTotal += $daily * $rem;
}

// ============================================
// ACTIVITY
// ============================================
$activityToday = (int) $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$activityWeek = (int) $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();

// ============================================
// CHART: Leave submissions per month (last 6 months)
// ============================================
$leaveTrendLabels = [];
$leaveTrendValues = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $leaveTrendLabels[] = date('M', strtotime($month . '-01'));
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM leave_requests
        WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
    ");
    $stmt->execute([$month]);
    $leaveTrendValues[] = (int) $stmt->fetchColumn();
}

// ============================================
// CHART: Users by role
// ============================================
$stmt = $pdo->query("
    SELECT role, COUNT(*) AS count
    FROM users
    WHERE status = 'active'
    GROUP BY role
    ORDER BY count DESC
");
$usersByRole = $stmt->fetchAll();
$roleCounts = ['admin' => 0, 'hr' => 0, 'faculty' => 0, 'staff' => 0, 'payroll' => 0, 'timekeeper' => 0];
foreach ($usersByRole as $row) {
    if (isset($roleCounts[$row['role']])) {
        $roleCounts[$row['role']] = (int) $row['count'];
    }
}

// ============================================
// Recent activity
// ============================================
$recentActivity = $pdo->query("
    SELECT * FROM activity_logs
    ORDER BY created_at DESC
    LIMIT 5
")->fetchAll();

$actionStyles = [
    'create_user' => 'text-[#0F5E3D] bg-[#F1FDF6]',
    'update_user' => 'text-blue-600 bg-blue-50',
    'archive_user' => 'text-amber-600 bg-amber-50',
    'send_reset' => 'text-purple-600 bg-purple-50',
    'file_leave' => 'text-blue-600 bg-blue-50',
    'decide_leave' => 'text-[#0F5E3D] bg-[#F1FDF6]',
    'cancel_leave' => 'text-amber-600 bg-amber-50',
    'login' => 'text-emerald-600 bg-emerald-50',
    'logout' => 'text-slate-600 bg-slate-100',
];
$actionLabels = [
    'create_user' => 'Created User',
    'update_user' => 'Updated User',
    'archive_user' => 'Archived User',
    'send_reset' => 'Sent Reset',
    'file_leave' => 'Filed Leave',
    'decide_leave' => 'Decided Leave',
    'cancel_leave' => 'Cancelled Leave',
    'login' => 'Login',
    'logout' => 'Logout',
];

function timeAgo($timestamp)
{
    $diff = time() - strtotime($timestamp);
    if ($diff < 60)
        return 'Just now';
    if ($diff < 3600)
        return floor($diff / 60) . 'm ago';
    if ($diff < 86400)
        return floor($diff / 3600) . 'h ago';
    if ($diff < 604800)
        return floor($diff / 86400) . 'd ago';
    return date('M j, Y', strtotime($timestamp));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | DAMMC Admin</title>
    <link rel="icon" type="image/png" href="/assets/images/dammc-logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>

<body class="bg-gradient-to-br from-[#F1FDF6] via-[#eafaf1] to-[#dcf3e5] min-h-screen">

    <div class="flex min-h-screen">

        <div id="sidebarOverlay" onclick="closeSidebar()"
            class="fixed inset-0 bg-black/40 z-40 hidden md:hidden backdrop-blur-sm"></div>

        <?php
        $activePage = 'dashboard';
        require __DIR__ . '/../components/sidebar.php';
        ?>

        <div class="flex-1 md:ml-64 w-full min-w-0">

            <?php
            $pageTitle = 'Dashboard';
            $pageSubtitle = 'Welcome back, ' . ($_SESSION['first_name'] ?? 'Admin');
            $pageIcon = 'layout-dashboard';
            require __DIR__ . '/../components/topbar.php';
            ?>

            <main class="p-4 sm:p-6 lg:p-8 space-y-4 sm:space-y-6">

                <!-- ============================================ -->
                <!-- HERO ROW                                       -->
                <!-- ============================================ -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 sm:gap-4">

                    <!-- Pending leave queue (spans 2 columns) -->
                    <div
                        class="lg:col-span-2 animate-fade-in-up bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-5 sm:p-6 shadow-sm">
                        <div class="flex items-start justify-between gap-4 mb-4">
                            <div>
                                <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/50 font-semibold mb-1">
                                    Pending Leave Requests</p>
                                <div class="flex items-baseline gap-2">
                                    <span class="text-3xl sm:text-4xl font-bold text-amber-600">
                                        <?= (int) $leaveStats['pending'] ?>
                                    </span>
                                    <span class="text-sm text-[#2C3E50]/50">
                                        request<?= ((int) $leaveStats['pending'] === 1) ? '' : 's' ?> awaiting decision
                                    </span>
                                </div>
                            </div>
                            <div
                                class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center text-amber-600 shrink-0">
                                <i data-lucide="clock" class="w-5 h-5"></i>
                            </div>
                        </div>

                        <?php if ((int) $leaveStats['pending_new'] > 0): ?>
                            <div class="mb-4">
                                <div class="flex items-center justify-between text-[11px] text-[#2C3E50]/60 mb-1.5">
                                    <span><?= (int) $leaveStats['pending_new'] ?> new this week</span>
                                </div>
                                <div class="h-2 bg-[#F1FDF6] rounded-full overflow-hidden">
                                    <?php
                                    $weekPct = $leaveStats['pending'] > 0
                                        ? min(100, round(($leaveStats['pending_new'] / $leaveStats['pending']) * 100))
                                        : 0;
                                    ?>
                                    <div class="h-full bg-amber-500 rounded-full transition-all duration-500"
                                        style="width: <?= $weekPct ?>%"></div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="grid grid-cols-3 gap-3 pt-4 border-t border-[#E0E0E0]">
                            <div>
                                <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-0.5">
                                    Total</p>
                                <p class="text-sm font-bold text-[#2C3E50]"><?= (int) $leaveStats['total'] ?></p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-0.5">
                                    Approved</p>
                                <p class="text-sm font-bold text-[#0F5E3D]"><?= (int) $leaveStats['approved'] ?></p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-0.5">
                                    Rejected</p>
                                <p class="text-sm font-bold text-red-600"><?= (int) $leaveStats['rejected'] ?></p>
                            </div>
                        </div>

                        <!-- Weekly decisions chart -->
                        <div class="pt-4 mt-4 border-t border-[#E0E0E0]">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/50 font-semibold">Weekly
                                    Decisions</p>
                                <div class="flex items-center gap-3 text-[10px] text-[#2C3E50]/50">
                                    <span class="flex items-center gap-1">
                                        <span class="w-2 h-2 rounded-full bg-amber-500"></span> Pending
                                    </span>
                                    <span class="flex items-center gap-1">
                                        <span class="w-2 h-2 rounded-full bg-[#0F5E3D]"></span> Approved
                                    </span>
                                    <span class="flex items-center gap-1">
                                        <span class="w-2 h-2 rounded-full bg-red-500"></span> Rejected
                                    </span>
                                </div>
                            </div>
                            <div class="relative h-32">
                                <canvas id="weeklyDecisionsChart"></canvas>
                            </div>
                        </div>
                    </div>



                    <!-- Right column: two stacked cards -->
                    <div class="flex flex-col gap-3 sm:gap-4">

                        <!-- Payroll saved -->
                        <div
                            class="animate-fade-in-up-delay-1 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-5 sm:p-6 shadow-sm flex flex-col">
                            <div class="flex items-start justify-between gap-4 mb-4">
                                <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/50 font-semibold">Payroll
                                    Saved</p>
                                <div
                                    class="w-9 h-9 rounded-lg bg-[#F1FDF6] flex items-center justify-center text-[#0F5E3D] shrink-0">
                                    <i data-lucide="landmark" class="w-4 h-4"></i>
                                </div>
                            </div>
                            <p class="text-2xl sm:text-3xl font-bold text-[#0F5E3D] mb-2">
                                ₱<?= number_format($savedTotal, 2) ?>
                            </p>
                            <p class="text-[10px] text-[#2C3E50]/50 mb-3">from unpaid leave days</p>

                            <div class="pt-3 mt-auto border-t border-[#E0E0E0]">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-[#2C3E50]/60">Convertible Liability</span>
                                    <span class="text-xs font-semibold text-amber-600">
                                        ₱<?= number_format($convertibleTotal, 2) ?>
                                    </span>
                                </div>
                                <p class="text-[10px] text-[#2C3E50]/40 mt-0.5">remaining credits if cashed out</p>
                            </div>
                        </div>

                        <!-- Users overview -->
                        <div
                            class="animate-fade-in-up-delay-1 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-5 sm:p-6 shadow-sm flex flex-col">
                            <div class="flex items-start justify-between gap-4 mb-3">
                                <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/50 font-semibold">Total
                                    Users</p>
                                <div
                                    class="w-9 h-9 rounded-lg bg-[#F1FDF6] flex items-center justify-center text-[#0F5E3D] shrink-0">
                                    <i data-lucide="users" class="w-4 h-4"></i>
                                </div>
                            </div>
                            <p class="text-3xl font-bold text-[#2C3E50] mb-3"><?= $totalUsers ?></p>

                            <div class="grid grid-cols-2 gap-2 pt-3 border-t border-[#E0E0E0]">
                                <div>
                                    <p
                                        class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-0.5">
                                        Active</p>
                                    <p class="text-sm font-bold text-[#0F5E3D]"><?= $activeUsers ?></p>
                                </div>
                                <div>
                                    <p
                                        class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-0.5">
                                        Archived</p>
                                    <p class="text-sm font-bold text-slate-500"><?= $archivedUsers ?></p>
                                </div>
                                <div>
                                    <p
                                        class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-0.5">
                                        Faculty</p>
                                    <p class="text-sm font-bold text-[#2C3E50]"><?= $facultyCount ?></p>
                                </div>
                                <div>
                                    <p
                                        class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-0.5">
                                        Staff</p>
                                    <p class="text-sm font-bold text-[#2C3E50]"><?= $staffCount ?></p>
                                </div>
                            </div>
                        </div>

                    </div>

                </div>

                <!-- ============================================ -->
                <!-- CHARTS ROW                                     -->
                <!-- ============================================ -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 sm:gap-4">

                    <!-- Line: leave submissions per month -->
                    <div
                        class="lg:col-span-2 animate-fade-in-up-delay-2 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-5 shadow-sm">
                        <div class="flex items-start justify-between mb-3">
                            <div>
                                <h2 class="text-sm font-bold text-[#2C3E50]">Leave Submissions</h2>
                                <p class="text-[10px] text-[#2C3E50]/50 mt-0.5">Requests submitted per month · last 6
                                    months</p>
                            </div>
                            <div class="flex items-center gap-1.5 text-[10px] text-[#2C3E50]/60">
                                <span class="w-2 h-2 rounded-full bg-[#0F5E3D]"></span>
                                <span>Requests</span>
                            </div>
                        </div>
                        <div class="relative h-56">
                            <canvas id="leaveTrendChart"></canvas>
                        </div>
                    </div>

                    <!-- Donut: users by role -->
                    <div
                        class="animate-fade-in-up-delay-2 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-5 shadow-sm">
                        <div class="mb-3">
                            <h2 class="text-sm font-bold text-[#2C3E50]">Users by Role</h2>
                            <p class="text-[10px] text-[#2C3E50]/50 mt-0.5">Active accounts only</p>
                        </div>
                        <div class="relative h-40">
                            <canvas id="roleChart"></canvas>
                        </div>
                        <div class="grid grid-cols-3 gap-2 mt-4 pt-3 border-t border-[#E0E0E0]">
                            <?php
                            $roleMeta = [
                                'admin' => ['Admin', '#0F5E3D'],
                                'hr' => ['HR', '#3b82f6'],
                                'faculty' => ['Faculty', '#a855f7'],
                                'staff' => ['Staff', '#f59e0b'],
                                'payroll' => ['Payroll', '#ef4444'],
                                'timekeeper' => ['Timekeeper', '#64748b'],
                            ];
                            foreach ($roleMeta as $key => [$label, $color]):
                                $c = $roleCounts[$key] ?? 0;
                                ?>
                                <div class="text-center">
                                    <span class="inline-block w-2 h-2 rounded-full mb-1"
                                        style="background: <?= $color ?>"></span>
                                    <p class="text-[10px] text-[#2C3E50]/50 uppercase tracking-wider"><?= $label ?></p>
                                    <p class="text-xs font-bold text-[#2C3E50]"><?= $c ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </div>

                <!-- ============================================ -->
                <!-- QUICK STATS STRIP                              -->
                <!-- ============================================ -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <div
                        class="animate-fade-in-up-delay-2 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-4 shadow-sm">
                        <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-1">Total Users
                        </p>
                        <p class="text-xl font-bold text-[#2C3E50]"><?= $totalUsers ?></p>
                    </div>
                    <div
                        class="animate-fade-in-up-delay-2 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-4 shadow-sm">
                        <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-1">Active
                            Users</p>
                        <p class="text-xl font-bold text-[#0F5E3D]"><?= $activeUsers ?></p>
                    </div>
                    <div
                        class="animate-fade-in-up-delay-3 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-4 shadow-sm">
                        <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-1">Pending
                            Leaves</p>
                        <p class="text-xl font-bold text-amber-600"><?= (int) $leaveStats['pending'] ?></p>
                    </div>
                    <div
                        class="animate-fade-in-up-delay-3 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-4 shadow-sm">
                        <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-1">Payroll
                            Saved</p>
                        <p class="text-xl font-bold text-[#0F5E3D]">₱<?= number_format($savedTotal, 0) ?></p>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- ACTIVITY + QUICK ACTIONS                       -->
                <!-- ============================================ -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6">

                    <!-- Recent activity -->
                    <div
                        class="animate-fade-in-up-delay-3 lg:col-span-2 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-5 sm:p-6 shadow-sm">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h2 class="text-base sm:text-lg font-bold text-[#2C3E50]">Recent Activity</h2>
                                <p class="text-xs text-[#2C3E50]/50 mt-0.5">
                                    <?= $activityToday ?> today · <?= $activityWeek ?> this week
                                </p>
                            </div>
                            <a href="/activity-logs"
                                class="text-xs font-medium text-[#0F5E3D] hover:text-[#0a4a2f] transition">
                                View all →
                            </a>
                        </div>

                        <?php if (empty($recentActivity)): ?>
                            <div class="py-8 text-center">
                                <div
                                    class="w-12 h-12 rounded-full bg-[#F1FDF6] flex items-center justify-center mx-auto mb-3 text-[#0F5E3D]">
                                    <i data-lucide="history" class="w-5 h-5"></i>
                                </div>
                                <p class="text-sm text-[#2C3E50]/60">No activity yet</p>
                                <p class="text-xs text-[#2C3E50]/40 mt-1">Actions will appear here as they happen.</p>
                            </div>
                        <?php else: ?>
                            <div class="space-y-1">
                                <?php foreach ($recentActivity as $log):
                                    $actionClass = $actionStyles[$log['action']] ?? 'text-slate-600 bg-slate-100';
                                    $actionLabel = $actionLabels[$log['action']] ?? ucfirst(str_replace('_', ' ', $log['action']));
                                    $initial = strtoupper(substr($log['username'] ?? 'S', 0, 1));
                                    ?>
                                    <div class="flex items-start gap-3 p-3 rounded-lg hover:bg-[#F1FDF6]/60 transition">
                                        <div
                                            class="w-8 h-8 rounded-full bg-[#0F5E3D] text-white flex items-center justify-center font-semibold text-[10px] shrink-0">
                                            <?= $initial ?>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex flex-wrap items-center gap-2 mb-0.5">
                                                <span class="text-sm font-medium text-[#2C3E50] truncate">
                                                    <?= htmlspecialchars($log['username'] ?? 'System') ?>
                                                </span>
                                                <span
                                                    class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-medium <?= $actionClass ?>">
                                                    <?= $actionLabel ?>
                                                </span>
                                            </div>
                                            <p class="text-xs text-[#2C3E50]/60 truncate">
                                                <?= htmlspecialchars($log['description'] ?? '—') ?>
                                            </p>
                                            <p class="text-[10px] text-[#2C3E50]/40 mt-0.5">
                                                <?= timeAgo($log['created_at']) ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Quick actions + system status -->
                    <div
                        class="animate-fade-in-up-delay-3 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-5 sm:p-6 shadow-sm">
                        <h2 class="text-base sm:text-lg font-bold text-[#2C3E50] mb-4">Quick Actions</h2>

                        <div class="space-y-3">
                            <a href="/users"
                                class="w-full flex items-center justify-center gap-2 bg-[#0F5E3D] hover:bg-[#0a4a2f] active:scale-[0.99] text-white font-medium py-2.5 rounded-lg transition text-sm shadow-sm hover:shadow-md">
                                <i data-lucide="users" class="w-4 h-4"></i>
                                Manage Users
                            </a>
                            <a href="/leaves"
                                class="w-full flex items-center justify-center gap-2 border border-[#0F5E3D] text-[#0F5E3D] hover:bg-[#F1FDF6] active:scale-[0.99] font-medium py-2.5 rounded-lg transition text-sm">
                                <i data-lucide="calendar-days" class="w-4 h-4"></i>
                                Review Leaves
                            </a>
                            <a href="/payroll"
                                class="w-full flex items-center justify-center gap-2 border border-[#0F5E3D] text-[#0F5E3D] hover:bg-[#F1FDF6] active:scale-[0.99] font-medium py-2.5 rounded-lg transition text-sm">
                                <i data-lucide="wallet" class="w-4 h-4"></i>
                                Payroll Overview
                            </a>
                            <a href="/activity-logs"
                                class="w-full flex items-center justify-center gap-2 border border-[#E0E0E0] text-[#2C3E50] hover:bg-gray-50 active:scale-[0.99] font-medium py-2.5 rounded-lg transition text-sm">
                                <i data-lucide="history" class="w-4 h-4"></i>
                                Activity Logs
                            </a>
                        </div>

                        <div class="mt-6 pt-4 border-t border-[#E0E0E0]">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-medium text-[#2C3E50]">System Status</p>
                                    <p class="text-[10px] text-[#2C3E50]/40 mt-0.5">All systems operational</p>
                                </div>
                                <span class="relative flex h-2.5 w-2.5">
                                    <span
                                        class="animate-ping absolute inline-flex h-full w-full rounded-full bg-[#1E8449] opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-[#1E8449]"></span>
                                </span>
                            </div>
                        </div>
                    </div>

                </div>

            </main>
        </div>
    </div>

    <?php require_once __DIR__ . '/../components/logout_modal.php'; ?>

    <script>
        window.DASHBOARD_DATA = {
            leaveTrend: {
                labels: <?= json_encode($leaveTrendLabels) ?>,
                values: <?= json_encode($leaveTrendValues) ?>
            },
            roles: {
                labels: <?= json_encode(array_map(fn($k) => ucfirst($k), array_keys($roleCounts))) ?>,
                values: <?= json_encode(array_values($roleCounts)) ?>
            },
            weeklyDecisions: {
                labels: <?= json_encode($weekLabels) ?>,
                pending: <?= json_encode($weekPending) ?>,
                approved: <?= json_encode($weekApproved) ?>,
                rejected: <?= json_encode($weekRejected) ?>
            }
        };
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="/assets/js/app.js"></script>
    <script src="/assets/js/notifications.js"></script>
    <script src="/assets/js/profile.js"></script>
    <script>
        // ============================================
        // Dashboard charts
        // ============================================
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof Chart === 'undefined') {
                console.warn('Chart.js not loaded');
                return;
            }

            const data = window.DASHBOARD_DATA || {};
            const COLORS = {
                green: '#0F5E3D',
                blue: '#3b82f6',
                purple: '#a855f7',
                amber: '#f59e0b',
                red: '#ef4444',
                slate: '#64748b',
                text: '#2C3E50',
                textMuted: 'rgba(44, 62, 80, 0.5)',
                divider: '#E0E0E0',
            };

            Chart.defaults.font.family = "'Inter', sans-serif";
            Chart.defaults.font.size = 11;
            Chart.defaults.color = COLORS.textMuted;

            // Line: leave submissions per month
            const trendCanvas = document.getElementById('leaveTrendChart');
            if (trendCanvas && data.leaveTrend) {
                const ctx = trendCanvas.getContext('2d');
                const gradient = ctx.createLinearGradient(0, 0, 0, 200);
                gradient.addColorStop(0, 'rgba(15, 94, 61, 0.22)');
                gradient.addColorStop(1, 'rgba(15, 94, 61, 0)');

                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: data.weeklyDecisions.labels,
                        datasets: [{
                            label: 'Pending',
                            data: data.weeklyDecisions.pending,
                            borderColor: COLORS.amber,
                            backgroundColor: 'rgba(245, 158, 11, 0.1)',
                            borderWidth: 2,
                            tension: 0.35,
                            fill: true,
                            pointRadius: 3,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: COLORS.text,
                                titleColor: '#fff',
                                bodyColor: '#fff',
                                padding: 8,
                                cornerRadius: 8,
                                displayColors: false,
                                callbacks: { label: (ctx) => `${ctx.parsed.y} request(s)` }
                            }
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                border: { display: false },
                                ticks: { color: COLORS.textMuted, font: { size: 10 } }
                            },
                            y: {
                                beginAtZero: true,
                                grid: { color: COLORS.divider },
                                border: { display: false },
                                ticks: {
                                    color: COLORS.textMuted,
                                    font: { size: 10 },
                                    stepSize: 1,
                                    precision: 0
                                }
                            }
                        }
                    }
                });
            }

            // Donut: users by role
            const roleCanvas = document.getElementById('roleChart');
            if (roleCanvas && data.roles) {
                const labels = data.roles.labels;
                const values = data.roles.values;
                const total = values.reduce((a, b) => a + b, 0);

                new Chart(roleCanvas.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels,
                        datasets: [{
                            data: values,
                            backgroundColor: [
                                COLORS.green,
                                COLORS.blue,
                                COLORS.purple,
                                COLORS.amber,
                                COLORS.red,
                                COLORS.slate,
                            ],
                            borderColor: '#ffffff',
                            borderWidth: 3,
                            hoverOffset: 4,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '68%',
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: COLORS.text,
                                titleColor: '#fff',
                                bodyColor: '#fff',
                                padding: 8,
                                cornerRadius: 8,
                                displayColors: false,
                                callbacks: {
                                    label: (ctx) => {
                                        const v = ctx.parsed || 0;
                                        const pct = total > 0 ? Math.round((v / total) * 100) : 0;
                                        return `${v} (${pct}%)`;
                                    }
                                }
                            }
                        }
                    },
                    plugins: [{
                        id: 'centerText',
                        afterDraw(chart) {
                            const { ctx, chartArea } = chart;
                            const cx = (chartArea.left + chartArea.right) / 2;
                            const cy = (chartArea.top + chartArea.bottom) / 2;

                            ctx.save();
                            ctx.textAlign = 'center';
                            ctx.textBaseline = 'middle';
                            ctx.fillStyle = COLORS.text;
                            ctx.font = 'bold 16px Inter, sans-serif';
                            ctx.fillText(String(total), cx, cy - 4);
                            ctx.fillStyle = COLORS.textMuted;
                            ctx.font = '9px Inter, sans-serif';
                            ctx.fillText('active users', cx, cy + 12);
                            ctx.restore();
                        }
                    }]
                });
            }

            // Bar: weekly decisions
            const weeklyCanvas = document.getElementById('weeklyDecisionsChart');
            if (weeklyCanvas && data.weeklyDecisions) {
                new Chart(weeklyCanvas.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: data.weeklyDecisions.labels,
                        datasets: [
                            {
                                label: 'Pending',
                                data: data.weeklyDecisions.pending,
                                backgroundColor: COLORS.amber,
                                borderRadius: 4,
                                borderSkipped: false,
                                barThickness: 12,
                            },
                            {
                                label: 'Approved',
                                data: data.weeklyDecisions.approved,
                                backgroundColor: COLORS.green,
                                borderRadius: 4,
                                borderSkipped: false,
                                barThickness: 12,
                            },
                            {
                                label: 'Rejected',
                                data: data.weeklyDecisions.rejected,
                                backgroundColor: COLORS.red,
                                borderRadius: 4,
                                borderSkipped: false,
                                barThickness: 12,
                            },
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: COLORS.text,
                                titleColor: '#fff',
                                bodyColor: '#fff',
                                padding: 8,
                                cornerRadius: 8,
                                displayColors: true,
                                callbacks: {
                                    label: (ctx) => `${ctx.dataset.label}: ${ctx.parsed.y}`
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                border: { display: false },
                                ticks: { color: COLORS.textMuted, font: { size: 10 } }
                            },
                            y: {
                                beginAtZero: true,
                                grid: { color: COLORS.divider },
                                border: { display: false },
                                ticks: {
                                    color: COLORS.textMuted,
                                    font: { size: 10 },
                                    stepSize: 1,
                                    precision: 0
                                }
                            }
                        }
                    }
                });
            }
        });
    </script>
</body>

</html>