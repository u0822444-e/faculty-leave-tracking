// ============================================
// Leave Monitoring — Faculty leadership view
// ============================================

// ============================================
// Helpers
// ============================================
function getTypeClasses(typeStr) {
    const t = (typeStr || "").toLowerCase();
    if (t.includes('sick')) return 'text-rose-700 bg-rose-50';
    if (t.includes('vacation')) return 'text-blue-700 bg-blue-50';
    return 'text-slate-700 bg-slate-100';
}

// ============================================
// Filter
// ============================================
function filterFacultyLeaves() {
    const search = (document.getElementById("flSearch")?.value || "").toLowerCase();
    const status = document.getElementById("flStatus")?.value || "";

    const rows = document.querySelectorAll(".faculty-leave-row");
    let visible = 0;

    rows.forEach((row) => {
        const dataSearch = row.dataset.search || "";
        const dataStatus = row.dataset.status || "";

        const matchesSearch = !search || dataSearch.includes(search);
        const matchesStatus = !status || dataStatus === status;

        const matches = matchesSearch && matchesStatus;
        row.classList.toggle("hidden", !matches);
        if (matches) visible++;
    });

    document.getElementById("flEmpty")?.classList.toggle("hidden", visible > 0);
}

// ============================================
// View leave — split-pane modal
// ============================================
function openViewFacultyLeave(data) {
    const $ = (id) => document.getElementById(id);

    $("flvRequestId").textContent = data.id ? `Request #${data.id}` : "—";

    // ── Sidebar: employee ──
    const name = data.name || "—";
    const initials = name
        .split(/\s+/)
        .filter(Boolean)
        .map((w) => w[0])
        .slice(0, 2)
        .join("")
        .toUpperCase();

    $("flvInitials").textContent = initials || "—";
    $("flvName").textContent = name;
    $("flvPosition").textContent =
        data.position && data.position !== "—" ? data.position : "—";

    let dept = "";
    if (data.year_level && data.year_level !== "—") dept = data.year_level;
    else if (data.subcat && data.subcat !== "—") dept = data.subcat;
    else if (data.category && data.category !== "—") dept = data.category;

    $("flvDepartment").textContent = dept || "—";
    $("flvEmail").textContent = data.email || "—";

    // ── Leave type (header + sidebar) ──
    const typeClasses = getTypeClasses(data.type);
    const typeLabel = data.type || "—";

    // Header pill
    const headerType = $("flvHeaderType");
    headerType.textContent = typeLabel;
    headerType.className =
        "inline-flex items-center px-2 py-0.5 rounded text-xs font-medium " + typeClasses;

    // Sidebar badge
    const sidebarType = $("flvSidebarType");
    if (sidebarType) {
        const span = sidebarType.querySelector("span:last-child");
        if (span) span.textContent = typeLabel;
        sidebarType.className =
            "inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium border border-[#E0E0E0] " +
            typeClasses;
    }

    // ── Sidebar: credits ──
    const credits = data.credits || { total: 0, used: 0, remaining: 0 };
    const fmtShort = (n) => {
        const v = parseFloat(n) || 0;
        return Number.isInteger(v) ? String(v) : v.toFixed(1);
    };

    $("flvCreditTotal").textContent = `${fmtShort(credits.remaining)} / ${fmtShort(credits.total)}`;
    $("flvCreditUsed").textContent = `${fmtShort(credits.used)} day(s)`;
    $("flvCreditRemaining").textContent = `${fmtShort(credits.remaining)} day(s)`;

    const pct = credits.total > 0 ? Math.min(100, (credits.remaining / credits.total) * 100) : 0;
    const bar = $("flvCreditBar");
    bar.style.width = pct + "%";
    bar.className =
        (pct > 50 ? "bg-[#0F5E3D]" : pct > 20 ? "bg-amber-500" : "bg-red-500") +
        " h-full";

    // ── Status pill ──
    const statusKey = (data.status || "").toLowerCase();
    const statusClasses = {
        pending: "text-amber-700 bg-amber-50",
        approved: "text-[#0F5E3D] bg-[#F1FDF6]",
        rejected: "text-red-700 bg-red-50",
        cancelled: "text-slate-700 bg-slate-100",
    };
    const statusLabels = {
        pending: "Pending",
        approved: "Approved",
        rejected: "Rejected",
        cancelled: "Cancelled",
    };
    const statusEl = $("flvStatusBadge");
    statusEl.textContent = statusLabels[statusKey] || data.status || "—";
    statusEl.className =
        "inline-flex items-center px-2.5 py-1 rounded-md text-sm font-medium " +
        (statusClasses[statusKey] || statusClasses.cancelled);

    // ── Dates ──
    const fmtDate = (iso) => {
        if (!iso) return "—";
        const d = new Date(iso + "T00:00:00");
        return d.toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" });
    };
    const fmtDateTime = (iso) => {
        if (!iso) return "—";
        const d = new Date(iso.replace(" ", "T"));
        return (
            d.toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" }) +
            " · " +
            d.toLocaleTimeString("en-US", { hour: "numeric", minute: "2-digit", hour12: true })
        );
    };

    const startStr = fmtDate(data.start);
    const endStr = fmtDate(data.end);
    $("flvDates").textContent = startStr === endStr ? startStr : `${startStr} – ${endStr}`;

    const days = parseFloat(data.days) || 0;
    $("flvDays").textContent = Number.isInteger(days)
        ? `${days} day(s)`
        : `${days.toFixed(1)} day(s)`;

    $("flvHalfDayTag").classList.toggle("hidden", days !== 0.5);
    $("flvCreated").textContent = fmtDateTime(data.created);

    // ── Reason ──
    $("flvReason").textContent = data.reason || "—";

    // ── Decision ──
    const decisionWrap = $("flvDecisionWrap");
    if (statusKey === "approved" || statusKey === "rejected") {
        decisionWrap.classList.remove("hidden");

        const verb = statusKey === "approved" ? "Approved" : "Rejected";
        const approver = data.approver || "an administrator";
        const when = fmtDateTime(data.approved);
        $("flvDecisionSummary").textContent = `${verb} by ${approver} · ${when}`;

        const remarksBlock = $("flvRemarksBlock");
        if (data.remarks && data.remarks.trim() !== "") {
            $("flvRemarks").textContent = `"${data.remarks}"`;
            remarksBlock.classList.remove("hidden");
        } else {
            remarksBlock.classList.add("hidden");
        }
    } else {
        decisionWrap.classList.add("hidden");
    }

    // ── Show ──
    showModal("flViewModal", "flViewCard");
    if (typeof initIcons === "function") initIcons();
}

function closeViewFacultyLeave() {
    hideModal("flViewModal", "flViewCard");
}

// ============================================
// Init
// ============================================
document.addEventListener("DOMContentLoaded", () => {
    if (typeof initIcons === "function") initIcons();
});