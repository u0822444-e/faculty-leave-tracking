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

$userId = (int) ($_GET['id'] ?? 0);
if ($userId <= 0) {
    header('Location: /users');
    exit;
}

// Fetch user + employee info
$stmt = $pdo->prepare("
    SELECT 
        u.id AS user_id, u.username, u.role, u.status,
        e.id AS employee_id,
        e.first_name, e.middle_name, e.last_name, e.email,
        e.category, e.sub_category, e.employment_type,
        e.basic_salary, e.leave_credits, u.created_at
    FROM users u
    LEFT JOIN employees e ON u.employee_id = e.id
    WHERE u.id = ?
    LIMIT 1
");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: /users');
    exit;
}

$employeeId = $user['employee_id'] ?? null;
$fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: $user['username'];
$initials = strtoupper(substr($user['first_name'] ?? $user['username'], 0, 1) . substr($user['last_name'] ?? '', 0, 1));

// Leave stats
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected,
        COALESCE(SUM(CASE WHEN status = 'approved' THEN days_count ELSE 0 END), 0) AS days_used
    FROM leave_requests
    WHERE employee_id = ?
");
$stmt->execute([$employeeId]);
$leaveStats = $stmt->fetch();

// Leave history (last 50)
$stmt = $pdo->prepare("
    SELECT lr.*, 
           u.username AS approver_username
    FROM leave_requests lr
    LEFT JOIN users u ON lr.approved_by = u.id
    WHERE lr.employee_id = ?
    ORDER BY lr.created_at DESC
    LIMIT 50
");
$stmt->execute([$employeeId]);
$leaves = $stmt->fetchAll();

// Payroll adjustments (last 50)
$stmt = $pdo->prepare("
    SELECT pa.*, 
           lr.leave_type, lr.start_date AS leave_start, lr.end_date AS leave_end
    FROM payroll_adjustments pa
    LEFT JOIN leave_requests lr ON pa.leave_request_id = lr.id
    WHERE pa.employee_id = ?
    ORDER BY pa.effective_date DESC, pa.created_at DESC
    LIMIT 50
");
$stmt->execute([$employeeId]);
$payrolls = $stmt->fetchAll();

// Payroll totals
$totalDeductions = 0;
$totalCredits = 0;
foreach ($payrolls as $p) {
    if ($p['adjustment_type'] === 'deduction') $totalDeductions += (float) $p['amount'];
    if ($p['adjustment_type'] === 'credit')    $totalCredits    += (float) $p['amount'];
}

// Badge styles
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
$payrollStyles = [
    'deduction'  => 'text-rose-600 bg-rose-50',
    'credit'     => 'text-[#0F5E3D] bg-[#F1FDF6]',
    'adjustment' => 'text-blue-600 bg-blue-50',
];

$roleLabels = [
    'admin' => 'Admin', 'hr' => 'HR', 'payroll' => 'Payroll',
    'faculty' => 'Faculty', 'staff' => 'Staff', 'timekeeper' => 'Timekeeper',
];
$roleClass = 'text-[#0F5E3D] bg-[#F1FDF6]';

function timeAgoShort($ts) {
    $diff = time() - strtotime($ts);
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', strtotime($ts));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($fullName) ?> | DAMMC Admin</title>
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
        $activePage = 'users';
        require __DIR__ . '/../components/sidebar.php';
        ?>

        <div class="flex-1 md:ml-64 w-full min-w-0">

            <?php
            $pageTitle = 'User Details';
            $pageSubtitle = $fullName;
            $pageIcon = 'user';
            require __DIR__ . '/../components/topbar.php';
            ?>

            <main class="p-4 sm:p-6 lg:p-8 space-y-4">

                <!-- Back + Profile header -->
                <div class="animate-fade-in-up flex items-center justify-between gap-3">

                    <a href="/users" class="flex items-center gap-1.5 text-xs font-medium text-[#2C3E50]/60 hover:text-[#0F5E3D] transition">
                        <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                        <span>Back to Users</span>
                    </a>

                    <button onclick='openEditUserModal(<?= json_encode([
                        "id" => $user["user_id"],
                        "first" => $user["first_name"] ?? "",
                        "middle" => $user["middle_name"] ?? "",
                        "last" => $user["last_name"] ?? "",
                        "email" => $user["email"] ?? "",
                        "username" => $user["username"],
                        "role" => $user["role"],
                        "category" => $user["category"] ?? "staff",
                        "sub" => $user["sub_category"] ?? "",
                        "emp" => $user["employment_type"] ?? "full-time",
                        "salary" => $user["basic_salary"] ?? 0,
                        "credits" => $user["leave_credits"] ?? 0,
                        "status" => $user["status"],
                    ]) ?>)'
                        class="flex items-center gap-1.5 bg-[#0F5E3D] hover:bg-[#0a4a2f] active:scale-[0.99] text-white font-medium py-2 px-4 rounded-lg transition text-xs shadow-sm hover:shadow-md">
                        <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                        <span>Edit User</span>
                    </button>
                </div>

                <!-- Profile card -->
                <div class="animate-fade-in-up bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl px-5 py-4 shadow-sm">
                    <div class="flex items-start gap-4 flex-wrap">
                        <div class="w-14 h-14 rounded-full bg-[#0F5E3D] text-white flex items-center justify-center font-bold text-lg shrink-0 ring-4 ring-[#F1FDF6]">
                            <?= $initials ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-1">
                                <h2 class="text-lg font-bold text-[#2C3E50]"><?= htmlspecialchars($fullName) ?></h2>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium <?= $roleClass ?>">
                                    <?= $roleLabels[$user['role']] ?? ucfirst($user['role']) ?>
                                </span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium text-slate-600 bg-slate-100 capitalize">
                                    <?= htmlspecialchars($user['category'] ?? 'staff') ?>
                                </span>
                                <?php if ($user['status'] === 'inactive'): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium text-red-600 bg-red-50">
                                        Archived
                                    </span>
                                <?php endif; ?>
                            </div>
                            <p class="text-xs text-[#2C3E50]/50">
                                @<?= htmlspecialchars($user['username']) ?>
                                <span class="mx-1 text-[#2C3E50]/30">·</span>
                                <?= htmlspecialchars($user['email'] ?? '—') ?>
                                <span class="mx-1 text-[#2C3E50]/30">·</span>
                                <?= htmlspecialchars($user['sub_category'] ?? '—') ?>
                            </p>
                        </div>
                    </div>

                    <!-- Quick facts -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-4 pt-4 border-t border-[#E0E0E0]">
                        <div>
                            <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 mb-0.5">Employment</p>
                            <p class="text-sm font-semibold text-[#2C3E50] capitalize"><?= htmlspecialchars($user['employment_type'] ?? '—') ?></p>
                        </div>
                        <div>
                            <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 mb-0.5">Basic Salary</p>
                            <p class="text-sm font-semibold text-[#2C3E50]">₱<?= number_format($user['basic_salary'] ?? 0, 2) ?></p>
                        </div>
                        <div>
                            <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 mb-0.5">Leave Credits</p>
                            <p class="text-sm font-semibold text-[#0F5E3D]"><?= number_format($user['leave_credits'] ?? 0, 2) ?> days</p>
                        </div>
                        <div>
                            <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 mb-0.5">Member Since</p>
                            <p class="text-sm font-semibold text-[#2C3E50]"><?= date('M j, Y', strtotime($user['created_at'])) ?></p>
                        </div>
                    </div>
                </div>

                <!-- Leave stats -->
                <div class="animate-fade-in-up-delay-1 grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <div class="bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-4 shadow-sm">
                        <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 mb-1">Total Leaves</p>
                        <p class="text-2xl font-bold text-[#2C3E50]"><?= (int) $leaveStats['total'] ?></p>
                    </div>
                    <div class="bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-4 shadow-sm">
                        <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 mb-1">Pending</p>
                        <p class="text-2xl font-bold text-amber-600"><?= (int) $leaveStats['pending'] ?></p>
                    </div>
                    <div class="bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-4 shadow-sm">
                        <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 mb-1">Approved</p>
                        <p class="text-2xl font-bold text-[#0F5E3D]"><?= (int) $leaveStats['approved'] ?></p>
                    </div>
                    <div class="bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-4 shadow-sm">
                        <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 mb-1">Days Used</p>
                        <p class="text-2xl font-bold text-[#2C3E50]"><?= number_format((float) $leaveStats['days_used'], 1) ?></p>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="animate-fade-in-up-delay-2 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl shadow-sm overflow-hidden">

                    <div class="border-b border-[#E0E0E0] flex overflow-x-auto">
                        <button onclick="switchUserTab('leaves')" id="tabLeaves"
                            class="user-tab whitespace-nowrap px-5 py-3 text-sm font-medium text-[#0F5E3D] border-b-2 border-[#0F5E3D] transition">
                            Leave History
                            <span class="ml-1.5 px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-[#F1FDF6] text-[#0F5E3D]">
                                <?= count($leaves) ?>
                            </span>
                        </button>
                        <button onclick="switchUserTab('payroll')" id="tabPayroll"
                            class="user-tab whitespace-nowrap px-5 py-3 text-sm font-medium text-[#2C3E50]/60 border-b-2 border-transparent hover:text-[#2C3E50] transition">
                            Payroll Adjustments
                            <span class="ml-1.5 px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-600">
                                <?= count($payrolls) ?>
                            </span>
                        </button>
                    </div>

                    <!-- Tab: Leaves -->
                    <div id="panelLeaves" class="user-panel">
                        <?php if (empty($leaves)): ?>
                            <div class="p-12 text-center">
                                <div class="w-12 h-12 rounded-full bg-[#F1FDF6] flex items-center justify-center mx-auto mb-3 text-[#0F5E3D]">
                                    <i data-lucide="calendar-x" class="w-5 h-5"></i>
                                </div>
                                <p class="text-sm font-medium text-[#2C3E50]">No leave requests yet</p>
                                <p class="text-xs text-[#2C3E50]/50 mt-1">This employee hasn't filed any leaves.</p>
                            </div>
                        <?php else: ?>
                            <div class="divide-y divide-[#E0E0E0]">
                                <?php foreach ($leaves as $l):
                                    $leaveStyle = $leaveStyles[$l['leave_type']] ?? 'text-slate-600 bg-slate-100';
                                    $statusStyle = $statusStyles[$l['status']] ?? 'text-slate-600 bg-slate-100';
                                    ?>
                                    <div class="px-4 py-3 hover:bg-[#F1FDF6]/40 transition">
                                        <div class="flex items-center justify-between gap-3 flex-wrap">
                                            <div class="flex items-center gap-2 flex-wrap min-w-0">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium capitalize <?= $leaveStyle ?>">
                                                    <?= htmlspecialchars($l['leave_type']) ?>
                                                </span>
                                                <span class="text-sm font-medium text-[#2C3E50]">
                                                    <?= date('M j', strtotime($l['start_date'])) ?>
                                                    <?= $l['start_date'] !== $l['end_date'] ? ' – ' . date('M j, Y', strtotime($l['end_date'])) : ', ' . date('Y', strtotime($l['start_date'])) ?>
                                                </span>
                                                <span class="text-xs text-[#2C3E50]/50">
                                                    (<?= number_format((float) $l['days_count'], 1) ?> day<?= $l['days_count'] == 1 ? '' : 's' ?>)
                                                </span>
                                            </div>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium capitalize <?= $statusStyle ?>">
                                                <?= htmlspecialchars($l['status']) ?>
                                            </span>
                                        </div>
                                        <?php if (!empty($l['reason'])): ?>
                                            <p class="text-xs text-[#2C3E50]/60 mt-1 truncate">
                                                <?= htmlspecialchars($l['reason']) ?>
                                            </p>
                                        <?php endif; ?>
                                        <div class="flex items-center gap-3 text-[10px] text-[#2C3E50]/40 mt-1.5">
                                            <span class="flex items-center gap-1">
                                                <i data-lucide="clock" class="w-3 h-3"></i>
                                                Filed <?= timeAgoShort($l['created_at']) ?>
                                            </span>
                                            <?php if ($l['approved_at']): ?>
                                                <span class="flex items-center gap-1">
                                                    <i data-lucide="check" class="w-3 h-3"></i>
                                                    <?= htmlspecialchars($l['approver_username'] ?? 'Admin') ?> · <?= timeAgoShort($l['approved_at']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Tab: Payroll -->
                    <div id="panelPayroll" class="user-panel hidden">

                        <!-- Payroll summary -->
                        <?php if (!empty($payrolls)): ?>
                            <div class="px-4 py-3 bg-[#F1FDF6]/40 border-b border-[#E0E0E0] grid grid-cols-2 gap-3">
                                <div>
                                    <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40">Total Deductions</p>
                                    <p class="text-lg font-bold text-rose-600">₱<?= number_format($totalDeductions, 2) ?></p>
                                </div>
                                <div>
                                    <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40">Total Credits</p>
                                    <p class="text-lg font-bold text-[#0F5E3D]">₱<?= number_format($totalCredits, 2) ?></p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (empty($payrolls)): ?>
                            <div class="p-12 text-center">
                                <div class="w-12 h-12 rounded-full bg-[#F1FDF6] flex items-center justify-center mx-auto mb-3 text-[#0F5E3D]">
                                    <i data-lucide="wallet" class="w-5 h-5"></i>
                                </div>
                                <p class="text-sm font-medium text-[#2C3E50]">No payroll adjustments yet</p>
                                <p class="text-xs text-[#2C3E50]/50 mt-1">Adjustments appear here when leaves affect payroll.</p>
                            </div>
                        <?php else: ?>
                            <div class="divide-y divide-[#E0E0E0]">
                                <?php foreach ($payrolls as $p):
                                    $payStyle = $payrollStyles[$p['adjustment_type']] ?? 'text-slate-600 bg-slate-100';
                                    $sign = $p['adjustment_type'] === 'deduction' ? '−' : ($p['adjustment_type'] === 'credit' ? '+' : '');
                                    $amountColor = $p['adjustment_type'] === 'deduction' ? 'text-rose-600' : ($p['adjustment_type'] === 'credit' ? 'text-[#0F5E3D]' : 'text-blue-600');
                                    ?>
                                    <div class="px-4 py-3 hover:bg-[#F1FDF6]/40 transition">
                                        <div class="flex items-center justify-between gap-3 flex-wrap">
                                            <div class="flex items-center gap-2 flex-wrap min-w-0">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium capitalize <?= $payStyle ?>">
                                                    <?= htmlspecialchars($p['adjustment_type']) ?>
                                                </span>
                                                <?php if (!empty($p['leave_type'])): ?>
                                                    <span class="text-xs text-[#2C3E50]/60">
                                                        Leave: <?= htmlspecialchars($p['leave_type']) ?>
                                                        (<?= date('M j', strtotime($p['leave_start'])) ?>–<?= date('M j', strtotime($p['leave_end'])) ?>)
                                                    </span>
                                                <?php endif; ?>
                                                <span class="text-xs text-[#2C3E50]/50">
                                                    Effective <?= date('M j, Y', strtotime($p['effective_date'])) ?>
                                                </span>
                                            </div>
                                            <span class="text-sm font-bold <?= $amountColor ?>">
                                                <?= $sign ?>₱<?= number_format((float) $p['amount'], 2) ?>
                                            </span>
                                        </div>
                                        <?php if (!empty($p['remarks'])): ?>
                                            <p class="text-xs text-[#2C3E50]/60 mt-1 truncate"><?= htmlspecialchars($p['remarks']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>

            </main>
        </div>
    </div>

    <!-- Logout modal -->
    <?php require_once __DIR__ . '/../components/logout_modal.php'; ?>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="/assets/js/app.js"></script>
    <script src="/assets/js/notifications.js"></script>
    <script src="/assets/js/profile.js"></script>
    <script>
        // Tab switching
        function switchUserTab(tab) {
            document.querySelectorAll('.user-tab').forEach(btn => {
                btn.className = 'user-tab whitespace-nowrap px-5 py-3 text-sm font-medium text-[#2C3E50]/60 border-b-2 border-transparent hover:text-[#2C3E50] transition';
            });
            const active = document.getElementById('tab' + tab.charAt(0).toUpperCase() + tab.slice(1));
            if (active) {
                active.className = 'user-tab whitespace-nowrap px-5 py-3 text-sm font-medium text-[#0F5E3D] border-b-2 border-[#0F5E3D] transition';
            }

            document.querySelectorAll('.user-panel').forEach(p => p.classList.add('hidden'));
            const panel = document.getElementById('panel' + tab.charAt(0).toUpperCase() + tab.slice(1));
            if (panel) panel.classList.remove('hidden');

            if (typeof initIcons === 'function') initIcons();
        }

        document.addEventListener('DOMContentLoaded', () => {
            if (typeof initIcons === 'function') initIcons();
        });
    </script>
</body>

</html>