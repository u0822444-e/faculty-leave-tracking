<?php
/**
 * Reusable Top Bar
 *
 * Expected variables before include:
 *   $pageTitle    (string) — main heading
 *   $pageSubtitle (string) — small line under heading
 *   $activePage   (string) — 'dashboard' | 'users' | 'activity-logs' | 'settings'
 */
$pageTitle = $pageTitle ?? 'Dashboard';
$pageSubtitle = $pageSubtitle ?? '';
$activePage = $activePage ?? '';
$pageIcon = $pageIcon ?? '';

// Get current user initials for the avatar
$currentInitial = strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1));
$currentUser = htmlspecialchars($_SESSION['username'] ?? 'Admin');
$currentRole = ucfirst($_SESSION['role'] ?? 'admin');

// Initials from username — first 2 letters
$initials = strtoupper(substr($_SESSION['username'] ?? 'A', 0, 2));
?>

<header
    class="bg-white/70 backdrop-blur-md border-b border-[#E0E0E0] px-4 sm:px-6 lg:px-8 py-3 sm:py-4 flex items-center justify-between sticky top-0 z-30">

    <!-- Left: Mobile menu + Title -->
    <div class="flex items-center gap-3">
        <button onclick="openSidebar()" class="md:hidden text-[#2C3E50] hover:text-[#0F5E3D] transition">
            <i data-lucide="menu" class="w-6 h-6"></i>
        </button>
        <div class="flex items-center gap-2.5">
            <?php if ($pageIcon): ?>
                <div class="w-8 h-8 rounded-lg bg-[#F1FDF6] flex items-center justify-center text-[#0F5E3D] shrink-0">
                    <i data-lucide="<?= htmlspecialchars($pageIcon) ?>" class="w-4 h-4"></i>
                </div>
            <?php endif; ?>
            <div>
                <h1 class="text-base sm:text-lg lg:text-xl font-bold text-[#2C3E50] tracking-tight">
                    <?= htmlspecialchars($pageTitle) ?>
                </h1>
                <?php if ($pageSubtitle): ?>
                    <p class="hidden sm:block text-xs text-[#2C3E50]/50">
                        <?= htmlspecialchars($pageSubtitle) ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right: Notifications + Profile dropdown -->
    <div class="flex items-center gap-2 sm:gap-3">

        <!-- Notification bell -->
        <div class="relative" id="notificationWrap">
            <button type="button" onclick="toggleNotifications()"
                class="relative w-9 h-9 rounded-full flex items-center justify-center text-[#2C3E50] hover:bg-[#F1FDF6] hover:text-[#0F5E3D] transition">
                <i data-lucide="bell" class="w-5 h-5"></i>
                <span id="notifBadge"
                    class="hidden absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 rounded-full bg-red-500 text-white text-[10px] font-semibold flex items-center justify-center ring-2 ring-white">
                    0
                </span>
            </button>

            <!-- Notifications dropdown -->
            <div id="notifDropdown"
                class="hidden absolute right-0 top-12 w-80 sm:w-96 bg-white rounded-xl shadow-xl border border-[#E0E0E0] overflow-hidden z-50">

                <div class="px-4 py-3 border-b border-[#E0E0E0] flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-[#2C3E50]">Notifications</p>
                        <p class="text-[10px] text-[#2C3E50]/50" id="notifSummary">—</p>
                    </div>
                    <button type="button" onclick="markAllRead()"
                        class="text-[11px] font-medium text-[#0F5E3D] hover:text-[#0a4a2f] transition">
                        Mark all as read
                    </button>
                </div>

                <div id="notifList" class="max-h-96 overflow-y-auto divide-y divide-[#E0E0E0]">
                    <div class="p-8 text-center text-xs text-[#2C3E50]/40">Loading...</div>
                </div>

                <div class="px-4 py-2 border-t border-[#E0E0E0] text-center">
                    <a href="/activity-logs"
                        class="text-[11px] font-medium text-[#0F5E3D] hover:text-[#0a4a2f] transition">
                        View all activity
                    </a>
                </div>
            </div>
        </div>

        <!-- Profile dropdown -->
        <div class="relative" id="profileWrap">
            <button type="button" onclick="toggleProfileMenu()"
                class="flex items-center gap-2 focus:outline-none group">
                <div
                    class="w-8 h-8 sm:w-9 sm:h-9 rounded-full bg-[#0F5E3D] text-white flex items-center justify-center font-semibold text-xs sm:text-sm ring-2 ring-white shadow-sm group-hover:ring-[#F1FDF6] transition">
                    <?= $initials ?>
                </div>
                <i data-lucide="chevron-down"
                    class="hidden sm:block w-3.5 h-3.5 text-[#2C3E50]/40 group-hover:text-[#2C3E50] transition"></i>
            </button>

            <!-- Profile menu -->
            <div id="profileMenu"
                class="hidden absolute right-0 top-12 w-64 bg-white rounded-xl shadow-xl border border-[#E0E0E0] overflow-hidden z-50">

                <!-- User summary -->
                <div class="px-4 py-3 border-b border-[#E0E0E0]">
                    <div class="flex items-center gap-3">
                        <div
                            class="w-10 h-10 rounded-full bg-[#0F5E3D] text-white flex items-center justify-center font-semibold text-sm shrink-0">
                            <?= $initials ?>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-[#2C3E50] truncate"><?= $currentUser ?></p>
                            <p class="text-[11px] text-[#2C3E50]/50"><?= $currentRole ?></p>
                        </div>
                    </div>
                </div>

                <!-- Menu items -->
                <div class="py-1">
                    <a href="/profile"
                        class="flex items-center gap-3 px-4 py-2.5 text-sm text-[#2C3E50] hover:bg-[#F1FDF6] transition">
                        <i data-lucide="user" class="w-4 h-4 text-[#2C3E50]/60"></i>
                        <span>My Profile</span>
                    </a>
                    <a href="/activity-logs"
                        class="flex items-center gap-3 px-4 py-2.5 text-sm text-[#2C3E50] hover:bg-[#F1FDF6] transition">
                        <i data-lucide="history" class="w-4 h-4 text-[#2C3E50]/60"></i>
                        <span>My Activity</span>
                    </a>
                </div>
            </div>
        </div>

    </div>
</header>