// ============================================
// Faculty Leaves — logic
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

  // Blocked ranges: any pending/approved leave (from PHP)
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

  // ---- Start ----
  fpStart = flatpickr("#leaveStart", {
    ...commonOpts,
    onChange: function (selectedDates, dateStr) {
      if (!dateStr) return;
      // End date can't be before the newly picked start
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

  // ---- End ----
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

  // Set both pickers to the server-provided default
  if (fpStart) fpStart.setDate(defaultStart, false);
  if (fpEnd) fpEnd.setDate(defaultStart, false);

  // Lock end's minimum to the default start
  if (fpEnd) fpEnd.set("minDate", defaultStart);

  document.getElementById("leaveDateHint")?.classList.add("hidden");
  updateDaysCount();

  showModal("leaveModal", "leaveCard");
  initIcons();
}

function closeLeaveModal() {
  hideModal("leaveModal", "leaveCard");
}

function openViewLeave(data) {
  document.getElementById("vlType").textContent = data.type;
  document.getElementById("vlStatus").textContent = data.status;
  document.getElementById("vlStart").textContent = formatDate(data.start);
  document.getElementById("vlEnd").textContent = formatDate(data.end);
  document.getElementById("vlDays").textContent = parseFloat(data.days).toFixed(
    2
  );
  document.getElementById("vlCreated").textContent = formatDateTime(
    data.created
  );
  document.getElementById("vlReason").textContent = data.reason || "—";

  const remWrap = document.getElementById("vlRemarksWrap");
  if (data.remarks) {
    document.getElementById("vlRemarks").textContent = data.remarks;
    remWrap.classList.remove("hidden");
  } else {
    remWrap.classList.add("hidden");
  }

  showModal("viewLeaveModal", "viewLeaveCard");
  initIcons();
}

function closeViewLeave() {
  hideModal("viewLeaveModal", "viewLeaveCard");
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
  return d.toLocaleDateString("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
  });
}

function updateDaysCount() {
  const out = document.getElementById("leaveDays");

  // Read raw values from the hidden inputs (Flatpickr stores Y-m-d there)
  const start =
    fpStart?.input?.value || document.getElementById("leaveStart").value;
  const end = fpEnd?.input?.value || document.getElementById("leaveEnd").value;

  if (!start || !end) {
    out.value = "";
    return;
  }

  const s = new Date(start + "T00:00:00");
  const e = new Date(end + "T00:00:00");

  if (e < s) {
    out.value = "";
    return;
  }

  const diffMs = e.getTime() - s.getTime();
  const days = Math.floor(diffMs / 86400000) + 1;
  out.value = days.toFixed(2);
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

  // Build the Flatpickr calendar before anything else
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

      const days = Math.floor((endDate - startDate) / 86400000) + 1;

      const limits = window.CREDIT_LIMITS || {};
      const totalLimit = parseFloat(limits.total) || 0;
      const typeCaps = limits.typeCaps || {};
      const typeRemaining = limits.typeRemaining || {};

      // Total pool check
      if (days > totalLimit) {
        showError(
          "Not enough leave credits",
          `You are requesting ${days} day(s) but only have ${totalLimit.toFixed(
            2
          )} total remaining.`
        );
        return;
      }

      // Per-type cap check
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
// Cancel leave
// ============================================
async function cancelLeave(id) {
  if (!confirm("Cancel this leave request?")) return;

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
      location.reload();
    } else {
      showError("Could not cancel", data.error || "Unknown error");
    }
  } catch (err) {
    hideLoading();
    console.error(err);
    showError("Connection error", "Unable to reach the server.");
  }
}
