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
        e.leave_credits, e.leave_pay, u.created_at
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

// ── Total Leave Pay = rate × credits ──
$leavePayRate    = (float) ($user['leave_pay'] ?? 0);
$leaveCredits    = (float) ($user['leave_credits'] ?? 0);
$totalLeavePay   = $leavePayRate * $leaveCredits;

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

// Leave Pay entries — read-only, used only for the Total Deduction sum
// and to build the With Pay / Deduction badges on each leave row.
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

// Total Deduction from existing entries
$totalLeaveDeduct = 0;
foreach ($payrolls as $p) {
    if ($p['adjustment_type'] === 'deduction') {
        $totalLeaveDeduct += (float) $p['amount'];
    }
}

// Index leave-pay entries by leave_request_id for badges on leave rows
$payByLeave = [];
foreach ($payrolls as $p) {
    if (empty($p['leave_request_id'])) continue;
    $lid = (int) $p['leave_request_id'];
    $type = $p['adjustment_type'];
    if (!isset($payByLeave[$lid])) {
        $payByLeave[$lid] = ['type' => $type, 'amount' => 0.0, 'count' => 0];
    }
    if ($type === 'credit' || ($type === 'adjustment' && $payByLeave[$lid]['type'] === 'deduction')) {
        $payByLeave[$lid]['type'] = $type;
    }
    $payByLeave[$lid]['amount'] += (float) $p['amount'];
    $payByLeave[$lid]['count']++;
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

                <div class="animate-fade-in-up">
                    <a href="/faculty" class="flex items-center gap-1.5 text-xs font-medium text-[#2C3E50]/60 hover:text-[#0F5E3D] transition">
                        <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                        <span>Back to Users</span>
                    </a>
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
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mt-4 pt-4 border-t border-[#E0E0E0]">
                        <div>
                            <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 mb-0.5">Employment</p>
                            <p class="text-sm font-semibold text-[#2C3E50] capitalize"><?= htmlspecialchars($user['employment_type'] ?? '—') ?></p>
                        </div>
                        <div>
                            <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 mb-0.5">Leave Credits</p>
                            <p class="text-sm font-semibold text-[#0F5E3D]"><?= number_format($leaveCredits) ?> days</p>
                        </div>
                        <div>
                            <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 mb-0.5">Total Leave Pay</p>
                            <p class="text-sm font-semibold text-[#0F5E3D]">₱<?= number_format($totalLeavePay, 2) ?></p>
                            <p class="text-[9px] text-[#2C3E50]/30 mt-0.5">
                                ₱<?= number_format($leavePayRate, 2) ?> × <?= number_format($leaveCredits) ?>
                            </p>
                        </div>
                        <div>
                            <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 mb-0.5">Total Deduction</p>
                            <p class="text-sm font-semibold text-rose-600">₱<?= number_format($totalLeaveDeduct, 2) ?></p>
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

                <!-- Leave History -->
                <div class="animate-fade-in-up-delay-2 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl shadow-sm overflow-hidden">

                    <div class="border-b border-[#E0E0E0] flex overflow-x-auto">
                        <div class="whitespace-nowrap px-5 py-3 text-sm font-medium text-[#0F5E3D] border-b-2 border-[#0F5E3D]">
                            Leave History
                            <span class="ml-1.5 px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-[#F1FDF6] text-[#0F5E3D]">
                                <?= count($leaves) ?>
                            </span>
                        </div>
                    </div>

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

                                $linkedPay = $payByLeave[(int) $l['id']] ?? null;
                                $payBadge = null;
                                if ($linkedPay) {
                                    if ($linkedPay['type'] === 'credit' || $linkedPay['type'] === 'adjustment') {
                                        $payBadge = [
                                            'label' => 'With Pay',
                                            'class' => 'text-[#0F5E3D] bg-[#F1FDF6]',
                                            'amount' => $linkedPay['amount'],
                                            'sign' => '+',
                                        ];
                                    } else {
                                        $payBadge = [
                                            'label' => 'Deduction',
                                            'class' => 'text-rose-600 bg-rose-50',
                                            'amount' => $linkedPay['amount'],
                                            'sign' => '−',
                                        ];
                                    }
                                }
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

                                            <?php if ($payBadge): ?>
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold <?= $payBadge['class'] ?>"
                                                    title="Linked leave-pay amount">
                                                    <i data-lucide="<?= $payBadge['sign'] === '+' ? 'plus' : 'minus' ?>" class="w-2.5 h-2.5"></i>
                                                    <?= $payBadge['label'] ?>
                                                    <span class="opacity-70">· <?= $payBadge['sign'] ?>₱<?= number_format($payBadge['amount'], 2) ?></span>
                                                </span>
                                            <?php elseif ($l['status'] === 'approved'): ?>
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium text-slate-500 bg-slate-100"
                                                    title="No leave-pay entry linked">
                                                    <i data-lucide="circle-dashed" class="w-2.5 h-2.5"></i>
                                                    Unpaid
                                                </span>
                                            <?php endif; ?>
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
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof initIcons === 'function') initIcons();
        });
    </script>
</body>

</html>