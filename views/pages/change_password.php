<?php
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

$firstName = $_SESSION['first_name'] ?? $_SESSION['username'] ?? 'there';
$role      = $_SESSION['role'] ?? 'staff';

$dashboard = match ($role) {
    'admin'   => '/dashboard',
    'hr'      => '/hr/dashboard',
    'faculty' => '/faculty/dashboard',
    'staff'   => '/staff/dashboard',
    default   => '/login',
};
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome — Change Password | DAMMC</title>
    <link rel="icon" type="image/png" href="/assets/images/dammc-logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>

<body class="min-h-screen login-gradient flex items-center justify-center relative p-4">

    <div class="card-in w-full max-w-[560px] bg-white rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.08)] border border-gray-100 overflow-hidden">

        <div class="px-8 pt-8 pb-6 border-b border-gray-100">
            <div class="flex items-center gap-3 mb-6">
                <img src="/assets/images/dammc-logo.png" alt="DAMMC" class="w-10 h-10 object-contain">
                <div>
                    <p class="text-xs uppercase tracking-wider text-gray-400 font-semibold">Welcome</p>
                    <p class="text-sm font-semibold text-[#2C3E50]">Faculty Leave Tracking &amp; Payroll System</p>
                </div>
            </div>

            <h1 class="text-2xl font-semibold text-[#2C3E50]">
                Hi, <?= htmlspecialchars($firstName) ?>
            </h1>
            <p class="text-sm text-gray-500 mt-2 leading-relaxed">
                For your account's security, we recommend changing the temporary password you received in your welcome email.
                You can also skip this and change it later from your profile.
            </p>
        </div>

        <div class="px-8 py-6">

            <form id="cpForm" class="space-y-5">

                <div>
                    <label class="block text-xs font-medium text-[#2C3E50] mb-1.5">
                        Current Password <span class="text-gray-400">(from your welcome email)</span>
                    </label>
                    <input type="password" id="cpCurrent" required autocomplete="current-password"
                        class="w-full px-4 py-3 border border-gray-200 rounded-md text-sm text-[#2C3E50] focus:outline-none focus:ring-1 focus:ring-[#0F5E3D] focus:border-[#0F5E3D] transition"
                        placeholder="Enter your temporary password">
                </div>

                <div>
                    <label class="block text-xs font-medium text-[#2C3E50] mb-1.5">New Password</label>
                    <input type="password" id="cpNew" required autocomplete="new-password"
                        class="w-full px-4 py-3 border border-gray-200 rounded-md text-sm text-[#2C3E50] focus:outline-none focus:ring-1 focus:ring-[#0F5E3D] focus:border-[#0F5E3D] transition"
                        placeholder="At least 8 characters">
                    <p class="text-[11px] text-gray-400 mt-1.5">
                        Use at least 8 characters. Mix letters, numbers, and symbols for a stronger password.
                    </p>
                </div>

                <div>
                    <label class="block text-xs font-medium text-[#2C3E50] mb-1.5">Confirm New Password</label>
                    <input type="password" id="cpConfirm" required autocomplete="new-password"
                        class="w-full px-4 py-3 border border-gray-200 rounded-md text-sm text-[#2C3E50] focus:outline-none focus:ring-1 focus:ring-[#0F5E3D] focus:border-[#0F5E3D] transition"
                        placeholder="Re-enter your new password">
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" id="cpShow" onclick="cpToggleShow()"
                        class="w-4 h-4 rounded border-gray-300 accent-[#0F5E3D] cursor-pointer">
                    <label for="cpShow" class="text-sm text-gray-500 cursor-pointer select-none">
                        Show passwords
                    </label>
                </div>

                <div id="cpError" class="hidden bg-red-50 border border-red-200 text-red-700 px-3 py-2 rounded-md text-sm"></div>

                <div class="flex flex-col sm:flex-row gap-3 pt-1">
                    <button type="button" onclick="cpSkip()"
                        class="flex-1 border border-gray-200 text-[#2C3E50] hover:bg-gray-50 font-medium py-3 rounded-md transition text-sm">
                        Skip for now
                    </button>
                    <button type="submit"
                        class="flex-1 bg-[#0F5E3D] hover:bg-[#0a4a2f] active:scale-[0.99] text-white font-medium py-3 rounded-md transition text-sm">
                        Update Password
                    </button>
                </div>

            </form>

        </div>

    </div>

    <script>
        const CP_DASHBOARD = <?= json_encode($dashboard) ?>;

        function cpToggleShow() {
            const on = document.getElementById('cpShow').checked;
            ['cpCurrent', 'cpNew', 'cpConfirm'].forEach(id => {
                document.getElementById(id).type = on ? 'text' : 'password';
            });
        }

        function cpShowError(msg) {
            const el = document.getElementById('cpError');
            el.textContent = msg;
            el.classList.remove('hidden');
        }

        function cpHideError() {
            document.getElementById('cpError').classList.add('hidden');
        }

        document.getElementById('cpForm').addEventListener('submit', async function (e) {
            e.preventDefault();
            cpHideError();

            const current = document.getElementById('cpCurrent').value;
            const next    = document.getElementById('cpNew').value;
            const confirm = document.getElementById('cpConfirm').value;

            if (!current || !next || !confirm) return cpShowError('Please fill in all fields.');
            if (next.length < 8) return cpShowError('New password must be at least 8 characters.');
            if (next !== confirm) return cpShowError('New passwords do not match.');
            if (next === current) return cpShowError('New password must be different from your current one.');

            const fd = new FormData();
            fd.append('action', 'change_password');
            fd.append('current_password', current);
            fd.append('new_password', next);
            fd.append('confirm_password', confirm);

            try {
                const res = await fetch('/change-password', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    window.location.href = CP_DASHBOARD;
                } else {
                    cpShowError(data.error || 'Could not update password.');
                }
            } catch (err) {
                console.error(err);
                cpShowError('Connection error. Please try again.');
            }
        });

        async function cpSkip() {
            cpHideError();
            try {
                const fd = new FormData();
                fd.append('action', 'skip_password_change');
                await fetch('/change-password', { method: 'POST', body: fd });
            } catch (err) {
                console.error(err);
            }
            window.location.href = CP_DASHBOARD;
        }
    </script>
</body>

</html>