<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Validate token presence
$token = $_GET['token'] ?? '';
if (empty($token)) {
    $_SESSION['error'] = 'Missing reset token.';
    header('Location: /forgot-password');
    exit;
}

// Pre-validate token before rendering
require_once __DIR__ . '/../../config/database.php';
$stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expires_at > NOW() LIMIT 1");
$stmt->execute([$token]);
$valid = (bool) $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | DAMMC</title>
    <link rel="icon" type="image/png" href="/assets/images/dammc-logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>

<body class="min-h-screen login-gradient flex items-center justify-center p-4">

    <div class="card-in w-full max-w-md bg-white rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.08)] border border-gray-100 overflow-hidden">

        <div class="p-8 sm:p-10">

            <div class="flex justify-center mb-6">
                <img src="/assets/images/dammc-logo.png" alt="DAMMC Logo" class="w-14 h-14 object-contain">
            </div>

            <h1 class="text-2xl font-bold text-[#2C3E50] text-center mb-2">Set New Password</h1>
            <p class="text-sm text-[#2C3E50]/60 text-center mb-8">
                Choose a strong password for your account.
            </p>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-3 py-2 rounded-md mb-5 text-sm">
                    <?= htmlspecialchars($_SESSION['error']) ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <?php if (!$valid): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-md text-sm text-center">
                    This reset link is invalid or has expired.<br>
                    <a href="/forgot-password" class="font-medium underline mt-1 inline-block">Request a new one</a>
                </div>
            <?php else: ?>
                <form action="/reset-password" method="POST" class="space-y-5" id="resetForm">
    <input type="hidden" name="action" value="reset">
    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

    <div>
        <label for="password" class="block text-sm font-medium text-[#2C3E50] mb-2">New Password</label>
        <input type="password" id="password" name="password" required minlength="8"
            oninput="checkPasswordStrength()"
            class="w-full px-4 py-3 border border-gray-200 rounded-md text-[#2C3E50] text-sm focus:outline-none focus:ring-1 focus:ring-[#0F5E3D] focus:border-[#0F5E3D] placeholder-gray-400 transition"
            placeholder="At least 8 characters">

        <!-- Strength meter -->
        <div id="strengthWrap" class="mt-3 hidden">
            <!-- Bars -->
            <div class="flex gap-1 mb-2">
                <div class="strength-bar h-1 flex-1 rounded-full bg-gray-200 transition-all" data-bar="1"></div>
                <div class="strength-bar h-1 flex-1 rounded-full bg-gray-200 transition-all" data-bar="2"></div>
                <div class="strength-bar h-1 flex-1 rounded-full bg-gray-200 transition-all" data-bar="3"></div>
                <div class="strength-bar h-1 flex-1 rounded-full bg-gray-200 transition-all" data-bar="4"></div>
            </div>

            <!-- Label -->
            <div class="flex items-center justify-between">
                <p id="strengthLabel" class="text-[11px] font-medium text-gray-500">Strength</p>
                <p id="strengthHint" class="text-[11px] text-gray-400"></p>
            </div>
        </div>

        <!-- Requirements checklist -->
        <ul id="pwRequirements" class="mt-3 space-y-1.5 text-[11px] text-[#2C3E50]/60 hidden">
            <li data-req="length" class="flex items-center gap-1.5">
                <span class="req-dot w-1.5 h-1.5 rounded-full bg-gray-300"></span>
                At least 8 characters
            </li>
            <li data-req="upper" class="flex items-center gap-1.5">
                <span class="req-dot w-1.5 h-1.5 rounded-full bg-gray-300"></span>
                One uppercase letter
            </li>
            <li data-req="lower" class="flex items-center gap-1.5">
                <span class="req-dot w-1.5 h-1.5 rounded-full bg-gray-300"></span>
                One lowercase letter
            </li>
            <li data-req="number" class="flex items-center gap-1.5">
                <span class="req-dot w-1.5 h-1.5 rounded-full bg-gray-300"></span>
                One number
            </li>
            <li data-req="special" class="flex items-center gap-1.5">
                <span class="req-dot w-1.5 h-1.5 rounded-full bg-gray-300"></span>
                One special character (!@#$%^&*)
            </li>
        </ul>
    </div>

    <div>
        <label for="confirm_password" class="block text-sm font-medium text-[#2C3E50] mb-2">Confirm Password</label>
        <input type="password" id="confirm_password" name="confirm_password" required minlength="8"
            oninput="checkMatch()"
            class="w-full px-4 py-3 border border-gray-200 rounded-md text-[#2C3E50] text-sm focus:outline-none focus:ring-1 focus:ring-[#0F5E3D] focus:border-[#0F5E3D] placeholder-gray-400 transition"
            placeholder="Re-enter password">
        <p id="matchHint" class="mt-2 text-[11px] text-gray-400 hidden">Passwords must match.</p>
    </div>

    <!-- Show Password Checkbox -->
    <div class="flex items-center gap-2">
        <input type="checkbox" id="showPassword" onclick="togglePassword()"
            class="w-4 h-4 rounded border-gray-300 accent-[#0F5E3D] cursor-pointer">
        <label for="showPassword"
            class="text-sm text-gray-500 cursor-pointer select-none hover:text-[#2C3E50] transition-colors">
            Show password
        </label>
    </div>

    <button type="submit" id="resetBtn"
        class="w-full bg-[#0F5E3D] hover:bg-[#0a4a2f] active:scale-[0.99] text-white font-medium py-3 rounded-md transition duration-200">
        Reset Password
    </button>
</form>
            <?php endif; ?>

            <div class="mt-6 text-center">
                <a href="/login" class="text-sm text-[#2C3E50]/60 hover:text-[#0F5E3D] transition">
                    ← Back to Sign In
                </a>
            </div>

        </div>
    </div>


    <script src="/assets/js/reset-password.js"></script>

</body>

</html>