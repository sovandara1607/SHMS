<x-modal-header :title="$appointment->appointment_id" :subtitle="$appointment->appointment_date . ' · ' . \Illuminate\Support\Str::substr($appointment->appointment_time, 0, 5)" />

<div class="space-y-3 px-6 py-5 text-sm">
    <div class="flex justify-between"><span class="text-slate-500">Patient</span><span class="font-medium text-slate-900">{{ $appointment->patient?->fullName() }} ({{ $appointment->patient_id }})</span></div>
    <div class="flex justify-between"><span class="text-slate-500">Doctor</span><span class="font-medium text-slate-900">{{ $appointment->doctor?->name() }}</span></div>
    <div class="flex justify-between"><span class="text-slate-500">Booked By</span><span class="text-slate-900">{{ $appointment->bookedByStaff?->fullName() ?? '—' }}</span></div>
    <div class="flex justify-between"><span class="text-slate-500">Date</span><span class="text-slate-900">{{ $appointment->appointment_date }}</span></div>
    <div class="flex justify-between"><span class="text-slate-500">Time</span><span class="text-slate-900">{{ \Illuminate\Support\Str::substr($appointment->appointment_time, 0, 5) }}</span></div>
    <div class="flex justify-between"><span class="text-slate-500">Reason</span><span class="text-slate-900">{{ $appointment->reason ?: '—' }}</span></div>
    <div class="flex justify-between"><span class="text-slate-500">Status</span><x-badge :status="$appointment->status" /></div>
    @if($appointment->status === 'cancelled')
        <div class="flex justify-between"><span class="text-slate-500">Cancellation Reason</span><span class="text-slate-900">{{ $appointment->cancellation_reason }}</span></div>
    @endif
    <div class="flex justify-between"><span class="text-slate-500">Created At</span><span class="text-slate-900">{{ $appointment->created_at }}</span></div>
    <div class="flex justify-between"><span class="text-slate-500">Updated At</span><span class="text-slate-900">{{ $appointment->updated_at }}</span></div>
</div>

<div class="flex justify-end border-t border-slate-100 px-6 py-4">
    <x-button variant="secondary" x-on:click="show = false">Close</x-button>
</div>
