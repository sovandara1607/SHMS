@php
$target = '/beds/' . $bed->bed_id . '/assign';
@endphp

<x-modal-header title="Assign Patient" :subtitle="'Room ' . ($bed->room->room_number ?: $bed->room->room_id) . ' · Bed ' . ($bed->bed_number ?: $bed->bed_id)" />
<form id="bed-assign-form" method="post" action="{{ $target }}" class="space-y-4 px-6 py-5">
    @csrf
    <input type="hidden" name="_modal_target" value="{{ $target }}">
    <div>
        <label class="mb-1.5 block text-sm font-medium text-slate-700">Patient *</label>
        <x-patient-picker name="patient_id" required />
    </div>
</form>
<div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4">
    <x-button variant="secondary" x-on:click="show = false">Cancel</x-button>
    <x-button variant="primary" type="submit" form="bed-assign-form">Assign</x-button>
</div>
