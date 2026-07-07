<x-modal-header title="Process Payment" :subtitle="$bill->bill_id . ' · ' . $bill->patient?->fullName()" />
<form id="pay-form" method="post" action="/bills/{{ $bill->bill_id }}/pay" class="px-6 py-5" x-data="{ method: 'cash' }">
    @csrf
    <input type="hidden" name="_modal_target" value="/bills/{{ $bill->bill_id }}/pay">
    <input type="hidden" name="payment_method" x-model="method">

    <dl class="mb-4 grid grid-cols-2 gap-y-1 text-sm">
        <dt class="text-slate-500">Total Amount</dt><dd class="text-right text-slate-900">${{ number_format((float) $bill->total_amount, 2) }}</dd>
        <dt class="text-slate-500">Paid Amount</dt><dd class="text-right text-green-600">${{ number_format($paid, 2) }}</dd>
        <dt class="text-slate-500">Remaining Amount</dt><dd class="text-right font-semibold text-red-600">${{ number_format($balance, 2) }}</dd>
    </dl>

    <label class="mb-1.5 block text-sm font-medium text-slate-700">Payment Method *</label>
    <div class="mb-4 grid grid-cols-3 gap-2">
        @foreach(['cash' => 'Cash', 'card' => 'Card', 'online' => 'Online'] as $value => $label)
            <button type="button" @click="method = '{{ $value }}'"
                    :class="method === '{{ $value }}' ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 border border-slate-200'"
                    class="rounded-lg px-3 py-2 text-sm font-medium">{{ $label }}</button>
        @endforeach
    </div>

    <div class="mb-4">
        <label class="mb-1.5 block text-sm font-medium text-slate-700">Amount Paid ($) *</label>
        <input type="number" step="0.01" name="amount_paid" required value="{{ old('amount_paid', $balance) }}"
               class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
    </div>
    <div class="mb-4">
        <label class="mb-1.5 block text-sm font-medium text-slate-700">Transaction Reference</label>
        <input name="transaction_reference" placeholder="Optional — auto-assigned if blank"
               class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
    </div>

    <div class="rounded-lg bg-blue-50 px-4 py-3 text-xs text-blue-700">
        <p class="font-semibold uppercase tracking-wider">System-Generated</p>
        <p class="mt-1">Received By: {{ auth()->user()->displayName() }}</p>
        <p>Payment Date: {{ now()->format('Y-m-d') }}</p>
    </div>

    <div class="mt-5 flex justify-end gap-2">
        <x-button variant="secondary" type="button" x-on:click="show = false">Cancel</x-button>
        <x-button variant="success" type="submit">Confirm Payment</x-button>
    </div>
</form>
