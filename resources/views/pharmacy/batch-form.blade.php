@php
$mode = $mode ?? 'create';
$batch = $batch ?? null;
$action = $mode === 'create' ? '/medicine-batches' : '/medicine-batches/' . $batch->batch_id;
$target = $mode === 'create' ? '/medicine-batches/create' : '/medicine-batches/' . $batch->batch_id . '/edit';
@endphp

<x-modal-header :title="$mode === 'create' ? 'Add Batch' : 'Edit Batch'" />
<form id="batch-form" method="post" action="{{ $action }}" class="space-y-4 px-6 py-5">
    @csrf
    @if($mode === 'edit')@method('PUT')@endif
    <input type="hidden" name="_modal_target" value="{{ $target }}">

    @if($mode === 'create')
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Medicine *</label>
            <select name="medicine_id" required class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                <option value="">— select —</option>
                @foreach($medicines as $m)
                    <option value="{{ $m->medicine_id }}" @selected(old('medicine_id') === $m->medicine_id)>{{ $m->medicine_name }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <div>
        <label class="mb-1.5 block text-sm font-medium text-slate-700">Batch Number{{ $mode === 'create' ? ' *' : '' }}</label>
        <input name="batch_number" value="{{ old('batch_number', $batch->batch_number ?? '') }}" placeholder="e.g. LIS-2025-04" @required($mode === 'create')
               class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
    </div>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Manufacture Date</label>
            <input type="date" name="manufacture_date" value="{{ old('manufacture_date', $batch->manufacture_date ?? '') }}" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Expiry Date</label>
            <input type="date" name="expiry_date" value="{{ old('expiry_date', $batch->expiry_date ?? '') }}" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
    </div>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Quantity *</label>
            <input type="number" name="quantity" required value="{{ old('quantity', $batch->quantity ?? 0) }}" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Batch Status</label>
            <select name="status" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                @foreach(['valid', 'expired', 'damaged'] as $s)
                    <option value="{{ $s }}" @selected(old('status', $batch->status ?? 'valid') === $s)>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
        </div>
    </div>
</form>
<div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4">
    <x-button variant="secondary" x-on:click="show = false">Cancel</x-button>
    <x-button variant="primary" type="submit" form="batch-form">{{ $mode === 'create' ? 'Add Batch' : 'Save Changes' }}</x-button>
</div>
