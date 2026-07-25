<div id="download-toast"
     class="fixed bottom-5 right-5 z-[110] hidden max-w-sm items-center gap-2.5 rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm shadow-lg"
     role="status" aria-live="polite">
    <svg id="download-toast-spinner" class="h-4 w-4 shrink-0 animate-spin text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
    </svg>
    <svg id="download-toast-check" class="hidden h-4 w-4 shrink-0 text-green-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
    </svg>
    <svg id="download-toast-error" class="hidden h-4 w-4 shrink-0 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
    </svg>
    <span id="download-toast-text" class="truncate font-medium text-slate-700">Downloading…</span>
</div>
