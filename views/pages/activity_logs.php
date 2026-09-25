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

// Fetch latest 200 activity logs
$stmt = $pdo->query("
    SELECT * FROM activity_logs
    ORDER BY created_at DESC
    LIMIT 200
");
$logs = $stmt->fetchAll();

// Action badge styles
$actionStyles = [
    'create_user' => 'text-[#0F5E3D] bg-[#F1FDF6]',
    'update_user' => 'text-blue-600 bg-blue-50',
    'archive_user' => 'text-amber-600 bg-amber-50',
    'send_reset' => 'text-purple-600 bg-purple-50',
    'login' => 'text-emerald-600 bg-emerald-50',
    'logout' => 'text-slate-600 bg-slate-100',
];

$actionLabels = [
    'create_user' => 'Created User',
    'update_user' => 'Updated User',
    'archive_user' => 'Archived User',
    'send_reset' => 'Sent Reset',
    'login' => 'Login',
    'logout' => 'Logout',
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs | DAMMC Admin</title>
    <link rel="icon" type="image/png" href="/assets/images/dammc-logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>

<body class="bg-gradient-to-br from-[#F1FDF6] via-[#eafaf1] to-[#dcf3e5] min-h-screen">

    <div class="flex min-h-screen">

        <!-- Mobile overlay -->
        <div id="sidebarOverlay" onclick="closeSidebar()"
            class="fixed inset-0 bg-black/40 z-30 hidden md:hidden backdrop-blur-sm"></div>

        <?php
        $activePage = 'activity-logs'; // or 'users' / 'activity-logs' / 'settings'
        require __DIR__ . '/../components/sidebar.php';
        ?>

        <!-- Main content -->
        <div class="flex-1 md:ml-64 w-full min-w-0">

            <?php
            $pageTitle = 'Activity Logs';       // customize per page
            $pageSubtitle = 'Welcome back, ' . ($_SESSION['first_name'] ?? 'Admin');
            $pageIcon = 'history';
            require __DIR__ . '/../components/topbar.php';
            ?>

            <!-- Page content -->
            <main class="p-4 sm:p-6 lg:p-8 space-y-4 sm:space-y-6">

                <!-- Toolbar -->
                <div
                    class="animate-fade-in-up bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-3 sm:p-4 shadow-sm">
                    <div class="flex flex-col lg:flex-row gap-3 lg:items-center lg:justify-between">

                        <div class="flex flex-wrap items-center gap-2">
                            <select id="actionFilter" onchange="filterLogs()"
                                class="px-3 py-1.5 rounded-lg text-xs font-medium text-[#2C3E50]/80 bg-[#F1FDF6] border border-[#E0E0E0] focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition cursor-pointer">
                                <option value="">All Actions</option>
                                <option value="create_user">Created User</option>
                                <option value="update_user">Updated User</option>
                                <option value="archive_user">Archived User</option>
                                <option value="send_reset">Sent Reset</option>
                                <option value="login">Login</option>
                                <option value="logout">Logout</option>
                            </select>

                            <button onclick="filterToday()"
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
                </div>

                <!-- Logs list -->
                <div
                    class="animate-fade-in-up-delay-1 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl shadow-sm overflow-hidden">

                    <div id="logsList" class="divide-y divide-[#E0E0E0]">
                        <?php if (empty($logs)): ?>
                            <div class="p-12 text-center">
                                <div
                                    class="w-12 h-12 rounded-full bg-[#F1FDF6] flex items-center justify-center mx-auto mb-3 text-[#0F5E3D]">
                                    <i data-lucide="history" class="w-5 h-5"></i>
                                </div>
                                <p class="text-sm font-medium text-[#2C3E50]">No activity yet</p>
                                <p class="text-xs text-[#2C3E50]/50 mt-1">System actions will appear here.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($logs as $log):
                                $actionClass = $actionStyles[$log['action']] ?? 'text-slate-600 bg-slate-100';
                                $actionLabel = $actionLabels[$log['action']] ?? ucfirst(str_replace('_', ' ', $log['action']));
                                $initial = strtoupper(substr($log['username'] ?? 'S', 0, 1));
                                $ts = strtotime($log['created_at']);
                                $timeAgo = time() - $ts;

                                if ($timeAgo < 60)
                                    $timeLabel = 'Just now';
                                elseif ($timeAgo < 3600)
                                    $timeLabel = floor($timeAgo / 60) . 'm ago';
                                elseif ($timeAgo < 86400)
                                    $timeLabel = floor($timeAgo / 3600) . 'h ago';
                                elseif ($timeAgo < 604800)
                                    $timeLabel = floor($timeAgo / 86400) . 'd ago';
                                else
                                    $timeLabel = date('M j, Y', $ts);
                                ?>
                                <div class="log-row px-4 py-2.5 hover:bg-[#F1FDF6]/40 transition"
                                    data-action="<?= htmlspecialchars($log['action']) ?>"
                                    data-search="<?= strtolower(htmlspecialchars(($log['username'] ?? '') . ' ' . ($log['description'] ?? '') . ' ' . $actionLabel)) ?>"
                                    data-date="<?= date('Y-m-d', $ts) ?>">

                                    <div class="flex items-center gap-3">
                                        <!-- Avatar -->
                                        <div
                                            class="w-7 h-7 rounded-full bg-[#0F5E3D] text-white flex items-center justify-center font-semibold text-[10px] shrink-0">
                                            <?= $initial ?>
                                        </div>

                                        <!-- Content -->
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="text-sm font-medium text-[#2C3E50]">
                                                    <?= htmlspecialchars($log['username'] ?? 'System') ?>
                                                </span>
                                                <span
                                                    class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-medium <?= $actionClass ?>">
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

                                        <!-- Meta (right side) -->
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

                    <!-- Empty state (filtered) -->
                    <div id="emptyState" class="hidden p-12 text-center">
                        <div
                            class="w-12 h-12 rounded-full bg-[#F1FDF6] flex items-center justify-center mx-auto mb-3 text-[#0F5E3D]">
                            <i data-lucide="search-x" class="w-5 h-5"></i>
                        </div>
                        <p class="text-sm font-medium text-[#2C3E50]">No logs found</p>
                        <p class="text-xs text-[#2C3E50]/50 mt-1">Try adjusting your search or filters.</p>
                    </div>

                    <!-- Footer -->
                    <div class="px-4 sm:px-6 py-3 border-t border-[#E0E0E0] flex items-center justify-between">
                        <p class="text-xs text-[#2C3E50]/50">
                            Showing <span class="font-medium text-[#2C3E50]"><?= count($logs) ?></span> recent
                            activities
                        </p>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <!-- Logout modal -->
    <?php require_once __DIR__ . '/../components/logout_modal.php'; ?>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="/assets/js/app.js"></script>
    <script src="/assets/js/activity-logs.js"></script>
    <script src="/assets/js/profile.js"></script>
    <script src="/assets/js/notifications.js"></script>
</body>

</html>