@extends('layouts.app')
@section('content')

@php
$filters = ['all' => 'All', 'unpaid' => 'Unpaid', 'partially_paid' => 'Partially Paid', 'paid' => 'Paid'];
@endphp

<div x-data="{
        async openModal(url) {
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const body = document.getElementById('billing-modal-body');
            body.innerHTML = await res.text();
            window.Alpine.initTree(body);
            window.dispatchEvent(new CustomEvent('open-modal', { detail: 'billing-modal' }));
        }
     }"
     x-init="@if($errors->any() && old('_modal_target'))openModal(@js(old('_modal_target')))@elseif(session('reopen_bill'))openModal(@js('/bills/' . session('reopen_bill')))@endif"
>
    <x-page-header title="Billing &amp; Payments">
        <x-slot:actions>
            @can('bill.create')
                <x-button variant="primary" x-on:click="openModal('/bills/create')"><x-icon name="plus" class="h-4 w-4" /> Create Bill</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-stat-card label="Total Bill Amount" value="${{ number_format($stats['total_amount'], 2) }}" icon="card" icon-color="blue" />
        <x-stat-card label="Unpaid Bills" :value="$stats['unpaid']" icon="clipboard" icon-color="amber" />
        <x-stat-card label="Partially Paid Bills" :value="$stats['partially_paid']" icon="clipboard" icon-color="purple" />
        <x-stat-card label="Paid Bills" :value="$stats['paid']" icon="clipboard" icon-color="green" />
    </div>

    <div class="mb-4 flex flex-wrap gap-1.5">
        @foreach($filters as $value => $label)
            <a href="/bills?status={{ $value }}"
               class="rounded-lg px-3 py-2 text-sm font-medium {{ $status === $value ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 border border-slate-200' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <p class="mb-2 text-sm font-semibold text-slate-700">Billing Records</p>
    <div class="mb-6 overflow-hidden rounded-xl border border-slate-200 bg-white">
        <table class="w-full text-sm">
            <thead><tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
                <th class="px-4 py-3">Bill ID</th><th class="px-4 py-3">Patient</th><th class="px-4 py-3">Bill Date</th><th class="px-4 py-3">Total Amount</th><th class="px-4 py-3">Paid Amount</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Actions</th>
            </tr></thead>
            <tbody>
            @forelse($bills as $b)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-4 py-3 font-medium text-blue-600">{{ $b->bill_id }}</td>
                    <td class="px-4 py-3 text-slate-900">{{ $b->patient_name }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $b->bill_date }}</td>
                    <td class="px-4 py-3 text-slate-600">${{ number_format((float) $b->total_amount, 2) }}</td>
                    <td class="px-4 py-3 text-slate-600">${{ number_format((float) $b->paid_amount, 2) }}</td>
                    <td class="px-4 py-3"><x-badge :status="$b->status" /></td>
                    <td class="px-4 py-3">
                        <div class="flex justify-end gap-2">
                            <button type="button" x-on:click="openModal('/bills/{{ $b->bill_id }}')" class="text-slate-400 hover:text-blue-600"><x-icon name="eye" class="h-4 w-4" /></button>
                            @if(auth()->user()->hasPermission('payment.create') && $b->status !== 'paid')
                                <button type="button" x-on:click="openModal('/bills/{{ $b->bill_id }}/pay')" class="text-xs font-medium text-green-600 hover:underline">Pay</button>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">No bills.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <p class="mb-2 text-sm font-semibold text-slate-700">Payment History</p>
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <table class="w-full text-sm">
            <thead><tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
                <th class="px-4 py-3">Payment ID</th><th class="px-4 py-3">Bill ID</th><th class="px-4 py-3">Patient</th><th class="px-4 py-3">Method</th><th class="px-4 py-3">Amount</th><th class="px-4 py-3">Date</th><th class="px-4 py-3">Reference</th>
            </tr></thead>
            <tbody>
            @forelse($payments as $p)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-4 py-3 font-medium text-purple-600">{{ $p->payment_id }}</td>
                    <td class="px-4 py-3 text-blue-600">{{ $p->bill_id }}</td>
                    <td class="px-4 py-3 text-slate-900">{{ $p->patient_name }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ ucfirst($p->payment_method) }}</td>
                    <td class="px-4 py-3 font-medium text-green-600">${{ number_format((float) $p->amount_paid, 2) }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $p->payment_date }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $p->transaction_reference ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">No payments yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <x-modal name="billing-modal" max-width="2xl">
        <div id="billing-modal-body"></div>
    </x-modal>
</div>
@endsection
