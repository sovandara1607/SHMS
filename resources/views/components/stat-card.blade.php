@props(['label', 'value', 'icon' => 'grid', 'iconColor' => 'blue', 'badge' => null, 'reportUrl' => null])

@php
$iconColors = [
    'blue' => 'text-blue-600',
    'green' => 'text-green-600',
    'amber' => 'text-amber-600',
    'red' => 'text-red-600',
    'purple' => 'text-purple-600',
];
$badgeType = $badge['type'] ?? 'none';
@endphp

{{-- Paper chart card, same family as every other card in the app — a
     monospace value and a quiet heartbeat trace keep a hint of the
     vitals-monitor idea without a dark bezel behind it. --}}
<div {{ $attributes->merge(['class' => 'shadow-paper rounded-xl border border-manila/60 bg-paper-card p-4']) }}>
    <div class="flex items-start justify-between gap-2">
        <x-icon :name="$icon" class="h-4 w-4 shrink-0 {{ $iconColors[$iconColor] ?? $iconColors['blue'] }}" />
        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-green-500"></span>
    </div>
    <p class="mt-2.5 text-xs uppercase tracking-wider text-slate-400">{{ $label }}</p>
    <p class="mt-0.5 break-words font-mono text-2xl font-semibold text-slate-900">{{ $value }}</p>

    {{-- Heartbeat trace sits in normal flow here, in the gap between the
         value and the footer row below — never absolutely positioned over
         the "View report" link, so it can't visually cut through it. --}}
    <svg class="pointer-events-none mt-2 h-4 w-full opacity-40" viewBox="0 0 200 16" preserveAspectRatio="none">
        <polyline points="0,8 40,8 48,2 56,14 64,8 100,8 108,3 116,13 124,8 200,8"
                  fill="none" stroke="currentColor" class="text-manila" stroke-width="1.5" />
    </svg>

    <div class="mt-2 flex items-center justify-between gap-2">
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
