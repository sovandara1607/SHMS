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
    'on_leave' => 'bg-amber-50 text-amber-700',
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
    'available' => 'bg-green-50 text-green-700',
    'occupied' => 'bg-amber-50 text-amber-700',
    'maintenance' => 'bg-red-50 text-red-700',
    'doctor' => 'bg-blue-50 text-blue-700',
    'nurse' => 'bg-purple-50 text-purple-700',
    'receptionist' => 'bg-amber-50 text-amber-700',
    'pharmacist' => 'bg-green-50 text-green-700',
    'lab_technician' => 'bg-cyan-50 text-cyan-700',
    'admin' => 'bg-red-50 text-red-700',
    'super_admin' => 'bg-red-50 text-red-700',
];
$key = strtolower(str_replace(' ', '_', (string) $status));
$classes = $map[$key] ?? 'bg-slate-100 text-slate-600';
$label = ucwords(str_replace('_', ' ', (string) $status));
@endphp

{{-- Styled like a hospital wristband/ID tag: a small punched hole at the
     leading edge, a slightly extruded plastic-tag shadow. --}}
<span {{ $attributes->merge(['class' => "relative inline-flex items-center gap-1 rounded-full py-1 pl-4 pr-2.5 text-xs font-semibold shadow-emboss $classes"]) }}>
    <span class="absolute left-1.5 top-1/2 h-1 w-1 -translate-y-1/2 rounded-full bg-white/70 shadow-[inset_0_1px_1px_rgba(0,0,0,0.25)]"></span>
    {{ $label }}
</span>
