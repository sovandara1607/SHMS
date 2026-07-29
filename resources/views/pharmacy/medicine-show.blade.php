<x-modal-header title="Medicine Details" :subtitle="$medicine->medicine_id" />

<div class="px-6 py-5">
    <div class="mb-4 flex items-center gap-3">
        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-blue-50">
            <x-icon name="pill" class="h-5 w-5 text-blue-600" />
        </div>
        <div>
            <p class="font-semibold text-slate-900">{{ $medicine->medicine_name }}</p>
            <p class="text-sm text-slate-400">{{ $medicine->medicine_type ?: '—' }}</p>
        </div>
    </div>
    <div class="grid grid-cols-2 gap-y-3 text-sm">
        <div><p class="text-xs text-slate-500">Manufacturer</p><p class="text-slate-900">{{ $medicine->manufacturer ?: '—' }}</p></div>
        <div><p class="text-xs text-slate-500">Unit Price</p><p class="text-slate-900">${{ number_format($medicine->unit_price ?? 0, 2) }}</p></div>
        <div><p class="text-xs text-slate-500">Stock Quantity</p><p class="text-slate-900">{{ $medicine->stock_quantity }}</p></div>
        <div><p class="text-xs text-slate-500">Status</p><p class="mt-0.5"><x-badge :status="$medicine->status" /></p></div>
    </div>
</div>
<div class="flex justify-end border-t border-slate-100 px-6 py-4">
    <x-button variant="secondary" x-on:click="show = false">Close</x-button>
</div>
