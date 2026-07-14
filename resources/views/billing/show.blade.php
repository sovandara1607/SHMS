<x-modal-header :title="$bill->bill_id" :subtitle="$bill->patient?->fullName()" />
<div class="max-h-[65vh] overflow-y-auto px-6 py-5">
    <div class="mb-4 grid grid-cols-4 gap-3 text-sm">
        <div class="rounded-lg bg-slate-50 px-3 py-2"><p class="text-xs text-slate-500">Total</p><p class="font-semibold text-slate-900">${{ number_format((float) $bill->total_amount, 2) }}</p></div>
        <div class="rounded-lg bg-slate-50 px-3 py-2"><p class="text-xs text-slate-500">Paid</p><p class="font-semibold text-green-600">${{ number_format($paid, 2) }}</p></div>
        <div class="rounded-lg bg-slate-50 px-3 py-2"><p class="text-xs text-slate-500">Balance</p><p class="font-semibold text-red-600">${{ number_format($balance, 2) }}</p></div>
        <div class="rounded-lg bg-slate-50 px-3 py-2"><p class="text-xs text-slate-500">Status</p><x-badge :status="$bill->status" /></div>
    </div>

    <div class="mb-2 flex items-center justify-between">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Bill Items</p>
        @can('bill.update')
            <button type="button" class="text-xs font-medium text-blue-600 hover:underline" x-on:click="openModal('/bills/{{ $bill->bill_id }}/items/create')">+ Add Item</button>
        @endcan
    </div>
    <div class="overflow-x-auto">
        <table class="mb-4 w-full text-sm">
            <thead><tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
                <th class="pb-2">Type</th><th class="pb-2">Description</th><th class="pb-2">Qty</th><th class="pb-2">Unit</th><th class="pb-2 text-right">Subtotal</th>
            </tr></thead>
            <tbody>
            @forelse($bill->items as $i)
                <tr class="border-b border-slate-50">
                    <td class="py-2">{{ ucfirst(str_replace('_', ' ', $i->item_type)) }}</td>
                    <td class="py-2">{{ $i->description ?: '—' }}</td>
                    <td class="py-2">{{ $i->quantity }}</td>
                    <td class="py-2">${{ number_format((float) $i->unit_price, 2) }}</td>
                    <td class="py-2 text-right">${{ number_format((float) $i->subtotal, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-3 text-center text-slate-400">No items.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Payments</p>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
                <th class="pb-2">Payment</th><th class="pb-2">Method</th><th class="pb-2">Amount</th><th class="pb-2">Date</th>
            </tr></thead>
            <tbody>
            @forelse($bill->payments as $p)
                <tr class="border-b border-slate-50">
                    <td class="py-2">{{ $p->payment_id }}</td>
                    <td class="py-2">{{ ucfirst($p->payment_method) }}</td>
                    <td class="py-2">${{ number_format((float) $p->amount_paid, 2) }}</td>
                    <td class="py-2">{{ $p->payment_date }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="py-3 text-center text-slate-400">No payments yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4">
    <x-button variant="secondary" x-on:click="show = false">Close</x-button>
    @if(auth()->user()->hasPermission('payment.create') && $bill->status !== 'paid')
        <x-button variant="success" x-on:click="openModal('/bills/{{ $bill->bill_id }}/pay')">Pay</x-button>
    @endif
</div>
