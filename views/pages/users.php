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

// Fetch all users with their linked employee info
$stmt = $pdo->query("
    SELECT 
        u.id AS user_id, u.username, u.role, u.status,
        e.first_name, e.middle_name, e.last_name, e.email,
        e.category, e.sub_category, e.employment_type,
        e.basic_salary, e.leave_credits, u.created_at
    FROM users u
    LEFT JOIN employees e ON u.employee_id = e.id
    ORDER BY u.id DESC
");
$users = $stmt->fetchAll();

// Unified role badge styles
$roleStyles = [
    'admin' => 'text-[#0F5E3D] bg-[#F1FDF6]',
    'hr' => 'text-[#0F5E3D] bg-[#F1FDF6]',
    'faculty' => 'text-[#0F5E3D] bg-[#F1FDF6]',
    'staff' => 'text-slate-600 bg-slate-100',
];

$roleLabels = [
    'admin' => 'Admin',
    'hr' => 'HR',
    'faculty' => 'Faculty',
    'staff' => 'Staff',
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users | DAMMC Admin</title>
    <link rel="icon" type="image/png" href="/assets/images/dammc-logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css">
    <style>
        /* Custom select styling */
        .custom-select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%232C3E50' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1.25em 1.25em;
            padding-right: 2.25rem;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }

        /* Disabled state — grey background */
        .custom-select:disabled {
            background-color: #f9fafb;
            cursor: not-allowed;
            opacity: 0.7;
        }

        .custom-select option {
            background-color: #ffffff;
            color: #2C3E50;
            padding: 8px 12px;
        }

        .custom-select option:checked {
            background-color: #F1FDF6;
            color: #0F5E3D;
        }

        .custom-select option:hover {
            background-color: #F1FDF6;
        }

        .custom-dropdown-item.selected {
            background-color: #F1FDF6;
            color: #0F5E3D;
            font-weight: 600;
        }

        .custom-dropdown-item.selected .check-slot i {
            opacity: 1 !important;
        }
    </style>
</head>

<body class="bg-gradient-to-br from-[#F1FDF6] via-[#eafaf1] to-[#dcf3e5] min-h-screen">

    <div class="flex min-h-screen">

        <div id="sidebarOverlay" onclick="closeSidebar()"
            class="fixed inset-0 bg-black/40 z-40 hidden md:hidden backdrop-blur-sm"></div>

        <?php
        $activePage = 'users';
        require __DIR__ . '/../components/sidebar.php';
        ?>

        <div class="flex-1 md:ml-64 w-full min-w-0">

            <?php
            $pageTitle = 'Users';
            $pageSubtitle = 'Welcome back, ' . ($_SESSION['first_name'] ?? 'Admin');
            $pageIcon = 'users';
            require __DIR__ . '/../components/topbar.php';
            ?>

            <main class="p-4 sm:p-6 lg:p-8 space-y-4 sm:space-y-6">

                <!-- Toolbar -->
                <div
                    class="relative z-20 animate-fade-in-up bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-3 sm:p-4 shadow-sm">
                    <div class="flex flex-col lg:flex-row gap-3 lg:items-center lg:justify-between">

                        <div class="flex flex-wrap items-center gap-2">

                            <!-- View toggle -->
                            <div class="flex items-center bg-[#F1FDF6] rounded-lg p-0.5">
                                <button id="viewGridBtn" onclick="setView('grid')"
                                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium text-[#2C3E50]/70 hover:text-[#2C3E50] transition">
                                    <i data-lucide="layout-grid" class="w-3.5 h-3.5"></i>
                                    <span>Grid</span>
                                </button>
                                <button id="viewListBtn" onclick="setView('list')"
                                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium bg-white text-[#0F5E3D] shadow-sm transition">
                                    <i data-lucide="list" class="w-3.5 h-3.5"></i>
                                    <span>List</span>
                                </button>
                            </div>

                            <!-- Sort dropdown -->
                            <div class="relative" id="sortWrap">
                                <button onclick="toggleSortMenu()"
                                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-[#2C3E50]/70 hover:text-[#2C3E50] hover:bg-[#F1FDF6] transition">
                                    <i data-lucide="arrow-up-down" class="w-3.5 h-3.5"></i>
                                    <span id="sortLabel">Sort by</span>
                                    <i data-lucide="chevron-down" class="w-3 h-3 transition-transform"
                                        id="sortChevron"></i>
                                </button>

                                <div id="sortMenu"
                                    class="hidden absolute left-0 top-full mt-2 w-56 bg-white rounded-xl shadow-2xl border border-[#E0E0E0] overflow-hidden z-[100]">

                                    <div class="px-3 py-2 border-b border-[#E0E0E0] bg-[#F1FDF6]/40">
                                        <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/50 font-semibold">
                                            Sort By</p>
                                    </div>

                                    <div class="py-1">
                                        <button onclick="applySort('name-asc')"
                                            class="w-full flex items-center gap-2.5 px-3 py-2 text-xs text-[#2C3E50] hover:bg-[#F1FDF6] transition text-left group">
                                            <div
                                                class="w-6 h-6 rounded-md bg-[#F1FDF6] flex items-center justify-center shrink-0 group-hover:bg-[#0F5E3D]/10">
                                                <i data-lucide="arrow-down-a-z" class="w-3.5 h-3.5 text-[#0F5E3D]"></i>
                                            </div>
                                            <span class="flex-1">Name (A → Z)</span>
                                            <i data-lucide="check"
                                                class="w-3.5 h-3.5 text-[#0F5E3D] opacity-0 sort-check"
                                                data-sort="name-asc"></i>
                                        </button>
                                        <button onclick="applySort('name-desc')"
                                            class="w-full flex items-center gap-2.5 px-3 py-2 text-xs text-[#2C3E50] hover:bg-[#F1FDF6] transition text-left group">
                                            <div
                                                class="w-6 h-6 rounded-md bg-[#F1FDF6] flex items-center justify-center shrink-0 group-hover:bg-[#0F5E3D]/10">
                                                <i data-lucide="arrow-up-z-a" class="w-3.5 h-3.5 text-[#0F5E3D]"></i>
                                            </div>
                                            <span class="flex-1">Name (Z → A)</span>
                                            <i data-lucide="check"
                                                class="w-3.5 h-3.5 text-[#0F5E3D] opacity-0 sort-check"
                                                data-sort="name-desc"></i>
                                        </button>
                                        <button onclick="applySort('role-asc')"
                                            class="w-full flex items-center gap-2.5 px-3 py-2 text-xs text-[#2C3E50] hover:bg-[#F1FDF6] transition text-left group">
                                            <div
                                                class="w-6 h-6 rounded-md bg-[#F1FDF6] flex items-center justify-center shrink-0 group-hover:bg-[#0F5E3D]/10">
                                                <i data-lucide="shield" class="w-3.5 h-3.5 text-[#0F5E3D]"></i>
                                            </div>
                                            <span class="flex-1">Role (A → Z)</span>
                                            <i data-lucide="check"
                                                class="w-3.5 h-3.5 text-[#0F5E3D] opacity-0 sort-check"
                                                data-sort="role-asc"></i>
                                        </button>
                                        <button onclick="applySort('role-desc')"
                                            class="w-full flex items-center gap-2.5 px-3 py-2 text-xs text-[#2C3E50] hover:bg-[#F1FDF6] transition text-left group">
                                            <div
                                                class="w-6 h-6 rounded-md bg-[#F1FDF6] flex items-center justify-center shrink-0 group-hover:bg-[#0F5E3D]/10">
                                                <i data-lucide="shield-check" class="w-3.5 h-3.5 text-[#0F5E3D]"></i>
                                            </div>
                                            <span class="flex-1">Role (Z → A)</span>
                                            <i data-lucide="check"
                                                class="w-3.5 h-3.5 text-[#0F5E3D] opacity-0 sort-check"
                                                data-sort="role-desc"></i>
                                        </button>
                                    </div>

                                    <div class="border-t border-[#E0E0E0] py-1">
                                        <button onclick="applySort('newest')"
                                            class="w-full flex items-center gap-2.5 px-3 py-2 text-xs text-[#2C3E50] hover:bg-[#F1FDF6] transition text-left group">
                                            <div
                                                class="w-6 h-6 rounded-md bg-[#F1FDF6] flex items-center justify-center shrink-0 group-hover:bg-[#0F5E3D]/10">
                                                <i data-lucide="clock" class="w-3.5 h-3.5 text-[#0F5E3D]"></i>
                                            </div>
                                            <span class="flex-1">Newest First</span>
                                            <i data-lucide="check"
                                                class="w-3.5 h-3.5 text-[#0F5E3D] opacity-0 sort-check"
                                                data-sort="newest"></i>
                                        </button>
                                        <button onclick="applySort('oldest')"
                                            class="w-full flex items-center gap-2.5 px-3 py-2 text-xs text-[#2C3E50] hover:bg-[#F1FDF6] transition text-left group">
                                            <div
                                                class="w-6 h-6 rounded-md bg-[#F1FDF6] flex items-center justify-center shrink-0 group-hover:bg-[#0F5E3D]/10">
                                                <i data-lucide="history" class="w-3.5 h-3.5 text-[#0F5E3D]"></i>
                                            </div>
                                            <span class="flex-1">Oldest First</span>
                                            <i data-lucide="check"
                                                class="w-3.5 h-3.5 text-[#0F5E3D] opacity-0 sort-check"
                                                data-sort="oldest"></i>
                                        </button>
                                    </div>

                                    <div class="border-t border-[#E0E0E0] py-1">
                                        <button onclick="applySort('default')"
                                            class="w-full flex items-center gap-2.5 px-3 py-2 text-xs text-[#2C3E50]/60 hover:bg-red-50 hover:text-red-600 transition text-left group">
                                            <div
                                                class="w-6 h-6 rounded-md bg-slate-100 flex items-center justify-center shrink-0 group-hover:bg-red-100">
                                                <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                                            </div>
                                            <span class="flex-1">Reset Sort</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Filter dropdown -->
                            <div class="relative" id="filterWrap">
                                <button onclick="toggleFilterMenu()"
                                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-[#2C3E50]/70 hover:text-[#2C3E50] hover:bg-[#F1FDF6] transition">
                                    <i data-lucide="filter" class="w-3.5 h-3.5"></i>
                                    <span>Filter</span>
                                    <span id="filterCount"
                                        class="hidden ml-1 px-1.5 py-0.5 rounded-full bg-[#0F5E3D] text-white text-[10px] font-semibold">0</span>
                                    <i data-lucide="chevron-down" class="w-3 h-3 transition-transform"
                                        id="filterChevron"></i>
                                </button>

                                <div id="filterMenu"
                                    class="hidden absolute left-0 top-full mt-2 w-72 bg-white rounded-xl shadow-2xl border border-[#E0E0E0] overflow-hidden z-[100]">

                                    <div
                                        class="px-4 py-3 border-b border-[#E0E0E0] bg-[#F1FDF6]/40 flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <i data-lucide="filter" class="w-3.5 h-3.5 text-[#0F5E3D]"></i>
                                            <p
                                                class="text-[10px] uppercase tracking-wider text-[#2C3E50]/70 font-semibold">
                                                Filters</p>
                                        </div>
                                        <button onclick="clearAllFilters()"
                                            class="text-[10px] font-medium text-[#0F5E3D] hover:text-[#0a4a2f] transition flex items-center gap-1">
                                            <i data-lucide="x" class="w-3 h-3"></i>
                                            <span>Clear all</span>
                                        </button>
                                    </div>

                                    <div class="p-4 space-y-4">

                                        <!-- Role filter -->
                                        <div>
                                            <div class="flex items-center gap-2 mb-2">
                                                <i data-lucide="shield" class="w-3 h-3 text-[#2C3E50]/40"></i>
                                                <label
                                                    class="block text-[10px] uppercase tracking-wider text-[#2C3E50]/60 font-semibold">Role</label>
                                            </div>
                                            <select id="filterRole" onchange="applyFilters()"
                                                class="custom-select w-full pl-3 py-2.5 bg-white border border-[#E0E0E0] rounded-lg text-xs text-[#2C3E50] focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition cursor-pointer">
                                                <option value="">All Roles</option>
                                                <option value="admin">Admin</option>
                                                <option value="hr">HR</option>
                                                <option value="faculty">Faculty</option>
                                                <option value="staff">Staff</option>
                                            </select>
                                        </div>

                                        <!-- Category filter -->
                                        <div>
                                            <div class="flex items-center gap-2 mb-2">
                                                <i data-lucide="layers" class="w-3 h-3 text-[#2C3E50]/40"></i>
                                                <label
                                                    class="block text-[10px] uppercase tracking-wider text-[#2C3E50]/60 font-semibold">Category</label>
                                            </div>
                                            <select id="filterCategory" onchange="applyFilters()"
                                                class="custom-select w-full pl-3 py-2.5 bg-white border border-[#E0E0E0] rounded-lg text-xs text-[#2C3E50] focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition cursor-pointer">
                                                <option value="">All Categories</option>
                                                <option value="faculty">Faculty</option>
                                                <option value="staff">Staff</option>
                                            </select>
                                        </div>

                                        <!-- Status filter -->
                                        <div>
                                            <div class="flex items-center gap-2 mb-2">
                                                <i data-lucide="activity" class="w-3 h-3 text-[#2C3E50]/40"></i>
                                                <label
                                                    class="block text-[10px] uppercase tracking-wider text-[#2C3E50]/60 font-semibold">Status</label>
                                            </div>
                                            <div class="grid grid-cols-3 gap-1.5 p-1 bg-[#F1FDF6] rounded-lg">
                                                <button type="button" onclick="setStatusFilter('')" data-status-btn=""
                                                    class="status-btn px-2 py-1.5 rounded-md text-[11px] font-medium transition text-[#2C3E50]/70 hover:text-[#2C3E50]">All</button>
                                                <button type="button" onclick="setStatusFilter('active')"
                                                    data-status-btn="active"
                                                    class="status-btn px-2 py-1.5 rounded-md text-[11px] font-medium transition text-[#2C3E50]/70 hover:text-[#2C3E50]">Active</button>
                                                <button type="button" onclick="setStatusFilter('inactive')"
                                                    data-status-btn="inactive"
                                                    class="status-btn px-2 py-1.5 rounded-md text-[11px] font-medium transition text-[#2C3E50]/70 hover:text-[#2C3E50]">Archived</button>
                                            </div>
                                            <input type="hidden" id="filterStatus" value="">
                                        </div>

                                    </div>
                                </div>
                            </div>

                        </div>

                        <!-- Right: Search + Add User -->
                        <div class="flex items-center gap-2">
                            <div class="relative flex-1 sm:w-72">
                                <i data-lucide="search"
                                    class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[#2C3E50]/40 pointer-events-none"></i>
                                <input type="text" id="searchInput" oninput="applyFilters()" placeholder="Search"
                                    class="w-full pl-10 pr-10 py-2 bg-[#F1FDF6]/60 border border-transparent rounded-lg text-sm text-[#2C3E50] placeholder:text-[#2C3E50]/40 focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:bg-white focus:border-transparent transition">
                                <kbd
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-mono text-[#2C3E50]/40 bg-white px-1.5 py-0.5 rounded border border-[#E0E0E0]">/</kbd>
                            </div>
                            <button onclick="openAddUserModal()"
                                class="flex items-center gap-1.5 bg-[#0F5E3D] hover:bg-[#0a4a2f] active:scale-[0.99] text-white font-medium py-2 px-4 rounded-lg transition text-xs shadow-sm hover:shadow-md whitespace-nowrap">
                                <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                                <span>Add User</span>
                            </button>
                        </div>

                    </div>
                </div>

                <!-- Grid view -->
                <div id="usersGrid" class="hidden grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 p-4">
                    <?php foreach ($users as $u):
                        $fullName = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
                        if (empty($fullName))
                            $fullName = $u['username'];
                        $initials = strtoupper(substr($u['first_name'] ?? $u['username'], 0, 1) . substr($u['last_name'] ?? '', 0, 1));
                        $roleClass = $roleStyles[$u['role']] ?? 'text-slate-600 bg-slate-100';
                        $roleLabel = $roleLabels[$u['role']] ?? ucfirst($u['role']);
                        ?>
                        <div class="grid-card group bg-white border border-[#E0E0E0] rounded-xl p-4 hover:shadow-md hover:border-[#0F5E3D]/20 transition"
                            data-id="<?= $u['user_id'] ?>" data-name="<?= strtolower($fullName) ?>"
                            data-username="<?= strtolower($u['username']) ?>" data-role="<?= $u['role'] ?>"
                            data-category="<?= $u['category'] ?? 'staff' ?>" data-status="<?= $u['status'] ?? 'active' ?>"
                            data-created="<?= strtotime($u['created_at']) ?>">

                            <div class="flex items-center justify-between mb-3">
                                <input type="checkbox"
                                    class="rowCheckbox w-4 h-4 rounded border-gray-300 accent-[#0F5E3D] cursor-pointer">
                                <span
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium <?= $roleClass ?>">
                                    <?= $roleLabel ?>
                                </span>
                            </div>

                            <div class="flex flex-col items-center text-center mb-3">
                                <div
                                    class="w-14 h-14 rounded-full bg-[#0F5E3D] text-white flex items-center justify-center font-bold text-lg mb-2 ring-4 ring-[#F1FDF6]">
                                    <?= $initials ?>
                                </div>
                                <a href="/users/<?= $u['user_id'] ?>"
                                    class="text-sm font-semibold text-[#2C3E50] truncate w-full hover:text-[#0F5E3D] transition block">
                                    <?= htmlspecialchars($fullName) ?>
                                </a>
                                <p class="text-xs text-[#2C3E50]/50 truncate w-full mt-0.5">
                                    <?= htmlspecialchars($u['email'] ?? '—') ?>
                                </p>
                            </div>

                            <div
                                class="flex items-center justify-center gap-3 text-[11px] text-[#2C3E50]/50 pb-3 mb-3 border-b border-[#E0E0E0]">
                                <span class="capitalize"><?= htmlspecialchars($u['category'] ?? 'staff') ?></span>
                                <span class="text-[#2C3E50]/20">·</span>
                                <span class="capitalize"><?= $u['status'] === 'active' ? 'Active' : 'Archived' ?></span>
                            </div>

                            <div class="flex items-center justify-center gap-1">
                                <button onclick='openEditUserModal(<?= json_encode([
                                    "id" => $u["user_id"],
                                    "first" => $u["first_name"] ?? "",
                                    "middle" => $u["middle_name"] ?? "",
                                    "last" => $u["last_name"] ?? "",
                                    "email" => $u["email"] ?? "",
                                    "username" => $u["username"],
                                    "role" => $u["role"],
                                    "category" => $u["category"] ?? "staff",
                                    "sub" => $u["sub_category"] ?? "",
                                    "emp" => $u["employment_type"] ?? "full-time",
                                    "salary" => $u["basic_salary"] ?? 0,
                                    "credits" => $u["leave_credits"] ?? 0,
                                    "status" => $u["status"],
                                ]) ?>)'
                                    class="w-8 h-8 rounded-lg flex items-center justify-center text-[#2C3E50]/60 hover:bg-[#F1FDF6] hover:text-[#0F5E3D] transition"
                                    title="Edit user">
                                    <i data-lucide="pencil" class="w-4 h-4"></i>
                                </button>
                                <button
                                    onclick="openSendResetModal(<?= $u['user_id'] ?>, '<?= htmlspecialchars($fullName, ENT_QUOTES) ?>', '<?= htmlspecialchars($u['email'] ?? '', ENT_QUOTES) ?>')"
                                    class="w-8 h-8 rounded-lg flex items-center justify-center text-[#2C3E50]/60 hover:bg-[#F1FDF6] hover:text-[#0F5E3D] transition"
                                    title="Send password reset link">
                                    <i data-lucide="mail" class="w-4 h-4"></i>
                                </button>
                                <button
                                    onclick="openArchiveUserModal(<?= $u['user_id'] ?>, '<?= htmlspecialchars($fullName, ENT_QUOTES) ?>')"
                                    class="w-8 h-8 rounded-lg flex items-center justify-center text-[#2C3E50]/60 hover:bg-amber-50 hover:text-amber-600 transition"
                                    title="Archive user">
                                    <i data-lucide="archive" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Users table -->
                <div
                    class="relative z-10 animate-fade-in-up-delay-1 bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl shadow-sm overflow-hidden">

                    <!-- Desktop table -->
                    <div class="hidden md:block overflow-x-auto">
                        <table class="w-full">
                            <thead class="border-b border-[#E0E0E0]">
                                <tr class="h-11">
                                    <th class="w-12 px-4 py-0">
                                        <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this)"
                                            class="w-4 h-4 rounded border-gray-300 accent-[#0F5E3D] cursor-pointer">
                                    </th>
                                    <th class="text-left text-xs font-medium text-[#2C3E50]/50 px-4 py-0">Name</th>
                                    <th class="text-left text-xs font-medium text-[#2C3E50]/50 px-4 py-0">Username</th>
                                    <th class="text-left text-xs font-medium text-[#2C3E50]/50 px-4 py-0">Role</th>
                                    <th class="text-left text-xs font-medium text-[#2C3E50]/50 px-4 py-0">Category</th>
                                    <th class="w-24 px-4 py-0 text-right text-xs font-medium text-[#2C3E50]/50">Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="usersTableBody" class="divide-y divide-[#E0E0E0]">
                                <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-sm text-[#2C3E50]/50 py-8">No users found.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($users as $u):
                                        $fullName = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
                                        if (empty($fullName))
                                            $fullName = $u['username'];
                                        $initials = strtoupper(substr($u['first_name'] ?? $u['username'], 0, 1) . substr($u['last_name'] ?? '', 0, 1));
                                        $roleClass = $roleStyles[$u['role']] ?? 'text-slate-600 bg-slate-100';
                                        $roleLabel = $roleLabels[$u['role']] ?? ucfirst($u['role']);
                                        ?>
                                        <tr class="hover:bg-[#F1FDF6]/40 transition h-12" data-id="<?= $u['user_id'] ?>"
                                            data-name="<?= strtolower($fullName) ?>"
                                            data-username="<?= strtolower($u['username']) ?>" data-role="<?= $u['role'] ?>"
                                            data-category="<?= $u['category'] ?? 'staff' ?>"
                                            data-status="<?= $u['status'] ?? 'active' ?>"
                                            data-created="<?= strtotime($u['created_at']) ?>">
                                            <td class="px-4 py-0">
                                                <input type="checkbox"
                                                    class="rowCheckbox w-4 h-4 rounded border-gray-300 accent-[#0F5E3D] cursor-pointer">
                                            </td>
                                            <td class="px-4 py-0">
                                                <div class="flex items-center gap-2.5">
                                                    <div
                                                        class="w-7 h-7 rounded-full bg-[#0F5E3D] text-white flex items-center justify-center font-semibold text-[10px] shrink-0">
                                                        <?= $initials ?>
                                                    </div>
                                                    <a href="/users/<?= $u['user_id'] ?>"
                                                        class="text-sm text-[#2C3E50] font-medium truncate hover:text-[#0F5E3D] transition">
                                                        <?= htmlspecialchars($fullName) ?>
                                                    </a>
                                                </div>
                                            </td>
                                            <td class="px-4 py-0">
                                                <span
                                                    class="text-sm text-[#2C3E50]/70 truncate block"><?= htmlspecialchars($u['email'] ?? '—') ?></span>
                                            </td>
                                            <td class="px-4 py-0">
                                                <span
                                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= $roleClass ?>"><?= $roleLabel ?></span>
                                            </td>
                                            <td class="px-4 py-0">
                                                <span
                                                    class="text-sm text-[#2C3E50]/70"><?= htmlspecialchars(ucfirst($u['category'] ?? 'staff')) ?></span>
                                            </td>
                                            <td class="px-4 py-0">
                                                <div class="flex items-center justify-end gap-1">
                                                    <button onclick='openEditUserModal(<?= json_encode([
                                                        "id" => $u["user_id"],
                                                        "first" => $u["first_name"] ?? "",
                                                        "middle" => $u["middle_name"] ?? "",
                                                        "last" => $u["last_name"] ?? "",
                                                        "email" => $u["email"] ?? "",
                                                        "username" => $u["username"],
                                                        "role" => $u["role"],
                                                        "category" => $u["category"] ?? "staff",
                                                        "sub" => $u["sub_category"] ?? "",
                                                        "emp" => $u["employment_type"] ?? "full-time",
                                                        "salary" => $u["basic_salary"] ?? 0,
                                                        "credits" => $u["leave_credits"] ?? 0,
                                                        "status" => $u["status"],
                                                    ]) ?>)'
                                                        class="w-7 h-7 rounded-lg flex items-center justify-center text-[#2C3E50]/60 hover:bg-[#F1FDF6] hover:text-[#0F5E3D] transition"
                                                        title="Edit user">
                                                        <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                                                    </button>
                                                    <button
                                                        onclick="openSendResetModal(<?= $u['user_id'] ?>, '<?= htmlspecialchars($fullName, ENT_QUOTES) ?>', '<?= htmlspecialchars($u['email'] ?? '', ENT_QUOTES) ?>')"
                                                        class="w-7 h-7 rounded-lg flex items-center justify-center text-[#2C3E50]/60 hover:bg-[#F1FDF6] hover:text-[#0F5E3D] transition"
                                                        title="Send password reset link">
                                                        <i data-lucide="mail" class="w-3.5 h-3.5"></i>
                                                    </button>
                                                    <button
                                                        onclick="openArchiveUserModal(<?= $u['user_id'] ?>, '<?= htmlspecialchars($fullName, ENT_QUOTES) ?>')"
                                                        class="w-7 h-7 rounded-lg flex items-center justify-center text-[#2C3E50]/60 hover:bg-amber-50 hover:text-amber-600 transition"
                                                        title="Archive user">
                                                        <i data-lucide="archive" class="w-3.5 h-3.5"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile cards -->
                    <div id="mobileCards" class="md:hidden divide-y divide-[#E0E0E0]">
                        <?php foreach ($users as $u):
                            $fullName = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
                            if (empty($fullName))
                                $fullName = $u['username'];
                            $initials = strtoupper(substr($u['first_name'] ?? $u['username'], 0, 1) . substr($u['last_name'] ?? '', 0, 1));
                            $roleClass = $roleStyles[$u['role']] ?? 'text-slate-600 bg-slate-100';
                            $roleLabel = $roleLabels[$u['role']] ?? ucfirst($u['role']);
                            ?>
                            <div class="p-4 hover:bg-[#F1FDF6]/40 transition" data-id="<?= $u['user_id'] ?>"
                                data-name="<?= strtolower($fullName) ?>" data-username="<?= strtolower($u['username']) ?>"
                                data-role="<?= $u['role'] ?>" data-category="<?= $u['category'] ?? 'staff' ?>"
                                data-status="<?= $u['status'] ?? 'active' ?>"
                                data-created="<?= strtotime($u['created_at']) ?>">
                                <div class="flex items-start gap-3">
                                    <input type="checkbox"
                                        class="rowCheckbox mt-1 w-4 h-4 rounded border-gray-300 accent-[#0F5E3D] cursor-pointer">
                                    <div
                                        class="w-9 h-9 rounded-full bg-[#0F5E3D] text-white flex items-center justify-center font-semibold text-xs shrink-0">
                                        <?= $initials ?>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between gap-2 mb-1">
                                            <a href="/users/<?= $u['user_id'] ?>"
                                                class="text-sm font-medium text-[#2C3E50] truncate hover:text-[#0F5E3D] transition block">
                                                <?= htmlspecialchars($fullName) ?>
                                            </a>
                                            <span
                                                class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium <?= $roleClass ?> shrink-0">
                                                <?= $roleLabel ?>
                                            </span>
                                        </div>
                                        <p class="text-xs text-[#2C3E50]/50 truncate">
                                            <?= htmlspecialchars($u['email'] ?? '—') ?>
                                        </p>
                                        <div class="flex items-center justify-between mt-3">
                                            <span
                                                class="text-xs text-[#2C3E50]/40 capitalize"><?= htmlspecialchars($u['category'] ?? 'staff') ?></span>
                                            <div class="flex items-center gap-1">
                                                <button onclick='openEditUserModal(<?= json_encode([
                                                    "id" => $u["user_id"],
                                                    "first" => $u["first_name"] ?? "",
                                                    "middle" => $u["middle_name"] ?? "",
                                                    "last" => $u["last_name"] ?? "",
                                                    "email" => $u["email"] ?? "",
                                                    "username" => $u["username"],
                                                    "role" => $u["role"],
                                                    "category" => $u["category"] ?? "staff",
                                                    "sub" => $u["sub_category"] ?? "",
                                                    "emp" => $u["employment_type"] ?? "full-time",
                                                    "salary" => $u["basic_salary"] ?? 0,
                                                    "credits" => $u["leave_credits"] ?? 0,
                                                    "status" => $u["status"],
                                                ]) ?>)'
                                                    class="w-7 h-7 rounded-lg flex items-center justify-center text-[#2C3E50]/60 hover:bg-[#F1FDF6] hover:text-[#0F5E3D] transition">
                                                    <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                                                </button>
                                                <button
                                                    onclick="openSendResetModal(<?= $u['user_id'] ?>, '<?= htmlspecialchars($fullName, ENT_QUOTES) ?>', '<?= htmlspecialchars($u['email'] ?? '', ENT_QUOTES) ?>')"
                                                    class="w-7 h-7 rounded-lg flex items-center justify-center text-[#2C3E50]/60 hover:bg-[#F1FDF6] hover:text-[#0F5E3D] transition">
                                                    <i data-lucide="mail" class="w-3.5 h-3.5"></i>
                                                </button>
                                                <button
                                                    onclick="openArchiveUserModal(<?= $u['user_id'] ?>, '<?= htmlspecialchars($fullName, ENT_QUOTES) ?>')"
                                                    class="w-7 h-7 rounded-lg flex items-center justify-center text-[#2C3E50]/60 hover:bg-amber-50 hover:text-amber-600 transition">
                                                    <i data-lucide="archive" class="w-3.5 h-3.5"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Empty state -->
                    <div id="emptyState" class="hidden p-12 text-center">
                        <div
                            class="w-12 h-12 rounded-full bg-[#F1FDF6] flex items-center justify-center mx-auto mb-3 text-[#0F5E3D]">
                            <i data-lucide="search-x" class="w-5 h-5"></i>
                        </div>
                        <p class="text-sm font-medium text-[#2C3E50]">No users found</p>
                        <p class="text-xs text-[#2C3E50]/50 mt-1">Try adjusting your search or filters.</p>
                    </div>

                    <!-- Pagination -->
                    <div
                        class="px-4 sm:px-6 py-3 border-t border-[#E0E0E0] flex flex-col sm:flex-row items-center justify-between gap-3">
                        <p class="text-xs text-[#2C3E50]/50">
                            Showing <span class="font-medium text-[#2C3E50]"><?= count($users) ?></span> of <span
                                class="font-medium text-[#2C3E50]"><?= count($users) ?></span> users
                        </p>
                        <div class="flex items-center gap-1">
                            <button
                                class="w-8 h-8 rounded-lg flex items-center justify-center text-[#2C3E50]/40 cursor-not-allowed"
                                disabled>
                                <i data-lucide="chevron-left" class="w-4 h-4"></i>
                            </button>
                            <button class="w-8 h-8 rounded-lg bg-[#0F5E3D] text-white text-xs font-medium">1</button>
                            <button
                                class="w-8 h-8 rounded-lg flex items-center justify-center text-[#2C3E50]/40 cursor-not-allowed"
                                disabled>
                                <i data-lucide="chevron-right" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <!-- Add user modal -->
    <div id="addUserModal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-lg transform transition-all duration-200 scale-95 overflow-hidden"
            id="addUserCard">

            <div class="flex items-center justify-between px-6 py-4 border-b border-[#E0E0E0]">
                <div>
                    <h3 class="text-base font-bold text-[#2C3E50]">Add New User</h3>
                    <p class="text-xs text-[#2C3E50]/50" id="addModalSubtitle">Step 1 of 2 — Personal Information</p>
                </div>
                <button onclick="closeAddUserModal()" class="text-[#2C3E50]/40 hover:text-[#2C3E50] transition">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <!-- Step indicator -->
            <div class="px-6 pt-5">
                <div class="flex items-center gap-2">
                    <div class="flex items-center gap-2">
                        <div id="stepDot1"
                            class="w-7 h-7 rounded-full bg-[#0F5E3D] text-white flex items-center justify-center text-xs font-semibold transition">
                            1</div>
                        <span id="stepLabel1" class="text-xs font-medium text-[#2C3E50]">Personal</span>
                    </div>
                    <div class="flex-1 h-0.5 bg-[#E0E0E0] relative">
                        <div id="stepProgressLine"
                            class="absolute inset-y-0 left-0 bg-[#0F5E3D] w-0 transition-all duration-300"></div>
                    </div>
                    <div class="flex items-center gap-2">
                        <div id="stepDot2"
                            class="w-7 h-7 rounded-full bg-[#E0E0E0] text-[#2C3E50]/40 flex items-center justify-center text-xs font-semibold transition">
                            2</div>
                        <span id="stepLabel2" class="text-xs font-medium text-[#2C3E50]/40">Account</span>
                    </div>
                </div>
            </div>

            <form id="addUserForm" novalidate>
                <input type="hidden" name="action" value="create">

                <!-- Step 1: Personal -->
                <div id="addStep1" class="p-6 space-y-4 max-h-[60vh] overflow-y-auto">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-[#2C3E50] mb-1.5">First Name *</label>
                            <input type="text" name="first_name" id="addFirstName" oninput="previewUsername()"
                                class="w-full px-3 py-2.5 border border-[#E0E0E0] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition"
                                placeholder="e.g. Juan">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-[#2C3E50] mb-1.5">Last Name *</label>
                            <input type="text" name="last_name" id="addLastName" oninput="previewUsername()"
                                class="w-full px-3 py-2.5 border border-[#E0E0E0] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition"
                                placeholder="e.g. Dela Cruz">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-[#2C3E50] mb-1.5">Middle Name <span
                                class="text-[#2C3E50]/40">(optional)</span></label>
                        <input type="text" name="middle_name"
                            class="w-full px-3 py-2.5 border border-[#E0E0E0] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition"
                            placeholder="Optional">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-[#2C3E50] mb-1.5">Email</label>
                        <input type="email" name="email" id="addEmail"
                            class="w-full px-3 py-2.5 border border-[#E0E0E0] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition"
                            placeholder="e.g. user@dammc.edu.ph">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-[#2C3E50] mb-1.5">
                            Username
                        </label>
                        <div class="relative">
                            <input type="text" id="addUsernamePreview" readonly
                                class="w-full px-3 py-2.5 bg-[#F1FDF6]/60 border border-[#E0E0E0] rounded-lg text-sm text-[#0F5E3D] font-mono cursor-not-allowed"
                                placeholder="firstname.lastname">
                            <i data-lucide="lock"
                                class="absolute right-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-[#0F5E3D]/40 pointer-events-none"></i>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Account -->
                <div id="addStep2" class="hidden p-6 space-y-4 max-h-[60vh] overflow-y-auto">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                        <!-- Role -->
                        <div>
                            <label class="block text-xs font-medium text-[#2C3E50] mb-1.5">Role *</label>
                            <select name="role" id="addRole" onchange="updateSubCategories()"
                                class="custom-select w-full pl-3 py-2.5 bg-white border border-[#E0E0E0] rounded-lg text-sm text-[#2C3E50] focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition cursor-pointer">
                                <option value="">Select role</option>
                                <option value="admin">Admin</option>
                                <option value="hr">HR</option>
                                <option value="faculty">Faculty</option>
                                <option value="staff">Staff</option>
                            </select>
                        </div>

                        <!-- Category -->
                        <div>
                            <label class="block text-xs font-medium text-[#2C3E50] mb-1.5">Category *</label>
                            <select name="category" id="addCategory" onchange="updateSubCategories()"
                                class="custom-select w-full pl-3 py-2.5 bg-white border border-[#E0E0E0] rounded-lg text-sm text-[#2C3E50] focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition cursor-pointer">
                                <option value="">Select category</option>
                                <option value="faculty">Faculty</option>
                                <option value="staff">Staff</option>
                            </select>
                        </div>
                    </div>

                    <!-- Sub-category / Department -->
                    <div>
                        <label class="block text-xs font-medium text-[#2C3E50] mb-1.5">Sub-category / Department</label>
                        <select name="sub_category" id="addSubCategory" disabled
                            class="custom-select w-full pl-3 py-2.5 bg-white border border-[#E0E0E0] rounded-lg text-sm text-[#2C3E50] focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition cursor-pointer disabled:bg-gray-50 disabled:cursor-not-allowed">
                            <option value="">Select a category first</option>
                        </select>
                    </div>

                    <!-- Employment / Salary / Credits -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

                        <!-- Employment Type -->
                        <div>
                            <label class="block text-xs font-medium text-[#2C3E50] mb-1.5">Employment *</label>
                            <select name="employment_type" id="addEmployment" onchange="handleEmploymentChange()"
                                class="custom-select w-full pl-3 py-2.5 bg-white border border-[#E0E0E0] rounded-lg text-sm text-[#2C3E50] focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition cursor-pointer">
                                <option value="full-time">Full-time</option>
                                <option value="part-time">Part-time</option>
                            </select>
                        </div>

                        <!-- Basic Salary -->
                        <div>
                            <label class="block text-xs font-medium text-[#2C3E50] mb-1.5">Monthly Salary</label>
                            <input type="number" step="0.01" name="basic_salary"
                                class="w-full px-3 py-2.5 border border-[#E0E0E0] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition"
                                placeholder="0.00">
                        </div>

                        <!-- Leave Credits -->
                        <div>
                            <label class="block text-xs font-medium text-[#2C3E50] mb-1.5">Leave Credits</label>
                            <input type="number" step="0.01" name="leave_credits" id="addLeaveCredits" value="15"
                                min="0"
                                class="w-full px-3 py-2.5 border border-[#E0E0E0] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition disabled:bg-gray-50 disabled:text-[#2C3E50]/40 disabled:cursor-not-allowed"
                                placeholder="0.00">
                        </div>
                    </div>

                    <!-- Temporary Password -->
                    <div>
                        <label class="block text-xs font-medium text-[#2C3E50] mb-1.5">
                            Temporary Password
                        </label>
                        <div class="relative">
                            <input type="text" id="addTempPassword" readonly
                                class="w-full px-3 py-2.5 pr-12 bg-[#F1FDF6]/60 border border-[#E0E0E0] rounded-lg text-sm text-[#0F5E3D] font-mono cursor-not-allowed">
                            <button type="button" onclick="regeneratePassword()"
                                class="absolute right-2 top-1/2 -translate-y-1/2 p-1.5 rounded-md text-[#0F5E3D]/60 hover:text-[#0F5E3D] hover:bg-white transition"
                                title="Regenerate password">
                                <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                            </button>
                        </div>
                    </div>
                </div>

            </form>

            <!-- Footer buttons -->
            <div class="flex gap-3 px-6 py-4 border-t border-[#E0E0E0]">
                <button type="button" id="addBackBtn" onclick="goToStep(1)"
                    class="hidden flex-1 border border-[#E0E0E0] text-[#2C3E50] hover:bg-gray-50 font-medium py-2.5 rounded-lg transition text-sm">
                    ← Back
                </button>
                <button type="button" id="addCancelBtn" onclick="closeAddUserModal()"
                    class="flex-1 border border-[#E0E0E0] text-[#2C3E50] hover:bg-gray-50 font-medium py-2.5 rounded-lg transition text-sm">
                    Cancel
                </button>
                <button type="button" id="addNextBtn" onclick="goToStep(2)"
                    class="flex-1 bg-[#0F5E3D] hover:bg-[#0a4a2f] text-white font-medium py-2.5 rounded-lg transition text-sm">
                    Next →
                </button>
                <button type="submit" id="addSubmitBtn" form="addUserForm"
                    class="hidden flex-1 bg-[#0F5E3D] hover:bg-[#0a4a2f] text-white font-medium py-2.5 rounded-lg transition text-sm">
                    Add User
                </button>
            </div>
        </div>
    </div>

    <!-- Edit user modal -->
    <div id="editUserModal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-lg transform transition-all duration-200 scale-95"
            id="editUserCard">
            <div class="flex items-center justify-between px-6 py-4 border-b border-[#E0E0E0]">
                <div>
                    <h3 class="text-base font-bold text-[#2C3E50]">Edit User</h3>
                    <p class="text-xs text-[#2C3E50]/50" id="editUserName">—</p>
                </div>
                <button onclick="closeEditUserModal()" class="text-[#2C3E50]/40 hover:text-[#2C3E50] transition">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form id="editUserForm" novalidate class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="editUserId">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-[#2C3E50] mb-1.5">First Name *</label>
                        <input type="text" name="first_name" id="editFirstName" oninput="previewEditUsername()"
                            class="w-full px-3 py-2.5 border border-[#E0E0E0] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-[#2C3E50] mb-1.5">Last Name *</label>
                        <input type="text" name="last_name" id="editLastName" oninput="previewEditUsername()"
                            class="w-full px-3 py-2.5 border border-[#E0E0E0] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-[#2C3E50] mb-1.5">Middle Name</label>
                    <input type="text" name="middle_name" id="editMiddleName"
                        class="w-full px-3 py-2.5 border border-[#E0E0E0] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition">
                </div>

                <div>
                    <label class="block text-xs font-medium text-[#2C3E50] mb-1.5">Email</label>
                    <input type="email" name="email" id="editEmail"
                        class="w-full px-3 py-2.5 border border-[#E0E0E0] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-[#2C3E50] mb-1.5">
                            Username <span class="text-[#2C3E50]/40">(auto-updates with name)</span>
                        </label>
                        <input type="text" name="username" id="editUsername"
                            class="w-full px-3 py-2.5 border border-[#E0E0E0] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-[#2C3E50] mb-1.5">New Password <span
                                class="text-[#2C3E50]/40">(blank = keep)</span></label>
                        <input type="password" name="password"
                            class="w-full px-3 py-2.5 border border-[#E0E0E0] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition"
                            placeholder="••••••••">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-[#2C3E50] mb-1.5">Role</label>
                        <select name="role" id="editRole"
                            class="custom-select w-full pl-3 py-2.5 bg-white border border-[#E0E0E0] rounded-lg text-sm text-[#2C3E50] focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition cursor-pointer">
                            <option value="admin">Admin</option>
                            <option value="hr">HR</option>
                            <option value="faculty">Faculty</option>
                            <option value="staff">Staff</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-[#2C3E50] mb-1.5">Category</label>
                        <select name="category" id="editCategory" onchange="updateEditSubCategories()"
                            class="custom-select w-full pl-3 py-2.5 bg-white border border-[#E0E0E0] rounded-lg text-sm text-[#2C3E50] focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition cursor-pointer">
                            <option value="faculty">Faculty</option>
                            <option value="staff">Staff</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-[#2C3E50] mb-1.5">Sub-category / Department</label>
                    <select name="sub_category" id="editSub"
                        class="custom-select w-full pl-3 py-2.5 bg-white border border-[#E0E0E0] rounded-lg text-sm text-[#2C3E50] focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition cursor-pointer disabled:bg-gray-50 disabled:cursor-not-allowed">
                        <option value="">Select a category first</option>
                    </select>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-[#2C3E50] mb-1.5">Employment</label>
                        <select name="employment_type" id="editEmpType" onchange="handleEditEmploymentChange()"
                            class="custom-select w-full pl-3 py-2.5 bg-white border border-[#E0E0E0] rounded-lg text-sm text-[#2C3E50] focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition cursor-pointer">
                            <option value="full-time">Full-time</option>
                            <option value="part-time">Part-time</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-[#2C3E50] mb-1.5">Salary</label>
                        <input type="number" step="0.01" name="basic_salary" id="editSalary"
                            class="w-full px-3 py-2.5 border border-[#E0E0E0] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-[#2C3E50] mb-1.5">Leave Credits</label>
                        <input type="number" step="0.01" name="leave_credits" id="editCredits" min="0"
                            class="w-full px-3 py-2.5 border border-[#E0E0E0] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition disabled:bg-gray-50 disabled:text-[#2C3E50]/40 disabled:cursor-not-allowed"
                            placeholder="0.00">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-[#2C3E50] mb-1.5">Status</label>
                    <select name="status" id="editStatus"
                        class="custom-select w-full pl-3 py-2.5 bg-white border border-[#E0E0E0] rounded-lg text-sm text-[#2C3E50] focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition cursor-pointer">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </form>

            <div class="flex gap-3 px-6 py-4 border-t border-[#E0E0E0]">
                <button type="button" onclick="closeEditUserModal()"
                    class="flex-1 border border-[#E0E0E0] text-[#2C3E50] hover:bg-gray-50 font-medium py-2.5 rounded-lg transition text-sm">Cancel</button>
                <button type="submit" form="editUserForm"
                    class="flex-1 bg-[#0F5E3D] hover:bg-[#0a4a2f] text-white font-medium py-2.5 rounded-lg transition text-sm">Save
                    Changes</button>
            </div>
        </div>
    </div>

    <!-- Archive user modal -->
    <div id="archiveUserModal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6 transform transition-all duration-200 scale-95"
            id="archiveUserCard">
            <div class="flex items-center justify-center w-12 h-12 rounded-full bg-amber-50 mx-auto mb-4">
                <i data-lucide="archive" class="w-6 h-6 text-amber-600"></i>
            </div>
            <h3 class="text-lg font-bold text-[#2C3E50] text-center mb-2">Archive User</h3>
            <p class="text-sm text-[#2C3E50]/70 text-center mb-6">
                Archive <span id="archiveUserName" class="font-semibold text-[#2C3E50]">—</span>?
                They will be marked inactive and can no longer log in.
            </p>
            <div class="flex flex-col sm:flex-row gap-3">
                <button type="button" onclick="closeArchiveUserModal()"
                    class="flex-1 border border-[#E0E0E0] text-[#2C3E50] hover:bg-gray-50 font-medium py-2.5 rounded-lg transition text-sm">Cancel</button>
                <button type="button" onclick="confirmArchiveUser()"
                    class="flex-1 bg-amber-600 hover:bg-amber-700 text-white font-medium py-2.5 rounded-lg transition text-sm">Archive</button>
            </div>
        </div>
    </div>

    <!-- Send reset link modal -->
    <div id="sendResetModal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6 transform transition-all duration-200 scale-95"
            id="sendResetCard">
            <div class="flex items-center justify-center w-12 h-12 rounded-full bg-[#F1FDF6] mx-auto mb-4">
                <i data-lucide="mail" class="w-6 h-6 text-[#0F5E3D]"></i>
            </div>
            <h3 class="text-lg font-bold text-[#2C3E50] text-center mb-2">Send Reset Link</h3>
            <p class="text-sm text-[#2C3E50]/70 text-center mb-1">Send a password reset link to</p>
            <p class="text-sm font-semibold text-[#2C3E50] text-center mb-6" id="sendResetEmail">—</p>
            <div class="flex flex-col sm:flex-row gap-3">
                <button type="button" onclick="closeSendResetModal()"
                    class="flex-1 border border-[#E0E0E0] text-[#2C3E50] hover:bg-gray-50 font-medium py-2.5 rounded-lg transition text-sm">Cancel</button>
                <button type="button" onclick="confirmSendReset()" id="sendResetConfirmBtn"
                    class="flex-1 bg-[#0F5E3D] hover:bg-[#0a4a2f] text-white font-medium py-2.5 rounded-lg transition text-sm">Send
                    Link</button>
            </div>
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
            <h3 class="text-lg font-bold text-[#2C3E50] mb-2">User Created Successfully</h3>
            <p class="text-sm text-[#2C3E50]/60 mb-6">The account has been set up and an email has been sent.</p>
            <button type="button" onclick="closeSuccessModal()"
                class="w-full bg-[#0F5E3D] hover:bg-[#0a4a2f] active:scale-[0.99] text-white font-medium py-2.5 rounded-lg transition text-sm">Done</button>
        </div>
    </div>

    <!-- Edit success modal -->
    <div id="editSuccessModal"
        class="fixed inset-0 z-[70] hidden items-center justify-center bg-black/40 backdrop-blur-sm p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-8 text-center transform transition-all duration-200 scale-95"
            id="editSuccessCard">
            <div class="relative mx-auto mb-5 w-16 h-16">
                <div class="absolute inset-0 rounded-full bg-[#F1FDF6] animate-ping opacity-40"></div>
                <div
                    class="relative w-16 h-16 rounded-full bg-[#F1FDF6] flex items-center justify-center text-[#0F5E3D]">
                    <i data-lucide="check-circle" class="w-8 h-8"></i>
                </div>
            </div>
            <h3 class="text-lg font-bold text-[#2C3E50] mb-2">Changes Saved</h3>
            <p class="text-sm text-[#2C3E50]/60 mb-6">The user's information has been updated successfully.</p>
            <button type="button" onclick="closeEditSuccessModal()"
                class="w-full bg-[#0F5E3D] hover:bg-[#0a4a2f] active:scale-[0.99] text-white font-medium py-2.5 rounded-lg transition text-sm">Done</button>
        </div>
    </div>

    <!-- Reset link sent modal -->
    <div id="resetSentModal"
        class="fixed inset-0 z-[70] hidden items-center justify-center bg-black/40 backdrop-blur-sm p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-8 text-center transform transition-all duration-200 scale-95"
            id="resetSentCard">
            <div class="relative mx-auto mb-5 w-16 h-16">
                <div class="absolute inset-0 rounded-full bg-[#F1FDF6] animate-ping opacity-40"></div>
                <div
                    class="relative w-16 h-16 rounded-full bg-[#F1FDF6] flex items-center justify-center text-[#0F5E3D]">
                    <i data-lucide="mail-check" class="w-8 h-8"></i>
                </div>
            </div>
            <h3 class="text-lg font-bold text-[#2C3E50] mb-2">Reset Link Sent</h3>
            <p class="text-sm text-[#2C3E50]/60 mb-6">
                A password reset link has been sent to<br>
                <span class="font-semibold text-[#2C3E50]" id="resetSentEmail">—</span>
            </p>
            <button type="button" onclick="closeResetSentModal()"
                class="w-full bg-[#0F5E3D] hover:bg-[#0a4a2f] active:scale-[0.99] text-white font-medium py-2.5 rounded-lg transition text-sm">Done</button>
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
            <p class="text-sm text-[#2C3E50]/60 mb-6" id="errorMessage">An unexpected error occurred. Please try again.
            </p>
            <button type="button" onclick="closeErrorModal()"
                class="w-full bg-[#0F5E3D] hover:bg-[#0a4a2f] active:scale-[0.99] text-white font-medium py-2.5 rounded-lg transition text-sm">Got
                it</button>
        </div>
    </div>

    <!-- Logout modal -->
    <?php require_once __DIR__ . '/../components/logout_modal.php'; ?>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="/assets/js/app.js"></script>
    <script src="/assets/js/profile.js"></script>
    <script src="/assets/js/notifications.js"></script>
    <script src="/assets/js/users.js"></script>
</body>

</html>