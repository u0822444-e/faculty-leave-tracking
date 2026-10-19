<?php
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

// Only faculty leadership roles can view this page
$leadershipPositions = [
    'College Dean',
    'Academic Dean',
    'Elementary Principal',
    'High School Principal',
];
$myPosition  = $_SESSION['position']     ?? '';
$mySubCat    = $_SESSION['sub_category'] ?? '';
$myYearLevel = $_SESSION['year_level']   ?? '';

if (!in_array($myPosition, $leadershipPositions, true)) {
    header('Location: /faculty/dashboard');
    exit;
}

require_once __DIR__ . '/../../config/database.php';

$userId = (int) $_SESSION['user_id'];

// Fetch own employee record for the header
$stmt = $pdo->prepare("
    SELECT e.id AS employee_id, e.first_name, e.last_name,
           e.category, e.sub_category, e.year_level, e.position
    FROM users u
    LEFT JOIN employees e ON u.employee_id = e.id
    WHERE u.id = ?
    LIMIT 1
");
$stmt->execute([$userId]);
$me = $stmt->fetch();
$myEmployeeId = (int) ($me['employee_id'] ?? 0);

// ============================================
// Build the scope filter based on the position
// ============================================
$where  = ["lr.status IN ('pending','approved')"];
$params = [];

// Exclude the dean/principal themselves
$where[] = "lr.employee_id <> ?";
$params[] = $myEmployeeId;

// Faculty only — no staff, no admins
$where[] = "emp.category = 'faculty'";

if ($myPosition === 'College Dean') {
    if ($myYearLevel !== '') {
        $where[]  = "emp.year_level = ?";
        $params[] = $myYearLevel;
    }
} elseif ($myPosition === 'Elementary Principal') {
    $where[]  = "emp.sub_category = 'Elementary'";
} elseif ($myPosition === 'High School Principal') {
    $where[]  = "emp.sub_category = 'High School'";
}
// Academic Dean sees ALL faculty — no extra filter

$whereSql = implode(' AND ', $where);

$sql = "
    SELECT
        lr.*,
        emp.id AS employee_id,
        emp.first_name, emp.middle_name, emp.last_name, emp.email,
        emp.category, emp.sub_category, emp.year_level, emp.position,
        emp.employment_type,
        approver.username AS approver_username
    FROM leave_requests lr
    INNER JOIN employees emp ON lr.employee_id = emp.id
    LEFT JOIN users approver ON lr.approved_by = approver.id
    WHERE {$whereSql}
    ORDER BY lr.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$leaves = $stmt->fetchAll();

// ============================================
// Precompute credits per employee for the view modal
// ============================================
$employeeCredits = [];
$stmt = $pdo->query("
    SELECT 
        e.id,
        e.leave_credits,
        COALESCE(SUM(CASE WHEN lr.status = 'approved' THEN lr.days_count ELSE 0 END), 0) AS used_days
    FROM employees e
    LEFT JOIN leave_requests lr ON lr.employee_id = e.id
    GROUP BY e.id
");
foreach ($stmt->fetchAll() as $row) {
    $cap = (float) $row['leave_credits'];
    $used = (float) $row['used_days'];
    $employeeCredits[(int) $row['id']] = [
        'total' => $cap,
        'used' => $used,
        'remaining' => max(0, $cap - $used),
    ];
}

// ============================================
// Summary numbers
// ============================================
$summary = [
    'total'    => count($leaves),
    'pending'  => 0,
    'approved' => 0,
];
foreach ($leaves as $l) {
    if (isset($summary[$l['status']])) $summary[$l['status']]++;
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

// Friendly scope label
switch ($myPosition) {
    case 'College Dean':
        $scopeLabel = $myYearLevel ?: 'Your College';
        break;
    case 'Elementary Principal':
        $scopeLabel = 'Elementary';
        break;
    case 'High School Principal':
        $scopeLabel = 'High School';
        break;
    case 'Academic Dean':
        $scopeLabel = 'All Faculty';
        break;
    default:
        $scopeLabel = 'Faculty';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Leave Oversight | DAMMC</title>
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
        $activePage = 'leave_monitoring';
        require __DIR__ . '/../components/sidebar.php';
        ?>

        <div class="flex-1 md:ml-64 w-full min-w-0">
            <?php
            $pageTitle    = 'Faculty Leave Oversight';
            $pageSubtitle = 'Viewing ' . $scopeLabel . ' · read-only';
            $pageIcon     = 'clipboard-list';
            require __DIR__ . '/../components/topbar.php';
            ?>

            <main class="p-4 sm:p-6 lg:p-8 space-y-4 sm:space-y-6">

                <!-- Info banner -->
                <div class="bg-[#F1FDF6] border border-[#0F5E3D]/15 rounded-xl p-4 flex items-start gap-3">
                    <div class="w-8 h-8 rounded-lg bg-white flex items-center justify-center text-[#0F5E3D] shrink-0">
                        <i data-lucide="info" class="w-4 h-4"></i>
                    </div>
                    <div class="text-xs">
                        <p class="font-medium text-[#2C3E50]">
                            You are viewing as <span class="text-[#0F5E3D]"><?= htmlspecialchars($myPosition) ?></span>
                        </p>
                        <p class="text-[#2C3E50]/60 mt-0.5">
                            Scope: <strong><?= htmlspecialchars($scopeLabel) ?></strong>
                            · HR and Admin are the only ones who can approve or reject requests.
                        </p>
                    </div>
                </div>

                <!-- Summary strip -->
                <div class="grid grid-cols-3 gap-3">
                    <div class="bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-4 shadow-sm">
                        <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-1">Total</p>
                        <p class="text-xl font-bold text-[#2C3E50]"><?= $summary['total'] ?></p>
                    </div>
                    <div class="bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-4 shadow-sm">
                        <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-1">Approved</p>
                        <p class="text-xl font-bold text-[#0F5E3D]"><?= $summary['approved'] ?></p>
                    </div>
                    <div class="bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-4 shadow-sm">
                        <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-1">Pending</p>
                        <p class="text-xl font-bold text-amber-600"><?= $summary['pending'] ?></p>
                    </div>
                </div>

                <!-- Toolbar -->
                <div class="bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-3 sm:p-4 shadow-sm flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between">
                    <div class="relative flex-1 sm:max-w-sm">
                        <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[#2C3E50]/40 pointer-events-none"></i>
                        <input type="text" id="flSearch" oninput="filterFacultyLeaves()" placeholder="Search name or type"
                            class="w-full pl-10 pr-4 py-2 bg-[#F1FDF6]/60 border border-transparent rounded-lg text-sm text-[#2C3E50] placeholder:text-[#2C3E50]/40 focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:bg-white focus:border-transparent transition">
                    </div>
                    <select id="flStatus" onchange="filterFacultyLeaves()"
                        class="px-3 py-2 rounded-lg text-xs font-medium text-[#2C3E50]/80 bg-[#F1FDF6] border border-[#E0E0E0] focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition cursor-pointer">
                        <option value="">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                    </select>
                </div>

                <!-- Leaves table -->
                <div class="bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl shadow-sm overflow-hidden">

                    <!-- Desktop -->
                    <div class="hidden md:block overflow-x-auto">
                        <table class="w-full">
                            <thead class="border-b border-[#E0E0E0]">
                                <tr class="h-11">
                                    <th class="text-left text-xs font-medium text-[#2C3E50]/50 px-4">Employee</th>
                                    <th class="text-left text-xs font-medium text-[#2C3E50]/50 px-4">Type</th>
                                    <th class="text-left text-xs font-medium text-[#2C3E50]/50 px-4">Start</th>
                                    <th class="text-left text-xs font-medium text-[#2C3E50]/50 px-4">End</th>
                                    <th class="text-left text-xs font-medium text-[#2C3E50]/50 px-4">Days</th>
                                    <th class="text-left text-xs font-medium text-[#2C3E50]/50 px-4">Status</th>
                                    <th class="w-20 px-4 text-right text-xs font-medium text-[#2C3E50]/50">Details</th>
                                </tr>
                            </thead>
                            <tbody id="flTableBody" class="divide-y divide-[#E0E0E0]">
                                <?php if (empty($leaves)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-sm text-[#2C3E50]/50 py-12">
                                            <i data-lucide="calendar-x" class="w-6 h-6 mx-auto mb-2 text-[#2C3E50]/30"></i>
                                            No faculty leave requests to show.
                                        </td>
                                    </tr>
                                <?php else: foreach ($leaves as $l):
                                    $empName = trim(($l['first_name'] ?? '') . ' ' . ($l['last_name'] ?? ''));
                                    $sClass  = $statusStyles[$l['status']] ?? 'text-slate-600 bg-slate-100';
                                    $sLabel  = $statusLabels[$l['status']] ?? ucfirst($l['status']);
                                    $tLabel  = $leaveTypes[$l['leave_type']] ?? ucfirst($l['leave_type']);
                                    $credit  = $employeeCredits[(int) $l['employee_id']] ?? ['total' => 0, 'used' => 0, 'remaining' => 0];
                                    ?>
                                    <tr class="hover:bg-[#F1FDF6]/40 transition h-12 faculty-leave-row"
                                        data-search="<?= strtolower($empName . ' ' . $tLabel . ' ' . $sLabel) ?>"
                                        data-status="<?= htmlspecialchars($l['status']) ?>">
                                        <td class="px-4">
                                            <div class="flex flex-col">
                                                <span class="text-sm font-medium text-[#2C3E50]"><?= htmlspecialchars($empName) ?></span>
                                                <span class="text-[10px] text-[#2C3E50]/50">
                                                    <?= htmlspecialchars($l['year_level'] ?: $l['sub_category'] ?: '—') ?>
                                                    <?= $l['position'] ? '· ' . htmlspecialchars($l['position']) : '' ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-4 text-sm text-[#2C3E50]"><?= htmlspecialchars($tLabel) ?></td>
                                        <td class="px-4 text-sm text-[#2C3E50]/70"><?= date('M j, Y', strtotime($l['start_date'])) ?></td>
                                        <td class="px-4 text-sm text-[#2C3E50]/70"><?= date('M j, Y', strtotime($l['end_date'])) ?></td>
                                        <td class="px-4 text-sm text-[#2C3E50]/70">
                                            <?= rtrim(rtrim(number_format((float) $l['days_count'], 2, '.', ''), '0'), '.') ?>
                                        </td>
                                        <td class="px-4">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium <?= $sClass ?>">
                                                <?= $sLabel ?>
                                            </span>
                                        </td>
                                        <td class="px-4">
                                            <div class="flex items-center justify-end">
                                                <button onclick='openViewFacultyLeave(<?= json_encode([
                                                    "id"         => (int) $l["id"],
                                                    "name"       => $empName,
                                                    "email"      => $l["email"] ?? "—",
                                                    "category"   => ucfirst($l["category"] ?? "—"),
                                                    "subcat"     => $l["sub_category"] ?? "—",
                                                    "year_level" => $l["year_level"] ?? "—",
                                                    "position"   => $l["position"] ?? "—",
                                                    "employment" => ucfirst($l["employment_type"] ?? "—"),
                                                    "type"       => $tLabel,
                                                    "start"      => $l["start_date"],
                                                    "end"        => $l["end_date"],
                                                    "days"       => $l["days_count"],
                                                    "reason"     => $l["reason"] ?? "",
                                                    "status"     => $l["status"],
                                                    "remarks"    => $l["remarks"] ?? "",
                                                    "created"    => $l["created_at"],
                                                    "approver"   => $l["approver_username"] ?? "",
                                                    "approved"   => $l["approved_at"] ?? "",
                                                    "credits"    => $credit,
                                                ]) ?>)'
                                                    class="w-7 h-7 rounded-lg flex items-center justify-center text-[#2C3E50]/60 hover:bg-[#F1FDF6] hover:text-[#0F5E3D] transition"
                                                    title="View details">
                                                    <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile cards -->
                    <div id="flMobileCards" class="md:hidden divide-y divide-[#E0E0E0]">
                        <?php if (empty($leaves)): ?>
                            <div class="p-10 text-center text-sm text-[#2C3E50]/50">No faculty leave requests.</div>
                        <?php else: foreach ($leaves as $l):
                            $empName = trim(($l['first_name'] ?? '') . ' ' . ($l['last_name'] ?? ''));
                            $sClass  = $statusStyles[$l['status']] ?? 'text-slate-600 bg-slate-100';
                            $sLabel  = $statusLabels[$l['status']] ?? ucfirst($l['status']);
                            $tLabel  = $leaveTypes[$l['leave_type']] ?? ucfirst($l['leave_type']);
                            $credit  = $employeeCredits[(int) $l['employee_id']] ?? ['total' => 0, 'used' => 0, 'remaining' => 0];
                            ?>
                            <div class="p-4 faculty-leave-row"
                                data-search="<?= strtolower($empName . ' ' . $tLabel . ' ' . $sLabel) ?>"
                                data-status="<?= htmlspecialchars($l['status']) ?>">
                                <div class="flex items-start justify-between gap-2 mb-1">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-[#2C3E50] truncate"><?= htmlspecialchars($empName) ?></p>
                                        <p class="text-[10px] text-[#2C3E50]/50 truncate">
                                            <?= htmlspecialchars($l['year_level'] ?: $l['sub_category'] ?: '—') ?>
                                        </p>
                                    </div>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium <?= $sClass ?> shrink-0">
                                        <?= $sLabel ?>
                                    </span>
                                </div>
                                <p class="text-xs text-[#2C3E50]/60">
                                    <?= htmlspecialchars($tLabel) ?> ·
                                    <?= date('M j', strtotime($l['start_date'])) ?>
                                    <?php if ($l['start_date'] !== $l['end_date']): ?>
                                        → <?= date('M j, Y', strtotime($l['end_date'])) ?>
                                    <?php endif; ?>
                                </p>
                                <button onclick='openViewFacultyLeave(<?= json_encode([
                                    "id"         => (int) $l["id"],
                                    "name"       => $empName,
                                    "email"      => $l["email"] ?? "—",
                                    "category"   => ucfirst($l["category"] ?? "—"),
                                    "subcat"     => $l["sub_category"] ?? "—",
                                    "year_level" => $l["year_level"] ?? "—",
                                    "position"   => $l["position"] ?? "—",
                                    "employment" => ucfirst($l["employment_type"] ?? "—"),
                                    "type"       => $tLabel,
                                    "start"      => $l["start_date"],
                                    "end"        => $l["end_date"],
                                    "days"       => $l["days_count"],
                                    "reason"     => $l["reason"] ?? "",
                                    "status"     => $l["status"],
                                    "remarks"    => $l["remarks"] ?? "",
                                    "created"    => $l["created_at"],
                                    "approver"   => $l["approver_username"] ?? "",
                                    "approved"   => $l["approved_at"] ?? "",
                                    "credits"    => $credit,
                                ]) ?>)'
                                    class="mt-3 w-full text-xs py-2 rounded-lg border border-[#E0E0E0] text-[#2C3E50] hover:bg-gray-50 transition">
                                    View Details
                                </button>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>

                    <div id="flEmpty" class="hidden p-12 text-center">
                        <div class="w-12 h-12 rounded-full bg-[#F1FDF6] flex items-center justify-center mx-auto mb-3 text-[#0F5E3D]">
                            <i data-lucide="search-x" class="w-5 h-5"></i>
                        </div>
                        <p class="text-sm font-medium text-[#2C3E50]">No matching leaves</p>
                        <p class="text-xs text-[#2C3E50]/50 mt-1">Try a different search or status.</p>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- View leave modal — split-pane (view-only)       -->
    <!-- ============================================ -->
    <div id="flViewModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div id="flViewCard"
            class="bg-white rounded-xl shadow-2xl w-full max-w-4xl h-[80vh] transform transition-all duration-200 scale-95 overflow-hidden flex flex-col">

            <!-- Top bar -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-[#E0E0E0] shrink-0 bg-white">
                <div>
                    <h3 class="text-base font-semibold text-[#2C3E50]">Leave Request Details</h3>
                    <p class="text-sm text-[#2C3E50]/50" id="flvRequestId">—</p>
                </div>
                <button onclick="closeViewFacultyLeave()" class="w-9 h-9 rounded-lg flex items-center justify-center text-[#2C3E50]/50 hover:bg-[#F1FDF6] hover:text-[#2C3E50] transition">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <!-- Content -->
            <div class="flex-1 flex min-h-0">

                <!-- SIDEBAR -->
                <aside class="w-72 border-r border-[#E0E0E0] bg-[#F9FAFB] flex flex-col shrink-0 overflow-y-auto">

                    <div class="p-6">
                        <div id="flvInitials" class="w-16 h-16 rounded-full bg-[#0F5E3D] text-white flex items-center justify-center font-semibold text-xl mb-4">—</div>
                        <p id="flvName" class="text-base font-semibold text-[#2C3E50] leading-tight">—</p>
                        <p id="flvPosition" class="text-sm text-[#2C3E50]/60 mt-1 leading-snug">—</p>
                        <p id="flvDepartment" class="text-sm text-[#2C3E50]/60 leading-snug">—</p>
                        <p id="flvEmail" class="text-xs text-[#2C3E50]/40 mt-2 truncate">—</p>
                    </div>

                    <div class="border-t border-[#E0E0E0]"></div>

                    <div class="p-6">
                        <p class="text-xs uppercase tracking-wider text-[#2C3E50]/50 font-semibold mb-1">Credits</p>
                        <p class="text-base font-semibold text-[#2C3E50] mb-4">
                            <span id="flvCreditTotal">—</span>
                        </p>

                        <div class="space-y-3">
                            <div>
                                <div class="flex items-baseline justify-between gap-2">
                                    <span class="text-sm text-[#2C3E50]">Used</span>
                                    <span class="text-sm text-[#2C3E50]" id="flvCreditUsed">—</span>
                                </div>
                            </div>
                            <div>
                                <div class="flex items-baseline justify-between gap-2">
                                    <span class="text-sm text-[#2C3E50]">Remaining</span>
                                    <span class="text-sm font-semibold text-[#0F5E3D]" id="flvCreditRemaining">—</span>
                                </div>
                                <div class="mt-1.5 h-1 bg-[#E0E0E0] rounded-full overflow-hidden">
                                    <div id="flvCreditBar" class="bg-[#0F5E3D] h-full" style="width: 0%"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                </aside>

                <!-- MAIN -->
                <section class="flex-1 flex flex-col min-h-0 bg-white">

                    <!-- Scrollable body -->
                    <div class="flex-1 overflow-y-auto px-8 py-6">

                        <!-- Header: type + status + dates -->
                        <div class="pb-5 border-b border-[#F0F0F0]">
                            <div class="flex items-center gap-2 flex-wrap mb-3">
                                <span id="flvType" class="inline-flex items-center px-2.5 py-1 rounded-md text-sm font-medium text-slate-700 bg-slate-100">—</span>
                                <span id="flvStatusBadge" class="inline-flex items-center px-2.5 py-1 rounded-md text-sm font-medium text-slate-700 bg-slate-100">—</span>
                            </div>
                            <p class="text-base text-[#2C3E50]">
                                <span id="flvDates" class="font-medium">—</span>
                                <span class="text-[#2C3E50]/30 mx-2">·</span>
                                <span id="flvDays" class="text-[#2C3E50]/70">—</span>
                                <span id="flvHalfDayTag" class="hidden ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-semibold text-amber-700 bg-amber-50">Half Day</span>
                            </p>
                            <p class="text-sm text-[#2C3E50]/50 mt-1">
                                Filed <span id="flvCreated">—</span>
                            </p>
                        </div>

                        <!-- Reason -->
                        <div class="py-5 border-b border-[#F0F0F0]">
                            <p class="text-xs uppercase tracking-wider text-[#2C3E50]/50 font-semibold mb-2">Reason</p>
                            <p id="flvReason" class="text-sm text-[#2C3E50] leading-relaxed whitespace-pre-wrap">—</p>
                        </div>

                        <!-- Decision -->
                        <div id="flvDecisionWrap" class="hidden py-5">
                            <p class="text-xs uppercase tracking-wider text-[#2C3E50]/50 font-semibold mb-3">Decision</p>
                            <p class="text-sm text-[#2C3E50]">
                                <span id="flvDecisionSummary" class="font-medium">—</span>
                            </p>
                            <div id="flvRemarksBlock" class="hidden mt-3 pl-4 border-l-2 border-[#E0E0E0]">
                                <p id="flvRemarks" class="text-sm text-[#2C3E50]/70 italic leading-relaxed">—</p>
                            </div>
                        </div>

                    </div>

                    <!-- Footer -->
                    <div class="border-t border-[#E0E0E0] bg-white px-8 py-3 shrink-0">
                        <button onclick="closeViewFacultyLeave()"
                            class="w-full border border-[#E0E0E0] text-[#2C3E50] hover:bg-gray-50 font-medium py-2.5 rounded-md transition text-sm">
                            Close
                        </button>
                    </div>

                </section>

            </div>
        </div>
    </div>

    <?php require_once __DIR__ . '/../components/logout_modal.php'; ?>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="/assets/js/app.js"></script>
    <script src="/assets/js/notifications.js"></script>
    <script src="/assets/js/profile.js"></script>
    <script src="/assets/js/leave_monitoring.js"></script>
</body>

</html>