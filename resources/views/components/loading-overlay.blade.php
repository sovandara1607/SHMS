{{-- Slim top progress bar — used for both partial link navigation (main.js
     swaps <main> without touching this) and as a visual cue during form
     submits. Deliberately shape-agnostic: every page here has a different
     layout (stat cards, card grids, tabs, forms...), so any "fake content"
     skeleton inevitably mismatches whatever the real page turns out to be.
     A neutral progress bar can never look wrong. --}}
<div id="top-progress-bar" class="pointer-events-none fixed inset-x-0 top-0 z-[110] h-[3px] w-0 bg-blue-600 opacity-0 transition-[width] duration-300 ease-out" aria-hidden="true"></div>

{{-- Full-page overlay — form submits only (a real mutating action, not just
     navigation), so it's reasonable to block interaction while it's in
     flight. Just a centered spinner + the action's own label; no fake
     content shapes to keep in sync with every page's real layout. --}}
<div id="global-loading-overlay"
     class="fixed inset-0 z-[100] hidden flex-col items-center justify-center gap-3 bg-paper/70 backdrop-blur-sm"
     role="status" aria-live="polite" aria-label="Loading">
    <svg class="h-8 w-8 animate-spin text-blue-600" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
    </svg>
    <span id="global-loading-overlay-text" class="text-sm font-medium text-slate-600">Loading…</span>
</div>
