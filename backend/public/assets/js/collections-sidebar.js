/**
 * Collections Sidebar
 * Fetches top collections and renders sidebar bubbles on collection pages.
 * Landing page uses SSR-rendered sidebar; this script only runs on collection pages.
 *
 * Built as DOM nodes rather than an innerHTML string: escapeHtml() escapes text
 * but not quotes, so it cannot safely guard the href/title attributes here.
 */
(function() {
    var container = document.getElementById('sidebarCollections');
    if (!container) return;

    var currentSlug = document.body.dataset.slug || '';

    function bubble(collection) {
        var count = parseInt(collection.entry_count, 10) || 0;
        var isActive = collection.slug === currentSlug;

        var link = document.createElement('a');
        link.className = 'sidebar-bubble' + (isActive ? ' active' : '');
        link.href = '/collection/' + encodeURIComponent(collection.slug);
        link.title = collection.name + ' \u00b7 ' + count + ' entries';
        if (isActive) link.setAttribute('aria-current', 'page');

        var avatar = document.createElement('img');
        avatar.className = 'sidebar-bubble-avatar';
        avatar.src = collection.avatar_url || '/assets/app-icon.webp';
        avatar.alt = '';
        avatar.width = 40;
        avatar.height = 40;
        avatar.loading = 'lazy';

        var name = document.createElement('span');
        name.className = 'sidebar-bubble-name';
        name.textContent = collection.name;

        var entries = document.createElement('span');
        entries.className = 'sidebar-bubble-count';
        entries.textContent = count;

        link.appendChild(avatar);
        link.appendChild(name);
        link.appendChild(entries);
        return link;
    }

    container.setAttribute('aria-busy', 'true');

    fetch('/api/collections/sidebar')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var collections = data.collections || [];
            if (collections.length === 0) return;

            var fragment = document.createDocumentFragment();
            collections.forEach(function(collection) {
                fragment.appendChild(bubble(collection));
            });

            container.textContent = '';
            container.appendChild(fragment);
        })
        .catch(function() {})
        .finally(function() { container.removeAttribute('aria-busy'); });
})();
