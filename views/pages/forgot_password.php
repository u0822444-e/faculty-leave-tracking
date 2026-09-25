<?php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | DAMMC</title>
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

            <h1 class="text-2xl font-bold text-[#2C3E50] text-center mb-2">Forgot Password?</h1>
            <p class="text-sm text-[#2C3E50]/60 text-center mb-8">
                Enter your email and we'll send you a reset link.
            </p>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-3 py-2 rounded-md mb-5 text-sm">
                    <?= htmlspecialchars($_SESSION['error']) ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="bg-[#F1FDF6] border border-[#0F5E3D]/20 text-[#0F5E3D] px-3 py-2 rounded-md mb-5 text-sm">
                    <?= htmlspecialchars($_SESSION['success']) ?>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <form action="/forgot-password" method="POST" class="space-y-5">
                <input type="hidden" name="action" value="request">

                <div>
                    <label for="email" class="block text-sm font-medium text-[#2C3E50] mb-2">Email Address</label>
                    <input type="email" id="email" name="email" required autocomplete="email"
                        class="w-full px-4 py-3 border border-gray-200 rounded-md text-[#2C3E50] text-sm focus:outline-none focus:ring-1 focus:ring-[#0F5E3D] focus:border-[#0F5E3D] placeholder-gray-400 transition"
                        placeholder="you@dammc.edu.ph">
                </div>

                <button type="submit"
                    class="w-full bg-[#0F5E3D] hover:bg-[#0a4a2f] active:scale-[0.99] text-white font-medium py-3 rounded-md transition duration-200">
                    Send Reset Link
                </button>
            </form>

            <div class="mt-6 text-center">
                <a href="/login" class="text-sm text-[#2C3E50]/60 hover:text-[#0F5E3D] transition">
                    ← Back to Sign In
                </a>
            </div>

        </div>
    </div>

</body>

</html>