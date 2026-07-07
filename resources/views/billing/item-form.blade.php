<x-modal-header title="Add Bill Item" :subtitle="$bill->bill_id . ' · ' . $bill->patient?->fullName()" />
<form id="bill-item-form" method="post" action="/bills/{{ $bill->bill_id }}/items" class="space-y-4 px-6 py-5" x-data="{ qty: 1, price: 0 }">
    @csrf
    <input type="hidden" name="_modal_target" value="/bills/{{ $bill->bill_id }}/items/create">
    <div>
        <label class="mb-1.5 block text-sm font-medium text-slate-700">Item Type *</label>
        <select name="item_type" required class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
            @foreach(['service', 'medicine', 'lab_test', 'procedure', 'room'] as $t)
                <option value="{{ $t }}">{{ ucfirst(str_replace('_', ' ', $t)) }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="mb-1.5 block text-sm font-medium text-slate-700">Description</label>
        <input name="description" placeholder="e.g. Cardiac consultation" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
    </div>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Quantity *</label>
            <input type="number" name="quantity" x-model.number="qty" min="1" required class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Unit Price ($) *</label>
            <input type="number" step="0.01" name="unit_price" x-model.number="price" min="0" required class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
    </div>
    <div class="rounded-lg bg-blue-50 px-4 py-3 text-sm text-blue-700">
        Subtotal: <span class="font-semibold" x-text="'$' + (qty * price).toFixed(2)"></span>
    </div>
</form>
<div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4">
    <x-button variant="secondary" x-on:click="show = false">Cancel</x-button>
    <x-button variant="primary" type="submit" form="bill-item-form">Add Item</x-button>
</div>
