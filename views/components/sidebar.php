<?php
$activePage = $activePage ?? '';
$role = $_SESSION['role'] ?? 'admin';

function navClass($key, $activePage)
{
    $base = 'flex items-center gap-3 px-4 py-3 rounded-lg transition';
    return $key === $activePage
        ? $base . ' bg-white/10 backdrop-blur-sm text-white font-medium ring-1 ring-white/10'
        : $base . ' text-white/70 hover:bg-white/5 hover:text-white';
}
?>

<aside id="sidebar"
    class="w-64 bg-gradient-to-b from-[#0F5E3D] to-[#0a4a2f] text-white flex flex-col fixed h-full z-50 transition-transform duration-300 -translate-x-full md:translate-x-0 shadow-xl">
    <div class="flex items-center gap-3 py-6 px-5 border-b border-white/10">
        <img src="/assets/images/dammc-logo.png" alt="DAMMC Logo" class="w-11 h-11 object-contain shrink-0">
        <span class="font-bold text-white tracking-wide text-sm leading-tight">
            Dr. Aurelio Mendoza <br> Memorial Colleges
        </span>
        <button onclick="closeSidebar()" class="md:hidden ml-auto text-white/80 hover:text-white transition shrink-0">
            <i data-lucide="x" class="w-5 h-5"></i>
        </button>
    </div>

    <nav class="flex-1 px-3 py-6 space-y-1 overflow-y-auto">

        <?php if ($role === 'admin'): ?>
            <a href="/dashboard" class="<?= navClass('dashboard', $activePage) ?>">
                <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
                <span>Dashboard</span>
            </a>
            <a href="/users" class="<?= navClass('users', $activePage) ?>">
                <i data-lucide="users" class="w-5 h-5"></i>
                <span>Users</span>
            </a>
            <a href="/leaves" class="<?= navClass('leaves', $activePage) ?>">
                <i data-lucide="calendar-days" class="w-5 h-5"></i>
                <span>Leaves</span>
            </a>
            <a href="/payroll" class="<?= navClass('payroll', $activePage) ?>">
                <i data-lucide="wallet" class="w-5 h-5"></i>
                <span>Payroll</span>
            </a>
            <a href="/activity-logs" class="<?= navClass('activity-logs', $activePage) ?>">
                <i data-lucide="history" class="w-5 h-5"></i>
                <span>Activity Logs</span>
            </a>
            <a href="/settings" class="<?= navClass('settings', $activePage) ?>">
                <i data-lucide="settings" class="w-5 h-5"></i>
                <span>Settings</span>
            </a>

        <?php elseif ($role === 'faculty'): ?>
            <a href="/faculty/dashboard" class="<?= navClass('faculty_dashboard', $activePage) ?>">
                <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
                <span>Dashboard</span>
            </a>
            <a href="/faculty/leaves" class="<?= navClass('faculty_leaves', $activePage) ?>">
                <i data-lucide="calendar-days" class="w-5 h-5"></i>
                <span>My Leaves</span>
            </a>

        <?php elseif (in_array($role, ['hr'], true)): ?>
            <a href="/hr/dashboard" class="<?= navClass('hr_dashboard', $activePage) ?>">
                <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
                <span>Dashboard</span>
            </a>
            <a href="/hr/leaves" class="<?= navClass('hr_leaves', $activePage) ?>">
                <i data-lucide="calendar-days" class="w-5 h-5"></i>
                <span>Leave Requests</span>
            </a>

        <?php elseif ($role === 'staff'): ?>

            <a href="/staff/dashboard" class="<?= navClass('staff_dashboard', $activePage) ?>">
                <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
                <span>Dashboard</span>
            </a>
            <a href="/staff/leaves" class="<?= navClass('staff_leaves', $activePage) ?>">
                <i data-lucide="calendar-days" class="w-5 h-5"></i>
                <span>My Leaves</span>
            </a>

        <?php endif; ?>

    </nav>

    <div class="px-3 py-4 border-t border-white/10">
        <button type="button" onclick="openLogoutModal()"
            class="w-full flex items-center gap-3 px-4 py-3 rounded-lg text-white/70 hover:bg-white/5 hover:text-white transition">
            <i data-lucide="log-out" class="w-5 h-5"></i>
            <span>Logout</span>
        </button>
    </div>
</aside>