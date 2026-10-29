// ============================================
// Users page — logic (HR manages users)
// ============================================

const SUB_CATEGORIES = {
  faculty: ["Elementary", "High School", "College"],
  staff: [
    "Accounting Section",
    "Registrar's Office",
    "Library & Guidance",
    "Registrar's Head",
  ],
};

const YEAR_LEVELS_OR_DEPARTMENTS = {
  Elementary: ["Grade 1", "Grade 2", "Grade 3", "Grade 4", "Grade 5", "Grade 6"],
  "High School": ["Grade 7", "Grade 8", "Grade 9", "Grade 10", "Grade 11", "Grade 12"],
  College: [
    "College of Teacher Education",
    "College of Business Education",
    "College of Computer Studies",
    "College of Criminal Justice Education",
    "College of Social Work",
  ],
};

// Only faculty sub-categories have a position list.
// Staff sub-categories are intentionally absent — they fall to DEFAULT_POSITIONS.
const POSITIONS_BY_CONTEXT = {
  Elementary: [
    "N/A", "Elementary Principal", "Assistant Principal", "Department Head",
    "Master Teacher", "Teacher I", "Teacher II", "Teacher III",
    "Guidance Counselor", "Librarian", "Registrar Staff", "Other",
  ],
  "High School": [
    "N/A", "High School Principal", "Assistant Principal", "Department Head",
    "Master Teacher", "Teacher I", "Teacher II", "Teacher III",
    "Guidance Counselor", "Librarian", "Registrar Staff", "Other",
  ],
  College: [
    "N/A", "College Dean", "Academic Dean",
    "Professor",
    "Associate Professor", "Assistant Professor",
    "Instructor",
    "Lecturer", "Guidance Counselor", "Librarian", "Other",
  ],
};

const DEFAULT_POSITIONS = ["N/A"];

// ============================================
// Role ↔ Category coupling (no admin role)
// ============================================
const ROLE_TO_CATEGORY = {
  hr: "staff",
  staff: "staff",
  faculty: "faculty",
};

const CATEGORY_TO_ROLE = {
  faculty: "faculty",
  staff: "staff",
};

let currentStep = 1;
let currentView = localStorage.getItem("usersView") || "list";
let currentSort = "default";

let pendingArchiveId = null;
let pendingRestoreId = null;
let pendingResetId = null;
let pendingProfileEditId = null;
let pendingProfileEditAction = null;

// ============================================
// Server-side search
// ============================================
let searchDebounceTimer = null;

function scheduleGlobalSearch() {
  clearTimeout(searchDebounceTimer);
  searchDebounceTimer = setTimeout(submitGlobalSearch, 350);
}

function submitGlobalSearch() {
  const input = document.getElementById("searchInput");
  const form = document.getElementById("searchForm");
  if (!input || !form) return;

  const q = input.value.trim();
  const status = form.querySelector('input[name="status"]')?.value || "";

  const params = new URLSearchParams();
  if (status) params.set("status", status);
  if (q) params.set("q", q);

  window.location.href = "/hr/users" + (params.toString() ? "?" + params.toString() : "");
}

// ============================================
// Role ↔ Category coupling enforcement
// ============================================
function enforceRoleCategoryCoupling(prefix = "add", source = "") {
  const roleEl = document.getElementById(`${prefix}Role`);
  const catEl = document.getElementById(`${prefix}Category`);
  const hiddenCatEl = document.getElementById(`${prefix}CategoryHidden`);
  if (!roleEl || !catEl) return;

  const role = roleEl.value;
  const cat = catEl.value;

  if (source === "role" && role) {
    const requiredCat = ROLE_TO_CATEGORY[role];
    if (requiredCat) {
      catEl.value = requiredCat;
      catEl.disabled = true;
      if (hiddenCatEl) hiddenCatEl.value = requiredCat;
      updateSubCategories(prefix);
    }
    return;
  }

  if (source === "category" && cat) {
    const requiredRole = CATEGORY_TO_ROLE[cat];
    if (requiredRole) {
      if (role === "faculty" && cat === "staff") roleEl.value = "staff";
      if (role === "staff" && cat === "faculty") roleEl.value = "faculty";
      if (role === "hr" && cat === "faculty") roleEl.value = "staff";
    }
    if (hiddenCatEl && catEl.disabled) hiddenCatEl.value = catEl.value;
    return;
  }
}

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
  if (typeof initIcons === "function") initIcons();
}

// ============================================
// Employment change
// ============================================
function handleEmploymentChange(prefix = "add") {
  // Leave Credits input has been removed from the UI.
  // This function is kept for backward compatibility but does nothing.
}

// ============================================
// Position visibility
// ============================================
function shouldShowPosition(prefix) {
  const catEl = document.getElementById(`${prefix}Category`);
  if (!catEl) return false;
  return catEl.value === "faculty";
}

function togglePositionField(prefix) {
  const positionEl =
    document.getElementById(`${prefix}Position`) ||
    document.getElementById(`${prefix}PositionSelect`);
  if (!positionEl) return;

  const wrap =
    document.getElementById(`${prefix}PositionWrap`) ||
    positionEl.closest("[data-field='position']") ||
    positionEl.parentElement;

  const show = shouldShowPosition(prefix);

  if (wrap) {
    wrap.classList.toggle("hidden", !show);
  }

  if (!show) {
    let hidden = document.getElementById(`${prefix}PositionHidden`);
    if (!hidden) {
      hidden = document.createElement("input");
      hidden.type = "hidden";
      hidden.name = "position";
      hidden.id = `${prefix}PositionHidden`;
      positionEl.parentElement.appendChild(hidden);
    }
    hidden.value = "N/A";
    positionEl.removeAttribute("required");
    positionEl.disabled = true;
    positionEl.value = "N/A";
  } else {
    const hidden = document.getElementById(`${prefix}PositionHidden`);
    if (hidden) hidden.remove();
    positionEl.disabled = false;
    positionEl.setAttribute("required", "required");
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
  const pwd = document.getElementById("addTempPassword").value.trim();

  if (!role) { markInvalid("addRole"); return false; }
  if (!cat) { markInvalid("addCategory"); return false; }
  if (!pwd || pwd.length < 8) { markInvalid("addTempPassword"); return false; }

  // Position is only required for faculty
  if (role === "faculty") {
    const pos = document.getElementById("addPosition").value;
    if (!pos) { markInvalid("addPosition"); return false; }
  }

  if (role === "faculty" && cat !== "faculty") {
    showError("Role mismatch", "Faculty role requires Faculty category.");
    return false;
  }
  if (role === "staff" && cat !== "staff") {
    showError("Role mismatch", "Staff role requires Staff category.");
    return false;
  }
  if (role === "hr" && cat !== "staff") {
    showError("Role mismatch", "HR role requires Staff category.");
    return false;
  }
  return true;
}

// ============================================
// Username previews
// ============================================
function previewUsername() {
  const first = document.getElementById("addFirstName").value.trim().toLowerCase().replace(/[^a-z0-9]/g, "");
  const last = document.getElementById("addLastName").value.trim().toLowerCase().replace(/[^a-z0-9]/g, "");
  document.getElementById("addUsernamePreview").value = first && last ? `${first}.${last}` : "";
}

function previewEditUsername() {
  const first = document.getElementById("editFirstName").value.trim().toLowerCase().replace(/[^a-z0-9]/g, "");
  const last = document.getElementById("editLastName").value.trim().toLowerCase().replace(/[^a-z0-9]/g, "");
  if (first && last) document.getElementById("editUsername").value = `${first}.${last}`;
}

// ============================================
// Temp password
// ============================================
function generateTempPassword(length = 10) {
  const chars = "ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789";
  let pwd = "";
  for (let i = 0; i < length; i++) pwd += chars.charAt(Math.floor(Math.random() * chars.length));
  return pwd;
}
function regeneratePassword() {
  document.getElementById("addTempPassword").value = generateTempPassword();
}

// ============================================
// Cascading dropdowns
// ============================================
function updateSubCategories(prefix = "add") {
  const catEl = document.getElementById(`${prefix}Category`);
  const subEl =
    document.getElementById(`${prefix}SubCategory`) ||
    document.getElementById(`${prefix}Sub`);
  const yearEl = document.getElementById(`${prefix}YearLevel`);
  if (!catEl || !subEl) return;

  const cat = catEl.value;
  subEl.innerHTML = "";

  if (!cat || !SUB_CATEGORIES[cat]) {
    subEl.disabled = true;
    subEl.innerHTML = '<option value="">Select a category first</option>';
    if (yearEl) {
      yearEl.disabled = true;
      yearEl.innerHTML = '<option value="">Select a sub-category first</option>';
    }
    populatePositionDropdown(`${prefix}Position`, "N/A", "");
    togglePositionField(prefix);
    return;
  }

  subEl.disabled = false;
  subEl.innerHTML = '<option value="">Select department</option>';
  SUB_CATEGORIES[cat].forEach((s) => {
    const opt = document.createElement("option");
    opt.value = s;
    opt.textContent = s;
    subEl.appendChild(opt);
  });

  if (yearEl) {
    yearEl.disabled = true;
    yearEl.innerHTML = '<option value="">Select a sub-category first</option>';
  }
  populatePositionDropdown(`${prefix}Position`, "N/A", "");
  togglePositionField(prefix);
}

function updateYearLevelOrDepartment(prefix, selectedValue = null) {
  const subEl =
    document.getElementById(`${prefix}SubCategory`) ||
    document.getElementById(`${prefix}Sub`);
  const yearEl = document.getElementById(`${prefix}YearLevel`);
  if (!subEl || !yearEl) return;
  const sub = subEl.value;
  const options = YEAR_LEVELS_OR_DEPARTMENTS[sub] || [];
  yearEl.innerHTML = "";

  if (!sub) {
    yearEl.disabled = true;
    yearEl.innerHTML = '<option value="">Select a sub-category first</option>';
    return;
  }
  if (options.length === 0) {
    yearEl.disabled = true;
    yearEl.innerHTML = '<option value="">No year levels/departments available</option>';
    return;
  }
  yearEl.disabled = false;
  yearEl.innerHTML = '<option value="">Select year level / department</option>';
  options.forEach((opt) => {
    const o = document.createElement("option");
    o.value = opt;
    o.textContent = opt;
    yearEl.appendChild(o);
  });
  if (selectedValue && !options.includes(selectedValue)) {
    const legacy = document.createElement("option");
    legacy.value = selectedValue;
    legacy.textContent = selectedValue;
    yearEl.appendChild(legacy);
  }
  if (selectedValue !== null && selectedValue !== undefined) yearEl.value = selectedValue;
}

function populatePositionDropdown(selectId, selectedValue = null, subCategory = "") {
  const el = document.getElementById(selectId);
  if (!el) return;
  const allowed = POSITIONS_BY_CONTEXT[subCategory] || DEFAULT_POSITIONS;
  const options = [...allowed];
  if (selectedValue && !options.includes(selectedValue)) options.push(selectedValue);
  el.innerHTML = '<option value="">Select position</option>';
  options.forEach((p) => {
    const o = document.createElement("option");
    o.value = p;
    o.textContent = p;
    el.appendChild(o);
  });
  if (selectedValue !== null && selectedValue !== undefined) el.value = selectedValue;
}

// ============================================
// Filter dropdowns (client-side)
// ============================================
function updateFilterSubCategories(selectedValue = null) {
  const catEl = document.getElementById("filterCategory");
  const subEl = document.getElementById("filterSubCategory");
  const yearEl = document.getElementById("filterYearLevel");
  if (!subEl) return;
  const cat = catEl?.value || "";
  const options = SUB_CATEGORIES[cat] || [];
  subEl.innerHTML = '<option value="">All Departments</option>';

  if (!cat) {
    const allSubs = new Set();
    Object.values(SUB_CATEGORIES).forEach((arr) => arr.forEach((s) => allSubs.add(s)));
    [...allSubs].sort().forEach((s) => {
      const o = document.createElement("option");
      o.value = s.toLowerCase();
      o.textContent = s;
      subEl.appendChild(o);
    });
  } else {
    options.forEach((s) => {
      const o = document.createElement("option");
      o.value = s.toLowerCase();
      o.textContent = s;
      subEl.appendChild(o);
    });
  }
  if (selectedValue !== null) subEl.value = selectedValue;
  if (yearEl) yearEl.innerHTML = '<option value="">All Year Levels</option>';
}

function updateFilterYearLevels(selectedValue = null) {
  const subEl = document.getElementById("filterSubCategory");
  const yearEl = document.getElementById("filterYearLevel");
  if (!yearEl) return;
  const sub = subEl?.value || "";
  yearEl.innerHTML = '<option value="">All Year Levels</option>';

  if (!sub) {
    const allYears = new Set();
    Object.values(YEAR_LEVELS_OR_DEPARTMENTS).forEach((arr) => arr.forEach((y) => allYears.add(y)));
    [...allYears].sort().forEach((y) => {
      const o = document.createElement("option");
      o.value = y.toLowerCase();
      o.textContent = y;
      yearEl.appendChild(o);
    });
  } else {
    const matchKey = Object.keys(YEAR_LEVELS_OR_DEPARTMENTS).find((k) => k.toLowerCase() === sub);
    const options = matchKey ? YEAR_LEVELS_OR_DEPARTMENTS[matchKey] : [];
    options.forEach((y) => {
      const o = document.createElement("option");
      o.value = y.toLowerCase();
      o.textContent = y;
      yearEl.appendChild(o);
    });
  }
  if (selectedValue !== null) yearEl.value = selectedValue;
}

// ============================================
// Add user modal
// ============================================
function openAddUserModal() {
  document.getElementById("addUserForm").reset();
  document.getElementById("addUsernamePreview").value = "";

  const sub = document.getElementById("addSubCategory");
  sub.innerHTML = '<option value="">Select a category first</option>';
  sub.disabled = true;

  const yearEl = document.getElementById("addYearLevel");
  if (yearEl) {
    yearEl.disabled = true;
    yearEl.innerHTML = '<option value="">Select a sub-category first</option>';
  }

  const catEl = document.getElementById("addCategory");
  if (catEl) catEl.disabled = false;

  const addCatHidden = document.getElementById("addCategoryHidden");
  if (addCatHidden) addCatHidden.value = "";

  // REMOVED: Leave Pay, Basic Salary, Leave Credits inputs no longer exist

  const pwdEl = document.getElementById("addTempPassword");
  if (!pwdEl.value) regeneratePassword();

  populatePositionDropdown("addPosition", "N/A", "");
  togglePositionField("add");

  currentStep = 1;
  goToStep(1);
  if (typeof initIcons === "function") initIcons();
  showModal("addUserModal", "addUserCard");
}

function closeAddUserModal() {
  hideModal("addUserModal", "addUserCard");
}

// ============================================
// Edit user modal
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

  const catEl = document.getElementById("editCategory");
  const hiddenCatEl = document.getElementById("editCategoryHidden");

  catEl.disabled = true;
  if (hiddenCatEl) hiddenCatEl.value = catEl.value;

  updateSubCategories("edit");

  const subEl = document.getElementById("editSub");
  if (subEl) subEl.value = data.sub || "";
  updateYearLevelOrDepartment("edit", data.year_level || "");
  populatePositionDropdown("editPosition", data.position || "N/A", data.sub || "");
  togglePositionField("edit");

  document.getElementById("editEmpType").value = data.emp || "full-time";

  // REMOVED: editBasicSalary, editLeavePay, editCredits inputs no longer exist

  document.getElementById("editStatus").value = data.status || "active";

  showModal("editUserModal", "editUserCard");
}

function closeEditUserModal() {
  hideModal("editUserModal", "editUserCard");
}

// ============================================
// Approve / Reject Profile Edit
// ============================================
function openProfileEditModal(userId, fullName, action) {
  pendingProfileEditId = userId;
  pendingProfileEditAction = action;

  const title = document.getElementById("editReqTitle");
  const desc = document.getElementById("editReqDesc");
  const btn = document.getElementById("editReqConfirmBtn");
  const iconWrap = document.getElementById("editReqIconWrap");
  const icon = document.getElementById("editReqIcon");

  if (action === "approve") {
    title.textContent = "Approve Profile Edit";
    desc.innerHTML = `Approve the profile update request for <span class="font-semibold text-[#2C3E50]">${fullName}</span>?`;
    btn.textContent = "Approve";
    btn.className = "flex-1 bg-[#0F5E3D] hover:bg-[#0a4a2f] text-white font-medium py-2.5 rounded-lg transition text-sm";
    iconWrap.className = "flex items-center justify-center w-12 h-12 rounded-full bg-[#F1FDF6] mx-auto mb-4";
    icon.setAttribute("data-lucide", "check-circle");
    icon.className = "w-6 h-6 text-[#0F5E3D]";
  } else {
    title.textContent = "Reject Profile Edit";
    desc.innerHTML = `Reject the profile update request for <span class="font-semibold text-[#2C3E50]">${fullName}</span>?`;
    btn.textContent = "Reject";
    btn.className = "flex-1 bg-red-600 hover:bg-red-700 text-white font-medium py-2.5 rounded-lg transition text-sm";
    iconWrap.className = "flex items-center justify-center w-12 h-12 rounded-full bg-red-50 mx-auto mb-4";
    icon.setAttribute("data-lucide", "x-circle");
    icon.className = "w-6 h-6 text-red-600";
  }

  if (typeof initIcons === "function") initIcons();
  showModal("editRequestModal", "editRequestCard");
}

function closeProfileEditModal() {
  hideModal("editRequestModal", "editRequestCard");
  pendingProfileEditId = null;
  pendingProfileEditAction = null;
}

async function confirmProfileEditAction() {
  if (!pendingProfileEditId || !pendingProfileEditAction) return;

  const action =
    pendingProfileEditAction === "approve" ? "approve_profile_edit" : "reject_profile_edit";
  const fd = new FormData();
  fd.append("action", action);
  fd.append("id", pendingProfileEditId);

  closeProfileEditModal();
  showLoading(
    pendingProfileEditAction === "approve" ? "Approving..." : "Rejecting...",
    "Updating request status."
  );

  try {
    const res = await fetch("/api/faculty", { method: "POST", body: fd });
    const data = await res.json();
    await new Promise((r) => setTimeout(r, 400));
    hideLoading();

    if (data.success) {
      location.reload();
    } else {
      showError("Could not save decision", data.error || "Unknown error");
    }
  } catch (err) {
    hideLoading();
    console.error("Profile edit action error:", err);
    showError("Connection error", "Unable to reach the server.");
  }
}

// ============================================
// Archive
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
    const res = await fetch("/api/faculty", { method: "POST", body: fd });
    const data = await res.json();
    if (data.success) {
      closeArchiveUserModal();
      location.reload();
    } else {
      closeArchiveUserModal();
      showError("Could not archive user", data.error || "Unknown error");
    }
  } catch (err) {
    console.error("Archive error:", err);
    closeArchiveUserModal();
    showError("Connection error", "Unable to reach the server. Please try again.");
  }
}

// ============================================
// Restore
// ============================================
function openRestoreUserModal(id, fullName) {
  pendingRestoreId = id;
  document.getElementById("restoreUserName").textContent = fullName;
  showModal("restoreUserModal", "restoreUserCard");
}
function closeRestoreUserModal() {
  hideModal("restoreUserModal", "restoreUserCard");
}
async function confirmRestoreUser() {
  if (!pendingRestoreId) return;
  const fd = new FormData();
  fd.append("action", "restore");
  fd.append("id", pendingRestoreId);
  try {
    const res = await fetch("/api/faculty", { method: "POST", body: fd });
    const data = await res.json();
    if (data.success) {
      closeRestoreUserModal();
      showModal("restoreSuccessModal", "restoreSuccessCard");
      if (typeof initIcons === "function") initIcons();
    } else {
      closeRestoreUserModal();
      showError("Could not restore user", data.error || "Unknown error");
    }
  } catch (err) {
    console.error("Restore error:", err);
    closeRestoreUserModal();
    showError("Connection error", "Unable to reach the server. Please try again.");
  }
}
function closeRestoreSuccessModal() {
  hideModal("restoreSuccessModal", "restoreSuccessCard");
  setTimeout(() => location.reload(), 200);
}

// ============================================
// Send reset
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
    const res = await fetch("/api/faculty", { method: "POST", body: fd });
    const data = await res.json();
    await new Promise((r) => setTimeout(r, 600));
    hideLoading();
    if (data.success) {
      const emailEl = document.getElementById("resetSentEmail");
      if (emailEl) emailEl.textContent = email;
      showModal("resetSentModal", "resetSentCard");
      if (typeof initIcons === "function") initIcons();
    } else {
      showError("Could not send reset link", data.error || "Unknown error");
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
// View toggle — pagination is outside both views
// ============================================
function setView(view) {
  currentView = view;
  localStorage.setItem("usersView", view);
  const gridBtn = document.getElementById("viewGridBtn");
  const listBtn = document.getElementById("viewListBtn");
  const activeClass =
    "flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium bg-white text-[#0F5E3D] shadow-sm transition";
  const inactiveClass =
    "flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium text-[#2C3E50]/70 hover:text-[#2C3E50] transition";
  if (view === "list") {
    listBtn.className = activeClass;
    gridBtn.className = inactiveClass;
  } else {
    gridBtn.className = activeClass;
    listBtn.className = inactiveClass;
  }
  applyView();
}

function applyView() {
  const grid = document.getElementById("usersGrid");
  const listView = document.getElementById("usersListView");
  if (currentView === "grid") {
    if (grid) { grid.classList.remove("hidden"); grid.style.display = "grid"; }
    if (listView) listView.style.display = "none";
  } else {
    if (grid) { grid.classList.add("hidden"); grid.style.display = "none"; }
    if (listView) listView.style.display = "";
  }
  applyFilters();
}

// ============================================
// Create
// ============================================
async function submitCreateUser(formData) {
  showLoading("Creating user...", "Setting up the account and sending the email.");
  try {
    const res = await fetch("/api/faculty", { method: "POST", body: formData });
    const data = await res.json();
    await new Promise((r) => setTimeout(r, 600));
    hideLoading();
    if (data.success) {
      closeAddUserModal();
      showModal("successModal", "successCard");
      if (typeof initIcons === "function") initIcons();
    } else if (data.error) {
      const e = data.error.toLowerCase();
      if (e.includes("username")) { markInvalid("addFirstName"); showError("Username already taken", data.error); }
      else if (e.includes("email")) { markInvalid("addEmail"); showError("Email already in use", data.error); }
      else if (e.includes("name")) { markInvalid("addFirstName"); markInvalid("addLastName"); showError("Name already exists", data.error); }
      else showError("Could not create user", data.error);
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
// Edit
// ============================================
async function submitEditUser(formData) {
  const isPasswordChange =
    !!formData.get("password") && String(formData.get("password")).trim() !== "";

  showLoading(
    isPasswordChange ? "Updating user & sending email..." : "Saving changes...",
    isPasswordChange
      ? "Updating the account and emailing the user."
      : "Applying your changes to the user."
  );

  try {
    const res = await fetch("/api/faculty", { method: "POST", body: formData });
    const data = await res.json();

    await new Promise((r) => setTimeout(r, isPasswordChange ? 800 : 400));
    hideLoading();

    if (data.success) {
      closeEditUserModal();
      showModal("editSuccessModal", "editSuccessCard");
      if (typeof initIcons === "function") initIcons();
    } else if (data.error && data.error.toLowerCase().includes("username")) {
      markInvalid("editUsername");
    } else if (data.error) {
      showError("Could not save changes", data.error);
    } else {
      showError("Could not save changes", "The server returned an unexpected response.");
    }
  } catch (err) {
    hideLoading();
    console.error("Edit error:", err);
    showError("Connection error", "Unable to reach the server.");
  }
}
function closeEditSuccessModal() {
  hideModal("editSuccessModal", "editSuccessCard");
  setTimeout(() => location.reload(), 200);
}

// ============================================
// Sort
// ============================================
function toggleSortMenu() {
  const menu = document.getElementById("sortMenu");
  const filterMenu = document.getElementById("filterMenu");
  const statusMenu = document.getElementById("statusMenu");
  if (statusMenu) statusMenu.classList.add("hidden");
  const statusChevron = document.getElementById("statusChevron");
  if (statusChevron) statusChevron.style.transform = "";
  const chevron = document.getElementById("sortChevron");
  if (!menu) return;
  const willOpen = menu.classList.contains("hidden");
  menu.classList.toggle("hidden");
  if (filterMenu) filterMenu.classList.add("hidden");
  const filterChevron = document.getElementById("filterChevron");
  if (filterChevron) filterChevron.style.transform = "";
  if (chevron) chevron.style.transform = willOpen ? "rotate(180deg)" : "";
  if (willOpen) setTimeout(() => document.addEventListener("click", closeSortOnOutside, { once: true }), 0);
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
    "name-asc": "Name (A → Z)", "name-desc": "Name (Z → A)",
    "role-asc": "Role (A → Z)", "role-desc": "Role (Z → A)",
    newest: "Newest First", oldest: "Oldest First", default: "Sort by",
  };
  document.getElementById("sortLabel").textContent = labels[type] || "Sort by";
  document.querySelectorAll(".sort-check").forEach((c) => (c.style.opacity = c.dataset.sort === type ? "1" : "0"));
  document.getElementById("sortMenu")?.classList.add("hidden");
  document.getElementById("sortChevron").style.transform = "";
  sortRows();
  applyFilters();
}
function sortRows() {
  if (currentSort === "default") return;
  const tbody = document.getElementById("usersTableBody");
  const mobileCards = document.getElementById("mobileCards");
  const grid = document.getElementById("usersGrid");
  if (!tbody) return;
  const compare = (a, b) => {
    switch (currentSort) {
      case "name-asc": return (a.dataset.name || "").localeCompare(b.dataset.name || "");
      case "name-desc": return (b.dataset.name || "").localeCompare(a.dataset.name || "");
      case "role-asc": return (a.dataset.role || "").localeCompare(b.dataset.role || "");
      case "role-desc": return (b.dataset.role || "").localeCompare(a.dataset.role || "");
      case "newest": return parseInt(b.dataset.created || 0) - parseInt(a.dataset.created || 0);
      case "oldest": return parseInt(a.dataset.created || 0) - parseInt(b.dataset.created || 0);
      default: return 0;
    }
  };
  const rows = Array.from(tbody.querySelectorAll("tr[data-name]"));
  rows.sort(compare);
  rows.forEach((row) => tbody.appendChild(row));
  if (mobileCards) {
    const cards = Array.from(mobileCards.querySelectorAll(":scope > div[data-name]"));
    cards.sort(compare);
    cards.forEach((c) => mobileCards.appendChild(c));
  }
  if (grid) {
    const gridCards = Array.from(grid.querySelectorAll(":scope > div[data-name]"));
    gridCards.sort(compare);
    gridCards.forEach((c) => grid.appendChild(c));
  }
}

// ============================================
// Filter menu (client-side only — status/search are server-side)
// ============================================
function toggleFilterMenu() {
  const menu = document.getElementById("filterMenu");
  const sortMenu = document.getElementById("sortMenu");
  const statusMenu = document.getElementById("statusMenu");
  const chevron = document.getElementById("filterChevron");
  if (!menu) return;
  const willOpen = menu.classList.contains("hidden");
  menu.classList.toggle("hidden");
  if (sortMenu) sortMenu.classList.add("hidden");
  if (statusMenu) statusMenu.classList.add("hidden");
  const statusChevron = document.getElementById("statusChevron");
  if (statusChevron) statusChevron.style.transform = "";
  const sortChevron = document.getElementById("sortChevron");
  if (sortChevron) sortChevron.style.transform = "";
  if (chevron) chevron.style.transform = willOpen ? "rotate(180deg)" : "";
  if (willOpen) setTimeout(() => document.addEventListener("click", closeFilterOnOutside, { once: true }), 0);
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

// ============================================
// Status menu
// ============================================
function toggleStatusMenu() {
  const menu = document.getElementById("statusMenu");
  const sortMenu = document.getElementById("sortMenu");
  const filterMenu = document.getElementById("filterMenu");
  const chevron = document.getElementById("statusChevron");
  if (!menu) return;
  const willOpen = menu.classList.contains("hidden");
  menu.classList.toggle("hidden");
  if (sortMenu) sortMenu.classList.add("hidden");
  if (filterMenu) filterMenu.classList.add("hidden");
  const sortChevron = document.getElementById("sortChevron");
  if (sortChevron) sortChevron.style.transform = "";
  const filterChevron = document.getElementById("filterChevron");
  if (filterChevron) filterChevron.style.transform = "";
  if (chevron) chevron.style.transform = willOpen ? "rotate(180deg)" : "";
  if (willOpen) setTimeout(() => document.addEventListener("click", closeStatusOnOutside, { once: true }), 0);
}
function closeStatusOnOutside(e) {
  const wrap = document.getElementById("statusWrap");
  const menu = document.getElementById("statusMenu");
  const chevron = document.getElementById("statusChevron");
  if (wrap && menu && !wrap.contains(e.target)) {
    menu.classList.add("hidden");
    if (chevron) chevron.style.transform = "";
  } else if (menu && !menu.classList.contains("hidden")) {
    document.addEventListener("click", closeStatusOnOutside, { once: true });
  }
}

// ============================================
// Filters — client-side only (role/category/sub/year)
// ============================================
function applyFilters() {
  const role     = document.getElementById("filterRole")?.value || "";
  const category = document.getElementById("filterCategory")?.value || "";
  const sub      = (document.getElementById("filterSubCategory")?.value || "").toLowerCase();
  const year     = (document.getElementById("filterYearLevel")?.value || "").toLowerCase();

  const rows  = document.querySelectorAll("#usersTableBody tr[data-name]");
  const cards = document.querySelectorAll("#mobileCards > div[data-name]");
  const grids = document.querySelectorAll("#usersGrid > div[data-name]");
  let visible = 0;

  [...rows, ...cards, ...grids].forEach((row) => {
    const rRole = row.dataset.role || "";
    const rCat  = row.dataset.category || "";
    const rSub  = (row.dataset.sub || "").toLowerCase();
    const rYear = (row.dataset.year || "").toLowerCase();

    const matches = (!role || rRole === role)
                 && (!category || rCat === category)
                 && (!sub || rSub === sub)
                 && (!year || rYear === year);

    row.classList.toggle("hidden", !matches);
    if (matches) visible++;
  });

  const activeFilters = [role, category, sub, year].filter((v) => v).length;
  const badge = document.getElementById("filterCount");
  if (badge) {
    if (activeFilters > 0) {
      badge.textContent = activeFilters;
      badge.classList.remove("hidden");
    } else badge.classList.add("hidden");
  }
  const empty = document.getElementById("emptyState");
  if (empty) empty.classList.toggle("hidden", visible > 0);
}

function clearAllFilters() {
  ["filterRole", "filterCategory", "filterSubCategory", "filterYearLevel"].forEach((id) => {
    const el = document.getElementById(id);
    if (el) el.value = "";
  });
  updateFilterSubCategories();
  updateFilterYearLevels();
  applyFilters();
  document.getElementById("filterMenu")?.classList.add("hidden");
  const chevron = document.getElementById("filterChevron");
  if (chevron) chevron.style.transform = "";
}

// ============================================
// Wire up on load
// ============================================
document.addEventListener("DOMContentLoaded", () => {
  if (typeof initIcons === "function") initIcons();
  setView(currentView);
  updateFilterSubCategories();
  updateFilterYearLevels();

  const addRoleEl = document.getElementById("addRole");
  const addCategoryEl = document.getElementById("addCategory");
  if (addRoleEl) addRoleEl.addEventListener("change", () => enforceRoleCategoryCoupling("add", "role"));
  if (addCategoryEl) addCategoryEl.addEventListener("change", () => enforceRoleCategoryCoupling("add", "category"));

  const addSubEl = document.getElementById("addSubCategory");
  if (addSubEl) addSubEl.addEventListener("change", () => togglePositionField("add"));

  const editRoleEl = document.getElementById("editRole");
  const editCategoryEl = document.getElementById("editCategory");
  if (editRoleEl) editRoleEl.addEventListener("change", () => enforceRoleCategoryCoupling("edit", "role"));
  if (editCategoryEl) editCategoryEl.addEventListener("change", () => enforceRoleCategoryCoupling("edit", "category"));

  const editSubEl = document.getElementById("editSub");
  if (editSubEl) editSubEl.addEventListener("change", () => togglePositionField("edit"));

  // Add user form
  document.getElementById("addUserForm")?.addEventListener("submit", async function (e) {
    e.preventDefault();
    if (!validateStep2()) return;

    const tempPassword = document.getElementById("addTempPassword").value.trim();
    if (!tempPassword) { markInvalid("addTempPassword"); return; }

    const formData = new FormData(this);
    const username = document.getElementById("addUsernamePreview").value;
    if (!username) { markInvalid("addFirstName"); return; }
    formData.append("username", username);
    formData.append("password", tempPassword);

    // If position field is hidden, force "N/A"
    if (!shouldShowPosition("add")) {
      formData.set("position", "N/A");
    }

    await submitCreateUser(formData);
  });

  // Edit user form
  document.getElementById("editUserForm")?.addEventListener("submit", async function (e) {
    e.preventDefault();
    const first = document.getElementById("editFirstName").value.trim();
    const last = document.getElementById("editLastName").value.trim();
    const username = document.getElementById("editUsername").value.trim();
    if (!first) { markInvalid("editFirstName"); return; }
    if (!last) { markInvalid("editLastName"); return; }
    if (!username) { markInvalid("editUsername"); return; }

    // Position is only required for faculty
    if (shouldShowPosition("edit")) {
      const position = document.getElementById("editPosition").value;
      if (!position) { markInvalid("editPosition"); return; }
    }

    const formData = new FormData(this);
    if (!shouldShowPosition("edit")) {
      formData.set("position", "N/A");
    }

    await submitEditUser(formData);
  });

  // `/` shortcut → focus search box
  document.addEventListener("keydown", function (e) {
    if (
      e.key === "/" &&
      document.activeElement.tagName !== "INPUT" &&
      document.activeElement.tagName !== "TEXTAREA"
    ) {
      e.preventDefault();
      document.getElementById("searchInput")?.focus();
    }
  });
});