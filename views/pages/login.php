<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Leave Tracking | Sign In</title>
    <link rel="icon" type="image/png" href="/assets/images/dammc-logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>

<body class="min-h-screen login-gradient flex items-center justify-center relative p-4">

    <!-- Split modal-style card -->
    <div class="card-in w-full max-w-[820px] min-h-[480px] md:min-h-[520px] bg-white rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.08)] border border-gray-100 overflow-hidden relative flex flex-col md:flex-row">

        <!-- Right: image (mobile: top) -->
        <div class="w-full md:w-1/2 relative bg-[#F1FDF6] h-48 sm:h-56 md:h-auto order-first md:order-last">
            <img src="/assets/images/dammc-bg.png" alt="Dr. Aurelio Mendoza Memorial Colleges"
                class="absolute inset-0 w-full h-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-t from-[#0F5E3D]/80 via-[#0F5E3D]/20 to-transparent"></div>

            <div class="absolute bottom-0 left-0 right-0 p-6 md:p-8 flex flex-col items-center text-center text-white">
                <img src="/assets/images/dammc-logo.png" alt="DAMMC Logo"
                    class="w-10 h-10 md:w-16 md:h-16 object-contain mb-2 md:mb-3 drop-shadow-md">
                <p class="text-xs md:text-sm font-semibold leading-snug tracking-wide">
                    Dr. Aurelio Mendoza<br>Memorial Colleges
                </p>
                <div class="w-10 h-0.5 bg-white/40 rounded-full mt-2 md:mt-3"></div>
            </div>
        </div>

        <!-- Left: form -->
        <div class="w-full md:w-1/2 p-8 sm:p-10 lg:p-12 flex flex-col justify-center order-last md:order-first">

            <div class="mb-8">
                <h2 class="text-2xl font-semibold text-[#2C3E50]">Sign in</h2>
                <p class="text-sm text-gray-500 mt-1">Access your faculty dashboard.</p>
            </div>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-3 py-2 rounded-md mb-5 text-sm">
                    <?= htmlspecialchars($_SESSION['error']) ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <form action="/login" method="POST" class="space-y-5">
                <div>
                    <input type="text" id="username" name="username" required autocomplete="username"
                        class="w-full px-4 py-3 border border-gray-200 rounded-md text-[#2C3E50] text-sm focus:outline-none focus:ring-1 focus:ring-[#0F5E3D] focus:border-[#0F5E3D] placeholder-gray-400 transition"
                        placeholder="Username">
                </div>

                <div>
                    <input type="password" id="password" name="password" required autocomplete="current-password"
                        class="w-full px-4 py-3 border border-gray-200 rounded-md text-[#2C3E50] text-sm focus:outline-none focus:ring-1 focus:ring-[#0F5E3D] focus:border-[#0F5E3D] placeholder-gray-400 transition"
                        placeholder="Password">

                    <div class="flex items-center gap-2 mt-3 pl-1">
                        <input type="checkbox" id="showPassword" onclick="togglePassword()"
                            class="w-4 h-4 rounded border-gray-300 accent-[#0F5E3D] cursor-pointer">
                        <label for="showPassword"
                            class="text-sm text-gray-500 cursor-pointer select-none hover:text-[#2C3E50] transition-colors">
                            Show password
                        </label>
                    </div>
                </div>

                <button type="submit"
                    class="w-full bg-[#0F5E3D] hover:bg-[#0a4a2f] active:scale-[0.99] text-white font-medium py-3 rounded-md transition duration-200 mt-2">
                    Sign in
                </button>
            </form>

            <div class="mt-6 flex justify-center">
                <a href="/forgot-password" class="text-sm text-gray-500 hover:text-[#0F5E3D] transition underline-offset-2 hover:underline">
                    Forgot Password?
                </a>
            </div>

        </div>
    </div>

    <script src="/assets/js/login.js"></script>
</body>

</html>