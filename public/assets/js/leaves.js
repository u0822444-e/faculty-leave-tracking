// ============================================
// Admin Leaves — logic
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
    return d.toLocaleString('en-US', {
        month: 'short', day: 'numeric', year: 'numeric',
        hour: 'numeric', minute: '2-digit'
    });
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
// View details
// ============================================
function openViewLeave(data) {
    document.getElementById('vlName').textContent    = data.name || '—';
    document.getElementById('vlEmail').textContent   = data.email || '';
    document.getElementById('vlType').textContent    = data.type || '—';
    document.getElementById('vlStatus').textContent  = data.status || '—';
    document.getElementById('vlStart').textContent   = formatDate(data.start);
    document.getElementById('vlEnd').textContent     = formatDate(data.end);
    document.getElementById('vlDays').textContent    = parseFloat(data.days || 0).toFixed(2);
    document.getElementById('vlCreated').textContent = formatDateTime(data.created);
    document.getElementById('vlReason').textContent  = data.reason || '—';

    const remWrap = document.getElementById('vlRemarksWrap');
    if (data.remarks) {
        document.getElementById('vlRemarks').textContent = data.remarks;
        remWrap.classList.remove('hidden');
    } else {
        remWrap.classList.add('hidden');
    }

    const appWrap = document.getElementById('vlApproverWrap');
    if (data.approver) {
        document.getElementById('vlApprover').textContent =
            data.approver + (data.approved ? ' · ' + formatDateTime(data.approved) : '');
        appWrap.classList.remove('hidden');
    } else {
        appWrap.classList.add('hidden');
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