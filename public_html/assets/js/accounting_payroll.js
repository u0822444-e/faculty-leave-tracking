// ============================================
// Accounting Payroll
// ============================================
let settleEmployeeId = null;
let settleStatus = null;
let settleDefaultAmount = 0;
let pvCurrentEmployeeId = null;
let editSalaryEmployeeId = null;
let editLeavePayEmployeeId = null;

// ============================================
// Helpers
// ============================================
function ucfirst(s) {
  return s ? s.charAt(0).toUpperCase() + s.slice(1) : "";
}

function fmtPeso(v) {
  return "₱" + (parseFloat(v) || 0).toLocaleString("en-PH", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function fmtNum(v) {
  return (parseFloat(v) || 0).toLocaleString("en-PH", {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  });
}

// ============================================
// Server-side Search & Filter
// ============================================
let searchDebounceTimer = null;

function scheduleSearch() {
  clearTimeout(searchDebounceTimer);
  searchDebounceTimer = setTimeout(() => {
    document.getElementById("filterForm").submit();
  }, 400);
}

// ============================================
// Payroll View Modal
// ============================================
function openPayrollView(row) {
  if (!row) return;
  const d = row.dataset;
  const num = (v) => parseFloat(v) || 0;

  // --- SIDEBAR (Profile) ---
  document.getElementById("pvInitials").textContent = d.initials || "—";
  document.getElementById("pvName").textContent = d.fullName || "—";
  document.getElementById("pvProfileSubtitle").textContent = `Employee ID #${d.id || "—"}`;
  document.getElementById("pvPosition").textContent = d.position || "N/A";
  
  let deptStr = "";
  if (d.category && d.sub) deptStr = `${ucfirst(d.category)} · ${d.sub}`;
  else if (d.category) deptStr = ucfirst(d.category);
  else if (d.sub) deptStr = d.sub;
  document.getElementById("pvDepartment").textContent = deptStr || "—";
  document.getElementById("pvEmail").textContent = d.email || "—";

  // --- STATUS BLOCK ---
  const status = d.status || "none";
  const badge = document.getElementById("pvStatusBadge");
  const settleDetails = document.getElementById("pvSettlementDetails");
  const remarksWrap = document.getElementById("pvRemarksWrap");

  if (status === "settled") {
    badge.textContent = "Paid";
    badge.className = "inline-flex items-center px-2.5 py-1 rounded-md text-sm font-medium text-[#0F5E3D] bg-[#F1FDF6] mb-4";
    settleDetails.classList.remove("hidden");
  } else if (status === "outstanding") {
    badge.textContent = "Unpaid";
    badge.className = "inline-flex items-center px-2.5 py-1 rounded-md text-sm font-medium text-amber-700 bg-amber-50 mb-4";
    settleDetails.classList.remove("hidden");
  } else {
    badge.textContent = "Not Yet Recorded";
    badge.className = "inline-flex items-center px-2.5 py-1 rounded-md text-sm font-medium text-slate-600 bg-slate-100 mb-4";
    settleDetails.classList.add("hidden");
  }

  document.getElementById("pvSettledAmount").textContent = num(d.settlementAmount) > 0 ? fmtPeso(d.settlementAmount) : "—";
  document.getElementById("pvSettledAt").textContent = d.settledAt 
    ? new Date(d.settledAt.replace(" ", "T")).toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" }) 
    : "—";
    
  if (d.remarks && d.remarks.trim() !== "") {
    document.getElementById("pvRemarks").textContent = `"${d.remarks}"`;
    remarksWrap.classList.remove("hidden");
  } else {
    remarksWrap.classList.add("hidden");
  }

  // --- MAIN AREA (Numbers) ---
  const salary = num(d.salary);
  document.getElementById("pvSalary").textContent = salary > 0 ? fmtPeso(salary) : "—";

  document.getElementById("pvLeavePayValue").textContent = fmtPeso(d.leavePayValue);

  document.getElementById("pvAllocated").textContent = fmtNum(d.allocated) + " day(s)";
  document.getElementById("pvApproved").textContent = fmtNum(d.approved) + " day(s)";
  document.getElementById("pvRemaining").textContent = fmtNum(d.remaining) + " day(s)";
  
  document.getElementById("pvLwopDays").textContent = num(d.lwopDays) > 0 ? fmtNum(d.lwopDays) + " day(s)" : "0 day(s)";
  document.getElementById("pvLwopAmount").textContent = num(d.lwopDeduction) > 0 ? "−" + fmtPeso(d.lwopDeduction) : "—";

  // --- DECISION BAR (Actions) ---
  const actionBar = document.getElementById("pvActionBar");
  actionBar.innerHTML = "";

  const employeeName = d.fullName || "";
  const employeeAmount = num(d.leavePayValue);
  const employeeId = parseInt(d.id) || null;
  const employeeSalary = num(d.salary);
  const employeeLeavePayRate = num(d.leavePayRate);
  pvCurrentEmployeeId = employeeId;

  // Edit Salary Button
  const btnEditSalary = document.createElement("button");
  btnEditSalary.type = "button";
  btnEditSalary.className = "px-4 border border-[#E0E0E0] text-[#2C3E50] hover:bg-gray-50 font-medium py-2 rounded-md transition text-sm flex items-center gap-1.5 mr-auto";
  btnEditSalary.innerHTML = '<i data-lucide="pencil" class="w-4 h-4"></i><span>Edit Salary</span>';
  btnEditSalary.onclick = () => {
    openEditSalaryModal({ id: employeeId, name: employeeName, salary: employeeSalary });
  };
  actionBar.appendChild(btnEditSalary);

  // Edit Leave Pay Button
  const btnEditLeavePay = document.createElement("button");
  btnEditLeavePay.type = "button";
  btnEditLeavePay.className = "px-4 border border-[#E0E0E0] text-[#2C3E50] hover:bg-gray-50 font-medium py-2 rounded-md transition text-sm flex items-center gap-1.5 mr-auto";
  btnEditLeavePay.innerHTML = '<i data-lucide="banknote" class="w-4 h-4"></i><span>Edit Leave Pay</span>';
  btnEditLeavePay.onclick = () => {
    openEditLeavePayModal({
      id: employeeId,
      name: employeeName,
      leavePayRate: employeeLeavePayRate,
    });
  };
  actionBar.appendChild(btnEditLeavePay);

  if (status !== "settled") {
    const btnSettle = document.createElement("button");
    btnSettle.type = "button";
    btnSettle.className = "px-4 bg-[#0F5E3D] hover:bg-[#0a4a2f] text-white font-medium py-2 rounded-md transition text-sm flex items-center gap-1.5";
    btnSettle.innerHTML = '<i data-lucide="check-circle-2" class="w-4 h-4"></i><span>Mark as Paid</span>';
    btnSettle.onclick = () => {
      closePayrollView();
      openSettleModal({ id: employeeId, name: employeeName, amount: employeeAmount, status: "settled" });
    };
    actionBar.appendChild(btnSettle);
  }

  if (status !== "outstanding") {
    const btnOut = document.createElement("button");
    btnOut.type = "button";
    btnOut.className = "px-4 border border-amber-300 text-amber-700 hover:bg-amber-50 font-medium py-2 rounded-md transition text-sm flex items-center gap-1.5";
    btnOut.innerHTML = '<i data-lucide="clock" class="w-4 h-4"></i><span>Mark as Unpaid</span>';
    btnOut.onclick = () => {
      closePayrollView();
      openSettleModal({ id: employeeId, name: employeeName, amount: employeeAmount, status: "outstanding" });
    };
    actionBar.appendChild(btnOut);
  }

  showModal("payrollViewModal", "payrollViewCard");
  if (typeof initIcons === "function") initIcons();
}

function closePayrollView() {
  hideModal("payrollViewModal", "payrollViewCard");
  pvCurrentEmployeeId = null;
}

// ============================================
// Edit Salary Modal
// ============================================
function openEditSalaryModal(employee) {
  editSalaryEmployeeId = employee.id;
  
  document.getElementById("editSalaryName").textContent = employee.name;
  document.getElementById("editSalaryEmployeeId").value = employee.id;
  document.getElementById("editSalaryInput").value = employee.salary > 0 ? employee.salary.toFixed(2) : "0.00";

  showModal("editSalaryModal", "editSalaryCard");
  if (typeof initIcons === "function") initIcons();
}

function closeEditSalaryModal() {
  hideModal("editSalaryModal", "editSalaryCard");
  editSalaryEmployeeId = null;
}

async function confirmEditSalary() {
  if (!editSalaryEmployeeId) return;

  const amount = parseFloat(document.getElementById("editSalaryInput").value) || 0;
  if (amount < 0) {
    showError("Invalid amount", "Salary cannot be negative.");
    return;
  }

  const fd = new FormData();
  fd.append("action", "update_salary");
  fd.append("employee_id", editSalaryEmployeeId);
  fd.append("basic_salary", amount);

  closeEditSalaryModal();
  showLoading("Saving salary...", "Updating the employee's monthly salary.");

  try {
    const res = await fetch("/api/accounting/payroll", { method: "POST", body: fd });
    const text = await res.text();
    let data;
    try {
      data = JSON.parse(text);
    } catch (parseErr) {
      hideLoading();
      showError("Server error", "The server returned a non-JSON response.");
      return;
    }

    await new Promise((r) => setTimeout(r, 400));
    hideLoading();

    if (data.success) {
      document.getElementById("successTitle").textContent = "Salary Updated";
      document.getElementById("successMessage").textContent = "The employee's monthly salary has been successfully updated.";
      showModal("successModal", "successCard");
      if (typeof initIcons === "function") initIcons();
    } else {
      showError("Could not save", data.error || "Unknown error");
    }
  } catch (err) {
    hideLoading();
    console.error(err);
    showError("Connection error", "Unable to reach the server.");
  }
}

// ============================================
// Edit Leave Pay Modal
// ============================================
function openEditLeavePayModal(employee) {
  editLeavePayEmployeeId = employee.id;

  document.getElementById("editLeavePayName").textContent = employee.name;
  document.getElementById("editLeavePayEmployeeId").value = employee.id;
  document.getElementById("editLeavePayInput").value =
    employee.leavePayRate > 0 ? Number(employee.leavePayRate).toFixed(2) : "0.00";

  showModal("editLeavePayModal", "editLeavePayCard");
  if (typeof initIcons === "function") initIcons();
}

function closeEditLeavePayModal() {
  hideModal("editLeavePayModal", "editLeavePayCard");
  editLeavePayEmployeeId = null;
}

async function confirmEditLeavePay() {
  if (!editLeavePayEmployeeId) return;

  const rate = parseFloat(document.getElementById("editLeavePayInput").value) || 0;
  if (rate < 0) {
    showError("Invalid rate", "Leave pay rate cannot be negative.");
    return;
  }

  const fd = new FormData();
  fd.append("action", "update_leave_pay");
  fd.append("employee_id", editLeavePayEmployeeId);
  fd.append("leave_pay", rate);

  closeEditLeavePayModal();
  showLoading("Saving leave pay...", "Updating the employee's leave pay rate.");

  try {
    const res = await fetch("/api/accounting/payroll", { method: "POST", body: fd });
    const text = await res.text();
    let data;
    try {
      data = JSON.parse(text);
    } catch (parseErr) {
      hideLoading();
      showError("Server error", "The server returned a non-JSON response.");
      return;
    }

    await new Promise((r) => setTimeout(r, 400));
    hideLoading();

    if (data.success) {
      document.getElementById("successTitle").textContent = "Leave Pay Updated";
      document.getElementById("successMessage").textContent =
        "The employee's leave pay rate has been successfully updated.";
      showModal("successModal", "successCard");
      if (typeof initIcons === "function") initIcons();
    } else {
      showError("Could not save", data.error || "Unknown error");
    }
  } catch (err) {
    hideLoading();
    console.error(err);
    showError("Connection error", "Unable to reach the server.");
  }
}

// ============================================
// Payment Modal
// ============================================
function openSettleModal(employee) {
  settleEmployeeId = employee.id;
  settleStatus = employee.status;
  settleDefaultAmount = employee.amount;

  const isSettle = employee.status === "settled";

  document.getElementById("settleTitle").textContent = isSettle ? "Mark as Paid" : "Mark as Unpaid";
  document.getElementById("settleSubtitle").textContent = employee.name;
  document.getElementById("settleEmployeeId").value = employee.id;
  document.getElementById("settleStatus").value = employee.status;
  document.getElementById("settleEmployeeName").textContent = employee.name;
  document.getElementById("settleAmountLabel").textContent = fmtPeso(employee.amount);
  document.getElementById("settleAmount").value = (parseFloat(employee.amount) || 0).toFixed(2);
  document.getElementById("settleRemarks").value = "";

  const iconWrap = document.getElementById("settleIconWrap");
  const icon = document.getElementById("settleIcon");
  if (isSettle) {
    iconWrap.className = "w-9 h-9 rounded-lg bg-[#F1FDF6] flex items-center justify-center text-[#0F5E3D] shrink-0";
    icon.setAttribute("data-lucide", "check-circle-2");
  } else {
    iconWrap.className = "w-9 h-9 rounded-lg bg-amber-50 flex items-center justify-center text-amber-600 shrink-0";
    icon.setAttribute("data-lucide", "clock");
  }

  const btn = document.getElementById("settleConfirmBtn");
  btn.textContent = isSettle ? "Confirm Payment" : "Mark Unpaid";
  btn.className = isSettle
    ? "flex-1 bg-[#0F5E3D] hover:bg-[#0a4a2f] text-white font-medium py-2.5 rounded-lg transition text-sm"
    : "flex-1 bg-amber-600 hover:bg-amber-700 text-white font-medium py-2.5 rounded-lg transition text-sm";

  showModal("settleModal", "settleCard");
  if (typeof initIcons === "function") initIcons();
}

function closeSettleModal() {
  hideModal("settleModal", "settleCard");
  settleEmployeeId = null;
  settleStatus = null;
}

async function confirmSettle() {
  if (!settleEmployeeId || !settleStatus) return;

  const amount = parseFloat(document.getElementById("settleAmount").value) || 0;
  if (amount < 0) {
    showError("Invalid amount", "Amount cannot be negative.");
    return;
  }

  const fd = new FormData();
  fd.append("action", "settle_payroll");
  fd.append("employee_id", settleEmployeeId);
  fd.append("status", settleStatus);
  fd.append("amount", amount);
  fd.append("remarks", document.getElementById("settleRemarks").value.trim());

  const isSettle = settleStatus === "settled";
  closeSettleModal();
  showLoading(
    isSettle ? "Recording payment..." : "Marking unpaid...",
    isSettle ? "Recording the paid amount." : "Marking as unpaid."
  );

  try {
    const res = await fetch("/api/accounting/payroll", { method: "POST", body: fd });

    const text = await res.text();
    let data;
    try {
      data = JSON.parse(text);
    } catch (parseErr) {
      hideLoading();
      console.error("Non-JSON response from /api/accounting/payroll:", text.slice(0, 200));
      showError(
        "Server error",
        res.status === 404
          ? "The endpoint /api/accounting/payroll is not routed. Check router.php."
          : "The server returned a non-JSON response."
      );
      return;
    }

    await new Promise((r) => setTimeout(r, 400));
    hideLoading();

    if (data.success) {
      document.getElementById("successTitle").textContent = isSettle ? "Marked as Paid" : "Marked as Unpaid";
      document.getElementById("successMessage").textContent = isSettle
        ? "The leave-pay liability has been paid."
        : "The liability is now marked as unpaid.";
      showModal("successModal", "successCard");
      if (typeof initIcons === "function") initIcons();
    } else {
      showError("Could not save", data.error || "Unknown error");
    }
  } catch (err) {
    hideLoading();
    console.error(err);
    showError("Connection error", "Unable to reach the server.");
  }
}

function closeSuccess() {
  hideModal("successModal", "successCard");
  setTimeout(() => location.reload(), 200);
}

// ============================================
// Keyboard shortcut
// ============================================
document.addEventListener("keydown", (e) => {
  if (
    e.key === "/" &&
    document.activeElement.tagName !== "INPUT" &&
    document.activeElement.tagName !== "TEXTAREA"
  ) {
    e.preventDefault();
    document.getElementById("searchInput")?.focus();
  }
});

// ============================================
// Init
// ============================================
document.addEventListener("DOMContentLoaded", () => {
  if (typeof initIcons === "function") initIcons();
});