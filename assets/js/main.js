/**
 * ThreadFlow - Client-side Utilities
 * 
 * Vanilla JavaScript for responsive navigation drawer and UI interactions.
 * No external runtime dependencies.
 */

document.addEventListener('DOMContentLoaded', function () {
    // 1. Mobile Sidebar Toggle
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const sidebar = document.querySelector('.app-sidebar');
    const backdrop = document.querySelector('.sidebar-backdrop');

    if (sidebarToggle && sidebar && backdrop) {
        function openSidebar() {
            sidebar.classList.add('open');
            backdrop.classList.add('active');
            sidebarToggle.setAttribute('aria-expanded', 'true');
        }

        function closeSidebar() {
            sidebar.classList.remove('open');
            backdrop.classList.remove('active');
            sidebarToggle.setAttribute('aria-expanded', 'false');
        }

        sidebarToggle.addEventListener('click', function () {
            const isOpen = sidebar.classList.contains('open');
            if (isOpen) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });

        backdrop.addEventListener('click', closeSidebar);

        // Close sidebar on Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && sidebar.classList.contains('open')) {
                closeSidebar();
            }
        });
    }

    // 2. Dismissible Alerts
    const alertCloseButtons = document.querySelectorAll('.alert-close');
    alertCloseButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const alert = this.closest('.alert');
            if (alert) {
                alert.style.transition = 'opacity 0.2s ease, max-height 0.2s ease';
                alert.style.opacity = '0';
                alert.style.maxHeight = '0';
                setTimeout(function () {
                    alert.remove();
                }, 200);
            }
        });
    });

    // 3. Destructive Action Confirmation
    const confirmActions = document.querySelectorAll('[data-confirm]');
    confirmActions.forEach(function (element) {
        element.addEventListener('click', function (e) {
            const message = this.getAttribute('data-confirm') || 'Are you sure you want to proceed?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
});
