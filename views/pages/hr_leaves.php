<?php
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'hr') {
    header('Location: /login');
    exit;
}
require_once __DIR__ . '/../../config/database.php';

$stats = $pdo->query("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected
    FROM leave_requests
")->fetch();

$stmt = $pdo->query("
    SELECT 
        lr.*,
        e.id AS employee_id,
        e.first_name, e.middle_name, e.last_name, e.email,
        e.category, e.sub_category, e.year_level, e.position,
        e.employment_type, e.leave_credits,
        u.username AS approver_username,
        d.username AS dean_username
    FROM leave_requests lr
    LEFT JOIN employees e ON lr.employee_id = e.id
    LEFT JOIN users u ON lr.approved_by = u.id
    LEFT JOIN users d ON lr.dean_approved_by = d.id
    ORDER BY 
        CASE WHEN lr.status = 'pending' THEN 0 ELSE 1 END,
        lr.created_at DESC
    LIMIT 200
");
$leaves = $stmt->fetchAll();

// ============================================
// Distinct positions that actually appear in leave requests
// (drives the Position/Designation dropdown — no dead options)
// ============================================
$positions = $pdo->query("
    SELECT DISTINCT e.position
    FROM leave_requests lr
    INNER JOIN employees e ON lr.employee_id = e.id
    WHERE e.position IS NOT NULL AND e.position <> ''
    ORDER BY e.position ASC
")->fetchAll(PDO::FETCH_COLUMN);

$leaveStyles = [
    'vacation' => 'text-blue-700 bg-blue-50',
    'sick' => 'text-rose-700 bg-rose-50',
    'maternity' => 'text-purple-700 bg-purple-50',
    'paternity' => 'text-indigo-700 bg-indigo-50',
    'terminal' => 'text-amber-700 bg-amber-50',
    'other' => 'text-slate-700 bg-slate-100',
];
$statusStyles = [
    'pending' => 'text-amber-700 bg-amber-50',
    'approved' => 'text-[#0F5E3D] bg-[#F1FDF6]',
    'rejected' => 'text-red-700 bg-red-50',
    'cancelled' => 'text-slate-700 bg-slate-100',
];
$leaveLabels = [
    'vacation' => 'Vacation',
    'sick' => 'Sick',
    'maternity' => 'Maternity',
    'paternity' => 'Paternity',
    'terminal' => 'Terminal',
    'other' => 'Other',
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
        $role = $_SESSION['role'] ?? 'hr';
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

                <!-- Stat cards -->
                <div class="animate-fade-in-up grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <div class="bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-4 shadow-sm">
                        <p class="text-xs uppercase tracking-wider text-[#2C3E50]/50 mb-1">Total Requests</p>
                        <p class="text-2xl font-bold text-[#2C3E50]"><?= (int) $stats['total'] ?></p>
                    </div>
                    <div class="bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-4 shadow-sm">
                        <p class="text-xs uppercase tracking-wider text-[#2C3E50]/50 mb-1">Pending</p>
                        <p class="text-2xl font-bold text-amber-600"><?= (int) $stats['pending'] ?></p>
                    </div>
                    <div class="bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-4 shadow-sm">
                        <p class="text-xs uppercase tracking-wider text-[#2C3E50]/50 mb-1">Approved</p>
                        <p class="text-2xl font-bold text-[#0F5E3D]"><?= (int) $stats['approved'] ?></p>
                    </div>
                    <div class="bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-4 shadow-sm">
                        <p class="text-xs uppercase tracking-wider text-[#2C3E50]/50 mb-1">Rejected</p>
                        <p class="text-2xl font-bold text-red-600"><?= (int) $stats['rejected'] ?></p>
                    </div>
                </div>

                <!-- Toolbar -->
                <div
                    class="animate-fade-in-up relative bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-3 sm:p-4 shadow-sm">
                    <div class="flex flex-col lg:flex-row gap-3 lg:items-center lg:justify-between">

                        <div class="flex flex-wrap items-center gap-2">
                            <select id="statusFilter" onchange="filterLeaves()"
                                class="px-3 py-1.5 rounded-lg text-sm font-medium text-[#2C3E50]/80 bg-[#F1FDF6] border border-[#E0E0E0] focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition cursor-pointer">
                                <option value="">All Status</option>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                                <option value="cancelled">Cancelled</option>
                            </select>

                            <select id="typeFilter" onchange="filterLeaves()"
                                class="px-3 py-1.5 rounded-lg text-sm font-medium text-[#2C3E50]/80 bg-[#F1FDF6] border border-[#E0E0E0] focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition cursor-pointer">
                                <option value="">All Types</option>
                                <option value="vacation">Vacation</option>
                                <option value="sick">Sick</option>
                                <option value="other">Other</option>
                            </select>

                            <select id="positionFilter" onchange="filterLeaves()"
                                class="px-3 py-1.5 rounded-lg text-sm font-medium text-[#2C3E50]/80 bg-[#F1FDF6] border border-[#E0E0E0] focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition cursor-pointer max-w-[200px] truncate">
                                <option value="">All Positions</option>
                                <?php foreach ($positions as $pos): ?>
                                    <option value="<?= htmlspecialchars($pos) ?>"><?= htmlspecialchars($pos) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="flex items-center gap-2">
                            <div class="relative flex-1 sm:w-72">
                                <i data-lucide="search"
                                    class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[#2C3E50]/40 pointer-events-none"></i>
                                <input type="text" id="searchInput" oninput="filterLeaves()"
                                    placeholder="Search name, position, department"
                                    class="w-full pl-10 pr-16 py-2 bg-[#F1FDF6]/60 border border-transparent rounded-lg text-sm text-[#2C3E50] placeholder:text-[#2C3E50]/40 focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:bg-white focus:border-transparent transition">
                                <button type="button" onclick="clearSearch()"
                                    class="absolute right-9 top-1/2 -translate-y-1/2 w-6 h-6 rounded-full flex items-center justify-center text-[#2C3E50]/40 hover:text-[#2C3E50] hover:bg-[#F1FDF6] transition"
                                    title="Clear search">
                                    <i data-lucide="x" class="w-3 h-3"></i>
                                </button>
                                <kbd
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-mono text-[#2C3E50]/40 bg-white px-1.5 py-0.5 rounded border border-[#E0E0E0] pointer-events-none">/</kbd>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Leaves table -->
                <div
                    class="relative z-10 animate-fade-in-up-delay-1 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl shadow-sm overflow-hidden">

                    <?php if (empty($leaves)): ?>
                        <div class="p-12 text-center">
                            <div
                                class="w-12 h-12 rounded-full bg-[#F1FDF6] flex items-center justify-center mx-auto mb-3 text-[#0F5E3D]">
                                <i data-lucide="calendar-x" class="w-5 h-5"></i>
                            </div>
                            <p class="text-sm font-medium text-[#2C3E50]">No leave requests yet</p>
                            <p class="text-sm text-[#2C3E50]/50 mt-1">Employee leave requests will appear here.</p>
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
                                        <th class="text-left text-xs font-medium text-[#2C3E50]/50 px-4 py-0">Pay Status</th>
                                        <th class="text-right text-xs font-medium text-[#2C3E50]/50 px-4 py-0">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#E0E0E0]">
                                    <?php foreach ($leaves as $l):
                                        $fullName = trim(($l['first_name'] ?? '') . ' ' . ($l['last_name'] ?? ''));
                                        if (empty($fullName))
                                            $fullName = 'Unknown';
                                        $initials = strtoupper(substr($l['first_name'] ?? 'U', 0, 1) . substr($l['last_name'] ?? '', 0, 1));
                                        $leaveClass = $leaveStyles[$l['leave_type']] ?? 'text-slate-700 bg-slate-100';
                                        $statusClass = $statusStyles[$l['status']] ?? 'text-slate-700 bg-slate-100';
                                        $leaveLabel = $leaveLabels[$l['leave_type']] ?? ucfirst($l['leave_type']);
                                        
                                        // Pay Status logic
                                        $payStatus = $l['pay_status'] ?? null;
                                        $payLabel = '—';
                                        $payClass = 'text-slate-400 bg-slate-50';
                                        
                                        if ($payStatus === 'with_pay') {
                                            $payLabel = 'With Pay';
                                            $payClass = 'text-[#0F5E3D] bg-[#F1FDF6] border border-[#0F5E3D]/20';
                                        } elseif ($payStatus === 'without_pay') {
                                            $payLabel = 'Without Pay';
                                            $payClass = 'text-amber-700 bg-amber-50 border border-amber-200';
                                        }

                                        // Locked = pending and not endorsed by dean
                                        $locked = ($l['status'] === 'pending' && empty($l['dean_approved_at']));

                                        // Search haystack — name + position + dept
                                        $searchHaystack = strtolower(
                                            $fullName . ' ' .
                                            ($l['position'] ?? '') . ' ' .
                                            ($l['sub_category'] ?? '')
                                        );
                                        ?>
                                        <tr class="hover:bg-[#F1FDF6]/40 transition h-12"
                                            data-name="<?= htmlspecialchars($searchHaystack) ?>"
                                            data-status="<?= $l['status'] ?>"
                                            data-type="<?= $l['leave_type'] ?>"
                                            data-position="<?= htmlspecialchars($l['position'] ?? '') ?>">
                                            <td class="px-4 py-0">
                                                <div class="flex items-center gap-2.5">
                                                    <div
                                                        class="w-7 h-7 rounded-full bg-[#0F5E3D] text-white flex items-center justify-center font-semibold text-xs shrink-0">
                                                        <?= $initials ?>
                                                    </div>
                                                    <div class="min-w-0">
                                                        <p class="text-sm font-medium text-[#2C3E50] truncate">
                                                            <?= htmlspecialchars($fullName) ?>
                                                        </p>
                                                        <p class="text-xs text-[#2C3E50]/50 truncate">
                                                            <?= htmlspecialchars($l['position'] ?? $l['sub_category'] ?? '') ?>
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-0">
                                                <span
                                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= $leaveClass ?>">
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
                                                <span
                                                    class="text-sm text-[#2C3E50]/70"><?= rtrim(rtrim(number_format((float) $l['days_count'], 2, '.', ''), '0'), '.') ?></span>
                                            </td>
                                            <td class="px-4 py-0">
                                                <span
                                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium capitalize <?= $statusClass ?>">
                                                    <?= htmlspecialchars($l['status']) ?>
                                                </span>
                                                <?php if ($l['status'] === 'pending' && empty($l['dean_approved_at'])): ?>
                                                    <p class="text-[9px] text-amber-600 mt-0.5">Waiting for Dean</p>
                                                <?php elseif ($l['status'] === 'pending' && !empty($l['dean_approved_at'])): ?>
                                                    <p class="text-[9px] text-[#0F5E3D] mt-0.5">Endorsed by Dean</p>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-0">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium <?= $payClass ?>">
                                                    <?= $payLabel ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-0">
                                                <div class="flex items-center justify-end gap-1">
                                                    <button <?= $locked ? 'disabled' : 'onclick="openReview(' . (int) $l['id'] . ')"' ?>
                                                        class="w-8 h-8 rounded-lg flex items-center justify-center transition <?= $locked ? 'opacity-30 cursor-not-allowed text-[#2C3E50]/60' : 'text-[#2C3E50]/60 hover:bg-[#F1FDF6] hover:text-[#0F5E3D]' ?>"
                                                        title="<?= $locked ? 'Waiting for Dean endorsement' : 'Review request' ?>">
                                                        <i data-lucide="eye" class="w-4 h-4"></i>
                                                    </button>
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
                                if (empty($fullName))
                                    $fullName = 'Unknown';
                                $initials = strtoupper(substr($l['first_name'] ?? 'U', 0, 1) . substr($l['last_name'] ?? '', 0, 1));
                                $leaveClass = $leaveStyles[$l['leave_type']] ?? 'text-slate-700 bg-slate-100';
                                $statusClass = $statusStyles[$l['status']] ?? 'text-slate-700 bg-slate-100';
                                $leaveLabel = $leaveLabels[$l['leave_type']] ?? ucfirst($l['leave_type']);

                                // Pay Status logic
                                $payStatus = $l['pay_status'] ?? null;
                                $payLabel = '—';
                                $payClass = 'text-slate-400';
                                
                                if ($payStatus === 'with_pay') {
                                    $payLabel = 'With Pay';
                                    $payClass = 'text-[#0F5E3D]';
                                } elseif ($payStatus === 'without_pay') {
                                    $payLabel = 'Without Pay';
                                    $payClass = 'text-amber-700';
                                }

                                $locked = ($l['status'] === 'pending' && empty($l['dean_approved_at']));

                                $searchHaystack = strtolower(
                                    $fullName . ' ' .
                                    ($l['position'] ?? '') . ' ' .
                                    ($l['sub_category'] ?? '')
                                );
                                ?>
                                <div class="p-4 hover:bg-[#F1FDF6]/40 transition"
                                    data-name="<?= htmlspecialchars($searchHaystack) ?>"
                                    data-status="<?= $l['status'] ?>"
                                    data-type="<?= $l['leave_type'] ?>"
                                    data-position="<?= htmlspecialchars($l['position'] ?? '') ?>">
                                    <div class="flex items-start gap-3">
                                        <div
                                            class="w-9 h-9 rounded-full bg-[#0F5E3D] text-white flex items-center justify-center font-semibold text-xs shrink-0">
                                            <?= $initials ?>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center justify-between gap-2 mb-1">
                                                <p class="text-sm font-medium text-[#2C3E50] truncate">
                                                    <?= htmlspecialchars($fullName) ?>
                                                </p>
                                                <span
                                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium capitalize <?= $statusClass ?>">
                                                    <?= htmlspecialchars($l['status']) ?>
                                                </span>
                                            </div>
                                            <?php if (!empty($l['position'])): ?>
                                                <p class="text-[10px] text-[#2C3E50]/50 truncate mb-1">
                                                    <?= htmlspecialchars($l['position']) ?>
                                                </p>
                                            <?php endif; ?>
                                            <div class="flex items-center gap-2 mb-1 flex-wrap">
                                                <span
                                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= $leaveClass ?>">
                                                    <?= htmlspecialchars($leaveLabel) ?>
                                                </span>
                                                <span class="text-sm text-[#2C3E50]/60">
                                                    <?= date('M j', strtotime($l['start_date'])) ?>–<?= date('M j', strtotime($l['end_date'])) ?>
                                                </span>
                                            </div>
                                            <?php if ($l['status'] === 'pending' && empty($l['dean_approved_at'])): ?>
                                                <p class="text-[10px] text-amber-600 font-medium mb-2">Needs Dean Endorsement</p>
                                            <?php elseif ($l['status'] === 'pending' && !empty($l['dean_approved_at'])): ?>
                                                <p class="text-[10px] text-[#0F5E3D] font-medium mb-2">Endorsed by Dean</p>
                                            <?php endif; ?>
                                            <div class="flex items-center gap-3">
                                                <p class="text-xs text-[#2C3E50]/50">
                                                    <?= rtrim(rtrim(number_format((float) $l['days_count'], 2, '.', ''), '0'), '.') ?>
                                                    days
                                                </p>
                                                <?php if ($payStatus): ?>
                                                    <p class="text-xs font-medium <?= $payClass ?>">
                                                        • <?= $payLabel ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex items-center gap-2 mt-3">
                                                <button <?= $locked ? 'disabled' : 'onclick="openReview(' . (int) $l['id'] . ')"' ?>
                                                    class="w-full text-sm py-2 rounded-lg border border-[#E0E0E0] text-[#2C3E50] transition <?= $locked ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-50' ?>">
                                                    Review
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                    <?php endif; ?>

                    <div id="emptyState" class="hidden p-12 text-center">
                        <div
                            class="w-12 h-12 rounded-full bg-[#F1FDF6] flex items-center justify-center mx-auto mb-3 text-[#0F5E3D]">
                            <i data-lucide="search-x" class="w-5 h-5"></i>
                        </div>
                        <p class="text-sm font-medium text-[#2C3E50]">No leaves found</p>
                        <p class="text-sm text-[#2C3E50]/50 mt-1">Try adjusting your search or filters.</p>
                    </div>

                    <div class="px-4 sm:px-6 py-3 border-t border-[#E0E0E0]">
                        <p class="text-sm text-[#2C3E50]/50">
                            Showing <span class="font-medium text-[#2C3E50]"><?= count($leaves) ?></span> recent
                            requests
                        </p>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- Review Modal — sidebar + main, formal        -->
    <!-- ============================================ -->
    <div id="reviewModal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div id="reviewCard"
            class="bg-white rounded-xl shadow-2xl w-full max-w-5xl h-[80vh] transform transition-all duration-200 scale-95 overflow-hidden flex flex-col">

            <!-- Top bar -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-[#E0E0E0] shrink-0 bg-white">
                <div>
                    <h3 class="text-base font-semibold text-[#2C3E50]">Review Leave Request</h3>
                    <p class="text-sm text-[#2C3E50]/50" id="rvRequestId">—</p>
                </div>
                <button onclick="closeReview()"
                    class="w-9 h-9 rounded-lg flex items-center justify-center text-[#2C3E50]/50 hover:bg-[#F1FDF6] hover:text-[#2C3E50] transition">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <!-- Loading -->
            <div id="rvLoading" class="p-16 text-center">
                <svg class="animate-spin h-8 w-8 text-[#0F5E3D] mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none"
                    viewBox="0 0 24 24">
                    <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                    <path class="opacity-90" fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                    </path>
                </svg>
                <p class="text-sm text-[#2C3E50]/60 mt-3">Loading request details…</p>
            </div>

            <!-- Content -->
            <div id="rvContent" class="hidden flex-1 flex min-h-0">

                <!-- SIDEBAR -->
                <aside class="w-64 border-r border-[#E0E0E0] bg-[#F9FAFB] flex flex-col shrink-0 overflow-y-auto">

                    <div class="p-6">
                        <div id="rvInitials"
                            class="w-16 h-16 rounded-full bg-[#0F5E3D] text-white flex items-center justify-center font-semibold text-xl mb-4">
                            —</div>
                        <p id="rvName" class="text-base font-semibold text-[#2C3E50] leading-tight">—</p>
                        <p id="rvPosition" class="text-sm text-[#2C3E50]/60 mt-1 leading-snug">—</p>
                        <p id="rvDepartment" class="text-sm text-[#2C3E50]/60 leading-snug">—</p>
                    </div>

                    <div class="border-t border-[#E0E0E0]"></div>

                    <div class="p-6">
                        <p class="text-xs uppercase tracking-wider text-[#2C3E50]/50 font-semibold mb-1">Credits</p>
                        <p class="text-base font-semibold text-[#2C3E50] mb-4">
                            <span id="rvCreditTotal">—</span>
                        </p>

                        <div id="rvCredits" class="space-y-4">
                            <!-- populated by JS -->
                        </div>
                    </div>

                </aside>

                <!-- MAIN -->
                <section class="flex-1 flex flex-col min-h-0 bg-white">

                    <!-- Scrollable body -->
                    <div class="flex-1 overflow-y-auto px-8 py-6">

                        <!-- Header: 2-column layout (left = type/status/dates, right = reason) -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 pb-5 border-b border-[#F0F0F0]">

                            <!-- LEFT: type + status + dates -->
                            <div>
                                <div class="flex items-center gap-2 flex-wrap mb-3">
                                    <span id="rvType"
                                        class="inline-flex items-center px-2.5 py-1 rounded-md text-sm font-medium text-slate-700 bg-slate-100">—</span>
                                    <span id="rvStatus"
                                        class="inline-flex items-center px-2.5 py-1 rounded-md text-sm font-medium text-slate-700 bg-slate-100">—</span>
                                    <span id="rvPayBadge"
                                        class="hidden inline-flex items-center px-2.5 py-1 rounded-md text-sm font-medium">—</span>
                                </div>
                                <p class="text-base text-[#2C3E50]">
                                    <span id="rvDates" class="font-medium">—</span>
                                    <span class="text-[#2C3E50]/30 mx-2">·</span>
                                    <span id="rvDays" class="text-[#2C3E50]/70">—</span>
                                </p>
                                <p class="text-sm text-[#2C3E50]/50 mt-1">
                                    Filed <span id="rvFiled">—</span>
                                </p>
                            </div>

                            <!-- RIGHT: reason -->
                            <div>
                                <p class="text-xs uppercase tracking-wider text-[#2C3E50]/50 font-semibold mb-2">Reason
                                </p>
                                <p id="rvReason" class="text-sm text-[#2C3E50] leading-relaxed whitespace-pre-wrap">—
                                </p>
                            </div>

                        </div>

                        <!-- Dean Endorsement (if pending) -->
                        <div id="rvDeanSection" class="hidden py-5 border-b border-[#F0F0F0]">
                            <p class="text-xs uppercase tracking-wider text-[#2C3E50]/50 font-semibold mb-3">Dean Endorsement</p>
                            <p id="rvDeanSummary" class="text-sm text-[#2C3E50] font-medium">—</p>
                        </div>

                        <!-- Decision (approved/rejected only) -->
                        <div id="rvDecisionSection" class="hidden py-5 border-b border-[#F0F0F0]">
                            <p class="text-xs uppercase tracking-wider text-[#2C3E50]/50 font-semibold mb-3">Decision
                            </p>
                            <p class="text-sm text-[#2C3E50]">
                                <span id="rvDecisionSummary" class="font-medium">—</span>
                            </p>
                            <div id="rvRemarksWrap" class="mt-3 pl-4 border-l-2 border-[#E0E0E0]">
                                <p id="rvRemarks" class="text-sm text-[#2C3E50]/70 italic leading-relaxed">—</p>
                            </div>
                        </div>

                                                <!-- Attachment -->
                        <div id="rvAttachmentWrap" class="hidden py-5 border-b border-[#F0F0F0]">
                            <p class="text-xs uppercase tracking-wider text-[#2C3E50]/50 font-semibold mb-2">Attachment</p>
                            <a id="rvAttachmentLink" href="#" target="_blank" rel="noopener"
                                class="flex items-center gap-2 p-2 bg-white border border-[#E0E0E0] rounded-lg hover:border-[#0F5E3D]/40 hover:bg-[#F1FDF6]/40 transition">
                                <div id="rvAttachmentThumb" class="w-10 h-10 rounded-md bg-[#F1FDF6] flex items-center justify-center text-[#0F5E3D] shrink-0 overflow-hidden">
                                    <i data-lucide="file-text" class="w-4 h-4"></i>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p id="rvAttachmentName" class="text-xs font-medium text-[#2C3E50] truncate">Attachment</p>
                                    <p id="rvAttachmentSize" class="text-[10px] text-[#2C3E50]/50"></p>
                                </div>
                                <i data-lucide="external-link" class="w-3.5 h-3.5 text-[#2C3E50]/40 shrink-0"></i>
                            </a>
                        </div>

                        <!-- Leave History -->
                        <div class="pt-5">

                            <!-- Header + filters -->
                            <div class="flex items-center justify-between gap-3 mb-3">
                                <p class="text-xs uppercase tracking-wider text-[#2C3E50]/50 font-semibold">Leave
                                    History</p>
                                <div class="flex items-center gap-2">

                                    <!-- Status filter — custom -->
                                    <div class="relative">
                                        <select id="rvHistoryStatus" onchange="rvRenderHistory(1)"
                                            class="appearance-none pl-3 pr-8 py-1.5 rounded-md text-sm font-medium text-[#2C3E50] bg-white border border-[#E0E0E0] focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition cursor-pointer">
                                            <option value="">All Status</option>
                                            <option value="pending">Pending</option>
                                            <option value="approved">Approved</option>
                                            <option value="rejected">Rejected</option>
                                            <option value="cancelled">Cancelled</option>
                                        </select>
                                        <i data-lucide="chevron-down"
                                            class="absolute right-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-[#2C3E50]/40 pointer-events-none"></i>
                                    </div>

                                    <!-- Type filter — custom, 3 options only -->
                                    <div class="relative">
                                        <select id="rvHistoryType" onchange="rvRenderHistory(1)"
                                            class="appearance-none pl-3 pr-8 py-1.5 rounded-md text-sm font-medium text-[#2C3E50] bg-white border border-[#E0E0E0] focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition cursor-pointer">
                                            <option value="">All Types</option>
                                            <option value="sick">Sick</option>
                                            <option value="mandatory">Mandatory / Vacation</option>
                                            <option value="others">Others</option>
                                        </select>
                                        <i data-lucide="chevron-down"
                                            class="absolute right-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-[#2C3E50]/40 pointer-events-none"></i>
                                    </div>

                                </div>
                            </div>

                            <!-- Table-style history -->
                            <div class="border border-[#E0E0E0] rounded-lg overflow-hidden">
                                <table class="w-full">
                                    <thead class="bg-[#F9FAFB] border-b border-[#E0E0E0]">
                                        <tr>
                                            <th class="text-left text-xs font-medium text-[#2C3E50]/50 px-4 py-2">Type
                                            </th>
                                            <th class="text-left text-xs font-medium text-[#2C3E50]/50 px-4 py-2">Dates
                                            </th>
                                            <th class="text-left text-xs font-medium text-[#2C3E50]/50 px-4 py-2 w-20">
                                                Days</th>
                                            <th class="text-left text-xs font-medium text-[#2C3E50]/50 px-4 py-2 w-28">
                                                Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="rvHistoryList" class="divide-y divide-[#F0F0F0]">
                                        <!-- populated by JS -->
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <div id="rvHistoryPagination" class="flex items-center justify-between pt-3">
                                <p id="rvHistoryInfo" class="text-sm text-[#2C3E50]/60"></p>
                                <div class="flex items-center gap-1">
                                    <button type="button" id="rvHistoryPrev" onclick="rvHistoryPrev()"
                                        class="w-8 h-8 rounded-md flex items-center justify-center text-[#2C3E50]/70 hover:bg-[#F1FDF6] hover:text-[#0F5E3D] border border-[#E0E0E0] transition disabled:opacity-30 disabled:cursor-not-allowed">
                                        <i data-lucide="chevron-left" class="w-4 h-4"></i>
                                    </button>
                                    <span id="rvHistoryPageNum" class="text-sm font-medium text-[#2C3E50] px-3">1</span>
                                    <button type="button" id="rvHistoryNext" onclick="rvHistoryNext()"
                                        class="w-8 h-8 rounded-md flex items-center justify-center text-[#2C3E50]/70 hover:bg-[#F1FDF6] hover:text-[#0F5E3D] border border-[#E0E0E0] transition disabled:opacity-30 disabled:cursor-not-allowed">
                                        <i data-lucide="chevron-right" class="w-4 h-4"></i>
                                    </button>
                                </div>
                            </div>

                        </div>

                    </div>

                    <!-- Decision bar (pinned) -->
                    <div id="rvDecideBar" class="hidden border-t border-[#E0E0E0] bg-white px-8 py-4 shrink-0">
                        <div class="flex items-center gap-3">
                            <input type="text" id="rvRemarksInput"
                                class="flex-1 min-w-0 px-3 py-2 border border-[#E0E0E0] rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition bg-white"
                                placeholder="Add remarks (optional)…">

                            <div class="flex gap-2 shrink-0">
                                <button type="button" onclick="rvSubmitDecision('rejected')"
                                    class="px-4 border border-red-200 text-red-700 hover:bg-red-50 font-medium py-2 rounded-md transition text-sm whitespace-nowrap">
                                    Reject
                                </button>
                                <button type="button" onclick="rvSubmitDecision('approved')"
                                    class="px-4 bg-[#0F5E3D] hover:bg-[#0a4a2f] text-white font-medium py-2 rounded-md transition text-sm whitespace-nowrap">
                                    Approve
                                </button>
                            </div>
                        </div>
                    </div>

                </section>

            </div>
        </div>
    </div>

    <!-- Loading overlay -->
    <div id="loadingOverlay"
        class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-xs p-8 text-center">
            <div class="flex items-center justify-center mb-4">
                <svg class="animate-spin h-10 w-10 text-[#0F5E3D]" xmlns="http://www.w3.org/2000/svg" fill="none"
                    viewBox="0 0 24 24">
                    <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                    <path class="opacity-90" fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                    </path>
                </svg>
            </div>
            <h3 class="text-base font-bold text-[#2C3E50] mb-1" id="loadingTitle">Working...</h3>
            <p class="text-sm text-[#2C3E50]/60" id="loadingMessage">Please wait.</p>
        </div>
    </div>

    

    <!-- Error modal -->
    <div id="errorModal"
        class="fixed inset-0 z-[80] hidden items-center justify-center bg-black/40 backdrop-blur-sm p-4">
        <div id="errorCard"
            class="bg-white rounded-xl shadow-xl w-full max-w-sm p-8 text-center transform transition-all duration-200 scale-95">
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
    <div id="successModal"
        class="fixed inset-0 z-[70] hidden items-center justify-center bg-black/40 backdrop-blur-sm p-4">
        <div id="successCard"
            class="bg-white rounded-xl shadow-xl w-full max-w-sm p-8 text-center transform transition-all duration-200 scale-95">
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
    <script>
        // Pass leaves to JS for the review modal history
                rvAllLeaves = <?= json_encode(array_map(function ($l) {
            return [
                'id' => (int) $l['id'],
                'employee_id' => (int) ($l['employee_id'] ?? 0),
                'name' => trim(($l['first_name'] ?? '') . ' ' . ($l['last_name'] ?? '')),
                'status' => $l['status'],
                'type' => $l['leave_type'],
                'start' => $l['start_date'],
                'end' => $l['end_date'],
                'days' => (float) $l['days_count'],
                'pay_status' => $l['pay_status'] ?? null,
                'dean_approved' => $l['dean_approved_at'] ?? null,
                'attachment' => !empty($l['attachment_path']) ? [
                    'path' => $l['attachment_path'],
                    'name' => $l['attachment_name'],
                    'size' => (int) ($l['attachment_size'] ?? 0),
                    'mime' => $l['attachment_mime'],
                ] : null,
            ];
        }, $leaves)) ?>;

        // ============================================
        // Filter — status / type / position / search
        // Overrides leaves.js's filterLeaves so this page is self-contained.
        // Works on both desktop <tr> rows and mobile <div> cards.
        // ============================================
        function filterLeaves() {
            const status   = (document.getElementById('statusFilter')?.value   || '').trim();
            const type     = (document.getElementById('typeFilter')?.value     || '').trim();
            const position = (document.getElementById('positionFilter')?.value || '').trim();
            const search   = (document.getElementById('searchInput')?.value    || '').toLowerCase().trim();

            // Rows carry data-name, data-status, data-type, data-position
            const rows = document.querySelectorAll('[data-name][data-status][data-type]');

            let visible = 0;

            rows.forEach((row) => {
                const rName     = row.dataset.name     || '';
                const rStatus   = row.dataset.status   || '';
                const rType     = row.dataset.type     || '';
                const rPosition = row.dataset.position || '';

                const matchStatus   = !status   || rStatus === status;
                const matchType     = !type     || rType   === type;
                const matchPosition = !position || rPosition === position;
                const matchSearch   = !search   || rName.includes(search);

                const show = matchStatus && matchType && matchPosition && matchSearch;

                row.classList.toggle('hidden', !show);
                if (show) visible++;
            });

            // Toggle "no results" empty state
            document.getElementById('emptyState')?.classList.toggle('hidden', visible > 0);

            // Live update the "Showing X of Y" footer count
            const footer = document.querySelector('main .px-4.sm\\:px-6.py-3.border-t p');
            if (footer) {
                const total = rows.length;
                footer.innerHTML = `Showing <span class="font-medium text-[#2C3E50]">${visible}</span> of <span class="font-medium text-[#2C3E50]">${total}</span> requests`;
            }
        }

        // Clear the search input + reapply filters
        function clearSearch() {
            const s = document.getElementById('searchInput');
            if (s) {
                s.value = '';
                filterLeaves();
                s.focus();
            }
        }

        // Keyboard shortcut: "/" focuses the search box
        document.addEventListener('keydown', (e) => {
            const tag = (document.activeElement?.tagName || '').toLowerCase();
            if (e.key === '/' && tag !== 'input' && tag !== 'textarea' && tag !== 'select') {
                e.preventDefault();
                document.getElementById('searchInput')?.focus();
            }
        });
    </script>
</body>

</html>