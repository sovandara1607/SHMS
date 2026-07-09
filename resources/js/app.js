import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();

/**
 * Global "app is navigating" loading overlay. This is a full-page-reload
 * Blade app (no SPA router), so the only way to avoid a frozen-feeling UI
 * during the server round-trip is to show a spinner the instant a link is
 * clicked or a form is submitted, right up until the browser replaces the
 * page. A safety timeout guards against a stuck overlay if a request hangs.
 */
(function () {
    const overlay = document.getElementById('global-loading-overlay');
    if (!overlay) return;

    let hideTimer = null;

    function show() {
        overlay.classList.remove('hidden');
        overlay.classList.add('flex');
        clearTimeout(hideTimer);
        hideTimer = setTimeout(hide, 20000);
    }

    function hide() {
        overlay.classList.add('hidden');
        overlay.classList.remove('flex');
        clearTimeout(hideTimer);
    }

    window.loadingOverlay = { show, hide };

    document.addEventListener('submit', (event) => {
        if (event.defaultPrevented) return; // e.g. a cancelled confirm() dialog
        show();
    });

    document.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== 0) return;
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return; // opening in new tab/window

        const link = event.target.closest('a[href]');
        if (!link) return;

        const href = link.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;
        if (link.target && link.target !== '_self') return;
        if (link.hasAttribute('download')) return;
        if (link.origin !== window.location.origin) return;

        show();
    });

    // Back/forward navigation can restore the page from bfcache with the
    // overlay still visible from the click that navigated away.
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) hide();
    });
})();
