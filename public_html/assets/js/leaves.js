// ============================================
// HR Leaves — logic
// ============================================

// ============================================
// Helpers
// ============================================
function formatDate(iso) {
  if (!iso) return "—";
  const d = new Date(iso + "T00:00:00");
  return d.toLocaleDateString("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
  });
}

function formatDateTime(iso) {
  if (!iso) return "—";
  const d = new Date(iso.replace(" ", "T"));
  return (
    d.toLocaleDateString("en-US", {
      month: "short",
      day: "numeric",
      year: "numeric",
    }) +
    " · " +
    d.toLocaleTimeString("en-US", {
      hour: "numeric",
      minute: "2-digit",
      hour12: true,
    })
  );
}

function fmtDays(n) {
  const v = parseFloat(n) || 0;
  return Number.isInteger(v) ? String(v) + " day(s)" : v.toFixed(1) + " day(s)";
}

function fmtDaysShort(n) {
  const v = parseFloat(n) || 0;
  return Number.isInteger(v) ? String(v) : v.toFixed(1);
}

// Map DB leave types to the 3 presentation groups
function groupType(dbType) {
  const t = (dbType || "").toLowerCase();
  if (t === "sick") return "sick";
  if (t === "vacation") return "mandatory";
  return "others";
}

// Detailed label for the header pill (still shows the exact subtype)
const TYPE_DETAIL_LABELS = {
  vacation: "Vacation",
  sick: "Sick",
  maternity: "Maternity",
  paternity: "Paternity",
  terminal: "Terminal",
  other: "Others",
};

const TYPE_CLASSES = {
  vacation: "text-blue-700 bg-blue-50",
  sick: "text-rose-700 bg-rose-50",
  maternity: "text-purple-700 bg-purple-50",
  paternity: "text-indigo-700 bg-indigo-50",
  terminal: "text-amber-700 bg-amber-50",
  other: "text-slate-700 bg-slate-100",
};

const STATUS_LABELS = {
  pending: "Pending",
  approved: "Approved",
  rejected: "Rejected",
  cancelled: "Cancelled",
};

const STATUS_CLASSES = {
  pending: "text-amber-700 bg-amber-50",
  approved: "text-[#0F5E3D] bg-[#F1FDF6]",
  rejected: "text-red-700 bg-red-50",
  cancelled: "text-slate-700 bg-slate-100",
};

// ============================================
// State
// ============================================
let rvAllLeaves = []; // populated by PHP inline script
let rvCurrentId = null;
let rvEmployeeData = null;
let rvFilteredHistory = [];
let rvHistoryPage = 1;
const RV_HISTORY_PAGE_SIZE = 5;

// ============================================
// Main table filter
// ============================================
function filterLeaves() {
  const search = (
    document.getElementById("searchInput")?.value || ""
  ).toLowerCase();
  const status = document.getElementById("statusFilter")?.value || "";
  const type = document.getElementById("typeFilter")?.value || "";

  const rows = document.querySelectorAll(
    "tbody tr[data-name], .md\\:hidden > div[data-name]"
  );
  let visible = 0;

  rows.forEach((row) => {
    const name = row.dataset.name || "";
    const s = row.dataset.status || "";
    const t = row.dataset.type || "";

    const matches =
      (!search || name.includes(search)) &&
      (!status || s === status) &&
      (!type || t === type);
    row.classList.toggle("hidden", !matches);
    if (matches) visible++;
  });

  document
    .getElementById("emptyState")
    ?.classList.toggle("hidden", visible > 0);
}

// ============================================
// Open review modal
// ============================================
async function openReview(leaveId) {
  rvCurrentId = leaveId;
  rvEmployeeData = null;
  rvFilteredHistory = [];
  rvHistoryPage = 1;

  document.getElementById("rvRemarksInput").value = "";

  document.getElementById("rvLoading").classList.remove("hidden");
  document.getElementById("rvContent").classList.add("hidden");
  document.getElementById("rvRequestId").textContent = leaveId
    ? `Request #${leaveId}`
    : "—";

  const hs = document.getElementById("rvHistoryStatus");
  const ht = document.getElementById("rvHistoryType");
  if (hs) hs.value = "";
  if (ht) ht.value = "";

  showModal("reviewModal", "reviewCard");
  if (typeof initIcons === "function") initIcons();

  const leave = rvAllLeaves.find((l) => l.id === leaveId);
  if (!leave || !leave.employee_id) {
    document.getElementById("rvLoading").innerHTML =
      '<p class="text-sm text-red-600 p-6 text-center">This request has no linked employee record.</p>';
    return;
  }

  try {
    const fd = new FormData();
    fd.append("action", "employee_history");
    fd.append("employee_id", leave.employee_id);

    const res = await fetch("/api/leaves", { method: "POST", body: fd });
    const payload = await res.json();

    if (!payload.success) {
      document.getElementById(
        "rvLoading"
      ).innerHTML = `<p class="text-sm text-red-600 p-6 text-center">${
        payload.error || "Could not load details."
      }</p>`;
      return;
    }

    rvEmployeeData = payload;
    renderReview(leave);
    rvRenderHistory(1);

    document.getElementById("rvLoading").classList.add("hidden");
    document.getElementById("rvContent").classList.remove("hidden");
    if (typeof initIcons === "function") initIcons();
  } catch (err) {
    console.error(err);
    document.getElementById("rvLoading").innerHTML =
      '<p class="text-sm text-red-600 p-6 text-center">Connection error.</p>';
  }
}

function closeReview() {
  hideModal("reviewModal", "reviewCard");
}

// ============================================
// Render sidebar + main header
// ============================================
function renderReview(leave) {
  const emp = rvEmployeeData.employee;
  const credits = rvEmployeeData.credits;
  const history = rvEmployeeData.history;
  const full = history.find((h) => h.id === leave.id) || leave;

  // ── Sidebar ──
  const fullName = emp.full_name || leave.name;
  const initials = fullName
    .split(/\s+/)
    .filter(Boolean)
    .map((w) => w[0])
    .slice(0, 2)
    .join("")
    .toUpperCase();
  document.getElementById("rvInitials").textContent = initials || "—";
  document.getElementById("rvName").textContent = fullName;
  document.getElementById("rvPosition").textContent = emp.position || "—";

  let dept = "";
  if (
    emp.sub_category &&
    emp.year_level &&
    emp.sub_category !== emp.year_level
  ) {
    dept = `${emp.sub_category} · ${emp.year_level}`;
  } else if (emp.sub_category) {
    dept = emp.sub_category;
  } else if (emp.year_level) {
    dept = emp.year_level;
  } else if (emp.category) {
    dept = emp.category;
  }
  document.getElementById("rvDepartment").textContent = dept || "—";

  document.getElementById("rvCreditTotal").textContent = `${fmtDaysShort(
    credits.remaining
  )} / ${fmtDaysShort(credits.total)}`;

  const creditRows = [];
  Object.keys(credits.by_type).forEach((t) => {
    const c = credits.by_type[t];
    if (c.cap === null && c.used === 0) return;

    let bar = "";
    if (c.cap !== null) {
      const pct = c.cap > 0 ? Math.min(100, (c.remaining / c.cap) * 100) : 0;
      const barColor =
        pct > 50 ? "bg-[#0F5E3D]" : pct > 20 ? "bg-amber-500" : "bg-red-500";
      bar = `
                <div class="mt-1.5 h-1 bg-[#E0E0E0] rounded-full overflow-hidden">
                    <div class="${barColor} h-full" style="width: ${pct}%"></div>
                </div>
            `;
    } else {
      bar = `<div class="mt-1.5 h-1 bg-[#F0F0F0] rounded-full"></div>`;
    }

    const rightLabel =
      c.cap === null
        ? `<span class="text-sm text-[#2C3E50]/50">${fmtDaysShort(
            c.used
          )} used</span>`
        : `<span class="text-sm text-[#2C3E50]">${fmtDaysShort(
            c.remaining
          )}<span class="text-[#2C3E50]/40">/${fmtDaysShort(
            c.cap
          )}</span></span>`;

    creditRows.push(`
            <div>
                <div class="flex items-baseline justify-between gap-2">
                    <span class="text-sm text-[#2C3E50]">${
                      TYPE_DETAIL_LABELS[t] || t
                    }</span>
                    ${rightLabel}
                </div>
                ${bar}
            </div>
        `);
  });
  document.getElementById("rvCredits").innerHTML = creditRows.join("");

  // ── Main header ──
  const typeKey = (full.type || "").toLowerCase();
  document.getElementById("rvType").textContent =
    TYPE_DETAIL_LABELS[typeKey] || full.type;
  document.getElementById(
    "rvType"
  ).className = `inline-flex items-center px-2.5 py-1 rounded-md text-sm font-medium ${
    TYPE_CLASSES[typeKey] || TYPE_CLASSES.other
  }`;

  const status = (full.status || "").toLowerCase();
  document.getElementById("rvStatus").textContent =
    STATUS_LABELS[status] || status;
  document.getElementById(
    "rvStatus"
  ).className = `inline-flex items-center px-2.5 py-1 rounded-md text-sm font-medium ${
    STATUS_CLASSES[status] || STATUS_CLASSES.cancelled
  }`;

  const payBadge = document.getElementById("rvPayBadge");
  if (status === "approved" && full.pay_status) {
    payBadge.classList.remove("hidden");
    if (full.pay_status === "with_pay") {
      payBadge.textContent = "With Pay";
      payBadge.className =
        "inline-flex items-center px-2.5 py-1 rounded-md text-sm font-medium text-[#0F5E3D] bg-[#F1FDF6]";
    } else {
      payBadge.textContent = "Without Pay";
      payBadge.className =
        "inline-flex items-center px-2.5 py-1 rounded-md text-sm font-medium text-amber-700 bg-amber-50";
    }
  } else {
    payBadge.classList.add("hidden");
  }

  const startStr = formatDate(full.start);
  const endStr = formatDate(full.end);
  document.getElementById("rvDates").textContent =
    startStr === endStr ? startStr : `${startStr} – ${endStr}`;
  document.getElementById("rvDays").textContent = fmtDays(full.days);
  document.getElementById("rvFiled").textContent = formatDateTime(full.created);
  document.getElementById("rvReason").textContent = full.reason || "—";

  // ── Decision ──
  const decisionSection = document.getElementById("rvDecisionSection");
  if (status === "approved" || status === "rejected") {
    decisionSection.classList.remove("hidden");

    const verb = status === "approved" ? "Approved" : "Rejected";
    const approver = full.approver || "an administrator";
    const when = formatDateTime(full.approved);
    document.getElementById(
      "rvDecisionSummary"
    ).textContent = `${verb} by ${approver} · ${when}`;

    const remarksWrap = document.getElementById("rvRemarksWrap");
    if (full.remarks && full.remarks.trim() !== "") {
      document.getElementById("rvRemarks").textContent = `“${full.remarks}”`;
      remarksWrap.classList.remove("hidden");
    } else {
      remarksWrap.classList.add("hidden");
    }
  } else {
    decisionSection.classList.add("hidden");
  }

  document
    .getElementById("rvDecideBar")
    .classList.toggle("hidden", status !== "pending");
}

// ============================================
// History — table style
// ============================================
function rvRenderHistory(page) {
  if (!rvEmployeeData) return;

  const listEl = document.getElementById("rvHistoryList");
  const countEl = document.getElementById("rvHistoryInfo");

  if (page === 1) {
    const status = document.getElementById("rvHistoryStatus")?.value || "";
    const typeGroup = document.getElementById("rvHistoryType")?.value || "";

    rvFilteredHistory = (rvEmployeeData.history || [])
      // .filter((h) => h.id !== rvCurrentId) // exclude current -> REMOVED to show current request
      .filter((h) => {
        if (status && h.status !== status) return false;
        if (typeGroup && groupType(h.type) !== typeGroup) return false;
        return true;
      });
  }

  const total = rvFilteredHistory.length;
  const totalPages = Math.max(1, Math.ceil(total / RV_HISTORY_PAGE_SIZE));
  rvHistoryPage = Math.max(1, Math.min(page, totalPages));

  const start = (rvHistoryPage - 1) * RV_HISTORY_PAGE_SIZE;
  const slice = rvFilteredHistory.slice(start, start + RV_HISTORY_PAGE_SIZE);

  if (slice.length === 0) {
    listEl.innerHTML = `
            <tr>
                <td colspan="4" class="px-4 py-6 text-center text-sm text-[#2C3E50]/50">
                    No previous leave records.
                </td>
            </tr>
        `;
  } else {
    listEl.innerHTML = slice
      .map((h) => {
        const typeKey = (h.type || "").toLowerCase();
        const statusKey = (h.status || "").toLowerCase();
        const startStr = formatDate(h.start);
        const endStr = formatDate(h.end);
        const dateRange =
          startStr === endStr ? startStr : `${startStr} – ${endStr}`;

        return `
                <tr class="h-10">
                    <td class="px-4 py-0">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${
                          TYPE_CLASSES[typeKey] || TYPE_CLASSES.other
                        }">
                            ${TYPE_DETAIL_LABELS[typeKey] || h.type}
                        </span>
                    </td>
                    <td class="px-4 py-0 text-sm text-[#2C3E50]/70">${dateRange}</td>
                    <td class="px-4 py-0 text-sm text-[#2C3E50]/70">${fmtDaysShort(
                      h.days
                    )}</td>
                    <td class="px-4 py-0">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium capitalize ${
                          STATUS_CLASSES[statusKey] || STATUS_CLASSES.cancelled
                        }">
                            ${STATUS_LABELS[statusKey] || h.status}
                        </span>
                    </td>
                </tr>
            `;
      })
      .join("");
  }

  if (countEl) {
    countEl.textContent =
      total > 0
        ? `Showing ${start + 1}–${Math.min(
            start + RV_HISTORY_PAGE_SIZE,
            total
          )} of ${total}`
        : "No records";
  }
  document.getElementById("rvHistoryPageNum").textContent = rvHistoryPage;
  document.getElementById("rvHistoryPrev").disabled = rvHistoryPage <= 1;
  document.getElementById("rvHistoryNext").disabled =
    rvHistoryPage >= totalPages;
}

function rvHistoryPrev() {
  if (rvHistoryPage > 1) rvRenderHistory(rvHistoryPage - 1);
}
function rvHistoryNext() {
  const total = rvFilteredHistory.length;
  const totalPages = Math.max(1, Math.ceil(total / RV_HISTORY_PAGE_SIZE));
  if (rvHistoryPage < totalPages) rvRenderHistory(rvHistoryPage + 1);
}

// ============================================
// Submit decision
// ============================================
async function rvSubmitDecision(decision) {
  if (!rvCurrentId) return;

  const remarks = document.getElementById("rvRemarksInput").value.trim();

  // Automatically assign Pay Status based on Leave Type
  let payStatus = "without_pay";
  const leave = rvAllLeaves.find((l) => l.id === rvCurrentId);
  if (leave && leave.type === "sick") {
    payStatus = "with_pay";
  }

  const fd = new FormData();
  fd.append("action", "decide");
  fd.append("id", rvCurrentId);
  fd.append("decision", decision);
  fd.append("remarks", remarks);

  if (decision === "approved") {
    fd.append("pay_status", payStatus);
  }

  showLoading(
    "Saving…",
    decision === "approved"
      ? "Approving leave request."
      : "Rejecting leave request."
  );

  try {
    const res = await fetch("/api/leaves", { method: "POST", body: fd });
    const data = await res.json();

    await new Promise((r) => setTimeout(r, 400));
    hideLoading();

    if (data.success) {
      closeReview();
      document.getElementById("successTitle").textContent =
        decision === "approved" ? "Leave Approved" : "Leave Rejected";
      document.getElementById("successMessage").textContent =
        decision === "approved"
          ? "The faculty member will see the approval."
          : "The faculty member will see the rejection.";
      showModal("successModal", "successCard");
      if (typeof initIcons === "function") initIcons();
    } else {
      showError("Could not save decision", data.error || "Unknown error");
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

document.addEventListener("DOMContentLoaded", () => {
  if (typeof initIcons === "function") initIcons();
});
