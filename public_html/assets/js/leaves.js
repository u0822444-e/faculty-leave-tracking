// ============================================
// Admin & HR Leaves — logic
// ============================================

// ============================================
// Helpers
// ============================================
function formatDate(iso) {
    if (!iso) return '—';
    const d = new Date(iso + 'T00:00:00');
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function formatDateTime(iso) {
    if (!iso) return '—';
    const d = new Date(iso.replace(' ', 'T'));
    return d.toLocaleDateString('en-US', {
        month: 'short', day: 'numeric', year: 'numeric'
    }) + ' · ' + d.toLocaleTimeString('en-US', {
        hour: 'numeric', minute: '2-digit', hour12: true
    });
}

function fmtDays(n) {
    const v = parseFloat(n) || 0;
    return Number.isInteger(v) ? String(v) + ' day(s)' : v.toFixed(1) + ' day(s)';
}

// ============================================
// Filter
// ============================================
function filterLeaves() {
    const search = (document.getElementById('searchInput')?.value || '').toLowerCase();
    const status = document.getElementById('statusFilter')?.value || '';
    const type   = document.getElementById('typeFilter')?.value || '';

    const rows = document.querySelectorAll('tbody tr[data-name], .md\\:hidden > div[data-name]');
    let visible = 0;

    rows.forEach(row => {
        const name = row.dataset.name || '';
        const s    = row.dataset.status || '';
        const t    = row.dataset.type || '';

        const matches = (!search || name.includes(search)) && (!status || s === status) && (!type || t === type);
        row.classList.toggle('hidden', !matches);
        if (matches) visible++;
    });

    document.getElementById('emptyState')?.classList.toggle('hidden', visible > 0);
}

// ============================================
// View details — rich modal
// ============================================
function openViewLeave(data) {
    // Request id
    document.getElementById('vlRequestId').textContent = data.id ? `Request #${data.id}` : '—';

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

    document.getElementById('vlStatus').textContent = s.label;
    document.getElementById('vlStatusBadge').textContent = s.label;
    document.getElementById('vlStatusBadge').className =
        `inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-medium border ${s.cls}`;
    document.getElementById('vlStatusRibbon').className =
        `flex items-center gap-3 p-3 rounded-xl border ${s.ribbon}`;
    document.getElementById('vlStatusIcon').className =
        `w-9 h-9 rounded-lg bg-white flex items-center justify-center shrink-0 ${s.iconCls}`;
    document.getElementById('vlStatusIcon').innerHTML =
        `<i data-lucide="${s.icon}" class="w-4 h-4"></i>`;

    // ── Employee block ──────────────────────
    const name = data.name || '—';
    const initials = name.split(/\s+/).filter(Boolean).map(w => w[0]).slice(0, 2).join('').toUpperCase();
    document.getElementById('vlInitials').textContent = initials || '—';
    document.getElementById('vlName').textContent    = name;
    document.getElementById('vlEmail').textContent   = data.email || '—';

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

    setChip('vlCategoryChip',   'vlCategory',   data.category);
    setChip('vlSubCatChip',     'vlSubCat',     data.subcat);
    setChip('vlYearChip',       'vlYear',       data.year_level);
    setChip('vlPositionChip',   'vlPosition',   data.position);
    setChip('vlEmploymentChip', 'vlEmployment', data.employment);

    // ── Request block ───────────────────────
    document.getElementById('vlType').textContent    = data.type  || '—';
    document.getElementById('vlDays').textContent    = fmtDays(data.days);
    document.getElementById('vlStart').textContent   = formatDate(data.start);
    document.getElementById('vlEnd').textContent     = formatDate(data.end);
    document.getElementById('vlCreated').textContent = formatDateTime(data.created);

    const days = parseFloat(data.days) || 0;
    document.getElementById('vlHalfDayTag').classList.toggle('hidden', days !== 0.5);

    // ── Reason ──────────────────────────────
    document.getElementById('vlReason').textContent = data.reason || '—';

    // ── Decision block ──────────────────────
    const decisionWrap = document.getElementById('vlDecisionWrap');
    if (status === 'approved' || status === 'rejected') {
        decisionWrap.classList.remove('hidden');
        document.getElementById('vlApprover').textContent   = data.approver || '—';
        document.getElementById('vlApprovedAt').textContent = formatDateTime(data.approved);

        const remarksBlock = document.getElementById('vlRemarksBlock');
        if (data.remarks && data.remarks.trim() !== '') {
            document.getElementById('vlRemarks').textContent = data.remarks;
            remarksBlock.classList.remove('hidden');
        } else {
            remarksBlock.classList.add('hidden');
        }
    } else {
        decisionWrap.classList.add('hidden');
    }

    showModal('viewLeaveModal', 'viewLeaveCard');
    initIcons();
}

function closeViewLeave() {
    hideModal('viewLeaveModal', 'viewLeaveCard');
}

// ============================================
// Approve / Reject
// ============================================
function openApproveReject(data) {
    document.getElementById('decideId').value             = data.id;
    document.getElementById('decideDecision').value       = '';
    document.getElementById('decideEmployee').textContent = data.name;
    document.getElementById('decideDays').textContent     = parseFloat(data.days).toFixed(2);
    document.getElementById('decideType').textContent     = data.type;
    document.getElementById('decideRemarks').value        = '';
    document.getElementById('decideSubtitle').textContent = 'Review the request and choose an action';

    showModal('decideModal', 'decideCard');
    initIcons();
}

function closeDecide() {
    hideModal('decideModal', 'decideCard');
}

async function submitDecision(decision) {
    const id = parseInt(document.getElementById('decideId').value || '0');
    const remarks = document.getElementById('decideRemarks').value.trim();

    if (!id) return;

    const fd = new FormData();
    fd.append('action', 'decide');
    fd.append('id', id);
    fd.append('decision', decision);
    fd.append('remarks', remarks);

    closeDecide();
    showLoading(
        'Saving...',
        decision === 'approved' ? 'Approving leave request.' : 'Rejecting leave request.'
    );

    try {
        const res = await fetch('/api/leaves', { method: 'POST', body: fd });
        const data = await res.json();

        await new Promise(r => setTimeout(r, 500));
        hideLoading();

        if (data.success) {
            document.getElementById('successTitle').textContent =
                decision === 'approved' ? 'Leave Approved' : 'Leave Rejected';
            document.getElementById('successMessage').textContent =
                decision === 'approved'
                    ? 'The faculty member will see the approval.'
                    : 'The faculty member will see the rejection.';
            showModal('successModal', 'successCard');
            initIcons();
        } else {
            showError('Could not save decision', data.error || 'Unknown error');
        }
    } catch (err) {
        hideLoading();
        console.error(err);
        showError('Connection error', 'Unable to reach the server.');
    }
}

function closeSuccess() {
    hideModal('successModal', 'successCard');
    setTimeout(() => location.reload(), 200);
}

// ============================================
// Keyboard shortcut: / focuses search
// ============================================
document.addEventListener('keydown', function (e) {
    if (e.key === '/' && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
        e.preventDefault();
        document.getElementById('searchInput')?.focus();
    }
});

document.addEventListener('DOMContentLoaded', () => {
    if (typeof initIcons === 'function') initIcons();
});