<?php
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: /login');
    exit;
}

require_once __DIR__ . '/../../config/database.php';

$userId = (int) $_SESSION['user_id'];

// Fetch employee record
$stmt = $pdo->prepare("
    SELECT e.id AS employee_id, e.first_name, e.last_name, e.leave_credits, e.employment_type, e.basic_salary
    FROM users u
    LEFT JOIN employees e ON u.employee_id = e.id
    WHERE u.id = ?
    LIMIT 1
");
$stmt->execute([$userId]);
$me = $stmt->fetch();
$employeeId = (int) ($me['employee_id'] ?? 0);
$isPartTime = (($me['employment_type'] ?? 'full-time') === 'part-time');

$leaves = [];
$creditsUsed = 0;

if ($employeeId > 0) {
    $stmt = $pdo->prepare("
        SELECT lr.*, 
               u.username AS approver_username,
               e.first_name AS approver_first,
               e.last_name  AS approver_last
        FROM leave_requests lr
        LEFT JOIN users u     ON lr.approved_by = u.id
        LEFT JOIN employees e ON u.employee_id   = e.id
        WHERE lr.employee_id = ?
        ORDER BY lr.created_at DESC
    ");
    $stmt->execute([$employeeId]);
    $leaves = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(days_count), 0) 
        FROM leave_requests 
        WHERE employee_id = ? AND status = 'approved'
    ");
    $stmt->execute([$employeeId]);
    $creditsUsed = (float) $stmt->fetchColumn();
}

$creditsLeft = max(0, (float)($me['leave_credits'] ?? 0) - $creditsUsed);

// Per-type credit caps + usage
$typeCaps = [
    'vacation' => 7.5,
    'sick'     => 7.5,
];

$typeUsed = [
    'vacation'  => 0,
    'sick'      => 0,
    'maternity' => 0,
    'paternity' => 0,
    'terminal'  => 0,
    'other'     => 0,
];

if ($employeeId > 0) {
    $stmt = $pdo->prepare("
        SELECT leave_type, COALESCE(SUM(days_count), 0) AS total
        FROM leave_requests
        WHERE employee_id = ? AND status = 'approved'
        GROUP BY leave_type
    ");
    $stmt->execute([$employeeId]);
    foreach ($stmt->fetchAll() as $row) {
        $typeUsed[$row['leave_type']] = (float) $row['total'];
    }
}

$typeRemaining = [];
foreach ($typeCaps as $t => $cap) {
    $typeRemaining[$t] = max(0, $cap - ($typeUsed[$t] ?? 0));
}

$today = date('Y-m-d');

$existingRanges = [];
foreach ($leaves as $l) {
    if (!in_array($l['status'], ['pending', 'approved'], true)) continue;
    if ($l['end_date'] < $today) continue;
    $existingRanges[] = [
        'start'  => $l['start_date'],
        'end'    => $l['end_date'],
        'status' => $l['status'],
    ];
}

$defaultStartDate = $today;
for ($i = 0; $i < 365; $i++) {
    $candidate = date('Y-m-d', strtotime("$today +$i days"));
    $blocked = false;
    foreach ($existingRanges as $r) {
        if ($candidate >= $r['start'] && $candidate <= $r['end']) {
            $blocked = true;
            break;
        }
    }
    if (!$blocked) {
        $defaultStartDate = $candidate;
        break;
    }
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Leaves | DAMMC Staff</title>
    <link rel="icon" type="image/png" href="/assets/images/dammc-logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .custom-select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%232C3E50' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1.25em 1.25em;
            padding-right: 2.25rem;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }

        .flatpickr-calendar {
            font-family: 'Inter', sans-serif;
            border-radius: 10px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
            border: 1px solid #E0E0E0;
            width: 260px;
            padding: 6px 0;
        }
        .flatpickr-months { padding: 4px 0; }
        .flatpickr-months .flatpickr-month { height: 28px; }
        .flatpickr-current-month { font-size: 13px; font-weight: 600; padding: 4px 0; }
        .flatpickr-current-month .flatpickr-monthDropdown-months,
        .flatpickr-current-month input.cur-year { font-weight: 600; font-size: 13px; }
        .flatpickr-weekdays { height: 22px; }
        span.flatpickr-weekday { font-size: 10px; font-weight: 600; color: #2C3E50; opacity: 0.5; }
        .flatpickr-days { width: 260px; }
        .dayContainer { width: 260px; min-width: 260px; max-width: 260px; padding: 0 4px; }
        .flatpickr-day {
            max-width: 32px; height: 32px; line-height: 30px;
            font-size: 12px; border-radius: 6px; margin: 0;
        }
        .flatpickr-day.selected,
        .flatpickr-day.startRange,
        .flatpickr-day.endRange,
        .flatpickr-day.selected:hover {
            background: #0F5E3D; border-color: #0F5E3D; color: #fff;
        }
        .flatpickr-day.today { border-color: #0F5E3D; }
        .flatpickr-day.today:hover {
            background: #F1FDF6; color: #0F5E3D; border-color: #0F5E3D;
        }
        .flatpickr-day.flatpickr-disabled,
        .flatpickr-day.flatpickr-disabled:hover {
            color: #cbd5e1; text-decoration: line-through;
            cursor: not-allowed; background: transparent;
        }
        .flatpickr-prev-month svg,
        .flatpickr-next-month svg { fill: #2C3E50; width: 12px; height: 12px; }
        .flatpickr-prev-month, .flatpickr-next-month { padding: 8px; }
        .flatpickr-prev-month:hover svg,
        .flatpickr-next-month:hover svg { fill: #0F5E3D; }
    </style>
</head>
<body class="bg-gradient-to-br from-[#F1FDF6] via-[#eafaf1] to-[#dcf3e5] min-h-screen"
      data-leave-disabled="<?= $isPartTime ? '1' : '0' ?>">

    <div class="flex min-h-screen">
        <div id="sidebarOverlay" onclick="closeSidebar()"
            class="fixed inset-0 bg-black/40 z-40 hidden md:hidden backdrop-blur-sm"></div>

        <?php
        $activePage = 'staff_leaves';
        require __DIR__ . '/../components/sidebar.php';
        ?>

        <div class="flex-1 md:ml-64 w-full min-w-0">
            <?php
            $pageTitle = 'My Leaves';
            $pageSubtitle = 'File and track your leave requests';
            $pageIcon = 'calendar-days';
            require __DIR__ . '/../components/topbar.php';
            ?>

            <main class="p-4 sm:p-6 lg:p-8 space-y-4 sm:space-y-6">

                <?php if ($isPartTime): ?>
                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 flex items-start gap-3">
                        <div class="w-8 h-8 rounded-lg bg-amber-100 flex items-center justify-center text-amber-600 shrink-0">
                            <i data-lucide="info" class="w-4 h-4"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-amber-800">Leave filing is not available</p>
                            <p class="text-xs text-amber-700/80 mt-0.5">
                                Part-time employees are not eligible to file leave requests. Contact HR if you have questions.
                            </p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Toolbar -->
                <div class="bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-3 sm:p-4 shadow-sm flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between">
                    <div class="relative flex-1 sm:max-w-sm">
                        <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[#2C3E50]/40 pointer-events-none"></i>
                        <input type="text" id="leaveSearch" oninput="filterLeaves()" placeholder="Search leaves"
                            class="w-full pl-10 pr-4 py-2 bg-[#F1FDF6]/60 border border-transparent rounded-lg text-sm text-[#2C3E50] placeholder:text-[#2C3E50]/40 focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:bg-white focus:border-transparent transition">
                    </div>
                    <?php if (!$isPartTime): ?>
                        <button onclick="openLeaveModal()"
                            class="flex items-center justify-center gap-1.5 bg-[#0F5E3D] hover:bg-[#0a4a2f] active:scale-[0.99] text-white font-medium py-2 px-4 rounded-lg transition text-xs shadow-sm hover:shadow-md whitespace-nowrap">
                            <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                            <span>File a Leave</span>
                        </button>
                    <?php else: ?>
                        <div class="flex items-center gap-1.5 bg-slate-100 text-slate-500 font-medium py-2 px-4 rounded-lg text-xs cursor-not-allowed"
                            title="Part-time employees are not eligible for leave">
                            <i data-lucide="lock" class="w-3.5 h-3.5"></i>
                            <span>Leave not available</span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Leaves table -->
                <div class="bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl shadow-sm overflow-hidden">

                    <div class="hidden md:block overflow-x-auto">
                        <table class="w-full">
                            <thead class="border-b border-[#E0E0E0]">
                                <tr class="h-11">
                                    <th class="text-left text-xs font-medium text-[#2C3E50]/50 px-4">Type</th>
                                    <th class="text-left text-xs font-medium text-[#2C3E50]/50 px-4">Start</th>
                                    <th class="text-left text-xs font-medium text-[#2C3E50]/50 px-4">End</th>
                                    <th class="text-left text-xs font-medium text-[#2C3E50]/50 px-4">Days</th>
                                    <th class="text-left text-xs font-medium text-[#2C3E50]/50 px-4">Status</th>
                                    <th class="w-20 px-4 text-right text-xs font-medium text-[#2C3E50]/50">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="leavesTableBody" class="divide-y divide-[#E0E0E0]">
                                <?php if (empty($leaves)): ?>
                                    <tr><td colspan="6" class="text-center text-sm text-[#2C3E50]/50 py-12">
                                        <i data-lucide="calendar-x" class="w-6 h-6 mx-auto mb-2 text-[#2C3E50]/30"></i>
                                        <?php if ($isPartTime): ?>
                                            Part-time employees are not eligible for leave.
                                        <?php else: ?>
                                            No leave requests yet. Click "File a Leave" to submit one.
                                        <?php endif; ?>
                                    </td></tr>
                                <?php else: foreach ($leaves as $l):
                                    $sClass = $statusStyles[$l['status']] ?? 'text-slate-600 bg-slate-100';
                                    $sLabel = $statusLabels[$l['status']] ?? ucfirst($l['status']);
                                    $tLabel = $leaveTypes[$l['leave_type']] ?? ucfirst($l['leave_type']);
                                    $isPending = $l['status'] === 'pending';
                                    ?>
                                    <tr class="hover:bg-[#F1FDF6]/40 transition h-12 leave-row"
                                        data-search="<?= strtolower($tLabel . ' ' . $sLabel . ' ' . ($l['reason'] ?? '')) ?>">
                                        <td class="px-4">
                                            <span class="text-sm font-medium text-[#2C3E50]"><?= htmlspecialchars($tLabel) ?></span>
                                        </td>
                                        <td class="px-4 text-sm text-[#2C3E50]/70"><?= date('M j, Y', strtotime($l['start_date'])) ?></td>
                                        <td class="px-4 text-sm text-[#2C3E50]/70"><?= date('M j, Y', strtotime($l['end_date'])) ?></td>
                                        <td class="px-4 text-sm text-[#2C3E50]/70"><?= number_format((float)$l['days_count'], 2) ?></td>
                                        <td class="px-4">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium <?= $sClass ?>">
                                                <?= $sLabel ?>
                                            </span>
                                        </td>
                                        <td class="px-4">
                                            <div class="flex items-center justify-end gap-1">
                                                <button onclick='openViewLeave(<?= json_encode([
                                                    "id" => $l["id"],
                                                    "type" => $tLabel,
                                                    "start" => $l["start_date"],
                                                    "end" => $l["end_date"],
                                                    "days" => $l["days_count"],
                                                    "reason" => $l["reason"] ?? "",
                                                    "status" => $sLabel,
                                                    "remarks" => $l["remarks"] ?? "",
                                                    "created" => $l["created_at"],
                                                ]) ?>)'
                                                    class="w-7 h-7 rounded-lg flex items-center justify-center text-[#2C3E50]/60 hover:bg-[#F1FDF6] hover:text-[#0F5E3D] transition"
                                                    title="View details">
                                                    <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                                </button>
                                                <?php if ($isPending): ?>
                                                    <button onclick="cancelLeave(<?= $l['id'] ?>)"
                                                        class="w-7 h-7 rounded-lg flex items-center justify-center text-[#2C3E50]/60 hover:bg-red-50 hover:text-red-600 transition"
                                                        title="Cancel request">
                                                        <i data-lucide="x" class="w-3.5 h-3.5"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div id="leavesMobileCards" class="md:hidden divide-y divide-[#E0E0E0]">
                        <?php if (empty($leaves)): ?>
                            <div class="p-10 text-center text-sm text-[#2C3E50]/50">
                                <?php if ($isPartTime): ?>
                                    Part-time employees are not eligible for leave.
                                <?php else: ?>
                                    No leave requests yet.
                                <?php endif; ?>
                            </div>
                        <?php else: foreach ($leaves as $l):
                            $sClass = $statusStyles[$l['status']] ?? 'text-slate-600 bg-slate-100';
                            $sLabel = $statusLabels[$l['status']] ?? ucfirst($l['status']);
                            $tLabel = $leaveTypes[$l['leave_type']] ?? ucfirst($l['leave_type']);
                            $isPending = $l['status'] === 'pending';
                            ?>
                            <div class="p-4 leave-row" data-search="<?= strtolower($tLabel . ' ' . $sLabel) ?>">
                                <div class="flex items-start justify-between gap-2 mb-2">
                                    <span class="text-sm font-medium text-[#2C3E50]"><?= htmlspecialchars($tLabel) ?></span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium <?= $sClass ?> shrink-0">
                                        <?= $sLabel ?>
                                    </span>
                                </div>
                                <p class="text-xs text-[#2C3E50]/60">
                                    <?= date('M j, Y', strtotime($l['start_date'])) ?> → <?= date('M j, Y', strtotime($l['end_date'])) ?>
                                </p>
                                <p class="text-xs text-[#2C3E50]/40 mt-1"><?= number_format((float)$l['days_count'], 2) ?> day(s)</p>
                                <div class="flex items-center gap-1 mt-3">
                                    <button onclick='openViewLeave(<?= json_encode([
                                        "id" => $l["id"], "type" => $tLabel, "start" => $l["start_date"], "end" => $l["end_date"],
                                        "days" => $l["days_count"], "reason" => $l["reason"] ?? "", "status" => $sLabel,
                                        "remarks" => $l["remarks"] ?? "", "created" => $l["created_at"],
                                    ]) ?>)'
                                        class="flex-1 text-xs py-2 rounded-lg border border-[#E0E0E0] text-[#2C3E50] hover:bg-gray-50 transition">
                                        View
                                    </button>
                                    <?php if ($isPending): ?>
                                        <button onclick="cancelLeave(<?= $l['id'] ?>)"
                                            class="flex-1 text-xs py-2 rounded-lg border border-red-200 text-red-600 hover:bg-red-50 transition">
                                            Cancel
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>

                    <div id="leavesEmpty" class="hidden p-12 text-center">
                        <div class="w-12 h-12 rounded-full bg-[#F1FDF6] flex items-center justify-center mx-auto mb-3 text-[#0F5E3D]">
                            <i data-lucide="search-x" class="w-5 h-5"></i>
                        </div>
                        <p class="text-sm font-medium text-[#2C3E50]">No matching leaves</p>
                        <p class="text-xs text-[#2C3E50]/50 mt-1">Try a different search term.</p>
                    </div>

                </div>

            </main>
        </div>
    </div>

    <!-- File leave modal -->
    <div id="leaveModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm p-4"
         data-default-start="<?= htmlspecialchars($defaultStartDate) ?>">
        <div id="leaveCard" class="bg-white rounded-xl shadow-xl w-full max-w-md transform transition-all duration-200 scale-95 overflow-hidden">
            <div class="flex items-center justify-between px-5 py-3.5 border-b border-[#E0E0E0]">
                <div>
                    <h3 class="text-sm font-bold text-[#2C3E50]">File a Leave</h3>
                    <p class="text-[11px] text-[#2C3E50]/50">Remaining: <span id="remainingCredits"><?= number_format($creditsLeft, 2) ?></span> day(s)</p>
                </div>
                <button onclick="closeLeaveModal()" class="text-[#2C3E50]/40 hover:text-[#2C3E50] transition">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>

            <form id="leaveForm" class="p-5 space-y-3.5 max-h-[70vh] overflow-y-auto">
                <input type="hidden" name="action" value="create">

                <div>
                    <label class="block text-xs font-medium text-[#2C3E50] mb-1">Leave Type *</label>
                    <select name="leave_type" id="leaveType" required
                        class="custom-select w-full pl-3 py-2 bg-white border border-[#E0E0E0] rounded-lg text-sm text-[#2C3E50] focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition cursor-pointer">
                        <option value="">Select leave type</option>
                        <option value="vacation">Vacation/Mandaroy</option>
                        <option value="sick">Sick</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-[#2C3E50] mb-1">Start Date *</label>
                        <input type="text" name="start_date" id="leaveStart" required readonly
                            placeholder="Select start date"
                            class="w-full px-3 py-2 border border-[#E0E0E0] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition cursor-pointer bg-white">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-[#2C3E50] mb-1">End Date *</label>
                        <input type="text" name="end_date" id="leaveEnd" required readonly
                            placeholder="Select end date"
                            class="w-full px-3 py-2 border border-[#E0E0E0] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition cursor-pointer bg-white">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-[#2C3E50] mb-1">Days Count</label>
                    <input type="text" id="leaveDays" readonly
                        class="w-full px-3 py-2 bg-[#F1FDF6]/60 border border-[#E0E0E0] rounded-lg text-sm text-[#0F5E3D] font-mono cursor-not-allowed"
                        placeholder="—">
                </div>

                <div>
                    <label class="block text-xs font-medium text-[#2C3E50] mb-1">Reason *</label>
                    <textarea name="reason" id="leaveReason" rows="2" required
                        class="w-full px-3 py-2 border border-[#E0E0E0] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition resize-none"
                        placeholder="Briefly explain the reason for your leave..."></textarea>
                </div>
            </form>

            <div class="flex gap-2 px-5 py-3.5 border-t border-[#E0E0E0]">
                <button type="button" onclick="closeLeaveModal()"
                    class="flex-1 border border-[#E0E0E0] text-[#2C3E50] hover:bg-gray-50 font-medium py-2 rounded-lg transition text-sm">
                    Cancel
                </button>
                <button type="submit" form="leaveForm" id="leaveSubmitBtn"
                    class="flex-1 bg-[#0F5E3D] hover:bg-[#0a4a2f] text-white font-medium py-2 rounded-lg transition text-sm">
                    Submit Request
                </button>
            </div>
        </div>
    </div>

    <!-- View leave modal -->
    <div id="viewLeaveModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm p-4">
        <div id="viewLeaveCard" class="bg-white rounded-xl shadow-xl w-full max-w-md transform transition-all duration-200 scale-95 overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-[#E0E0E0]">
                <h3 class="text-base font-bold text-[#2C3E50]">Leave Details</h3>
                <button onclick="closeViewLeave()" class="text-[#2C3E50]/40 hover:text-[#2C3E50] transition">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-0.5">Type</p>
                        <p id="vlType" class="text-sm font-medium text-[#2C3E50]">—</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-0.5">Status</p>
                        <p id="vlStatus" class="text-sm font-medium text-[#2C3E50]">—</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-0.5">Start</p>
                        <p id="vlStart" class="text-sm text-[#2C3E50]">—</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-0.5">End</p>
                        <p id="vlEnd" class="text-sm text-[#2C3E50]">—</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-0.5">Days</p>
                        <p id="vlDays" class="text-sm text-[#2C3E50]">—</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-0.5">Filed</p>
                        <p id="vlCreated" class="text-sm text-[#2C3E50]">—</p>
                    </div>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-1">Reason</p>
                    <p id="vlReason" class="text-sm text-[#2C3E50] whitespace-pre-wrap">—</p>
                </div>
                <div id="vlRemarksWrap" class="hidden">
                    <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-1">Remarks</p>
                    <p id="vlRemarks" class="text-sm text-[#2C3E50] whitespace-pre-wrap">—</p>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-[#E0E0E0]">
                <button onclick="closeViewLeave()"
                    class="w-full border border-[#E0E0E0] text-[#2C3E50] hover:bg-gray-50 font-medium py-2.5 rounded-lg transition text-sm">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Loading overlay -->
    <div id="loadingOverlay" class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-xs p-8 text-center">
            <div class="flex items-center justify-center mb-4">
                <svg class="animate-spin h-10 w-10 text-[#0F5E3D]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                    <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
            <h3 class="text-base font-bold text-[#2C3E50] mb-1" id="loadingTitle">Working...</h3>
            <p class="text-xs text-[#2C3E50]/60" id="loadingMessage">Please wait.</p>
        </div>
    </div>

    <!-- Error modal -->
    <div id="errorModal" class="fixed inset-0 z-[80] hidden items-center justify-center bg-black/40 backdrop-blur-sm p-4">
        <div id="errorCard" class="bg-white rounded-xl shadow-xl w-full max-w-sm p-8 text-center transform transition-all duration-200 scale-95">
            <div class="flex items-center justify-center w-16 h-16 rounded-full bg-red-50 mx-auto mb-5">
                <i data-lucide="alert-circle" class="w-8 h-8 text-red-600"></i>
            </div>
            <h3 class="text-lg font-bold text-[#2C3E50] mb-2" id="errorTitle">Something went wrong</h3>
            <p class="text-sm text-[#2C3E50]/60 mb-6" id="errorMessage">—</p>
            <button onclick="closeErrorModal()"
                class="w-full bg-[#0F5E3D] hover:bg-[#0a4a2f] text-white font-medium py-2.5 rounded-lg transition text-sm">
                Got it
            </button>
        </div>
    </div>

    <!-- Success modal -->
    <div id="successModal" class="fixed inset-0 z-[70] hidden items-center justify-center bg-black/40 backdrop-blur-sm p-4">
        <div id="successCard" class="bg-white rounded-xl shadow-xl w-full max-w-sm p-8 text-center transform transition-all duration-200 scale-95">
            <div class="relative mx-auto mb-5 w-16 h-16">
                <div class="absolute inset-0 rounded-full bg-[#F1FDF6] animate-ping opacity-40"></div>
                <div class="relative w-16 h-16 rounded-full bg-[#F1FDF6] flex items-center justify-center text-[#0F5E3D]">
                    <i data-lucide="check-circle" class="w-8 h-8"></i>
                </div>
            </div>
            <h3 class="text-lg font-bold text-[#2C3E50] mb-2" id="successTitle">Success</h3>
            <p class="text-sm text-[#2C3E50]/60 mb-6" id="successMessage">—</p>
            <button onclick="closeSuccessModal()"
                class="w-full bg-[#0F5E3D] hover:bg-[#0a4a2f] text-white font-medium py-2.5 rounded-lg transition text-sm">
                Done
            </button>
        </div>
    </div>

    <?php require_once __DIR__ . '/../components/logout_modal.php'; ?>

    <script>
    window.EXISTING_LEAVE_RANGES = <?= json_encode($existingRanges, JSON_UNESCAPED_SLASHES) ?>;
    window.CREDIT_LIMITS = {
        total:         <?= (float) $creditsLeft ?>,
        typeCaps:      <?= json_encode($typeCaps, JSON_UNESCAPED_SLASHES) ?>,
        typeRemaining: <?= json_encode($typeRemaining, JSON_UNESCAPED_SLASHES) ?>
    };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="/assets/js/app.js"></script>
    <script src="/assets/js/notifications.js"></script>
    <script src="/assets/js/profile.js"></script>
    <script src="/assets/js/staff_leaves.js"></script>
</body>
</html>