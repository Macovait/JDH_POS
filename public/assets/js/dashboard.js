// public/assets/js/dashboard.js
// Sidebar toggle
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const wrapper = document.getElementById('appWrapper');

    if (window.innerWidth <= 768) {
        sidebar.classList.toggle('mobile-open');
        overlay.classList.toggle('show');
    } else {
        sidebar.classList.toggle('collapsed');
        wrapper.classList.toggle('full-width');
    }
}

// Close sidebar on overlay click
document.getElementById('sidebarOverlay').addEventListener('click', function () {
    toggleSidebar();
});

// Handle window resize
window.addEventListener('resize', function () {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const wrapper = document.getElementById('appWrapper');

    if (window.innerWidth > 768) {
        sidebar.classList.remove('mobile-open');
        overlay.classList.remove('show');
    }
});

// Dropdown toggle
document.addEventListener('DOMContentLoaded', function () {
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');

    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const dropdownId = this.getAttribute('data-dropdown');
            const dropdownContent = document.getElementById(dropdownId);
            const icon = this.querySelector('.fa-chevron-down');

            // Close all other dropdowns
            dropdownToggles.forEach(otherToggle => {
                const otherId = otherToggle.getAttribute('data-dropdown');
                const otherContent = document.getElementById(otherId);
                const otherIcon = otherToggle.querySelector('.fa-chevron-down');

                if (otherId !== dropdownId && otherContent && !otherContent.classList.contains('hidden')) {
                    otherContent.classList.add('hidden');
                    if (otherIcon) otherIcon.style.transform = 'rotate(0deg)';
                }
            });

            // Toggle current dropdown
            if (dropdownContent) {
                dropdownContent.classList.toggle('hidden');
                if (icon) {
                    icon.style.transform = dropdownContent.classList.contains('hidden') ? 'rotate(0deg)' : 'rotate(180deg)';
                }
            }
        });
    });

    // Keep dropdown open if current page is in that group
    const currentPage = window.location.pathname.split('/').pop();

    dropdownToggles.forEach(toggle => {
        const dropdownId = toggle.getAttribute('data-dropdown');
        const dropdownContent = document.getElementById(dropdownId);
        if (dropdownContent) {
            const links = dropdownContent.querySelectorAll('a');
            for (let i = 0; i < links.length; i++) {
                const href = links[i].getAttribute('href');
                if (href === currentPage || (currentPage === 'index.php' && href === 'index.php')) {
                    dropdownContent.classList.remove('hidden');
                    const icon = toggle.querySelector('.fa-chevron-down');
                    if (icon) icon.style.transform = 'rotate(180deg)';
                    break;
                }
            }
        }
    });
});

// Close dropdowns when clicking outside
document.addEventListener('click', function (e) {
    if (!e.target.closest('.dropdown-toggle') && !e.target.closest('.dropdown-content')) {
        const openDropdowns = document.querySelectorAll('.dropdown-content:not(.hidden)');
        const openToggles = document.querySelectorAll('.dropdown-toggle');

        openDropdowns.forEach(dropdown => {
            dropdown.classList.add('hidden');
        });

        openToggles.forEach(toggle => {
            const icon = toggle.querySelector('.fa-chevron-down');
            if (icon) icon.style.transform = 'rotate(0deg)';
        });
    }
});

// Refresh data (if refresh button exists)
const refreshBtn = document.getElementById('refreshDataBtn');
if (refreshBtn) {
    refreshBtn.addEventListener('click', function () {
        const $btn = $(this);
        $btn.html('<i class="fas fa-spinner fa-spin mr-2"></i> Refreshing...');
        $.ajax({
            url: '../ajax/dashboard_filters.php',
            method: 'GET',
            data: { action: 'refresh_stats' },
            dataType: 'json',
            success: function (response) {
                if (response && response.success) {
                    location.reload();
                } else {
                    alert(response && response.message ? response.message : 'Failed to refresh data');
                    $btn.html('<i class="fas fa-sync-alt mr-2"></i> Refresh Data');
                }
            },
            error: function () {
                alert('Connection error. Please try again.');
                $btn.html('<i class="fas fa-sync-alt mr-2"></i> Refresh Data');
            }
        });
    });
}