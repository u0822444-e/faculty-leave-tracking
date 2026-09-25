// ============================================
// Settings page — logic
// ============================================

// Tab switcher
function switchSettingsTab(tab) {
    // Update buttons
    document.querySelectorAll('.settings-tab').forEach(btn => {
        btn.className = 'settings-tab whitespace-nowrap px-5 py-3 text-sm font-medium text-[#2C3E50]/60 border-b-2 border-transparent hover:text-[#2C3E50] transition';
    });

    const activeBtn = document.getElementById('tab' + capitalize(tab));
    if (activeBtn) {
        activeBtn.className = 'settings-tab whitespace-nowrap px-5 py-3 text-sm font-medium text-[#0F5E3D] border-b-2 border-[#0F5E3D] transition';
    }

    // Show/hide panels
    document.querySelectorAll('.settings-panel').forEach(p => p.classList.add('hidden'));
    const panel = document.getElementById('panel' + capitalize(tab));
    if (panel) panel.classList.remove('hidden');

    if (typeof initIcons === 'function') initIcons();
}

function capitalize(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}

// Form submissions
document.addEventListener('DOMContentLoaded', () => {
    if (typeof initIcons === 'function') initIcons();

    document.querySelectorAll('.settings-form').forEach(form => {
        form.addEventListener('submit', async function (e) {
            e.preventDefault();

            const category = this.dataset.category;
            const fd = new FormData(this);
            fd.append('action', 'save');
            fd.append('category', category);

            showLoading('Saving settings...', 'Applying your changes.');

            try {
                const res = await fetch('/api/settings', { method: 'POST', body: fd });
                const data = await res.json();

                await new Promise(r => setTimeout(r, 500));
                hideLoading();

                if (data.success) {
                    showModal('successModal', 'successCard');
                    if (typeof initIcons === 'function') initIcons();
                } else if (data.error) {
                    showError('Could not save settings', data.error);
                }
            } catch (err) {
                hideLoading();
                console.error(err);
                showError('Connection error', 'Unable to reach the server.');
            }
        });
    });
});