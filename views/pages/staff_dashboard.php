<?php
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: /login');
    exit;
}

require_once __DIR__ . '/../../config/database.php';

$userId = (int) $_SESSION['user_id'];

// Fetch this staff member's full record
$stmt = $pdo->prepare("
    SELECT 
        e.id AS employee_id,
        e.first_name, e.middle_name, e.last_name, e.email,
        e.category, e.sub_category, e.employment_type,
        e.basic_salary, e.leave_credits
    FROM users u
    LEFT JOIN employees e ON u.employee_id = e.id
    WHERE u.id = ?
    LIMIT 1
");
$stmt->execute([$userId]);
$me = $stmt->fetch();

$firstName = $me['first_name'] ?? ($_SESSION['first_name'] ?? 'Staff');
$lastName  = $me['last_name']  ?? ($_SESSION['last_name']  ?? '');
$initials  = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
$employeeId = (int) ($me['employee_id'] ?? 0);
$isPartTime = (($me['employment_type'] ?? 'full-time') === 'part-time');
$basicSalary = (float) ($me['basic_salary'] ?? 0);

// Leave stats
$pendingCount  = 0;
$approvedCount = 0;
$rejectedCount = 0;
$recentLeaves  = [];
$creditsUsed = 0;

$typeUsed = [
    'vacation'  => 0,
    'sick'      => 0,
    'maternity' => 0,
    'paternity' => 0,
    'terminal'  => 0,
    'other'     => 0,
];

$statusCounts = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'cancelled' => 0];

if ($employeeId > 0) {
    $stmt = $pdo->prepare("
        SELECT 
            SUM(status = 'pending')  AS pending_count,
            SUM(status = 'approved') AS approved_count,
            SUM(status = 'rejected') AS rejected_count,
            COALESCE(SUM(CASE WHEN status = 'approved' THEN days_count ELSE 0 END), 0) AS credits_used
        FROM leave_requests
        WHERE employee_id = ?
    ");
    $stmt->execute([$employeeId]);
    $stats = $stmt->fetch() ?: [];
    $pendingCount  = (int) ($stats['pending_count']  ?? 0);
    $approvedCount = (int) ($stats['approved_count'] ?? 0);
    $rejectedCount = (int) ($stats['rejected_count'] ?? 0);
    $creditsUsed   = (float) ($stats['credits_used'] ?? 0);

    // Recent 5 leaves
    $stmt = $pdo->prepare("
        SELECT id, leave_type, start_date, end_date, days_count, status, created_at
        FROM leave_requests
        WHERE employee_id = ?
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$employeeId]);
    $recentLeaves = $stmt->fetchAll();

    // Per-type usage (approved)
    $stmt = $pdo->prepare("
        SELECT leave_type, COALESCE(SUM(days_count), 0) AS total
        FROM leave_requests
        WHERE employee_id = ? AND status = 'approved'
        GROUP BY leave_type
    ");
    $stmt->execute([$employeeId]);
    foreach ($stmt->fetchAll() as $row) {
        if (isset($typeUsed[$row['leave_type']])) {
            $typeUsed[$row['leave_type']] = (float) $row['total'];
        }
    }

    // Status counts (all leaves)
    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) AS c
        FROM leave_requests
        WHERE employee_id = ?
        GROUP BY status
    ");
    $stmt->execute([$employeeId]);
    foreach ($stmt->fetchAll() as $row) {
        if (isset($statusCounts[$row['status']])) {
            $statusCounts[$row['status']] = (int) $row['c'];
        }
    }
}

$totalCredits = (float)($me['leave_credits'] ?? 0);
$leaveCreditsLeft = max(0, $totalCredits - $creditsUsed);
$otherUsed = $typeUsed['maternity'] + $typeUsed['paternity'] + $typeUsed['terminal'] + $typeUsed['other'];
$usedPct = $totalCredits > 0 ? min(100, round(($creditsUsed / $totalCredits) * 100, 1)) : 0;

// Estimated payroll deduction
$workingDaysPerMonth = 22;
$dailyRate = $basicSalary > 0 ? $basicSalary / $workingDaysPerMonth : 0;
$estimatedDeduction = $dailyRate * $creditsUsed;

// Estimated convertible credits to payroll
$convertibleCredits = $leaveCreditsLeft;
$convertiblePayroll = $dailyRate * $convertibleCredits;

// Monthly trend — approved days per month (last 6 months)
$trendLabels = [];
$trendValues = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $trendLabels[] = date('M', strtotime($month . '-01'));

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(days_count), 0)
        FROM leave_requests
        WHERE employee_id = ?
          AND status = 'approved'
          AND DATE_FORMAT(start_date, '%Y-%m') = ?
    ");
    $stmt->execute([$employeeId, $month]);
    $trendValues[] = (float) $stmt->fetchColumn();
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | DAMMC Staff</title>
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
        $activePage = 'staff_dashboard';
        require __DIR__ . '/../components/sidebar.php';
        ?>

        <div class="flex-1 md:ml-64 w-full min-w-0">

            <?php
            $pageTitle = 'Dashboard';
            $pageSubtitle = 'Welcome back, ' . $firstName;
            $pageIcon = 'layout-dashboard';
            require __DIR__ . '/../components/topbar.php';
            ?>

            <main class="p-4 sm:p-6 lg:p-8 space-y-4 sm:space-y-6">

                <!-- Welcome banner -->
                <div class="animate-fade-in-up bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-5 sm:p-6 shadow-sm flex flex-col sm:flex-row items-start sm:items-center gap-4">
                    <div class="w-14 h-14 rounded-full bg-[#0F5E3D] text-white flex items-center justify-center font-bold text-lg ring-4 ring-[#F1FDF6] shrink-0">
                        <?= htmlspecialchars($initials) ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h2 class="text-lg font-bold text-[#2C3E50]">
                            Hello, <?= htmlspecialchars($firstName) ?>!
                        </h2>
                        <p class="text-sm text-[#2C3E50]/60 mt-0.5">
                            <?= htmlspecialchars(ucfirst($me['category'] ?? 'staff')) ?>
                            <?= !empty($me['sub_category']) ? ' · ' . htmlspecialchars($me['sub_category']) : '' ?>
                            <?= !empty($me['employment_type']) ? ' · ' . htmlspecialchars(ucfirst($me['employment_type'])) : '' ?>
                        </p>
                    </div>
                    <?php if (!$isPartTime): ?>
                        <a href="/staff/leaves"
                            class="w-full sm:w-auto flex items-center justify-center gap-2 bg-[#0F5E3D] hover:bg-[#0a4a2f] active:scale-[0.99] text-white font-medium py-2.5 px-4 rounded-lg transition text-sm shadow-sm hover:shadow-md">
                            <i data-lucide="plus" class="w-4 h-4"></i>
                            <span>File a Leave</span>
                        </a>
                    <?php else: ?>
                        <div class="w-full sm:w-auto flex items-center justify-center gap-2 bg-slate-100 text-slate-500 font-medium py-2.5 px-4 rounded-lg text-sm cursor-not-allowed"
                            title="Part-time employees are not eligible for leave">
                            <i data-lucide="lock" class="w-4 h-4"></i>
                            <span>Leave not available</span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- HERO ROW -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 sm:gap-4">

                    <!-- Leave credits overview -->
                    <div class="lg:col-span-2 animate-fade-in-up bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-5 sm:p-6 shadow-sm">
                        <div class="flex items-start justify-between gap-4 mb-4">
                            <div>
                                <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/50 font-semibold mb-1">Leave Credits</p>
                                <div class="flex items-baseline gap-2">
                                    <span class="text-3xl sm:text-4xl font-bold text-[#0F5E3D]">
                                        <?= number_format($leaveCreditsLeft, 2) ?>
                                    </span>
                                    <span class="text-sm text-[#2C3E50]/50">
                                        / <?= number_format($totalCredits, 2) ?> remaining
                                    </span>
                                </div>
                            </div>
                            <div class="w-10 h-10 rounded-lg bg-[#F1FDF6] flex items-center justify-center text-[#0F5E3D] shrink-0">
                                <i data-lucide="calendar-check" class="w-5 h-5"></i>
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="flex items-center justify-between text-[11px] text-[#2C3E50]/60 mb-1.5">
                                <span><?= number_format($creditsUsed, 2) ?> used</span>
                                <span><?= $usedPct ?>%</span>
                            </div>
                            <div class="h-2 bg-[#F1FDF6] rounded-full overflow-hidden">
                                <div class="h-full bg-[#0F5E3D] rounded-full transition-all duration-500"
                                     style="width: <?= $usedPct ?>%"></div>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-3 pt-4 border-t border-[#E0E0E0]">
                            <div>
                                <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-0.5">Allocated</p>
                                <p class="text-sm font-bold text-[#2C3E50]"><?= number_format($totalCredits, 2) ?></p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-0.5">Used</p>
                                <p class="text-sm font-bold text-amber-600"><?= number_format($creditsUsed, 2) ?></p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-0.5">Remaining</p>
                                <p class="text-sm font-bold text-[#0F5E3D]"><?= number_format($leaveCreditsLeft, 2) ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Right column: two stacked cards -->
                    <div class="flex flex-col gap-3 sm:gap-4">

                        <div class="animate-fade-in-up-delay-1 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-5 shadow-sm flex flex-col">
                            <div class="flex items-start justify-between gap-4 mb-3">
                                <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/50 font-semibold">Est. Payroll Deduction</p>
                                <div class="w-9 h-9 rounded-lg bg-amber-50 flex items-center justify-center text-amber-600 shrink-0">
                                    <i data-lucide="receipt" class="w-4 h-4"></i>
                                </div>
                            </div>
                            <span class="text-2xl sm:text-3xl font-bold text-[#2C3E50]">
                                ₱<?= number_format($estimatedDeduction, 2) ?>
                            </span>
                            <p class="text-[10px] text-[#2C3E50]/50 mt-1.5">
                                <?php if ($basicSalary > 0): ?>
                                    ₱<?= number_format($basicSalary, 2) ?>/mo ÷ 22 × <?= number_format($creditsUsed, 2) ?> day(s)
                                <?php else: ?>
                                    no salary on file
                                <?php endif; ?>
                            </p>
                        </div>

                        <div class="animate-fade-in-up-delay-2 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-5 shadow-sm flex flex-col">
                            <div class="flex items-start justify-between gap-4 mb-3">
                                <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/50 font-semibold">Est. Convertible Credits</p>
                                <div class="w-9 h-9 rounded-lg bg-[#F1FDF6] flex items-center justify-center text-[#0F5E3D] shrink-0">
                                    <i data-lucide="banknote" class="w-4 h-4"></i>
                                </div>
                            </div>
                            <span class="text-2xl sm:text-3xl font-bold text-[#0F5E3D]">
                                ₱<?= number_format($convertiblePayroll, 2) ?>
                            </span>
                            <p class="text-[10px] text-[#2C3E50]/50 mt-1.5">
                                <?php if ($basicSalary > 0): ?>
                                    <?= number_format($convertibleCredits, 2) ?> day(s) × ₱<?= number_format($dailyRate, 2) ?>/day
                                <?php else: ?>
                                    no salary on file
                                <?php endif; ?>
                            </p>
                        </div>

                    </div>

                </div>

                <!-- CHARTS ROW -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 sm:gap-4">

                    <div class="animate-fade-in-up-delay-2 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-5 shadow-sm">
                        <div class="mb-3">
                            <h2 class="text-sm font-bold text-[#2C3E50]">Leave Breakdown</h2>
                            <p class="text-[10px] text-[#2C3E50]/50 mt-0.5">Approved days by type</p>
                        </div>
                        <div class="relative h-40">
                            <canvas id="breakdownChart"></canvas>
                        </div>
                        <div class="grid grid-cols-3 gap-2 mt-4 pt-3 border-t border-[#E0E0E0]">
                            <div class="text-center">
                                <span class="inline-block w-2 h-2 rounded-full bg-blue-500 mb-1"></span>
                                <p class="text-[10px] text-[#2C3E50]/50 uppercase tracking-wider">Vacation</p>
                                <p class="text-xs font-bold text-[#2C3E50]"><?= number_format($typeUsed['vacation'], 2) ?></p>
                            </div>
                            <div class="text-center">
                                <span class="inline-block w-2 h-2 rounded-full bg-rose-500 mb-1"></span>
                                <p class="text-[10px] text-[#2C3E50]/50 uppercase tracking-wider">Sick</p>
                                <p class="text-xs font-bold text-[#2C3E50]"><?= number_format($typeUsed['sick'], 2) ?></p>
                            </div>
                            <div class="text-center">
                                <span class="inline-block w-2 h-2 rounded-full bg-purple-500 mb-1"></span>
                                <p class="text-[10px] text-[#2C3E50]/50 uppercase tracking-wider">Other</p>
                                <p class="text-xs font-bold text-[#2C3E50]"><?= number_format($otherUsed, 2) ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="lg:col-span-2 animate-fade-in-up-delay-2 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-5 shadow-sm">
                        <div class="flex items-start justify-between mb-3">
                            <div>
                                <h2 class="text-sm font-bold text-[#2C3E50]">Usage Trend</h2>
                                <p class="text-[10px] text-[#2C3E50]/50 mt-0.5">Approved days per month · last 6 months</p>
                            </div>
                            <div class="flex items-center gap-1.5 text-[10px] text-[#2C3E50]/60">
                                <span class="w-2 h-2 rounded-full bg-[#0F5E3D]"></span>
                                <span>Approved days</span>
                            </div>
                        </div>
                        <div class="relative h-40">
                            <canvas id="trendChart"></canvas>
                        </div>
                    </div>

                </div>

                <!-- QUICK STATS STRIP -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <div class="animate-fade-in-up-delay-2 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-4 shadow-sm">
                        <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-1">Total Requests</p>
                        <p class="text-xl font-bold text-[#2C3E50]"><?= array_sum($statusCounts) ?></p>
                    </div>
                    <div class="animate-fade-in-up-delay-2 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-4 shadow-sm">
                        <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-1">Approved</p>
                        <p class="text-xl font-bold text-[#0F5E3D]"><?= $statusCounts['approved'] ?></p>
                    </div>
                    <div class="animate-fade-in-up-delay-3 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-4 shadow-sm">
                        <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-1">Pending</p>
                        <p class="text-xl font-bold text-amber-600"><?= $statusCounts['pending'] ?></p>
                    </div>
                    <div class="animate-fade-in-up-delay-3 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-4 shadow-sm">
                        <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-1">Rejected</p>
                        <p class="text-xl font-bold text-red-600"><?= $statusCounts['rejected'] ?></p>
                    </div>
                </div>

                <!-- ROW 4: Recent Leaves + My Info -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6">

                    <div class="animate-fade-in-up-delay-3 lg:col-span-2 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-5 sm:p-6 shadow-sm">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h2 class="text-base sm:text-lg font-bold text-[#2C3E50]">Recent Leave Requests</h2>
                                <p class="text-xs text-[#2C3E50]/50 mt-0.5">Your last 5 submissions</p>
                            </div>
                            <a href="/staff/leaves" class="text-xs font-medium text-[#0F5E3D] hover:text-[#0a4a2f] transition">
                                View all →
                            </a>
                        </div>

                        <?php if (empty($recentLeaves)): ?>
                            <div class="py-10 text-center">
                                <div class="w-12 h-12 rounded-full bg-[#F1FDF6] flex items-center justify-center mx-auto mb-3 text-[#0F5E3D]">
                                    <i data-lucide="calendar" class="w-5 h-5"></i>
                                </div>
                                <p class="text-sm font-medium text-[#2C3E50]">No leave requests yet</p>
                                <?php if ($isPartTime): ?>
                                    <p class="text-xs text-[#2C3E50]/50 mt-1">Part-time employees are not eligible for leave.</p>
                                <?php else: ?>
                                    <p class="text-xs text-[#2C3E50]/50 mt-1 mb-4">File your first leave and it'll show up here.</p>
                                    <a href="/staff/leaves"
                                        class="inline-flex items-center gap-2 bg-[#0F5E3D] hover:bg-[#0a4a2f] text-white font-medium py-2 px-4 rounded-lg transition text-xs">
                                        <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                                        <span>File a Leave</span>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="divide-y divide-[#E0E0E0]">
                                <?php foreach ($recentLeaves as $l):
                                    $sClass = $statusStyles[$l['status']] ?? 'text-slate-600 bg-slate-100';
                                    $sLabel = $statusLabels[$l['status']] ?? ucfirst($l['status']);
                                    ?>
                                    <div class="flex items-center gap-3 py-3">
                                        <div class="w-9 h-9 rounded-lg bg-[#F1FDF6] flex items-center justify-center text-[#0F5E3D] shrink-0">
                                            <i data-lucide="calendar-days" class="w-4 h-4"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-[#2C3E50] capitalize">
                                                <?= htmlspecialchars($l['leave_type']) ?>
                                            </p>
                                            <p class="text-xs text-[#2C3E50]/50">
                                                <?= date('M j, Y', strtotime($l['start_date'])) ?>
                                                <?php if ($l['end_date'] !== $l['start_date']): ?>
                                                    → <?= date('M j, Y', strtotime($l['end_date'])) ?>
                                                <?php endif; ?>
                                                · <?= number_format((float)$l['days_count'], 2) ?> day(s)
                                            </p>
                                        </div>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium <?= $sClass ?> shrink-0">
                                            <?= $sLabel ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="animate-fade-in-up-delay-3 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-5 sm:p-6 shadow-sm">
                        <h2 class="text-base sm:text-lg font-bold text-[#2C3E50] mb-4">My Info</h2>

                        <dl class="space-y-3 text-sm">
                            <div>
                                <dt class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-0.5">Email</dt>
                                <dd class="text-[#2C3E50] truncate"><?= htmlspecialchars($me['email'] ?? '—') ?></dd>
                            </div>
                            <div>
                                <dt class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-0.5">Category</dt>
                                <dd class="text-[#2C3E50] capitalize"><?= htmlspecialchars($me['category'] ?? '—') ?></dd>
                            </div>
                            <div>
                                <dt class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-0.5">Employment</dt>
                                <dd class="text-[#2C3E50] capitalize"><?= htmlspecialchars($me['employment_type'] ?? '—') ?></dd>
                            </div>
                            <div>
                                <dt class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-0.5">Monthly Salary</dt>
                                <dd class="text-[#2C3E50]">₱<?= number_format($basicSalary, 2) ?></dd>
                            </div>
                        </dl>

                        <div class="mt-5 pt-4 border-t border-[#E0E0E0] space-y-2">
                            <?php if (!$isPartTime): ?>
                                <a href="/staff/leaves"
                                    class="w-full flex items-center justify-center gap-2 bg-[#0F5E3D] hover:bg-[#0a4a2f] active:scale-[0.99] text-white font-medium py-2.5 rounded-lg transition text-sm shadow-sm">
                                    <i data-lucide="file-plus" class="w-4 h-4"></i>
                                    File a Leave
                                </a>
                            <?php else: ?>
                                <div class="w-full flex items-center justify-center gap-2 bg-slate-100 text-slate-500 font-medium py-2.5 rounded-lg text-sm cursor-not-allowed">
                                    <i data-lucide="lock" class="w-4 h-4"></i>
                                    Leave not available
                                </div>
                            <?php endif; ?>
                            <a href="/faculty/profile"
                                class="w-full flex items-center justify-center gap-2 border border-[#E0E0E0] text-[#2C3E50] hover:bg-gray-50 active:scale-[0.99] font-medium py-2.5 rounded-lg transition text-sm">
                                <i data-lucide="user" class="w-4 h-4"></i>
                                View Profile
                            </a>
                        </div>
                    </div>

                </div>

            </main>
        </div>
    </div>

    <?php require_once __DIR__ . '/../components/logout_modal.php'; ?>

    <script>
    window.CHART_DATA = {
        breakdown: {
            vacation: <?= (float) $typeUsed['vacation'] ?>,
            sick:     <?= (float) $typeUsed['sick'] ?>,
            other:    <?= (float) $otherUsed ?>
        },
        trend: {
            labels: <?= json_encode($trendLabels) ?>,
            values: <?= json_encode($trendValues) ?>
        }
    };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="/assets/js/app.js"></script>
    <script src="/assets/js/notifications.js"></script>
    <script src="/assets/js/profile.js"></script>
    <script src="/assets/js/staff_dashboard.js"></script>
</body>
</html>