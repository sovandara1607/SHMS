<x-modal-header title="Create Bill" />
<form id="bill-form" method="post" action="/bills" class="max-h-[75vh] overflow-y-auto space-y-4 px-6 py-5"
      x-data="{ items: [{ item_type: 'service', description: '', quantity: 1, unit_price: 0 }],
                total() { return this.items.reduce((sum, i) => sum + (Number(i.quantity) || 0) * (Number(i.unit_price) || 0), 0).toFixed(2); } }">
    @csrf
    <input type="hidden" name="_modal_target" value="/bills/create">

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Patient *</label>
            <x-patient-picker name="patient_id" required />
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Related Medical Record</label>
            <x-medical-record-picker name="medical_record_id" />
        </div>
    </div>

    <div>
        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Bill Items</p>
        <template x-for="(item, i) in items" :key="i">
            <div class="mb-3 space-y-3 rounded-lg bg-slate-50 p-4">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Item <span x-text="i + 1"></span></p>
                    <button type="button" x-show="items.length > 1" x-transition.opacity x-on:click="items.splice(i, 1)" class="text-xs font-medium text-red-600 hover:underline">Remove</button>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Item Type *</label>
                        <select :name="`items[${i}][item_type]`" x-model="item.item_type" required
                                class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                            @foreach(['service', 'medicine', 'lab_test', 'procedure', 'room'] as $t)
                                <option value="{{ $t }}">{{ ucfirst(str_replace('_', ' ', $t)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Description</label>
                        <input :name="`items[${i}][description]`" x-model="item.description" placeholder="e.g. Cardiac consultation"
                               class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Quantity *</label>
                        <input type="number" min="1" :name="`items[${i}][quantity]`" x-model.number="item.quantity" required
                               class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Unit Price ($) *</label>
                        <input type="number" step="0.01" min="0" :name="`items[${i}][unit_price]`" x-model.number="item.unit_price" required
                               class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                    </div>
                </div>
            </div>
        </template>
        <button type="button" x-on:click="items.push({ item_type: 'service', description: '', quantity: 1, unit_price: 0 })"
                class="text-sm font-medium text-blue-600 hover:underline">+ Add another item</button>
    </div>

    <div class="rounded-lg bg-blue-50 px-4 py-3 text-sm text-blue-700">
        Total: <span class="font-semibold" x-text="'$' + total()"></span>
    </div>
</form>
<div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4">
    <x-button variant="secondary" x-on:click="show = false">Cancel</x-button>
    <x-button variant="primary" type="submit" form="bill-form">Create Bill</x-button>
</div>
