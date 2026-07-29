<x-modal-header :title="$appointment->appointment_id" :subtitle="$appointment->appointment_date . ' · ' . \Illuminate\Support\Str::substr($appointment->appointment_time, 0, 5)" icon="calendar" />

<div class="max-h-[65vh] overflow-y-auto px-6 py-5">
    <div class="mb-4 flex items-center justify-between rounded-lg bg-slate-50 px-4 py-3">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Status</span>
        <x-badge :status="$appointment->status" />
    </div>

    <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Appointment Details</p>
    <div class="mb-4 grid grid-cols-1 gap-2.5 sm:grid-cols-2">
        <div class="rounded-lg border border-slate-100 bg-slate-50/60 px-3 py-2">
            <p class="text-xs text-slate-500">Patient</p>
            <p class="mt-0.5 text-sm font-medium text-slate-900">{{ $appointment->patient?->fullName() }}</p>
            <p class="text-xs text-slate-400">{{ $appointment->patient_id }}</p>
        </div>
        <div class="rounded-lg border border-slate-100 bg-slate-50/60 px-3 py-2">
            <p class="text-xs text-slate-500">Doctor</p>
            <p class="mt-0.5 text-sm font-medium text-slate-900">{{ $appointment->doctor?->name() }}</p>
        </div>
        <div class="rounded-lg border border-slate-100 bg-slate-50/60 px-3 py-2"><p class="text-xs text-slate-500">Date</p><p class="mt-0.5 text-sm text-slate-900">{{ $appointment->appointment_date }}</p></div>
        <div class="rounded-lg border border-slate-100 bg-slate-50/60 px-3 py-2"><p class="text-xs text-slate-500">Time</p><p class="mt-0.5 text-sm text-slate-900">{{ \Illuminate\Support\Str::substr($appointment->appointment_time, 0, 5) }}</p></div>
    </div>

    <p class="mb-1 text-xs font-semibold uppercase tracking-wider text-slate-400">Reason</p>
    <p class="mb-4 rounded-lg bg-blue-50 px-4 py-3 text-sm text-slate-800">{{ $appointment->reason ?: '—' }}</p>

    @if($appointment->status === 'cancelled')
        <p class="mb-1 text-xs font-semibold uppercase tracking-wider text-slate-400">Cancellation Reason</p>
        <p class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800">{{ $appointment->cancellation_reason }}</p>
    @endif

    <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Booking Information</p>
    <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
        <div class="rounded-lg border border-slate-100 bg-slate-50/60 px-3 py-2"><p class="text-xs text-slate-500">Booked By</p><p class="mt-0.5 text-sm text-slate-900">{{ $appointment->bookedByStaff?->fullName() ?? '—' }}</p></div>
        <div class="rounded-lg border border-slate-100 bg-slate-50/60 px-3 py-2"><p class="text-xs text-slate-500">Created At</p><p class="mt-0.5 text-sm text-slate-900">{{ $appointment->created_at ? \Carbon\Carbon::parse($appointment->created_at)->format('Y-m-d H:i') : '—' }}</p></div>
        <div class="rounded-lg border border-slate-100 bg-slate-50/60 px-3 py-2"><p class="text-xs text-slate-500">Updated At</p><p class="mt-0.5 text-sm text-slate-900">{{ $appointment->updated_at ? \Carbon\Carbon::parse($appointment->updated_at)->format('Y-m-d H:i') : '—' }}</p></div>
    </div>
</div>

<div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4">
    <x-button variant="secondary" x-on:click="show = false">Close</x-button>
    @can('appointment.complete')
        @if($appointment->status === 'scheduled')
            <form method="post" action="/appointments/{{ $appointment->appointment_id }}/complete" onsubmit="return confirm('Mark this appointment as completed?')">
                @csrf
                <x-button variant="primary" type="submit"><x-icon name="check" class="h-4 w-4" /> Mark as Completed</x-button>
            </form>
        @endif
    @endcan
</div>
