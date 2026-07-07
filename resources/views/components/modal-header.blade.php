@props(['title', 'subtitle' => null])

<div class="flex items-start justify-between border-b border-slate-100 px-6 py-4">
    <div>
        <h3 class="text-base font-semibold text-slate-900">{{ $title }}</h3>
        @if($subtitle)
            <p class="mt-0.5 text-sm text-slate-500">{{ $subtitle }}</p>
        @endif
    </div>
    <button type="button" x-on:click="show = false" class="text-slate-400 hover:text-slate-600">
        <x-icon name="x" class="h-5 w-5" />
    </button>
</div>
