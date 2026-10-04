// ============================================
// Activity Logs page — logic
// ============================================

let todayOnly = false;

// ============================================
// Filter logs by search + action + date
// ============================================
function filterLogs() {
    const searchEl = document.getElementById('searchInput');
    const actionEl = document.getElementById('actionFilter');
    const search = (searchEl?.value || '').toLowerCase();
    const action = actionEl?.value || '';
    const today  = new Date().toISOString().split('T')[0];

    const rows = document.querySelectorAll('.log-row');
    let visible = 0;

    rows.forEach(row => {
        const matchesSearch = !search || (row.dataset.search || '').includes(search);
        const matchesAction = !action || row.dataset.action === action;
        const matchesDate   = !todayOnly || row.dataset.date === today;

        if (matchesSearch && matchesAction && matchesDate) {
            row.classList.remove('hidden');
            visible++;
        } else {
            row.classList.add('hidden');
        }
    });

    document.getElementById('emptyState')?.classList.toggle('hidden', visible > 0);
}

// ============================================
// Today filter toggle
// ============================================
function filterToday(evt) {
    todayOnly = !todayOnly;

    const btn = (evt && evt.currentTarget) || document.querySelector('[onclick^="filterToday"]');
    if (btn) {
        if (todayOnly) {
            btn.classList.add('bg-[#F1FDF6]', 'text-[#0F5E3D]');
            btn.classList.remove('text-[#2C3E50]/70');
        } else {
            btn.classList.remove('bg-[#F1FDF6]', 'text-[#0F5E3D]');
            btn.classList.add('text-[#2C3E50]/70');
        }
    }

    filterLogs();
}

// ============================================
// Keyboard shortcut: "/" focuses search
// ============================================
document.addEventListener('keydown', function (e) {
    if (e.key === '/' &&
        document.activeElement.tagName !== 'INPUT' &&
        document.activeElement.tagName !== 'TEXTAREA') {
        e.preventDefault();
        document.getElementById('searchInput')?.focus();    
    }
});

// ============================================
// Init
// ============================================
document.addEventListener('DOMContentLoaded', () => {
    if (typeof initIcons === 'function') initIcons();
});