{{-- Single source of truth for the skeleton shape, cloned by app.js both into
     the full-page overlay below (form submits, full reloads) and inline into
     <main> for partial page navigation (link clicks) — see app.js. --}}
<template id="skeleton-template">
    <div class="mb-4 flex items-center justify-between">
        <div class="h-6 w-48 animate-pulse rounded-md bg-slate-200"></div>
        <div class="h-9 w-28 animate-pulse rounded-lg bg-slate-200"></div>
    </div>

    <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
        @for ($i = 0; $i < 4; $i++)
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="h-3 w-20 animate-pulse rounded bg-slate-200"></div>
                <div class="mt-2 h-6 w-14 animate-pulse rounded bg-slate-200"></div>
            </div>
        @endfor
    </div>

    <div class="mb-4 flex gap-1.5">
        <div class="h-9 w-32 animate-pulse rounded-lg bg-slate-200"></div>
        <div class="h-9 w-28 animate-pulse rounded-lg bg-slate-100"></div>
        <div class="h-9 w-28 animate-pulse rounded-lg bg-slate-100"></div>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <div class="border-b border-slate-100 px-4 py-3">
            <div class="h-3 w-full max-w-md animate-pulse rounded bg-slate-100"></div>
        </div>
        @for ($row = 0; $row < 8; $row++)
            <div class="flex items-center gap-6 border-b border-slate-50 px-4 py-3.5 last:border-0">
                <div class="h-3 w-16 shrink-0 animate-pulse rounded bg-slate-200" style="animation-delay: {{ $row * 60 }}ms"></div>
                <div class="h-3 w-28 shrink-0 animate-pulse rounded bg-slate-200/70" style="animation-delay: {{ $row * 60 + 30 }}ms"></div>
                <div class="h-3 w-20 shrink-0 animate-pulse rounded bg-slate-200/70" style="animation-delay: {{ $row * 60 + 60 }}ms"></div>
                <div class="h-3 w-24 shrink-0 animate-pulse rounded bg-slate-200/70" style="animation-delay: {{ $row * 60 + 90 }}ms"></div>
                <div class="ml-auto h-5 w-16 shrink-0 animate-pulse rounded-full bg-slate-200/70" style="animation-delay: {{ $row * 60 + 120 }}ms"></div>
            </div>
        @endfor
    </div>
</template>

<div id="global-loading-overlay"
     class="fixed inset-0 z-[100] hidden flex-col overflow-hidden bg-slate-50"
     role="status" aria-live="polite" aria-label="Loading">
    <span id="global-loading-overlay-text" class="sr-only">Loading…</span>
    <div id="global-loading-overlay-content" class="mx-auto w-full max-w-6xl flex-1 overflow-hidden p-4 sm:p-6"></div>
</div>
