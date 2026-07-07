@php
$action = $mode === 'create' ? '/departments' : '/departments/' . $department->department_id;
$target = $mode === 'create' ? '/departments/create' : '/departments/' . $department->department_id . '/edit';
@endphp

<x-modal-header :title="$mode === 'create' ? 'Add Department' : 'Edit Department'" />
<form id="department-form" method="post" action="{{ $action }}" class="space-y-4 px-6 py-5">
    @csrf
    @if($mode === 'edit')@method('PUT')@endif
    <input type="hidden" name="_modal_target" value="{{ $target }}">
    <div>
        <label class="mb-1.5 block text-sm font-medium text-slate-700">Name *</label>
        <input name="department_name" required value="{{ old('department_name', $department->department_name) }}" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
    </div>
    <div>
        <label class="mb-1.5 block text-sm font-medium text-slate-700">Description</label>
        <textarea name="description" rows="2" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">{{ old('description', $department->description) }}</textarea>
    </div>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Capacity</label>
            <input type="number" name="capacity" value="{{ old('capacity', $department->capacity) }}" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Status</label>
            <select name="status" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                @foreach(['active', 'inactive'] as $s)
                    <option value="{{ $s }}" @selected(old('status', $department->status ?? 'active') === $s)>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
        </div>
    </div>
</form>
<div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4">
    <x-button variant="secondary" x-on:click="show = false">Cancel</x-button>
    <x-button variant="primary" type="submit" form="department-form">{{ $mode === 'create' ? 'Add Department' : 'Save Changes' }}</x-button>
</div>
