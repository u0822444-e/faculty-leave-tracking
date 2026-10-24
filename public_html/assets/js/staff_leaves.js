// ============================================
// Faculty/Staff Leaves — logic
// ============================================

const MAX_CREDITS = parseFloat(
  document.getElementById("remainingCredits")?.textContent || "0"
);
const LEAVE_DISABLED = document.body.dataset.leaveDisabled === "1";

// Flatpickr instances
let fpStart = null;
let fpEnd = null;

// ============================================
// Flatpickr initialization
// ============================================
function initLeaveDatePickers() {
  if (typeof flatpickr === "undefined") return;

  const todayStr = new Date().toISOString().split("T")[0];

  const blocked = (window.EXISTING_LEAVE_RANGES || []).map((r) => ({
    from: r.start,
    to: r.end,
  }));

  const commonOpts = {
    dateFormat: "Y-m-d",
    altInput: true,
    altFormat: "M j, Y",
    allowInput: false,
    disable: blocked,
    minDate: "today",
    disableMobile: true,
  };

  fpStart = flatpickr("#leaveStart", {
    ...commonOpts,
    onChange: function (selectedDates, dateStr) {
      if (!dateStr) return;
      if (fpEnd) {
        fpEnd.set("minDate", dateStr);
        if (
          fpEnd.selectedDates[0] &&
          fpEnd.selectedDates[0] < selectedDates[0]
        ) {
          fpEnd.setDate(dateStr, true);
        }
      }
      updateDaysCount();
    },
  });

  const halfInput = document.getElementById("isHalfDay");
  if (halfInput) {
    halfInput.addEventListener("change", updateDaysCount);
  }

  fpEnd = flatpickr("#leaveEnd", {
    ...commonOpts,
    onChange: function () {
      updateDaysCount();
    },
  });
}

// ============================================
// Modal open/close
// ============================================
function openLeaveModal() {
  if (LEAVE_DISABLED) {
    showError(
      "Leave filing unavailable",
      "Part-time employees are not eligible to file leave requests."
    );
    return;
  }

  document.getElementById("leaveForm").reset();
  document.getElementById("leaveDays").value = "";

  const modal = document.getElementById("leaveModal");
  const defaultStart =
    modal?.dataset.defaultStart || new Date().toISOString().split("T")[0];

  if (fpStart) fpStart.setDate(defaultStart, false);
  if (fpEnd) fpEnd.setDate(defaultStart, false);
  if (fpEnd) fpEnd.set("minDate", defaultStart);

  document.getElementById("leaveDateHint")?.classList.add("hidden");
  updateDaysCount();

  showModal("leaveModal", "leaveCard");
  initIcons();
}

function closeLeaveModal() {
  hideModal("leaveModal", "leaveCard");
}

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

// ============================================
// View details — rich modal
// ============================================
function openViewLeave(data) {
  document.getElementById("vlRequestId").textContent = data.id
    ? `Request #${data.id}`
    : "—";

  const status = (data.status || "").toLowerCase();
  const statusMap = {
    pending: {
      label: "Pending",
      icon: "clock",
      cls: "text-amber-700 bg-amber-50 border-amber-200",
      ribbon: "bg-amber-50 border-amber-200",
      iconCls: "text-amber-600",
    },
    approved: {
      label: "Approved",
      icon: "check-circle-2",
      cls: "text-[#0F5E3D] bg-[#F1FDF6] border-[#0F5E3D]/15",
      ribbon: "bg-[#F1FDF6] border-[#0F5E3D]/15",
      iconCls: "text-[#0F5E3D]",
    },
    rejected: {
      label: "Rejected",
      icon: "x-circle",
      cls: "text-red-700 bg-red-50 border-red-200",
      ribbon: "bg-red-50 border-red-200",
      iconCls: "text-red-600",
    },
    cancelled: {
      label: "Cancelled",
      icon: "ban",
      cls: "text-slate-600 bg-slate-100 border-slate-200",
      ribbon: "bg-slate-50 border-slate-200",
      iconCls: "text-slate-500",
    },
  };
  const s = statusMap[status] || statusMap.cancelled;

  document.getElementById("vlStatus").textContent = s.label;
  document.getElementById("vlStatusBadge").textContent = s.label;
  document.getElementById(
    "vlStatusBadge"
  ).className = `inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-medium border ${s.cls}`;
  document.getElementById(
    "vlStatusRibbon"
  ).className = `flex items-center gap-3 p-3 rounded-xl border ${s.ribbon}`;
  document.getElementById(
    "vlStatusIcon"
  ).className = `w-9 h-9 rounded-lg bg-white flex items-center justify-center shrink-0 ${s.iconCls}`;
  document.getElementById(
    "vlStatusIcon"
  ).innerHTML = `<i data-lucide="${s.icon}" class="w-4 h-4"></i>`;

  // Employee
  const name = data.name || "—";
  const initials = name
    .split(/\s+/)
    .filter(Boolean)
    .map((w) => w[0])
    .slice(0, 2)
    .join("")
    .toUpperCase();
  document.getElementById("vlInitials").textContent = initials || "—";
  document.getElementById("vlName").textContent = name;
  document.getElementById("vlEmail").textContent = data.email || "—";

  const setChip = (chipId, valueId, value) => {
    const chip = document.getElementById(chipId);
    if (!chip) return;
    if (value && value !== "—") {
      document.getElementById(valueId).textContent = value;
      chip.classList.remove("hidden");
    } else {
      chip.classList.add("hidden");
    }
  };

  setChip("vlCategoryChip", "vlCategory", data.category);
  setChip("vlSubCatChip", "vlSubCat", data.subcat);
  setChip("vlYearChip", "vlYear", data.year_level);
  setChip("vlPositionChip", "vlPosition", data.position);
  setChip("vlEmploymentChip", "vlEmployment", data.employment);

  // Request
  document.getElementById("vlType").textContent = data.type || "—";
  document.getElementById("vlDays").textContent = fmtDays(data.days);
  document.getElementById("vlStart").textContent = formatDate(data.start);
  document.getElementById("vlEnd").textContent = formatDate(data.end);
  document.getElementById("vlCreated").textContent = formatDateTime(
    data.created
  );

  const days = parseFloat(data.days) || 0;
  document
    .getElementById("vlHalfDayTag")
    .classList.toggle("hidden", days !== 0.5);

  document.getElementById("vlReason").textContent = data.reason || "—";

  // Decision
  const decisionWrap = document.getElementById("vlDecisionWrap");
  if (status === "approved" || status === "rejected") {
    decisionWrap.classList.remove("hidden");
    document.getElementById("vlApprover").textContent = data.approver || "—";
    document.getElementById("vlApprovedAt").textContent = formatDateTime(
      data.approved
    );

    const remarksBlock = document.getElementById("vlRemarksBlock");
    if (data.remarks && data.remarks.trim() !== "") {
      document.getElementById("vlRemarks").textContent = data.remarks;
      remarksBlock.classList.remove("hidden");
    } else {
      remarksBlock.classList.add("hidden");
    }
  } else {
    decisionWrap.classList.add("hidden");
  }

  showModal("viewLeaveModal", "viewLeaveCard");
  initIcons();
}

function closeViewLeave() {
  hideModal("viewLeaveModal", "viewLeaveCard");
}

// ============================================
// Days count
// ============================================
function updateDaysCount() {
  const out = document.getElementById("leaveDays");
  const halfWrap = document.getElementById("halfDayWrap");
  const halfInput = document.getElementById("isHalfDay");

  const start =
    fpStart?.input?.value || document.getElementById("leaveStart").value;
  const end = fpEnd?.input?.value || document.getElementById("leaveEnd").value;

  if (!start || !end) {
    out.value = "";
    if (halfWrap) halfWrap.classList.add("hidden");
    if (halfInput) halfInput.checked = false;
    return;
  }

  const s = new Date(start + "T00:00:00");
  const e = new Date(end + "T00:00:00");

  if (e < s) {
    out.value = "";
    if (halfWrap) halfWrap.classList.add("hidden");
    if (halfInput) halfInput.checked = false;
    return;
  }

  const sameDay = start === end;

  if (halfWrap) {
    halfWrap.classList.toggle("hidden", !sameDay);
  }

  if (!sameDay && halfInput && halfInput.checked) {
    halfInput.checked = false;
  }

  let days;
  if (sameDay && halfInput?.checked) {
    days = 0.5;
  } else {
    days = Math.floor((e.getTime() - s.getTime()) / 86400000) + 1;
  }

  out.value = Number.isInteger(days) ? String(days) : days.toFixed(1);

  if (typeof updateTypeRemainingHint === "function") {
    updateTypeRemainingHint();
  }
}

// ============================================
// Search
// ============================================
function filterLeaves() {
  const q = (document.getElementById("leaveSearch")?.value || "").toLowerCase();
  const rows = document.querySelectorAll(".leave-row");
  let visible = 0;

  rows.forEach((r) => {
    const haystack = r.dataset.search || "";
    const match = !q || haystack.includes(q);
    r.classList.toggle("hidden", !match);
    if (match) visible++;
  });

  document
    .getElementById("leavesEmpty")
    ?.classList.toggle("hidden", visible > 0);
}

// ============================================
// Submit leave
// ============================================
document.addEventListener("DOMContentLoaded", () => {
  if (typeof initIcons === "function") initIcons();
  initLeaveDatePickers();

  document
    .getElementById("leaveForm")
    ?.addEventListener("submit", async function (e) {
      e.preventDefault();

      const type = document.getElementById("leaveType").value;
      const start =
        fpStart?.input?.value || document.getElementById("leaveStart").value;
      const end =
        fpEnd?.input?.value || document.getElementById("leaveEnd").value;
      const reason = document.getElementById("leaveReason").value.trim();
      const isHalfDay = document.getElementById("isHalfDay")?.checked === true;

      if (!type) {
        showError("Missing leave type", "Please select a leave type.");
        return;
      }
      if (!start) {
        showError("Missing start date", "Please pick a start date.");
        return;
      }
      if (!end) {
        showError("Missing end date", "Please pick an end date.");
        return;
      }
      if (!reason) {
        showError("Missing reason", "Please provide a reason.");
        return;
      }

      const today = new Date();
      today.setHours(0, 0, 0, 0);

      const startDate = new Date(start + "T00:00:00");
      const endDate = new Date(end + "T00:00:00");

      if (startDate < today) {
        showError(
          "Invalid start date",
          "You cannot file a leave with a start date in the past."
        );
        return;
      }
      if (endDate < startDate) {
        showError(
          "Invalid date range",
          "End date cannot be before the start date."
        );
        return;
      }

      const sameDay = start === end;
      const days =
        sameDay && isHalfDay
          ? 0.5
          : Math.floor((endDate - startDate) / 86400000) + 1;

      const limits = window.CREDIT_LIMITS || {};
      const totalLimit = parseFloat(limits.total) || 0;
      const typeCaps = limits.typeCaps || {};
      const typeRemaining = limits.typeRemaining || {};

      if (days > totalLimit) {
        showError(
          "Not enough leave credits",
          `You are requesting ${days} day(s) but only have ${totalLimit.toFixed(
            2
          )} total remaining.`
        );
        return;
      }

      if (typeCaps[type] !== undefined) {
        const typeLeft = parseFloat(typeRemaining[type]) || 0;
        if (days > typeLeft) {
          const typeLabel = type.charAt(0).toUpperCase() + type.slice(1);
          showError(
            `${typeLabel} leave cap reached`,
            `${typeLabel} leave is capped at ${
              typeCaps[type]
            } day(s). You have ${typeLeft.toFixed(
              2
            )} day(s) remaining for this type.`
          );
          return;
        }
      }

      const fd = new FormData(this);
      fd.append("action", "create");

      closeLeaveModal();
      showLoading("Submitting...", "Filing your leave request.");

      try {
        const res = await fetch("/api/leaves", { method: "POST", body: fd });
        const data = await res.json();

        await new Promise((r) => setTimeout(r, 500));
        hideLoading();

        if (data.success) {
          document.getElementById("successTitle").textContent = "Leave filed";
          document.getElementById("successMessage").textContent =
            "Your request has been submitted for approval.";
          showModal("successModal", "successCard");
          initIcons();
        } else {
          showError("Could not submit leave", data.error || "Unknown error");
        }
      } catch (err) {
        hideLoading();
        console.error(err);
        showError(
          "Connection error",
          "Unable to reach the server. Please try again."
        );
      }
    });
});

function closeSuccessModal() {
  hideModal("successModal", "successCard");
  setTimeout(() => location.reload(), 200);
}

// ============================================
// Cancel leave — custom modal (no native confirm)
// ============================================
let _pendingCancelId = null;

function cancelLeave(id) {
  // Find the leave row so we can show what's being cancelled
  const row = document.querySelector(`.leave-row[data-leave-id="${id}"]`);
  let summary = "This action cannot be undone.";

  // Best-effort: pull type + dates from the row's data-search haystack
  if (row) {
    // Nothing structured on the row yet — fall back to a generic message.
    // If you want richer copy, add data-type/data-start/data-end
    // attributes to each .leave-row in the PHP and read them here.
    summary =
      "The leave request will be marked as cancelled and removed from the pending queue.";
  }

  _pendingCancelId = id;

  const summaryEl = document.getElementById("cancelLeaveSummary");
  if (summaryEl) summaryEl.textContent = summary;

  // Wire the confirm button
  const confirmBtn = document.getElementById("cancelLeaveConfirmBtn");
  if (confirmBtn) {
    confirmBtn.onclick = confirmCancelLeave;
  }

  showModal("cancelLeaveModal", "cancelLeaveCard");
  if (typeof initIcons === "function") initIcons();
}

function closeCancelLeaveModal() {
  hideModal("cancelLeaveModal", "cancelLeaveCard");
  _pendingCancelId = null;
}

async function confirmCancelLeave() {
  const id = _pendingCancelId;
  if (!id) return;

  closeCancelLeaveModal();

  showLoading("Cancelling...", "Updating your request.");

  const fd = new FormData();
  fd.append("action", "cancel");
  fd.append("id", id);

  try {
    const res = await fetch("/api/leaves", { method: "POST", body: fd });
    const data = await res.json();

    await new Promise((r) => setTimeout(r, 400));
    hideLoading();

    if (data.success) {
      showModal("cancelSuccessModal", "cancelSuccessCard");
      if (typeof initIcons === "function") initIcons();
    } else {
      showError("Could not cancel", data.error || "Unknown error");
    }
  } catch (err) {
    hideLoading();
    console.error(err);
    showError("Connection error", "Unable to reach the server.");
  }
}

// ============================================
// Close cancel-success modal and reload the page
// (fired by the "Done" button)
// ============================================
function finishCancelLeave() {
    hideModal("cancelSuccessModal", "cancelSuccessCard");
    setTimeout(() => location.reload(), 200);
}