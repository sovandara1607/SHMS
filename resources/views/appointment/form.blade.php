@php
$action = $mode === 'create' ? '/appointments' : '/appointments/' . $appointment->appointment_id;
$target = $mode === 'create' ? '/appointments/create' : '/appointments/' . $appointment->appointment_id . '/edit';
@endphp

<x-modal-header :title="$mode === 'create' ? 'Create Appointment' : 'Edit Appointment'" />

<form id="appointment-form" method="post" action="{{ $action }}" class="space-y-4 px-6 py-5">
    @csrf
    @if($mode === 'edit')@method('PUT')@endif
    <input type="hidden" name="_modal_target" value="{{ $target }}">

    <div>
        <label class="mb-1.5 block text-sm font-medium text-slate-700">Patient *</label>
        <x-patient-picker name="patient_id" :selected="$selectedPatient" required />
    </div>
    <div>
        <label class="mb-1.5 block text-sm font-medium text-slate-700">Doctor *</label>
        <select name="doctor_id" required class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
            <option value="">— select —</option>
            @foreach($doctors as $d)
                <option value="{{ $d->doctor_id }}" @selected(old('doctor_id', $appointment->doctor_id) === $d->doctor_id)>{{ $d->name() }} — {{ $d->specialization }}</option>
            @endforeach
        </select>
    </div>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Appointment Date *</label>
            <input type="date" name="appointment_date" value="{{ old('appointment_date', $appointment->appointment_date) }}" required
                   class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Appointment Time *</label>
            <input type="time" name="appointment_time" value="{{ old('appointment_time', is_string($appointment->appointment_time) ? substr($appointment->appointment_time, 0, 5) : '') }}" required
                   class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
    </div>
    <div>
        <label class="mb-1.5 block text-sm font-medium text-slate-700">Reason for Visit</label>
        <input name="reason" value="{{ old('reason', $appointment->reason) }}" placeholder="e.g. Follow-up, consultation..."
               class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
    </div>

    <div class="rounded-lg bg-blue-50 px-4 py-3 text-xs text-blue-700">
        <p class="font-semibold uppercase tracking-wider">System-Generated</p>
        <p class="mt-1">Booked By: {{ auth()->user()->displayName() }}</p>
        <p>{{ $mode === 'create' ? 'Created At' : 'Updated At' }}: {{ now()->format('Y-m-d H:i') }}</p>
    </div>
</form>

<div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4">
    <x-button variant="secondary" x-on:click="show = false">Cancel</x-button>
    <x-button variant="primary" type="submit" form="appointment-form">
        {{ $mode === 'create' ? 'Schedule Appointment' : 'Save Changes' }}
    </x-button>
</div>
