<!-- Overlay -->
<div 
    id="logoutModal" 
    class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm transition-opacity duration-200 p-4"
>
    <!-- Modal Card -->
    <div 
        class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6 transform transition-all duration-200 scale-95"
        id="logoutModalCard"
    >
        <div class="flex items-center justify-center w-12 h-12 rounded-full bg-red-50 mx-auto mb-4">
            <i data-lucide="log-out" class="w-6 h-6 text-red-600"></i>
        </div>

        <h3 class="text-lg font-bold text-[#2C3E50] text-center mb-2">
            Confirm Logout
        </h3>

        <p class="text-sm text-[#2C3E50]/70 text-center mb-6">
            Are you sure you want to log out? You'll need to sign in again to access your dashboard.
        </p>

        <div class="flex flex-col sm:flex-row gap-3">
            <button 
                type="button"
                onclick="closeLogoutModal()"
                class="flex-1 border border-[#E0E0E0] text-[#2C3E50] hover:bg-gray-50 
                       font-medium py-2.5 rounded-lg transition text-sm"
            >
                Cancel
            </button>
            <a 
                href="/logout"
                class="flex-1 bg-red-600 hover:bg-red-700 text-white font-medium 
                       py-2.5 rounded-lg transition text-sm text-center"
            >
                Yes, Logout
            </a>
        </div>
    </div>
</div>

<script>
    function openLogoutModal() {
        document.body.style.overflow = 'hidden';
        const modal = document.getElementById('logoutModal');
        const card = document.getElementById('logoutModalCard');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        requestAnimationFrame(() => {
            card.classList.remove('scale-95');
            card.classList.add('scale-100');
        });
    }

    function closeLogoutModal() {
        document.body.style.overflow = '';
        const modal = document.getElementById('logoutModal');
        const card = document.getElementById('logoutModalCard');
        card.classList.remove('scale-100');
        card.classList.add('scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }, 150);
    }

    document.getElementById('logoutModal')?.addEventListener('click', function (e) {
        if (e.target === this) closeLogoutModal();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeLogoutModal();
    });
</script>