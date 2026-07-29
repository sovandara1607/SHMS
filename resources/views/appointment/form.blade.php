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
    <div
        x-data="{
            doctorId: @js(old('doctor_id', $appointment->doctor_id)),
            apptDate: @js(old('appointment_date', $appointment->appointment_date)),
            apptTime: @js(old('appointment_time', is_string($appointment->appointment_time) ? substr($appointment->appointment_time, 0, 5) : '')),
            today: @js(now()->toDateString()),
            nowTime: @js(now()->format('H:i')),
            bookedSlots: [],
            fetchBookedSlots() {
                if (!this.doctorId || !this.apptDate) { this.bookedSlots = []; return; }
                fetch('/appointments/booked-slots?doctor_id=' + encodeURIComponent(this.doctorId) + '&date=' + encodeURIComponent(this.apptDate))
                    .then(r => r.json())
                    .then(data => { this.bookedSlots = data; });
            },
            get minTime() { return this.apptDate === this.today ? this.nowTime : null; },
            get timeConflict() { return this.apptTime && this.bookedSlots.includes(this.apptTime); },
        }"
        x-init="fetchBookedSlots()"
    >
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Doctor *</label>
            <select name="doctor_id" x-model="doctorId" @change="fetchBookedSlots()" required class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                <option value="">— select —</option>
                @foreach($doctors as $d)
                    <option value="{{ $d->doctor_id }}">{{ $d->name() }} — {{ $d->specialization }}</option>
                @endforeach
            </select>
        </div>
        <div class="mt-4 grid grid-cols-2 gap-4">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Appointment Date *</label>
                <input type="date" name="appointment_date" x-model="apptDate" @change="fetchBookedSlots()" :min="today" required
                       class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Appointment Time *</label>
                <input type="time" name="appointment_time" x-model="apptTime" :min="minTime" required
                       class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                       :class="timeConflict ? 'border-red-400 focus:border-red-500 focus:ring-red-500/20' : 'border-slate-200'">
            </div>
        </div>
        <p x-show="bookedSlots.length" style="display: none;" class="mt-1.5 text-xs text-slate-500">
            Already booked for this doctor on this date: <span x-text="bookedSlots.join(', ')"></span>
        </p>
        <p x-show="timeConflict" style="display: none;" class="mt-1 text-xs font-medium text-red-600">
            This doctor already has an appointment at this time — pick a different time.
        </p>
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
