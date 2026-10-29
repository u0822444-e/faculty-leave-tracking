// ============================================
// Faculty/Staff Leaves — logic
// ============================================

const MAX_CREDITS = parseFloat(
  document.getElementById("remainingCredits")?.textContent || "0"
);
const LEAVE_DISABLED = document.body.dataset.leaveDisabled === "1";

let fpStart = null;
let fpEnd = null;

// ============================================
// Flatpickr initialization
// ============================================
function initLeaveDatePickers() {
  if (typeof flatpickr === "undefined") return;

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
  if (halfInput) halfInput.addEventListener("change", updateDaysCount);

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

  if (typeof window.__resetLeaveAttachment === "function") {
    window.__resetLeaveAttachment();
  }

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

function escapeHtml(str) {
  return String(str)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
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

  // ── Header paperclip ──
  const closeBtn = document.querySelector(
    '#viewLeaveModal button[onclick="closeViewLeave()"]'
  );
  if (closeBtn) {
    const existingPaperclip =
      closeBtn.parentElement.querySelector(".vl-header-paperclip");
    if (existingPaperclip) existingPaperclip.remove();

    const a = data.attachment;
    if (a && a.path) {
      const paperclip = document.createElement("a");
      paperclip.className =
        "vl-header-paperclip w-8 h-8 rounded-lg flex items-center justify-center bg-white border border-[#E0E0E0] text-[#0F5E3D] hover:bg-[#F1FDF6] hover:border-[#0F5E3D]/40 transition shrink-0";
      paperclip.href = "/" + a.path;
      paperclip.target = "_blank";
      paperclip.rel = "noopener";
      paperclip.title = "View attachment" + (a.name ? ` (${a.name})` : "");
      paperclip.innerHTML =
        '<i data-lucide="paperclip" class="w-4 h-4"></i>';
      closeBtn.parentElement.insertBefore(paperclip, closeBtn);
    }
  }

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

  // ── Attachment card ──
  const attachWrap = document.getElementById("vlAttachmentWrap");
  if (attachWrap) {
    const a = data.attachment;
    if (a && a.path) {
      const isImage = (a.mime || "").startsWith("image/");
      const sizeLabel = a.size
        ? a.size >= 1024 * 1024
          ? (a.size / (1024 * 1024)).toFixed(2) + " MB"
          : (a.size / 1024).toFixed(1) + " KB"
        : "";

      attachWrap.classList.remove("hidden");
      attachWrap.innerHTML = `
        <p class="text-[10px] uppercase tracking-wider text-[#2C3E50]/40 font-semibold mb-2">Attachment</p>
        <a href="/${a.path}" target="_blank" rel="noopener"
           class="flex items-center gap-2 p-2 bg-white border border-[#E0E0E0] rounded-lg hover:border-[#0F5E3D]/40 hover:bg-[#F1FDF6]/40 transition">
          <div class="w-10 h-10 rounded-md bg-[#F1FDF6] flex items-center justify-center text-[#0F5E3D] shrink-0 overflow-hidden">
            ${
              isImage
                ? `<img src="/${a.path}" alt="" class="w-full h-full object-cover">`
                : `<i data-lucide="file-text" class="w-4 h-4"></i>`
            }
          </div>
          <div class="min-w-0 flex-1">
            <p class="text-xs font-medium text-[#2C3E50] truncate">${escapeHtml(
              a.name || "Attachment"
            )}</p>
            <p class="text-[10px] text-[#2C3E50]/50">${sizeLabel}</p>
          </div>
          <i data-lucide="external-link" class="w-3.5 h-3.5 text-[#2C3E50]/40 shrink-0"></i>
        </a>
      `;
    } else {
      attachWrap.classList.add("hidden");
      attachWrap.innerHTML = "";
    }
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
  if (halfWrap) halfWrap.classList.toggle("hidden", !sameDay);
  if (!sameDay && halfInput && halfInput.checked) halfInput.checked = false;

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
  wireLeaveAttachment();

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

      if (!type) { showError("Missing leave type", "Please select a leave type."); return; }
      if (!start) { showError("Missing start date", "Please pick a start date."); return; }
      if (!end) { showError("Missing end date", "Please pick an end date."); return; }
      if (!reason) { showError("Missing reason", "Please provide a reason."); return; }

      const today = new Date();
      today.setHours(0, 0, 0, 0);

      const startDate = new Date(start + "T00:00:00");
      const endDate = new Date(end + "T00:00:00");

            // Allow past dates for sick leave, block for others
      if (startDate < today && type !== 'sick') {
        showError("Invalid start date", "You cannot file a non-sick leave with a start date in the past.");
        return;
      }
      if (endDate < startDate) {
        showError("Invalid date range", "End date cannot be before the start date.");
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
          `You are requesting ${days} day(s) but only have ${totalLimit.toFixed(2)} total remaining.`
        );
        return;
      }

      if (typeCaps[type] !== undefined) {
        const typeLeft = parseFloat(typeRemaining[type]) || 0;
        if (days > typeLeft) {
          const typeLabel = type.charAt(0).toUpperCase() + type.slice(1);
          showError(
            `${typeLabel} leave cap reached`,
            `${typeLabel} leave is capped at ${typeCaps[type]} day(s). You have ${typeLeft.toFixed(2)} day(s) remaining for this type.`
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
        showError("Connection error", "Unable to reach the server. Please try again.");
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
let _pendingCancelId = null;

function cancelLeave(id) {
  _pendingCancelId = id;

  const summaryEl = document.getElementById("cancelLeaveSummary");
  if (summaryEl) {
    summaryEl.textContent =
      "The leave request will be marked as cancelled and removed from the pending queue.";
  }

  const confirmBtn = document.getElementById("cancelLeaveConfirmBtn");
  if (confirmBtn) confirmBtn.onclick = confirmCancelLeave;

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

function finishCancelLeave() {
  hideModal("cancelSuccessModal", "cancelSuccessCard");
  setTimeout(() => location.reload(), 200);
}

// ============================================
// Attachment picker — preview + clear
// ============================================
function wireLeaveAttachment() {
  const input = document.getElementById("leaveAttachment");
  const label = document.getElementById("leaveAttachmentLabel");
  const hint = document.getElementById("leaveAttachmentHint");
  const preview = document.getElementById("leaveAttachmentPreview");
  const drop = document.getElementById("leaveAttachmentDrop");
  if (!input || !preview || !drop) return;

  const MAX_SIZE = 5 * 1024 * 1024;
  const ALLOWED = ["image/jpeg", "image/png", "image/webp", "application/pdf"];

  function clearPreview() {
    preview.innerHTML = "";
    preview.classList.add("hidden");
    label.textContent = "Click to upload a file";
    hint.textContent = "Medical certificate, letter, etc.";
    input.value = "";
  }

  function showPreview(file) {
    preview.innerHTML = "";
    preview.classList.remove("hidden");

    const wrap = document.createElement("div");
    wrap.className =
      "flex items-center gap-2 p-2 bg-white border border-[#E0E0E0] rounded-lg";

    const isImage = file.type.startsWith("image/");
    const thumb = document.createElement("div");
    thumb.className =
      "w-10 h-10 rounded-md bg-[#F1FDF6] flex items-center justify-center text-[#0F5E3D] shrink-0 overflow-hidden";

    if (isImage) {
      const img = document.createElement("img");
      img.src = URL.createObjectURL(file);
      img.className = "w-full h-full object-cover";
      img.onload = () => URL.revokeObjectURL(img.src);
      thumb.appendChild(img);
    } else {
      thumb.innerHTML = '<i data-lucide="file-text" class="w-4 h-4"></i>';
    }

    const info = document.createElement("div");
    info.className = "min-w-0 flex-1";
    info.innerHTML = `
      <p class="text-xs font-medium text-[#2C3E50] truncate">${escapeHtml(file.name)}</p>
      <p class="text-[10px] text-[#2C3E50]/50">${(file.size / 1024).toFixed(1)} KB</p>
    `;

    const removeBtn = document.createElement("button");
    removeBtn.type = "button";
    removeBtn.className =
      "w-7 h-7 rounded-lg flex items-center justify-center text-[#2C3E50]/60 hover:bg-red-50 hover:text-red-600 transition shrink-0";
    removeBtn.title = "Remove attachment";
    removeBtn.innerHTML = '<i data-lucide="x" class="w-3.5 h-3.5"></i>';
    removeBtn.onclick = (e) => {
      e.preventDefault();
      e.stopPropagation();
      clearPreview();
      if (typeof initIcons === "function") initIcons();
    };

    wrap.appendChild(thumb);
    wrap.appendChild(info);
    wrap.appendChild(removeBtn);
    preview.appendChild(wrap);

    label.textContent = "File attached";
    hint.textContent = "Click to replace";

    if (typeof initIcons === "function") initIcons();
  }

  input.addEventListener("change", () => {
    const file = input.files[0];
    if (!file) {
      clearPreview();
      return;
    }

    if (!ALLOWED.includes(file.type)) {
      if (typeof showError === "function") {
        showError("Invalid file", "Please upload a JPG, PNG, WEBP, or PDF.");
      }
      clearPreview();
      return;
    }

    if (file.size > MAX_SIZE) {
      if (typeof showError === "function") {
        showError("File too large", "Maximum file size is 5 MB.");
      }
      clearPreview();
      return;
    }

    showPreview(file);
  });

  window.__resetLeaveAttachment = clearPreview;
}