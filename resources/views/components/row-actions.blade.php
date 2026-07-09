<div class="relative inline-block text-left" x-data="{ open: false }">
    <button type="button" x-on:click="open = !open" x-on:click.outside="open = false"
            class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-600">
        <x-icon name="dots" class="h-4 w-4" />
    </button>
    <div x-show="open" x-on:click="open = false" x-transition style="display: none;"
         class="absolute right-0 z-20 mt-1 w-40 rounded-lg border border-slate-200 bg-white py-1 shadow-lg">
        {{ $slot }}
    </div>
</div>
