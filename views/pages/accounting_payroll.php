<?php
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: /login');
    exit;
}

require_once __DIR__ . '/../../config/database.php';

$userId = (int) $_SESSION['user_id'];
$workingDaysPerMonth = 22;

$stmt = $pdo->prepare("
    SELECT e.sub_category
    FROM users u
    LEFT JOIN employees e ON u.employee_id = e.id
    WHERE u.id = ?
    LIMIT 1
");
$stmt->execute([$userId]);
if (($stmt->fetchColumn() ?: '') !== 'Accounting Section') {
    header('Location: /staff/dashboard');
    exit;
}

// ============================================
// Filters & Search
// ============================================
$searchQuery = trim($_GET['q'] ?? '');
$searchQuery = substr($searchQuery, 0, 100);
$statusFilter = $_GET['settlement_status'] ?? '';
$categoryFilter = $_GET['category'] ?? '';

$whereClauses = ["e.category IN ('faculty', 'staff')"];
$bindings = [];

if ($searchQuery !== '') {
    $whereClauses[] = "(
        e.first_name LIKE ?
        OR e.last_name LIKE ?
        OR CONCAT_WS(' ', e.first_name, e.last_name) LIKE ?
    )";
    $like = '%' . $searchQuery . '%';
    $bindings = array_merge($bindings, [$like, $like, $like]);
}

if ($statusFilter === 'none') {
    $whereClauses[] = "ps.status IS NULL";
} elseif (in_array($statusFilter, ['settled', 'outstanding'], true)) {
    $whereClauses[] = "ps.status = ?";
    $bindings[] = $statusFilter;
}

if (in_array($categoryFilter, ['faculty', 'staff'], true)) {
    $whereClauses[] = "e.category = ?";
    $bindings[] = $categoryFilter;
}

$whereSql = ' WHERE ' . implode(' AND ', $whereClauses);

// ============================================
// Pagination
// ============================================
$perPage = 10;
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM employees e
    LEFT JOIN (
        SELECT ps1.*
        FROM payroll_settlements ps1
        INNER JOIN (
            SELECT employee_id, MAX(settled_at) AS max_settled_at
            FROM payroll_settlements
            GROUP BY employee_id
        ) ps2 ON ps2.employee_id = ps1.employee_id
             AND ps2.max_settled_at = ps1.settled_at
    ) ps ON ps.employee_id = e.id
    {$whereSql}
");
$countStmt->execute($bindings);
$totalEmployees = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalEmployees / $perPage));

if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$baseQs = '';
if ($searchQuery !== '')
    $baseQs .= '&q=' . urlencode($searchQuery);
if ($statusFilter !== '')
    $baseQs .= '&settlement_status=' . urlencode($statusFilter);
if ($categoryFilter !== '')
    $baseQs .= '&category=' . urlencode($categoryFilter);

// ============================================
// Fetch current page
// ============================================
$stmt = $pdo->prepare("
    SELECT
        e.id AS employee_id,
        e.first_name, e.middle_name, e.last_name, e.email,
        e.category, e.sub_category, e.position,
        e.employment_type,
        e.basic_salary, e.leave_credits, e.leave_pay,
        COALESCE(lr.approved_days, 0) AS approved_days,
        COALESCE(lr.sick_used, 0) AS sick_used,
        COALESCE(lr.lwop_days, 0) AS lwop_days,
        ps.status AS settlement_status,
        ps.amount AS settlement_amount,
        ps.settled_at,
        ps.remarks AS settlement_remarks
    FROM employees e
    LEFT JOIN (
        SELECT
            employee_id,
            SUM(CASE WHEN status = 'approved' THEN days_count ELSE 0 END) AS approved_days,
            SUM(CASE WHEN status = 'approved' AND leave_type = 'sick' THEN days_count ELSE 0 END) AS sick_used,
            SUM(CASE WHEN status = 'approved' AND leave_type = 'other' THEN days_count ELSE 0 END) AS lwop_days
        FROM leave_requests
        GROUP BY employee_id
    ) lr ON lr.employee_id = e.id
    LEFT JOIN (
        SELECT ps1.*
        FROM payroll_settlements ps1
        INNER JOIN (
            SELECT employee_id, MAX(settled_at) AS max_settled_at
            FROM payroll_settlements
            GROUP BY employee_id
        ) ps2 ON ps2.employee_id = ps1.employee_id
             AND ps2.max_settled_at = ps1.settled_at
    ) ps ON ps.employee_id = e.id
    {$whereSql}
    ORDER BY e.last_name ASC, e.first_name ASC
    LIMIT ? OFFSET ?
");

$i = 1;
foreach ($bindings as $b) {
    $stmt->bindValue($i++, $b, PDO::PARAM_STR);
}
$stmt->bindValue($i++, $perPage, PDO::PARAM_INT);
$stmt->bindValue($i++, $offset, PDO::PARAM_INT);
$stmt->execute();
$employees = $stmt->fetchAll();

// ============================================
// Compute rows
// ============================================
$rows = [];
foreach ($employees as $e) {
    $salary = (float) $e['basic_salary'];
    $leavePayRate = (float) $e['leave_pay'];
    $allocated = (float) $e['leave_credits'];
    $approved = (float) $e['approved_days'];
    $sickUsed = (float) $e['sick_used'];
    $lwopDays = (float) $e['lwop_days'];
    $remaining = max(0, $allocated - $approved);

    $dailyRate = $salary > 0 ? $salary / $workingDaysPerMonth : 0;
    $lwopDeduction = $dailyRate * $lwopDays;

    // Potential convertible = remaining SICK credits × Leave Pay Rate
    $sickCap = 7.5;
    $sickRemaining = max(0, $sickCap - $sickUsed);
    $potentialConvertible = $leavePayRate * $sickRemaining;

    // Adjust convertible based on settlement:
    // - Not settled → show full potential
    // - Settled fully → show 0
    // - Partially settled → show balance
    $statusKey = $e['settlement_status'] ?? 'none';
    $settledAmount = (float) ($e['settlement_amount'] ?? 0);
    $convertibleLeavePay = $potentialConvertible;
    $isFullySettled = false;

    if ($statusKey === 'settled') {
        if ($settledAmount >= $potentialConvertible && $potentialConvertible > 0) {
            $convertibleLeavePay = 0;
            $isFullySettled = true;
        } else {
            $convertibleLeavePay = max(0, $potentialConvertible - $settledAmount);
        }
    }

    $rows[] = [
        'employee_id' => (int) $e['employee_id'],
        'name' => trim(($e['first_name'] ?? '') . ' ' . ($e['last_name'] ?? '')) ?: 'Unknown',
        'initials' => strtoupper(substr($e['first_name'] ?? 'U', 0, 1) . substr($e['last_name'] ?? '', 0, 1)),
        'email' => $e['email'] ?? '—',
        'category' => $e['category'] ?? 'staff',
        'sub_category' => $e['sub_category'] ?? '',
        'position' => $e['position'] ?? '',
        'employment' => $e['employment_type'] ?? 'full-time',
        'monthly_salary' => $salary,
        'allocated_credits' => $allocated,
        'approved_days' => $approved,
        'remaining_credits' => $remaining,
        'sick_remaining' => $sickRemaining,
        'lwop_days' => $lwopDays,
        'lwop_deduction' => $lwopDeduction,
        'leave_pay_rate' => $leavePayRate,
        'potential_convertible' => $potentialConvertible,
        'convertible_leave_pay' => $convertibleLeavePay,
        'is_fully_settled' => $isFullySettled,
        'settlement_status' => $e['settlement_status'],
        'settlement_amount' => $settledAmount,
        'settled_at' => $e['settled_at'] ?? null,
        'settlement_remarks' => $e['settlement_remarks'] ?? '',
    ];
}

// ============================================
// Fetch full settlement history for employees on this page
// ============================================
$employeeIds = array_column($rows, 'employee_id');
$settlementHistory = [];

if (!empty($employeeIds)) {
    $placeholders = implode(',', array_fill(0, count($employeeIds), '?'));
    $stmt = $pdo->prepare("
        SELECT employee_id, status, amount, settled_at, remarks
        FROM payroll_settlements
        WHERE employee_id IN ($placeholders)
        ORDER BY settled_at DESC
    ");
    $stmt->execute($employeeIds);

    foreach ($stmt->fetchAll() as $h) {
        $settlementHistory[(int) $h['employee_id']][] = [
            'status' => $h['status'],
            'amount' => (float) $h['amount'],
            'settled_at' => $h['settled_at'],
            'remarks' => $h['remarks'] ?? '',
        ];
    }
}

// Attach history to each row
foreach ($rows as &$r) {
    $r['settlement_history'] = $settlementHistory[$r['employee_id']] ?? [];
}
unset($r);

// ============================================
// Global aggregates
// ============================================
$agg = $pdo->query("
    SELECT
        e.id,
        e.category,
        e.basic_salary,
        e.leave_credits,
        e.leave_pay,
        COALESCE(lr.approved_days, 0) AS approved_days,
        COALESCE(lr.sick_used, 0) AS sick_used,
        COALESCE(lr.lwop_days, 0) AS lwop_days,
        ps.status AS settlement_status,
        ps.amount AS settlement_amount
    FROM employees e
    LEFT JOIN (
        SELECT
            employee_id,
            SUM(CASE WHEN status = 'approved' THEN days_count ELSE 0 END) AS approved_days,
            SUM(CASE WHEN status = 'approved' AND leave_type = 'sick' THEN days_count ELSE 0 END) AS sick_used,
            SUM(CASE WHEN status = 'approved' AND leave_type = 'other' THEN days_count ELSE 0 END) AS lwop_days
        FROM leave_requests
        GROUP BY employee_id
    ) lr ON lr.employee_id = e.id
    LEFT JOIN (
        SELECT ps1.*
        FROM payroll_settlements ps1
        INNER JOIN (
            SELECT employee_id, MAX(settled_at) AS max_settled_at
            FROM payroll_settlements
            GROUP BY employee_id
        ) ps2 ON ps2.employee_id = ps1.employee_id
             AND ps2.max_settled_at = ps1.settled_at
    ) ps ON ps.employee_id = e.id
    WHERE e.category IN ('faculty', 'staff')
")->fetchAll();

$totalSalaryPool = 0;
$totalLwopDeduction = 0;
$totalConvertibleLeavePay = 0;
$totalSettled = 0;
$totalOutstanding = 0;
$settledCount = 0;
$outstandingCount = 0;
$pendingCount = 0;

foreach ($agg as $a) {
    $salary = (float) $a['basic_salary'];
    $leavePayRate = (float) $a['leave_pay'];
    $sickUsed = (float) $a['sick_used'];
    $lwopDays = (float) $a['lwop_days'];

    $dailyRate = $salary > 0 ? $salary / $workingDaysPerMonth : 0;
    $lwopDeduction = $dailyRate * $lwopDays;

    $sickCap = 7.5;
    $sickRemaining = max(0, $sickCap - $sickUsed);
    $potentialConvertible = $leavePayRate * $sickRemaining;

    $statusKey = $a['settlement_status'];
    $settledAmount = (float) ($a['settlement_amount'] ?? 0);
    $convertibleLeavePay = $potentialConvertible;

    if ($statusKey === 'settled') {
        if ($settledAmount >= $potentialConvertible && $potentialConvertible > 0) {
            $convertibleLeavePay = 0;
        } else {
            $convertibleLeavePay = max(0, $potentialConvertible - $settledAmount);
        }
    }

    $totalSalaryPool += $salary;
    $totalLwopDeduction += $lwopDeduction;
    $totalConvertibleLeavePay += $convertibleLeavePay;

    if ($statusKey === 'settled') {
        $totalSettled += $settledAmount;
        $settledCount++;
    } elseif ($statusKey === 'outstanding') {
        // Outstanding = still-owed balance (not the recorded amount which may be 0)
        $totalOutstanding += $convertibleLeavePay;
        $outstandingCount++;
    } else {
        $pendingCount++;
    }
}

$totalActiveEmployees = $settledCount + $outstandingCount + $pendingCount;

$activePage = 'accounting_payroll';
$pageTitle = 'Payroll';
$pageSubtitle = 'Leave liability payments across faculty & staff';
$pageIcon = 'receipt';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll | DAMMC Accounting</title>
    <link rel="icon" type="image/png" href="/assets/images/dammc-logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>

<body class="bg-gradient-to-br from-[#F1FDF6] via-[#eafaf1] to-[#dcf3e5] min-h-screen">

    <div class="flex min-h-screen">
        <div id="sidebarOverlay" onclick="closeSidebar()"
            class="fixed inset-0 bg-black/40 z-40 hidden md:hidden backdrop-blur-sm"></div>

        <?php require __DIR__ . '/../components/sidebar.php'; ?>

        <div class="flex-1 md:ml-64 w-full min-w-0">

            <?php require __DIR__ . '/../components/topbar.php'; ?>

            <main class="p-4 sm:p-6 lg:p-8 space-y-4">

                <!-- SUMMARY CARDS -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <div class="bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/50 font-semibold">Total Salary
                                Pool</p>
                            <div
                                class="w-7 h-7 rounded-md bg-[#F1FDF6] flex items-center justify-center text-[#0F5E3D] shrink-0">
                                <i data-lucide="landmark" class="w-3.5 h-3.5"></i>
                            </div>
                        </div>
                        <p class="text-xl font-bold text-[#2C3E50]">₱<?= number_format($totalSalaryPool, 2) ?></p>
                    </div>

                    <div class="bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/50 font-semibold">Convertible
                                Leave Pay</p>
                            <div
                                class="w-7 h-7 rounded-md bg-[#F1FDF6] flex items-center justify-center text-[#0F5E3D] shrink-0">
                                <i data-lucide="banknote" class="w-3.5 h-3.5"></i>
                            </div>
                        </div>
                        <p class="text-xl font-bold text-[#0F5E3D]">₱<?= number_format($totalConvertibleLeavePay, 2) ?>
                        </p>
                    </div>

                    <div class="bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/50 font-semibold">Paid</p>
                            <div
                                class="w-7 h-7 rounded-md bg-[#F1FDF6] flex items-center justify-center text-[#0F5E3D] shrink-0">
                                <i data-lucide="check-circle-2" class="w-3.5 h-3.5"></i>
                            </div>
                        </div>
                        <p class="text-xl font-bold text-[#0F5E3D]">₱<?= number_format($totalSettled, 2) ?></p>
                    </div>

                    <div class="bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/50 font-semibold">Unpaid</p>
                            <div
                                class="w-7 h-7 rounded-md bg-amber-50 flex items-center justify-center text-amber-600 shrink-0">
                                <i data-lucide="clock" class="w-3.5 h-3.5"></i>
                            </div>
                        </div>
                        <p class="text-xl font-bold text-amber-600">₱<?= number_format($totalOutstanding, 2) ?></p>
                    </div>
                </div>

                <!-- TOOLBAR -->
                <form method="GET" action="/accounting/payroll" id="filterForm"
                    class="bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-3 sm:p-4 shadow-sm">
                    <div class="flex flex-col lg:flex-row gap-3 lg:items-center lg:justify-between">
                        <div class="flex flex-wrap items-center gap-2">
                            <select name="settlement_status" onchange="this.form.submit()"
                                class="px-3 py-1.5 rounded-lg text-xs font-medium text-[#2C3E50]/80 bg-[#F1FDF6] border border-[#E0E0E0] focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition cursor-pointer">
                                <option value="">All Statuses</option>
                                <option value="settled" <?= $statusFilter === 'settled' ? 'selected' : '' ?>>Paid</option>
                                <option value="outstanding" <?= $statusFilter === 'outstanding' ? 'selected' : '' ?>>Unpaid
                                </option>
                                <option value="none" <?= $statusFilter === 'none' ? 'selected' : '' ?>>Not Yet Recorded
                                </option>
                            </select>

                            <select name="category" onchange="this.form.submit()"
                                class="px-3 py-1.5 rounded-lg text-xs font-medium text-[#2C3E50]/80 bg-[#F1FDF6] border border-[#E0E0E0] focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition cursor-pointer">
                                <option value="">All Categories</option>
                                <option value="faculty" <?= $categoryFilter === 'faculty' ? 'selected' : '' ?>>Faculty
                                </option>
                                <option value="staff" <?= $categoryFilter === 'staff' ? 'selected' : '' ?>>Staff</option>
                            </select>

                            <button type="button" onclick="togglePayrollAmounts()"
                                class="payroll-amount-toggle flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-[#2C3E50]/70 hover:text-[#2C3E50] hover:bg-[#F1FDF6] transition"
                                title="Show / hide amounts">
                                <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                <span id="payrollAmountToggleLabel">Hide amounts</span>
                            </button>
                        </div>

                        <div class="relative flex-1 sm:w-72">
                            <i data-lucide="search"
                                class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[#2C3E50]/40 pointer-events-none"></i>
                            <input type="text" name="q" id="searchInput" value="<?= htmlspecialchars($searchQuery) ?>"
                                placeholder="Search employee" oninput="scheduleSearch()"
                                class="w-full pl-10 pr-10 py-2 bg-[#F1FDF6]/60 border border-transparent rounded-lg text-sm text-[#2C3E50] placeholder:text-[#2C3E50]/40 focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:bg-white focus:border-transparent transition">
                            <kbd
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-mono text-[#2C3E50]/40 bg-white px-1.5 py-0.5 rounded border border-[#E0E0E0]">/</kbd>
                        </div>
                    </div>
                </form>

                <!-- TABLE -->
                <div class="bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl shadow-sm overflow-hidden">

                    <?php if (empty($rows)): ?>
                        <div class="p-12 text-center">
                            <div
                                class="w-12 h-12 rounded-full bg-[#F1FDF6] flex items-center justify-center mx-auto mb-3 text-[#0F5E3D]">
                                <i data-lucide="wallet" class="w-5 h-5"></i>
                            </div>
                            <p class="text-sm font-medium text-[#2C3E50]">No employees found</p>
                        </div>
                    <?php else: ?>

                        <!-- Desktop -->
                        <div class="hidden md:block overflow-x-auto">
                            <table class="w-full">
                                <thead class="border-b border-[#E0E0E0]">
                                    <tr class="h-11">
                                        <th class="text-left text-xs font-medium text-[#2C3E50]/50 px-4 py-0">Employee</th>
                                        <th class="text-right text-xs font-medium text-[#2C3E50]/50 px-4 py-0">Monthly
                                            Salary</th>
                                        <th class="text-right text-xs font-medium text-[#2C3E50]/50 px-4 py-0">Leave Credits
                                        </th>
                                        <th class="text-right text-xs font-medium text-[#2C3E50]/50 px-4 py-0">Leave Pay
                                        </th>
                                        <th class="text-right text-xs font-medium text-[#2C3E50]/50 px-4 py-0">Convertible
                                            Leave Pay</th>
                                        <th class="text-right text-xs font-medium text-[#2C3E50]/50 px-4 py-0">LWOP
                                            Deduction</th>
                                        <th class="text-left text-xs font-medium text-[#2C3E50]/50 px-4 py-0">Payment Status
                                        </th>
                                        <th class="text-right text-xs font-medium text-[#2C3E50]/50 px-4 py-0">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="payrollTableBody" class="divide-y divide-[#E0E0E0]">
                                    <?php foreach ($rows as $r):
                                        $statusKey = $r['settlement_status'] ?? 'none';
                                        $statusLabel = [
                                            'settled' => 'Paid',
                                            'outstanding' => 'Unpaid',
                                            'none' => 'Not Yet Recorded',
                                        ][$statusKey] ?? ucfirst($statusKey);
                                        $statusClass = [
                                            'settled' => 'text-[#0F5E3D] bg-[#F1FDF6]',
                                            'outstanding' => 'text-amber-700 bg-amber-50',
                                            'none' => 'text-slate-600 bg-slate-100',
                                        ][$statusKey] ?? 'text-slate-600 bg-slate-100';
                                        $historyJson = json_encode($r['settlement_history']);
                                        ?>
                                        <tr class="hover:bg-[#F1FDF6]/40 transition h-16 payroll-row"
                                            data-id="<?= (int) $r['employee_id'] ?>" data-name="<?= strtolower($r['name']) ?>"
                                            data-full-name="<?= htmlspecialchars($r['name']) ?>"
                                            data-initials="<?= htmlspecialchars($r['initials']) ?>"
                                            data-email="<?= htmlspecialchars($r['email']) ?>"
                                            data-category="<?= htmlspecialchars($r['category']) ?>"
                                            data-sub="<?= htmlspecialchars($r['sub_category']) ?>"
                                            data-position="<?= htmlspecialchars($r['position']) ?>"
                                            data-employment="<?= htmlspecialchars($r['employment']) ?>"
                                            data-status="<?= $statusKey ?>" data-salary="<?= (float) $r['monthly_salary'] ?>"
                                            data-allocated="<?= (float) $r['allocated_credits'] ?>"
                                            data-approved="<?= (float) $r['approved_days'] ?>"
                                            data-remaining="<?= (float) $r['remaining_credits'] ?>"
                                            data-sick-remaining="<?= (float) $r['sick_remaining'] ?>"
                                            data-lwop-days="<?= (float) $r['lwop_days'] ?>"
                                            data-lwop-deduction="<?= (float) $r['lwop_deduction'] ?>"
                                            data-leave-pay-rate="<?= (float) $r['leave_pay_rate'] ?>"
                                            data-potential-convertible="<?= (float) $r['potential_convertible'] ?>"
                                            data-convertible="<?= (float) $r['convertible_leave_pay'] ?>"
                                            data-is-settled="<?= $r['is_fully_settled'] ? '1' : '0' ?>"
                                            data-settlement-amount="<?= (float) $r['settlement_amount'] ?>"
                                            data-settled-at="<?= htmlspecialchars($r['settled_at'] ?? '') ?>"
                                            data-remarks="<?= htmlspecialchars($r['settlement_remarks']) ?>"
                                            data-history="<?= htmlspecialchars($historyJson, ENT_QUOTES, 'UTF-8') ?>">
                                            <td class="px-4 py-0">
                                                <div class="flex items-center gap-2.5">
                                                    <div
                                                        class="w-8 h-8 rounded-full bg-[#0F5E3D] text-white flex items-center justify-center font-semibold text-[10px] shrink-0">
                                                        <?= $r['initials'] ?>
                                                    </div>
                                                    <div class="min-w-0">
                                                        <p class="text-sm font-medium text-[#2C3E50] truncate">
                                                            <?= htmlspecialchars($r['name']) ?></p>
                                                        <p class="text-[10px] text-[#2C3E50]/40 truncate">
                                                            <?= htmlspecialchars(ucfirst($r['category'])) ?>
                                                            <?php if (!empty($r['sub_category'])): ?>
                                                                · <?= htmlspecialchars($r['sub_category']) ?>
                                                            <?php endif; ?>
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-0 text-right">
                                                <span class="payroll-amount text-sm text-[#2C3E50] font-medium"
                                                    data-amount="<?= (float) $r['monthly_salary'] ?>" data-prefix="₱"
                                                    data-empty="—">
                                                    <?= $r['monthly_salary'] > 0 ? '₱' . number_format($r['monthly_salary'], 2) : '—' ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-0 text-right">
                                                <span
                                                    class="text-sm text-[#2C3E50] font-medium"><?= number_format($r['remaining_credits']) ?></span>
                                            </td>
                                            <td class="px-4 py-0 text-right">
                                                <span class="payroll-amount text-sm text-[#2C3E50] font-medium"
                                                    data-amount="<?= (float) $r['leave_pay_rate'] ?>" data-prefix="₱">
                                                    ₱<?= number_format($r['leave_pay_rate'], 2) ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-0 text-right">
                                                <?php if ($r['is_fully_settled']): ?>
                                                    <span class="payroll-amount block text-sm font-semibold text-[#0F5E3D] mt-0.5"
                                                        data-amount="0" data-prefix="₱">
                                                        ₱0.00
                                                    </span>
                                                <?php else: ?>
                                                    <span class="payroll-amount text-sm font-semibold text-amber-600"
                                                        data-amount="<?= (float) $r['convertible_leave_pay'] ?>" data-prefix="₱">
                                                        ₱<?= number_format($r['convertible_leave_pay'], 2) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-0 text-right">
                                                <span class="payroll-amount text-sm text-red-600 font-medium"
                                                    data-amount="<?= (float) $r['lwop_deduction'] ?>" data-prefix="−₱"
                                                    data-empty="—">
                                                    <?= $r['lwop_deduction'] > 0 ? '−₱' . number_format($r['lwop_deduction'], 2) : '—' ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-0">
                                                <span
                                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium <?= $statusClass ?>"><?= $statusLabel ?></span>
                                            </td>
                                            <td class="px-4 py-0">
                                                <div class="flex items-center justify-end gap-1">
                                                    <button onclick="openPayrollView(this.closest('tr'))"
                                                        class="w-8 h-8 rounded-lg flex items-center justify-center text-[#2C3E50]/60 hover:bg-[#F1FDF6] hover:text-[#0F5E3D] transition"
                                                        title="View payroll details">
                                                        <i data-lucide="eye" class="w-4 h-4"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>

                                <tfoot class="border-t-2 border-[#E0E0E0] bg-[#F1FDF6]/40">
                                    <tr class="h-14">
                                        <td class="px-4 py-0">
                                            <span
                                                class="text-xs uppercase tracking-wider text-[#2C3E50]/50 font-semibold">Total
                                                (all employees)</span>
                                        </td>
                                        <td class="px-4 py-0 text-right">
                                            <span class="payroll-amount text-sm font-bold text-[#2C3E50]"
                                                data-amount="<?= (float) $totalSalaryPool ?>" data-prefix="₱">
                                                ₱<?= number_format($totalSalaryPool, 2) ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-0 text-right">
                                            <span class="text-xs text-[#2C3E50]/50">—</span>
                                        </td>
                                        <td class="px-4 py-0 text-right">
                                            <span class="text-xs text-[#2C3E50]/50">—</span>
                                        </td>
                                        <td class="px-4 py-0 text-right">
                                            <span class="payroll-amount text-sm font-bold text-amber-600"
                                                data-amount="<?= (float) $totalConvertibleLeavePay ?>" data-prefix="₱">
                                                ₱<?= number_format($totalConvertibleLeavePay, 2) ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-0 text-right">
                                            <span class="payroll-amount text-sm font-bold text-red-600"
                                                data-amount="<?= (float) $totalLwopDeduction ?>" data-prefix="−₱">
                                                −₱<?= number_format($totalLwopDeduction, 2) ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-0">
                                            <span class="text-xs text-[#2C3E50]/50">
                                                <?= $settledCount ?> paid · <?= $outstandingCount ?> unpaid
                                            </span>
                                        </td>
                                        <td class="px-4 py-0 text-right"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- Mobile -->
                        <div id="payrollMobileCards" class="md:hidden divide-y divide-[#E0E0E0]">
                            <?php foreach ($rows as $r):
                                $statusKey = $r['settlement_status'] ?? 'none';
                                $statusLabel = ['settled' => 'Paid', 'outstanding' => 'Unpaid', 'none' => 'Not Recorded'][$statusKey] ?? ucfirst($statusKey);
                                $statusClass = ['settled' => 'text-[#0F5E3D] bg-[#F1FDF6]', 'outstanding' => 'text-amber-700 bg-amber-50', 'none' => 'text-slate-600 bg-slate-100'][$statusKey] ?? 'text-slate-600 bg-slate-100';
                                $historyJson = json_encode($r['settlement_history']);
                                ?>
                                <div class="p-4 payroll-row" data-id="<?= (int) $r['employee_id'] ?>"
                                    data-name="<?= strtolower($r['name']) ?>"
                                    data-full-name="<?= htmlspecialchars($r['name']) ?>"
                                    data-initials="<?= htmlspecialchars($r['initials']) ?>"
                                    data-email="<?= htmlspecialchars($r['email']) ?>"
                                    data-category="<?= htmlspecialchars($r['category']) ?>"
                                    data-sub="<?= htmlspecialchars($r['sub_category']) ?>"
                                    data-position="<?= htmlspecialchars($r['position']) ?>"
                                    data-employment="<?= htmlspecialchars($r['employment']) ?>" data-status="<?= $statusKey ?>"
                                    data-salary="<?= (float) $r['monthly_salary'] ?>"
                                    data-allocated="<?= (float) $r['allocated_credits'] ?>"
                                    data-approved="<?= (float) $r['approved_days'] ?>"
                                    data-remaining="<?= (float) $r['remaining_credits'] ?>"
                                    data-sick-remaining="<?= (float) $r['sick_remaining'] ?>"
                                    data-lwop-days="<?= (float) $r['lwop_days'] ?>"
                                    data-lwop-deduction="<?= (float) $r['lwop_deduction'] ?>"
                                    data-leave-pay-rate="<?= (float) $r['leave_pay_rate'] ?>"
                                    data-potential-convertible="<?= (float) $r['potential_convertible'] ?>"
                                    data-convertible="<?= (float) $r['convertible_leave_pay'] ?>"
                                    data-is-settled="<?= $r['is_fully_settled'] ? '1' : '0' ?>"
                                    data-settlement-amount="<?= (float) $r['settlement_amount'] ?>"
                                    data-settled-at="<?= htmlspecialchars($r['settled_at'] ?? '') ?>"
                                    data-remarks="<?= htmlspecialchars($r['settlement_remarks']) ?>"
                                    data-history="<?= htmlspecialchars($historyJson, ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="flex items-start justify-between gap-2 mb-2">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <div
                                                class="w-8 h-8 rounded-full bg-[#0F5E3D] text-white flex items-center justify-center font-semibold text-[10px] shrink-0">
                                                <?= $r['initials'] ?>
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium text-[#2C3E50] truncate">
                                                    <?= htmlspecialchars($r['name']) ?></p>
                                                <p class="text-[10px] text-[#2C3E50]/40 truncate">
                                                    <?= htmlspecialchars(ucfirst($r['category'])) ?></p>
                                            </div>
                                        </div>
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium <?= $statusClass ?> shrink-0"><?= $statusLabel ?></span>
                                    </div>

                                    <div class="grid grid-cols-2 gap-2 text-xs">
                                        <div>
                                            <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 mb-0.5">Monthly
                                                Salary</p>
                                            <p class="payroll-amount text-[#2C3E50] font-medium"
                                                data-amount="<?= (float) $r['monthly_salary'] ?>" data-prefix="₱" data-empty="—">
                                                <?= $r['monthly_salary'] > 0 ? '₱' . number_format($r['monthly_salary'], 2) : '—' ?>
                                            </p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 mb-0.5">Leave
                                                Credits</p>
                                            <p class="text-[#2C3E50] font-medium">
                                                <?= number_format($r['remaining_credits'], 0) ?></p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 mb-0.5">Leave Pay
                                            </p>
                                            <p class="payroll-amount text-[#2C3E50] font-medium"
                                                data-amount="<?= (float) $r['leave_pay_rate'] ?>" data-prefix="₱">
                                                ₱<?= number_format($r['leave_pay_rate'], 2) ?> <span
                                                    class="text-[10px] text-[#2C3E50]/40"></span>
                                            </p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 mb-0.5">LWOP
                                                Deduction</p>
                                            <p class="payroll-amount text-red-600 font-medium"
                                                data-amount="<?= (float) $r['lwop_deduction'] ?>" data-prefix="−₱"
                                                data-empty="—">
                                                <?= $r['lwop_deduction'] > 0 ? '−₱' . number_format($r['lwop_deduction'], 2) : '—' ?>
                                            </p>
                                        </div>
                                        <div class="col-span-2">
                                            <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 mb-0.5">Convertible
                                                Leave Pay</p>
                                            <?php if ($r['is_fully_settled']): ?>
                                                <p class="text-[#0F5E3D] font-bold inline-flex items-center gap-1">
                                                    <i data-lucide="check" class="w-3 h-3"></i>
                                                    Settled · ₱0.00
                                                </p>
                                            <?php else: ?>
                                                <p class="payroll-amount text-amber-600 font-bold"
                                                    data-amount="<?= (float) $r['convertible_leave_pay'] ?>" data-prefix="₱">
                                                    ₱<?= number_format($r['convertible_leave_pay'], 2) ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2 mt-3">
                                        <button onclick="openPayrollView(this.closest('.payroll-row'))"
                                            class="flex-1 flex items-center justify-center gap-1.5 text-xs py-2 rounded-lg border border-[#E0E0E0] text-[#2C3E50] hover:bg-gray-50 transition">
                                            <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                            <span>View Details</span>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                    <?php endif; ?>

                    <div id="payrollEmpty" class="hidden p-12 text-center">
                        <div
                            class="w-12 h-12 rounded-full bg-[#F1FDF6] flex items-center justify-center mx-auto mb-3 text-[#0F5E3D]">
                            <i data-lucide="search-x" class="w-5 h-5"></i>
                        </div>
                        <p class="text-sm font-medium text-[#2C3E50]">No matching employees</p>
                        <p class="text-xs text-[#2C3E50]/50 mt-1">Try adjusting your search or filters.</p>
                    </div>

                    <!-- Pagination -->
                    <div
                        class="px-4 sm:px-6 py-3 border-t border-[#E0E0E0] flex flex-col sm:flex-row items-center justify-between gap-3">
                        <p class="text-xs text-[#2C3E50]/50">
                            Showing <span class="font-medium text-[#2C3E50]"><?= count($rows) ?></span>
                            of <span class="font-medium text-[#2C3E50]"><?= $totalEmployees ?></span> employees
                            <?php if ($totalPages > 1): ?>
                                · Page <span class="font-medium text-[#2C3E50]"><?= $page ?></span> of <span
                                    class="font-medium text-[#2C3E50]"><?= $totalPages ?></span>
                            <?php endif; ?>
                        </p>

                        <?php if ($totalPages > 1): ?>
                            <div class="flex items-center gap-1">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?= $page - 1 ?><?= $baseQs ?>"
                                        class="w-8 h-8 rounded-lg flex items-center justify-center text-[#2C3E50]/70 hover:bg-[#F1FDF6] hover:text-[#0F5E3D] transition">
                                        <i data-lucide="chevron-left" class="w-4 h-4"></i>
                                    </a>
                                <?php else: ?>
                                    <button
                                        class="w-8 h-8 rounded-lg flex items-center justify-center text-[#2C3E50]/30 cursor-not-allowed"
                                        disabled>
                                        <i data-lucide="chevron-left" class="w-4 h-4"></i>
                                    </button>
                                <?php endif; ?>

                                <?php
                                $start = max(1, $page - 2);
                                $end = min($totalPages, $start + 4);
                                $start = max(1, $end - 4);
                                for ($i = $start; $i <= $end; $i++):
                                    ?>
                                    <?php if ($i === $page): ?>
                                        <span
                                            class="w-8 h-8 rounded-lg bg-[#0F5E3D] text-white text-xs font-medium flex items-center justify-center"><?= $i ?></span>
                                    <?php else: ?>
                                        <a href="?page=<?= $i ?><?= $baseQs ?>"
                                            class="w-8 h-8 rounded-lg flex items-center justify-center text-[#2C3E50]/70 hover:bg-[#F1FDF6] hover:text-[#0F5E3D] transition text-xs font-medium"><?= $i ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <a href="?page=<?= $page + 1 ?><?= $baseQs ?>"
                                        class="w-8 h-8 rounded-lg flex items-center justify-center text-[#2C3E50]/70 hover:bg-[#F1FDF6] hover:text-[#0F5E3D] transition">
                                        <i data-lucide="chevron-right" class="w-4 h-4"></i>
                                    </a>
                                <?php else: ?>
                                    <button
                                        class="w-8 h-8 rounded-lg flex items-center justify-center text-[#2C3E50]/30 cursor-not-allowed"
                                        disabled>
                                        <i data-lucide="chevron-right" class="w-4 h-4"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <!-- PAYROLL VIEW MODAL -->
    <div id="payrollViewModal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm p-4">
        <div id="payrollViewCard"
            class="bg-white rounded-xl shadow-2xl w-full max-w-4xl h-[80vh] transform transition-all duration-200 scale-95 overflow-hidden flex flex-col">

            <div class="flex items-center justify-between px-6 py-4 border-b border-[#E0E0E0] shrink-0 bg-white">
                <div>
                    <h3 class="text-base font-semibold text-[#2C3E50]">Employee Payroll Profile</h3>
                    <p class="text-sm text-[#2C3E50]/50" id="pvProfileSubtitle">—</p>
                </div>
                <button onclick="closePayrollView()"
                    class="w-9 h-9 rounded-lg flex items-center justify-center text-[#2C3E50]/50 hover:bg-[#F1FDF6] hover:text-[#2C3E50] transition">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <div class="flex-1 flex min-h-0">

                <aside class="w-72 border-r border-[#E0E0E0] bg-[#F9FAFB] flex flex-col shrink-0 overflow-y-auto">
                    <div class="p-6">
                        <div id="pvInitials"
                            class="w-16 h-16 rounded-full bg-[#0F5E3D] text-white flex items-center justify-center font-semibold text-xl mb-4">
                            —
                        </div>
                        <p id="pvName" class="text-base font-semibold text-[#2C3E50] leading-tight">—</p>
                        <p id="pvPosition" class="text-sm text-[#2C3E50]/60 mt-1 leading-snug">—</p>
                        <p id="pvDepartment" class="text-sm text-[#2C3E50]/60 leading-snug">—</p>
                        <p id="pvEmail" class="text-xs text-[#2C3E50]/40 mt-2 truncate">—</p>
                    </div>

                    <div class="border-t border-[#E0E0E0]"></div>

                    <div class="p-6">
                        <p class="text-xs uppercase tracking-wider text-[#2C3E50]/50 font-semibold mb-3">Payment Status
                        </p>
                        <div id="pvStatusBadge"
                            class="inline-flex items-center px-2.5 py-1 rounded-md text-sm font-medium text-slate-600 bg-slate-100 mb-4">
                            —
                        </div>

                        <div id="pvSettlementDetails" class="space-y-3 hidden">
                            <div>
                                <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold">Amount
                                </p>
                                <p id="pvSettledAmount" class="text-sm font-semibold text-[#2C3E50]">—</p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold">Date</p>
                                <p id="pvSettledAt" class="text-sm text-[#2C3E50]">—</p>
                            </div>
                            <div id="pvRemarksWrap" class="hidden">
                                <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold">Remarks
                                </p>
                                <p id="pvRemarks" class="text-sm text-[#2C3E50]/70 italic leading-snug">—</p>
                            </div>
                        </div>
                    </div>
                </aside>

                <section class="flex-1 flex flex-col min-h-0 bg-white">
                    <div class="flex-1 overflow-y-auto px-8 py-6 space-y-6">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="bg-[#F1FDF6]/50 border border-[#E0E0E0] rounded-xl p-4">
                                <div class="flex items-center gap-2 mb-2">
                                    <div
                                        class="w-8 h-8 rounded-lg bg-white shadow-sm flex items-center justify-center text-[#0F5E3D] shrink-0">
                                        <i data-lucide="landmark" class="w-4 h-4"></i>
                                    </div>
                                    <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/50 font-semibold">
                                        Monthly Salary</p>
                                </div>
                                <p id="pvSalary" class="text-xl font-bold text-[#2C3E50]">—</p>
                            </div>

                            <div class="bg-[#F1FDF6]/50 border border-[#E0E0E0] rounded-xl p-4">
                                <div class="flex items-center gap-2 mb-2">
                                    <div
                                        class="w-8 h-8 rounded-lg bg-white shadow-sm flex items-center justify-center text-[#0F5E3D] shrink-0">
                                        <i data-lucide="banknote" class="w-4 h-4"></i>
                                    </div>
                                    <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/50 font-semibold">
                                        Leave Pay Rate</p>
                                </div>
                                <p id="pvLeavePayRate" class="text-xl font-bold text-[#0F5E3D]">—</p>
                            </div>
                        </div>

                        <div class="border border-[#E0E0E0] rounded-lg overflow-hidden">
                            <table class="w-full">
                                <thead class="bg-[#F9FAFB] border-b border-[#E0E0E0]">
                                    <tr>
                                        <th class="text-left text-xs font-medium text-[#2C3E50]/50 px-4 py-3">Metric
                                        </th>
                                        <th class="text-right text-xs font-medium text-[#2C3E50]/50 px-4 py-3">Value
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#F0F0F0]">
                                    <tr class="h-12">
                                        <td class="px-4 py-0 text-sm text-[#2C3E50]">Allocated Leave Credits</td>
                                        <td class="px-4 py-0 text-sm text-[#2C3E50] text-right font-medium"
                                            id="pvAllocated">—</td>
                                    </tr>
                                    <tr class="h-12">
                                        <td class="px-4 py-0 text-sm text-[#2C3E50]">Approved Leave Days</td>
                                        <td class="px-4 py-0 text-sm text-[#2C3E50] text-right font-medium"
                                            id="pvApproved">—</td>
                                    </tr>
                                    <tr class="h-12 bg-[#F1FDF6]/30">
                                        <td class="px-4 py-0 text-sm font-medium text-[#0F5E3D]">Remaining Credits</td>
                                        <td class="px-4 py-0 text-sm font-bold text-[#0F5E3D] text-right"
                                            id="pvRemaining">—</td>
                                    </tr>
                                    <tr class="h-12">
                                        <td class="px-4 py-0 text-sm text-[#2C3E50]">Remaining Sick Credits (cap 7.5)
                                        </td>
                                        <td class="px-4 py-0 text-sm text-[#2C3E50] text-right font-medium"
                                            id="pvSickRemaining">—</td>
                                    </tr>
                                    <tr class="h-12">
                                        <td class="px-4 py-0 text-sm text-[#2C3E50]">Leave Pay Rate</td>
                                        <td class="px-4 py-0 text-sm text-[#2C3E50] text-right font-medium"
                                            id="pvLeavePayRateRow">—</td>
                                    </tr>
                                    <tr class="h-12 bg-amber-50/30">
                                        <td class="px-4 py-0 text-sm font-medium text-amber-700">Convertible Leave Pay
                                        </td>
                                        <td class="px-4 py-0 text-sm font-bold text-right" id="pvConvertible">—</td>
                                    </tr>
                                    <tr class="h-12">
                                        <td class="px-4 py-0 text-sm text-[#2C3E50]">LWOP Days</td>
                                        <td class="px-4 py-0 text-sm text-[#2C3E50] text-right font-medium"
                                            id="pvLwopDays">—</td>
                                    </tr>
                                    <tr class="h-12">
                                        <td class="px-4 py-0 text-sm text-[#2C3E50]">LWOP Deduction</td>
                                        <td class="px-4 py-0 text-sm text-red-600 text-right font-medium"
                                            id="pvLwopAmount">—</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- ============================================ -->
                        <!-- SETTLEMENT RECORD                            -->
                        <!-- ============================================ -->
                        <div class="border border-[#E0E0E0] rounded-lg overflow-hidden">
                            <div
                                class="bg-[#F9FAFB] border-b border-[#E0E0E0] px-4 py-3 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div
                                        class="w-7 h-7 rounded-md bg-white shadow-sm flex items-center justify-center text-[#0F5E3D] shrink-0">
                                        <i data-lucide="history" class="w-3.5 h-3.5"></i>
                                    </div>
                                    <div>
                                        <p class="text-xs font-semibold text-[#2C3E50]">Settlement Record</p>
                                        <p class="text-[10px] text-[#2C3E50]/50">Current payroll settlement</p>
                                    </div>
                                </div>
                                <span id="pvHistoryCount"
                                    class="text-[10px] font-medium text-[#2C3E50]/60 bg-white border border-[#E0E0E0] px-2 py-0.5 rounded-full">No
                                    record</span>
                            </div>
                            <div id="pvHistoryList" class="divide-y divide-[#F0F0F0] max-h-72 overflow-y-auto"></div>
                            <div id="pvHistoryEmpty" class="hidden p-6 text-center">
                                <p class="text-xs text-[#2C3E50]/50">No settlement recorded for this employee yet.</p>
                            </div>
                        </div>

                    </div>

                    <div class="border-t border-[#E0E0E0] bg-white px-8 py-4 shrink-0 flex items-center justify-end gap-3"
                        id="pvActionBar"></div>
                </section>
            </div>
        </div>
    </div>

    <!-- EDIT SALARY MODAL -->
    <div id="editSalaryModal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-sm transform transition-all duration-200 scale-95"
            id="editSalaryCard">
            <div class="flex items-center justify-between px-6 py-4 border-b border-[#E0E0E0]">
                <div class="flex items-center gap-3">
                    <div
                        class="w-9 h-9 rounded-lg bg-[#F1FDF6] flex items-center justify-center text-[#0F5E3D] shrink-0">
                        <i data-lucide="pencil" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-[#2C3E50]">Edit Salary</h3>
                        <p class="text-xs text-[#2C3E50]/50" id="editSalaryName">—</p>
                    </div>
                </div>
                <button onclick="closeEditSalaryModal()" class="text-[#2C3E50]/40 hover:text-[#2C3E50] transition">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <form id="editSalaryForm" class="p-6 space-y-4">
                <input type="hidden" name="employee_id" id="editSalaryEmployeeId">
                <div>
                    <label class="block text-xs font-medium text-[#2C3E50] mb-1.5">Monthly Salary (₱) *</label>
                    <input type="number" step="0.01" min="0" name="basic_salary" id="editSalaryInput" required
                        class="w-full px-3 py-2.5 border border-[#E0E0E0] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition">
                </div>
            </form>
            <div class="flex gap-3 px-6 py-4 border-t border-[#E0E0E0]">
                <button type="button" onclick="closeEditSalaryModal()"
                    class="flex-1 border border-[#E0E0E0] text-[#2C3E50] hover:bg-gray-50 font-medium py-2.5 rounded-lg transition text-sm">Cancel</button>
                <button type="button" onclick="confirmEditSalary()" id="editSalaryConfirmBtn"
                    class="flex-1 bg-[#0F5E3D] hover:bg-[#0a4a2f] text-white font-medium py-2.5 rounded-lg transition text-sm">Save
                    Salary</button>
            </div>
        </div>
    </div>

    <!-- EDIT LEAVE PAY MODAL -->
    <div id="editLeavePayModal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-sm transform transition-all duration-200 scale-95"
            id="editLeavePayCard">
            <div class="flex items-center justify-between px-6 py-4 border-b border-[#E0E0E0]">
                <div class="flex items-center gap-3">
                    <div
                        class="w-9 h-9 rounded-lg bg-[#F1FDF6] flex items-center justify-center text-[#0F5E3D] shrink-0">
                        <i data-lucide="banknote" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-[#2C3E50]">Edit Leave Pay Rate</h3>
                        <p class="text-xs text-[#2C3E50]/50" id="editLeavePayName">—</p>
                    </div>
                </div>
                <button onclick="closeEditLeavePayModal()" class="text-[#2C3E50]/40 hover:text-[#2C3E50] transition">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <form id="editLeavePayForm" class="p-6 space-y-4">
                <input type="hidden" name="employee_id" id="editLeavePayEmployeeId">
                <div>
                    <label class="block text-xs font-medium text-[#2C3E50] mb-1.5">Leave Pay Rate (₱ per credit)
                        *</label>
                    <input type="number" step="0.01" min="0" name="leave_pay" id="editLeavePayInput" required
                        class="w-full px-3 py-2.5 border border-[#E0E0E0] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition">
                    <p class="text-[10px] text-[#2C3E50]/40 mt-1.5">Leave Pay Rate × remaining sick credits =
                        Convertible Leave Pay</p>
                </div>
            </form>
            <div class="flex gap-3 px-6 py-4 border-t border-[#E0E0E0]">
                <button type="button" onclick="closeEditLeavePayModal()"
                    class="flex-1 border border-[#E0E0E0] text-[#2C3E50] hover:bg-gray-50 font-medium py-2.5 rounded-lg transition text-sm">Cancel</button>
                <button type="button" onclick="confirmEditLeavePay()" id="editLeavePayConfirmBtn"
                    class="flex-1 bg-[#0F5E3D] hover:bg-[#0a4a2f] text-white font-medium py-2.5 rounded-lg transition text-sm">Save
                    Leave Pay Rate</button>
            </div>
        </div>
    </div>

    <!-- PAYMENT MODAL -->
    <div id="settleModal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md transform transition-all duration-200 scale-95"
            id="settleCard">
            <div class="flex items-center justify-between px-6 py-4 border-b border-[#E0E0E0]">
                <div class="flex items-center gap-3">
                    <div id="settleIconWrap"
                        class="w-9 h-9 rounded-lg bg-[#F1FDF6] flex items-center justify-center text-[#0F5E3D] shrink-0">
                        <i data-lucide="check-circle-2" id="settleIcon" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-[#2C3E50]" id="settleTitle">Record Payment</h3>
                        <p class="text-xs text-[#2C3E50]/50" id="settleSubtitle">—</p>
                    </div>
                </div>
                <button onclick="closeSettleModal()" class="text-[#2C3E50]/40 hover:text-[#2C3E50] transition">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <form id="settleForm" class="p-6 space-y-4">
                <input type="hidden" name="employee_id" id="settleEmployeeId">
                <input type="hidden" name="status" id="settleStatus">
                <div class="bg-[#F1FDF6] border border-[#0F5E3D]/10 rounded-lg p-3">
                    <p class="text-xs text-[#2C3E50]/70">
                        <span class="font-semibold text-[#2C3E50]" id="settleEmployeeName">—</span>
                        has a convertible leave pay of
                        <span class="font-semibold text-amber-600" id="settleAmountLabel">—</span>.
                    </p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-[#2C3E50] mb-1.5">Amount Paid (₱) *</label>
                    <input type="number" step="0.01" min="0" name="amount" id="settleAmount" required
                        class="w-full px-3 py-2.5 border border-[#E0E0E0] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition">
                </div>
                <div>
                    <label class="block text-xs font-medium text-[#2C3E50] mb-1.5">Remarks <span
                            class="text-[#2C3E50]/40">(optional)</span></label>
                    <textarea name="remarks" id="settleRemarks" rows="2"
                        class="w-full px-3 py-2.5 border border-[#E0E0E0] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition resize-none"
                        placeholder="e.g. Year-end cash conversion"></textarea>
                </div>
            </form>
            <div class="flex gap-3 px-6 py-4 border-t border-[#E0E0E0]">
                <button type="button" onclick="closeSettleModal()"
                    class="flex-1 border border-[#E0E0E0] text-[#2C3E50] hover:bg-gray-50 font-medium py-2.5 rounded-lg transition text-sm">Cancel</button>
                <button type="button" onclick="confirmSettle()" id="settleConfirmBtn"
                    class="flex-1 bg-[#0F5E3D] hover:bg-[#0a4a2f] text-white font-medium py-2.5 rounded-lg transition text-sm">Confirm</button>
            </div>
        </div>
    </div>

    <!-- Loading / Error / Success -->
    <div id="loadingOverlay"
        class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-xs p-8 text-center">
            <svg class="animate-spin h-10 w-10 text-[#0F5E3D] mx-auto mb-4" xmlns="http://www.w3.org/2000/svg"
                fill="none" viewBox="0 0 24 24">
                <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                <path class="opacity-90" fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                </path>
            </svg>
            <h3 class="text-base font-bold text-[#2C3E50] mb-1" id="loadingTitle">Working...</h3>
            <p class="text-xs text-[#2C3E50]/60" id="loadingMessage">Please wait.</p>
        </div>
    </div>

    <div id="errorModal"
        class="fixed inset-0 z-[80] hidden items-center justify-center bg-black/40 backdrop-blur-sm p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-8 text-center" id="errorCard">
            <div class="flex items-center justify-center w-16 h-16 rounded-full bg-red-50 mx-auto mb-5">
                <i data-lucide="alert-circle" class="w-8 h-8 text-red-600"></i>
            </div>
            <h3 class="text-lg font-bold text-[#2C3E50] mb-2" id="errorTitle">Error</h3>
            <p class="text-sm text-[#2C3E50]/60 mb-6" id="errorMessage">—</p>
            <button onclick="closeErrorModal()"
                class="w-full bg-[#0F5E3D] hover:bg-[#0a4a2f] text-white font-medium py-2.5 rounded-lg transition text-sm">Got
                it</button>
        </div>
    </div>

    <div id="successModal"
        class="fixed inset-0 z-[70] hidden items-center justify-center bg-black/40 backdrop-blur-sm p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-8 text-center" id="successCard">
            <div class="relative mx-auto mb-5 w-16 h-16">
                <div class="absolute inset-0 rounded-full bg-[#F1FDF6] animate-ping opacity-40"></div>
                <div
                    class="relative w-16 h-16 rounded-full bg-[#F1FDF6] flex items-center justify-center text-[#0F5E3D]">
                    <i data-lucide="check-circle" class="w-8 h-8"></i>
                </div>
            </div>
            <h3 class="text-lg font-bold text-[#2C3E50] mb-2" id="successTitle">Done</h3>
            <p class="text-sm text-[#2C3E50]/60 mb-6" id="successMessage">—</p>
            <button onclick="closeSuccess()"
                class="w-full bg-[#0F5E3D] hover:bg-[#0a4a2f] text-white font-medium py-2.5 rounded-lg transition text-sm">Done</button>
        </div>
    </div>

    <?php require_once __DIR__ . '/../components/logout_modal.php'; ?>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="/assets/js/app.js"></script>
    <script src="/assets/js/notifications.js"></script>
    <script src="/assets/js/profile.js"></script>
    <script src="/assets/js/accounting_payroll.js"></script>
</body>

</html>