import Alpine from 'alpinejs';

window.Alpine = Alpine;

// Collapsible sidebar category sections (Overview, Patient Care, ...). Open
// state persists per section so a collapsed category stays collapsed across
// full page loads, matching the sidebarOpen persistence in layouts/app.blade.php.
Alpine.data('sidebarSection', (key) => ({
    open: JSON.parse(localStorage.getItem(`sh_sidebar_section_${key}`) ?? 'true'),
    toggle() {
        this.open = !this.open;
        localStorage.setItem(`sh_sidebar_section_${key}`, JSON.stringify(this.open));
    },
}));

Alpine.start();

/**
 * Slim top progress bar shared by both loading affordances below. Every
 * page in this app has a different shape (stat cards, card grids, tabs,
 * plain forms...), so a "fake content" skeleton inevitably mismatches
 * whatever the real page turns out to be — this can't, since it doesn't
 * pretend to be page content at all.
 */
const topProgress = (function () {
    const bar = document.getElementById('top-progress-bar');
    let hideTimer = null;

    function start() {
        if (!bar) return;
        clearTimeout(hideTimer);
        bar.style.transition = 'none';
        bar.style.width = '0%';
        bar.style.opacity = '1';
        void bar.offsetWidth; // force reflow so the width transition below actually animates from 0
        bar.style.transition = '';
        bar.style.width = '80%';
    }

    function finish() {
        if (!bar) return;
        bar.style.width = '100%';
        hideTimer = setTimeout(() => {
            bar.style.opacity = '0';
        }, 150);
    }

    return { start, finish };
})();

/**
 * Full-page loading overlay, shown only for form submits (which still cause
 * a real full-page reload via POST -> redirect -> GET). Link navigation is
 * handled by the partial-page-navigation block below instead, which never
 * reloads the sidebar/header, so it doesn't use this overlay.
 */
(function () {
    const overlay = document.getElementById('global-loading-overlay');
    const label = document.getElementById('global-loading-overlay-text');
    if (!overlay) return;

    let hideTimer = null;

    // Labels the trigger's own text ("Regenerate", "Sign In", "Save")
    // rather than a fixed "Loading…" for every action, so what the overlay
    // says always matches what the user just asked the app to do.
    function labelFor(el) {
        const text = (el && el.textContent || '').trim().replace(/\s+/g, ' ');
        return text ? `${text}…` : 'Loading…';
    }

    function show(text) {
        if (label) label.textContent = text || 'Loading…';
        topProgress.start();
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
        const submitter = event.submitter || event.target.querySelector('button[type="submit"], input[type="submit"]');
        show(labelFor(submitter));
    });

    // Back/forward navigation can restore the page from bfcache with the
    // overlay still visible from the click that navigated away.
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) hide();
    });
})();

/**
 * Partial page navigation. This is otherwise a full-page-reload Blade app
 * with no SPA router, but every page shares the exact same <aside>/<header>
 * chrome (see layouts/app.blade.php) — reloading and remounting them on
 * every click is pure waste and feels heavier than it needs to. This
 * fetches the destination page and swaps only <main>'s content, leaving the
 * sidebar/header DOM (and its Alpine state, e.g. sidebarOpen) untouched.
 *
 * Falls back to a real navigation whenever it can't safely do a partial
 * swap: non-2xx responses, a network error, or a fetched page that isn't
 * the app shell (e.g. redirected to /login, which uses a different layout
 * with no <aside>/<main>).
 */
(function () {
    const main = document.querySelector('main');
    if (!main) return; // e.g. the login page has no <main> at all

    let navToken = 0;
    let scrollSaveScheduled = false;

    function isNavigableLink(link) {
        if (!link) return false;
        const href = link.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('javascript:')) return false;
        if (link.target && link.target !== '_self') return false;
        if (link.hasAttribute('download')) return false;
        if (link.origin !== window.location.origin) return false;
        return true;
    }

    // Mirrors the active/inactive classes from components/nav-link.blade.php
    // so the sidebar highlight follows the page even though the sidebar
    // itself never re-renders.
    function updateActiveNav(path) {
        document.querySelectorAll('aside nav a[href]').forEach((a) => {
            const linkPath = a.getAttribute('href');
            const active = linkPath !== '/' && (path === linkPath || path.startsWith(`${linkPath}/`) || path.startsWith(`${linkPath}?`));
            a.classList.toggle('bg-blue-600', active);
            a.classList.toggle('text-white', active);
            a.classList.toggle('text-slate-600', !active);
            a.classList.toggle('hover:bg-slate-50', !active);
            const icon = a.querySelector('svg');
            if (icon) {
                icon.classList.toggle('text-white', active);
                icon.classList.toggle('text-slate-400', !active);
            }
        });
    }

    async function navigateTo(url, { pushState = true, restoreScroll = 0 } = {}) {
        const token = ++navToken;
        // Current content stays on screen for the whole fetch — only the
        // progress bar indicates something's happening. Swapping in a fake
        // skeleton first (even briefly, on a fast local request) is its own
        // flicker; not swapping anything at all until real content is ready
        // has none.
        topProgress.start();

        let response;
        try {
            response = await fetch(url, { credentials: 'same-origin' });
        } catch (err) {
            window.location.href = url;
            return;
        }
        if (token !== navToken) return; // a newer navigation superseded this one

        if (!response.ok) {
            window.location.href = response.url || url;
            return;
        }

        const html = await response.text();
        if (token !== navToken) return;

        const doc = new DOMParser().parseFromString(html, 'text/html');
        const newAside = doc.querySelector('aside');
        const newMain = doc.querySelector('main');
        if (!newAside || !newMain) {
            // Not the app shell (e.g. session expired -> redirected to
            // /login), which needs a real navigation to render correctly.
            window.location.href = response.url || url;
            return;
        }

        // Same-origin, session-authenticated HTML from our own Laravel
        // backend — not third-party/user-controlled content, and the
        // browser would render this exact markup via a normal navigation
        // anyway. Matches the existing modal-loading pattern elsewhere in
        // this codebase (e.g. openModal() in lab/orders.blade.php).
        main.innerHTML = newMain.innerHTML;
        window.Alpine.initTree(main);
        topProgress.finish();

        // Replay the page fade-in — the CSS animation only fires once per
        // element lifetime, and this <main> persists across every partial
        // navigation (that's the whole point), so swapping its content
        // alone doesn't retrigger it without this nudge.
        main.classList.remove('animate-page-fade-in');
        void main.offsetWidth; // force reflow
        main.classList.add('animate-page-fade-in');

        const finalUrl = response.url || url;
        updateActiveNav(new URL(finalUrl, window.location.origin).pathname);

        if (pushState) history.pushState({ partial: true, scrollTop: 0 }, '', finalUrl);
        main.scrollTop = restoreScroll;
    }

    document.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== 0) return;
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        const link = event.target.closest('a[href]');
        if (!isNavigableLink(link)) return;

        event.preventDefault();
        navigateTo(link.getAttribute('href'));
    });

    window.addEventListener('popstate', (event) => {
        navigateTo(window.location.href, { pushState: false, restoreScroll: event.state?.scrollTop ?? 0 });
    });

    // Remember scroll position (throttled to one write per frame) so
    // back/forward can restore it instead of always landing at the top.
    main.addEventListener('scroll', () => {
        if (scrollSaveScheduled) return;
        scrollSaveScheduled = true;
        requestAnimationFrame(() => {
            history.replaceState({ ...history.state, scrollTop: main.scrollTop }, '', window.location.href);
            scrollSaveScheduled = false;
        });
    }, { passive: true });
})();

/**
 * File downloads (a[download]) never navigate the page, so partial
 * navigation above intentionally ignores them — but the user still gets
 * zero feedback with a plain browser-native download. This fetches the
 * file itself so we can show real "downloading" -> "downloaded" states tied
 * to the response actually finishing, instead of guessing when a native
 * download is done.
 */
(function () {
    const toast = document.getElementById('download-toast');
    if (!toast) return;

    const text = document.getElementById('download-toast-text');
    const spinner = document.getElementById('download-toast-spinner');
    const check = document.getElementById('download-toast-check');
    const errorIcon = document.getElementById('download-toast-error');

    let hideTimer = null;

    function setState(state) {
        spinner.classList.toggle('hidden', state !== 'loading');
        check.classList.toggle('hidden', state !== 'done');
        errorIcon.classList.toggle('hidden', state !== 'error');
    }

    function show(message, state) {
        clearTimeout(hideTimer);
        text.textContent = message;
        setState(state);
        toast.classList.remove('hidden');
        toast.classList.add('flex');
    }

    function hideAfter(ms) {
        clearTimeout(hideTimer);
        hideTimer = setTimeout(() => {
            toast.classList.add('hidden');
            toast.classList.remove('flex');
        }, ms);
    }

    function filenameFrom(disposition, fallback) {
        if (!disposition) return fallback;
        const match = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(disposition);
        return match ? decodeURIComponent(match[1]) : fallback;
    }

    async function downloadViaFetch(link) {
        const href = link.getAttribute('href');
        const label = (link.textContent || 'File').trim();

        show(`Downloading ${label}…`, 'loading');

        try {
            const response = await fetch(href, { credentials: 'same-origin' });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const blob = await response.blob();
            const filename = filenameFrom(response.headers.get('Content-Disposition'), label);

            const objectUrl = URL.createObjectURL(blob);
            const tempLink = document.createElement('a');
            tempLink.href = objectUrl;
            tempLink.download = filename;
            document.body.appendChild(tempLink);
            tempLink.click();
            tempLink.remove();
            URL.revokeObjectURL(objectUrl);

            show(`Downloaded ${filename}`, 'done');
            hideAfter(2500);
        } catch (err) {
            show(`Couldn't download ${label}`, 'error');
            hideAfter(4000);
        }
    }

    document.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== 0) return;
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        const link = event.target.closest('a[download]');
        if (!link) return;
        if (link.href.startsWith('blob:')) return; // our own synthetic save link, not a user-facing download trigger

        event.preventDefault();
        downloadViaFetch(link);
    });
})();
