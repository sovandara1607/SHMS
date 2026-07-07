@props(['label', 'value', 'trend' => null, 'icon' => 'grid', 'iconColor' => 'blue'])

@php
$iconColors = [
    'blue' => 'bg-blue-50 text-blue-600',
    'green' => 'bg-green-50 text-green-600',
    'amber' => 'bg-amber-50 text-amber-600',
    'red' => 'bg-red-50 text-red-600',
    'purple' => 'bg-purple-50 text-purple-600',
];
$trendPositive = is_string($trend) && str_starts_with($trend, '+');
$trendNegative = is_string($trend) && str_starts_with($trend, '-');
@endphp

<div {{ $attributes->merge(['class' => 'rounded-xl border border-slate-200 bg-white p-4']) }}>
    <div class="flex items-start justify-between">
        <div>
            <p class="text-sm text-slate-500">{{ $label }}</p>
            <p class="mt-1 text-2xl font-bold text-slate-900">{{ $value }}</p>
        </div>
        <div class="flex h-9 w-9 items-center justify-center rounded-lg {{ $iconColors[$iconColor] ?? $iconColors['blue'] }}">
            <x-icon :name="$icon" class="h-5 w-5" />
        </div>
    </div>
    @if($trend)
        <p class="mt-2 text-xs font-medium {{ $trendPositive ? 'text-green-600' : ($trendNegative ? 'text-red-600' : 'text-slate-400') }}">
            {{ $trend }} from last month
        </p>
    @endif
</div>
