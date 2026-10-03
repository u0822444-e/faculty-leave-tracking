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
$myPosition   = $_SESSION['position']     ?? '';
$mySubCat     = $_SESSION['sub_category'] ?? '';
$myYearLevel  = $_SESSION['year_level']   ?? '';

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
$where  = ["lr.status IN ('pending','approved','rejected','cancelled')"];
$params = [];

// Exclude the dean/principal themselves
$where[] = "lr.employee_id <> ?";
$params[] = $myEmployeeId;

// Faculty only — no staff, no admins
$where[] = "emp.category = 'faculty'";

if ($myPosition === 'College Dean') {
    // Only faculty in the same college (year_level)
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
        emp.first_name, emp.middle_name, emp.last_name,
        emp.sub_category, emp.year_level, emp.position,
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
// Small summary numbers
// ============================================
$summary = [
    'total'    => count($leaves),
    'pending'  => 0,
    'approved' => 0,
    'rejected' => 0,
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

// Friendly scope label for the page subtitle
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
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
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
                    <div class="bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-4 shadow-sm">
                        <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-1">Rejected</p>
                        <p class="text-xl font-bold text-red-600"><?= $summary['rejected'] ?></p>
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
                        <option value="rejected">Rejected</option>
                        <option value="cancelled">Cancelled</option>
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
                                            <?= rtrim(rtrim(number_format((float)$l['days_count'], 2, '.', ''), '0'), '.') ?>
                                        </td>
                                        <td class="px-4">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium <?= $sClass ?>">
                                                <?= $sLabel ?>
                                            </span>
                                        </td>
                                        <td class="px-4">
                                            <div class="flex items-center justify-end">
                                                <button onclick='openViewFacultyLeave(<?= json_encode([
                                                    "employee" => $empName,
                                                    "type"     => $tLabel,
                                                    "start"    => $l["start_date"],
                                                    "end"      => $l["end_date"],
                                                    "days"     => $l["days_count"],
                                                    "reason"   => $l["reason"] ?? "",
                                                    "status"   => $sLabel,
                                                    "remarks"  => $l["remarks"] ?? "",
                                                    "created"  => $l["created_at"],
                                                    "approver" => $l["approver_username"] ?? "",
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

    <!-- View leave modal (read-only) -->
    <div id="flViewModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm p-4">
        <div id="flViewCard" class="bg-white rounded-xl shadow-xl w-full max-w-md transform transition-all duration-200 scale-95 overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-[#E0E0E0]">
                <div>
                    <h3 class="text-base font-bold text-[#2C3E50]">Leave Details</h3>
                    <p class="text-xs text-[#2C3E50]/50" id="flvEmployee">—</p>
                </div>
                <button onclick="closeViewFacultyLeave()" class="text-[#2C3E50]/40 hover:text-[#2C3E50] transition">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-0.5">Type</p>
                        <p id="flvType" class="text-sm font-medium text-[#2C3E50]">—</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-0.5">Status</p>
                        <p id="flvStatus" class="text-sm font-medium text-[#2C3E50]">—</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-0.5">Start</p>
                        <p id="flvStart" class="text-sm text-[#2C3E50]">—</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-0.5">End</p>
                        <p id="flvEnd" class="text-sm text-[#2C3E50]">—</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-0.5">Days</p>
                        <p id="flvDays" class="text-sm text-[#2C3E50]">—</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-0.5">Filed</p>
                        <p id="flvCreated" class="text-sm text-[#2C3E50]">—</p>
                    </div>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-1">Reason</p>
                    <p id="flvReason" class="text-sm text-[#2C3E50] whitespace-pre-wrap">—</p>
                </div>
                <div id="flvRemarksWrap" class="hidden">
                    <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-1">Remarks</p>
                    <p id="flvRemarks" class="text-sm text-[#2C3E50] whitespace-pre-wrap">—</p>
                </div>
                <div id="flvApproverWrap" class="hidden">
                    <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-1">Approved By</p>
                    <p id="flvApprover" class="text-sm text-[#2C3E50]">—</p>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-[#E0E0E0]">
                <button onclick="closeViewFacultyLeave()"
                    class="w-full border border-[#E0E0E0] text-[#2C3E50] hover:bg-gray-50 font-medium py-2.5 rounded-lg transition text-sm">
                    Close
                </button>
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