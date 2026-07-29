@php
$action = $mode === 'create' ? '/schedule' : '/schedule/' . $shift->shift_id;
$target = $mode === 'create' ? '/schedule/create' : '/schedule/' . $shift->shift_id . '/edit';
@endphp

<x-modal-header :title="$mode === 'create' ? 'Assign Shift' : 'Edit Shift'" :subtitle="$mode === 'edit' ? $shift->shift_id . ' · ' . $shift->staff_name : null" />

<form id="schedule-form" method="post" action="{{ $action }}" class="max-h-[70vh] overflow-y-auto px-6 py-5">
    @csrf
    @if($mode === 'edit')@method('PUT')@endif
    <input type="hidden" name="_modal_target" value="{{ $target }}">

    @if($mode === 'create')
        <div class="mb-4">
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Staff *</label>
            <x-staff-picker :required="true" />
        </div>
    @else
        <div class="mb-4 rounded-lg bg-slate-50 px-4 py-3">
            <div class="grid grid-cols-2 gap-y-1 text-sm">
                <span class="text-slate-500">Staff ID</span><span class="text-right font-medium text-slate-900">{{ $shift->staff_id }}</span>
                <span class="text-slate-500">Staff Name</span><span class="text-right text-slate-900">{{ $shift->staff_name }}</span>
            </div>
        </div>
    @endif

    <div class="mb-4 grid grid-cols-2 gap-4">
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Shift Date *</label>
            <input type="date" name="shift_date" value="{{ old('shift_date', $shift->shift_date ?? '') }}" required
                   class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Shift Type *</label>
            <select name="shift_type" required class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                <option value="">— select —</option>
                @foreach(['morning' => 'Morning', 'afternoon' => 'Afternoon', 'night' => 'Night'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('shift_type', $shift->shift_type ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="mb-4 grid grid-cols-2 gap-4">
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Start Time *</label>
            <input type="time" name="start_time" value="{{ old('start_time', $shift->start_time ?? '') }}" required
                   class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">End Time *</label>
            <input type="time" name="end_time" value="{{ old('end_time', $shift->end_time ?? '') }}" required
                   class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
    </div>

    @if($mode === 'edit')
        <div class="mb-4">
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Status *</label>
            <select name="status" required class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                @foreach(['scheduled' => 'Scheduled', 'completed' => 'Completed', 'on_leave' => 'On Leave', 'cancelled' => 'Cancelled'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('status', $shift->status ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    @endif
</form>
<div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4">
    <x-button variant="secondary" x-on:click="show = false">Cancel</x-button>
    <x-button variant="primary" type="submit" form="schedule-form">
        {{ $mode === 'create' ? 'Assign Shift' : 'Save Changes' }}
    </x-button>
</div>
