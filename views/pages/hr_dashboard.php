<?php
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hr') {
    header('Location: /login');
    exit;
}

require_once __DIR__ . '/../../config/database.php';

$userId = (int) $_SESSION['user_id'];

// HR's own info
$stmt = $pdo->prepare("
    SELECT e.first_name, e.last_name, e.email, e.sub_category
    FROM users u
    LEFT JOIN employees e ON u.employee_id = e.id
    WHERE u.id = ?
    LIMIT 1
");
$stmt->execute([$userId]);
$me = $stmt->fetch() ?: [];
$firstName = $me['first_name'] ?? ($_SESSION['first_name'] ?? 'HR');

// Overall leave stats
$stats = $pdo->query("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled,
        SUM(CASE WHEN status = 'pending' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS new_this_week
    FROM leave_requests
")->fetch();

// Leave distribution by type — ALL statuses
$byType = $pdo->query("
    SELECT leave_type, COUNT(*) AS count
    FROM leave_requests
    GROUP BY leave_type
")->fetchAll();

$typeCounts = [
    'vacation'  => 0,
    'sick'      => 0,
    'other'     => 0,
];
foreach ($byType as $row) {
    if (isset($typeCounts[$row['leave_type']])) {
        $typeCounts[$row['leave_type']] = (int) $row['count'];
    }
}

// Pending requests (top 5, oldest first — FIFO)
$stmt = $pdo->query("
    SELECT lr.*, e.first_name, e.last_name, e.sub_category
    FROM leave_requests lr
    LEFT JOIN employees e ON lr.employee_id = e.id
    WHERE lr.status = 'pending'
    ORDER BY lr.created_at ASC
    LIMIT 5
");
$pendingRecent = $stmt->fetchAll();

// Recent decisions (last 5)
$stmt = $pdo->query("
    SELECT lr.*, e.first_name, e.last_name, u.username AS approver
    FROM leave_requests lr
    LEFT JOIN employees e ON lr.employee_id = e.id
    LEFT JOIN users u ON lr.approved_by = u.id
    WHERE lr.status IN ('approved', 'rejected')
    ORDER BY lr.approved_at DESC
    LIMIT 5
");
$recentDecisions = $stmt->fetchAll();

// Monthly trend — requests submitted per month (last 6 months)
$trendLabels = [];
$trendValues = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $trendLabels[] = date('M', strtotime($month . '-01'));

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM leave_requests
        WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
    ");
    $stmt->execute([$month]);
    $trendValues[] = (int) $stmt->fetchColumn();
}

$statusStyles = [
    'pending'   => 'text-amber-700 bg-amber-50',
    'approved'  => 'text-[#0F5E3D] bg-[#F1FDF6]',
    'rejected'  => 'text-red-700 bg-red-50',
    'cancelled' => 'text-slate-600 bg-slate-100',
];
$statusLabels = [
    'pending'   => 'Pending',
    'approved'  => 'Approved',
    'rejected'  => 'Rejected',
    'cancelled' => 'Cancelled',
];
$leaveTypes = [
    'vacation'  => 'Vacation',
    'sick'      => 'Sick',
    'maternity' => 'Maternity',
    'paternity' => 'Paternity',
    'terminal'  => 'Terminal',
    'other'     => 'Other',
];
$typeColors = [
    'vacation'  => '#3b82f6',
    'sick'      => '#f43f5e',
    'maternity' => '#a855f7',
    'paternity' => '#6366f1',
    'terminal'  => '#f59e0b',
    'other'     => '#64748b',
];

function timeAgo($ts) {
    $diff = time() - strtotime($ts);
    if ($diff < 60)     return 'Just now';
    if ($diff < 3600)   return floor($diff / 60) . 'm ago';
    if ($diff < 86400)  return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', strtotime($ts));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | DAMMC HR</title>
    <link rel="icon" type="image/png" href="/assets/images/dammc-logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="bg-gradient-to-br from-[#F1FDF6] via-[#eafaf1] to-[#dcf3e5] min-h-screen">

    <div class="flex min-h-screen">
        <div id="sidebarOverlay" onclick="closeSidebar()"
            class="fixed inset-0 bg-black/40 z-30 hidden md:hidden backdrop-blur-sm"></div>

        <?php
        $activePage = 'hr_dashboard';
        require __DIR__ . '/../components/sidebar.php';
        ?>

        <div class="flex-1 md:ml-64 w-full min-w-0">
            <?php
            $pageTitle = 'HR Dashboard';
            $pageSubtitle = 'Welcome back, ' . $firstName;
            $pageIcon = 'layout-dashboard';
            require __DIR__ . '/../components/topbar.php';
            ?>

            <main class="p-4 sm:p-6 lg:p-8 space-y-4 sm:space-y-6">

                <!-- Welcome banner -->
                <div class="animate-fade-in-up bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-5 sm:p-6 shadow-sm flex flex-col sm:flex-row items-start sm:items-center gap-4">
                    <div class="flex-1 min-w-0">
                        <h2 class="text-lg font-bold text-[#2C3E50]">Hello, <?= htmlspecialchars($firstName) ?>!</h2>
                        <p class="text-sm text-[#2C3E50]/60 mt-0.5">
                            You have <span class="font-semibold text-amber-600"><?= (int) $stats['pending'] ?></span>
                            pending leave request<?= ((int)$stats['pending'] === 1) ? '' : 's' ?> to review.
                        </p>
                    </div>
                    <a href="/hr/leaves"
                        class="w-full sm:w-auto flex items-center justify-center gap-2 bg-[#0F5E3D] hover:bg-[#0a4a2f] active:scale-[0.99] text-white font-medium py-2.5 px-4 rounded-lg transition text-sm shadow-sm hover:shadow-md">
                        <i data-lucide="calendar-check" class="w-4 h-4"></i>
                        <span>Review Requests</span>
                    </a>
                </div>

                <!-- ROW 1: Hero cards -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 sm:gap-4">

                    <!-- Pending review queue -->
                    <div class="lg:col-span-2 animate-fade-in-up bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-5 sm:p-6 shadow-sm">
                        <div class="flex items-start justify-between gap-4 mb-4">
                            <div>
                                <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/50 font-semibold mb-1">Pending Review</p>
                                <div class="flex items-baseline gap-2">
                                    <span class="text-3xl sm:text-4xl font-bold text-amber-600">
                                        <?= (int) $stats['pending'] ?>
                                    </span>
                                    <span class="text-sm text-[#2C3E50]/50">
                                        request<?= ((int)$stats['pending'] === 1) ? '' : 's' ?> awaiting decision
                                    </span>
                                </div>
                            </div>
                            <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center text-amber-600 shrink-0">
                                <i data-lucide="clock" class="w-5 h-5"></i>
                            </div>
                        </div>

                        <?php if ((int) $stats['new_this_week'] > 0): ?>
                            <div class="mb-4">
                                <div class="flex items-center justify-between text-[11px] text-[#2C3E50]/60 mb-1.5">
                                    <span><?= (int) $stats['new_this_week'] ?> new this week</span>
                                </div>
                                <div class="h-2 bg-[#F1FDF6] rounded-full overflow-hidden">
                                    <?php
                                    $weekPct = $stats['pending'] > 0
                                        ? min(100, round(($stats['new_this_week'] / $stats['pending']) * 100))
                                        : 0;
                                    ?>
                                    <div class="h-full bg-amber-500 rounded-full transition-all duration-500"
                                         style="width: <?= $weekPct ?>%"></div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="grid grid-cols-3 gap-3 pt-4 border-t border-[#E0E0E0]">
                            <div>
                                <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-0.5">Total</p>
                                <p class="text-sm font-bold text-[#2C3E50]"><?= (int) $stats['total'] ?></p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-0.5">Approved</p>
                                <p class="text-sm font-bold text-[#0F5E3D]"><?= (int) $stats['approved'] ?></p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-0.5">Rejected</p>
                                <p class="text-sm font-bold text-red-600"><?= (int) $stats['rejected'] ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Quick action -->
                    <div class="animate-fade-in-up-delay-1 bg-gradient-to-br from-[#0F5E3D] to-[#0a4a2f] rounded-xl p-5 sm:p-6 shadow-sm flex flex-col text-white">
                        <div class="flex items-start justify-between gap-4 mb-3">
                            <p class="text-[10px] uppercase tracking-wider text-white/70 font-semibold">Quick Action</p>
                            <div class="w-9 h-9 rounded-lg bg-white/10 flex items-center justify-center shrink-0">
                                <i data-lucide="clipboard-check" class="w-4 h-4"></i>
                            </div>
                        </div>
                        <p class="text-sm text-white/80 leading-relaxed mb-4">
                            Review pending leave requests and approve or reject them.
                        </p>
                        <a href="/hr/leaves"
                            class="mt-auto inline-flex items-center justify-center gap-2 bg-white text-[#0F5E3D] hover:bg-[#F1FDF6] font-medium py-2.5 px-4 rounded-lg transition text-sm shadow-sm">
                            <span>Go to Leave Requests</span>
                            <i data-lucide="arrow-right" class="w-4 h-4"></i>
                        </a>
                    </div>

                </div>

                <!-- ROW 2: Charts -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 sm:gap-4">

                    <!-- Donut: status breakdown -->
                    <div class="animate-fade-in-up-delay-2 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-5 shadow-sm">
                        <div class="mb-3">
                            <h2 class="text-sm font-bold text-[#2C3E50]">Status Breakdown</h2>
                            <p class="text-[10px] text-[#2C3E50]/50 mt-0.5">All leave requests</p>
                        </div>
                        <div class="relative h-40">
                            <canvas id="statusChart"></canvas>
                        </div>
                        <div class="grid grid-cols-2 gap-2 mt-4 pt-3 border-t border-[#E0E0E0]">
                            <div class="text-center">
                                <span class="inline-block w-2 h-2 rounded-full bg-amber-500 mb-1"></span>
                                <p class="text-[10px] text-[#2C3E50]/50 uppercase tracking-wider">Pending</p>
                                <p class="text-xs font-bold text-[#2C3E50]"><?= (int) $stats['pending'] ?></p>
                            </div>
                            <div class="text-center">
                                <span class="inline-block w-2 h-2 rounded-full bg-[#0F5E3D] mb-1"></span>
                                <p class="text-[10px] text-[#2C3E50]/50 uppercase tracking-wider">Approved</p>
                                <p class="text-xs font-bold text-[#2C3E50]"><?= (int) $stats['approved'] ?></p>
                            </div>
                            <div class="text-center">
                                <span class="inline-block w-2 h-2 rounded-full bg-red-500 mb-1"></span>
                                <p class="text-[10px] text-[#2C3E50]/50 uppercase tracking-wider">Rejected</p>
                                <p class="text-xs font-bold text-[#2C3E50]"><?= (int) $stats['rejected'] ?></p>
                            </div>
                            <div class="text-center">
                                <span class="inline-block w-2 h-2 rounded-full bg-slate-400 mb-1"></span>
                                <p class="text-[10px] text-[#2C3E50]/50 uppercase tracking-wider">Cancelled</p>
                                <p class="text-xs font-bold text-[#2C3E50]"><?= (int) $stats['cancelled'] ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Line: monthly trend -->
                    <div class="lg:col-span-2 animate-fade-in-up-delay-2 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-5 shadow-sm">
                        <div class="flex items-start justify-between mb-3">
                            <div>
                                <h2 class="text-sm font-bold text-[#2C3E50]">Submission Trend</h2>
                                <p class="text-[10px] text-[#2C3E50]/50 mt-0.5">Leave requests per month · last 6 months</p>
                            </div>
                            <div class="flex items-center gap-1.5 text-[10px] text-[#2C3E50]/60">
                                <span class="w-2 h-2 rounded-full bg-[#0F5E3D]"></span>
                                <span>Requests</span>
                            </div>
                        </div>
                        <div class="relative h-40">
                            <canvas id="hrTrendChart"></canvas>
                        </div>
                    </div>

                </div>

                <!-- ROW 2.5: Leave Types Breakdown -->
                <div class="animate-fade-in-up-delay-2 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-5 sm:p-6 shadow-sm">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <h2 class="text-base sm:text-lg font-bold text-[#2C3E50]">Leave Types</h2>
                            <p class="text-xs text-[#2C3E50]/50 mt-0.5">Total requests by type · all statuses</p>
                        </div>
                        <div class="flex items-center gap-1.5 text-[10px] text-[#2C3E50]/60">
                            <span class="w-2 h-2 rounded-full bg-[#0F5E3D]"></span>
                            <span>Requests</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

                        <!-- Bar chart -->
                        <div class="lg:col-span-2 relative h-56">
                            <canvas id="leaveTypesChart"></canvas>
                        </div>

                        <!-- Legend / counts -->
                        <div class="space-y-2">
                            <?php
                            $typeTotal = array_sum($typeCounts) ?: 1;
                            foreach ($typeCounts as $t => $c):
                                $pct = round(($c / $typeTotal) * 100);
                                $color = $typeColors[$t] ?? '#64748b';
                                $label = $leaveTypes[$t] ?? ucfirst($t);
                            ?>
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <div class="flex items-center gap-2">
                                            <span class="inline-block w-2 h-2 rounded-full" style="background:<?= $color ?>"></span>
                                            <span class="text-xs font-medium text-[#2C3E50]"><?= htmlspecialchars($label) ?></span>
                                        </div>
                                        <span class="text-xs text-[#2C3E50]/50"><?= $c ?> · <?= $pct ?>%</span>
                                    </div>
                                    <div class="h-1.5 bg-[#F1FDF6] rounded-full overflow-hidden">
                                        <div class="h-full rounded-full transition-all"
                                             style="width: <?= $pct ?>%; background: <?= $color ?>"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                    </div>
                </div>

                <!-- ROW 3: Quick stats strip -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <div class="animate-fade-in-up-delay-2 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-4 shadow-sm">
                        <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-1">Total Requests</p>
                        <p class="text-xl font-bold text-[#2C3E50]"><?= (int) $stats['total'] ?></p>
                    </div>
                    <div class="animate-fade-in-up-delay-2 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-4 shadow-sm">
                        <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-1">Approved</p>
                        <p class="text-xl font-bold text-[#0F5E3D]"><?= (int) $stats['approved'] ?></p>
                    </div>
                    <div class="animate-fade-in-up-delay-3 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-4 shadow-sm">
                        <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-1">Pending</p>
                        <p class="text-xl font-bold text-amber-600"><?= (int) $stats['pending'] ?></p>
                    </div>
                    <div class="animate-fade-in-up-delay-3 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-4 shadow-sm">
                        <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-1">Rejected</p>
                        <p class="text-xl font-bold text-red-600"><?= (int) $stats['rejected'] ?></p>
                    </div>
                </div>

                <!-- ROW 4: Pending + Recent -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6">

                    <!-- Pending requests -->
                    <div class="animate-fade-in-up-delay-3 lg:col-span-2 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-5 sm:p-6 shadow-sm">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h2 class="text-base sm:text-lg font-bold text-[#2C3E50]">Pending Requests</h2>
                                <p class="text-xs text-[#2C3E50]/50 mt-0.5">Oldest first — first in, first served</p>
                            </div>
                            <a href="/hr/leaves" class="text-xs font-medium text-[#0F5E3D] hover:text-[#0a4a2f] transition">
                                View all →
                            </a>
                        </div>

                        <?php if (empty($pendingRecent)): ?>
                            <div class="py-10 text-center">
                                <div class="w-12 h-12 rounded-full bg-[#F1FDF6] flex items-center justify-center mx-auto mb-3 text-[#0F5E3D]">
                                    <i data-lucide="check-check" class="w-5 h-5"></i>
                                </div>
                                <p class="text-sm font-medium text-[#2C3E50]">All caught up</p>
                                <p class="text-xs text-[#2C3E50]/50 mt-1">No pending leave requests right now.</p>
                            </div>
                        <?php else: ?>
                            <div class="divide-y divide-[#E0E0E0]">
                                <?php foreach ($pendingRecent as $l):
                                    $name = trim(($l['first_name'] ?? '') . ' ' . ($l['last_name'] ?? '')) ?: 'Unknown';
                                    $typeLabel = $leaveTypes[$l['leave_type']] ?? ucfirst($l['leave_type']);
                                    ?>
                                    <div class="flex items-center gap-3 py-3">
                                        <div class="w-9 h-9 rounded-lg bg-amber-50 flex items-center justify-center text-amber-600 shrink-0">
                                            <i data-lucide="calendar-clock" class="w-4 h-4"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-[#2C3E50] truncate">
                                                <?= htmlspecialchars($name) ?>
                                            </p>
                                            <p class="text-xs text-[#2C3E50]/50 truncate">
                                                <?= htmlspecialchars($typeLabel) ?> ·
                                                <?= date('M j', strtotime($l['start_date'])) ?>–<?= date('M j', strtotime($l['end_date'])) ?> ·
                                                <?= number_format((float)$l['days_count'], 1) ?> day(s)
                                            </p>
                                            <p class="text-[10px] text-[#2C3E50]/40 mt-0.5"><?= timeAgo($l['created_at']) ?></p>
                                        </div>
                                        <a href="/hr/leaves"
                                            class="text-xs font-medium text-[#0F5E3D] hover:text-[#0a4a2f] transition shrink-0">
                                            Review →
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Recent decisions -->
                    <div class="animate-fade-in-up-delay-3 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-5 sm:p-6 shadow-sm">
                        <h2 class="text-base sm:text-lg font-bold text-[#2C3E50] mb-4">Recent Decisions</h2>

                        <?php if (empty($recentDecisions)): ?>
                            <p class="text-sm text-[#2C3E50]/50 text-center py-6">No decisions yet.</p>
                        <?php else: ?>
                            <div class="divide-y divide-[#E0E0E0]">
                                <?php foreach ($recentDecisions as $l):
                                    $name = trim(($l['first_name'] ?? '') . ' ' . ($l['last_name'] ?? '')) ?: 'Unknown';
                                    $typeLabel = $leaveTypes[$l['leave_type']] ?? ucfirst($l['leave_type']);
                                    $sClass = $statusStyles[$l['status']] ?? 'text-slate-600 bg-slate-100';
                                    ?>
                                    <div class="flex items-center gap-3 py-3">
                                        <div class="w-8 h-8 rounded-lg bg-[#F1FDF6] flex items-center justify-center text-[#0F5E3D] shrink-0">
                                            <i data-lucide="<?= $l['status'] === 'approved' ? 'check-circle' : 'x-circle' ?>" class="w-3.5 h-3.5"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-xs font-medium text-[#2C3E50] truncate">
                                                <?= htmlspecialchars($name) ?>
                                            </p>
                                            <p class="text-[10px] text-[#2C3E50]/50 truncate">
                                                <?= htmlspecialchars($typeLabel) ?>
                                                <?= $l['approved_at'] ? ' · ' . timeAgo($l['approved_at']) : '' ?>
                                            </p>
                                        </div>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium capitalize <?= $sClass ?> shrink-0">
                                            <?= htmlspecialchars($l['status']) ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>

            </main>
        </div>
    </div>

    <?php require_once __DIR__ . '/../components/logout_modal.php'; ?>

    <script>
    window.HR_CHART_DATA = {
        status: {
            pending:   <?= (int) $stats['pending'] ?>,
            approved:  <?= (int) $stats['approved'] ?>,
            rejected:  <?= (int) $stats['rejected'] ?>,
            cancelled: <?= (int) $stats['cancelled'] ?>
        },
        trend: {
            labels: <?= json_encode($trendLabels) ?>,
            values: <?= json_encode($trendValues) ?>
        },
        types: {
            labels: <?= json_encode(array_map(fn($t) => $leaveTypes[$t] ?? ucfirst($t), array_keys($typeCounts))) ?>,
            values: <?= json_encode(array_values($typeCounts)) ?>,
            colors: <?= json_encode(array_values($typeColors)) ?>
        }
    };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="/assets/js/app.js"></script>
    <script src="/assets/js/notifications.js"></script>
    <script src="/assets/js/profile.js"></script>
    <script src="/assets/js/hr_dashboard.js"></script>
</body>
</html>