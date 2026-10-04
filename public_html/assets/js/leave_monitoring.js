// ============================================
// Search / filter
// ============================================
function filterFacultyLeaves() {
  const q = (document.getElementById("flSearch")?.value || "").toLowerCase();
  const status = document.getElementById("flStatus")?.value || "";

  const rows = document.querySelectorAll(".faculty-leave-row");
  let visible = 0;

  rows.forEach((r) => {
    const haystack = r.dataset.search || "";
    const s = r.dataset.status || "";
    const match = (!q || haystack.includes(q)) && (!status || s === status);
    r.classList.toggle("hidden", !match);
    if (match) visible++;
  });

  document.getElementById("flEmpty")?.classList.toggle("hidden", visible > 0);
}

// ============================================
// View details (read-only)
// ============================================
function openViewFacultyLeave(data) {
  document.getElementById("flvEmployee").textContent = data.employee || "—";
  document.getElementById("flvType").textContent = data.type || "—";
  document.getElementById("flvStatus").textContent = data.status || "—";
  document.getElementById("flvStart").textContent = formatFlDate(data.start);
  document.getElementById("flvEnd").textContent = formatFlDate(data.end);
  document.getElementById("flvDays").textContent = fmtFlDays(data.days);
  document.getElementById("flvCreated").textContent = formatFlDate(
    data.created
  );
  document.getElementById("flvReason").textContent = data.reason || "—";

  const remWrap = document.getElementById("flvRemarksWrap");
  if (data.remarks) {
    document.getElementById("flvRemarks").textContent = data.remarks;
    remWrap.classList.remove("hidden");
  } else {
    remWrap.classList.add("hidden");
  }

  const appWrap = document.getElementById("flvApproverWrap");
  if (data.approver) {
    document.getElementById("flvApprover").textContent = data.approver;
    appWrap.classList.remove("hidden");
  } else {
    appWrap.classList.add("hidden");
  }

  showModal("flViewModal", "flViewCard");
  initIcons();
}

function closeViewFacultyLeave() {
  hideModal("flViewModal", "flViewCard");
}

function formatFlDate(iso) {
  if (!iso) return "—";
  const d = new Date(String(iso).replace(" ", "T").split("T")[0] + "T00:00:00");
  return d.toLocaleDateString("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
  });
}

function fmtFlDays(n) {
  const v = parseFloat(n) || 0;
  return Number.isInteger(v) ? String(v) : v.toFixed(1);
}

document.addEventListener("DOMContentLoaded", () => {
  if (typeof initIcons === "function") initIcons();
});
