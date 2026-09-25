// ============================================
// Users page — logic
// ============================================

// Sub-category options per category
const SUB_CATEGORIES = {
    faculty: ["Elementary", "High School", "College"],
    staff: [
        "Registrar's Office",
        "Accounting Section",
        "HR",
        "Library & Guidance",
    ],
};

let currentStep = 1;
let pendingArchiveId = null;
let pendingResetId = null;

// ============================================
// Step navigation
// ============================================
function goToStep(step) {
    if (step === 2 && currentStep === 1 && !validateStep1()) return;
    currentStep = step;

    const step1 = document.getElementById("addStep1");
    const step2 = document.getElementById("addStep2");
    const dot1 = document.getElementById("stepDot1");
    const dot2 = document.getElementById("stepDot2");
    const label1 = document.getElementById("stepLabel1");
    const label2 = document.getElementById("stepLabel2");
    const line = document.getElementById("stepProgressLine");
    const subtitle = document.getElementById("addModalSubtitle");
    const backBtn = document.getElementById("addBackBtn");
    const cancelBtn = document.getElementById("addCancelBtn");
    const nextBtn = document.getElementById("addNextBtn");
    const submitBtn = document.getElementById("addSubmitBtn");

    const activeDot =
        "w-7 h-7 rounded-full bg-[#0F5E3D] text-white flex items-center justify-center text-xs font-semibold transition";
    const inactiveDot =
        "w-7 h-7 rounded-full bg-[#E0E0E0] text-[#2C3E50]/40 flex items-center justify-center text-xs font-semibold transition";

    if (step === 1) {
        step1.classList.remove("hidden");
        step2.classList.add("hidden");
        dot1.className = activeDot;
        dot2.className = inactiveDot;
        label1.className = "text-xs font-medium text-[#2C3E50]";
        label2.className = "text-xs font-medium text-[#2C3E50]/40";
        line.style.width = "0%";
        subtitle.textContent = "Step 1 of 2 — Personal Information";
        backBtn.classList.add("hidden");
        cancelBtn.classList.remove("hidden");
        nextBtn.classList.remove("hidden");
        submitBtn.classList.add("hidden");
    } else {
        step1.classList.add("hidden");
        step2.classList.remove("hidden");
        dot1.className = activeDot;
        dot2.className = activeDot;
        label1.className = "text-xs font-medium text-[#2C3E50]";
        label2.className = "text-xs font-medium text-[#2C3E50]";
        line.style.width = "100%";
        subtitle.textContent = "Step 2 of 2 — Account Setup";
        backBtn.classList.remove("hidden");
        cancelBtn.classList.add("hidden");
        nextBtn.classList.add("hidden");
        submitBtn.classList.remove("hidden");
    }
    initIcons();
}

// ============================================
// Toggle Leave Credits based on employment type
// ============================================
function handleEmploymentChange() {
    const employment = document.getElementById('addEmployment');
    const leaveCredits = document.getElementById('addLeaveCredits');

    if (!employment || !leaveCredits) return;

    if (employment.value === 'part-time') {
        leaveCredits.disabled = true;
        leaveCredits.value = '0';
        leaveCredits.placeholder = 'N/A';
    } else {
        leaveCredits.disabled = false;
        leaveCredits.placeholder = '0.00';
        // Restore default 15 when switching back to full-time if empty or 0
        if (!leaveCredits.value || leaveCredits.value === '0') {
            leaveCredits.value = '15';
        }
    }
}

// ============================================
// Validation
// ============================================
function validateStep1() {
    const first = document.getElementById("addFirstName").value.trim();
    const last = document.getElementById("addLastName").value.trim();
    if (!first) { markInvalid("addFirstName"); return false; }
    if (!last) { markInvalid("addLastName"); return false; }
    if (!document.getElementById("addUsernamePreview").value) {
        markInvalid("addFirstName");
        return false;
    }
    return true;
}

function validateStep2() {
    const role = document.getElementById("addRole").value;
    const cat = document.getElementById("addCategory").value;
    if (!role) { markInvalid("addRole"); return false; }
    if (!cat) { markInvalid("addCategory"); return false; }
    return true;
}

// ============================================
// Username + Password generators
// ============================================
function previewUsername() {
    const first = document.getElementById("addFirstName").value.trim().toLowerCase().replace(/[^a-z0-9]/g, "");
    const last = document.getElementById("addLastName").value.trim().toLowerCase().replace(/[^a-z0-9]/g, "");
    document.getElementById("addUsernamePreview").value = first && last ? `${first}.${last}` : "";
}

function previewEditUsername() {
    const first = document.getElementById("editFirstName").value.trim().toLowerCase().replace(/[^a-z0-9]/g, "");
    const last = document.getElementById("editLastName").value.trim().toLowerCase().replace(/[^a-z0-9]/g, "");
    if (first && last) {
        document.getElementById("editUsername").value = `${first}.${last}`;
    }
}

function generateTempPassword(length = 10) {
    const chars = "ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789";
    let pwd = "";
    for (let i = 0; i < length; i++)
        pwd += chars.charAt(Math.floor(Math.random() * chars.length));
    return pwd;
}
function regeneratePassword() {
    document.getElementById("addTempPassword").value = generateTempPassword();
}

// ============================================
// Sub-category dropdown
// ============================================
function updateSubCategories() {
    const cat = document.getElementById("addCategory").value;
    const sub = document.getElementById("addSubCategory");
    sub.innerHTML = "";
    if (!cat || !SUB_CATEGORIES[cat]) {
        sub.disabled = true;
        sub.innerHTML = '<option value="">Select a category first</option>';
        return;
    }
    sub.disabled = false;
    sub.innerHTML = '<option value="">Select department</option>';
    SUB_CATEGORIES[cat].forEach((s) => {
        const opt = document.createElement("option");
        opt.value = s;
        opt.textContent = s;
        sub.appendChild(opt);
    });
}

// ============================================
// Add User modal
// ============================================
function openAddUserModal() {
    document.getElementById("addUserForm").reset();
    document.getElementById("addUsernamePreview").value = "";
    const sub = document.getElementById("addSubCategory");
    sub.innerHTML = '<option value="">Select a category first</option>';
    sub.disabled = true;
    regeneratePassword();

    // ✅ Reset leave credits state
    handleEmploymentChange();

    currentStep = 1;
    goToStep(1);
    initIcons();
    showModal("addUserModal", "addUserCard");
} 
function closeAddUserModal() {
    hideModal("addUserModal", "addUserCard");
}

// ============================================
// Edit User modal
// ============================================
function openEditUserModal(data) {
    document.getElementById("editUserId").value = data.id;
    document.getElementById("editUserName").textContent =
        `${data.first} ${data.last}`.trim() || data.username;
    document.getElementById("editFirstName").value = data.first || "";
    document.getElementById("editLastName").value = data.last || "";
    document.getElementById("editMiddleName").value = data.middle || "";
    document.getElementById("editEmail").value = data.email || "";
    document.getElementById("editUsername").value = data.username || "";
    document.getElementById("editRole").value = data.role || "staff";
    document.getElementById("editCategory").value = data.category || "staff";

    // Build the sub-category <select> based on the category, then pick the stored value
    updateEditSubCategories(data.sub || "");

    document.getElementById("editEmpType").value = data.emp || "full-time";
    document.getElementById("editSalary").value = data.salary || 0;
    document.getElementById("editCredits").value = data.credits || 0;
    document.getElementById("editStatus").value = data.status || "active";

    // ✅ Apply employment rule
    handleEditEmploymentChange();

    showModal("editUserModal", "editUserCard");
}

function closeEditUserModal() {
    hideModal("editUserModal", "editUserCard");
}

// ============================================
// Toggle Edit Leave Credits based on employment type
// ============================================
function handleEditEmploymentChange() {
    const employment = document.getElementById('editEmpType');
    const leaveCredits = document.getElementById('editCredits');

    if (!employment || !leaveCredits) return;

    if (employment.value === 'part-time') {
        leaveCredits.disabled = true;
        leaveCredits.value = '0';
        leaveCredits.placeholder = 'Not applicable';
    } else {
        leaveCredits.disabled = false;
        leaveCredits.placeholder = '0.00';
    }
}

// ============================================
// Archive modal
// ============================================
function openArchiveUserModal(id, fullName) {
    pendingArchiveId = id;
    document.getElementById("archiveUserName").textContent = fullName;
    showModal("archiveUserModal", "archiveUserCard");
}
function closeArchiveUserModal() {
    hideModal("archiveUserModal", "archiveUserCard");
}

async function confirmArchiveUser() {
    if (!pendingArchiveId) return;

    const fd = new FormData();
    fd.append("action", "archive");
    fd.append("id", pendingArchiveId);

    try {
        const res = await fetch("/api/users", { method: "POST", body: fd });
        const data = await res.json();

        if (data.success) {
            closeArchiveUserModal();
            location.reload();
        } else if (data.error) {
            closeArchiveUserModal();
            showError("Could not archive user", data.error);
        }
    } catch (err) {
        console.error("Archive error:", err);
        closeArchiveUserModal();
        showError("Connection error", "Unable to reach the server. Please try again.");
    }
}

// ============================================
// Send reset link modal
// ============================================
function openSendResetModal(id, fullName, email) {
    pendingResetId = id;
    document.getElementById("sendResetEmail").textContent = email || "—";
    showModal("sendResetModal", "sendResetCard");
}
function closeSendResetModal() {
    hideModal("sendResetModal", "sendResetCard");
}

async function confirmSendReset() {
    if (!pendingResetId) return;

    const email = document.getElementById("sendResetEmail").textContent;
    closeSendResetModal();
    showLoading("Sending reset link...", "Sending a password reset link to the user.");

    const fd = new FormData();
    fd.append("action", "send_reset");
    fd.append("id", pendingResetId);

    try {
        const res = await fetch("/api/users", { method: "POST", body: fd });
        const data = await res.json();

        await new Promise((resolve) => setTimeout(resolve, 600));
        hideLoading();

        if (data.success) {
            const emailEl = document.getElementById("resetSentEmail");
            if (emailEl) emailEl.textContent = email;
            showModal("resetSentModal", "resetSentCard");
            initIcons();
        } else if (data.error) {
            showError("Could not send reset link", data.error);
        }
    } catch (err) {
        hideLoading();
        console.error("Send reset error:", err);
        showError("Connection error", "Unable to reach the server. Please try again.");
    }
}

function closeResetSentModal() {
    hideModal("resetSentModal", "resetSentCard");
    setTimeout(() => location.reload(), 200);
}

// ============================================
// View toggle — List vs Grid
// ============================================
let currentView = localStorage.getItem('usersView') || 'list';

function setView(view) {
    currentView = view;
    localStorage.setItem('usersView', view);

    const gridBtn = document.getElementById('viewGridBtn');
    const listBtn = document.getElementById('viewListBtn');

    const activeClass = 'flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium bg-white text-[#0F5E3D] shadow-sm transition';
    const inactiveClass = 'flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium text-[#2C3E50]/70 hover:text-[#2C3E50] transition';

    if (view === 'list') {
        listBtn.className = activeClass;
        gridBtn.className = inactiveClass;
    } else {
        gridBtn.className = activeClass;
        listBtn.className = inactiveClass;
    }

    applyView();
}

function applyView() {
    const listTable   = document.querySelector('#usersTableBody')?.closest('.hidden.md\\:block');
    const mobileCards = document.getElementById('mobileCards');
    const grid        = document.getElementById('usersGrid');

    if (currentView === 'grid') {
        if (grid) grid.style.display = 'grid';
        if (listTable)   listTable.style.display   = 'none';
        if (mobileCards) mobileCards.style.display = 'none';
    } else {
        if (grid) grid.style.display = 'none';
        if (listTable)   listTable.style.display   = '';
        if (mobileCards) mobileCards.style.display = '';
    }

    applyFilters();
}

function toggleSelectAll(checkbox) {
    document.querySelectorAll(".rowCheckbox").forEach((cb) => (cb.checked = checkbox.checked));
}

// ============================================
// Create user
// ============================================
async function submitCreateUser(formData) {
    showLoading("Creating user...", "Setting up the account and sending the email.");

    try {
        const res = await fetch("/api/users", { method: "POST", body: formData });
        const data = await res.json();

        await new Promise((resolve) => setTimeout(resolve, 600));
        hideLoading();

        if (data.success) {
            closeAddUserModal();
            showModal("successModal", "successCard");
            initIcons();
        } else if (data.error) {
            const errLower = data.error.toLowerCase();

            if (errLower.includes("username")) {
                markInvalid("addFirstName");
                showError("Username already taken", data.error);
            } else if (errLower.includes("email")) {
                markInvalid("addEmail");
                showError("Email already in use", data.error);
            } else if (errLower.includes("name")) {
                markInvalid("addFirstName");
                markInvalid("addLastName");
                showError("Name already exists", data.error);
            } else {
                showError("Could not create user", data.error);
            }
        }
    } catch (err) {
        hideLoading();
        console.error("Create error:", err);
        showError("Connection error", "Unable to reach the server. Please try again.");
    }
}

function closeSuccessModal() {
    hideModal("successModal", "successCard");
    setTimeout(() => location.reload(), 200);
}

// ============================================
// Edit user
// ============================================
async function submitEditUser(formData) {
    try {
        const res = await fetch("/api/users", { method: "POST", body: formData });
        const data = await res.json();

        if (data.success) {
            closeEditUserModal();
            showModal("editSuccessModal", "editSuccessCard");
            initIcons();
        } else if (data.error && data.error.toLowerCase().includes("username")) {
            markInvalid("editUsername");
        } else if (data.error) {
            showError("Could not save changes", data.error);
        } else {
            showError("Could not save changes", "The server returned an unexpected response.");
        }
    } catch (err) {
        console.error("Edit error:", err);
        showError("Connection error", "Unable to reach the server. Please check your connection and try again.");
    }
}

function closeEditSuccessModal() {
    hideModal("editSuccessModal", "editSuccessCard");
    setTimeout(() => location.reload(), 200);
}

// ============================================
// Sort menu
// ============================================
let currentSort = "default";

function toggleSortMenu() {
    const menu = document.getElementById("sortMenu");
    const filterMenu = document.getElementById("filterMenu");
    const chevron = document.getElementById("sortChevron");
    if (!menu) return;

    const willOpen = menu.classList.contains("hidden");
    menu.classList.toggle("hidden");
    if (filterMenu) filterMenu.classList.add("hidden");
    if (chevron) chevron.style.transform = willOpen ? "rotate(180deg)" : "";

    if (willOpen) {
        setTimeout(() => document.addEventListener("click", closeSortOnOutside, { once: true }), 0);
    }
}

function closeSortOnOutside(e) {
    const wrap = document.getElementById("sortWrap");
    const menu = document.getElementById("sortMenu");
    const chevron = document.getElementById("sortChevron");
    if (wrap && menu && !wrap.contains(e.target)) {
        menu.classList.add("hidden");
        if (chevron) chevron.style.transform = "";
    } else if (menu && !menu.classList.contains("hidden")) {
        document.addEventListener("click", closeSortOnOutside, { once: true });
    }
}

function applySort(type) {
    currentSort = type;

    const labels = {
        "name-asc": "Name (A → Z)",
        "name-desc": "Name (Z → A)",
        "role-asc": "Role (A → Z)",
        "role-desc": "Role (Z → A)",
        newest: "Newest First",
        oldest: "Oldest First",
        default: "Sort by",
    };
    document.getElementById("sortLabel").textContent = labels[type] || "Sort by";

    // Highlight active sort option
    document.querySelectorAll('.sort-check').forEach(c => {
        c.style.opacity = c.dataset.sort === type ? '1' : '0';
    });

    document.getElementById("sortMenu")?.classList.add("hidden");
    document.getElementById("sortChevron").style.transform = "";

    sortRows();
    applyFilters();
}

function sortRows() {
    if (currentSort === 'default') return;

    const tbody = document.getElementById('usersTableBody');
    const mobileCards = document.getElementById('mobileCards');
    const grid = document.getElementById('usersGrid');
    if (!tbody) return;

    const compare = (a, b) => {
        switch (currentSort) {
            case 'name-asc':  return (a.dataset.name || '').localeCompare(b.dataset.name || '');
            case 'name-desc': return (b.dataset.name || '').localeCompare(a.dataset.name || '');
            case 'role-asc':  return (a.dataset.role || '').localeCompare(b.dataset.role || '');
            case 'role-desc': return (b.dataset.role || '').localeCompare(a.dataset.role || '');
            case 'newest':    return parseInt(b.dataset.created || 0) - parseInt(a.dataset.created || 0);
            case 'oldest':    return parseInt(a.dataset.created || 0) - parseInt(b.dataset.created || 0);
            default:          return 0;
        }
    };

    const rows = Array.from(tbody.querySelectorAll('tr[data-name]'));
    rows.sort(compare);
    rows.forEach(row => tbody.appendChild(row));

    if (mobileCards) {
        const cards = Array.from(mobileCards.querySelectorAll(':scope > div[data-name]'));
        cards.sort(compare);
        cards.forEach(card => mobileCards.appendChild(card));
    }

    if (grid) {
        const gridCards = Array.from(grid.querySelectorAll(':scope > div[data-name]'));
        gridCards.sort(compare);
        gridCards.forEach(card => grid.appendChild(card));
    }
}

// ============================================
// Filter menu
// ============================================
function toggleFilterMenu() {
    const menu = document.getElementById("filterMenu");
    const sortMenu = document.getElementById("sortMenu");
    const chevron = document.getElementById("filterChevron");
    if (!menu) return;

    const willOpen = menu.classList.contains("hidden");
    menu.classList.toggle("hidden");
    if (sortMenu) sortMenu.classList.add("hidden");
    if (chevron) chevron.style.transform = willOpen ? "rotate(180deg)" : "";

    if (willOpen) {
        setTimeout(() => document.addEventListener("click", closeFilterOnOutside, { once: true }), 0);
    }
}

function closeFilterOnOutside(e) {
    const wrap = document.getElementById("filterWrap");
    const menu = document.getElementById("filterMenu");
    const chevron = document.getElementById("filterChevron");
    if (wrap && menu && !wrap.contains(e.target)) {
        menu.classList.add("hidden");
        if (chevron) chevron.style.transform = "";
    } else if (menu && !menu.classList.contains("hidden")) {
        document.addEventListener("click", closeFilterOnOutside, { once: true });
    }
}

function setStatusFilter(status) {
    document.getElementById("filterStatus").value = status;

    document.querySelectorAll('.status-btn').forEach(btn => {
        const isActive = btn.dataset.statusBtn === status;
        btn.className = isActive
            ? 'status-btn px-2 py-1.5 rounded-md text-[11px] font-medium transition bg-white text-[#0F5E3D] shadow-sm'
            : 'status-btn px-2 py-1.5 rounded-md text-[11px] font-medium transition text-[#2C3E50]/70 hover:text-[#2C3E50]';
    });

    applyFilters();
}

function applyFilters() {
    const search   = (document.getElementById('searchInput')?.value || '').toLowerCase();
    const role     = document.getElementById('filterRole')?.value || '';
    const category = document.getElementById('filterCategory')?.value || '';
    const status   = document.getElementById('filterStatus')?.value || '';

    const rows  = document.querySelectorAll('#usersTableBody tr[data-name]');
    const cards = document.querySelectorAll('#mobileCards > div[data-name]');
    const grids = document.querySelectorAll('#usersGrid > div[data-name]');
    let visible = 0;

    [...rows, ...cards, ...grids].forEach(row => {
        const name     = row.dataset.name || '';
        const username = row.dataset.username || '';
        const rRole    = row.dataset.role || '';
        const rCat     = row.dataset.category || '';
        const rStatus  = row.dataset.status || 'active';

        const matchesSearch   = !search   || name.includes(search) || username.includes(search);
        const matchesRole     = !role     || rRole === role;
        const matchesCategory = !category || rCat === category;
        const matchesStatus   = !status   || rStatus === status;

        const matches = matchesSearch && matchesRole && matchesCategory && matchesStatus;

        row.classList.toggle('hidden', !matches);
        if (matches) visible++;
    });

    const activeFilters = [role, category, status].filter(v => v).length;
    const badge = document.getElementById('filterCount');
    if (badge) {
        if (activeFilters > 0) {
            badge.textContent = activeFilters;
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    }

    document.getElementById('emptyState')?.classList.toggle('hidden', visible > 0);
}

function clearAllFilters() {
    const roleEl = document.getElementById("filterRole");
    const catEl = document.getElementById("filterCategory");
    const searchEl = document.getElementById("searchInput");

    if (roleEl) roleEl.value = "";
    if (catEl) catEl.value = "";
    if (searchEl) searchEl.value = "";

    setStatusFilter("");

    applyFilters();
    document.getElementById("filterMenu")?.classList.add("hidden");
    const chevron = document.getElementById("filterChevron");
    if (chevron) chevron.style.transform = "";
}

function toggleCustomDropdown(btn) {
    const wrap = btn.closest('.custom-dropdown');
    const menu = wrap.querySelector('.custom-dropdown-menu');
    const chevron = btn.querySelector('svg, [data-lucide]');

    const willOpen = menu.classList.contains('hidden');

    // Close all other custom dropdowns
    document.querySelectorAll('.custom-dropdown-menu').forEach(m => m.classList.add('hidden'));

    if (willOpen) {
        menu.classList.remove('hidden');
        if (chevron) chevron.style.transform = 'rotate(180deg)';
        setTimeout(() => document.addEventListener('click', closeCustomOnOutside, { once: true }), 0);
    }
}

function closeCustomOnOutside(e) {
    let stillOpen = false;
    document.querySelectorAll('.custom-dropdown').forEach(wrap => {
        if (wrap.contains(e.target)) stillOpen = true;
        else {
            wrap.querySelector('.custom-dropdown-menu')?.classList.add('hidden');
            const chev = wrap.querySelector('[data-lucide="chevron-down"]');
            if (chev) chev.style.transform = '';
        }
    });
    if (stillOpen) document.addEventListener('click', closeCustomOnOutside, { once: true });
}

function selectCustomOption(btn, value) {
    const wrap = btn.closest('.custom-dropdown');
    const label = wrap.querySelector('.custom-dropdown-label');
    const menu = wrap.querySelector('.custom-dropdown-menu');

    // Remove selected state from all
    wrap.querySelectorAll('.custom-dropdown-item').forEach(i => i.classList.remove('selected'));
    // Add to clicked
    btn.classList.add('selected');

    // Update label
    label.textContent = btn.querySelector('span:last-child').textContent;
    label.className = 'custom-dropdown-label text-[#2C3E50]';

    // Store value
    wrap.dataset.value = value;

    // Close
    menu.classList.add('hidden');
    const chev = wrap.querySelector('[data-lucide="chevron-down"]');
    if (chev) chev.style.transform = '';
}

// ============================================
// Edit modal — sub-category dropdown
// ============================================
function updateEditSubCategories(selectedValue = null) {
    const catEl = document.getElementById('editCategory');
    const subEl = document.getElementById('editSub');
    if (!catEl || !subEl) return;

    const cat = catEl.value;
    const options = SUB_CATEGORIES[cat] || [];

    // Preserve any value that doesn't exist in the predefined list
    // (e.g., legacy free-text sub-category values typed before this change)
    subEl.innerHTML = '';

    if (!cat) {
        subEl.disabled = true;
        subEl.innerHTML = '<option value="">Select a category first</option>';
        return;
    }

    subEl.disabled = false;
    subEl.innerHTML = '<option value="">Select department</option>';

    // Add standard options
    options.forEach((s) => {
        const opt = document.createElement('option');
        opt.value = s;
        opt.textContent = s;
        subEl.appendChild(opt);
    });

    // If the current sub-category isn't in the list, add it so it's preserved
    if (selectedValue && !options.includes(selectedValue)) {
        const legacyOpt = document.createElement('option');
        legacyOpt.value = selectedValue;
        legacyOpt.textContent = selectedValue;
        subEl.appendChild(legacyOpt);
    }

    if (selectedValue !== null && selectedValue !== undefined) {
        subEl.value = selectedValue;
    }
}

// ============================================
// Toggle Leave Credits based on employment type
// ============================================
function handleEmploymentChange() {
    const employment = document.getElementById('addEmployment');
    const leaveCredits = document.getElementById('addLeaveCredits');

    if (!employment || !leaveCredits) return;

    if (employment.value === 'part-time') {
        leaveCredits.disabled = true;
        leaveCredits.value = '0';
        leaveCredits.placeholder = 'N/A';
    } else {
        leaveCredits.disabled = false;
        leaveCredits.placeholder = '0.00';
        // Restore default 15 when switching back to full-time
        if (!leaveCredits.value || leaveCredits.value === '0') {
            leaveCredits.value = '15';
        }
    }
}

// ============================================
// Wire up forms on load
// ============================================
document.addEventListener("DOMContentLoaded", () => {
    initIcons();

    setView(currentView);

    document.getElementById("addUserForm")?.addEventListener("submit", async function (e) {
        e.preventDefault();
        if (!validateStep2()) return;

        const formData = new FormData(this);

        const username = document.getElementById("addUsernamePreview").value;
        if (!username) { markInvalid("addFirstName"); return; }
        formData.append("username", username);

        const tempPassword = document.getElementById("addTempPassword").value;
        if (!tempPassword) {
            regeneratePassword();
            formData.append("password", document.getElementById("addTempPassword").value);
        } else {
            formData.append("password", tempPassword);
        }

        await submitCreateUser(formData);
    });

    document.getElementById("editUserForm")?.addEventListener("submit", async function (e) {
        e.preventDefault();

        const first = document.getElementById("editFirstName").value.trim();
        const last = document.getElementById("editLastName").value.trim();
        const username = document.getElementById("editUsername").value.trim();

        if (!first) { markInvalid("editFirstName"); return; }
        if (!last) { markInvalid("editLastName"); return; }
        if (!username) { markInvalid("editUsername"); return; }

        await submitEditUser(new FormData(this));
    });

    document.addEventListener("keydown", function (e) {
        if (e.key === "/" && document.activeElement.tagName !== "INPUT" && document.activeElement.tagName !== "TEXTAREA") {
            e.preventDefault();
            document.getElementById("searchInput")?.focus();
        }
    });
});

