
</div><!-- /.main-area -->
</div><!-- /.app-shell -->

<script>
document.addEventListener('click', function (event) {
    const sidebar = document.querySelector('.sidebar');
    const toggle = document.querySelector('.mobile-menu-toggle');

    if (
        document.body.classList.contains('sidebar-open') &&
        sidebar &&
        toggle &&
        !sidebar.contains(event.target) &&
        !toggle.contains(event.target)
    ) {
        document.body.classList.remove('sidebar-open');
    }
});
</script>
</body>
</html>
