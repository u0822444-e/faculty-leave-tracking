<?php
// views/pages/hr_manage_options.php
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hr') {
    header('Location: /login');
    exit;
}
require_once __DIR__ . '/../../config/database.php';

$activePage = 'hr_manage_options'; 

// Check URL for the active tab (defaults to leave_types)
$initialTab = $_GET['tab'] ?? 'leave_types';
if (!in_array($initialTab, ['leave_types', 'positions'])) {
    $initialTab = 'leave_types';
}

// Fetch dynamic data
$leaveTypes = $pdo->query("SELECT * FROM leave_types ORDER BY name ASC")->fetchAll();
$positions = $pdo->query("SELECT * FROM positions ORDER BY category ASC, title ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Options | DAMMC HR</title>
    <link rel="icon" type="image/png" href="/assets/images/dammc-logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="bg-gradient-to-br from-[#F1FDF6] via-[#eafaf1] to-[#dcf3e5] min-h-screen">
    <div class="flex min-h-screen">
        <div id="sidebarOverlay" onclick="closeSidebar()"
            class="fixed inset-0 bg-black/40 z-40 hidden md:hidden backdrop-blur-sm"></div>

        <?php require __DIR__ . '/../components/sidebar.php'; ?>
        
        <div class="flex-1 md:ml-64 w-full min-w-0">
            <?php
            $pageTitle = 'Manage Options';
            $pageSubtitle = 'System configuration for Leave Types & Positions';
            $pageIcon = 'list-plus';
            require __DIR__ . '/../components/topbar.php';
            ?>

            <main class="p-4 sm:p-6 lg:p-8 space-y-4">
                
                <!-- Toolbar -->
                <div class="bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl p-3 sm:p-4 shadow-sm flex flex-col lg:flex-row gap-3 lg:items-center lg:justify-between">
                    
                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Toggle Group -->
                        <div class="flex items-center bg-[#F1FDF6] rounded-lg p-1 border border-[#E0E0E0]">
                            <button id="tabLeaveTypes" onclick="switchTab('leave_types')"
                                class="flex items-center justify-center gap-2 px-4 py-1.5 rounded-md text-xs font-medium bg-white text-[#0F5E3D] shadow-sm transition-all duration-200">
                                <i data-lucide="tag" class="w-3.5 h-3.5"></i>
                                <span>Leave Types</span>
                            </button>
                            <button id="tabPositions" onclick="switchTab('positions')"
                                class="flex items-center justify-center gap-2 px-4 py-1.5 rounded-md text-xs font-medium text-[#2C3E50]/60 hover:text-[#2C3E50] transition-all duration-200">
                                <i data-lucide="badge-check" class="w-3.5 h-3.5"></i>
                                <span>Positions</span>
                            </button>
                        </div>

                        <!-- Status Filter -->
                        <select id="filterStatus" onchange="filterTable()" class="px-3 py-1.5 rounded-lg text-xs font-medium text-[#2C3E50]/80 bg-[#F1FDF6] border border-[#E0E0E0] focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] cursor-pointer">
                            <option value="all">All Status</option>
                            <option value="active" selected>Active Only</option>
                            <option value="archived">Archived Only</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-2 flex-1 lg:flex-none">
                        <!-- Search -->
                        <div class="relative flex-1 lg:w-64">
                            <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[#2C3E50]/40 pointer-events-none"></i>
                            <input type="text" id="searchInput" oninput="filterTable()" placeholder="Search..." 
                                class="w-full pl-9 pr-3 py-2 bg-[#F1FDF6]/60 border border-transparent rounded-lg text-sm text-[#2C3E50] focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:bg-white transition">
                        </div>

                        <!-- Add Button -->
                        <button id="btnAddAction" onclick="openAddModal()" 
                            class="flex items-center justify-center gap-1.5 bg-[#0F5E3D] hover:bg-[#0a4a2f] text-white px-4 py-2 rounded-lg text-sm font-medium transition shadow-sm whitespace-nowrap">
                            <i data-lucide="plus" class="w-4 h-4"></i>
                            <span id="btnAddLabel">Add Leave Type</span>
                        </button>
                    </div>
                </div>

                <!-- Unified Table -->
                <div class="bg-white/80 backdrop-blur-sm border border-[#E0E0E0] rounded-xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead class="bg-[#F9FAFB] border-b border-[#E0E0E0]">
                                <tr>
                                    <th class="text-xs font-medium text-[#2C3E50]/50 px-5 py-3 w-1/3">Name</th>
                                    <th class="text-xs font-medium text-[#2C3E50]/50 px-5 py-3 w-1/3" id="thExtraLabel">Limit Rule</th>
                                    <th class="text-xs font-medium text-[#2C3E50]/50 px-5 py-3 w-1/6">Status</th>
                                    <th class="text-right text-xs font-medium text-[#2C3E50]/50 px-5 py-3">Actions</th>
                                </tr>
                            </thead>
                            
                            <!-- Leave Types Tbody -->
                            <tbody id="tbodyLeaveTypes" class="divide-y divide-[#E0E0E0]">
                                <?php if (empty($leaveTypes)): ?>
                                    <tr><td colspan="4" class="text-center text-sm text-[#2C3E50]/50 py-8">No leave types configured.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($leaveTypes as $lt): ?>
                                        <tr class="hover:bg-[#F1FDF6]/40 transition data-row" 
                                            data-type="leave_type" 
                                            data-name="<?= strtolower(htmlspecialchars($lt['name'])) ?>"
                                            data-status="<?= $lt['is_active'] ? 'active' : 'archived' ?>">
                                            <td class="px-5 py-3 text-sm font-medium text-[#2C3E50]"><?= htmlspecialchars($lt['name']) ?></td>
                                            <td class="px-5 py-3">
                                                <?php if ($lt['is_capped']): ?>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium text-amber-700 bg-amber-50">Capped at <?= htmlspecialchars($lt['default_cap']) ?> days</span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium text-slate-600 bg-slate-100">Unlimited</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-5 py-3">
                                                <?php if ($lt['is_active']): ?>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium text-[#0F5E3D] bg-[#F1FDF6]">Active</span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium text-slate-600 bg-slate-100">Archived</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-5 py-3 text-right">
                                                <button onclick="toggleLeaveTypeStatus(<?= $lt['id'] ?>, <?= $lt['is_active'] ?>)" 
                                                    class="w-8 h-8 rounded-lg inline-flex items-center justify-center transition <?= $lt['is_active'] ? 'text-amber-600 hover:bg-amber-50' : 'text-[#0F5E3D] hover:bg-[#F1FDF6]' ?>" 
                                                    title="<?= $lt['is_active'] ? 'Archive this leave type' : 'Restore this leave type' ?>">
                                                    <i data-lucide="<?= $lt['is_active'] ? 'archive' : 'rotate-ccw' ?>" class="w-4 h-4"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>

                            <!-- Positions Tbody (Hidden by default) -->
                            <tbody id="tbodyPositions" class="hidden divide-y divide-[#E0E0E0]">
                                <?php if (empty($positions)): ?>
                                    <tr><td colspan="4" class="text-center text-sm text-[#2C3E50]/50 py-8">No positions configured.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($positions as $pos): ?>
                                        <tr class="hover:bg-[#F1FDF6]/40 transition data-row" 
                                            data-type="position" 
                                            data-name="<?= strtolower(htmlspecialchars($pos['title'])) ?>"
                                            data-status="<?= $pos['is_active'] ? 'active' : 'archived' ?>">
                                            <td class="px-5 py-3">
                                                <p class="text-sm font-medium text-[#2C3E50]"><?= htmlspecialchars($pos['title']) ?></p>
                                                <p class="text-xs text-[#2C3E50]/50 capitalize"><?= htmlspecialchars($pos['category']) ?></p>
                                            </td>
                                            <td class="px-5 py-3 text-sm text-[#2C3E50]/70">
                                                <?= ucfirst($pos['category']) ?> Role
                                            </td>
                                            <td class="px-5 py-3">
                                                <?php if ($pos['is_active']): ?>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium text-[#0F5E3D] bg-[#F1FDF6]">Active</span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium text-slate-600 bg-slate-100">Archived</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-5 py-3 text-right">
                                                <button onclick="togglePositionStatus(<?= $pos['id'] ?>, <?= $pos['is_active'] ?>)" 
                                                    class="w-8 h-8 rounded-lg inline-flex items-center justify-center transition <?= $pos['is_active'] ? 'text-amber-600 hover:bg-amber-50' : 'text-[#0F5E3D] hover:bg-[#F1FDF6]' ?>" 
                                                    title="<?= $pos['is_active'] ? 'Archive this position' : 'Restore this position' ?>">
                                                    <i data-lucide="<?= $pos['is_active'] ? 'archive' : 'rotate-ccw' ?>" class="w-4 h-4"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Empty state for search -->
                    <div id="tableEmptyState" class="hidden p-12 text-center">
                        <div class="w-12 h-12 rounded-full bg-[#F1FDF6] flex items-center justify-center mx-auto mb-3 text-[#0F5E3D]">
                            <i data-lucide="search-x" class="w-5 h-5"></i>
                        </div>
                        <p class="text-sm font-medium text-[#2C3E50]">No results found</p>
                        <p class="text-xs text-[#2C3E50]/50 mt-1">Try adjusting your search or filters.</p>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <!-- ADD MODAL -->
    <div id="addOptionModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6 transform transition-all duration-200 scale-95" id="addOptionCard">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-bold text-[#2C3E50]" id="modalTitle">Add Leave Type</h3>
                <button onclick="closeAddModal()" class="text-[#2C3E50]/40 hover:text-[#2C3E50] transition">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            
            <form id="formAddLeaveType" class="hidden">
                <div class="mb-5">
                    <label class="block text-xs font-medium text-[#2C3E50] mb-1.5">Leave Type Name *</label>
                    <input type="text" name="name" required placeholder="e.g. Emergency Leave" 
                        class="w-full px-3 py-2 border border-[#E0E0E0] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#0F5E3D]">
                </div>
                <button type="submit" class="w-full bg-[#0F5E3D] hover:bg-[#0a4a2f] text-white font-medium py-2.5 rounded-lg transition text-sm">Save Leave Type</button>
            </form>

            <form id="formAddPosition" class="hidden">
                <div class="mb-4">
                    <label class="block text-xs font-medium text-[#2C3E50] mb-1.5">Position Title *</label>
                    <input type="text" name="title" required placeholder="e.g. Registrar" 
                        class="w-full px-3 py-2 border border-[#E0E0E0] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#0F5E3D]">
                </div>
                <div class="mb-5">
                    <label class="block text-xs font-medium text-[#2C3E50] mb-1.5">Category *</label>
                    <select name="category" required class="w-full px-3 py-2 border border-[#E0E0E0] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] bg-white cursor-pointer">
                        <option value="faculty">Faculty</option>
                        <option value="staff">Staff</option>
                        <option value="leadership">Leadership</option>
                    </select>
                </div>
                <button type="submit" class="w-full bg-[#0F5E3D] hover:bg-[#0a4a2f] text-white font-medium py-2.5 rounded-lg transition text-sm">Save Position</button>
            </form>
        </div>
    </div>

    <!-- CONFIRMATION MODAL (Archive/Restore) -->
    <div id="confirmModal" class="fixed inset-0 z-[60] hidden items-center justify-center bg-black/40 backdrop-blur-sm p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6 transform transition-all duration-200 scale-95" id="confirmCard">
            <div class="flex items-center justify-center w-12 h-12 rounded-full bg-amber-50 mx-auto mb-4" id="confirmIconWrap">
                <i data-lucide="archive" class="w-6 h-6 text-amber-600" id="confirmIcon"></i>
            </div>
            <h3 class="text-lg font-bold text-[#2C3E50] text-center mb-2" id="confirmTitle">Archive Item</h3>
            <p class="text-sm text-[#2C3E50]/70 text-center mb-6" id="confirmMessage">Are you sure you want to archive this item? It will be hidden from dropdowns but remain in past records.</p>
            <div class="flex flex-col sm:flex-row gap-3">
                <button type="button" onclick="closeConfirmModal()"
                    class="flex-1 border border-[#E0E0E0] text-[#2C3E50] hover:bg-gray-50 font-medium py-2.5 rounded-lg transition text-sm">Cancel</button>
                <button type="button" id="confirmActionBtn"
                    class="flex-1 bg-amber-600 hover:bg-amber-700 text-white font-medium py-2.5 rounded-lg transition text-sm">Confirm</button>
            </div>
        </div>
    </div>

    <!-- SUCCESS MODAL -->
    <div id="successModal" class="fixed inset-0 z-[70] hidden items-center justify-center bg-black/40 backdrop-blur-sm p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-8 text-center transform transition-all duration-200 scale-95" id="successCard">
            <div class="relative mx-auto mb-5 w-16 h-16">
                <div class="absolute inset-0 rounded-full bg-[#F1FDF6] animate-ping opacity-40"></div>
                <div class="relative w-16 h-16 rounded-full bg-[#F1FDF6] flex items-center justify-center text-[#0F5E3D]">
                    <i data-lucide="check-circle" class="w-8 h-8"></i>
                </div>
            </div>
            <h3 class="text-lg font-bold text-[#2C3E50] mb-2">Success</h3>
            <p class="text-sm text-[#2C3E50]/60 mb-6" id="successMessage">Action completed successfully.</p>
            <button type="button" onclick="reloadWithTab()"
                class="w-full bg-[#0F5E3D] hover:bg-[#0a4a2f] active:scale-[0.99] text-white font-medium py-2.5 rounded-lg transition text-sm">Done</button>
        </div>
    </div>

    <!-- ERROR MODAL -->
    <div id="errorModal" class="fixed inset-0 z-[80] hidden items-center justify-center bg-black/40 backdrop-blur-sm p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-8 text-center transform transition-all duration-200 scale-95" id="errorCard">
            <div class="flex items-center justify-center w-16 h-16 rounded-full bg-red-50 mx-auto mb-5">
                <i data-lucide="alert-circle" class="w-8 h-8 text-red-600"></i>
            </div>
            <h3 class="text-lg font-bold text-[#2C3E50] mb-2">Error</h3>
            <p class="text-sm text-[#2C3E50]/60 mb-6" id="errorMessage">Something went wrong.</p>
            <button onclick="closeErrorModal()"
                class="w-full bg-[#0F5E3D] hover:bg-[#0a4a2f] text-white font-medium py-2.5 rounded-lg transition text-sm">Got it</button>
        </div>
    </div>

    <!-- LOADING OVERLAY -->
    <div id="loadingOverlay" class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-xs p-8 text-center">
            <svg class="animate-spin h-10 w-10 text-[#0F5E3D] mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <h3 class="text-base font-bold text-[#2C3E50] mb-1">Working...</h3>
        </div>
    </div>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="/assets/js/app.js"></script>
    <script>
        // Set initial tab from PHP
        let currentTab = '<?= $initialTab ?>';

        // --- Tab Switching Logic ---
        function switchTab(tab) {
            currentTab = tab;

            const btnLeave = document.getElementById('tabLeaveTypes');
            const btnPos = document.getElementById('tabPositions');
            const tbodyLeave = document.getElementById('tbodyLeaveTypes');
            const tbodyPos = document.getElementById('tbodyPositions');
            const thLabel = document.getElementById('thExtraLabel');
            const btnLabel = document.getElementById('btnAddLabel');

            const activeClass = ['bg-white', 'text-[#0F5E3D]', 'shadow-sm'];
            const inactiveClass = ['text-[#2C3E50]/60', 'hover:text-[#2C3E50]'];

            if (tab === 'leave_types') {
                btnLeave.classList.remove(...inactiveClass);
                btnLeave.classList.add(...activeClass);
                btnPos.classList.remove(...activeClass);
                btnPos.classList.add(...inactiveClass);

                tbodyLeave.classList.remove('hidden');
                tbodyPos.classList.add('hidden');
                thLabel.textContent = 'Limit Rule';
                btnLabel.textContent = 'Add Leave Type';
            } else {
                btnPos.classList.remove(...inactiveClass);
                btnPos.classList.add(...activeClass);
                btnLeave.classList.remove(...activeClass);
                btnLeave.classList.add(...inactiveClass);

                tbodyPos.classList.remove('hidden');
                tbodyLeave.classList.add('hidden');
                thLabel.textContent = 'Category';
                btnLabel.textContent = 'Add Position';
            }
            
            // Update URL without reloading to keep the tab state on refresh
            const url = new URL(window.location);
            url.searchParams.set('tab', tab);
            window.history.replaceState({}, '', url);

            filterTable(); // Re-apply filters
        }

        // --- Search & Filter Logic ---
        function filterTable() {
            const statusFilter = document.getElementById('filterStatus').value;
            const searchQuery = document.getElementById('searchInput').value.toLowerCase().trim();
            
            const activeTbody = document.getElementById(currentTab === 'leave_types' ? 'tbodyLeaveTypes' : 'tbodyPositions');
            const rows = activeTbody.querySelectorAll('.data-row');
            
            let visibleCount = 0;

            rows.forEach(row => {
                const rowStatus = row.dataset.status;
                const rowName = row.dataset.name;

                const matchStatus = (statusFilter === 'all') || (rowStatus === statusFilter);
                const matchSearch = !searchQuery || rowName.includes(searchQuery);

                if (matchStatus && matchSearch) {
                    row.classList.remove('hidden');
                    visibleCount++;
                } else {
                    row.classList.add('hidden');
                }
            });

            const emptyState = document.getElementById('tableEmptyState');
            if (visibleCount === 0) {
                emptyState.classList.remove('hidden');
            } else {
                emptyState.classList.add('hidden');
            }
        }

        // --- Modal Logic ---
        function openAddModal() {
            const modal = document.getElementById('addOptionModal');
            const card = document.getElementById('addOptionCard');
            const title = document.getElementById('modalTitle');
            const formLeave = document.getElementById('formAddLeaveType');
            const formPos = document.getElementById('formAddPosition');

            if (currentTab === 'leave_types') {
                title.textContent = 'Add Leave Type';
                formLeave.classList.remove('hidden');
                formPos.classList.add('hidden');
            } else {
                title.textContent = 'Add Position';
                formPos.classList.remove('hidden');
                formLeave.classList.add('hidden');
            }

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            setTimeout(() => card.classList.remove('scale-95'), 10);
            lucide.createIcons();
        }

        function closeAddModal() {
            const modal = document.getElementById('addOptionModal');
            const card = document.getElementById('addOptionCard');
            
            card.classList.add('scale-95');
            setTimeout(() => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                document.getElementById('formAddLeaveType').reset();
                document.getElementById('formAddPosition').reset();
            }, 200);
        }

        // --- Confirmation Modal Logic ---
        let pendingAction = null;
        let pendingId = null;

        function openConfirmModal(action, id, isCurrentlyActive, name) {
            pendingAction = action;
            pendingId = id;

            const modal = document.getElementById('confirmModal');
            const card = document.getElementById('confirmCard');
            const title = document.getElementById('confirmTitle');
            const msg = document.getElementById('confirmMessage');
            const icon = document.getElementById('confirmIcon');
            const iconWrap = document.getElementById('confirmIconWrap');
            const btn = document.getElementById('confirmActionBtn');

            if (isCurrentlyActive) {
                // Archiving
                title.textContent = 'Archive Item';
                msg.textContent = `Are you sure you want to archive "${name}"? It will be hidden from new forms but kept in history.`;
                icon.setAttribute('data-lucide', 'archive');
                iconWrap.className = 'flex items-center justify-center w-12 h-12 rounded-full bg-amber-50 mx-auto mb-4';
                icon.className = 'w-6 h-6 text-amber-600';
                btn.textContent = 'Archive';
                btn.className = 'flex-1 bg-amber-600 hover:bg-amber-700 text-white font-medium py-2.5 rounded-lg transition text-sm';
            } else {
                // Restoring
                title.textContent = 'Restore Item';
                msg.textContent = `Are you sure you want to restore "${name}"? It will become available in forms again.`;
                icon.setAttribute('data-lucide', 'rotate-ccw');
                iconWrap.className = 'flex items-center justify-center w-12 h-12 rounded-full bg-[#F1FDF6] mx-auto mb-4';
                icon.className = 'w-6 h-6 text-[#0F5E3D]';
                btn.textContent = 'Restore';
                btn.className = 'flex-1 bg-[#0F5E3D] hover:bg-[#0a4a2f] text-white font-medium py-2.5 rounded-lg transition text-sm';
            }

            btn.onclick = executePendingAction;
            
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            setTimeout(() => card.classList.remove('scale-95'), 10);
            lucide.createIcons();
        }

        function closeConfirmModal() {
            const modal = document.getElementById('confirmModal');
            const card = document.getElementById('confirmCard');
            card.classList.add('scale-95');
            setTimeout(() => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }, 200);
            pendingAction = null;
            pendingId = null;
        }

        // --- API Calls ---
        function showLoadingMsg() {
            document.getElementById('loadingOverlay').classList.remove('hidden');
            document.getElementById('loadingOverlay').classList.add('flex');
        }

        function hideLoadingMsg() {
            document.getElementById('loadingOverlay').classList.add('hidden');
            document.getElementById('loadingOverlay').classList.remove('flex');
        }

        async function apiCall(action, data) {
            closeConfirmModal();
            showLoadingMsg();
            
            const fd = new FormData();
            fd.append('action', action);
            for (const key in data) fd.append(key, data[key]);
            
            try {
                const res = await fetch('/api/hr/options', { method: 'POST', body: fd });
                const json = await res.json();
                
                hideLoadingMsg();

                if (json.success) {
                    document.getElementById('successMessage').textContent = "Action completed successfully.";
                    document.getElementById('successModal').classList.remove('hidden');
                    document.getElementById('successModal').classList.add('flex');
                } else {
                    showError('Error', json.error || 'Something went wrong');
                }
            } catch (err) {
                hideLoadingMsg();
                showError('Connection Error', 'Unable to reach the server.');
            }
        }

        // --- Reload Logic (Keeps Tab) ---
        function reloadWithTab() {
            window.location.href = '/hr/manage-options?tab=' + currentTab;
        }

        function showError(title, msg) {
            document.getElementById('errorTitle').textContent = title;
            document.getElementById('errorMessage').textContent = msg;
            document.getElementById('errorModal').classList.remove('hidden');
            document.getElementById('errorModal').classList.add('flex');
        }

        function closeErrorModal() {
            document.getElementById('errorModal').classList.add('hidden');
            document.getElementById('errorModal').classList.remove('flex');
        }

        function executePendingAction() {
            if (pendingAction === 'toggle_leave_type') apiCall('toggle_leave_type_status', { id: pendingId });
            if (pendingAction === 'toggle_position') apiCall('toggle_position_status', { id: pendingId });
        }

        // --- Form Submissions ---
        document.getElementById('formAddLeaveType').onsubmit = (e) => {
            e.preventDefault();
            closeAddModal();
            apiCall('add_leave_type', { name: e.target.name.value });
        };
        
        document.getElementById('formAddPosition').onsubmit = (e) => {
            e.preventDefault();
            closeAddModal();
            apiCall('add_position', { title: e.target.title.value, category: e.target.category.value });
        };

        // --- Trigger Confirm Actions ---
        function toggleLeaveTypeStatus(id, isActive) {
            const row = document.querySelector(`tr[data-name][data-status] button[onclick*="${id}"]`).closest('tr');
            const name = row.querySelector('td').textContent.trim();
            openConfirmModal('toggle_leave_type', id, isActive, name);
        }

        function togglePositionStatus(id, isActive) {
            const row = document.querySelector(`tr[data-name][data-status] button[onclick*="${id}"]`).closest('tr');
            const name = row.querySelector('td p').textContent.trim();
            openConfirmModal('toggle_position', id, isActive, name);
        }

        // --- Initialization on Page Load ---
        lucide.createIcons();
        switchTab(currentTab); // This applies the correct tab classes on load
    </script>
</body>
</html>