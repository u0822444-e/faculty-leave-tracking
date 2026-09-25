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

// Load all settings into a keyed array
$stmt = $pdo->query("SELECT setting_key, setting_value, setting_type, category FROM settings");
$rows = $stmt->fetchAll();

$settings = [];
foreach ($rows as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Helper
function s($settings, $key, $default = '') {
    return htmlspecialchars($settings[$key] ?? $default);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php
    $pageTitle = 'Settings';
    require __DIR__ . '/../components/head.php';
    ?>
</head>

<body class="bg-gradient-to-br from-[#F1FDF6] via-[#eafaf1] to-[#dcf3e5] min-h-screen">

    <div class="flex min-h-screen">

        <!-- Mobile overlay -->
        <div id="sidebarOverlay" onclick="closeSidebar()"
            class="fixed inset-0 bg-black/40 z-40 hidden md:hidden backdrop-blur-sm"></div>

        <?php
        $activePage = 'settings';
        require __DIR__ . '/../components/sidebar.php';
        ?>

        <!-- Main content -->
        <div class="flex-1 md:ml-64 w-full min-w-0">

            <?php
            $pageTitle = 'Settings';
            $pageSubtitle = 'Configure system-wide preferences';
            $pageIcon = 'settings';
            require __DIR__ . '/../components/topbar.php';
            ?>

            <main class="p-4 sm:p-6 lg:p-8">

                <!-- Tabs -->
                <div class="animate-fade-in-up bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl shadow-sm overflow-hidden">

                    <!-- Tab nav -->
                    <div class="border-b border-[#E0E0E0] flex overflow-x-auto">
                        <button onclick="switchSettingsTab('general')" id="tabGeneral"
                            class="settings-tab whitespace-nowrap px-5 py-3 text-sm font-medium text-[#0F5E3D] border-b-2 border-[#0F5E3D] transition">
                            General
                        </button>
                        <button onclick="switchSettingsTab('leave')" id="tabLeave"
                            class="settings-tab whitespace-nowrap px-5 py-3 text-sm font-medium text-[#2C3E50]/60 border-b-2 border-transparent hover:text-[#2C3E50] transition">
                            Leave Rules
                        </button>
                        <button onclick="switchSettingsTab('payroll')" id="tabPayroll"
                            class="settings-tab whitespace-nowrap px-5 py-3 text-sm font-medium text-[#2C3E50]/60 border-b-2 border-transparent hover:text-[#2C3E50] transition">
                            Payroll
                        </button>
                        <button onclick="switchSettingsTab('notifications')" id="tabNotifications"
                            class="settings-tab whitespace-nowrap px-5 py-3 text-sm font-medium text-[#2C3E50]/60 border-b-2 border-transparent hover:text-[#2C3E50] transition">
                            Notifications
                        </button>
                    </div>

                    <!-- Tab: General -->
                    <div id="panelGeneral" class="settings-panel p-5">
                        <form class="settings-form" data-category="general">
                            <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 mb-4">School Information</p>

                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-6 gap-y-4">
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-xs font-medium text-[#2C3E50] mb-1">School Name</label>
                                        <input type="text" name="settings[school_name]" value="<?= s($settings, 'school_name') ?>"
                                            class="w-full px-3 py-2 border border-[#E0E0E0] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-[#2C3E50] mb-1">Tagline</label>
                                        <input type="text" name="settings[school_tagline]" value="<?= s($settings, 'school_tagline') ?>"
                                            class="w-full px-3 py-2 border border-[#E0E0E0] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-[#2C3E50] mb-1">Address</label>
                                        <input type="text" name="settings[school_address]" value="<?= s($settings, 'school_address') ?>"
                                            class="w-full px-3 py-2 border border-[#E0E0E0] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition">
                                    </div>
                                </div>
                                <div class="space-y-4 lg:border-l lg:border-[#E0E0E0] lg:pl-6">
                                    <div>
                                        <label class="block text-xs font-medium text-[#2C3E50] mb-1">Contact Email</label>
                                        <input type="email" name="settings[school_email]" value="<?= s($settings, 'school_email') ?>"
                                            class="w-full px-3 py-2 border border-[#E0E0E0] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-[#2C3E50] mb-1">Contact Phone</label>
                                        <input type="text" name="settings[school_phone]" value="<?= s($settings, 'school_phone') ?>"
                                            class="w-full px-3 py-2 border border-[#E0E0E0] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition"
                                            placeholder="+63 9XX XXX XXXX">
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-col sm:flex-row gap-2 pt-5 mt-5 border-t border-[#E0E0E0]">
                                <button type="submit"
                                    class="bg-[#0F5E3D] hover:bg-[#0a4a2f] active:scale-[0.99] text-white font-medium px-5 py-2 rounded-lg transition text-sm shadow-sm hover:shadow-md">
                                    Save Changes
                                </button>
                                <button type="reset"
                                    class="border border-[#E0E0E0] text-[#2C3E50] hover:bg-gray-50 font-medium px-5 py-2 rounded-lg transition text-sm">
                                    Reset
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Tab: Leave -->
                    <div id="panelLeave" class="settings-panel hidden p-5">
                        <form class="settings-form" data-category="leave">
                            <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 mb-4">Leave Credit Rules</p>

                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-6 gap-y-4">
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-xs font-medium text-[#2C3E50] mb-1">Default Leave Credits (days/year)</label>
                                        <input type="number" step="0.5" name="settings[default_leave_credits]"
                                            value="<?= s($settings, 'default_leave_credits', '15') ?>"
                                            class="w-full px-3 py-2 border border-[#E0E0E0] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition">
                                        <p class="text-[11px] text-[#2C3E50]/40 mt-1">Applied to new employees on creation.</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-[#2C3E50] mb-1">Fiscal Year Start</label>
                                        <input type="text" name="settings[fiscal_year_start]"
                                            value="<?= s($settings, 'fiscal_year_start', '01-01') ?>"
                                            placeholder="MM-DD"
                                            class="w-full px-3 py-2 border border-[#E0E0E0] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition">
                                        <p class="text-[11px] text-[#2C3E50]/40 mt-1">Format: MM-DD (e.g., 01-01)</p>
                                    </div>
                                </div>
                                <div class="space-y-4 lg:border-l lg:border-[#E0E0E0] lg:pl-6">
                                    <div class="p-3 bg-[#F1FDF6] border border-[#0F5E3D]/10 rounded-lg">
                                        <p class="text-[11px] text-[#2C3E50]/70 leading-relaxed">
                                            <strong class="text-[#0F5E3D]">Tip:</strong> Leave credits reset on the fiscal year start date. Employees can carry over unused credits if enabled.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-col sm:flex-row gap-2 pt-5 mt-5 border-t border-[#E0E0E0]">
                                <button type="submit"
                                    class="bg-[#0F5E3D] hover:bg-[#0a4a2f] active:scale-[0.99] text-white font-medium px-5 py-2 rounded-lg transition text-sm shadow-sm hover:shadow-md">
                                    Save Changes
                                </button>
                                <button type="reset"
                                    class="border border-[#E0E0E0] text-[#2C3E50] hover:bg-gray-50 font-medium px-5 py-2 rounded-lg transition text-sm">
                                    Reset
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Tab: Payroll -->
                    <div id="panelPayroll" class="settings-panel hidden p-5">
                        <form class="settings-form" data-category="payroll">
                            <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 mb-4">Payroll Configuration</p>

                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-6 gap-y-4">
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-xs font-medium text-[#2C3E50] mb-1">Default Work Days per Month</label>
                                        <input type="number" name="settings[default_work_days]"
                                            value="<?= s($settings, 'default_work_days', '22') ?>"
                                            class="w-full px-3 py-2 border border-[#E0E0E0] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-[#2C3E50] mb-1">Payroll Cycle</label>
                                        <select name="settings[payroll_cycle]"
                                            class="w-full px-3 py-2 border border-[#E0E0E0] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition cursor-pointer">
                                            <option value="monthly" <?= ($settings['payroll_cycle'] ?? '') === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                                            <option value="semi-monthly" <?= ($settings['payroll_cycle'] ?? 'semi-monthly') === 'semi-monthly' ? 'selected' : '' ?>>Semi-monthly</option>
                                            <option value="weekly" <?= ($settings['payroll_cycle'] ?? '') === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="space-y-4 lg:border-l lg:border-[#E0E0E0] lg:pl-6">
                                    <div class="p-3 bg-[#F1FDF6] border border-[#0F5E3D]/10 rounded-lg">
                                        <p class="text-[11px] text-[#2C3E50]/70 leading-relaxed">
                                            <strong class="text-[#0F5E3D]">Note:</strong> These values are used for leave-to-payroll adjustment calculations. The system does not perform full payroll computation — only leave-related adjustments.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-col sm:flex-row gap-2 pt-5 mt-5 border-t border-[#E0E0E0]">
                                <button type="submit"
                                    class="bg-[#0F5E3D] hover:bg-[#0a4a2f] active:scale-[0.99] text-white font-medium px-5 py-2 rounded-lg transition text-sm shadow-sm hover:shadow-md">
                                    Save Changes
                                </button>
                                <button type="reset"
                                    class="border border-[#E0E0E0] text-[#2C3E50] hover:bg-gray-50 font-medium px-5 py-2 rounded-lg transition text-sm">
                                    Reset
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Tab: Notifications -->
                    <div id="panelNotifications" class="settings-panel hidden p-5">
                        <form class="settings-form" data-category="notifications">
                            <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 mb-4">Notification Preferences</p>

                            <div class="space-y-4 max-w-2xl">

                                <label class="flex items-start gap-3 p-3 rounded-lg border border-[#E0E0E0] hover:bg-[#F1FDF6]/40 transition cursor-pointer">
                                    <input type="hidden" name="settings[email_notifications]" value="0">
                                    <input type="checkbox" name="settings[email_notifications]" value="1"
                                        <?= ($settings['email_notifications'] ?? '1') == '1' ? 'checked' : '' ?>
                                        class="w-4 h-4 mt-0.5 rounded border-gray-300 accent-[#0F5E3D] cursor-pointer">
                                    <div>
                                        <p class="text-sm font-medium text-[#2C3E50]">Enable Email Notifications</p>
                                        <p class="text-xs text-[#2C3E50]/50 mt-0.5">Send emails for account creation, password resets, and leave updates.</p>
                                    </div>
                                </label>

                                <label class="flex items-start gap-3 p-3 rounded-lg border border-[#E0E0E0] hover:bg-[#F1FDF6]/40 transition cursor-pointer">
                                    <input type="hidden" name="settings[notify_on_leave_request]" value="0">
                                    <input type="checkbox" name="settings[notify_on_leave_request]" value="1"
                                        <?= ($settings['notify_on_leave_request'] ?? '1') == '1' ? 'checked' : '' ?>
                                        class="w-4 h-4 mt-0.5 rounded border-gray-300 accent-[#0F5E3D] cursor-pointer">
                                    <div>
                                        <p class="text-sm font-medium text-[#2C3E50]">Notify on New Leave Request</p>
                                        <p class="text-xs text-[#2C3E50]/50 mt-0.5">Alert HR and approvers when a leave request is submitted.</p>
                                    </div>
                                </label>

                                <label class="flex items-start gap-3 p-3 rounded-lg border border-[#E0E0E0] hover:bg-[#F1FDF6]/40 transition cursor-pointer">
                                    <input type="hidden" name="settings[notify_on_leave_approval]" value="0">
                                    <input type="checkbox" name="settings[notify_on_leave_approval]" value="1"
                                        <?= ($settings['notify_on_leave_approval'] ?? '1') == '1' ? 'checked' : '' ?>
                                        class="w-4 h-4 mt-0.5 rounded border-gray-300 accent-[#0F5E3D] cursor-pointer">
                                    <div>
                                        <p class="text-sm font-medium text-[#2C3E50]">Notify on Leave Approval / Rejection</p>
                                        <p class="text-xs text-[#2C3E50]/50 mt-0.5">Alert employees when their request is approved or rejected.</p>
                                    </div>
                                </label>

                                <label class="flex items-start gap-3 p-3 rounded-lg border border-[#E0E0E0] hover:bg-[#F1FDF6]/40 transition cursor-pointer">
                                    <input type="hidden" name="settings[notify_on_new_user]" value="0">
                                    <input type="checkbox" name="settings[notify_on_new_user]" value="1"
                                        <?= ($settings['notify_on_new_user'] ?? '1') == '1' ? 'checked' : '' ?>
                                        class="w-4 h-4 mt-0.5 rounded border-gray-300 accent-[#0F5E3D] cursor-pointer">
                                    <div>
                                        <p class="text-sm font-medium text-[#2C3E50]">Notify Admins on New User</p>
                                        <p class="text-xs text-[#2C3E50]/50 mt-0.5">Broadcast a notification to all admins when a new account is created.</p>
                                    </div>
                                </label>

                            </div>

                            <div class="flex flex-col sm:flex-row gap-2 pt-5 mt-5 border-t border-[#E0E0E0]">
                                <button type="submit"
                                    class="bg-[#0F5E3D] hover:bg-[#0a4a2f] active:scale-[0.99] text-white font-medium px-5 py-2 rounded-lg transition text-sm shadow-sm hover:shadow-md">
                                    Save Changes
                                </button>
                            </div>
                        </form>
                    </div>

                </div>

            </main>
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
            <h3 class="text-base font-bold text-[#2C3E50] mb-1" id="loadingTitle">Loading...</h3>
            <p class="text-xs text-[#2C3E50]/60" id="loadingMessage">Please wait...</p>
        </div>
    </div>

    <!-- Success modal -->
    <div id="successModal" class="fixed inset-0 z-[70] hidden items-center justify-center bg-black/40 backdrop-blur-sm p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-8 text-center transform transition-all duration-200 scale-95" id="successCard">
            <div class="relative mx-auto mb-5 w-16 h-16">
                <div class="absolute inset-0 rounded-full bg-[#F1FDF6] animate-ping opacity-40"></div>
                <div class="relative w-16 h-16 rounded-full bg-[#F1FDF6] flex items-center justify-center text-[#0F5E3D]">
                    <i data-lucide="check-circle" class="w-8 h-8"></i>
                </div>
            </div>
            <h3 class="text-lg font-bold text-[#2C3E50] mb-2">Settings Saved</h3>
            <p class="text-sm text-[#2C3E50]/60 mb-6">Your changes have been saved successfully.</p>
            <button type="button" onclick="closeSuccessModal()"
                class="w-full bg-[#0F5E3D] hover:bg-[#0a4a2f] active:scale-[0.99] text-white font-medium py-2.5 rounded-lg transition text-sm">
                Done
            </button>
        </div>
    </div>

    <!-- Error modal -->
    <div id="errorModal" class="fixed inset-0 z-[80] hidden items-center justify-center bg-black/40 backdrop-blur-sm p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-8 text-center transform transition-all duration-200 scale-95" id="errorCard">
            <div class="flex items-center justify-center w-16 h-16 rounded-full bg-red-50 mx-auto mb-5">
                <i data-lucide="alert-circle" class="w-8 h-8 text-red-600"></i>
            </div>
            <h3 class="text-lg font-bold text-[#2C3E50] mb-2" id="errorTitle">Something went wrong</h3>
            <p class="text-sm text-[#2C3E50]/60 mb-6" id="errorMessage">An unexpected error occurred.</p>
            <button type="button" onclick="closeErrorModal()"
                class="w-full bg-[#0F5E3D] hover:bg-[#0a4a2f] active:scale-[0.99] text-white font-medium py-2.5 rounded-lg transition text-sm">
                Got it
            </button>
        </div>
    </div>

    <!-- Logout modal -->
    <?php require_once __DIR__ . '/../components/logout_modal.php'; ?>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="/assets/js/app.js"></script>
    <script src="/assets/js/notifications.js"></script>
    <script src="/assets/js/profile.js"></script>
    <script src="/assets/js/settings.js"></script>
</body>

</html>