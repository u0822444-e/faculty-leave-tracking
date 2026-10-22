<?php
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

require_once __DIR__ . '/../../config/database.php';

$userId  = (int) $_SESSION['user_id'];
$role    = $_SESSION['role'] ?? 'staff';
$isAdmin = ($role === 'hr');

// ============================================
// Action filter options — role-aware
// ============================================
$actionOptions = [
    'admin' => [
        'create_user'     => 'Created User',
        'update_user'     => 'Updated User',
        'archive_user'    => 'Archived User',
        'restore_user'    => 'Restored User',
        'send_reset'      => 'Sent Reset',
        'leave_filed'     => 'Leave Filed',
        'leave_decided'   => 'Leave Decided',
        'leave_cancelled' => 'Leave Cancelled',
        'login'           => 'Login',
        'logout'          => 'Logout',
    ],
    'hr' => [
        'leave_decided'   => 'Leave Decided',
        'send_reset'      => 'Sent Reset',
        'leave_filed'     => 'Leave Filed',
        'leave_cancelled' => 'Leave Cancelled',
        'login'           => 'Login',
        'logout'          => 'Logout',
    ],
    'user' => [
        'leave_filed'     => 'Leave Filed',
        'leave_cancelled' => 'Leave Cancelled',
        'login'           => 'Login',
        'logout'          => 'Logout',
    ],
];

if ($isAdmin) {
    $filterActions = $actionOptions['admin'];
} elseif ($role === 'hr') {
    $filterActions = $actionOptions['hr'];
} else {
    $filterActions = $actionOptions['user'];
}

// ============================================
// Active action filter (from URL)
// ============================================
$actionFilter = trim($_GET['action'] ?? '');

// Whitelist — reject anything not in the allowed set
if ($actionFilter !== '' && !array_key_exists($actionFilter, $filterActions)) {
    $actionFilter = '';
}

// ============================================
// Pagination
// ============================================
$perPage = 10;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

// ============================================
// Build WHERE clause (base + action filter)
// ============================================
$whereClauses = [];
$params       = [];

if (!$isAdmin) {
    $whereClauses[] = "user_id = ?";
    $params[]       = $userId;
}

if ($actionFilter !== '') {
    $whereClauses[] = "action = ?";
    $params[]       = $actionFilter;
}

$whereSql = $whereClauses ? ('WHERE ' . implode(' AND ', $whereClauses)) : '';

// ============================================
// Total count for pagination
// ============================================
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs {$whereSql}");
$countStmt->execute($params);
$totalLogs = (int) $countStmt->fetchColumn();

$totalPages = max(1, (int) ceil($totalLogs / $perPage));

if ($page > $totalPages) {
    $page   = $totalPages;
    $offset = ($page - 1) * $perPage;
}

// ============================================
// Fetch current page
// ============================================
$sql = "SELECT * FROM activity_logs {$whereSql} ORDER BY created_at DESC LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);

$bindIndex = 1;
foreach ($params as $p) {
    $stmt->bindValue($bindIndex++, $p);
}
$stmt->bindValue($bindIndex++, $perPage, PDO::PARAM_INT);
$stmt->bindValue($bindIndex++, $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

// Action badge styles (covers all known actions)
$actionStyles = [
    'create_user'     => 'text-[#0F5E3D] bg-[#F1FDF6]',
    'update_user'     => 'text-blue-600 bg-blue-50',
    'archive_user'    => 'text-amber-600 bg-amber-50',
    'restore_user'    => 'text-[#0F5E3D] bg-[#F1FDF6]',
    'send_reset'      => 'text-purple-600 bg-purple-50',
    'leave_filed'     => 'text-indigo-600 bg-indigo-50',
    'leave_decided'   => 'text-emerald-600 bg-emerald-50',
    'leave_cancelled' => 'text-slate-600 bg-slate-100',
    'login'           => 'text-emerald-600 bg-emerald-50',
    'logout'          => 'text-slate-600 bg-slate-100',
];

$actionLabels = [
    'create_user'     => 'Created User',
    'update_user'     => 'Updated User',
    'archive_user'    => 'Archived User',
    'restore_user'    => 'Restored User',
    'send_reset'      => 'Sent Reset',
    'leave_filed'     => 'Leave Filed',
    'leave_decided'   => 'Leave Decided',
    'leave_cancelled' => 'Leave Cancelled',
    'login'           => 'Login',
    'logout'          => 'Logout',
];

$pageTitle    = $isAdmin ? 'Activity Logs' : 'My Activity';
$pageSubtitle = $isAdmin ? 'All system activity' : 'Your recent account activity';
$activePage   = 'activity-logs';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | DAMMC</title>
    <link rel="icon" type="image/png" href="/assets/images/dammc-logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>

<body class="bg-gradient-to-br from-[#F1FDF6] via-[#eafaf1] to-[#dcf3e5] min-h-screen">

    <div class="flex min-h-screen">

        <div id="sidebarOverlay" onclick="closeSidebar()"
            class="fixed inset-0 bg-black/40 z-40 hidden md:hidden backdrop-blur-sm"></div>

        <?php require __DIR__ . '/../components/sidebar.php'; ?>

        <div class="flex-1 md:ml-64 w-full min-w-0">

            <?php
            $pageIcon = 'history';
            require __DIR__ . '/../components/topbar.php';
            ?>

            <main class="p-4 sm:p-6 lg:p-8 space-y-4 sm:space-y-6">

                <!-- Toolbar -->
                <div class="animate-fade-in-up bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-3 sm:p-4 shadow-sm">
                    <div class="flex flex-col lg:flex-row gap-3 lg:items-center lg:justify-between">

                        <div class="flex flex-wrap items-center gap-2">

                            <!-- Action filter — role-aware, server-side -->
                            <select id="actionFilter"
                                onchange="window.location.href = this.value ? '?action=' + encodeURIComponent(this.value) : '<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>'"
                                class="px-3 py-1.5 rounded-lg text-xs font-medium text-[#2C3E50]/80 bg-[#F1FDF6] border border-[#E0E0E0] focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition cursor-pointer">
                                <option value="">All Actions</option>
                                <?php foreach ($filterActions as $value => $label): ?>
                                    <option value="<?= htmlspecialchars($value) ?>" <?= $actionFilter === $value ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <button onclick="filterToday(event)"
                                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-[#2C3E50]/70 hover:text-[#2C3E50] hover:bg-[#F1FDF6] transition">
                                <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
                                <span>Today</span>
                            </button>
                        </div>

                        <div class="flex items-center gap-2">
                            <div class="relative flex-1 sm:w-72">
                                <i data-lucide="search"
                                    class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[#2C3E50]/40 pointer-events-none"></i>
                                <input type="text" id="searchInput" oninput="filterLogs()" placeholder="Search logs"
                                    class="w-full pl-10 pr-10 py-2 bg-[#F1FDF6]/60 border border-transparent rounded-lg text-sm text-[#2C3E50] placeholder:text-[#2C3E50]/40 focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:bg-white focus:border-transparent transition">
                                <kbd
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-mono text-[#2C3E50]/40 bg-white px-1.5 py-0.5 rounded border border-[#E0E0E0]">/</kbd>
                            </div>
                        </div>

                    </div>

                    <?php if (!$isAdmin): ?>
                        <p class="mt-3 text-[11px] text-[#2C3E50]/50 flex items-center gap-1.5">
                            <i data-lucide="info" class="w-3.5 h-3.5"></i>
                            You are viewing your own activity. Contact an admin for the full system log.
                        </p>
                    <?php endif; ?>
                </div>

                <!-- Logs list -->
                <div class="animate-fade-in-up-delay-1 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl shadow-sm overflow-hidden">

                    <div id="logsList" class="divide-y divide-[#E0E0E0]">
                        <?php if (empty($logs)): ?>
                            <div class="p-12 text-center">
                                <div class="w-12 h-12 rounded-full bg-[#F1FDF6] flex items-center justify-center mx-auto mb-3 text-[#0F5E3D]">
                                    <i data-lucide="history" class="w-5 h-5"></i>
                                </div>
                                <p class="text-sm font-medium text-[#2C3E50]">No activity yet</p>
                                <p class="text-xs text-[#2C3E50]/50 mt-1">
                                    <?= $actionFilter !== '' ? 'No logs for this action filter.' : 'System actions will appear here.' ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($logs as $log):
                                $actionClass = $actionStyles[$log['action']] ?? 'text-slate-600 bg-slate-100';
                                $actionLabel = $actionLabels[$log['action']] ?? ucfirst(str_replace('_', ' ', $log['action']));
                                $initial = strtoupper(substr($log['username'] ?? 'S', 0, 1));
                                $ts = strtotime($log['created_at']);
                                $timeAgo = time() - $ts;

                                if ($timeAgo < 60)         $timeLabel = 'Just now';
                                elseif ($timeAgo < 3600)   $timeLabel = floor($timeAgo / 60) . 'm ago';
                                elseif ($timeAgo < 86400)  $timeLabel = floor($timeAgo / 3600) . 'h ago';
                                elseif ($timeAgo < 604800) $timeLabel = floor($timeAgo / 86400) . 'd ago';
                                else                       $timeLabel = date('M j, Y', $ts);
                                ?>
                                <div class="log-row px-4 py-2.5 hover:bg-[#F1FDF6]/40 transition"
                                    data-action="<?= htmlspecialchars($log['action']) ?>"
                                    data-search="<?= strtolower(htmlspecialchars(($log['username'] ?? '') . ' ' . ($log['description'] ?? '') . ' ' . $actionLabel)) ?>"
                                    data-date="<?= date('Y-m-d', $ts) ?>">

                                    <div class="flex items-center gap-3">
                                        <div class="w-7 h-7 rounded-full bg-[#0F5E3D] text-white flex items-center justify-center font-semibold text-[10px] shrink-0">
                                            <?= $initial ?>
                                        </div>

                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="text-sm font-medium text-[#2C3E50]">
                                                    <?= htmlspecialchars($log['username'] ?? 'System') ?>
                                                </span>
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-medium <?= $actionClass ?>">
                                                    <?= $actionLabel ?>
                                                </span>
                                            </div>
                                            <p class="text-sm text-[#2C3E50]/60 truncate mt-0.5">
                                                <?= htmlspecialchars($log['description'] ?? '—') ?>
                                            </p>
                                            <p class="text-[10px] text-[#2C3E50]/40 mt-0.5 sm:hidden">
                                                <?= $timeLabel ?>
                                            </p>
                                        </div>

                                        <div class="hidden sm:flex items-center gap-3 text-[11px] text-[#2C3E50]/40 shrink-0">
                                            <?php if (!empty($log['ip_address'])): ?>
                                                <span class="flex items-center gap-1">
                                                    <i data-lucide="globe" class="w-3 h-3"></i>
                                                    <?= htmlspecialchars($log['ip_address']) ?>
                                                </span>
                                            <?php endif; ?>
                                            <span class="flex items-center gap-1">
                                                <i data-lucide="clock" class="w-3 h-3"></i>
                                                <?= $timeLabel ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div id="emptyState" class="hidden p-12 text-center">
                        <div class="w-12 h-12 rounded-full bg-[#F1FDF6] flex items-center justify-center mx-auto mb-3 text-[#0F5E3D]">
                            <i data-lucide="search-x" class="w-5 h-5"></i>
                        </div>
                        <p class="text-sm font-medium text-[#2C3E50]">No logs found</p>
                        <p class="text-xs text-[#2C3E50]/50 mt-1">Try adjusting your search or filters.</p>
                    </div>

                    <!-- Pagination -->
                    <div class="px-4 sm:px-6 py-3 border-t border-[#E0E0E0] flex flex-col sm:flex-row items-center justify-between gap-3">
                        <p class="text-xs text-[#2C3E50]/50">
                            Showing
                            <span class="font-medium text-[#2C3E50]"><?= count($logs) ?></span>
                            of
                            <span class="font-medium text-[#2C3E50]"><?= $totalLogs ?></span>
                            activities
                            <?php if ($totalPages > 1): ?>
                                · Page <span class="font-medium text-[#2C3E50]"><?= $page ?></span> of <span class="font-medium text-[#2C3E50]"><?= $totalPages ?></span>
                            <?php endif; ?>
                        </p>

                        <?php
                        // Preserve action filter when paginating
                        $pageQS = $actionFilter !== '' ? '&action=' . urlencode($actionFilter) : '';
                        ?>

                        <?php if ($totalPages > 1): ?>
                            <div class="flex items-center gap-1">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?= $page - 1 ?><?= $pageQS ?>"
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
                                        <a href="?page=<?= $i ?><?= $pageQS ?>"
                                            class="w-8 h-8 rounded-lg flex items-center justify-center text-[#2C3E50]/70 hover:bg-[#F1FDF6] hover:text-[#0F5E3D] transition text-xs font-medium">
                                            <?= $i ?>
                                        </a>
                                    <?php endif; ?>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <a href="?page=<?= $page + 1 ?><?= $pageQS ?>"
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

    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="/assets/js/app.js"></script>
    <script src="/assets/js/notifications.js"></script>
    <script src="/assets/js/activity-logs.js"></script>
    <script src="/assets/js/profile.js"></script>
</body>

</html>