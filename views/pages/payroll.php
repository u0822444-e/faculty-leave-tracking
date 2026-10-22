<?php
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}
if ($_SESSION['role'] !== 'hr') {
    header('Location: /login');
    exit;
}

require_once __DIR__ . '/../../config/database.php';

$workingDaysPerMonth = 22;

// ============================================
// Pagination setup
// ============================================
$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

// ============================================
// Fetch current page of employees
// ============================================
$stmt = $pdo->prepare("
    SELECT 
        e.id AS employee_id,
        e.first_name, e.middle_name, e.last_name, e.email,
        e.category, e.sub_category, e.employment_type,
        e.basic_salary, e.leave_pay, e.leave_credits,
        u.username, u.status AS user_status,
        COALESCE(lr.credits_used, 0) AS credits_used
    FROM employees e
    LEFT JOIN users u ON u.employee_id = e.id
    LEFT JOIN (
        SELECT employee_id, SUM(days_count) AS credits_used
        FROM leave_requests
        WHERE status = 'approved'
        GROUP BY employee_id
    ) lr ON lr.employee_id = e.id
    WHERE e.category IN ('faculty', 'staff')
    ORDER BY e.last_name ASC, e.first_name ASC
    LIMIT ? OFFSET ?
");
$stmt->bindValue(1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$employees = $stmt->fetchAll();

$totalEmployeesAll = (int) $pdo->query("
    SELECT COUNT(*) FROM employees WHERE category IN ('faculty', 'staff')
")->fetchColumn();
$totalPages = max(1, (int) ceil($totalEmployeesAll / $perPage));

if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
    $stmt->bindValue(1, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $employees = $stmt->fetchAll();
}

// ============================================
// Compute rows for THIS page
// ============================================
$rows = [];
$categoryCountsPage = ['faculty' => 0, 'staff' => 0];
$employmentCountsPage = ['full-time' => 0, 'part-time' => 0];

foreach ($employees as $e) {
    $leavePayRate = (float) $e['leave_pay'];
    $basicSalary  = (float) $e['basic_salary'];
    $used         = (float) $e['credits_used'];
    $allocated    = (float) $e['leave_credits'];
    $remaining    = max(0, $allocated - $used);

    $totalLeavePay = $leavePayRate * $allocated;
    $saved         = $leavePayRate * $used;
    $convertible   = $leavePayRate * $remaining;

    // Salary-based deduction: monthly_salary ÷ 22 × used_days
    $dailyRate = $basicSalary > 0 ? $basicSalary / $workingDaysPerMonth : 0;
    $monthlyDeduction = $dailyRate * $used;

    $cat = $e['category'] ?? 'staff';
    if (isset($categoryCountsPage[$cat])) $categoryCountsPage[$cat]++;

    $emp = $e['employment_type'] ?? 'full-time';
    if (isset($employmentCountsPage[$emp])) $employmentCountsPage[$emp]++;

    $rows[] = [
        'employee_id'      => (int) $e['employee_id'],
        'name'             => trim(($e['first_name'] ?? '') . ' ' . ($e['last_name'] ?? '')) ?: ($e['username'] ?? 'Unknown'),
        'initials'         => strtoupper(substr($e['first_name'] ?? 'U', 0, 1) . substr($e['last_name'] ?? '', 0, 1)),
        'email'            => $e['email'] ?? '—',
        'category'         => $cat,
        'sub_category'     => $e['sub_category'] ?? '',
        'employment'       => $emp,
        'basic_salary'     => $basicSalary,
        'daily_rate'       => $dailyRate,
        'monthly_deduction'=> $monthlyDeduction,
        'leave_pay'        => $leavePayRate,
        'total_pay'        => $totalLeavePay,
        'allocated'        => $allocated,
        'used'             => $used,
        'remaining'        => $remaining,
        'saved'            => $saved,
        'convertible'      => $convertible,
        'status'           => $e['user_status'] ?? 'active',
    ];
}

// Sort current page by saved DESC
usort($rows, fn($a, $b) => $b['saved'] <=> $a['saved']);

// ============================================
// GLOBAL aggregates — across ALL employees
// ============================================
$agg = $pdo->query("
    SELECT 
        e.id,
        e.category,
        e.sub_category,
        e.employment_type,
        e.basic_salary,
        e.leave_pay,
        e.leave_credits,
        COALESCE(lr.credits_used, 0) AS credits_used
    FROM employees e
    LEFT JOIN (
        SELECT employee_id, SUM(days_count) AS credits_used
        FROM leave_requests
        WHERE status = 'approved'
        GROUP BY employee_id
    ) lr ON lr.employee_id = e.id
    WHERE e.category IN ('faculty', 'staff')
")->fetchAll();

$totalSaved            = 0;
$totalConvertible      = 0;
$totalUsedDays         = 0;
$totalRemaining        = 0;
$totalPayValue         = 0;
$totalMonthlyDeduction = 0;
$totalSalaryPool       = 0;
$savedByDept           = [];
$convertibleByCat      = ['faculty' => 0, 'staff' => 0];
$categoryCounts        = ['faculty' => 0, 'staff' => 0];
$employmentCounts      = ['full-time' => 0, 'part-time' => 0];

foreach ($agg as $a) {
    $leavePayRate = (float) $a['leave_pay'];
    $basicSalary  = (float) $a['basic_salary'];
    $used         = (float) $a['credits_used'];
    $allocated    = (float) $a['leave_credits'];
    $remaining    = max(0, $allocated - $used);

    $saved       = $leavePayRate * $used;
    $convertible = $leavePayRate * $remaining;
    $payValue    = $leavePayRate * $allocated;

    $dailyRate        = $basicSalary > 0 ? $basicSalary / $workingDaysPerMonth : 0;
    $monthlyDeduction = $dailyRate * $used;

    $totalSaved            += $saved;
    $totalConvertible      += $convertible;
    $totalUsedDays         += $used;
    $totalRemaining        += $remaining;
    $totalPayValue         += $payValue;
    $totalMonthlyDeduction += $monthlyDeduction;
    $totalSalaryPool       += $basicSalary;

    $cat = $a['category'] ?? 'staff';
    if (isset($categoryCounts[$cat])) $categoryCounts[$cat]++;

    $emp = $a['employment_type'] ?? 'full-time';
    if (isset($employmentCounts[$emp])) $employmentCounts[$emp]++;

    $dept = trim($a['sub_category'] ?? '') ?: 'Unassigned';
    if (!isset($savedByDept[$dept])) $savedByDept[$dept] = 0;
    $savedByDept[$dept] += $saved;

    if (isset($convertibleByCat[$cat])) $convertibleByCat[$cat] += $convertible;
}

$totalEmployees = count($agg);

arsort($savedByDept);
$deptChart  = array_slice($savedByDept, 0, 6, true);
$deptLabels = array_keys($deptChart);
$deptValues = array_values($deptChart);

$avgConvertible = $totalEmployees > 0 ? $totalConvertible / $totalEmployees : 0;
$avgSalary      = $totalEmployees > 0 ? $totalSalaryPool / $totalEmployees : 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll | DAMMC HR</title>
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
        $activePage = 'hr_payroll';
        require __DIR__ . '/../components/sidebar.php';
        ?>

        <div class="flex-1 md:ml-64 w-full min-w-0">

            <?php
            $pageTitle = 'Payroll';
            $pageSubtitle = 'Institution-wide leave pay usage, salary context & convertible balance';
            $pageIcon = 'wallet';
            require __DIR__ . '/../components/topbar.php';
            ?>

            <main class="p-4 sm:p-6 lg:p-8 space-y-4 sm:space-y-6 overflow-visible">

                <!-- ============================================ -->
                <!-- HERO ROW                                       -->
                <!-- ============================================ -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 sm:gap-4">

                    <!-- Leave pay used + salary context -->
                    <div class="lg:col-span-2 animate-fade-in-up bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-5 sm:p-6 shadow-sm">
                        <div class="flex items-start justify-between gap-4 mb-4">
                            <div>
                                <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/50 font-semibold mb-1">
                                    Leave Pay Used This Period</p>
                                <div class="flex items-baseline gap-2">
                                    <span class="text-3xl sm:text-4xl font-bold text-[#0F5E3D]">
                                        ₱<?= number_format($totalSaved, 2) ?>
                                    </span>
                                </div>
                                <p class="text-xs text-[#2C3E50]/50 mt-1">
                                    value of paid leave used across all faculty &amp; staff
                                </p>
                            </div>
                            <div class="w-10 h-10 rounded-lg bg-[#F1FDF6] flex items-center justify-center text-[#0F5E3D] shrink-0">
                                <i data-lucide="shield-check" class="w-5 h-5"></i>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-4 border-t border-[#E0E0E0]">
                            <div>
                                <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-0.5">
                                    Approved Leave Days</p>
                                <p class="text-lg font-bold text-[#2C3E50]"><?= number_format($totalUsedDays, 2) ?></p>
                                <p class="text-[10px] text-[#2C3E50]/40 mt-0.5">paid leave days</p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-0.5">
                                    Convertible Balance</p>
                                <p class="text-lg font-bold text-amber-600">₱<?= number_format($totalConvertible, 2) ?></p>
                                <p class="text-[10px] text-[#2C3E50]/40 mt-0.5">remaining unused credits</p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-0.5">
                                    Monthly Deduction</p>
                                <p class="text-lg font-bold text-red-600">₱<?= number_format($totalMonthlyDeduction, 2) ?></p>
                                <p class="text-[10px] text-[#2C3E50]/40 mt-0.5">salary value of used days</p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-0.5">
                                    Monthly Salary Pool</p>
                                <p class="text-lg font-bold text-[#2C3E50]">₱<?= number_format($totalSalaryPool, 2) ?></p>
                                <p class="text-[10px] text-[#2C3E50]/40 mt-0.5">sum of basic salaries</p>
                            </div>
                        </div>
                    </div>

                    <!-- Employees covered -->
                    <div class="animate-fade-in-up-delay-1 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-5 sm:p-6 shadow-sm">
                        <div class="flex items-start justify-between gap-4 mb-4">
                            <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/50 font-semibold">Employees
                                Covered</p>
                            <div class="w-9 h-9 rounded-lg bg-[#F1FDF6] flex items-center justify-center text-[#0F5E3D] shrink-0">
                                <i data-lucide="users" class="w-4 h-4"></i>
                            </div>
                        </div>
                        <p class="text-3xl font-bold text-[#2C3E50] mb-1"><?= $totalEmployees ?></p>
                        <p class="text-[10px] text-[#2C3E50]/40 mb-3">avg salary ₱<?= number_format($avgSalary, 2) ?></p>

                        <div class="space-y-2 pt-3 border-t border-[#E0E0E0]">
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-[#2C3E50]/60">Faculty</span>
                                <span class="text-xs font-semibold text-[#2C3E50]"><?= $categoryCounts['faculty'] ?></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-[#2C3E50]/60">Staff</span>
                                <span class="text-xs font-semibold text-[#2C3E50]"><?= $categoryCounts['staff'] ?></span>
                            </div>
                            <div class="flex items-center justify-between pt-1 border-t border-dashed border-[#E0E0E0]">
                                <span class="text-xs text-[#2C3E50]/60">Full-time</span>
                                <span class="text-xs font-semibold text-[#2C3E50]"><?= $employmentCounts['full-time'] ?></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-[#2C3E50]/60">Part-time</span>
                                <span class="text-xs font-semibold text-[#2C3E50]"><?= $employmentCounts['part-time'] ?></span>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- ============================================ -->
                <!-- CHARTS ROW                                     -->
                <!-- ============================================ -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 sm:gap-4">

                    <div class="lg:col-span-2 animate-fade-in-up-delay-2 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-5 shadow-sm">
                        <div class="flex items-start justify-between mb-3">
                            <div>
                                <h2 class="text-sm font-bold text-[#2C3E50]">Leave Pay Used by Department</h2>
                                <p class="text-[10px] text-[#2C3E50]/50 mt-0.5">₱ value of paid leave · top departments</p>
                            </div>
                            <div class="flex items-center gap-1.5 text-[10px] text-[#2C3E50]/60">
                                <span class="w-2 h-2 rounded-full bg-[#0F5E3D]"></span>
                                <span>Used (₱)</span>
                            </div>
                        </div>
                        <div class="relative h-56">
                            <canvas id="deptChart"></canvas>
                        </div>
                    </div>

                    <div class="animate-fade-in-up-delay-2 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-5 shadow-sm">
                        <div class="mb-3">
                            <h2 class="text-sm font-bold text-[#2C3E50]">Convertible Balance</h2>
                            <p class="text-[10px] text-[#2C3E50]/50 mt-0.5">Remaining credits · ₱ value</p>
                        </div>
                        <div class="relative h-40">
                            <canvas id="catChart"></canvas>
                        </div>
                        <div class="grid grid-cols-2 gap-2 mt-4 pt-3 border-t border-[#E0E0E0]">
                            <div class="text-center">
                                <span class="inline-block w-2 h-2 rounded-full bg-[#0F5E3D] mb-1"></span>
                                <p class="text-[10px] text-[#2C3E50]/50 uppercase tracking-wider">Faculty</p>
                                <p class="text-xs font-bold text-[#2C3E50]">₱<?= number_format($convertibleByCat['faculty'], 2) ?></p>
                            </div>
                            <div class="text-center">
                                <span class="inline-block w-2 h-2 rounded-full bg-slate-500 mb-1"></span>
                                <p class="text-[10px] text-[#2C3E50]/50 uppercase tracking-wider">Staff</p>
                                <p class="text-xs font-bold text-[#2C3E50]">₱<?= number_format($convertibleByCat['staff'], 2) ?></p>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- ============================================ -->
                <!-- QUICK STATS STRIP                              -->
                <!-- ============================================ -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <div class="animate-fade-in-up-delay-2 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-4 shadow-sm">
                        <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-1">Employees</p>
                        <p class="text-xl font-bold text-[#2C3E50]"><?= $totalEmployees ?></p>
                    </div>
                    <div class="animate-fade-in-up-delay-2 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-4 shadow-sm">
                        <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-1">Leave Pay Used</p>
                        <p class="text-xl font-bold text-[#0F5E3D]">₱<?= number_format($totalSaved, 2) ?></p>
                    </div>
                    <div class="animate-fade-in-up-delay-3 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-4 shadow-sm">
                        <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-1">Monthly Deduction</p>
                        <p class="text-xl font-bold text-red-600">₱<?= number_format($totalMonthlyDeduction, 2) ?></p>
                    </div>
                    <div class="animate-fade-in-up-delay-3 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-4 shadow-sm">
                        <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-1">Avg. Convertible</p>
                        <p class="text-xl font-bold text-amber-600">₱<?= number_format($avgConvertible, 2) ?></p>
                        <p class="text-[10px] text-[#2C3E50]/40 mt-0.5">per employee</p>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- TOOLBAR — z-30 + isolate keeps dropdowns above the table -->
                <!-- ============================================ -->
                <div class="animate-fade-in-up relative z-20 isolate bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-3 sm:p-4 shadow-sm">
                    <div class="flex flex-col lg:flex-row gap-3 lg:items-center lg:justify-between">

                        <div class="flex flex-wrap items-center gap-2">

                            <div class="relative" id="sortWrap">
                                <button onclick="toggleSortMenu()"
                                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-[#2C3E50]/70 hover:text-[#2C3E50] hover:bg-[#F1FDF6] transition">
                                    <i data-lucide="arrow-up-down" class="w-3.5 h-3.5"></i>
                                    <span id="sortLabel">Sort by</span>
                                    <i data-lucide="chevron-down" class="w-3 h-3 transition-transform" id="sortChevron"></i>
                                </button>

                                <div id="sortMenu"
                                    class="hidden absolute left-0 top-full mt-2 w-56 bg-white rounded-xl shadow-2xl border border-[#E0E0E0] overflow-hidden z-[999]">

                                    <div class="px-3 py-2 border-b border-[#E0E0E0] bg-[#F1FDF6]/40">
                                        <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/50 font-semibold">Sort By</p>
                                    </div>

                                    <div class="py-1">
                                        <button onclick="applySort('saved-desc')"
                                            class="w-full flex items-center gap-2.5 px-3 py-2 text-xs text-[#2C3E50] hover:bg-[#F1FDF6] transition text-left group">
                                            <div class="w-6 h-6 rounded-md bg-[#F1FDF6] flex items-center justify-center shrink-0 group-hover:bg-[#0F5E3D]/10">
                                                <i data-lucide="piggy-bank" class="w-3.5 h-3.5 text-[#0F5E3D]"></i>
                                            </div>
                                            <span class="flex-1">Most Used</span>
                                            <i data-lucide="check" class="w-3.5 h-3.5 text-[#0F5E3D] opacity-0 sort-check" data-sort="saved-desc"></i>
                                        </button>
                                        <button onclick="applySort('convertible-desc')"
                                            class="w-full flex items-center gap-2.5 px-3 py-2 text-xs text-[#2C3E50] hover:bg-[#F1FDF6] transition text-left group">
                                            <div class="w-6 h-6 rounded-md bg-[#F1FDF6] flex items-center justify-center shrink-0 group-hover:bg-[#0F5E3D]/10">
                                                <i data-lucide="banknote" class="w-3.5 h-3.5 text-[#0F5E3D]"></i>
                                            </div>
                                            <span class="flex-1">Most Convertible</span>
                                            <i data-lucide="check" class="w-3.5 h-3.5 text-[#0F5E3D] opacity-0 sort-check" data-sort="convertible-desc"></i>
                                        </button>
                                        <button onclick="applySort('salary-desc')"
                                            class="w-full flex items-center gap-2.5 px-3 py-2 text-xs text-[#2C3E50] hover:bg-[#F1FDF6] transition text-left group">
                                            <div class="w-6 h-6 rounded-md bg-[#F1FDF6] flex items-center justify-center shrink-0 group-hover:bg-[#0F5E3D]/10">
                                                <i data-lucide="badge-dollar-sign" class="w-3.5 h-3.5 text-[#0F5E3D]"></i>
                                            </div>
                                            <span class="flex-1">Highest Salary</span>
                                            <i data-lucide="check" class="w-3.5 h-3.5 text-[#0F5E3D] opacity-0 sort-check" data-sort="salary-desc"></i>
                                        </button>
                                    </div>

                                    <div class="border-t border-[#E0E0E0] py-1">
                                        <button onclick="applySort('name-asc')"
                                            class="w-full flex items-center gap-2.5 px-3 py-2 text-xs text-[#2C3E50] hover:bg-[#F1FDF6] transition text-left group">
                                            <div class="w-6 h-6 rounded-md bg-[#F1FDF6] flex items-center justify-center shrink-0 group-hover:bg-[#0F5E3D]/10">
                                                <i data-lucide="arrow-down-a-z" class="w-3.5 h-3.5 text-[#0F5E3D]"></i>
                                            </div>
                                            <span class="flex-1">Name (A → Z)</span>
                                            <i data-lucide="check" class="w-3.5 h-3.5 text-[#0F5E3D] opacity-0 sort-check" data-sort="name-asc"></i>
                                        </button>
                                        <button onclick="applySort('name-desc')"
                                            class="w-full flex items-center gap-2.5 px-3 py-2 text-xs text-[#2C3E50] hover:bg-[#F1FDF6] transition text-left group">
                                            <div class="w-6 h-6 rounded-md bg-[#F1FDF6] flex items-center justify-center shrink-0 group-hover:bg-[#0F5E3D]/10">
                                                <i data-lucide="arrow-up-z-a" class="w-3.5 h-3.5 text-[#0F5E3D]"></i>
                                            </div>
                                            <span class="flex-1">Name (Z → A)</span>
                                            <i data-lucide="check" class="w-3.5 h-3.5 text-[#0F5E3D] opacity-0 sort-check" data-sort="name-desc"></i>
                                        </button>
                                    </div>

                                    <div class="border-t border-[#E0E0E0] py-1">
                                        <button onclick="applySort('default')"
                                            class="w-full flex items-center gap-2.5 px-3 py-2 text-xs text-[#2C3E50]/60 hover:bg-red-50 hover:text-red-600 transition text-left group">
                                            <div class="w-6 h-6 rounded-md bg-slate-100 flex items-center justify-center shrink-0 group-hover:bg-red-100">
                                                <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                                            </div>
                                            <span class="flex-1">Reset Sort</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="relative" id="employmentWrap">
                                <button onclick="toggleEmploymentMenu()"
                                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-[#2C3E50]/70 hover:text-[#2C3E50] hover:bg-[#F1FDF6] transition">
                                    <i data-lucide="briefcase" class="w-3.5 h-3.5"></i>
                                    <span id="employmentLabel">Employment</span>
                                    <span id="employmentCount"
                                        class="hidden ml-1 px-1.5 py-0.5 rounded-full bg-[#0F5E3D] text-white text-[10px] font-semibold">0</span>
                                    <i data-lucide="chevron-down" class="w-3 h-3 transition-transform" id="employmentChevron"></i>
                                </button>

                                <div id="employmentMenu"
                                    class="hidden absolute left-0 top-full mt-2 w-56 bg-white rounded-xl shadow-2xl border border-[#E0E0E0] overflow-hidden z-[999]">

                                    <div class="px-3 py-2 border-b border-[#E0E0E0] bg-[#F1FDF6]/40 flex items-center justify-between">
                                        <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/50 font-semibold">Employment</p>
                                        <button onclick="setEmploymentFilter('')"
                                            class="text-[10px] font-medium text-[#0F5E3D] hover:text-[#0a4a2f] transition flex items-center gap-1">
                                            <i data-lucide="x" class="w-3 h-3"></i>
                                            <span>Clear</span>
                                        </button>
                                    </div>

                                    <div class="py-1">
                                        <button onclick="setEmploymentFilter('')"
                                            class="employment-option w-full flex items-center gap-2.5 px-3 py-2 text-xs text-[#2C3E50] hover:bg-[#F1FDF6] transition text-left group">
                                            <div class="w-6 h-6 rounded-md bg-slate-100 flex items-center justify-center shrink-0 group-hover:bg-[#0F5E3D]/10">
                                                <i data-lucide="list" class="w-3.5 h-3.5 text-[#2C3E50]/70"></i>
                                            </div>
                                            <span class="flex-1">All Employment</span>
                                            <i data-lucide="check" class="w-3.5 h-3.5 text-[#0F5E3D] opacity-0 employment-check" data-employment=""></i>
                                        </button>

                                        <button onclick="setEmploymentFilter('full-time')"
                                            class="employment-option w-full flex items-center gap-2.5 px-3 py-2 text-xs text-[#2C3E50] hover:bg-[#F1FDF6] transition text-left group">
                                            <div class="w-6 h-6 rounded-md bg-[#F1FDF6] flex items-center justify-center shrink-0 group-hover:bg-[#0F5E3D]/10">
                                                <i data-lucide="clock" class="w-3.5 h-3.5 text-[#0F5E3D]"></i>
                                            </div>
                                            <span class="flex-1">Full-time</span>
                                            <i data-lucide="check" class="w-3.5 h-3.5 text-[#0F5E3D] opacity-0 employment-check" data-employment="full-time"></i>
                                        </button>

                                        <button onclick="setEmploymentFilter('part-time')"
                                            class="employment-option w-full flex items-center gap-2.5 px-3 py-2 text-xs text-[#2C3E50] hover:bg-[#F1FDF6] transition text-left group">
                                            <div class="w-6 h-6 rounded-md bg-amber-50 flex items-center justify-center shrink-0 group-hover:bg-amber-100">
                                                <i data-lucide="clock-4" class="w-3.5 h-3.5 text-amber-600"></i>
                                            </div>
                                            <span class="flex-1">Part-time</span>
                                            <i data-lucide="check" class="w-3.5 h-3.5 text-[#0F5E3D] opacity-0 employment-check" data-employment="part-time"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="relative" id="filterWrap">
                                <button onclick="toggleFilterMenu()"
                                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-[#2C3E50]/70 hover:text-[#2C3E50] hover:bg-[#F1FDF6] transition">
                                    <i data-lucide="filter" class="w-3.5 h-3.5"></i>
                                    <span>Filter</span>
                                    <span id="filterCount"
                                        class="hidden ml-1 px-1.5 py-0.5 rounded-full bg-[#0F5E3D] text-white text-[10px] font-semibold">0</span>
                                    <i data-lucide="chevron-down" class="w-3 h-3 transition-transform" id="filterChevron"></i>
                                </button>

                                <div id="filterMenu"
                                    class="hidden absolute left-0 top-full mt-2 w-72 bg-white rounded-xl shadow-2xl border border-[#E0E0E0] overflow-hidden z-[999]">

                                    <div class="px-4 py-3 border-b border-[#E0E0E0] bg-[#F1FDF6]/40 flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <i data-lucide="filter" class="w-3.5 h-3.5 text-[#0F5E3D]"></i>
                                            <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/70 font-semibold">Filters</p>
                                        </div>
                                        <button onclick="clearAllFilters()"
                                            class="text-[10px] font-medium text-[#0F5E3D] hover:text-[#0a4a2f] transition flex items-center gap-1">
                                            <i data-lucide="x" class="w-3 h-3"></i>
                                            <span>Clear all</span>
                                        </button>
                                    </div>

                                    <div class="p-4 space-y-4">

                                        <div>
                                            <select id="filterCategory"
                                                onchange="updateFilterSubCategories(); updateFilterYearLevels(); filterPayroll();"
                                                class="custom-select w-full pl-3 py-2.5 bg-white border border-[#E0E0E0] rounded-lg text-xs text-[#2C3E50] focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition cursor-pointer">
                                                <option value="">All Categories</option>
                                                <option value="faculty">Faculty</option>
                                                <option value="staff">Staff</option>
                                            </select>
                                        </div>

                                        <div>
                                            <select id="filterEmployment" onchange="filterPayroll()"
                                                class="custom-select w-full pl-3 py-2.5 bg-white border border-[#E0E0E0] rounded-lg text-xs text-[#2C3E50] focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition cursor-pointer">
                                                <option value="">All Employment</option>
                                                <option value="full-time">Full-time</option>
                                                <option value="part-time">Part-time</option>
                                            </select>
                                        </div>

                                        <div>
                                            <select id="filterSubCategory"
                                                onchange="updateFilterYearLevels(); filterPayroll();"
                                                class="custom-select w-full pl-3 py-2.5 bg-white border border-[#E0E0E0] rounded-lg text-xs text-[#2C3E50] focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition cursor-pointer">
                                                <option value="">All Departments</option>
                                            </select>
                                        </div>

                                        <div>
                                            <select id="filterYearLevel" onchange="filterPayroll()"
                                                class="custom-select w-full pl-3 py-2.5 bg-white border border-[#E0E0E0] rounded-lg text-xs text-[#2C3E50] focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition cursor-pointer">
                                                <option value="">All Year Levels</option>
                                            </select>
                                        </div>

                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class="flex items-center gap-2">
                            <div class="relative flex-1 sm:w-72">
                                <i data-lucide="search"
                                    class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[#2C3E50]/40 pointer-events-none"></i>
                                <input type="text" id="searchInput" oninput="filterPayroll()" placeholder="Search employee"
                                    class="w-full pl-10 pr-10 py-2 bg-[#F1FDF6]/60 border border-transparent rounded-lg text-sm text-[#2C3E50] placeholder:text-[#2C3E50]/40 focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:bg-white focus:border-transparent transition">
                                <kbd class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-mono text-[#2C3E50]/40 bg-white px-1.5 py-0.5 rounded border border-[#E0E0E0]">/</kbd>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- ============================================ -->
                <!-- TABLE — no z-index so toolbar dropdowns stay above -->
                <!-- ============================================ -->
                <div class="animate-fade-in-up-delay-1 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl shadow-sm overflow-hidden">

                    <?php if (empty($rows)): ?>
                        <div class="p-12 text-center">
                            <div class="w-12 h-12 rounded-full bg-[#F1FDF6] flex items-center justify-center mx-auto mb-3 text-[#0F5E3D]">
                                <i data-lucide="wallet" class="w-5 h-5"></i>
                            </div>
                            <p class="text-sm font-medium text-[#2C3E50]">No employees found</p>
                            <p class="text-xs text-[#2C3E50]/50 mt-1">Add faculty or staff to see payroll data.</p>
                        </div>
                    <?php else: ?>

                        <div class="hidden md:block overflow-x-auto">
                            <table class="w-full">
                                <thead class="border-b border-[#E0E0E0]">
                                    <tr class="h-11">
                                        <th class="text-left text-xs font-medium text-[#2C3E50]/50 px-4 py-0">Employee</th>
                                        <th class="text-left text-xs font-medium text-[#2C3E50]/50 px-4 py-0">Category</th>
                                        <th class="text-right text-xs font-medium text-[#2C3E50]/50 px-4 py-0">Salary</th>
                                        <th class="text-right text-xs font-medium text-[#2C3E50]/50 px-4 py-0">Leave Pay</th>
                                        <th class="text-right text-xs font-medium text-[#2C3E50]/50 px-4 py-0">Used</th>
                                        <th class="text-right text-xs font-medium text-[#2C3E50]/50 px-4 py-0">Remaining</th>
                                        <th class="text-right text-xs font-medium text-[#2C3E50]/50 px-4 py-0">Pay Used</th>
                                        <th class="text-right text-xs font-medium text-[#2C3E50]/50 px-4 py-0">Deduction</th>
                                        <th class="text-right text-xs font-medium text-[#2C3E50]/50 px-4 py-0">Convertible</th>
                                    </tr>
                                </thead>
                                <tbody id="payrollTableBody" class="divide-y divide-[#E0E0E0]">
                                    <?php foreach ($rows as $r):
                                        $empClass = $r['employment'] === 'full-time'
                                            ? 'text-[#0F5E3D] bg-[#F1FDF6]'
                                            : 'text-slate-600 bg-slate-100';
                                    ?>
                                        <tr class="hover:bg-[#F1FDF6]/40 transition h-14 payroll-row"
                                            data-name="<?= strtolower($r['name']) ?>"
                                            data-category="<?= htmlspecialchars($r['category']) ?>"
                                            data-employment="<?= htmlspecialchars($r['employment']) ?>"
                                            data-sub="<?= htmlspecialchars(strtolower($r['sub_category'] ?? '')) ?>"
                                            data-year=""
                                            data-saved="<?= (float) $r['saved'] ?>"
                                            data-convertible="<?= (float) $r['convertible'] ?>"
                                            data-salary="<?= (float) $r['basic_salary'] ?>">
                                            <td class="px-4 py-0">
                                                <div class="flex items-center gap-2.5">
                                                    <div class="w-8 h-8 rounded-full bg-[#0F5E3D] text-white flex items-center justify-center font-semibold text-[10px] shrink-0">
                                                        <?= $r['initials'] ?>
                                                    </div>
                                                    <div class="min-w-0">
                                                        <p class="text-sm font-medium text-[#2C3E50] truncate">
                                                            <?= htmlspecialchars($r['name']) ?>
                                                        </p>
                                                        <p class="text-[10px] text-[#2C3E50]/40 truncate">
                                                            <?= htmlspecialchars($r['sub_category'] ?: $r['email']) ?>
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-0">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium capitalize text-slate-600 bg-slate-100">
                                                    <?= htmlspecialchars($r['category']) ?>
                                                </span>
                                                
                                            </td>
                                            <td class="px-4 py-0 text-right">
                                                <span class="text-sm text-[#2C3E50]"><?= $r['basic_salary'] > 0 ? '₱' . number_format($r['basic_salary'], 2) : '—' ?></span>
                                                <?php if ($r['daily_rate'] > 0): ?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-0 text-right">
                                                <span class="text-sm text-[#2C3E50]">₱<?= number_format($r['leave_pay'], 2) ?></span>
                                            </td>
                                            <td class="px-4 py-0 text-right">
                                                <span class="text-sm text-[#2C3E50] font-medium"><?= number_format($r['used'], 2) ?></span>
                                            </td>
                                            <td class="px-4 py-0 text-right">
                                                <span class="text-sm text-[#2C3E50] font-medium"><?= number_format($r['remaining']) ?></span>
                                            </td>
                                            <td class="px-4 py-0 text-right">
                                                <span class="text-sm font-bold text-[#0F5E3D]">₱<?= number_format($r['saved'], 2) ?></span>
                                            </td>
                                            <td class="px-4 py-0 text-right">
                                                <span class="text-sm font-bold text-red-600"><?= $r['monthly_deduction'] > 0 ? '₱' . number_format($r['monthly_deduction'], 2) : '—' ?></span>
                                            </td>
                                            <td class="px-4 py-0 text-right">
                                                <span class="text-sm font-bold text-amber-600">₱<?= number_format($r['convertible'], 2) ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>

                                <tfoot class="border-t-2 border-[#E0E0E0] bg-[#F1FDF6]/40">
                                    <tr class="h-14">
                                        <td class="px-4 py-0" colspan="2">
                                            <span class="text-xs uppercase tracking-wider text-[#2C3E50]/50 font-semibold">Total (all employees)</span>
                                        </td>
                                        <td class="px-4 py-0 text-right">
                                            <span class="text-sm font-bold text-[#2C3E50]">₱<?= number_format($totalSalaryPool, 2) ?></span>
                                        </td>
                                        <td class="px-4 py-0 text-right">
                                            <span class="text-xs text-[#2C3E50]/50">—</span>
                                        </td>
                                        <td class="px-4 py-0 text-right">
                                            <span class="text-sm font-bold text-[#2C3E50]"><?= number_format($totalUsedDays, 2) ?></span>
                                        </td>
                                        <td class="px-4 py-0 text-right">
                                            <span class="text-sm font-bold text-[#2C3E50]"><?= number_format($totalRemaining, 2) ?></span>
                                        </td>
                                        <td class="px-4 py-0 text-right">
                                            <span class="text-sm font-bold text-[#0F5E3D]">₱<?= number_format($totalSaved, 2) ?></span>
                                        </td>
                                        <td class="px-4 py-0 text-right">
                                            <span class="text-sm font-bold text-red-600">₱<?= number_format($totalMonthlyDeduction, 2) ?></span>
                                        </td>
                                        <td class="px-4 py-0 text-right">
                                            <span class="text-sm font-bold text-amber-600">₱<?= number_format($totalConvertible, 2) ?></span>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div id="payrollMobileCards" class="md:hidden divide-y divide-[#E0E0E0]">
                            <?php foreach ($rows as $r): ?>
                                <div class="p-4 hover:bg-[#F1FDF6]/40 transition payroll-row"
                                    data-name="<?= strtolower($r['name']) ?>"
                                    data-category="<?= htmlspecialchars($r['category']) ?>"
                                    data-employment="<?= htmlspecialchars($r['employment']) ?>"
                                    data-sub="<?= htmlspecialchars(strtolower($r['sub_category'] ?? '')) ?>"
                                    data-year=""
                                    data-saved="<?= (float) $r['saved'] ?>"
                                    data-convertible="<?= (float) $r['convertible'] ?>"
                                    data-salary="<?= (float) $r['basic_salary'] ?>">
                                    <div class="flex items-start gap-3 mb-3">
                                        <div class="w-9 h-9 rounded-full bg-[#0F5E3D] text-white flex items-center justify-center font-semibold text-xs shrink-0">
                                            <?= $r['initials'] ?>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-[#2C3E50] truncate">
                                                <?= htmlspecialchars($r['name']) ?>
                                            </p>
                                            <p class="text-[10px] text-[#2C3E50]/40 truncate">
                                                <?= htmlspecialchars(ucfirst($r['category'])) ?> · <?= htmlspecialchars(ucfirst($r['employment'])) ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2 text-xs">
                                        <div>
                                            <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 mb-0.5">Salary</p>
                                            <p class="text-[#2C3E50] font-medium"><?= $r['basic_salary'] > 0 ? '₱' . number_format($r['basic_salary'], 2) : '—' ?></p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 mb-0.5">Leave Pay</p>
                                            <p class="text-[#2C3E50] font-medium">₱<?= number_format($r['leave_pay'], 2) ?> <span class="text-[10px] text-[#2C3E50]/40">/ credit</span></p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 mb-0.5">Used / Remaining</p>
                                            <p class="text-[#2C3E50] font-medium">
                                                <span><?= number_format($r['used'], 2) ?></span>
                                                <span class="text-[#2C3E50]/40"> / </span>
                                                <span><?= number_format($r['remaining'], 2) ?></span>
                                            </p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 mb-0.5">Pay Used</p>
                                            <p class="text-[#0F5E3D] font-bold">₱<?= number_format($r['saved'], 2) ?></p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 mb-0.5">Deduction</p>
                                            <p class="text-red-600 font-bold"><?= $r['monthly_deduction'] > 0 ? '₱' . number_format($r['monthly_deduction'], 2) : '—' ?></p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 mb-0.5">Convertible</p>
                                            <p class="text-amber-600 font-bold">₱<?= number_format($r['convertible'], 2) ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                    <?php endif; ?>

                    <div id="emptyState" class="hidden p-12 text-center">
                        <div class="w-12 h-12 rounded-full bg-[#F1FDF6] flex items-center justify-center mx-auto mb-3 text-[#0F5E3D]">
                            <i data-lucide="search-x" class="w-5 h-5"></i>
                        </div>
                        <p class="text-sm font-medium text-[#2C3E50]">No employees found</p>
                        <p class="text-xs text-[#2C3E50]/50 mt-1">Try adjusting your search or filters.</p>
                    </div>

                    <div class="px-4 sm:px-6 py-3 border-t border-[#E0E0E0] flex flex-col sm:flex-row items-center justify-between gap-3">
                        <p class="text-xs text-[#2C3E50]/50">
                            Showing
                            <span class="font-medium text-[#2C3E50]"><?= count($rows) ?></span>
                            of
                            <span class="font-medium text-[#2C3E50]"><?= $totalEmployees ?></span>
                            employees
                            <?php if ($totalPages > 1): ?>
                                · Page <span class="font-medium text-[#2C3E50]"><?= $page ?></span> of <span class="font-medium text-[#2C3E50]"><?= $totalPages ?></span>
                            <?php endif; ?>
                        </p>

                        <?php if ($totalPages > 1): ?>
                            <div class="flex items-center gap-1">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?= $page - 1 ?>"
                                        class="w-8 h-8 rounded-lg flex items-center justify-center text-[#2C3E50]/70 hover:bg-[#F1FDF6] hover:text-[#0F5E3D] transition">
                                        <i data-lucide="chevron-left" class="w-4 h-4"></i>
                                    </a>
                                <?php else: ?>
                                    <button class="w-8 h-8 rounded-lg flex items-center justify-center text-[#2C3E50]/30 cursor-not-allowed" disabled>
                                        <i data-lucide="chevron-left" class="w-4 h-4"></i>
                                    </button>
                                <?php endif; ?>

                                <?php
                                $start = max(1, $page - 2);
                                $end   = min($totalPages, $start + 4);
                                $start = max(1, $end - 4);
                                for ($i = $start; $i <= $end; $i++):
                                ?>
                                    <?php if ($i === $page): ?>
                                        <span class="w-8 h-8 rounded-lg bg-[#0F5E3D] text-white text-xs font-medium flex items-center justify-center"><?= $i ?></span>
                                    <?php else: ?>
                                        <a href="?page=<?= $i ?>"
                                            class="w-8 h-8 rounded-lg flex items-center justify-center text-[#2C3E50]/70 hover:bg-[#F1FDF6] hover:text-[#0F5E3D] transition text-xs font-medium">
                                            <?= $i ?>
                                        </a>
                                    <?php endif; ?>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <a href="?page=<?= $page + 1 ?>"
                                        class="w-8 h-8 rounded-lg flex items-center justify-center text-[#2C3E50]/70 hover:bg-[#F1FDF6] hover:text-[#0F5E3D] transition">
                                        <i data-lucide="chevron-right" class="w-4 h-4"></i>
                                    </a>
                                <?php else: ?>
                                    <button class="w-8 h-8 rounded-lg flex items-center justify-center text-[#2C3E50]/30 cursor-not-allowed" disabled>
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

    <?php require_once __DIR__ . '/../components/logout_modal.php'; ?>

    <script>
        window.PAYROLL_CHART_DATA = {
            dept: {
                labels: <?= json_encode($deptLabels) ?>,
                values: <?= json_encode($deptValues) ?>
            },
            category: {
                faculty: <?= (float) $convertibleByCat['faculty'] ?>,
                staff: <?= (float) $convertibleByCat['staff'] ?>
            }
        };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="/assets/js/app.js"></script>
    <script src="/assets/js/notifications.js"></script>
    <script src="/assets/js/profile.js"></script>
    <script src="/assets/js/payroll.js"></script>
</body>

</html>