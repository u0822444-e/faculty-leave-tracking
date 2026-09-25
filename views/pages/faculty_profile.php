<?php
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    header('Location: /login');
    exit;
}
require_once __DIR__ . '/../../config/database.php';

$stmt = $pdo->prepare("
    SELECT u.username, u.role, u.status, u.created_at,
           e.first_name, e.middle_name, e.last_name, e.email,
           e.category, e.sub_category, e.employment_type,
           e.basic_salary, e.leave_credits
    FROM users u
    LEFT JOIN employees e ON u.employee_id = e.id
    WHERE u.id = ? LIMIT 1
");
$stmt->execute([$_SESSION['user_id']]);
$me = $stmt->fetch();

$initials = strtoupper(substr($me['first_name'] ?? '', 0, 1) . substr($me['last_name'] ?? '', 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | DAMMC Faculty</title>
    <link rel="icon" type="image/png" href="/assets/images/dammc-logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="bg-gradient-to-br from-[#F1FDF6] via-[#eafaf1] to-[#dcf3e5] min-h-screen">
    <div class="flex min-h-screen">
        <div id="sidebarOverlay" onclick="closeSidebar()" class="fixed inset-0 bg-black/40 z-30 hidden md:hidden backdrop-blur-sm"></div>
        <?php $activePage = 'faculty_profile'; require __DIR__ . '/../components/sidebar.php'; ?>
        <div class="flex-1 md:ml-64 w-full min-w-0">
            <?php
            $pageTitle = 'My Profile';
            $pageSubtitle = 'Your personal information';
            $pageIcon = 'user';
            require __DIR__ . '/../components/topbar.php';
            ?>
            <main class="p-4 sm:p-6 lg:p-8 space-y-4 sm:space-y-6">
                <div class="bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-6 sm:p-8 shadow-sm">
                    <div class="flex flex-col sm:flex-row items-center sm:items-start gap-5 mb-6 pb-6 border-b border-[#E0E0E0]">
                        <div class="w-20 h-20 rounded-full bg-[#0F5E3D] text-white flex items-center justify-center font-bold text-2xl ring-4 ring-[#F1FDF6] shrink-0">
                            <?= htmlspecialchars($initials) ?>
                        </div>
                        <div class="text-center sm:text-left">
                            <h2 class="text-xl font-bold text-[#2C3E50]">
                                <?= htmlspecialchars(trim(($me['first_name'] ?? '') . ' ' . ($me['last_name'] ?? ''))) ?>
                            </h2>
                            <p class="text-sm text-[#2C3E50]/60 mt-0.5">@<?= htmlspecialchars($me['username']) ?></p>
                            <span class="inline-flex items-center mt-2 px-2.5 py-0.5 rounded-full text-xs font-medium text-[#0F5E3D] bg-[#F1FDF6]">
                                <?= htmlspecialchars(ucfirst($me['role'])) ?>
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                        <?php
                        $fields = [
                            'First Name'      => $me['first_name'] ?? '—',
                            'Middle Name'     => $me['middle_name'] ?? '—',
                            'Last Name'       => $me['last_name'] ?? '—',
                            'Email'           => $me['email'] ?? '—',
                            'Category'        => ucfirst($me['category'] ?? '—'),
                            'Sub-category'    => $me['sub_category'] ?? '—',
                            'Employment Type' => ucfirst($me['employment_type'] ?? '—'),
                            'Basic Salary'    => '₱' . number_format((float)($me['basic_salary'] ?? 0), 2),
                            'Leave Credits'   => number_format((float)($me['leave_credits'] ?? 0), 2) . ' day(s)',
                            'Account Status'  => ucfirst($me['status'] ?? '—'),
                        ];
                        foreach ($fields as $label => $value): ?>
                            <div>
                                <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-1"><?= $label ?></p>
                                <p class="text-sm text-[#2C3E50]"><?= htmlspecialchars($value) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <?php require_once __DIR__ . '/../components/logout_modal.php'; ?>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="/assets/js/app.js"></script>
    <script src="/assets/js/notifications.js"></script>
    <script src="/assets/js/profile.js"></script>
</body>
</html>