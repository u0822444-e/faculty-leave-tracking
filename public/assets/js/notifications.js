// ============================================
// Real-time notifications (polling)
// ============================================

let notifPollTimer = null;
let notifDropdownOpen = false;

// Fetch notifications from server
async function fetchNotifications() {
    try {
        const res = await fetch('/api/notifications?action=list');
        const data = await res.json();

        if (!data.success) return;

        updateNotifBadge(data.unread);
        renderNotifList(data.items);
    } catch (err) {
        console.error('Notification fetch failed:', err);
    }
}

// Update the bell badge
function updateNotifBadge(count) {
    const badge = document.getElementById('notifBadge');
    if (!badge) return;

    if (count > 0) {
        badge.textContent = count > 99 ? '99+' : count;
        badge.classList.remove('hidden');
        badge.classList.add('flex');
    } else {
        badge.classList.add('hidden');
        badge.classList.remove('flex');
    }
}

// Render the dropdown list
function renderNotifList(items) {
    const list = document.getElementById('notifList');
    const summary = document.getElementById('notifSummary');
    if (!list) return;

    if (!items || items.length === 0) {
        list.innerHTML = `
            <div class="p-8 text-center">
                <div class="w-10 h-10 rounded-full bg-[#F1FDF6] flex items-center justify-center mx-auto mb-2 text-[#0F5E3D]">
                    <i data-lucide="bell-off" class="w-4 h-4"></i>
                </div>
                <p class="text-xs text-[#2C3E50]/50">No notifications yet</p>
            </div>
        `;
        if (summary) summary.textContent = 'You are all caught up';
        if (typeof initIcons === 'function') initIcons();
        return;
    }

    const unreadCount = items.filter(i => !i.is_read).length;
    if (summary) {
        summary.textContent = unreadCount > 0
            ? `${unreadCount} unread`
            : 'You are all caught up';
    }

    const iconMap = {
        create_user:  'user-plus',
        update_user:  'pencil',
        archive_user: 'archive',
        send_reset:   'mail',
        login:        'log-in',
        logout:       'log-out',
        default:      'bell',
    };

    const colorMap = {
        create_user:  'text-[#0F5E3D] bg-[#F1FDF6]',
        update_user:  'text-blue-600 bg-blue-50',
        archive_user: 'text-amber-600 bg-amber-50',
        send_reset:   'text-purple-600 bg-purple-50',
        login:        'text-emerald-600 bg-emerald-50',
        logout:       'text-slate-600 bg-slate-100',
        default:      'text-[#2C3E50] bg-[#F1FDF6]',
    };

    list.innerHTML = items.map(n => {
        const icon = iconMap[n.type] || iconMap.default;
        const color = colorMap[n.type] || colorMap.default;
        const unreadClass = n.is_read ? '' : 'bg-[#F1FDF6]/40';
        const dot = n.is_read ? '' : '<span class="w-1.5 h-1.5 rounded-full bg-[#0F5E3D] shrink-0"></span>';

        return `
            <div class="notif-item p-3 hover:bg-[#F1FDF6]/60 transition cursor-pointer ${unreadClass}"
                 onclick="openNotification(${n.id}, '${n.link || ''}')">
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-full ${color} flex items-center justify-center shrink-0">
                        <i data-lucide="${icon}" class="w-4 h-4"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-0.5">
                            <p class="text-xs font-semibold text-[#2C3E50] truncate">${escapeHtml(n.title)}</p>
                            ${dot}
                        </div>
                        ${n.message ? `<p class="text-[11px] text-[#2C3E50]/60 line-clamp-2">${escapeHtml(n.message)}</p>` : ''}
                        <p class="text-[10px] text-[#2C3E50]/40 mt-1">${n.time_ago}</p>
                    </div>
                </div>
            </div>
        `;
    }).join('');

    if (typeof initIcons === 'function') initIcons();
}

// Escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}

// Toggle dropdown
function toggleNotifications() {
    const dd = document.getElementById('notifDropdown');
    if (!dd) return;

    notifDropdownOpen = !notifDropdownOpen;
    dd.classList.toggle('hidden', !notifDropdownOpen);

    if (notifDropdownOpen) {
        fetchNotifications();
    }
}

// Close dropdown on outside click
document.addEventListener('click', function (e) {
    const wrap = document.getElementById('notificationWrap');
    const dd = document.getElementById('notifDropdown');
    if (!wrap || !dd) return;
    if (!wrap.contains(e.target)) {
        dd.classList.add('hidden');
        notifDropdownOpen = false;
    }
});

// Open a notification (mark read + navigate)
async function openNotification(id, link) {
    try {
        await fetch('/api/notifications', {
            method: 'POST',
            body: new URLSearchParams({ action: 'read', id }),
        });
        fetchNotifications();
    } catch (err) {
        console.error(err);
    }

    if (link) {
        setTimeout(() => window.location.href = link, 200);
    }
}

// Mark all as read
async function markAllRead() {
    try {
        await fetch('/api/notifications', {
            method: 'POST',
            body: new URLSearchParams({ action: 'read_all' }),
        });
        fetchNotifications();
    } catch (err) {
        console.error(err);
    }
}

// ============================================
// Start polling
// ============================================
document.addEventListener('DOMContentLoaded', () => {
    // Initial fetch
    fetchNotifications();

    // Poll every 10 seconds
    notifPollTimer = setInterval(fetchNotifications, 10000);
});