// ============================================
// Leave Monitoring — logic
// ============================================

// ============================================
// Helpers
// ============================================
function formatFlDate(iso) {
    if (!iso) return '—';
    const d = new Date(String(iso).replace(' ', 'T').split('T')[0] + 'T00:00:00');
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function formatFlDateTime(iso) {
    if (!iso) return '—';
    const d = new Date(String(iso).replace(' ', 'T'));
    return d.toLocaleDateString('en-US', {
        month: 'short', day: 'numeric', year: 'numeric'
    }) + ' · ' + d.toLocaleTimeString('en-US', {
        hour: 'numeric', minute: '2-digit', hour12: true
    });
}

function fmtFlDays(n) {
    const v = parseFloat(n) || 0;
    return Number.isInteger(v) ? String(v) + ' day(s)' : v.toFixed(1) + ' day(s)';
}

// ============================================
// Search / filter
// ============================================
function filterFacultyLeaves() {
    const q = (document.getElementById('flSearch')?.value || '').toLowerCase();
    const status = document.getElementById('flStatus')?.value || '';

    const rows = document.querySelectorAll('.faculty-leave-row');
    let visible = 0;

    rows.forEach(r => {
        const haystack = r.dataset.search || '';
        const s = r.dataset.status || '';
        const match = (!q || haystack.includes(q)) && (!status || s === status);
        r.classList.toggle('hidden', !match);
        if (match) visible++;
    });

    document.getElementById('flEmpty')?.classList.toggle('hidden', visible > 0);
}

// ============================================
// View details — rich read-only modal
// ============================================
function openViewFacultyLeave(data) {
    // Request id
    document.getElementById('flvRequestId').textContent = data.id ? `Request #${data.id}` : '—';

    // ── Status ribbon ───────────────────────
    const status = (data.status || '').toLowerCase();
    const statusMap = {
        pending: {
            label: 'Pending',
            icon: 'clock',
            cls: 'text-amber-700 bg-amber-50 border-amber-200',
            ribbon: 'bg-amber-50 border-amber-200',
            iconCls: 'text-amber-600'
        },
        approved: {
            label: 'Approved',
            icon: 'check-circle-2',
            cls: 'text-[#0F5E3D] bg-[#F1FDF6] border-[#0F5E3D]/15',
            ribbon: 'bg-[#F1FDF6] border-[#0F5E3D]/15',
            iconCls: 'text-[#0F5E3D]'
        },
        rejected: {
            label: 'Rejected',
            icon: 'x-circle',
            cls: 'text-red-700 bg-red-50 border-red-200',
            ribbon: 'bg-red-50 border-red-200',
            iconCls: 'text-red-600'
        },
        cancelled: {
            label: 'Cancelled',
            icon: 'ban',
            cls: 'text-slate-600 bg-slate-100 border-slate-200',
            ribbon: 'bg-slate-50 border-slate-200',
            iconCls: 'text-slate-500'
        }
    };
    const s = statusMap[status] || statusMap.cancelled;

    document.getElementById('flvStatus').textContent = s.label;
    document.getElementById('flvStatusBadge').textContent = s.label;
    document.getElementById('flvStatusBadge').className =
        `inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-medium border ${s.cls}`;
    document.getElementById('flvStatusRibbon').className =
        `flex items-center gap-3 p-3 rounded-xl border ${s.ribbon}`;
    document.getElementById('flvStatusIcon').className =
        `w-9 h-9 rounded-lg bg-white flex items-center justify-center shrink-0 ${s.iconCls}`;
    document.getElementById('flvStatusIcon').innerHTML =
        `<i data-lucide="${s.icon}" class="w-4 h-4"></i>`;

    // ── Employee block ──────────────────────
    const name = data.name || '—';
    const initials = name.split(/\s+/).filter(Boolean).map(w => w[0]).slice(0, 2).join('').toUpperCase();
    document.getElementById('flvInitials').textContent = initials || '—';
    document.getElementById('flvName').textContent    = name;
    document.getElementById('flvEmail').textContent   = data.email || '—';

    const setChip = (chipId, valueId, value) => {
        const chip = document.getElementById(chipId);
        if (!chip) return;
        if (value && value !== '—') {
            document.getElementById(valueId).textContent = value;
            chip.classList.remove('hidden');
        } else {
            chip.classList.add('hidden');
        }
    };

    setChip('flvCategoryChip',   'flvCategory',   data.category);
    setChip('flvSubCatChip',     'flvSubCat',     data.subcat);
    setChip('flvYearChip',       'flvYear',       data.year_level);
    setChip('flvPositionChip',   'flvPosition',   data.position);
    setChip('flvEmploymentChip', 'flvEmployment', data.employment);

    // ── Request block ───────────────────────
    document.getElementById('flvType').textContent    = data.type  || '—';
    document.getElementById('flvDays').textContent    = fmtFlDays(data.days);
    document.getElementById('flvStart').textContent   = formatFlDate(data.start);
    document.getElementById('flvEnd').textContent     = formatFlDate(data.end);
    document.getElementById('flvCreated').textContent = formatFlDateTime(data.created);

    const days = parseFloat(data.days) || 0;
    document.getElementById('flvHalfDayTag').classList.toggle('hidden', days !== 0.5);

    // ── Reason ──────────────────────────────
    document.getElementById('flvReason').textContent = data.reason || '—';

    // ── Decision block ──────────────────────
    const decisionWrap = document.getElementById('flvDecisionWrap');
    if (status === 'approved' || status === 'rejected') {
        decisionWrap.classList.remove('hidden');
        document.getElementById('flvApprover').textContent   = data.approver || '—';
        document.getElementById('flvApprovedAt').textContent = formatFlDateTime(data.approved);

        const remarksBlock = document.getElementById('flvRemarksBlock');
        if (data.remarks && data.remarks.trim() !== '') {
            document.getElementById('flvRemarks').textContent = data.remarks;
            remarksBlock.classList.remove('hidden');
        } else {
            remarksBlock.classList.add('hidden');
        }
    } else {
        decisionWrap.classList.add('hidden');
    }

    showModal('flViewModal', 'flViewCard');
    initIcons();
}

function closeViewFacultyLeave() {
    hideModal('flViewModal', 'flViewCard');
}

document.addEventListener('DOMContentLoaded', () => {
    if (typeof initIcons === 'function') initIcons();
});