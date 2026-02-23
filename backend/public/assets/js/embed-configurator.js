/**
 * Embed Configurator
 *
 * Manages the collapsible embed section on the profile settings page.
 * Generates live-updating iframe embed code and an optional auto-resize script.
 */

(function () {
    'use strict';

    const toggle = document.getElementById('embedSectionToggle');
    const content = document.getElementById('embedSectionContent');

    if (!toggle || !content) return;

    // ── Collapse toggle ──

    toggle.addEventListener('click', () => {
        const expanded = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', String(!expanded));
        content.hidden = expanded;

        if (!expanded) {
            initConfigurator();
        }
    });

    let initialized = false;

    function initConfigurator() {
        if (initialized) return;
        initialized = true;

        const themeRadios = document.querySelectorAll('input[name="embed-theme"]');
        const showHeaderCheckbox = document.getElementById('embedShowHeader');
        const showSearchCheckbox = document.getElementById('embedShowSearch');
        const autoResizeCheckbox = document.getElementById('embedAutoResize');
        const previewIframe = document.getElementById('embedPreviewIframe');
        const codeOutput = document.getElementById('embedCodeOutput');
        const copyBtn = document.getElementById('embedCopyBtn');

        function getNickname() {
            const el = document.getElementById('identity-nickname-text');
            if (el && el.textContent.trim()) return el.textContent.trim();
            const input = document.getElementById('nickname');
            if (input && input.value.trim()) return input.value.trim();
            return '';
        }

        function getBaseUrl() {
            return window.location.origin;
        }

        function buildEmbedUrl() {
            const nickname = getNickname();
            if (!nickname) return '';

            const base = `${getBaseUrl()}/@${encodeURIComponent(nickname)}/embed`;
            const params = new URLSearchParams();

            const theme = document.querySelector('input[name="embed-theme"]:checked')?.value || 'dark';
            params.set('theme', theme);

            if (showHeaderCheckbox?.checked) params.set('header', '1');
            if (showSearchCheckbox?.checked) params.set('search', '1');

            return `${base}?${params.toString()}`;
        }

        function generateIframeTag() {
            const url = buildEmbedUrl();
            if (!url) return '';
            return `<iframe src="${url}" style="border:none; width:100%; min-width:320px;" loading="lazy" allow="web-share; clipboard-write"></iframe>`;
        }

        function generateResizeScript() {
            return `<script>\nwindow.addEventListener("message", function(e) {\n  if (e.data && e.data.type === "trail-embed-resize") {\n    var frames = document.querySelectorAll('iframe[src*="/embed"]');\n    frames.forEach(function(f) { if (e.source === f.contentWindow) f.style.height = e.data.height + "px"; });\n  }\n});\n<\/script>`;
        }

        function generateEmbedCode() {
            const iframe = generateIframeTag();
            if (!iframe) return '';
            if (autoResizeCheckbox?.checked) {
                return iframe + '\n\n' + generateResizeScript();
            }
            return iframe;
        }

        function escapeHtml(str) {
            return str
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function update() {
            const url = buildEmbedUrl();
            if (!url) return;

            if (previewIframe) previewIframe.src = url;
            if (codeOutput) codeOutput.innerHTML = escapeHtml(generateEmbedCode());
        }

        function updateCodeOnly() {
            if (codeOutput) codeOutput.innerHTML = escapeHtml(generateEmbedCode());
        }

        // Listen to auto-resize messages from the preview iframe (validate origin)
        window.addEventListener('message', (e) => {
            if (e.origin !== getBaseUrl()) return;
            if (e.data && e.data.type === 'trail-embed-resize' && previewIframe) {
                if (e.source === previewIframe.contentWindow) {
                    const h = parseInt(e.data.height, 10);
                    if (h > 0 && h < 50000) previewIframe.style.height = h + 'px';
                }
            }
        });

        themeRadios.forEach((r) => r.addEventListener('change', update));
        if (showHeaderCheckbox) showHeaderCheckbox.addEventListener('change', update);
        if (showSearchCheckbox) showSearchCheckbox.addEventListener('change', update);
        if (autoResizeCheckbox) autoResizeCheckbox.addEventListener('change', updateCodeOnly);

        if (copyBtn) {
            copyBtn.addEventListener('click', () => {
                copyToClipboard(generateEmbedCode(), 'Embed code copied!');
            });
        }

        // Wait briefly for the nickname to be populated by profile-manager.js
        const waitForNickname = setInterval(() => {
            if (getNickname()) {
                clearInterval(waitForNickname);
                update();
            }
        }, 200);
        setTimeout(() => clearInterval(waitForNickname), 5000);
    }

    function copyToClipboard(text, successMessage) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(() => {
                if (typeof showSnackbar === 'function') showSnackbar(successMessage, 'success');
            }).catch(() => {
                fallbackCopy(text, successMessage);
            });
        } else {
            fallbackCopy(text, successMessage);
        }
    }

    function fallbackCopy(text, successMessage) {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.cssText = 'position:fixed;opacity:0';
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand('copy');
            if (typeof showSnackbar === 'function') showSnackbar(successMessage, 'success');
        } catch (_) {
            if (typeof showSnackbar === 'function') showSnackbar('Failed to copy', 'error');
        }
        document.body.removeChild(ta);
    }
})();
