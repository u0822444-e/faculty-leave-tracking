<?php
// Admin contact info for customer service
$adminEmail = 'admin@dammc.edu.ph';
$adminPhone = '(062) 991-1234';
$adminHours = 'Mon–Fri, 8:00 AM – 5:00 PM';
$officeLocation = 'Registrar\'s Office, Main Building';
?>
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

            <!-- Forgot password + Contact admin -->
            <div class="mt-6 flex flex-col items-center gap-2">
                <a href="/forgot-password" class="text-sm text-gray-500 hover:text-[#0F5E3D] transition underline-offset-2 hover:underline">
                    Forgot Password?
                </a>
                <button type="button" onclick="openContactModal()"
                    class="text-sm text-gray-500 hover:text-[#0F5E3D] transition underline-offset-2 hover:underline flex items-center gap-1.5">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                    </svg>
                    <span>Need help? Contact admin</span>
                </button>
            </div>

        </div>
    </div>

    <!-- Floating help button (bottom-right) -->
    <button type="button" onclick="openContactModal()" title="Contact administrator"
        class="fixed bottom-5 right-5 w-12 h-12 rounded-full bg-[#0F5E3D] hover:bg-[#0a4a2f] text-white shadow-lg hover:shadow-xl transition flex items-center justify-center z-40">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
            <line x1="12" y1="17" x2="12.01" y2="17"></line>
        </svg>
    </button>

    <!-- Contact admin modal -->
    <div id="contactModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm p-4">
        <div id="contactCard"
            class="bg-white rounded-2xl shadow-2xl w-full max-w-md transform transition-all duration-200 scale-95 overflow-hidden">

            <!-- Header -->
            <div class="px-6 py-5 border-b border-gray-100 flex items-start justify-between">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-lg bg-[#F1FDF6] flex items-center justify-center text-[#0F5E3D] shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-[#2C3E50]">Need help signing in?</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Reach out to the administrator for assistance.</p>
                    </div>
                </div>
                <button onclick="closeContactModal()" class="text-gray-400 hover:text-[#2C3E50] transition shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>

            <!-- Body -->
            <div class="p-6 space-y-5">

                <!-- Common issues -->
                <div>
                    <p class="text-xs uppercase tracking-wider text-gray-400 font-semibold mb-2">Common issues</p>
                    <ul class="space-y-1.5 text-sm text-[#2C3E50]">
                        <li class="flex items-start gap-2">
                            <span class="text-[#0F5E3D] mt-0.5">•</span>
                            <span>Forgot your password — try the <a href="/forgot-password" class="text-[#0F5E3D] underline underline-offset-2">Forgot Password</a> link first.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-[#0F5E3D] mt-0.5">•</span>
                            <span>Account deactivated or locked.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-[#0F5E3D] mt-0.5">•</span>
                            <span>Wrong or forgotten username.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-[#0F5E3D] mt-0.5">•</span>
                            <span>New employee — no account yet.</span>
                        </li>
                    </ul>
                </div>

                <!-- Contact methods -->
                <div>
                    <p class="text-xs uppercase tracking-wider text-gray-400 font-semibold mb-2">Contact</p>
                    <div class="space-y-2.5">

                        <!-- Email -->
                        <a href="mailto:<?= htmlspecialchars($adminEmail) ?>?subject=Login%20help%20request"
                            class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 hover:border-[#0F5E3D]/30 hover:bg-[#F1FDF6]/40 transition">
                            <div class="w-9 h-9 rounded-lg bg-[#F1FDF6] flex items-center justify-center text-[#0F5E3D] shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                    <polyline points="22,6 12,13 2,6"></polyline>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-[#2C3E50]">Email</p>
                                <p class="text-xs text-gray-500 truncate"><?= htmlspecialchars($adminEmail) ?></p>
                            </div>
                        </a>

                        <!-- Phone -->
                        <a href="tel:<?= htmlspecialchars(preg_replace('/[^0-9+]/', '', $adminPhone)) ?>"
                            class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 hover:border-[#0F5E3D]/30 hover:bg-[#F1FDF6]/40 transition">
                            <div class="w-9 h-9 rounded-lg bg-[#F1FDF6] flex items-center justify-center text-[#0F5E3D] shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-[#2C3E50]">Phone</p>
                                <p class="text-xs text-gray-500"><?= htmlspecialchars($adminPhone) ?></p>
                            </div>
                        </a>

                        <!-- Office -->
                        <div class="flex items-center gap-3 p-3 rounded-lg border border-gray-200">
                            <div class="w-9 h-9 rounded-lg bg-[#F1FDF6] flex items-center justify-center text-[#0F5E3D] shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                    <circle cx="12" cy="10" r="3"></circle>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-[#2C3E50]">Visit</p>
                                <p class="text-xs text-gray-500"><?= htmlspecialchars($officeLocation) ?></p>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Hours -->
                <div class="bg-[#F1FDF6]/60 rounded-lg px-3 py-2.5 text-xs text-[#2C3E50]/70 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                    <span>Available <strong class="font-semibold"><?= htmlspecialchars($adminHours) ?></strong></span>
                </div>

            </div>

            <!-- Footer -->
            <div class="px-6 py-4 border-t border-gray-100">
                <button onclick="closeContactModal()"
                    class="w-full border border-gray-200 text-[#2C3E50] hover:bg-gray-50 font-medium py-2.5 rounded-lg transition text-sm">
                    Close
                </button>
            </div>
        </div>
    </div>

    <script>
        function openContactModal() {
            const m = document.getElementById('contactModal');
            const c = document.getElementById('contactCard');
            m.classList.remove('hidden');
            m.classList.add('flex');
            requestAnimationFrame(() => {
                c.classList.remove('scale-95');
                c.classList.add('scale-100');
            });
        }

        function closeContactModal() {
            const m = document.getElementById('contactModal');
            const c = document.getElementById('contactCard');
            c.classList.remove('scale-100');
            c.classList.add('scale-95');
            setTimeout(() => {
                m.classList.add('hidden');
                m.classList.remove('flex');
            }, 150);
        }

        // Close on outside click
        document.getElementById('contactModal')?.addEventListener('click', function (e) {
            if (e.target === this) closeContactModal();
        });

        // Close on Escape
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeContactModal();
        });
    </script>

    <script src="/assets/js/login.js"></script>
    <script src="/assets/js/support-chat.js"></script>
</body>

</html>