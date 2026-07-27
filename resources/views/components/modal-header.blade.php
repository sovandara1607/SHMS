@props(['title', 'subtitle' => null, 'icon' => null, 'iconColor' => 'blue'])

<div class="flex items-start justify-between border-b border-slate-100 px-6 py-4">
    <div class="flex items-start gap-3">
        @if($icon)
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $iconColor === 'amber' ? 'bg-amber-100 text-amber-600' : 'bg-blue-100 text-blue-600' }}">
                <x-icon :name="$icon" class="h-4.5 w-4.5" />
            </div>
        @endif
        <div>
            <h3 class="text-base font-semibold text-slate-900">{{ $title }}</h3>
            @if($subtitle)
                <p class="mt-0.5 text-sm text-slate-500">{{ $subtitle }}</p>
            @endif
        </div>
    </div>
    <button type="button" x-on:click="show = false" class="text-slate-400 hover:text-slate-600">
        <x-icon name="x" class="h-5 w-5" />
    </button>
</div>
