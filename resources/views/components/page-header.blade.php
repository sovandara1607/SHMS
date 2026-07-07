@props(['title', 'subtitle' => null])

<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-xl font-bold text-slate-900">{{ $title }}</h1>
        @if($subtitle)
            <p class="mt-0.5 text-sm text-slate-500">{{ $subtitle }}</p>
        @endif
    </div>
    @if(isset($actions))
        <div class="flex items-center gap-2">{{ $actions }}</div>
    @endif
</div>
