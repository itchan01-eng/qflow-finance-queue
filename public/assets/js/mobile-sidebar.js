document.addEventListener('DOMContentLoaded', function () {
    const menuToggle = document.getElementById('mobileMenuToggle');
    const sidebar = document.getElementById('mobileSidebar');
    const closeButton = document.getElementById('mobileSidebarClose');
    const overlay = document.getElementById('mobileSidebarOverlay');

    if (!menuToggle || !sidebar || !closeButton || !overlay) {
        console.error('QueueFlow: Mobile sidebar elements are missing.');
        return;
    }

    function openSidebar() {
        sidebar.classList.add('is-open');

        overlay.hidden = false;

        sidebar.removeAttribute('inert');
        sidebar.setAttribute('aria-hidden', 'false');

        menuToggle.setAttribute('aria-expanded', 'true');

        document.body.classList.add('sidebar-open');

        closeButton.focus();
    }

    function closeSidebar() {
        sidebar.classList.remove('is-open');

        overlay.hidden = true;

        sidebar.setAttribute('inert', '');
        sidebar.setAttribute('aria-hidden', 'true');

        menuToggle.setAttribute('aria-expanded', 'false');

        document.body.classList.remove('sidebar-open');

        menuToggle.focus();
    }

    menuToggle.addEventListener('click', function () {
        if (sidebar.classList.contains('is-open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    });

    closeButton.addEventListener('click', closeSidebar);
    overlay.addEventListener('click', closeSidebar);

    document.addEventListener('keydown', function (event) {
        if (
            event.key === 'Escape' &&
            sidebar.classList.contains('is-open')
        ) {
            closeSidebar();
        }
    });

    sidebar.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', closeSidebar);
    });

    // Initial state
    sidebar.classList.remove('is-open');
    sidebar.setAttribute('inert', '');
    sidebar.setAttribute('aria-hidden', 'true');
    overlay.hidden = true;
    menuToggle.setAttribute('aria-expanded', 'false');
});