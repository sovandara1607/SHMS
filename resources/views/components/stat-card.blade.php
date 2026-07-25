@props(['label', 'value', 'icon' => 'grid', 'iconColor' => 'blue', 'badge' => null, 'reportUrl' => null])

@php
$iconColors = [
    'blue' => 'bg-blue-50 text-blue-600',
    'green' => 'bg-green-50 text-green-600',
    'amber' => 'bg-amber-50 text-amber-600',
    'red' => 'bg-red-50 text-red-600',
    'purple' => 'bg-purple-50 text-purple-600',
];
$badgeType = $badge['type'] ?? 'none';
@endphp

<div {{ $attributes->merge(['class' => 'rounded-xl border border-slate-200 bg-white p-4']) }}>
    <div class="flex items-start justify-between gap-2">
        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $iconColors[$iconColor] ?? $iconColors['blue'] }}">
            <x-icon :name="$icon" class="h-5 w-5" />
        </div>
    </div>
    <p class="mt-3 text-sm text-slate-500">{{ $label }}</p>
    <p class="mt-0.5 break-words text-2xl font-bold text-slate-900">{{ $value }}</p>

    <div class="mt-3 flex items-center justify-between gap-2">
        @if($badgeType === 'trend')
            <p class="flex items-center gap-1 text-xs font-medium {{ $badge['direction'] === 'up' ? 'text-green-600' : 'text-red-600' }}">
                <x-icon name="chevron-down" class="h-3 w-3 shrink-0 {{ $badge['direction'] === 'up' ? 'rotate-180' : '' }}" />
                {{ $badge['text'] }}
            </p>
        @elseif($badgeType === 'ratio')
            <p class="text-xs font-medium text-slate-400">{{ $badge['text'] }}</p>
        @else
            <span></span>
        @endif

        @if($reportUrl)
            <a href="{{ $reportUrl }}" class="shrink-0 rounded-lg border border-blue-200 px-2.5 py-1 text-xs font-medium text-blue-600 hover:bg-blue-50">View report</a>
        @endif
    </div>
</div>
