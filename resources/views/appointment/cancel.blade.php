<x-modal-header title="Cancel Appointment" />

<form method="post" action="/appointments/{{ $appointment->appointment_id }}/cancel" class="space-y-4 px-6 py-5">
    @csrf
    <dl class="space-y-2 text-sm">
        <div class="flex justify-between"><dt class="text-slate-500">Appointment ID</dt><dd class="font-medium text-slate-900">{{ $appointment->appointment_id }}</dd></div>
        <div class="flex justify-between"><dt class="text-slate-500">Patient</dt><dd class="text-slate-900">{{ $appointment->patient?->fullName() }}</dd></div>
        <div class="flex justify-between"><dt class="text-slate-500">Doctor</dt><dd class="text-slate-900">{{ $appointment->doctor?->name() }}</dd></div>
        <div class="flex justify-between"><dt class="text-slate-500">Date</dt><dd class="text-slate-900">{{ $appointment->appointment_date }}</dd></div>
        <div class="flex justify-between"><dt class="text-slate-500">Time</dt><dd class="text-slate-900">{{ \Illuminate\Support\Str::substr($appointment->appointment_time, 0, 5) }}</dd></div>
    </dl>
    <div>
        <label class="mb-1.5 block text-sm font-medium text-red-700">Cancellation Reason *</label>
        <textarea name="cancellation_reason" required rows="2" placeholder="Explain why this appointment is being cancelled..."
                  class="w-full rounded-lg border border-red-200 px-3.5 py-2.5 text-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-500/20"></textarea>
    </div>
    <div class="flex justify-end gap-2 pt-2">
        <x-button variant="secondary" type="button" x-on:click="show = false">Back</x-button>
        <x-button variant="danger" type="submit">Confirm Cancel</x-button>
    </div>
</form>
