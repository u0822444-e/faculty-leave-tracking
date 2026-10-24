<?php
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hr') {
    header('Location: /login');
    exit;
}

require_once __DIR__ . '/../../config/database.php';

// ============================================
// EXPORT HANDLER — ?export=xlsx
// ============================================
if (isset($_GET['export']) && $_GET['export'] === 'xlsx') {
    require_once __DIR__ . '/../lib/SimpleXlsx.php';

    // ---------- SHEET 1: Directory ----------
    $stmt = $pdo->query("
        SELECT e.id, e.first_name, e.middle_name, e.last_name, e.email,
               e.category, e.sub_category, e.year_level, e.position,
               e.employment_type, e.basic_salary, e.leave_credits, e.leave_pay
        FROM employees e
        WHERE e.category IN ('faculty', 'staff')
        ORDER BY e.category ASC, e.last_name ASC, e.first_name ASC
    ");
    $employees = $stmt->fetchAll();

    $directory = [[
        'ID','First Name','Middle Name','Last Name','Full Name','Email',
        'Category','Department','Year Level','Position',
        'Employment Type','Monthly Salary','Leave Credits','Leave Pay Rate (₱/credit)',
    ]];
    foreach ($employees as $e) {
        $directory[] = [
            ['v' => (int) $e['id'], 'style' => 'number'],
            $e['first_name'] ?? '',
            $e['middle_name'] ?? '',
            $e['last_name'] ?? '',
            trim(($e['first_name'] ?? '') . ' ' . ($e['middle_name'] ?? '') . ' ' . ($e['last_name'] ?? '')),
            $e['email'] ?? '',
            ucfirst($e['category'] ?? ''),
            $e['sub_category'] ?? '',
            $e['year_level'] ?? '',
            $e['position'] ?? '',
            ucfirst($e['employment_type'] ?? ''),
            ['v' => (float) ($e['basic_salary'] ?? 0), 'style' => 'currency'],
            ['v' => (float) ($e['leave_credits'] ?? 0), 'style' => 'number'],
            ['v' => (float) ($e['leave_pay'] ?? 0), 'style' => 'currency'],
        ];
    }

    // ---------- SHEET 2: Leave Requests ----------
    $stmt = $pdo->query("
        SELECT lr.id, lr.leave_type, lr.start_date, lr.end_date, lr.days_count,
               lr.status, lr.pay_status, lr.reason, lr.remarks,
               lr.created_at, lr.approved_at,
               e.first_name, e.last_name, e.category, e.sub_category,
               u.username AS approver
        FROM leave_requests lr
        LEFT JOIN employees e ON lr.employee_id = e.id
        LEFT JOIN users u ON lr.approved_by = u.id
        ORDER BY lr.created_at DESC
    ");
    $leaves = $stmt->fetchAll();

    $typeLabels = [
        'vacation'=>'Vacation','sick'=>'Sick','maternity'=>'Maternity',
        'paternity'=>'Paternity','terminal'=>'Terminal','other'=>'Other',
    ];

    $leavesSheet = [[
        'Request ID','Employee','Category','Department',
        'Leave Type','Start Date','End Date','Days',
        'Status','Pay Status','Reason','Remarks',
        'Filed At','Decided At','Decided By',
    ]];
    foreach ($leaves as $l) {
        $name = trim(($l['first_name'] ?? '') . ' ' . ($l['last_name'] ?? '')) ?: 'Unknown';
        $pay = $l['pay_status'] === 'with_pay' ? 'With Pay'
             : ($l['pay_status'] === 'without_pay' ? 'Without Pay' : '—');
        $leavesSheet[] = [
            ['v' => (int) $l['id'], 'style' => 'number'],
            $name,
            ucfirst($l['category'] ?? ''),
            $l['sub_category'] ?? '',
            $typeLabels[strtolower($l['leave_type'] ?? '')] ?? ucfirst($l['leave_type'] ?? ''),
            $l['start_date'] ? date('Y-m-d', strtotime($l['start_date'])) : '',
            $l['end_date'] ? date('Y-m-d', strtotime($l['end_date'])) : '',
            ['v' => (float) $l['days_count'], 'style' => 'number'],
            ucfirst($l['status'] ?? ''),
            $pay,
            $l['reason'] ?? '',
            $l['remarks'] ?? '',
            $l['created_at'] ? date('Y-m-d H:i', strtotime($l['created_at'])) : '',
            $l['approved_at'] ? date('Y-m-d H:i', strtotime($l['approved_at'])) : '',
            $l['approver'] ?? '',
        ];
    }

    // ---------- SHEET 3: Leave Summary per Employee ----------
    $stmt = $pdo->query("
        SELECT e.id, e.first_name, e.last_name, e.category, e.sub_category, e.leave_credits,
            SUM(CASE WHEN lr.status='approved' THEN 1 ELSE 0 END) AS approved_count,
            SUM(CASE WHEN lr.status='approved' THEN lr.days_count ELSE 0 END) AS approved_days,
            SUM(CASE WHEN lr.status='pending' THEN 1 ELSE 0 END) AS pending_count,
            SUM(CASE WHEN lr.status='rejected' THEN 1 ELSE 0 END) AS rejected_count,
            SUM(CASE WHEN lr.status='approved' AND lr.leave_type='sick' THEN lr.days_count ELSE 0 END) AS sick_used,
            SUM(CASE WHEN lr.status='approved' AND lr.leave_type='vacation' THEN lr.days_count ELSE 0 END) AS vacation_used,
            SUM(CASE WHEN lr.status='approved' AND lr.leave_type='other' THEN lr.days_count ELSE 0 END) AS lwop_days
        FROM employees e
        LEFT JOIN leave_requests lr ON lr.employee_id = e.id
        WHERE e.category IN ('faculty', 'staff')
        GROUP BY e.id
        ORDER BY e.category ASC, e.last_name ASC
    ");
    $summary = $stmt->fetchAll();

    $summarySheet = [[
        'Employee','Category','Department','Allocated Credits',
        'Approved Days','Remaining Credits','Sick Used','Vacation Used',
        'LWOP Days','Pending Requests','Rejected Requests',
    ]];
    foreach ($summary as $s) {
        $name = trim(($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? ''));
        $allocated = (float) $s['leave_credits'];
        $used = (float) $s['approved_days'];
        $summarySheet[] = [
            $name,
            ucfirst($s['category'] ?? ''),
            $s['sub_category'] ?? '',
            ['v' => $allocated, 'style' => 'number'],
            ['v' => $used, 'style' => 'number'],
            ['v' => max(0, $allocated - $used), 'style' => 'number'],
            ['v' => (float) $s['sick_used'], 'style' => 'number'],
            ['v' => (float) $s['vacation_used'], 'style' => 'number'],
            ['v' => (float) $s['lwop_days'], 'style' => 'number'],
            ['v' => (int) $s['pending_count'], 'style' => 'number'],
            ['v' => (int) $s['rejected_count'], 'style' => 'number'],
        ];
    }

    // ---------- SHEET 4: Payroll Settlements ----------
    $stmt = $pdo->query("
        SELECT e.id, e.first_name, e.last_name, e.category, e.sub_category,
               e.basic_salary, e.leave_pay,
               COALESCE(SUM(CASE WHEN lr.status='approved' AND lr.leave_type='sick' THEN lr.days_count ELSE 0 END), 0) AS sick_used,
               ps.status AS settlement_status,
               ps.amount AS settlement_amount,
               ps.settled_at, ps.remarks AS settlement_remarks
        FROM employees e
        LEFT JOIN leave_requests lr ON lr.employee_id = e.id
        LEFT JOIN payroll_settlements ps ON ps.employee_id = e.id
        WHERE e.category IN ('faculty', 'staff')
        GROUP BY e.id
        ORDER BY e.last_name ASC, e.first_name ASC
    ");
    $settlements = $stmt->fetchAll();

    $payrollSheet = [[
        'Employee','Category','Department','Monthly Salary',
        'Leave Pay Rate','Sick Credits Remaining','Potential Convertible',
        'Settlement Status','Settled Amount','Settled At','Remarks',
    ]];

    $totalPotential = 0;
    $totalSettled = 0;
    foreach ($settlements as $s) {
        $name = trim(($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? ''));
        $sickRemaining = max(0, 7.5 - (float) $s['sick_used']);
        $potential = (float) $s['leave_pay'] * $sickRemaining;
        $settled = (float) ($s['settlement_amount'] ?? 0);
        $statusKey = $s['settlement_status'] ?? 'none';
        $statusLabel = ['settled'=>'Paid','outstanding'=>'Unpaid','none'=>'Not Yet Recorded'][$statusKey] ?? 'Not Yet Recorded';

        $totalPotential += $potential;
        $totalSettled += $settled;

        $payrollSheet[] = [
            $name,
            ucfirst($s['category'] ?? ''),
            $s['sub_category'] ?? '',
            ['v' => (float) ($s['basic_salary'] ?? 0), 'style' => 'currency'],
            ['v' => (float) ($s['leave_pay'] ?? 0), 'style' => 'currency'],
            ['v' => $sickRemaining, 'style' => 'number'],
            ['v' => $potential, 'style' => 'currency'],
            $statusLabel,
            ['v' => $settled, 'style' => 'currency'],
            $s['settled_at'] ? date('Y-m-d', strtotime($s['settled_at'])) : '',
            $s['settlement_remarks'] ?? '',
        ];
    }
    $payrollSheet[] = [
        ['v' => 'TOTAL', 'style' => 'total'],
        '', '', '', '', '',
        ['v' => $totalPotential, 'style' => 'total'],
        '',
        ['v' => $totalSettled, 'style' => 'total'],
        '', '',
    ];

    // ---------- SHEET 5: Payroll Summary ----------
    $totalSalaryPool = 0;
    $totalOutstanding = 0;
    $paidCount = 0;
    $unpaidCount = 0;
    foreach ($settlements as $s) {
        $salary = (float) ($s['basic_salary'] ?? 0);
        $leavePayRate = (float) ($s['leave_pay'] ?? 0);
        $sickRemaining = max(0, 7.5 - (float) $s['sick_used']);
        $potential = $leavePayRate * $sickRemaining;
        $settled = (float) ($s['settlement_amount'] ?? 0);
        $statusKey = $s['settlement_status'] ?? 'none';

        $totalSalaryPool += $salary;
        if ($statusKey === 'settled' && $settled < $potential) {
            $totalOutstanding += ($potential - $settled);
        } elseif ($statusKey === 'outstanding') {
            $totalOutstanding += $potential;
        }
        if ($statusKey === 'settled') $paidCount++;
        elseif ($statusKey === 'outstanding') $unpaidCount++;
    }

    $payrollSummary = [
        ['Metric', 'Value'],
        ['Total Employees', ['v' => count($settlements), 'style' => 'number']],
        ['Total Salary Pool', ['v' => $totalSalaryPool, 'style' => 'currency']],
        ['Total Convertible Liability', ['v' => $totalPotential, 'style' => 'currency']],
        ['Total Settled (Paid)', ['v' => $totalSettled, 'style' => 'currency']],
        ['Total Outstanding (Unpaid)', ['v' => $totalOutstanding, 'style' => 'currency']],
        ['Employees Marked Paid', ['v' => $paidCount, 'style' => 'number']],
        ['Employees Marked Unpaid', ['v' => $unpaidCount, 'style' => 'number']],
        ['Report Generated At', date('Y-m-d H:i:s')],
    ];

    // ---------- SHEET 6: Leave Trends (last 12 months) ----------
    $trendSheet = [['Month','Filed','Approved','Rejected','Pending','Days Approved']];
    for ($i = 11; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $label = date('M Y', strtotime($month . '-01'));
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS filed,
                SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) AS approved,
                SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END) AS rejected,
                SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) AS pending,
                COALESCE(SUM(CASE WHEN status='approved' THEN days_count ELSE 0 END), 0) AS days
            FROM leave_requests WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
        ");
        $stmt->execute([$month]);
        $m = $stmt->fetch() ?: [];
        $trendSheet[] = [
            $label,
            ['v' => (int) ($m['filed'] ?? 0), 'style' => 'number'],
            ['v' => (int) ($m['approved'] ?? 0), 'style' => 'number'],
            ['v' => (int) ($m['rejected'] ?? 0), 'style' => 'number'],
            ['v' => (int) ($m['pending'] ?? 0), 'style' => 'number'],
            ['v' => (float) ($m['days'] ?? 0), 'style' => 'number'],
        ];
    }

    // ---------- Build & download ----------
    $x = new SimpleXlsx();
    $x->sheet('Directory', $directory, [6,14,14,14,26,28,10,20,14,24,14,16,14,18]);
    $x->sheet('Leave Requests', $leavesSheet, [10,24,10,18,14,12,12,8,10,14,40,40,14,14,14]);
    $x->sheet('Leave Summary', $summarySheet, [26,10,18,12,12,14,10,12,10,12,12]);
    $x->sheet('Payroll Settlements', $payrollSheet, [26,10,18,16,16,14,16,14,16,14,24]);
    $x->sheet('Payroll Summary', $payrollSummary, [30,24]);
    $x->sheet('Leave Trends', $trendSheet, [12,10,10,10,10,18]);
    $x->download('DAMMC_HR_Report_' . date('Y-m-d_His') . '.xlsx');
    exit;
}

// ============================================
// NORMAL PAGE DATA
// ============================================
$userId = (int) $_SESSION['user_id'];

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

$typeCounts = ['vacation' => 0, 'sick' => 0, 'other' => 0];
foreach ($byType as $row) {
    if (isset($typeCounts[$row['leave_type']])) {
        $typeCounts[$row['leave_type']] = (int) $row['count'];
    }
}

// Calendar data
$calendarStart = date('Y-m-01', strtotime('-1 month'));
$calendarEnd   = date('Y-m-t', strtotime('+1 month'));

$stmt = $pdo->prepare("
    SELECT lr.id, lr.leave_type, lr.start_date, lr.end_date, lr.days_count,
        e.first_name, e.last_name, e.sub_category, e.position, e.leave_credits
    FROM leave_requests lr
    INNER JOIN employees e ON lr.employee_id = e.id
    WHERE lr.status = 'approved'
      AND NOT (lr.end_date < ? OR lr.start_date > ?)
    ORDER BY lr.start_date ASC
");
$stmt->execute([$calendarStart, $calendarEnd]);
$calendarLeaves = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT lr.id, lr.leave_type, lr.start_date, lr.end_date, lr.days_count,
        e.first_name, e.last_name, e.sub_category, e.position
    FROM leave_requests lr
    INNER JOIN employees e ON lr.employee_id = e.id
    WHERE lr.status = 'pending'
      AND NOT (lr.end_date < ? OR lr.start_date > ?)
    ORDER BY lr.start_date ASC
");
$stmt->execute([$calendarStart, $calendarEnd]);
$calendarPending = $stmt->fetchAll();

// Leaves happening this month
$monthStart = date('Y-m-01');
$monthEnd   = date('Y-m-t');
$stmt = $pdo->prepare("
    SELECT lr.id, lr.leave_type, lr.start_date, lr.end_date, lr.days_count,
        e.first_name, e.last_name, e.sub_category, e.position
    FROM leave_requests lr
    INNER JOIN employees e ON lr.employee_id = e.id
    WHERE lr.status = 'approved'
      AND NOT (lr.end_date < ? OR lr.start_date > ?)
    ORDER BY lr.start_date ASC
");
$stmt->execute([$monthStart, $monthEnd]);
$upcomingLeaves = $stmt->fetchAll();

// Pending requests (top 5, FIFO)
$stmt = $pdo->query("
    SELECT lr.*, e.first_name, e.last_name, e.sub_category
    FROM leave_requests lr
    LEFT JOIN employees e ON lr.employee_id = e.id
    WHERE lr.status = 'pending'
    ORDER BY lr.created_at ASC
    LIMIT 5
");
$pendingRecent = $stmt->fetchAll();

// Recent decisions
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

// Monthly trend
$trendLabels = [];
$trendValues = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $trendLabels[] = date('M', strtotime($month . '-01'));
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM leave_requests
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
    <style>
        .cal-day {
            position: relative;
            aspect-ratio: 1 / 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            padding-top: 6px;
            font-size: 11px;
            color: #2C3E50;
            border-radius: 6px;
            transition: background-color 0.15s ease;
            cursor: default;
        }
        .cal-day:hover { background-color: rgba(15, 94, 61, 0.04); }
        .cal-day.is-today { background-color: #F1FDF6; color: #0F5E3D; font-weight: 600; }
        .cal-day.is-outside { color: #CBD5E1; cursor: not-allowed; }
        .cal-day.is-outside:hover { background-color: transparent; }
        .cal-day .cal-dots { display: flex; gap: 2px; margin-top: 4px; flex-wrap: wrap; justify-content: center; max-width: 90%; }
        .cal-day .cal-dot { width: 4px; height: 4px; border-radius: 50%; flex-shrink: 0; }
        .cal-day .cal-count {
            position: absolute; bottom: 2px; right: 2px;
            font-size: 8px; font-weight: 600;
            background: #0F5E3D; color: #fff;
            padding: 0 3px; border-radius: 4px; line-height: 1.2;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-[#F1FDF6] via-[#eafaf1] to-[#dcf3e5] min-h-screen">

    <div class="flex min-h-screen">
        <div id="sidebarOverlay" onclick="closeSidebar()"
            class="fixed inset-0 bg-black/40 z-40 hidden md:hidden backdrop-blur-sm"></div>

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
                    <div class="w-full sm:w-auto flex flex-col sm:flex-row gap-2">
                        <a href="/hr/dashboard?export=xlsx"
                           class="flex items-center justify-center gap-2 border border-[#0F5E3D]/30 text-[#0F5E3D] hover:bg-[#F1FDF6] active:scale-[0.99] font-medium py-2.5 px-4 rounded-lg transition text-sm shadow-sm">
                            <i data-lucide="download" class="w-4 h-4"></i>
                            <span>Export to Excel</span>
                        </a>
                        <a href="/hr/leaves"
                           class="flex items-center justify-center gap-2 bg-[#0F5E3D] hover:bg-[#0a4a2f] active:scale-[0.99] text-white font-medium py-2.5 px-4 rounded-lg transition text-sm shadow-sm hover:shadow-md">
                            <i data-lucide="calendar-check" class="w-4 h-4"></i>
                            <span>Review Requests</span>
                        </a>
                    </div>
                </div>

                <!-- CALENDAR ROW -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 sm:gap-4">
                    <div class="lg:col-span-2 animate-fade-in-up bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-5 sm:p-6 shadow-sm">
                        <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                            <div>
                                <h2 class="text-base font-bold text-[#2C3E50] flex items-center gap-2">
                                    <i data-lucide="calendar" class="w-4 h-4 text-[#0F5E3D]"></i>
                                    Leave Calendar
                                </h2>
                                <p class="text-xs text-[#2C3E50]/50 mt-0.5">Approved leaves this month</p>
                            </div>
                            <div class="flex items-center gap-1">
                                <button type="button" onclick="calendarPrevMonth()"
                                    class="w-8 h-8 rounded-lg flex items-center justify-center text-[#2C3E50]/60 hover:bg-[#F1FDF6] hover:text-[#0F5E3D] transition"
                                    title="Previous month">
                                    <i data-lucide="chevron-left" class="w-4 h-4"></i>
                                </button>
                                <span id="calMonthLabel" class="text-sm font-semibold text-[#2C3E50] px-3 min-w-[120px] text-center"></span>
                                <button type="button" onclick="calendarNextMonth()"
                                    class="w-8 h-8 rounded-lg flex items-center justify-center text-[#2C3E50]/60 hover:bg-[#F1FDF6] hover:text-[#0F5E3D] transition"
                                    title="Next month">
                                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </div>

                        <div class="grid grid-cols-7 gap-1 mb-1">
                            <div class="text-center text-[10px] font-semibold uppercase tracking-wider text-[#2C3E50]/40 py-1">Sun</div>
                            <div class="text-center text-[10px] font-semibold uppercase tracking-wider text-[#2C3E50]/40 py-1">Mon</div>
                            <div class="text-center text-[10px] font-semibold uppercase tracking-wider text-[#2C3E50]/40 py-1">Tue</div>
                            <div class="text-center text-[10px] font-semibold uppercase tracking-wider text-[#2C3E50]/40 py-1">Wed</div>
                            <div class="text-center text-[10px] font-semibold uppercase tracking-wider text-[#2C3E50]/40 py-1">Thu</div>
                            <div class="text-center text-[10px] font-semibold uppercase tracking-wider text-[#2C3E50]/40 py-1">Fri</div>
                            <div class="text-center text-[10px] font-semibold uppercase tracking-wider text-[#2C3E50]/40 py-1">Sat</div>
                        </div>

                        <div id="calendarGrid" class="grid grid-cols-7 gap-1"></div>

                        <div class="flex flex-wrap items-center gap-3 mt-4 pt-3 border-t border-[#E0E0E0]">
                            <div class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                                <span class="text-[10px] text-[#2C3E50]/60">Vacation</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                                <span class="text-[10px] text-[#2C3E50]/60">Sick</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-slate-500"></span>
                                <span class="text-[10px] text-[#2C3E50]/60">Other</span>
                            </div>
                            <div class="flex items-center gap-1.5 ml-auto">
                                <span class="w-3 h-3 rounded bg-[#F1FDF6] border border-[#0F5E3D]/20"></span>
                                <span class="text-[10px] text-[#2C3E50]/60">Today</span>
                            </div>
                        </div>
                    </div>

                    <div class="animate-fade-in-up-delay-1 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-5 shadow-sm flex flex-col">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <h2 class="text-sm font-bold text-[#2C3E50] flex items-center gap-1.5">
                                    <i data-lucide="users" class="w-3.5 h-3.5 text-[#0F5E3D]"></i>
                                    On Leave This Month
                                </h2>
                                <p class="text-[10px] text-[#2C3E50]/50 mt-0.5"><?= count($upcomingLeaves) ?> employee<?= count($upcomingLeaves) === 1 ? '' : 's' ?></p>
                            </div>
                        </div>

                        <?php if (empty($upcomingLeaves)): ?>
                            <div class="flex-1 flex flex-col items-center justify-center py-6 text-center">
                                <div class="w-10 h-10 rounded-full bg-[#F1FDF6] flex items-center justify-center text-[#0F5E3D] mb-2">
                                    <i data-lucide="calendar-x" class="w-4 h-4"></i>
                                </div>
                                <p class="text-xs font-medium text-[#2C3E50]">No leaves this month</p>
                                <p class="text-[10px] text-[#2C3E50]/50 mt-0.5">Everyone's in the office.</p>
                            </div>
                        <?php else: ?>
                            <div class="space-y-2 overflow-y-auto pr-1" style="max-height: 260px;">
                                <?php foreach ($upcomingLeaves as $l):
                                    $name = trim(($l['first_name'] ?? '') . ' ' . ($l['last_name'] ?? '')) ?: 'Unknown';
                                    $typeLabel = $leaveTypes[$l['leave_type']] ?? ucfirst($l['leave_type']);
                                    $typeColor = $typeColors[$l['leave_type']] ?? '#64748b';
                                    $startLbl = date('M j', strtotime($l['start_date']));
                                    $endLbl   = date('M j', strtotime($l['end_date']));
                                    $range = ($l['start_date'] === $l['end_date']) ? $startLbl : "{$startLbl}–{$endLbl}";
                                ?>
                                    <div class="flex items-start gap-2 p-2 rounded-lg hover:bg-[#F1FDF6]/60 transition">
                                        <span class="w-2 h-2 rounded-full mt-1.5 shrink-0" style="background: <?= $typeColor ?>"></span>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-xs font-medium text-[#2C3E50] truncate"><?= htmlspecialchars($name) ?></p>
                                            <p class="text-[10px] text-[#2C3E50]/50 truncate">
                                                <?= htmlspecialchars($typeLabel) ?> · <?= $range ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ROW 1: Hero cards -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 sm:gap-4">
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

                <!-- ROW 2.5: Leave Types -->
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
                        <div class="lg:col-span-2 relative h-56">
                            <canvas id="leaveTypesChart"></canvas>
                        </div>
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

                <!-- ROW 3: Quick stats -->
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

    <!-- Calendar Day Details Modal -->
    <div id="leaveCalendarModal"
        class="fixed inset-0 z-[60] hidden items-center justify-center bg-black/40 backdrop-blur-sm p-4">
        <div id="leaveCalendarCard"
            class="bg-white rounded-2xl shadow-2xl w-full max-w-lg transform transition-all duration-200 scale-95 overflow-hidden max-h-[85vh] flex flex-col">
            <div class="flex items-start justify-between px-6 py-4 border-b border-[#E0E0E0] bg-[#F1FDF6]/40 shrink-0">
                <div class="min-w-0">
                    <div class="flex items-center gap-2 mb-0.5">
                        <i data-lucide="calendar-days" class="w-4 h-4 text-[#0F5E3D]"></i>
                        <h3 class="text-base font-bold text-[#2C3E50]">Leaves on This Day</h3>
                    </div>
                    <p class="text-xs text-[#2C3E50]/50" id="calModalDate">—</p>
                </div>
                <button onclick="closeDayDetails()"
                    class="text-[#2C3E50]/40 hover:text-[#2C3E50] transition shrink-0 ml-3">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <div class="p-5 overflow-y-auto flex-1" id="calModalBody"></div>
            <div class="px-6 py-3 border-t border-[#E0E0E0] bg-white shrink-0">
                <button onclick="closeDayDetails()"
                    class="w-full border border-[#E0E0E0] text-[#2C3E50] hover:bg-gray-50 font-medium py-2.5 rounded-lg transition text-sm">
                    Close
                </button>
            </div>
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

    window.LEAVE_CALENDAR_DATA = {
        approved: <?= json_encode(array_map(function ($l) {
            return [
                'id'         => (int) $l['id'],
                'type'       => $l['leave_type'],
                'start'      => $l['start_date'],
                'end'        => $l['end_date'],
                'days'       => (float) $l['days_count'],
                'name'       => trim(($l['first_name'] ?? '') . ' ' . ($l['last_name'] ?? '')),
                'department' => $l['sub_category'] ?? '',
                'position'   => $l['position'] ?? '',
                'status'     => 'approved'
            ];
        }, $calendarLeaves)) ?>,
        pending: <?= json_encode(array_map(function ($l) {
            return [
                'id'         => (int) $l['id'],
                'type'       => $l['leave_type'],
                'start'      => $l['start_date'],
                'end'        => $l['end_date'],
                'days'       => (float) $l['days_count'],
                'name'       => trim(($l['first_name'] ?? '') . ' ' . ($l['last_name'] ?? '')),
                'department' => $l['sub_category'] ?? '',
                'position'   => $l['position'] ?? '',
                'status'     => 'pending'
            ];
        }, $calendarPending)) ?>
    };

    window.LEAVE_TYPE_COLORS = <?= json_encode($typeColors) ?>;
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="/assets/js/app.js"></script>
    <script src="/assets/js/notifications.js"></script>
    <script src="/assets/js/profile.js"></script>
    <script src="/assets/js/hr_dashboard.js"></script>
    <script src="/assets/js/hr_dashboard_calendar.js"></script>
</body>
</html>