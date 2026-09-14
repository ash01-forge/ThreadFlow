/**
 * ThreadFlow - Client-Side Modern UI Interactions
 * 
 * Vanilla JavaScript for responsive drawer, accessible keyboard controls,
 * smooth dismissible alerts, and confirmation dialogs.
 * 
 * Strictly zero external dependencies.
 */

document.addEventListener('DOMContentLoaded', function () {
    // 1. Accessible Mobile Sidebar Drawer
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const sidebar = document.querySelector('.app-sidebar');
    const backdrop = document.querySelector('.sidebar-backdrop');

    if (sidebarToggle && sidebar && backdrop) {
        function openSidebar() {
            sidebar.classList.add('open');
            backdrop.classList.add('active');
            sidebarToggle.setAttribute('aria-expanded', 'true');
            // Prevent body scrolling when mobile drawer is active
            document.body.style.overflow = 'hidden';
        }

        function closeSidebar() {
            sidebar.classList.remove('open');
            backdrop.classList.remove('active');
            sidebarToggle.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
        }

        sidebarToggle.addEventListener('click', function (e) {
            e.stopPropagation();
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
                sidebarToggle.focus();
            }
        });

        // Close mobile drawer automatically if resized beyond tablet breakpoint
        window.addEventListener('resize', function () {
            if (window.innerWidth > 768 && sidebar.classList.contains('open')) {
                closeSidebar();
            }
        });
    }

    // 2. Dismissible Alerts with Smooth Animation
    const alertCloseButtons = document.querySelectorAll('.alert-close');
    alertCloseButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const alert = this.closest('.alert');
            if (alert) {
                alert.style.transition = 'opacity 0.2s ease, max-height 0.25s ease, margin 0.2s ease, padding 0.2s ease';
                alert.style.overflow = 'hidden';
                alert.style.opacity = '0';
                alert.style.maxHeight = '0';
                alert.style.paddingTop = '0';
                alert.style.paddingBottom = '0';
                alert.style.marginBottom = '0';
                setTimeout(function () {
                    alert.remove();
                }, 250);
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

    // 4. Responsive Table Horizontal Scroll Hint
    const responsiveTables = document.querySelectorAll('.table-responsive');
    responsiveTables.forEach(function (container) {
        const table = container.querySelector('table');
        if (table) {
            function checkScroll() {
                if (table.offsetWidth > container.clientWidth) {
                    container.classList.add('has-horizontal-scroll');
                } else {
                    container.classList.remove('has-horizontal-scroll');
                }
            }
            checkScroll();
            window.addEventListener('resize', checkScroll);
        }
    });
});
