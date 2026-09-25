<?php
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

require_once __DIR__ . '/../../config/database.php';

$userId = (int) $_SESSION['user_id'];

// Fetch current user's data
$stmt = $pdo->prepare("
    SELECT 
        u.id, u.username, u.role, u.status,
        e.first_name, e.middle_name, e.last_name, e.email,
        e.category, e.sub_category, e.employment_type,
        e.basic_salary, e.leave_credits, u.created_at
    FROM users u
    LEFT JOIN employees e ON u.employee_id = e.id
    WHERE u.id = ?
    LIMIT 1
");
$stmt->execute([$userId]);
$me = $stmt->fetch();

if (!$me) {
    header('Location: /login');
    exit;
}

$fullName = trim(($me['first_name'] ?? '') . ' ' . ($me['last_name'] ?? '')) ?: $me['username'];
$initials = strtoupper(substr($me['first_name'] ?? $me['username'], 0, 1) . substr($me['last_name'] ?? '', 0, 1));

$pageTitle = 'My Profile';
$pageSubtitle = 'Update your account information';
$activePage = 'profile';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | DAMMC</title>
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

        <!-- Sidebar -->
        <?php require __DIR__ . '/../components/sidebar.php'; ?>

        <!-- Main content -->
        <div class="flex-1 md:ml-64 w-full min-w-0">

            <!-- Top bar (reusable component) -->
            <?php
            $pageIcon = 'user';
            require __DIR__ . '/../components/topbar.php'; ?>

            <!-- Page content -->
            <main class="p-4 sm:p-6 lg:p-8">

                <!-- Profile header card -->
                <div
                    class="animate-fade-in-up bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl px-5 py-3 shadow-sm mb-4">
                    <div class="flex items-center gap-3">
                        <div
                            class="w-10 h-10 rounded-full bg-[#0F5E3D] text-white flex items-center justify-center font-bold text-sm shrink-0">
                            <?= $initials ?>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-sm font-bold text-[#2C3E50] truncate">
                                    <?= htmlspecialchars($fullName) ?>
                                </h2>
                                <span
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium text-[#0F5E3D] bg-[#F1FDF6]">
                                    <?= ucfirst($me['role']) ?>
                                </span>
                            </div>
                            <p class="text-[11px] text-[#2C3E50]/50 truncate mt-0.5">
                                @<?= htmlspecialchars($me['username']) ?>
                                <span class="text-[#2C3E50]/30 mx-1">·</span>
                                Joined <?= date('M Y', strtotime($me['created_at'])) ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <div
                    class="animate-fade-in-up-delay-1 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl shadow-sm overflow-hidden">

                    <div class="border-b border-[#E0E0E0] flex">
                        <button onclick="switchProfileTab('info')" id="tabInfo"
                            class="profile-tab flex-1 sm:flex-none px-5 py-2.5 text-sm font-medium text-[#0F5E3D] border-b-2 border-[#0F5E3D] transition">
                            Personal Info
                        </button>
                        <button onclick="switchProfileTab('password')" id="tabPassword"
                            class="profile-tab flex-1 sm:flex-none px-5 py-2.5 text-sm font-medium text-[#2C3E50]/60 border-b-2 border-transparent hover:text-[#2C3E50] transition">
                            Password
                        </button>
                    </div>

                    <!-- Tab: Personal Info -->
                    <div id="panelInfo" class="profile-panel p-5">
                        <form id="profileInfoForm" novalidate>
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-6 gap-y-4">

                                <!-- LEFT: Editable fields -->
                                <div class="space-y-4">
                                    <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40">Personal Details
                                    </p>

                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-xs font-medium text-[#2C3E50] mb-1">First Name
                                                *</label>
                                            <input type="text" name="first_name" id="profFirstName"
                                                value="<?= htmlspecialchars($me['first_name'] ?? '') ?>"
                                                class="w-full px-3 py-2 border border-[#E0E0E0] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-[#2C3E50] mb-1">Last Name
                                                *</label>
                                            <input type="text" name="last_name" id="profLastName"
                                                value="<?= htmlspecialchars($me['last_name'] ?? '') ?>"
                                                class="w-full px-3 py-2 border border-[#E0E0E0] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition">
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-medium text-[#2C3E50] mb-1">Middle Name</label>
                                        <input type="text" name="middle_name"
                                            value="<?= htmlspecialchars($me['middle_name'] ?? '') ?>"
                                            class="w-full px-3 py-2 border border-[#E0E0E0] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition">
                                    </div>

                                    <div>
                                        <label class="block text-xs font-medium text-[#2C3E50] mb-1">Email</label>
                                        <input type="email" name="email" id="profEmail"
                                            value="<?= htmlspecialchars($me['email'] ?? '') ?>"
                                            class="w-full px-3 py-2 border border-[#E0E0E0] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition">
                                    </div>

                                    <!-- Actions -->
                                    <div class="flex flex-col sm:flex-row gap-2 pt-1">
                                        <button type="submit"
                                            class="flex-1 bg-[#0F5E3D] hover:bg-[#0a4a2f] active:scale-[0.99] text-white font-medium py-2 rounded-lg transition text-sm shadow-sm hover:shadow-md">
                                            Save Changes
                                        </button>
                                        <button type="reset"
                                            class="flex-1 border border-[#E0E0E0] text-[#2C3E50] hover:bg-gray-50 font-medium py-2 rounded-lg transition text-sm">
                                            Reset
                                        </button>
                                    </div>
                                </div>

                                <!-- RIGHT: Read-only info -->
                                <div class="space-y-4 lg:border-l lg:border-[#E0E0E0] lg:pl-6">

                                    <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40">Account
                                        Information</p>

                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label
                                                class="block text-xs font-medium text-[#2C3E50]/50 mb-1">Username</label>
                                            <input type="text" value="<?= htmlspecialchars($me['username']) ?>" readonly
                                                class="w-full px-3 py-2 bg-[#F1FDF6]/60 border border-[#E0E0E0] rounded-lg text-sm text-[#2C3E50]/60 font-mono cursor-not-allowed">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-[#2C3E50]/50 mb-1">Role</label>
                                            <input type="text" value="<?= ucfirst($me['role']) ?>" readonly
                                                class="w-full px-3 py-2 bg-[#F1FDF6]/60 border border-[#E0E0E0] rounded-lg text-sm text-[#2C3E50]/60 cursor-not-allowed">
                                        </div>
                                    </div>

                                    <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 pt-1">Employment
                                        Details</p>

                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label
                                                class="block text-xs font-medium text-[#2C3E50]/50 mb-1">Department</label>
                                            <input type="text"
                                                value="<?= htmlspecialchars($me['sub_category'] ?? '—') ?>" readonly
                                                class="w-full px-3 py-2 bg-[#F1FDF6]/60 border border-[#E0E0E0] rounded-lg text-sm text-[#2C3E50]/60 cursor-not-allowed">
                                        </div>
                                        <div>
                                            <label
                                                class="block text-xs font-medium text-[#2C3E50]/50 mb-1">Employment</label>
                                            <input type="text" value="<?= ucfirst($me['employment_type'] ?? '—') ?>"
                                                readonly
                                                class="w-full px-3 py-2 bg-[#F1FDF6]/60 border border-[#E0E0E0] rounded-lg text-sm text-[#2C3E50]/60 cursor-not-allowed">
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-xs font-medium text-[#2C3E50]/50 mb-1">Leave
                                                Credits</label>
                                            <input type="text"
                                                value="<?= number_format((float) ($me['leave_credits'] ?? 0), 2) ?>"
                                                readonly
                                                class="w-full px-3 py-2 bg-[#F1FDF6]/60 border border-[#E0E0E0] rounded-lg text-sm text-[#0F5E3D] font-mono cursor-not-allowed">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-[#2C3E50]/50 mb-1">Monthly
                                                Salary</label>
                                            <input type="text"
                                                value="₱<?= number_format((float) ($me['basic_salary'] ?? 0), 2) ?>"
                                                readonly
                                                class="w-full px-3 py-2 bg-[#F1FDF6]/60 border border-[#E0E0E0] rounded-lg text-sm text-[#0F5E3D] font-mono cursor-not-allowed">
                                        </div>
                                    </div>

                                </div>

                            </div>
                        </form>
                    </div>

                    <!-- Tab: Password -->
                    <div id="panelPassword" class="profile-panel hidden p-5">
                        <form id="profilePasswordForm" novalidate>
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-6 gap-y-4">

                                <!-- LEFT: Password inputs -->
                                <div class="space-y-4">

                                    <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40">Change Password
                                    </p>

                                    <div>
                                        <label class="block text-xs font-medium text-[#2C3E50] mb-1">Current Password
                                            *</label>
                                        <input type="password" name="current_password" id="currentPassword" required
                                            class="w-full px-3 py-2 border border-[#E0E0E0] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition"
                                            placeholder="Enter your current password">
                                    </div>

                                    <div>
                                        <label class="block text-xs font-medium text-[#2C3E50] mb-1">New Password
                                            *</label>
                                        <input type="password" name="new_password" id="newPassword" required
                                            minlength="8" oninput="checkProfilePasswordStrength()"
                                            class="w-full px-3 py-2 border border-[#E0E0E0] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition"
                                            placeholder="At least 8 characters">

                                        <!-- Strength meter -->
                                        <div id="pwStrengthWrap" class="mt-1.5 hidden">
                                            <div class="flex gap-1 mb-1">
                                                <div class="pw-strength-bar h-1 flex-1 rounded-full bg-gray-200 transition-all"
                                                    data-bar="1"></div>
                                                <div class="pw-strength-bar h-1 flex-1 rounded-full bg-gray-200 transition-all"
                                                    data-bar="2"></div>
                                                <div class="pw-strength-bar h-1 flex-1 rounded-full bg-gray-200 transition-all"
                                                    data-bar="3"></div>
                                                <div class="pw-strength-bar h-1 flex-1 rounded-full bg-gray-200 transition-all"
                                                    data-bar="4"></div>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <p id="pwStrengthLabel" class="text-[11px] font-medium text-gray-500">
                                                    Strength</p>
                                                <p id="pwStrengthHint" class="text-[11px] text-gray-400"></p>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-medium text-[#2C3E50] mb-1">Confirm New
                                            Password *</label>
                                        <input type="password" name="confirm_password" id="confirmPassword" required
                                            minlength="8" oninput="checkProfilePasswordMatch()"
                                            class="w-full px-3 py-2 border border-[#E0E0E0] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition"
                                            placeholder="Re-enter new password">
                                        <p id="pwMatchHint" class="mt-1 text-[11px] text-gray-400 hidden">Passwords must
                                            match.</p>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <input type="checkbox" id="showPasswords" onclick="toggleProfilePasswords()"
                                            class="w-4 h-4 rounded border-gray-300 accent-[#0F5E3D] cursor-pointer">
                                        <label for="showPasswords"
                                            class="text-sm text-gray-500 cursor-pointer select-none hover:text-[#2C3E50] transition-colors">
                                            Show passwords
                                        </label>
                                    </div>

                                    <!-- Actions -->
                                    <div class="flex flex-col sm:flex-row gap-2 pt-1">
                                        <button type="submit"
                                            class="flex-1 bg-[#0F5E3D] hover:bg-[#0a4a2f] active:scale-[0.99] text-white font-medium py-2 rounded-lg transition text-sm shadow-sm hover:shadow-md">
                                            Update Password
                                        </button>
                                        <button type="reset"
                                            class="flex-1 border border-[#E0E0E0] text-[#2C3E50] hover:bg-gray-50 font-medium py-2 rounded-lg transition text-sm">
                                            Clear
                                        </button>
                                    </div>
                                </div>

                                <!-- RIGHT: Requirements checklist -->
                                <div class="lg:border-l lg:border-[#E0E0E0] lg:pl-6">

                                    <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 mb-3">Password
                                        Requirements</p>

                                    <ul id="pwRequirements" class="space-y-1.5 text-xs text-[#2C3E50]/60">
                                        <li data-req="length" class="flex items-center gap-2">
                                            <span class="req-dot w-1.5 h-1.5 rounded-full bg-gray-300"></span>
                                            At least 8 characters
                                        </li>
                                        <li data-req="upper" class="flex items-center gap-2">
                                            <span class="req-dot w-1.5 h-1.5 rounded-full bg-gray-300"></span>
                                            One uppercase letter (A–Z)
                                        </li>
                                        <li data-req="lower" class="flex items-center gap-2">
                                            <span class="req-dot w-1.5 h-1.5 rounded-full bg-gray-300"></span>
                                            One lowercase letter (a–z)
                                        </li>
                                        <li data-req="number" class="flex items-center gap-2">
                                            <span class="req-dot w-1.5 h-1.5 rounded-full bg-gray-300"></span>
                                            One number (0–9)
                                        </li>
                                        <li data-req="special" class="flex items-center gap-2">
                                            <span class="req-dot w-1.5 h-1.5 rounded-full bg-gray-300"></span>
                                            One special character (!@#$%^&*)
                                        </li>
                                    </ul>

                                    <div class="mt-4 p-3 bg-[#F1FDF6] border border-[#0F5E3D]/10 rounded-lg">
                                        <p class="text-[11px] text-[#2C3E50]/70 leading-relaxed">
                                            <strong class="text-[#0F5E3D]">Tip:</strong> Use a mix of letters, numbers,
                                            and symbols. Avoid common words.
                                        </p>
                                    </div>
                                </div>

                            </div>
                        </form>
                    </div>

                </div>

            </main>
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
            <h3 class="text-base font-bold text-[#2C3E50] mb-1" id="loadingTitle">Loading...</h3>
            <p class="text-xs text-[#2C3E50]/60" id="loadingMessage">Please wait...</p>
        </div>
    </div>

    <!-- Success modal -->
    <div id="successModal"
        class="fixed inset-0 z-[70] hidden items-center justify-center bg-black/40 backdrop-blur-sm p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-8 text-center transform transition-all duration-200 scale-95"
            id="successCard">
            <div class="relative mx-auto mb-5 w-16 h-16">
                <div class="absolute inset-0 rounded-full bg-[#F1FDF6] animate-ping opacity-40"></div>
                <div
                    class="relative w-16 h-16 rounded-full bg-[#F1FDF6] flex items-center justify-center text-[#0F5E3D]">
                    <i data-lucide="check-circle" class="w-8 h-8"></i>
                </div>
            </div>
            <h3 class="text-lg font-bold text-[#2C3E50] mb-2" id="successTitle">Saved Successfully</h3>
            <p class="text-sm text-[#2C3E50]/60 mb-6" id="successMessage">Your changes have been saved.</p>
            <button type="button" onclick="closeSuccessModal()"
                class="w-full bg-[#0F5E3D] hover:bg-[#0a4a2f] active:scale-[0.99] text-white font-medium py-2.5 rounded-lg transition text-sm">
                Done
            </button>
        </div>
    </div>

    <!-- Error modal -->
    <div id="errorModal"
        class="fixed inset-0 z-[80] hidden items-center justify-center bg-black/40 backdrop-blur-sm p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-8 text-center transform transition-all duration-200 scale-95"
            id="errorCard">
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
</body>

</html>