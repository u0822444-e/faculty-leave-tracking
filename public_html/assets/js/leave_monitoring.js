// ============================================
// Leave Monitoring — Faculty leadership view
// Filters + pagination + search
// ============================================

const PER_PAGE = 10;
let currentPage = 1;

// ============================================
// Helpers
// ============================================
function getTypeClasses(typeStr) {
    const t = (typeStr || "").toLowerCase();
    if (t.includes('sick')) return 'text-rose-700 bg-rose-50';
    if (t.includes('vacation') || t.includes('mandatory')) return 'text-blue-700 bg-blue-50';
    return 'text-slate-700 bg-slate-100';
}

// ============================================
// Apply filters + pagination
// Called by: status/type/position selects, search input
// ============================================
function applyFilters(resetPage = true) {
    if (resetPage) currentPage = 1;
    renderPage();
}

// Backwards-compat alias (old code calls filterFacultyLeaves)
function filterFacultyLeaves() {
    applyFilters(true);
}

// ============================================
// Compute the filtered set of rows
// ============================================
function getFilteredRows() {
    const status   = (document.getElementById("flStatus")?.value   || "").trim();
    const type     = (document.getElementById("flType")?.value     || "").trim();
    const position = (document.getElementById("flPosition")?.value || "").trim();
    const search   = (document.getElementById("flSearch")?.value   || "").toLowerCase().trim();

    const rows = Array.from(document.querySelectorAll(".faculty-leave-row"));

    return rows.filter((row) => {
        const rSearch   = row.dataset.search   || "";
        const rStatus   = row.dataset.status   || "";
        const rType     = row.dataset.type     || "";
        const rPosition = row.dataset.position || "";

        const matchStatus   = !status   || rStatus   === status;
        const matchType     = !type     || rType     === type;
        const matchPosition = !position || rPosition === position;
        const matchSearch   = !search   || rSearch.includes(search);

        return matchStatus && matchType && matchPosition && matchSearch;
    });
}

// ============================================
// Render current page
// ============================================
function renderPage() {
    const allRows = Array.from(document.querySelectorAll(".faculty-leave-row"));
    const filtered = getFilteredRows();

    // Hide everything first
    allRows.forEach((r) => r.classList.add("hidden"));

    // Slice for pagination
    const total       = filtered.length;
    const totalPages  = Math.max(1, Math.ceil(total / PER_PAGE));
    if (currentPage > totalPages) currentPage = totalPages;

    const start = (currentPage - 1) * PER_PAGE;
    const end   = Math.min(start + PER_PAGE, total);

    filtered.slice(start, end).forEach((r) => r.classList.remove("hidden"));

    // Empty state
    document.getElementById("flEmpty")?.classList.toggle("hidden", total > 0);

    // Info text
    const info = document.getElementById("flPaginationInfo");
    if (info) {
        if (total === 0) {
            info.innerHTML = `Showing <span class="font-medium text-[#2C3E50]">0</span> of <span class="font-medium text-[#2C3E50]">0</span> requests`;
        } else {
            info.innerHTML = `Showing <span class="font-medium text-[#2C3E50]">${start + 1}–${end}</span> of <span class="font-medium text-[#2C3E50]">${total}</span> requests`;
        }
    }

    // Render pagination nav
    renderPagination(totalPages);
}

// ============================================
// Render Prev / Pages / Next
// ============================================
function renderPagination(totalPages) {
    const nav = document.getElementById("flPaginationNav");
    if (!nav) return;

    if (totalPages <= 1) {
        nav.innerHTML = "";
        return;
    }

    const btnBase =
        "w-8 h-8 rounded-md flex items-center justify-center text-[#2C3E50]/70 border border-[#E0E0E0] transition";
    const btnEnabled = " hover:bg-[#F1FDF6] hover:text-[#0F5E3D]";
    const btnDisabled = " opacity-30 cursor-not-allowed";

    let html = "";

    // Prev
    if (currentPage > 1) {
        html += `<button type="button" onclick="goToPage(${currentPage - 1})" class="${btnBase}${btnEnabled}" title="Previous">
                    <i data-lucide="chevron-left" class="w-4 h-4"></i>
                 </button>`;
    } else {
        html += `<button type="button" disabled class="${btnBase}${btnDisabled}">
                    <i data-lucide="chevron-left" class="w-4 h-4"></i>
                 </button>`;
    }

    // Numbered pages (window of 5)
    let startPage = Math.max(1, currentPage - 2);
    let endPage   = Math.min(totalPages, startPage + 4);
    startPage     = Math.max(1, endPage - 4);

    for (let p = startPage; p <= endPage; p++) {
        if (p === currentPage) {
            html += `<span class="w-8 h-8 rounded-md bg-[#0F5E3D] text-white text-xs font-medium flex items-center justify-center">${p}</span>`;
        } else {
            html += `<button type="button" onclick="goToPage(${p})" class="w-8 h-8 rounded-md flex items-center justify-center text-[#2C3E50]/70 hover:bg-[#F1FDF6] hover:text-[#0F5E3D] transition text-xs font-medium">${p}</button>`;
        }
    }

    // Next
    if (currentPage < totalPages) {
        html += `<button type="button" onclick="goToPage(${currentPage + 1})" class="${btnBase}${btnEnabled}" title="Next">
                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                 </button>`;
    } else {
        html += `<button type="button" disabled class="${btnBase}${btnDisabled}">
                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                 </button>`;
    }

    nav.innerHTML = html;
    if (typeof initIcons === "function") initIcons();
}

// ============================================
// Page navigation
// ============================================
function goToPage(p) {
    currentPage = p;
    renderPage();
    // Scroll the table into view for good UX
    document.querySelector("table")?.scrollIntoView({ behavior: "smooth", block: "start" });
}

// ============================================
// Clear all filters
// ============================================
function clearFilters() {
    const s = document.getElementById("flStatus");
    const t = document.getElementById("flType");
    const p = document.getElementById("flPosition");
    const q = document.getElementById("flSearch");
    if (s) s.value = "";
    if (t) t.value = "";
    if (p) p.value = "";
    if (q) q.value = "";
    applyFilters(true);
    q?.focus();
}

// ============================================
// View leave — split-pane modal
// ============================================
function openViewFacultyLeave(data) {
    const $ = (id) => document.getElementById(id);

    $("flvRequestId").textContent = data.id ? `Request #${data.id}` : "—";

    // Sidebar: employee
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

    // Leave type
    const typeClasses = getTypeClasses(data.type);
    const typeLabel = data.type || "—";

    const headerType = $("flvHeaderType");
    headerType.textContent = typeLabel;
    headerType.className =
        "inline-flex items-center px-2 py-0.5 rounded text-xs font-medium " + typeClasses;

    const sidebarType = $("flvSidebarType");
    if (sidebarType) {
        const span = sidebarType.querySelector("span:last-child");
        if (span) span.textContent = typeLabel;
        sidebarType.className =
            "inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium border border-[#E0E0E0] " +
            typeClasses;
    }

    // Credits
    const credits = data.credits || { total: 0, used: 0, remaining: 0, by_type: {} };
    
    const fmtShort = (n) => {
        const v = parseFloat(n) || 0;
        return Number.isInteger(v) ? String(v) : v.toFixed(1);
    };
    
    $("flvCreditTotal").textContent = `${fmtShort(credits.remaining)} / ${fmtShort(credits.total)}`;

    const creditRows = [];
    const TYPE_LABELS = {
        vacation: 'Vacation', sick: 'Sick',
        maternity: 'Maternity', paternity: 'Paternity',
        terminal: 'Terminal', other: 'Others'
    };

    Object.keys(credits.by_type || {}).forEach(t => {
        const c = credits.by_type[t];
        if (c.cap === null && c.used === 0) return;

        let bar = '';
        if (c.cap !== null) {
            const pct = c.cap > 0 ? Math.min(100, (c.remaining / c.cap) * 100) : 0;
            const barColor = pct > 50 ? 'bg-[#0F5E3D]' : pct > 20 ? 'bg-amber-500' : 'bg-red-500';
            bar = `
                <div class="mt-1.5 h-1 bg-[#E0E0E0] rounded-full overflow-hidden">
                    <div class="${barColor} h-full" style="width: ${pct}%"></div>
                </div>
            `;
        } else {
            bar = `<div class="mt-1.5 h-1 bg-[#F0F0F0] rounded-full"></div>`;
        }

        const rightLabel = c.cap === null
            ? `<span class="text-sm text-[#2C3E50]/50">${fmtShort(c.used)} used</span>`
            : `<span class="text-sm text-[#2C3E50]">${fmtShort(c.remaining)}<span class="text-[#2C3E50]/40">/${fmtShort(c.cap)}</span></span>`;

        creditRows.push(`
            <div>
                <div class="flex items-baseline justify-between gap-2">
                    <span class="text-sm text-[#2C3E50]">${TYPE_LABELS[t] || t}</span>
                    ${rightLabel}
                </div>
                ${bar}
            </div>
        `);
    });

    $("flvCreditsList").innerHTML = creditRows.join('');

    // Status
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

    // Dates
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

    // Reason
    $("flvReason").textContent = data.reason || "—";

    // Dean Endorsement
    const deanWrap = $("flvDeanWrap");
    if (statusKey === "pending" && data.dean_approved) {
        deanWrap.classList.remove("hidden");
        $("flvDeanSummary").textContent = `Endorsed by ${data.dean_name || 'Leadership'} · ${fmtDateTime(data.dean_approved)}`;
    } else if (statusKey === "pending" && !data.dean_approved) {
        deanWrap.classList.remove("hidden");
        $("flvDeanSummary").textContent = "Awaiting endorsement";
        $("flvDeanSummary").className = "text-sm text-amber-600 font-medium italic";
    } else {
        deanWrap.classList.add("hidden");
    }

    // HR Decision
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

    // Action Bar (Endorse Button)
    const actionBar = $("flvActionBar");
    actionBar.innerHTML = `
        <button onclick="closeViewFacultyLeave()" class="w-full border border-[#E0E0E0] text-[#2C3E50] hover:bg-gray-50 font-medium py-2.5 rounded-md transition text-sm">
            Close
        </button>
    `;

    if (data.user_position === 'Academic Dean' && statusKey === 'pending' && !data.dean_approved) {
        actionBar.innerHTML = `
            <div class="flex gap-2 w-full">
                <button onclick="closeViewFacultyLeave()" class="flex-1 border border-[#E0E0E0] text-[#2C3E50] hover:bg-gray-50 font-medium py-2.5 rounded-md transition text-sm">
                    Close
                </button>
                <button onclick="promptEndorse(${data.id})" class="flex-1 bg-[#0F5E3D] hover:bg-[#0a4a2f] text-white font-medium py-2.5 rounded-md transition text-sm flex items-center justify-center gap-2">
                    <i data-lucide="check-circle" class="w-4 h-4"></i> Endorse to HR
                </button>
            </div>
        `;
    }

    showModal("flViewModal", "flViewCard");
    if (typeof initIcons === "function") initIcons();
}

function closeViewFacultyLeave() {
    hideModal("flViewModal", "flViewCard");
}

// Endorse flow
function promptEndorse(id) {
    document.getElementById("confirmEndorseBtn").onclick = () => executeEndorse(id);
    showModal("confirmModal", "confirmCard");
    if (typeof initIcons === "function") initIcons();
}

function closeConfirmModal() {
    hideModal("confirmModal", "confirmCard");
}

async function executeEndorse(id) {
    closeConfirmModal();
    
    const fd = new FormData();
    fd.append("action", "endorse_dean");
    fd.append("id", id);
    
    if (typeof showLoading === 'function') {
        showLoading("Endorsing...", "Forwarding request to HR.");
    }
    
    try {
        const res = await fetch("/api/leaves", { method: "POST", body: fd });
        const data = await res.json();
        
        if (typeof hideLoading === 'function') hideLoading();
        
        if (data.success) {
            closeViewFacultyLeave(); 
            document.getElementById('successTitle').textContent = "Endorsed Successfully";
            document.getElementById('successMessage').textContent = "The leave request has been forwarded to HR for final approval.";
            showModal('successModal', 'successCard');
            if (typeof initIcons === "function") initIcons();
        } else {
            alert("Error: " + (data.error || "Could not endorse request."));
        }
    } catch (err) {
        if (typeof hideLoading === 'function') hideLoading();
        console.error(err);
        alert("Connection error. Unable to reach the server.");
    }
}

// ============================================
// Keyboard shortcut: "/" focuses search
// ============================================
document.addEventListener("keydown", (e) => {
    const tag = (document.activeElement?.tagName || "").toLowerCase();
    if (e.key === "/" && tag !== "input" && tag !== "textarea" && tag !== "select") {
        e.preventDefault();
        document.getElementById("flSearch")?.focus();
    }
});

// ============================================
// Init
// ============================================
document.addEventListener("DOMContentLoaded", () => {
    if (typeof initIcons === "function") initIcons();
    renderPage();
});