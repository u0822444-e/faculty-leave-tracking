// ============================================
// HR Dashboard — Interactive Leave Calendar
// Click any date to see who's on leave.
// ============================================

(function () {
    const data       = window.LEAVE_CALENDAR_DATA || { approved: [], pending: [] };
    const typeColors = window.LEAVE_TYPE_COLORS || {};
    const grid       = document.getElementById('calendarGrid');
    const monthLabel = document.getElementById('calMonthLabel');

    if (!grid || !monthLabel) return;

    const MONTHS = ['January','February','March','April','May','June',
                    'July','August','September','October','November','December'];

    let viewYear  = new Date().getFullYear();
    let viewMonth = new Date().getMonth();

    function pad(n) { return String(n).padStart(2, '0'); }

    // ------------------------------------------------------------
    // Build day index — YYYY-MM-DD -> [leave, ...]
    // ------------------------------------------------------------
    function buildDayIndex() {
        const index = {};

        [...data.approved, ...data.pending].forEach((leave) => {
            const start = new Date(leave.start + 'T00:00:00');
            const end   = new Date(leave.end   + 'T00:00:00');

            for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
                const key = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
                if (!index[key]) index[key] = [];
                index[key].push(leave);
            }
        });

        return index;
    }

    // ------------------------------------------------------------
    // Format a date key (YYYY-MM-DD) as "Fri, Oct 24, 2026"
    // ------------------------------------------------------------
    function formatDateKey(key) {
        const [y, m, d] = key.split('-').map(Number);
        const date = new Date(y, m - 1, d);
        return date.toLocaleDateString('en-US', {
            weekday: 'long',
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });
    }

    // ------------------------------------------------------------
    // Render the month grid
    // ------------------------------------------------------------
    function render() {
        monthLabel.textContent = `${MONTHS[viewMonth]} ${viewYear}`;
        grid.innerHTML = '';

        const dayIndex = buildDayIndex();

        const firstOfMonth = new Date(viewYear, viewMonth, 1);
        const lastOfMonth  = new Date(viewYear, viewMonth + 1, 0);

        const startDayOfWeek = firstOfMonth.getDay();
        const daysInMonth    = lastOfMonth.getDate();

        const today = new Date();
        const isCurrentMonth = (viewYear === today.getFullYear() && viewMonth === today.getMonth());
        const todayDate      = today.getDate();

        // Leading blanks
        for (let i = 0; i < startDayOfWeek; i++) {
            const blank = document.createElement('div');
            blank.className = 'cal-day is-outside';
            grid.appendChild(blank);
        }

        // Day cells
        for (let day = 1; day <= daysInMonth; day++) {
            const dateKey = `${viewYear}-${pad(viewMonth + 1)}-${pad(day)}`;
            const leaves  = dayIndex[dateKey] || [];

            const cell = document.createElement('button');
            cell.type  = 'button';
            cell.className = 'cal-day is-clickable';
            cell.dataset.date = dateKey;

            if (isCurrentMonth && day === todayDate) {
                cell.classList.add('is-today');
            }

            if (leaves.length > 0) {
                cell.classList.add('has-leaves');
            }

            // Day number
            const dayNum = document.createElement('span');
            dayNum.textContent = day;
            cell.appendChild(dayNum);

            // Dots
            if (leaves.length > 0) {
                const dotsWrap = document.createElement('div');
                dotsWrap.className = 'cal-dots';

                leaves.slice(0, 4).forEach((leave) => {
                    const dot = document.createElement('span');
                    dot.className = 'cal-dot';
                    dot.style.background = typeColors[leave.type] || '#64748b';
                    if (leave.status === 'pending') {
                        dot.style.opacity = '0.4';
                        dot.style.border = '1px solid ' + (typeColors[leave.type] || '#64748b');
                        dot.style.background = 'transparent';
                    }
                    dotsWrap.appendChild(dot);
                });

                cell.appendChild(dotsWrap);

                if (leaves.length > 4) {
                    const badge = document.createElement('span');
                    badge.className = 'cal-count';
                    badge.textContent = `+${leaves.length - 4}`;
                    cell.appendChild(badge);
                }
            }

            // Click handler → open details
            cell.addEventListener('click', () => openDayDetails(dateKey));

            grid.appendChild(cell);
        }

        // Trailing blanks
        const totalCells = startDayOfWeek + daysInMonth;
        const trailing   = (7 - (totalCells % 7)) % 7;
        for (let i = 0; i < trailing; i++) {
            const blank = document.createElement('div');
            blank.className = 'cal-day is-outside';
            grid.appendChild(blank);
        }

        if (typeof initIcons === 'function') initIcons();
    }

    // ------------------------------------------------------------
    // Open the details modal for a specific date
    // ------------------------------------------------------------
    window.openDayDetails = function (dateKey) {
        const dayIndex = buildDayIndex();
        const leaves   = dayIndex[dateKey] || [];

        document.getElementById('calModalDate').textContent = formatDateKey(dateKey);

        const body = document.getElementById('calModalBody');

        if (leaves.length === 0) {
            body.innerHTML = `
                <div class="text-center py-8">
                    <div class="w-12 h-12 rounded-full bg-[#F1FDF6] flex items-center justify-center mx-auto mb-3 text-[#0F5E3D]">
                        <i data-lucide="calendar-check" class="w-5 h-5"></i>
                    </div>
                    <p class="text-sm font-medium text-[#2C3E50]">No leaves on this day</p>
                    <p class="text-xs text-[#2C3E50]/50 mt-1">Everyone's in the office.</p>
                </div>
            `;
        } else {
            const approvedCount = leaves.filter(l => l.status === 'approved').length;
            const pendingCount  = leaves.filter(l => l.status === 'pending').length;

            // Summary strip
            let summary = `<div class="flex items-center gap-3 mb-3 pb-3 border-b border-[#E0E0E0]">`;
            summary += `<span class="text-xs text-[#2C3E50]/60"><strong class="text-[#2C3E50]">${leaves.length}</strong> total</span>`;
            if (approvedCount > 0) {
                summary += `<span class="text-xs text-[#0F5E3D]"><strong>${approvedCount}</strong> approved</span>`;
            }
            if (pendingCount > 0) {
                summary += `<span class="text-xs text-amber-600"><strong>${pendingCount}</strong> pending</span>`;
            }
            summary += `</div>`;

            // Sort: approved first, then pending; alpha within
            leaves.sort((a, b) => {
                if (a.status !== b.status) return a.status === 'approved' ? -1 : 1;
                return a.name.localeCompare(b.name);
            });

            let rows = '';
            leaves.forEach((l) => {
                const color = typeColors[l.type] || '#64748b';
                const initials = l.name
                    .split(/\s+/).filter(Boolean)
                    .map(w => w[0]).slice(0, 2).join('').toUpperCase();

                const statusClass = l.status === 'approved'
                    ? 'text-[#0F5E3D] bg-[#F1FDF6]'
                    : 'text-amber-700 bg-amber-50';
                const statusLabel = l.status === 'approved' ? 'Approved' : 'Pending';

                rows += `
                    <div class="flex items-start gap-3 p-3 rounded-lg hover:bg-[#F1FDF6]/40 transition mb-1">
                        <div class="w-9 h-9 rounded-full bg-[#0F5E3D] text-white flex items-center justify-center font-semibold text-xs shrink-0">
                            ${initials}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5 flex-wrap mb-0.5">
                                <p class="text-sm font-medium text-[#2C3E50] truncate">${l.name}</p>
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-semibold uppercase tracking-wide ${statusClass}">
                                    ${statusLabel}
                                </span>
                            </div>
                            <p class="text-[11px] text-[#2C3E50]/60 truncate">
                                ${l.position || '—'}${l.department ? ' · ' + l.department : ''}
                            </p>
                            <div class="flex items-center gap-2 mt-1 flex-wrap">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium"
                                      style="background: ${color}22; color: ${color}">
                                    <span class="w-1.5 h-1.5 rounded-full" style="background: ${color}"></span>
                                    ${l.type.charAt(0).toUpperCase() + l.type.slice(1)}
                                </span>
                                <span class="text-[10px] text-[#2C3E50]/50">
                                    ${l.days} day${l.days === 1 ? '' : 's'}
                                </span>
                            </div>
                        </div>
                    </div>
                `;
            });

            body.innerHTML = summary + rows;
        }

        // Show modal
        const modal = document.getElementById('leaveCalendarModal');
        const card  = document.getElementById('leaveCalendarCard');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        setTimeout(() => {
            card.classList.remove('scale-95');
            card.classList.add('scale-100');
        }, 10);

        if (typeof initIcons === 'function') initIcons();
    };

    // ------------------------------------------------------------
    // Close the details modal
    // ------------------------------------------------------------
    window.closeDayDetails = function () {
        const modal = document.getElementById('leaveCalendarModal');
        const card  = document.getElementById('leaveCalendarCard');
        card.classList.remove('scale-100');
        card.classList.add('scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }, 150);
    };

    // ------------------------------------------------------------
    // Click outside the card to close
    // ------------------------------------------------------------
    document.addEventListener('click', (e) => {
        const modal = document.getElementById('leaveCalendarModal');
        if (!modal || modal.classList.contains('hidden')) return;
        if (e.target === modal) closeDayDetails();
    });

    // ------------------------------------------------------------
    // Escape key closes the modal
    // ------------------------------------------------------------
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const modal = document.getElementById('leaveCalendarModal');
            if (modal && !modal.classList.contains('hidden')) closeDayDetails();
        }
    });

    // ------------------------------------------------------------
    // Public month navigation
    // ------------------------------------------------------------
    window.calendarPrevMonth = function () {
        viewMonth--;
        if (viewMonth < 0) { viewMonth = 11; viewYear--; }
        render();
    };

    window.calendarNextMonth = function () {
        viewMonth++;
        if (viewMonth > 11) { viewMonth = 0; viewYear++; }
        render();
    };

    document.addEventListener('DOMContentLoaded', render);
})();