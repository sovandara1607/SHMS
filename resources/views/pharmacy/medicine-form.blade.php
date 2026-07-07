<x-modal-header title="Add Medicine" />
<form id="medicine-form" method="post" action="/medicines" class="space-y-4 px-6 py-5">
    @csrf
    <input type="hidden" name="_modal_target" value="/medicines/create">
    <div>
        <label class="mb-1.5 block text-sm font-medium text-slate-700">Name *</label>
        <input name="medicine_name" required value="{{ old('medicine_name') }}" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
    </div>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Type</label>
            <input name="medicine_type" value="{{ old('medicine_type') }}" placeholder="Tablet / Capsule" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Manufacturer</label>
            <input name="manufacturer" value="{{ old('manufacturer') }}" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
    </div>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Unit Price</label>
            <input type="number" step="0.01" name="unit_price" value="{{ old('unit_price') }}" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Initial Stock</label>
            <input type="number" name="stock_quantity" value="{{ old('stock_quantity', 0) }}" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
    </div>
</form>
<div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4">
    <x-button variant="secondary" x-on:click="show = false">Cancel</x-button>
    <x-button variant="primary" type="submit" form="medicine-form">Add Medicine</x-button>
</div>
