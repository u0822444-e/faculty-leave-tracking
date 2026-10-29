<?php
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: /login');
    exit;
}

require_once __DIR__ . '/../../config/database.php';

$userId = (int) $_SESSION['user_id'];

if (!function_exists('fmt_credits')) {
    function fmt_credits($n) {
        return rtrim(rtrim(number_format((float)$n, 2, '.', ''), '0'), '.');
    }
}

// Fetch this staff member's full record
$stmt = $pdo->prepare("
    SELECT 
        e.id AS employee_id,
        e.first_name, e.middle_name, e.last_name, e.email,
        e.category, e.sub_category, e.year_level, e.position, e.employment_type,
        e.leave_credits, e.leave_pay
    FROM users u
    LEFT JOIN employees e ON u.employee_id = e.id
    WHERE u.id = ?
    LIMIT 1
");
$stmt->execute([$userId]);
$me = $stmt->fetch();

if (!$me || $me['sub_category'] !== 'Accounting Section') {
    header('Location: /staff/dashboard');
    exit;
}

$firstName = $me['first_name'] ?? ($_SESSION['first_name'] ?? 'Staff');
$lastName  = $me['last_name']  ?? ($_SESSION['last_name']  ?? '');
$initials  = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
$employeeId = (int) ($me['employee_id'] ?? 0);
$isPartTime = (($me['employment_type'] ?? 'full-time') === 'part-time');
$leavePayRate = (float) ($me['leave_pay'] ?? 0);

// ============================================
// Leave stats
// ============================================
$creditsUsed = 0;
$typeUsed = [
    'vacation' => 0, 'sick' => 0, 'maternity' => 0,
    'paternity' => 0, 'terminal' => 0, 'other' => 0,
];
$lwopDays = 0;
$statusCounts = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'cancelled' => 0];
$recentLeaves = [];

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
    $creditsUsed = (float) ($stats['credits_used'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT id, leave_type, start_date, end_date, days_count, status, created_at
        FROM leave_requests
        WHERE employee_id = ?
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$employeeId]);
    $recentLeaves = $stmt->fetchAll();

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

    // LWOP days
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(days_count), 0)
        FROM leave_requests
        WHERE employee_id = ? AND status = 'approved' AND leave_type = 'other'
    ");
    $stmt->execute([$employeeId]);
    $lwopDays = (float) $stmt->fetchColumn();
}

// ============================================
// Payroll settlement — how much of the convertible has been paid
// (mirrors faculty/staff dashboards)
// ============================================
$settlementStatus  = null;   // 'settled' | 'outstanding' | null
$settlementAmount  = 0.0;
$settledAt         = null;

if ($employeeId > 0) {
    $stmt = $pdo->prepare("
        SELECT status, amount, settled_at
        FROM payroll_settlements
        WHERE employee_id = ?
        ORDER BY settled_at DESC
        LIMIT 1
    ");
    $stmt->execute([$employeeId]);
    $settlement = $stmt->fetch();

    if ($settlement) {
        $settlementStatus = $settlement['status'];
        $settlementAmount = (float) $settlement['amount'];
        $settledAt        = $settlement['settled_at'] ?? null;
    }
}

$totalCredits     = (float)($me['leave_credits'] ?? 0);
$leaveCreditsLeft = max(0, $totalCredits - $creditsUsed);
$otherUsed        = $typeUsed['maternity'] + $typeUsed['paternity'] + $typeUsed['terminal'] + $typeUsed['other'];
$usedPct          = $totalCredits > 0 ? min(100, round(($creditsUsed / $totalCredits) * 100, 1)) : 0;

$typeCaps = ['vacation' => 7.5, 'sick' => 7.5];
$typeRemaining = [];
foreach ($typeCaps as $t => $cap) {
    $typeRemaining[$t] = max(0, $cap - ($typeUsed[$t] ?? 0));
}

// ============================================
// Sick-only convertible calculation
// ============================================
$sickCap       = 7.5;
$sickUsed      = (float) ($typeUsed['sick'] ?? 0);
$sickRemaining = max(0, $sickCap - $sickUsed);

// Full potential convertible
$potentialConvertible = $leavePayRate * $sickRemaining;

// ============================================
// Adjust based on settlement
// — If Accounting marked this employee as 'settled' (Paid),
//   the convertible amount is fully consumed → show ₱0.00.
// — If 'outstanding' (Unpaid), the full convertible is still owed.
// ============================================
$convertibleLeavePay = $potentialConvertible;
$isFullySettled      = false;

if ($settlementStatus === 'settled') {
    $convertibleLeavePay = 0;
    $isFullySettled      = true;
} elseif ($settlementStatus === 'outstanding') {
    $convertibleLeavePay = $potentialConvertible;
}

$leaveDeduction = $leavePayRate * $lwopDays;

// ============================================
// PAYROLL SUMMARY (accountant-only)
// ============================================
$payrollTotalLiability    = 0;
$payrollTotalSettled      = 0;
$payrollTotalPending      = 0;
$payrollPendingCount      = 0;
$payrollSettledCount      = 0;
$payrollSettledThisMonth  = 0;

$stmt = $pdo->query("
    SELECT 
        e.id,
        e.first_name, e.last_name,
        e.leave_pay,
        e.leave_credits,
        COALESCE(SUM(CASE WHEN lr.status = 'approved' THEN lr.days_count ELSE 0 END), 0) AS used,
        COALESCE(SUM(CASE WHEN lr.status = 'approved' AND lr.leave_type = 'sick' THEN lr.days_count ELSE 0 END), 0) AS sick_used
    FROM employees e
    LEFT JOIN leave_requests lr ON lr.employee_id = e.id
    WHERE e.category IN ('faculty', 'staff')
    GROUP BY e.id
");
$perEmployee = $stmt->fetchAll();

$sickCap = 7.5;
foreach ($perEmployee as $row) {
    $sickRemainingEmp = max(0, $sickCap - (float)$row['sick_used']);
    $payrollTotalLiability += (float)$row['leave_pay'] * $sickRemainingEmp;
}

$stmt = $pdo->query("
    SELECT 
        COALESCE(SUM(CASE WHEN status = 'settled' THEN amount ELSE 0 END), 0) AS settled_total,
        COALESCE(SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END), 0) AS pending_total,
        COALESCE(SUM(CASE WHEN status = 'settled' THEN 1 ELSE 0 END), 0) AS settled_count,
        COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) AS pending_count,
        COALESCE(SUM(CASE WHEN status = 'settled' AND DATE(settled_at) >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN amount ELSE 0 END), 0) AS settled_this_month
    FROM payroll_settlements
");
$settlementStats = $stmt->fetch() ?: [];
$payrollTotalSettled     = (float) ($settlementStats['settled_total'] ?? 0);
$payrollTotalPending     = (float) ($settlementStats['pending_total'] ?? 0);
$payrollSettledCount     = (int) ($settlementStats['settled_count'] ?? 0);
$payrollPendingCount     = (int) ($settlementStats['pending_count'] ?? 0);
$payrollSettledThisMonth = (float) ($settlementStats['settled_this_month'] ?? 0);

// ============================================
// Monthly trend
// ============================================
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
    <title>Accounting Dashboard | DAMMC</title>
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
        $activePage = 'accounting_dashboard';
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

                <!-- PAYROLL SUMMARY -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
                    <div class="animate-fade-in-up bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3 mb-2">
                            <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/50 font-semibold">Total Convertible Leave Pay (Owed)</p>
                            <div class="flex items-center gap-1.5 shrink-0">
                                <button type="button" onclick="toggleAmountVisibility()"
                                    class="w-8 h-8 rounded-lg bg-[#F1FDF6] flex items-center justify-center text-[#0F5E3D] hover:bg-[#e8faf0] transition amount-toggle-btn"
                                    title="Show / hide amount" aria-label="Toggle amount visibility">
                                    <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                </button>
                            </div>
                        </div>
                        <p id="payrollLiability" class="text-2xl font-bold text-[#2C3E50] transition-colors"
                           data-amount="<?= number_format($payrollTotalLiability, 2, '.', '') ?>">
                            ₱<?= number_format($payrollTotalLiability, 2) ?>
                        </p>
                        <p class="text-[11px] text-[#2C3E50]/50 mt-1">Convertible from unused sick credits (cap 7.5)</p>
                    </div>

                    <div class="animate-fade-in-up-delay-1 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3 mb-2">
                            <p class="text-[10px] uppercase tracking-wider text-[#0F5E3D]/70 font-semibold">Paid Out This Month</p>
                            <div class="flex items-center gap-1.5 shrink-0">
                                <button type="button" onclick="toggleAmountVisibility()"
                                    class="w-8 h-8 rounded-lg bg-[#F1FDF6] flex items-center justify-center text-[#0F5E3D] hover:bg-[#e8faf0] transition amount-toggle-btn"
                                    title="Show / hide amount" aria-label="Toggle amount visibility">
                                    <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                </button>
                            </div>
                        </div>
                        <p id="payrollSettledMonth" class="text-2xl font-bold text-[#0F5E3D] transition-colors"
                           data-amount="<?= number_format($payrollSettledThisMonth, 2, '.', '') ?>">
                            ₱<?= number_format($payrollSettledThisMonth, 2) ?>
                        </p>
                        <p class="text-[11px] text-[#2C3E50]/50 mt-1"><?= $payrollSettledCount ?> settlement(s) total</p>
                    </div>

                    <div class="animate-fade-in-up-delay-2 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3 mb-2">
                            <p class="text-[10px] uppercase tracking-wider text-[#0F5E3D]/70 font-semibold">Paid Out This Month</p>
                            <div class="flex items-center gap-1.5 shrink-0">
                                <button type="button" onclick="toggleAmountVisibility()"
                                    class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center text-amber-600 hover:bg-amber-100 transition amount-toggle-btn"
                                    title="Show / hide amount" aria-label="Toggle amount visibility">
                                    <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                </button>
                            </div>
                        </div>
                        <p id="payrollPending" class="text-2xl font-bold text-amber-600 transition-colors"
                           data-amount="<?= number_format($payrollTotalPending, 2, '.', '') ?>">
                            ₱<?= number_format($payrollTotalPending, 2) ?>
                        </p>
                        <p class="text-[11px] text-[#2C3E50]/50 mt-1"><?= $payrollPendingCount ?> awaiting settlement</p>
                    </div>
                </div>

                <!-- HERO ROW -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 sm:gap-4 items-stretch">

                    <div class="lg:col-span-2 animate-fade-in-up bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-5 sm:p-6 shadow-sm flex flex-col">
                        <div class="flex items-start justify-between gap-4 mb-4 flex-wrap">
                            <div class="min-w-0">
                                <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/50 font-semibold mb-1">My Leave Credits</p>
                                <div class="flex items-baseline gap-2">
                                    <span class="text-3xl sm:text-4xl font-bold text-[#0F5E3D]"><?= number_format($leaveCreditsLeft, 0) ?></span>
                                    <span class="text-sm text-[#2C3E50]/50">/ <?= number_format($totalCredits, 0) ?> remaining</span>
                                </div>

                                <div class="flex items-center gap-1.5 flex-wrap mt-2">
                                    <?php if (!empty($me['category'])): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium text-[#0F5E3D] bg-[#F1FDF6] border border-[#0F5E3D]/10">
                                            <i data-lucide="layers" class="w-2.5 h-2.5"></i>
                                            <?= htmlspecialchars(ucfirst($me['category'])) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($me['sub_category'])): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium text-[#2C3E50]/70 bg-white border border-[#E0E0E0]">
                                            <i data-lucide="building-2" class="w-2.5 h-2.5"></i>
                                            <?= htmlspecialchars($me['sub_category']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($me['position'])): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium text-[#2C3E50]/70 bg-white border border-[#E0E0E0]">
                                            <i data-lucide="badge-check" class="w-2.5 h-2.5"></i>
                                            <?= htmlspecialchars($me['position']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <?php if (!$isPartTime): ?>
                                    <a href="/staff/leaves"
                                        class="hidden sm:inline-flex items-center gap-1.5 bg-[#0F5E3D] hover:bg-[#0a4a2f] active:scale-[0.99] text-white font-medium py-2 px-3.5 rounded-lg transition text-xs shadow-sm hover:shadow-md whitespace-nowrap">
                                        <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                                        <span>File a Leave</span>
                                    </a>
                                <?php endif; ?>
                                <div class="w-10 h-10 rounded-lg bg-[#F1FDF6] flex items-center justify-center text-[#0F5E3D] shrink-0">
                                    <i data-lucide="calendar-check" class="w-5 h-5"></i>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="flex items-center justify-between text-[11px] text-[#2C3E50]/60 mb-1.5">
                                <span><?= number_format($creditsUsed, 0) ?> used</span>
                                <span><?= $usedPct ?>%</span>
                            </div>
                            <div class="h-2 bg-[#F1FDF6] rounded-full overflow-hidden">
                                <div class="h-full bg-[#0F5E3D] rounded-full transition-all duration-500" style="width: <?= $usedPct ?>%"></div>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-3 pt-4 border-t border-[#E0E0E0]">
                            <div>
                                <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-0.5">Allocated</p>
                                <p class="text-sm font-bold text-[#2C3E50]"><?= number_format($totalCredits, 0) ?></p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-0.5">Used</p>
                                <p class="text-sm font-bold text-amber-600"><?= number_format($creditsUsed, 0) ?></p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-0.5">Remaining</p>
                                <p class="text-sm font-bold text-[#0F5E3D]"><?= number_format($leaveCreditsLeft, 0) ?></p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3 pt-4 mt-1 border-t border-[#E0E0E0] flex-1 content-start">
                            <?php
                            $vUsed = (float) ($typeUsed['vacation'] ?? 0);
                            $vCap  = (float) ($typeCaps['vacation'] ?? 0);
                            $vLeft = (float) ($typeRemaining['vacation'] ?? 0);
                            $vPct  = $vCap > 0 ? min(100, round(($vUsed / $vCap) * 100, 1)) : 0;
                            ?>
                            <div class="rounded-lg bg-[#F1FDF6]/60 border border-[#0F5E3D]/10 p-3">
                                <div class="flex items-center gap-2 mb-2">
                                    <div class="w-6 h-6 rounded-md bg-white flex items-center justify-center text-[#0F5E3D] shrink-0">
                                        <i data-lucide="plane" class="w-3 h-3"></i>
                                    </div>
                                    <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/60 font-semibold">Vacation</p>
                                </div>
                                <div class="flex items-baseline gap-1 mb-1.5">
                                    <span class="text-lg font-bold text-[#0F5E3D]"><?= fmt_credits($vLeft) ?></span>
                                    <span class="text-[10px] text-[#2C3E50]/50">/ <?= fmt_credits($vCap) ?> day(s)</span>
                                </div>
                                <div class="h-1.5 bg-white rounded-full overflow-hidden">
                                    <div class="h-full bg-[#0F5E3D] rounded-full transition-all duration-500" style="width: <?= $vPct ?>%"></div>
                                </div>
                            </div>

                            <?php
                            $sUsed = (float) ($typeUsed['sick'] ?? 0);
                            $sCap  = (float) ($typeCaps['sick'] ?? 0);
                            $sLeft = (float) ($typeRemaining['sick'] ?? 0);
                            $sPct  = $sCap > 0 ? min(100, round(($sUsed / $sCap) * 100, 1)) : 0;
                            ?>
                            <div class="rounded-lg bg-amber-50/60 border border-amber-500/10 p-3">
                                <div class="flex items-center gap-2 mb-2">
                                    <div class="w-6 h-6 rounded-md bg-white flex items-center justify-center text-amber-600 shrink-0">
                                        <i data-lucide="thermometer" class="w-3 h-3"></i>
                                    </div>
                                    <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/60 font-semibold">Sick</p>
                                </div>
                                <div class="flex items-baseline gap-1 mb-1.5">
                                    <span class="text-lg font-bold text-amber-600"><?= fmt_credits($sLeft) ?></span>
                                    <span class="text-[10px] text-[#2C3E50]/50">/ <?= fmt_credits($sCap) ?> day(s)</span>
                                </div>
                                <div class="h-1.5 bg-white rounded-full overflow-hidden">
                                    <div class="h-full bg-amber-500 rounded-full transition-all duration-500" style="width: <?= $sPct ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 sm:gap-4 h-full">
                        <!-- Convertible Leave Pay — with show/hide -->
                        <div class="animate-fade-in-up-delay-1 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-5 shadow-sm flex flex-col flex-1 justify-center">
                            <div class="flex items-start justify-between gap-4 mb-3">
                                <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/50 font-semibold">Convertible Leave Pay</p>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <button type="button" onclick="toggleAmountVisibility()"
                                        class="w-9 h-9 rounded-lg bg-amber-50 flex items-center justify-center text-amber-600 hover:bg-amber-100 transition amount-toggle-btn"
                                        title="Show / hide amount" aria-label="Toggle amount visibility">
                                        <i data-lucide="eye" class="w-4 h-4"></i>
                                    </button>
                                </div>
                            </div>
                            <span id="convertiblePayAmount"
                                class="text-2xl sm:text-3xl font-bold <?= $isFullySettled ? 'text-[#0F5E3D]' : 'text-amber-600' ?> transition-colors"
                                data-amount="<?= number_format($convertibleLeavePay, 2, '.', '') ?>">
                                ₱<?= number_format($convertibleLeavePay, 2) ?>
                            </span>

                            <?php if ($isFullySettled): ?>
                                <p class="text-[10px] text-[#0F5E3D] mt-1.5 inline-flex items-center gap-1">
                                    <i data-lucide="check-circle-2" class="w-3 h-3"></i>
                                    <span>Fully settled<?= $settledAt ? ' · ' . date('M j, Y', strtotime($settledAt)) : '' ?></span>
                                </p>
                            <?php elseif ($settlementStatus === 'settled' && $settlementAmount > 0): ?>
                                <p class="text-[10px] text-[#2C3E50]/50 mt-1.5">
                                    ₱<?= number_format($settlementAmount, 2) ?> already paid · balance shown above
                                </p>
                            <?php elseif ($settlementStatus === 'outstanding'): ?>
                                <p class="text-[10px] text-amber-600 mt-1.5 inline-flex items-center gap-1">
                                    <i data-lucide="clock" class="w-3 h-3"></i>
                                    <span>Marked unpaid by Accounting</span>
                                </p>
                            <?php endif; ?>
                        </div>

                        <!-- My Leave Deduction — with show/hide -->
                        <div class="animate-fade-in-up-delay-2 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-5 shadow-sm flex flex-col flex-1 justify-center">
                            <div class="flex items-start justify-between gap-4 mb-3">
                                <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/50 font-semibold">My Leave Deduction</p>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <button type="button" onclick="toggleAmountVisibility()"
                                        class="w-9 h-9 rounded-lg bg-amber-50 flex items-center justify-center text-amber-600 hover:bg-amber-100 transition amount-toggle-btn"
                                        title="Show / hide amount" aria-label="Toggle amount visibility">
                                        <i data-lucide="eye" class="w-4 h-4"></i>
                                    </button>
                                </div>
                            </div>
                            <span id="deductionPayAmount" class="text-2xl sm:text-3xl font-bold text-[#2C3E50] transition-colors"
                                data-amount="<?= number_format($leaveDeduction, 2, '.', '') ?>">
                                ₱<?= number_format($leaveDeduction, 2) ?>
                            </span>
                            <p class="text-[10px] text-[#2C3E50]/50 mt-1.5">
                                <?php if ($leavePayRate > 0 && $lwopDays > 0): ?>
                                    <?= fmt_credits($lwopDays) ?> day(s) × ₱<?= number_format($leavePayRate, 2) ?>/credit
                                <?php elseif ($lwopDays <= 0): ?>
                                    no leave-without-pay days
                                <?php else: ?>
                                    no leave pay rate on file
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- PAYROLL CHARTS -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 sm:gap-4">
                    <div class="lg:col-span-2 animate-fade-in-up-delay-2 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-5 shadow-sm">
                        <div class="flex items-start justify-between mb-3">
                            <div>
                                <h2 class="text-sm font-bold text-[#2C3E50]">Payroll Liability</h2>
                                <p class="text-[10px] text-[#2C3E50]/50 mt-0.5">Convertible sick credits per department · live</p>
                            </div>
                            <a href="/accounting/payroll"
                                class="text-[11px] font-medium text-[#0F5E3D] hover:text-[#0a4a2f] transition">
                                Open payroll →
                            </a>
                        </div>
                        <div class="relative h-48">
                            <canvas id="payrollChart"></canvas>
                        </div>
                    </div>

                    <div class="animate-fade-in-up-delay-2 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-5 shadow-sm">
                        <div class="mb-3">
                            <h2 class="text-sm font-bold text-[#2C3E50]">Settlement Status</h2>
                            <p class="text-[10px] text-[#2C3E50]/50 mt-0.5">All-time totals</p>
                        </div>
                        <div class="relative h-48">
                            <canvas id="settlementChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Leave Breakdown + Trend -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 sm:gap-4">
                    <div class="animate-fade-in-up-delay-2 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-5 shadow-sm">
                        <div class="mb-3">
                            <h2 class="text-sm font-bold text-[#2C3E50]">My Leave Breakdown</h2>
                            <p class="text-[10px] text-[#2C3E50]/50 mt-0.5">Approved days by type</p>
                        </div>
                        <div class="relative h-40">
                            <canvas id="breakdownChart"></canvas>
                        </div>
                        <div class="grid grid-cols-3 gap-2 mt-4 pt-3 border-t border-[#E0E0E0]">
                            <div class="text-center">
                                <span class="inline-block w-2 h-2 rounded-full bg-blue-500 mb-1"></span>
                                <p class="text-[10px] text-[#2C3E50]/50 uppercase tracking-wider">Vacation</p>
                                <p class="text-xs font-bold text-[#2C3E50]"><?= fmt_credits($typeUsed['vacation']) ?></p>
                            </div>
                            <div class="text-center">
                                <span class="inline-block w-2 h-2 rounded-full bg-rose-500 mb-1"></span>
                                <p class="text-[10px] text-[#2C3E50]/50 uppercase tracking-wider">Sick</p>
                                <p class="text-xs font-bold text-[#2C3E50]"><?= fmt_credits($typeUsed['sick']) ?></p>
                            </div>
                            <div class="text-center">
                                <span class="inline-block w-2 h-2 rounded-full bg-purple-500 mb-1"></span>
                                <p class="text-[10px] text-[#2C3E50]/50 uppercase tracking-wider">Other</p>
                                <p class="text-xs font-bold text-[#2C3E50]"><?= fmt_credits($otherUsed) ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="lg:col-span-2 animate-fade-in-up-delay-2 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-5 shadow-sm">
                        <div class="flex items-start justify-between mb-3">
                            <div>
                                <h2 class="text-sm font-bold text-[#2C3E50]">My Usage Trend</h2>
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

                <!-- Recent Leaves + My Info -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6">
                    <div class="animate-fade-in-up-delay-3 lg:col-span-2 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-5 sm:p-6 shadow-sm">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h2 class="text-base sm:text-lg font-bold text-[#2C3E50]">My Recent Leaves</h2>
                                <p class="text-xs text-[#2C3E50]/50 mt-0.5">Your last 5 submissions</p>
                            </div>
                            <a href="/staff/leaves" class="text-xs font-medium text-[#0F5E3D] hover:text-[#0a4a2f] transition">View all →</a>
                        </div>

                        <?php if (empty($recentLeaves)): ?>
                            <div class="py-10 text-center">
                                <div class="w-12 h-12 rounded-full bg-[#F1FDF6] flex items-center justify-center mx-auto mb-3 text-[#0F5E3D]">
                                    <i data-lucide="calendar" class="w-5 h-5"></i>
                                </div>
                                <p class="text-sm font-medium text-[#2C3E50]">No leave requests yet</p>
                                <p class="text-xs text-[#2C3E50]/50 mt-1">File your first leave and it'll show up here.</p>
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
                                            <p class="text-sm font-medium text-[#2C3E50] capitalize"><?= htmlspecialchars($l['leave_type']) ?></p>
                                            <p class="text-xs text-[#2C3E50]/50">
                                                <?= date('M j, Y', strtotime($l['start_date'])) ?>
                                                <?php if ($l['end_date'] !== $l['start_date']): ?>
                                                    → <?= date('M j, Y', strtotime($l['end_date'])) ?>
                                                <?php endif; ?>
                                                · <?= fmt_credits($l['days_count']) ?> day(s)
                                            </p>
                                        </div>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium <?= $sClass ?> shrink-0"><?= $sLabel ?></span>
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
                                <dt class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-0.5">Department</dt>
                                <dd class="text-[#2C3E50]"><?= htmlspecialchars($me['sub_category'] ?? '—') ?></dd>
                            </div>
                            <div>
                                <dt class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-0.5">Position</dt>
                                <dd class="text-[#2C3E50]"><?= htmlspecialchars($me['position'] ?? '—') ?></dd>
                            </div>
                            <div>
                                <dt class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-0.5">Employment</dt>
                                <dd class="text-[#2C3E50] capitalize"><?= htmlspecialchars($me['employment_type'] ?? '—') ?></dd>
                            </div>
                            <div>
                                <dt class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-0.5">Leave Pay Rate</dt>
                                <dd class="text-[#2C3E50]">₱<?= number_format($leavePayRate, 2) ?> / credit</dd>
                            </div>
                        </dl>

                        <div class="mt-5 pt-4 border-t border-[#E0E0E0] space-y-2">
                            <?php if (!$isPartTime): ?>
                                <a href="/staff/leaves"
                                    class="w-full flex items-center justify-center gap-2 bg-[#0F5E3D] hover:bg-[#0a4a2f] active:scale-[0.99] text-white font-medium py-2.5 rounded-lg transition text-sm shadow-sm">
                                    <i data-lucide="file-plus" class="w-4 h-4"></i>
                                    File a Leave
                                </a>
                            <?php endif; ?>
                            <a href="/staff/profile"
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
        },
        payroll: {
            settled: <?= (float) $payrollTotalSettled ?>,
            pending: <?= (float) $payrollTotalPending ?>,
            liability: <?= (float) $payrollTotalLiability ?>
        }
    };

    // ============================================
    // Show / hide monetary amounts (banking-app style)
    // Persists via localStorage; toggles all 5 amounts together.
    // ============================================
    (function () {
        const STORAGE_KEY = 'dammc_amounts_hidden';
        const MASK = '₱••••••';

        // All amount elements on this page (aggregate + personal)
        const AMOUNT_IDS = [
            'payrollLiability',      // Total Payroll Liability
            'payrollSettledMonth',   // Settled This Month
            'payrollPending',        // Pending Settlement
            'convertiblePayAmount',  // Convertible Leave Pay
            'deductionPayAmount'     // My Leave Deduction
        ];

        function isHidden() {
            return localStorage.getItem(STORAGE_KEY) === '1';
        }

        function setHidden(hidden) {
            localStorage.setItem(STORAGE_KEY, hidden ? '1' : '0');
            apply();
        }

        function apply() {
            const hidden = isHidden();

            AMOUNT_IDS.forEach((id) => {
                const el = document.getElementById(id);
                if (!el) return;
                const real = el.dataset.amount || '0';

                if (hidden) {
                    el.textContent = MASK;
                    el.classList.add('text-[#2C3E50]/40');
                    el.classList.remove('text-amber-600', 'text-[#2C3E50]', 'text-[#0F5E3D]');
                } else {
                    const formatted = parseFloat(real).toLocaleString('en-PH', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                    el.textContent = '₱' + formatted;
                    el.classList.remove('text-[#2C3E50]/40');

                    // Restore original color per element
                    if (id === 'payrollLiability') {
                        el.classList.add('text-[#2C3E50]');
                    } else if (id === 'payrollSettledMonth') {
                        el.classList.add('text-[#0F5E3D]');
                    } else if (id === 'payrollPending') {
                        el.classList.add('text-amber-600');
                    } else if (id === 'convertiblePayAmount') {
                        // Green if fully settled (₱0.00), amber otherwise
                        const isSettled = parseFloat(real) === 0;
                        el.classList.add(isSettled ? 'text-[#0F5E3D]' : 'text-amber-600');
                    } else {
                        el.classList.add('text-[#2C3E50]');
                    }
                }
            });

            // Swap all toggle button icons
            document.querySelectorAll('.amount-toggle-btn').forEach((btn) => {
                btn.innerHTML = hidden
                    ? '<i data-lucide="eye-off" class="w-3.5 h-3.5"></i>'
                    : '<i data-lucide="eye" class="w-3.5 h-3.5"></i>';
                btn.title = hidden ? 'Show amounts' : 'Hide amounts';
            });

            if (typeof initIcons === 'function') initIcons();
        }

        window.toggleAmountVisibility = function () {
            setHidden(!isHidden());
        };

        document.addEventListener('DOMContentLoaded', apply);
    })();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="/assets/js/app.js"></script>
    <script src="/assets/js/notifications.js"></script>
    <script src="/assets/js/profile.js"></script>
    <script src="/assets/js/accounting_dashboard.js"></script>
</body>
</html>