@php
$action = $mode === 'create' ? '/rooms' : '/rooms/' . $room->room_id;
$target = $mode === 'create' ? '/rooms/create' : '/rooms/' . $room->room_id . '/edit';
@endphp

<x-modal-header :title="$mode === 'create' ? 'Add Room' : 'Edit Room'" />
<form id="room-form" method="post" action="{{ $action }}" class="space-y-4 px-6 py-5">
    @csrf
    @if($mode === 'edit')@method('PUT')@endif
    <input type="hidden" name="_modal_target" value="{{ $target }}">
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Room Number</label>
            <input name="room_number" value="{{ old('room_number', $room->room_number) }}" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Floor</label>
            <input type="number" name="floor_number" value="{{ old('floor_number', $room->floor_number) }}" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
    </div>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Room Type</label>
            <select name="room_type" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                <option value="">— select —</option>
                @foreach(['general', 'private', 'icu', 'emergency'] as $t)
                    <option value="{{ $t }}" @selected(old('room_type', $room->room_type) === $t)>{{ ucfirst($t) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Department</label>
            <select name="department_id" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                <option value="">— none —</option>
                @foreach($departments as $d)
                    <option value="{{ $d->department_id }}" @selected(old('department_id', $room->department_id) === $d->department_id)>{{ $d->department_name }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div>
        <label class="mb-1.5 block text-sm font-medium text-slate-700">Status</label>
        <select name="status" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
            @foreach(['available', 'occupied', 'maintenance'] as $s)
                <option value="{{ $s }}" @selected(old('status', $room->status ?? 'available') === $s)>{{ ucfirst($s) }}</option>
            @endforeach
        </select>
    </div>
</form>
<div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4">
    <x-button variant="secondary" x-on:click="show = false">Cancel</x-button>
    <x-button variant="primary" type="submit" form="room-form">{{ $mode === 'create' ? 'Add Room' : 'Save Changes' }}</x-button>
</div>
