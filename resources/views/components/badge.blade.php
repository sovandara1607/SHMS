@props(['status' => ''])

@php
$map = [
    'active' => 'bg-green-50 text-green-700',
    'admitted' => 'bg-blue-50 text-blue-700',
    'icu' => 'bg-red-50 text-red-700',
    'discharged' => 'bg-purple-50 text-purple-700',
    'inactive' => 'bg-slate-100 text-slate-500',
    'scheduled' => 'bg-blue-50 text-blue-700',
    'completed' => 'bg-green-50 text-green-700',
    'cancelled' => 'bg-red-50 text-red-700',
    'pending' => 'bg-amber-50 text-amber-700',
    'in_progress' => 'bg-blue-50 text-blue-700',
    'paid' => 'bg-green-50 text-green-700',
    'unpaid' => 'bg-amber-50 text-amber-700',
    'partially_paid' => 'bg-blue-50 text-blue-700',
    'overdue' => 'bg-red-50 text-red-700',
    'expired' => 'bg-red-50 text-red-700',
    'low' => 'bg-amber-50 text-amber-700',
    'ok' => 'bg-green-50 text-green-700',
    'service' => 'bg-amber-50 text-amber-700',
    'dispensed' => 'bg-green-50 text-green-700',
    'partially_dispensed' => 'bg-amber-50 text-amber-700',
    'normal' => 'bg-green-50 text-green-700',
    'abnormal' => 'bg-amber-50 text-amber-700',
];
$key = strtolower(str_replace(' ', '_', (string) $status));
$classes = $map[$key] ?? 'bg-slate-100 text-slate-600';
$label = ucwords(str_replace('_', ' ', (string) $status));
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold $classes"]) }}>
    {{ $label }}
</span>
