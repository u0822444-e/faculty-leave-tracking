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

$workingDaysPerMonth = 22;

// ============================================
// Fetch all faculty & staff with their leave usage
// ============================================
$stmt = $pdo->query("
    SELECT 
        e.id AS employee_id,
        e.first_name, e.middle_name, e.last_name, e.email,
        e.category, e.sub_category, e.employment_type,
        e.basic_salary, e.leave_credits,
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
");
$employees = $stmt->fetchAll();

// ============================================
// Compute rows + aggregates
// ============================================
$rows = [];
$totalSaved       = 0;
$totalConvertible = 0;
$totalUsedDays    = 0;
$totalRemaining   = 0;

$categoryCounts   = ['faculty' => 0, 'staff' => 0];
$employmentCounts = ['full-time' => 0, 'part-time' => 0];

$savedByDept     = [];
$convertibleByCat = ['faculty' => 0, 'staff' => 0];

foreach ($employees as $e) {
    $monthlySalary = (float) $e['basic_salary'];
    $dailyRate     = $monthlySalary > 0 ? $monthlySalary / $workingDaysPerMonth : 0;

    $used      = (float) $e['credits_used'];
    $allocated = (float) $e['leave_credits'];
    $remaining = max(0, $allocated - $used);

    // Saved = ₱ value of unpaid leave days (institution keeps this)
    $saved       = $dailyRate * $used;
    // Convertible = ₱ value of unused credits (institution may owe this)
    $convertible = $dailyRate * $remaining;

    $totalSaved       += $saved;
    $totalConvertible += $convertible;
    $totalUsedDays    += $used;
    $totalRemaining   += $remaining;

    $cat = $e['category'] ?? 'staff';
    if (isset($categoryCounts[$cat])) $categoryCounts[$cat]++;

    $emp = $e['employment_type'] ?? 'full-time';
    if (isset($employmentCounts[$emp])) $employmentCounts[$emp]++;

    $dept = trim($e['sub_category'] ?? '');
    if ($dept === '') $dept = 'Unassigned';
    if (!isset($savedByDept[$dept])) $savedByDept[$dept] = 0;
    $savedByDept[$dept] += $saved;

    if (isset($convertibleByCat[$cat])) {
        $convertibleByCat[$cat] += $convertible;
    }

    $rows[] = [
        'employee_id'  => (int) $e['employee_id'],
        'name'         => trim(($e['first_name'] ?? '') . ' ' . ($e['last_name'] ?? '')) ?: ($e['username'] ?? 'Unknown'),
        'initials'     => strtoupper(substr($e['first_name'] ?? 'U', 0, 1) . substr($e['last_name'] ?? '', 0, 1)),
        'email'        => $e['email'] ?? '—',
        'category'     => $cat,
        'sub_category' => $e['sub_category'] ?? '',
        'employment'   => $emp,
        'monthly'      => $monthlySalary,
        'daily_rate'   => $dailyRate,
        'allocated'    => $allocated,
        'used'         => $used,
        'remaining'    => $remaining,
        'saved'        => $saved,
        'convertible'  => $convertible,
        'status'       => $e['user_status'] ?? 'active',
    ];
}

$totalEmployees = count($rows);

// Sort by saved DESC (biggest institutional savings first)
usort($rows, fn($a, $b) => $b['saved'] <=> $a['saved']);

// Top departments by saved
arsort($savedByDept);
$deptChart  = array_slice($savedByDept, 0, 6, true);
$deptLabels = array_keys($deptChart);
$deptValues = array_values($deptChart);

// Average convertible per employee (for the stat card)
$avgConvertible = $totalEmployees > 0 ? $totalConvertible / $totalEmployees : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll | DAMMC Admin</title>
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
        $activePage = 'payroll';
        require __DIR__ . '/../components/sidebar.php';
        ?>

        <div class="flex-1 md:ml-64 w-full min-w-0">

            <?php
            $pageTitle = 'Payroll';
            $pageSubtitle = 'Institution-wide payroll savings & convertible liabilities';
            $pageIcon = 'wallet';
            require __DIR__ . '/../components/topbar.php';
            ?>

            <main class="p-4 sm:p-6 lg:p-8 space-y-4 sm:space-y-6">

                <!-- ============================================ -->
                <!-- HERO ROW                                       -->
                <!-- ============================================ -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 sm:gap-4">

                    <!-- Payroll saved this period -->
                    <div class="lg:col-span-2 animate-fade-in-up bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-5 sm:p-6 shadow-sm">
                        <div class="flex items-start justify-between gap-4 mb-4">
                            <div>
                                <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/50 font-semibold mb-1">Payroll Saved This Period</p>
                                <div class="flex items-baseline gap-2">
                                    <span class="text-3xl sm:text-4xl font-bold text-[#0F5E3D]">
                                        ₱<?= number_format($totalSaved, 2) ?>
                                    </span>
                                </div>
                                <p class="text-xs text-[#2C3E50]/50 mt-1">
                                    from unpaid leave days across all faculty &amp; staff
                                </p>
                            </div>
                            <div class="w-10 h-10 rounded-lg bg-[#F1FDF6] flex items-center justify-center text-[#0F5E3D] shrink-0">
                                <i data-lucide="shield-check" class="w-5 h-5"></i>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4 pt-4 border-t border-[#E0E0E0]">
                            <div>
                                <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-0.5">Approved Leave Days</p>
                                <p class="text-lg font-bold text-[#2C3E50]"><?= number_format($totalUsedDays, 2) ?></p>
                                <p class="text-[10px] text-[#2C3E50]/40 mt-0.5">unpaid work days</p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-0.5">Convertible Liability</p>
                                <p class="text-lg font-bold text-amber-600">₱<?= number_format($totalConvertible, 2) ?></p>
                                <p class="text-[10px] text-[#2C3E50]/40 mt-0.5">remaining unused credits</p>
                            </div>
                        </div>
                    </div>

                    <!-- Employees covered -->
                    <div class="animate-fade-in-up-delay-1 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-5 sm:p-6 shadow-sm">
                        <div class="flex items-start justify-between gap-4 mb-4">
                            <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/50 font-semibold">Employees Covered</p>
                            <div class="w-9 h-9 rounded-lg bg-[#F1FDF6] flex items-center justify-center text-[#0F5E3D] shrink-0">
                                <i data-lucide="users" class="w-4 h-4"></i>
                            </div>
                        </div>
                        <p class="text-3xl font-bold text-[#2C3E50] mb-3"><?= $totalEmployees ?></p>

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

                    <!-- Bar: saved by department -->
                    <div class="lg:col-span-2 animate-fade-in-up-delay-2 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-5 shadow-sm">
                        <div class="flex items-start justify-between mb-3">
                            <div>
                                <h2 class="text-sm font-bold text-[#2C3E50]">Unpaid Leave Cost by Department</h2>
                                <p class="text-[10px] text-[#2C3E50]/50 mt-0.5">₱ value of unpaid leave days · top departments</p>
                            </div>
                            <div class="flex items-center gap-1.5 text-[10px] text-[#2C3E50]/60">
                                <span class="w-2 h-2 rounded-full bg-[#0F5E3D]"></span>
                                <span>Saved (₱)</span>
                            </div>
                        </div>
                        <div class="relative h-56">
                            <canvas id="deptChart"></canvas>
                        </div>
                    </div>

                    <!-- Donut: convertible liability by category -->
                    <div class="animate-fade-in-up-delay-2 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-5 shadow-sm">
                        <div class="mb-3">
                            <h2 class="text-sm font-bold text-[#2C3E50]">Convertible Liability</h2>
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
                        <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-1">Approved Leave Days</p>
                        <p class="text-xl font-bold text-[#2C3E50]"><?= number_format($totalUsedDays, 2) ?></p>
                    </div>
                    <div class="animate-fade-in-up-delay-3 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-4 shadow-sm">
                        <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-1">Payroll Saved</p>
                        <p class="text-xl font-bold text-[#0F5E3D]">₱<?= number_format($totalSaved, 2) ?></p>
                    </div>
                    <div class="animate-fade-in-up-delay-3 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-4 shadow-sm">
                        <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-1">Avg. Convertible</p>
                        <p class="text-xl font-bold text-amber-600">₱<?= number_format($avgConvertible, 2) ?></p>
                        <p class="text-[10px] text-[#2C3E50]/40 mt-0.5">per employee</p>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- TOOLBAR                                        -->
                <!-- ============================================ -->
                <div class="animate-fade-in-up relative z-30 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-3 sm:p-4 shadow-sm">
                    <div class="flex flex-col lg:flex-row gap-3 lg:items-center lg:justify-between">

                        <div class="flex flex-wrap items-center gap-2">
                            <select id="categoryFilter" onchange="filterPayroll()"
                                class="px-3 py-1.5 rounded-lg text-xs font-medium text-[#2C3E50]/80 bg-[#F1FDF6] border border-[#E0E0E0] focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition cursor-pointer">
                                <option value="">All Categories</option>
                                <option value="faculty">Faculty</option>
                                <option value="staff">Staff</option>
                            </select>

                            <select id="employmentFilter" onchange="filterPayroll()"
                                class="px-3 py-1.5 rounded-lg text-xs font-medium text-[#2C3E50]/80 bg-[#F1FDF6] border border-[#E0E0E0] focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition cursor-pointer">
                                <option value="">All Employment</option>
                                <option value="full-time">Full-time</option>
                                <option value="part-time">Part-time</option>
                            </select>

                            <select id="sortFilter" onchange="sortPayroll()"
                                class="px-3 py-1.5 rounded-lg text-xs font-medium text-[#2C3E50]/80 bg-[#F1FDF6] border border-[#E0E0E0] focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition cursor-pointer">
                                <option value="saved-desc">Most Saved</option>
                                <option value="convertible-desc">Most Convertible</option>
                                <option value="name-asc">Name (A → Z)</option>
                            </select>
                        </div>

                        <div class="flex items-center gap-2">
                            <div class="relative flex-1 sm:w-72">
                                <i data-lucide="search"
                                    class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[#2C3E50]/40 pointer-events-none"></i>
                                <input type="text" id="searchInput" oninput="filterPayroll()" placeholder="Search employee"
                                    class="w-full pl-10 pr-10 py-2 bg-[#F1FDF6]/60 border border-transparent rounded-lg text-sm text-[#2C3E50] placeholder:text-[#2C3E50]/40 focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:bg-white focus:border-transparent transition">
                                <kbd
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-mono text-[#2C3E50]/40 bg-white px-1.5 py-0.5 rounded border border-[#E0E0E0]">/</kbd>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- ============================================ -->
                <!-- TABLE                                          -->
                <!-- ============================================ -->
                <div class="relative z-10 animate-fade-in-up-delay-1 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl shadow-sm overflow-hidden">

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
                                        <th class="text-right text-xs font-medium text-[#2C3E50]/50 px-4 py-0">Monthly</th>
                                        <th class="text-right text-xs font-medium text-[#2C3E50]/50 px-4 py-0">Used</th>
                                        <th class="text-right text-xs font-medium text-[#2C3E50]/50 px-4 py-0">Remaining</th>
                                        <th class="text-right text-xs font-medium text-[#2C3E50]/50 px-4 py-0">Payroll Saved</th>
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
                                            data-category="<?= $r['category'] ?>"
                                            data-employment="<?= $r['employment'] ?>"
                                            data-saved="<?= $r['saved'] ?>"
                                            data-convertible="<?= $r['convertible'] ?>">
                                            <td class="px-4 py-0">
                                                <div class="flex items-center gap-2.5">
                                                    <div class="w-8 h-8 rounded-full bg-[#0F5E3D] text-white flex items-center justify-center font-semibold text-[10px] shrink-0">
                                                        <?= $r['initials'] ?>
                                                    </div>
                                                    <div class="min-w-0">
                                                        <p class="text-sm font-medium text-[#2C3E50] truncate"><?= htmlspecialchars($r['name']) ?></p>
                                                        <p class="text-[10px] text-[#2C3E50]/40 truncate"><?= htmlspecialchars($r['sub_category'] ?: $r['email']) ?></p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-0">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium capitalize text-slate-600 bg-slate-100">
                                                    <?= htmlspecialchars($r['category']) ?>
                                                </span>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium capitalize ml-1 <?= $empClass ?>">
                                                    <?= htmlspecialchars($r['employment']) ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-0 text-right">
                                                <span class="text-sm text-[#2C3E50]">₱<?= number_format($r['monthly'], 2) ?></span>
                                            </td>
                                            <td class="px-4 py-0 text-right">
                                                <span class="text-sm text-[#2C3E50] font-medium"><?= number_format($r['used'], 2) ?></span>
                                                <p class="text-[10px] text-[#2C3E50]/40">of <?= number_format($r['allocated'], 2) ?></p>
                                            </td>
                                            <td class="px-4 py-0 text-right">
                                                <span class="text-sm text-[#2C3E50] font-medium"><?= number_format($r['remaining'], 2) ?></span>
                                            </td>
                                            <td class="px-4 py-0 text-right">
                                                <span class="text-sm font-bold text-[#0F5E3D]">
                                                    ₱<?= number_format($r['saved'], 2) ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-0 text-right">
                                                <span class="text-sm font-bold text-amber-600">
                                                    ₱<?= number_format($r['convertible'], 2) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>

                                <!-- Total row -->
                                <tfoot class="border-t-2 border-[#E0E0E0] bg-[#F1FDF6]/40">
                                    <tr class="h-14">
                                        <td class="px-4 py-0">
                                            <span class="text-xs uppercase tracking-wider text-[#2C3E50]/50 font-semibold">Total</span>
                                        </td>
                                        <td class="px-4 py-0"></td>
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
                                            <span class="text-sm font-bold text-amber-600">₱<?= number_format($totalConvertible, 2) ?></span>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- Mobile cards -->
                        <div id="payrollMobileCards" class="md:hidden divide-y divide-[#E0E0E0]">
                            <?php foreach ($rows as $r): ?>
                                <div class="p-4 hover:bg-[#F1FDF6]/40 transition payroll-row"
                                    data-name="<?= strtolower($r['name']) ?>"
                                    data-category="<?= $r['category'] ?>"
                                    data-employment="<?= $r['employment'] ?>"
                                    data-saved="<?= $r['saved'] ?>"
                                    data-convertible="<?= $r['convertible'] ?>">
                                    <div class="flex items-start gap-3 mb-3">
                                        <div class="w-9 h-9 rounded-full bg-[#0F5E3D] text-white flex items-center justify-center font-semibold text-xs shrink-0">
                                            <?= $r['initials'] ?>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-[#2C3E50] truncate"><?= htmlspecialchars($r['name']) ?></p>
                                            <p class="text-[10px] text-[#2C3E50]/40 truncate">
                                                <?= htmlspecialchars(ucfirst($r['category'])) ?> · <?= htmlspecialchars(ucfirst($r['employment'])) ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2 text-xs">
                                        <div>
                                            <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 mb-0.5">Monthly</p>
                                            <p class="text-[#2C3E50] font-medium">₱<?= number_format($r['monthly'], 2) ?></p>
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
                                            <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 mb-0.5">Payroll Saved</p>
                                            <p class="text-[#0F5E3D] font-bold">₱<?= number_format($r['saved'], 2) ?></p>
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

                    <div class="px-4 sm:px-6 py-3 border-t border-[#E0E0E0]">
                        <p class="text-xs text-[#2C3E50]/50">
                            Showing <span class="font-medium text-[#2C3E50]"><?= $totalEmployees ?></span> employee<?= $totalEmployees === 1 ? '' : 's' ?>
                        </p>
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
            staff:   <?= (float) $convertibleByCat['staff'] ?>
        }
    };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="/assets/js/app.js"></script>
    <script src="/assets/js/notifications.js"></script>
    <script src="/assets/js/profile.js"></script>
    <script>
        // ============================================
        // Filter + sort
        // ============================================
        function filterPayroll() {
            const search     = (document.getElementById('searchInput')?.value || '').toLowerCase();
            const category   = document.getElementById('categoryFilter')?.value || '';
            const employment = document.getElementById('employmentFilter')?.value || '';

            const rows = document.querySelectorAll('.payroll-row');
            let visible = 0;

            rows.forEach(row => {
                const name = row.dataset.name || '';
                const cat  = row.dataset.category || '';
                const emp  = row.dataset.employment || '';

                const matches = (!search || name.includes(search))
                             && (!category || cat === category)
                             && (!employment || emp === employment);

                row.classList.toggle('hidden', !matches);
                if (matches) visible++;
            });

            document.getElementById('emptyState').classList.toggle('hidden', visible > 0);
        }

        function sortPayroll() {
            const mode = document.getElementById('sortFilter')?.value || 'saved-desc';

            const tbody = document.getElementById('payrollTableBody');
            const mobileWrap = document.getElementById('payrollMobileCards');

            const cmp = (a, b) => {
                if (mode === 'name-asc') {
                    return (a.dataset.name || '').localeCompare(b.dataset.name || '');
                }
                if (mode === 'convertible-desc') {
                    return parseFloat(b.dataset.convertible || 0) - parseFloat(a.dataset.convertible || 0);
                }
                // default: saved-desc
                return parseFloat(b.dataset.saved || 0) - parseFloat(a.dataset.saved || 0);
            };

            if (tbody) {
                const rows = Array.from(tbody.querySelectorAll('tr.payroll-row'));
                rows.sort(cmp);
                rows.forEach(r => tbody.appendChild(r));
            }

            if (mobileWrap) {
                const cards = Array.from(mobileWrap.querySelectorAll('div.payroll-row'));
                cards.sort(cmp);
                cards.forEach(c => mobileWrap.appendChild(c));
            }
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === '/' && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
                e.preventDefault();
                document.getElementById('searchInput')?.focus();
            }
        });

        // ============================================
        // Charts
        // ============================================
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof initIcons === 'function') initIcons();
            if (typeof Chart === 'undefined') return;

            const data = window.PAYROLL_CHART_DATA || {};
            const COLORS = {
                green:     '#0F5E3D',
                amber:     '#f59e0b',
                slate:     '#64748b',
                text:      '#2C3E50',
                textMuted: 'rgba(44, 62, 80, 0.5)',
                divider:   '#E0E0E0',
            };

            Chart.defaults.font.family = "'Inter', sans-serif";
            Chart.defaults.font.size = 11;
            Chart.defaults.color = COLORS.textMuted;

            const pesoFmt = (v) => '₱' + Number(v).toLocaleString('en-PH', {
                minimumFractionDigits: 2, maximumFractionDigits: 2
            });

            // Bar: saved by department
            const deptCanvas = document.getElementById('deptChart');
            if (deptCanvas && data.dept) {
                new Chart(deptCanvas.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: data.dept.labels,
                        datasets: [{
                            data: data.dept.values,
                            backgroundColor: COLORS.green,
                            borderRadius: 6,
                            borderSkipped: false,
                            barThickness: 22,
                        }]
                    },
                    options: {
                        indexAxis: 'y',
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
                                callbacks: { label: (ctx) => pesoFmt(ctx.parsed.x) }
                            }
                        },
                        scales: {
                            x: {
                                beginAtZero: true,
                                grid: { color: COLORS.divider },
                                border: { display: false },
                                ticks: {
                                    color: COLORS.textMuted,
                                    font: { size: 10 },
                                    callback: (v) => '₱' + Number(v).toLocaleString('en-PH'),
                                }
                            },
                            y: {
                                grid: { display: false },
                                border: { display: false },
                                ticks: { color: COLORS.text, font: { size: 11, weight: '500' } }
                            }
                        }
                    }
                });
            }

            // Donut: convertible by category
            const catCanvas = document.getElementById('catChart');
            if (catCanvas && data.category) {
                const faculty = data.category.faculty || 0;
                const staff   = data.category.staff || 0;
                const total   = faculty + staff;

                new Chart(catCanvas.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: ['Faculty', 'Staff'],
                        datasets: [{
                            data: [faculty, staff],
                            backgroundColor: [COLORS.green, COLORS.slate],
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
                                        return `₱${Number(v).toLocaleString('en-PH')} (${pct}%)`;
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
                            ctx.font = 'bold 13px Inter, sans-serif';
                            ctx.fillText('₱' + Math.round(total).toLocaleString('en-PH'), cx, cy - 4);
                            ctx.fillStyle = COLORS.textMuted;
                            ctx.font = '9px Inter, sans-serif';
                            ctx.fillText('total', cx, cy + 12);
                            ctx.restore();
                        }
                    }]
                });
            }
        });
    </script>
</body>

</html>