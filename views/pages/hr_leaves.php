<?php
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hr') {
    header('Location: /login');
    exit;
}

require_once __DIR__ . '/../../config/database.php';

// Stats
$stats = $pdo->query("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected
    FROM leave_requests
")->fetch();

// All leave requests (pending first, then newest)
$stmt = $pdo->query("
    SELECT 
        lr.*,
        e.first_name, e.middle_name, e.last_name, e.email,
        e.category, e.sub_category, e.leave_credits,
        u.username AS approver_username
    FROM leave_requests lr
    LEFT JOIN employees e ON lr.employee_id = e.id
    LEFT JOIN users u ON lr.approved_by = u.id
    ORDER BY 
        CASE WHEN lr.status = 'pending' THEN 0 ELSE 1 END,
        lr.created_at DESC
    LIMIT 200
");
$leaves = $stmt->fetchAll();

$leaveStyles = [
    'vacation'  => 'text-blue-600 bg-blue-50',
    'sick'      => 'text-rose-600 bg-rose-50',
    'maternity' => 'text-purple-600 bg-purple-50',
    'paternity' => 'text-indigo-600 bg-indigo-50',
    'terminal'  => 'text-amber-600 bg-amber-50',
    'other'     => 'text-slate-600 bg-slate-100',
];
$statusStyles = [
    'pending'   => 'text-amber-600 bg-amber-50',
    'approved'  => 'text-[#0F5E3D] bg-[#F1FDF6]',
    'rejected'  => 'text-red-600 bg-red-50',
    'cancelled' => 'text-slate-600 bg-slate-100',
];
$leaveLabels = [
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
    <title>Leave Requests | DAMMC HR</title>
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
        $activePage = 'hr_leaves';
        require __DIR__ . '/../components/sidebar.php';
        ?>

        <div class="flex-1 md:ml-64 w-full min-w-0">
            <?php
            $pageTitle = 'Leave Requests';
            $pageSubtitle = 'Review and decide on employee leaves';
            $pageIcon = 'calendar-days';
            require __DIR__ . '/../components/topbar.php';
            ?>

            <main class="p-4 sm:p-6 lg:p-8 space-y-4">
                <!-- Toolbar -->
                <div class="animate-fade-in-up relative z-30 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-3 sm:p-4 shadow-sm">
                    <div class="flex flex-col lg:flex-row gap-3 lg:items-center lg:justify-between">
                        <div class="flex flex-wrap items-center gap-2">
                            <select id="statusFilter" onchange="filterLeaves()"
                                class="px-3 py-1.5 rounded-lg text-xs font-medium text-[#2C3E50]/80 bg-[#F1FDF6] border border-[#E0E0E0] focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition cursor-pointer">
                                <option value="">All Status</option>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                                <option value="cancelled">Cancelled</option>
                            </select>

                            <select id="typeFilter" onchange="filterLeaves()"
                                class="px-3 py-1.5 rounded-lg text-xs font-medium text-[#2C3E50]/80 bg-[#F1FDF6] border border-[#E0E0E0] focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition cursor-pointer">
                                <option value="">All Types</option>
                                <option value="vacation">Vacation</option>
                                <option value="sick">Sick</option>
                                <option value="maternity">Maternity</option>
                                <option value="paternity">Paternity</option>
                                <option value="terminal">Terminal</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div class="flex items-center gap-2">
                            <div class="relative flex-1 sm:w-72">
                                <i data-lucide="search"
                                    class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[#2C3E50]/40 pointer-events-none"></i>
                                <input type="text" id="searchInput" oninput="filterLeaves()" placeholder="Search by employee"
                                    class="w-full pl-10 pr-10 py-2 bg-[#F1FDF6]/60 border border-transparent rounded-lg text-sm text-[#2C3E50] placeholder:text-[#2C3E50]/40 focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:bg-white focus:border-transparent transition">
                                <kbd
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-mono text-[#2C3E50]/40 bg-white px-1.5 py-0.5 rounded border border-[#E0E0E0]">/</kbd>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Leaves table -->
                <div class="relative z-10 animate-fade-in-up-delay-1 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl shadow-sm overflow-hidden">

                    <?php if (empty($leaves)): ?>
                        <div class="p-12 text-center">
                            <div class="w-12 h-12 rounded-full bg-[#F1FDF6] flex items-center justify-center mx-auto mb-3 text-[#0F5E3D]">
                                <i data-lucide="calendar-x" class="w-5 h-5"></i>
                            </div>
                            <p class="text-sm font-medium text-[#2C3E50]">No leave requests yet</p>
                            <p class="text-xs text-[#2C3E50]/50 mt-1">Employee leave requests will appear here.</p>
                        </div>
                    <?php else: ?>

                        <!-- Desktop table -->
                        <div class="hidden md:block overflow-x-auto">
                            <table class="w-full">
                                <thead class="border-b border-[#E0E0E0]">
                                    <tr class="h-11">
                                        <th class="text-left text-xs font-medium text-[#2C3E50]/50 px-4 py-0">Employee</th>
                                        <th class="text-left text-xs font-medium text-[#2C3E50]/50 px-4 py-0">Type</th>
                                        <th class="text-left text-xs font-medium text-[#2C3E50]/50 px-4 py-0">Dates</th>
                                        <th class="text-left text-xs font-medium text-[#2C3E50]/50 px-4 py-0">Days</th>
                                        <th class="text-left text-xs font-medium text-[#2C3E50]/50 px-4 py-0">Status</th>
                                        <th class="text-right text-xs font-medium text-[#2C3E50]/50 px-4 py-0">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#E0E0E0]">
                                    <?php foreach ($leaves as $l):
                                        $fullName = trim(($l['first_name'] ?? '') . ' ' . ($l['last_name'] ?? ''));
                                        if (empty($fullName)) $fullName = 'Unknown';
                                        $initials = strtoupper(substr($l['first_name'] ?? 'U', 0, 1) . substr($l['last_name'] ?? '', 0, 1));
                                        $leaveClass = $leaveStyles[$l['leave_type']] ?? 'text-slate-600 bg-slate-100';
                                        $statusClass = $statusStyles[$l['status']] ?? 'text-slate-600 bg-slate-100';
                                        $leaveLabel = $leaveLabels[$l['leave_type']] ?? ucfirst($l['leave_type']);
                                        $isPending = $l['status'] === 'pending';
                                    ?>
                                        <tr class="hover:bg-[#F1FDF6]/40 transition h-12"
                                            data-name="<?= strtolower($fullName) ?>"
                                            data-status="<?= $l['status'] ?>"
                                            data-type="<?= $l['leave_type'] ?>">
                                            <td class="px-4 py-0">
                                                <div class="flex items-center gap-2.5">
                                                    <div class="w-7 h-7 rounded-full bg-[#0F5E3D] text-white flex items-center justify-center font-semibold text-[10px] shrink-0">
                                                        <?= $initials ?>
                                                    </div>
                                                    <div class="min-w-0">
                                                        <p class="text-sm font-medium text-[#2C3E50] truncate"><?= htmlspecialchars($fullName) ?></p>
                                                        <p class="text-[10px] text-[#2C3E50]/40 truncate"><?= htmlspecialchars($l['sub_category'] ?? '') ?></p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-0">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium <?= $leaveClass ?>">
                                                    <?= htmlspecialchars($leaveLabel) ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-0">
                                                <span class="text-sm text-[#2C3E50]/70">
                                                    <?= date('M j', strtotime($l['start_date'])) ?>
                                                    <?= $l['start_date'] !== $l['end_date'] ? ' – ' . date('M j, Y', strtotime($l['end_date'])) : ', ' . date('Y', strtotime($l['start_date'])) ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-0">
                                                <span class="text-sm text-[#2C3E50]/70"><?= number_format((float) $l['days_count'], 1) ?></span>
                                            </td>
                                            <td class="px-4 py-0">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium capitalize <?= $statusClass ?>">
                                                    <?= htmlspecialchars($l['status']) ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-0">
                                                <div class="flex items-center justify-end gap-1">
                                                    <button
                                                        onclick='openViewLeave(<?= json_encode([
                                                            "id"       => (int) $l["id"],
                                                            "name"     => $fullName,
                                                            "email"    => $l["email"] ?? "—",
                                                            "category" => $l["category"] ?? "—",
                                                            "subcat"   => $l["sub_category"] ?? "—",
                                                            "type"     => $leaveLabel,
                                                            "start"    => $l["start_date"],
                                                            "end"      => $l["end_date"],
                                                            "days"     => $l["days_count"],
                                                            "reason"   => $l["reason"] ?? "",
                                                            "status"   => $l["status"],
                                                            "remarks"  => $l["remarks"] ?? "",
                                                            "created"  => $l["created_at"],
                                                            "approver" => $l["approver_username"] ?? "",
                                                            "approved" => $l["approved_at"] ?? "",
                                                            "credits"  => $l["leave_credits"] ?? 0,
                                                        ]) ?>)'
                                                        class="w-7 h-7 rounded-lg flex items-center justify-center text-[#2C3E50]/60 hover:bg-[#F1FDF6] hover:text-[#0F5E3D] transition"
                                                        title="View details">
                                                        <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                                    </button>
                                                    <?php if ($isPending): ?>
                                                        <button
                                                            onclick='openApproveReject(<?= json_encode([
                                                                "id"   => (int) $l["id"],
                                                                "name" => $fullName,
                                                                "days" => (float) $l["days_count"],
                                                                "type" => $leaveLabel,
                                                            ]) ?>)'
                                                            class="w-7 h-7 rounded-lg flex items-center justify-center text-[#2C3E50]/60 hover:bg-[#F1FDF6] hover:text-[#0F5E3D] transition"
                                                            title="Approve / Reject">
                                                            <i data-lucide="check-check" class="w-3.5 h-3.5"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Mobile cards -->
                        <div class="md:hidden divide-y divide-[#E0E0E0]">
                            <?php foreach ($leaves as $l):
                                $fullName = trim(($l['first_name'] ?? '') . ' ' . ($l['last_name'] ?? ''));
                                if (empty($fullName)) $fullName = 'Unknown';
                                $initials = strtoupper(substr($l['first_name'] ?? 'U', 0, 1) . substr($l['last_name'] ?? '', 0, 1));
                                $leaveClass = $leaveStyles[$l['leave_type']] ?? 'text-slate-600 bg-slate-100';
                                $statusClass = $statusStyles[$l['status']] ?? 'text-slate-600 bg-slate-100';
                                $leaveLabel = $leaveLabels[$l['leave_type']] ?? ucfirst($l['leave_type']);
                                $isPending = $l['status'] === 'pending';
                            ?>
                                <div class="p-4 hover:bg-[#F1FDF6]/40 transition"
                                    data-name="<?= strtolower($fullName) ?>"
                                    data-status="<?= $l['status'] ?>"
                                    data-type="<?= $l['leave_type'] ?>">
                                    <div class="flex items-start gap-3">
                                        <div class="w-9 h-9 rounded-full bg-[#0F5E3D] text-white flex items-center justify-center font-semibold text-xs shrink-0">
                                            <?= $initials ?>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center justify-between gap-2 mb-1">
                                                <p class="text-sm font-medium text-[#2C3E50] truncate"><?= htmlspecialchars($fullName) ?></p>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium capitalize <?= $statusClass ?>">
                                                    <?= htmlspecialchars($l['status']) ?>
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-2 mb-1">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium <?= $leaveClass ?>">
                                                    <?= htmlspecialchars($leaveLabel) ?>
                                                </span>
                                                <span class="text-xs text-[#2C3E50]/60">
                                                    <?= date('M j', strtotime($l['start_date'])) ?>–<?= date('M j', strtotime($l['end_date'])) ?>
                                                </span>
                                            </div>
                                            <p class="text-[10px] text-[#2C3E50]/40"><?= number_format((float) $l['days_count'], 1) ?> days</p>
                                            <div class="flex items-center gap-2 mt-3">
                                                <button
                                                    onclick='openViewLeave(<?= json_encode([
                                                        "id" => (int) $l["id"], "name" => $fullName,
                                                        "type" => $leaveLabel, "start" => $l["start_date"], "end" => $l["end_date"],
                                                        "days" => $l["days_count"], "reason" => $l["reason"] ?? "",
                                                        "status" => $l["status"], "remarks" => $l["remarks"] ?? "",
                                                        "created" => $l["created_at"], "credits" => $l["leave_credits"] ?? 0,
                                                    ]) ?>)'
                                                    class="flex-1 text-xs py-2 rounded-lg border border-[#E0E0E0] text-[#2C3E50] hover:bg-gray-50 transition">
                                                    View
                                                </button>
                                                <?php if ($isPending): ?>
                                                    <button
                                                        onclick='openApproveReject(<?= json_encode([
                                                            "id" => (int) $l["id"], "name" => $fullName,
                                                            "days" => (float) $l["days_count"], "type" => $leaveLabel,
                                                        ]) ?>)'
                                                        class="flex-1 text-xs py-2 rounded-lg bg-[#0F5E3D] text-white hover:bg-[#0a4a2f] transition">
                                                        Decide
                                                    </button>
                                                <?php endif; ?>
                                            </div>
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
                        <p class="text-sm font-medium text-[#2C3E50]">No leaves found</p>
                        <p class="text-xs text-[#2C3E50]/50 mt-1">Try adjusting your search or filters.</p>
                    </div>

                    <div class="px-4 sm:px-6 py-3 border-t border-[#E0E0E0]">
                        <p class="text-xs text-[#2C3E50]/50">
                            Showing <span class="font-medium text-[#2C3E50]"><?= count($leaves) ?></span> recent requests
                        </p>
                    </div>
                </div>

            </main>
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
            <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
                <div>
                    <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-0.5">Employee</p>
                    <p id="vlName" class="text-sm font-medium text-[#2C3E50]">—</p>
                    <p id="vlEmail" class="text-xs text-[#2C3E50]/50">—</p>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-0.5">Type</p>
                        <p id="vlType" class="text-sm font-medium text-[#2C3E50]">—</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-0.5">Status</p>
                        <p id="vlStatus" class="text-sm font-medium text-[#2C3E50] capitalize">—</p>
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
                <div id="vlApproverWrap" class="hidden">
                    <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-1">Decided by</p>
                    <p id="vlApprover" class="text-sm text-[#2C3E50]">—</p>
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

    <!-- Approve / Reject modal -->
    <div id="decideModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm p-4">
        <div id="decideCard" class="bg-white rounded-xl shadow-xl w-full max-w-md transform transition-all duration-200 scale-95 overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-[#E0E0E0]">
                <div>
                    <h3 class="text-base font-bold text-[#2C3E50]">Decide Leave</h3>
                    <p class="text-xs text-[#2C3E50]/50" id="decideSubtitle">—</p>
                </div>
                <button onclick="closeDecide()" class="text-[#2C3E50]/40 hover:text-[#2C3E50] transition">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form id="decideForm" class="p-6 space-y-4">
                <input type="hidden" name="action" value="decide">
                <input type="hidden" name="id" id="decideId">
                <input type="hidden" name="decision" id="decideDecision">

                <div class="bg-[#F1FDF6] border border-[#0F5E3D]/10 rounded-lg p-3">
                    <p class="text-xs text-[#2C3E50]/70">
                        <span class="font-semibold text-[#2C3E50]" id="decideEmployee">—</span>
                        is requesting <span class="font-semibold" id="decideDays">—</span> day(s) of
                        <span class="font-semibold" id="decideType">—</span> leave.
                    </p>
                </div>

                <div>
                    <label class="block text-xs font-medium text-[#2C3E50] mb-1.5">
                        Remarks <span class="text-[#2C3E50]/40">(optional)</span>
                    </label>
                    <textarea name="remarks" id="decideRemarks" rows="3"
                        class="w-full px-3 py-2.5 border border-[#E0E0E0] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition resize-none"
                        placeholder="Add a note for the faculty member..."></textarea>
                </div>
            </form>

            <div class="flex gap-3 px-6 py-4 border-t border-[#E0E0E0]">
                <button type="button" onclick="closeDecide()"
                    class="flex-1 border border-[#E0E0E0] text-[#2C3E50] hover:bg-gray-50 font-medium py-2.5 rounded-lg transition text-sm">
                    Cancel
                </button>
                <button type="button" onclick="submitDecision('rejected')"
                    class="flex-1 bg-red-600 hover:bg-red-700 text-white font-medium py-2.5 rounded-lg transition text-sm">
                    Reject
                </button>
                <button type="button" onclick="submitDecision('approved')"
                    class="flex-1 bg-[#0F5E3D] hover:bg-[#0a4a2f] text-white font-medium py-2.5 rounded-lg transition text-sm">
                    Approve
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
            <h3 class="text-lg font-bold text-[#2C3E50] mb-2" id="successTitle">Done</h3>
            <p class="text-sm text-[#2C3E50]/60 mb-6" id="successMessage">—</p>
            <button onclick="closeSuccess()"
                class="w-full bg-[#0F5E3D] hover:bg-[#0a4a2f] text-white font-medium py-2.5 rounded-lg transition text-sm">
                Done
            </button>
        </div>
    </div>

    <?php require_once __DIR__ . '/../components/logout_modal.php'; ?>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="/assets/js/app.js"></script>
    <script src="/assets/js/notifications.js"></script>
    <script src="/assets/js/profile.js"></script>
    <script src="/assets/js/leaves.js"></script>
</body>
</html>