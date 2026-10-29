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

function groupType(dbType) {
  const t = (dbType || "").toLowerCase();
  if (t === "sick") return "sick";
  if (t === "vacation") return "mandatory";
  return "others";
}

function escapeHtml(str) {
  return String(str)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

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
  rejected: "Disapproved",
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
let rvAllLeaves = [];
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
  const position = document.getElementById("positionFilter")?.value || "";

  const rows = document.querySelectorAll(
    "tbody tr[data-name], .md\\:hidden > div[data-name]"
  );
  let visible = 0;

  rows.forEach((row) => {
    const name = row.dataset.name || "";
    const s = row.dataset.status || "";
    const t = row.dataset.type || "";
    const p = row.dataset.position || "";

    const matches =
      (!search || name.includes(search)) &&
      (!status || s === status) &&
      (!type || t === type) &&
      (!position || p === position);
    row.classList.toggle("hidden", !matches);
    if (matches) visible++;
  });

  document
    .getElementById("emptyState")
    ?.classList.toggle("hidden", visible > 0);
}

function clearSearch() {
  const s = document.getElementById("searchInput");
  if (s) {
    s.value = "";
    filterLeaves();
    s.focus();
  }
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

  // ── Dean Endorsement ──
  const deanSection = document.getElementById("rvDeanSection");
  const myDeanInfo = rvAllLeaves.find((l) => l.id === rvCurrentId);

  if (status === "pending" && myDeanInfo) {
    if (myDeanInfo.dean_approved) {
      deanSection.classList.remove("hidden");
      document.getElementById("rvDeanSummary").textContent =
        "Endorsed by Academic Dean";
      document.getElementById("rvDeanSummary").className =
        "text-sm text-[#0F5E3D] font-medium";
    } else {
      deanSection.classList.remove("hidden");
      document.getElementById("rvDeanSummary").textContent =
        "Awaiting endorsement from Academic Dean";
      document.getElementById("rvDeanSummary").className =
        "text-sm text-amber-600 font-medium italic";
    }
  } else {
    deanSection.classList.add("hidden");
  }

  // ── Decision ──
  const decisionSection = document.getElementById("rvDecisionSection");
  
  // Clean up any previous Undo button
  const existingUndo = document.getElementById("rvUndoBtn");
  if (existingUndo) existingUndo.remove();

  if (status === "approved" || status === "rejected") {
    decisionSection.classList.remove("hidden");

    const verb = status === "approved" ? "Approved" : "Disapproved";
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

    // NEW: Add Undo button
    const undoBtn = document.createElement('button');
    undoBtn.type = 'button';
    undoBtn.id = 'rvUndoBtn';
    undoBtn.className = 'mt-3 w-full border border-amber-300 text-amber-700 bg-amber-50 hover:bg-amber-100 font-medium py-2.5 rounded-md transition text-sm flex items-center justify-center gap-2';
    undoBtn.innerHTML = '<i data-lucide="rotate-ccw" class="w-4 h-4"></i> Undo Decision & Set to Pending';
    undoBtn.onclick = rvUndoDecision;
    decisionSection.appendChild(undoBtn);
    
  } else {
    decisionSection.classList.add("hidden");
  }

  // ── Intelligent Pay Suggestion ──
  const payWith = document.getElementById("rvPayWith");
  const payWithout = document.getElementById("rvPayWithout");
  
  if (payWith && payWithout) {
    // If employee has enough remaining credits for the requested days, suggest With Pay
    if (credits.remaining >= full.days) {
        payWith.checked = true;
    } else {
        payWithout.checked = true;
    }
  }

  // ── Attachment ──
  renderLeaveAttachment(full);

  // Hide decide bar if not pending OR if pending but not endorsed by dean
  const canDecide =
    status === "pending" && myDeanInfo && myDeanInfo.dean_approved;
  document.getElementById("rvDecideBar").classList.toggle("hidden", !canDecide);

  // Refresh icons so the undo icon appears
  if (typeof initIcons === "function") initIcons();
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
      .filter((h) => h.id !== rvCurrentId) // exclude current
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
  document.getElementById("rvHistoryNext").disabled = rvHistoryPage >= totalPages;
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
  
  // NEW: Require remarks for disapproval
  if (decision === 'rejected' && !remarks) {
      showError("Reason required", "You must provide remarks when disapproving a leave request.");
      document.getElementById("rvRemarksInput").focus();
      return;
  }

  // Get the selected pay status from the radio buttons
  const selectedPayStatus = document.querySelector('input[name="rvPayStatus"]:checked')?.value || 'without_pay';

  const fd = new FormData();
  fd.append("action", "decide");
  fd.append("id", rvCurrentId);
  fd.append("decision", decision);
  fd.append("remarks", remarks);

  if (decision === "approved") {
    fd.append("pay_status", selectedPayStatus);
  }

  showLoading(
    "Saving…",
    decision === "approved"
      ? "Approving leave request."
      : "Disapproving leave request."
  );

  try {
    const res = await fetch("/api/leaves", { method: "POST", body: fd });
    const data = await res.json();

    await new Promise((r) => setTimeout(r, 400));
    hideLoading();

    if (data.success) {
      closeReview();
      document.getElementById("successTitle").textContent =
        decision === "approved" ? "Leave Approved" : "Leave Disapproved";
      document.getElementById("successMessage").textContent =
        decision === "approved"
          ? "The employee will see the approval."
          : "The employee will see the disapproval.";
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

async function rvUndoDecision() {
  if (!rvCurrentId) return;
  
  if (!confirm("Are you sure you want to revert this decision and set it back to Pending?")) return;

  const fd = new FormData();
  fd.append("action", "undo_decision");
  fd.append("id", rvCurrentId);

  showLoading("Reverting...", "Setting the request back to pending.");
  
  try {
    const res = await fetch("/api/leaves", { method: "POST", body: fd });
    const data = await res.json();
    await new Promise((r) => setTimeout(r, 400));
    hideLoading();

    if (data.success) {
      closeReview();
      location.reload();
    } else {
      showError("Could not revert", data.error || "Unknown error");
    }
  } catch (err) {
    hideLoading();
    showError("Connection error", "Unable to reach the server.");
  }
}

function closeSuccess() {
  hideModal("successModal", "successCard");
  setTimeout(() => location.reload(), 200);
}

// ============================================
// Render attachment in the review modal
// ============================================
function renderLeaveAttachment(leave) {
  const wrap = document.getElementById("rvAttachmentWrap");
  const link = document.getElementById("rvAttachmentLink");
  const name = document.getElementById("rvAttachmentName");
  const size = document.getElementById("rvAttachmentSize");
  const thumb = document.getElementById("rvAttachmentThumb");
  if (!wrap || !link) return;

  if (leave.attachment && leave.attachment.path) {
    wrap.classList.remove("hidden");
    link.href = "/" + leave.attachment.path;
    name.textContent = leave.attachment.name || "Attachment";

    const sizeLabel = leave.attachment.size
      ? leave.attachment.size >= 1024 * 1024
        ? (leave.attachment.size / (1024 * 1024)).toFixed(2) + " MB"
        : (leave.attachment.size / 1024).toFixed(1) + " KB"
      : "";
    size.textContent = sizeLabel;

    const isImage = (leave.attachment.mime || "").startsWith("image/");
    thumb.innerHTML = isImage
      ? `<img src="/${leave.attachment.path}" alt="" class="w-full h-full object-cover">`
      : `<i data-lucide="file-text" class="w-4 h-4"></i>`;

    if (typeof initIcons === "function") initIcons();
  } else {
    wrap.classList.add("hidden");
    link.href = "#";
    name.textContent = "";
    size.textContent = "";
    thumb.innerHTML = `<i data-lucide="file-text" class="w-4 h-4"></i>`;
  }
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