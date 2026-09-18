/**
 * Collections Sidebar
 * The rail itself is server-rendered on the landing page; all this does is
 * drive the disclosure toggle that collapses it on narrow viewports.
 */
(function() {
    var toggle = document.getElementById('sidebarToggle');
    if (!toggle) return;

    var sidebar = toggle.closest('.collections-sidebar');
    if (!sidebar) return;

    toggle.addEventListener('click', function() {
        var expanded = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        sidebar.classList.toggle('expanded', !expanded);
    });
})();
