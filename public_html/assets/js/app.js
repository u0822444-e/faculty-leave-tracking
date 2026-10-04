// ============================================
// Shared helpers — used by all admin pages
// ============================================

// Initialize Lucide icons
function initIcons() {
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

// Sidebar toggle
function openSidebar() {
    document.getElementById('sidebar')?.classList.remove('-translate-x-full');
    document.getElementById('sidebarOverlay')?.classList.remove('hidden');
}
function closeSidebar() {
    document.getElementById('sidebar')?.classList.add('-translate-x-full');
    document.getElementById('sidebarOverlay')?.classList.add('hidden');
}

// Generic modal helpers
function showModal(modalId, cardId) {
    document.body.style.overflow = 'hidden';
    const modal = document.getElementById(modalId);
    const card = document.getElementById(cardId);
    if (!modal || !card) return;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    requestAnimationFrame(() => {
        card.classList.remove('scale-95');
        card.classList.add('scale-100');
    });
}
function hideModal(modalId, cardId) {
    document.body.style.overflow = '';
    const modal = document.getElementById(modalId);
    const card = document.getElementById(cardId);
    if (!modal || !card) return;
    card.classList.remove('scale-100');
    card.classList.add('scale-95');
    setTimeout(() => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }, 150);
}

// ============================================
// Error modal
// ============================================
function showError(title, message) {
    const titleEl = document.getElementById('errorTitle');
    const msgEl = document.getElementById('errorMessage');

    if (titleEl) titleEl.textContent = title || 'Something went wrong';
    if (msgEl) msgEl.textContent = message || 'An unexpected error occurred. Please try again.';

    showModal('errorModal', 'errorCard');
    if (typeof initIcons === 'function') initIcons();
}

function closeErrorModal() {
    hideModal('errorModal', 'errorCard');
}

// Highlight an invalid field
function markInvalid(elementId) {
    const el = document.getElementById(elementId);
    if (!el) return;
    el.classList.add('field-invalid');
    el.focus();
    setTimeout(() => el.classList.remove('field-invalid'), 2000);
}

// Close all modals on Escape
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        if (typeof closeAddUserModal === 'function') closeAddUserModal();
        if (typeof closeEditUserModal === 'function') closeEditUserModal();
        if (typeof closeArchiveUserModal === 'function') closeArchiveUserModal();
        if (typeof closeSendResetModal === 'function') closeSendResetModal();
        if (typeof closeSuccessModal === 'function') closeSuccessModal();
        if (typeof closeEditSuccessModal === 'function') closeEditSuccessModal();
        if (typeof closeResetSentModal === 'function') closeResetSentModal();
        if (typeof closeErrorModal === 'function') closeErrorModal();
        if (typeof closeLogoutModal === 'function') closeLogoutModal();
    }
});

// ============================================
// Loading overlay
// ============================================
function showLoading(title, message) {
    const overlay = document.getElementById('loadingOverlay');
    if (!overlay) return;

    const titleEl = document.getElementById('loadingTitle');
    const msgEl = document.getElementById('loadingMessage');

    if (titleEl && title) titleEl.textContent = title;
    if (msgEl && message) msgEl.textContent = message;

    overlay.classList.remove('hidden');
    overlay.classList.add('flex');
    document.body.style.overflow = 'hidden';
}

function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (!overlay) return;
    overlay.classList.add('hidden');
    overlay.classList.remove('flex');
    document.body.style.overflow = '';

    // Reset text so the next call starts clean
    const titleEl = document.getElementById('loadingTitle');
    const msgEl = document.getElementById('loadingMessage');
    if (titleEl) titleEl.textContent = 'Loading...';
    if (msgEl) msgEl.textContent = 'Please wait...';
}