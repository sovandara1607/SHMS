@php
$mode = $mode ?? 'create';
$medicine = $medicine ?? null;
$action = $mode === 'create' ? '/medicines' : '/medicines/' . $medicine->medicine_id;
$target = $mode === 'create' ? '/medicines/create' : '/medicines/' . $medicine->medicine_id . '/edit';
@endphp

<x-modal-header :title="$mode === 'create' ? 'Add Medicine' : 'Edit Medicine'" />
<form id="medicine-form" method="post" action="{{ $action }}" class="space-y-4 px-6 py-5">
    @csrf
    @if($mode === 'edit')@method('PUT')@endif
    <input type="hidden" name="_modal_target" value="{{ $target }}">
    <div>
        <label class="mb-1.5 block text-sm font-medium text-slate-700">Name *</label>
        <input name="medicine_name" required value="{{ old('medicine_name', $medicine->medicine_name ?? '') }}" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
    </div>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Type</label>
            <input name="medicine_type" value="{{ old('medicine_type', $medicine->medicine_type ?? '') }}" placeholder="Tablet / Capsule" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Manufacturer</label>
            <input name="manufacturer" value="{{ old('manufacturer', $medicine->manufacturer ?? '') }}" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
    </div>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Unit Price</label>
            <input type="number" step="0.01" name="unit_price" value="{{ old('unit_price', $medicine->unit_price ?? '') }}" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">{{ $mode === 'create' ? 'Initial Stock' : 'Stock' }}</label>
            <input type="number" name="stock_quantity" value="{{ old('stock_quantity', $medicine->stock_quantity ?? 0) }}" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
    </div>
    @if($mode === 'edit')
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Status</label>
            <select name="status" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                @foreach(['available' => 'Available', 'unavailable' => 'Unavailable'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('status', $medicine->status ?? 'available') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    @endif
</form>
<div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4">
    <x-button variant="secondary" x-on:click="show = false">Cancel</x-button>
    <x-button variant="primary" type="submit" form="medicine-form">{{ $mode === 'create' ? 'Add Medicine' : 'Save Changes' }}</x-button>
</div>
