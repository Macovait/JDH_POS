<div class="welcome-banner rounded-xl p-6 mb-8">
    <div class="flex justify-between items-center flex-wrap gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-white mb-2" style="font-family: 'Poppins', sans-serif;">
                Welcome back, <?php echo htmlspecialchars($user_name); ?>! 👋
            </h1>
            <p class="text-text-secondary">Here's what's happening with your business today.</p>
        </div>
        <button id="refreshDataBtn"
            class="px-5 py-2.5 bg-amber-500 hover:bg-amber-600 text-slate-900 rounded-xl transition-all flex items-center gap-2 font-semibold shadow-lg hover:shadow-xl">
            <i class="fas fa-sync-alt"></i> Refresh Data
        </button>
    </div>
</div>