@extends('layouts.app')
@section('content')

<div x-data="{
        tab: 'orders',
        async openModal(url) {
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const body = document.getElementById('lab-modal-body');
            body.innerHTML = await res.text();
            window.Alpine.initTree(body);
            window.dispatchEvent(new CustomEvent('open-modal', { detail: 'lab-modal' }));
        }
     }"
     x-init="@if($errors->any() && old('_modal_target'))openModal(@js(old('_modal_target')))@endif"
>
    <x-page-header title="Laboratory &amp; Diagnostics">
        <x-slot:actions>
            <template x-if="tab === 'orders'">
                @can('lab_order.create')
                    <x-button variant="primary" x-on:click="openModal('/lab-orders/create')"><x-icon name="plus" class="h-4 w-4" /> Order Lab Test</x-button>
                @endcan
            </template>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-stat-card label="Pending Test Orders" :value="$stats['pending']" icon="clipboard" icon-color="amber" />
        <x-stat-card label="In Progress Tests" :value="$stats['in_progress']" icon="flask" icon-color="blue" />
        <x-stat-card label="Completed Tests" :value="$stats['completed']" icon="clipboard" icon-color="green" />
        <x-stat-card label="Pending Result Entry" :value="$stats['pending_results']" icon="document" icon-color="purple" />
    </div>

    <div class="mb-4 flex flex-wrap gap-1.5">
        <button type="button" @click="tab = 'orders'" :class="tab === 'orders' ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 border border-slate-200'" class="rounded-lg px-3.5 py-2 text-sm font-medium">Lab Test Orders</button>
        <button type="button" @click="tab = 'results'" :class="tab === 'results' ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 border border-slate-200'" class="rounded-lg px-3.5 py-2 text-sm font-medium">Test Results</button>
        <button type="button" @click="tab = 'reports'" :class="tab === 'reports' ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 border border-slate-200'" class="rounded-lg px-3.5 py-2 text-sm font-medium">Lab Reports</button>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        {{-- Lab Test Orders --}}
        <div x-show="tab === 'orders'">
            <table class="w-full text-sm">
                <thead><tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
                    <th class="px-4 py-3">Order ID</th><th class="px-4 py-3">Patient</th><th class="px-4 py-3">Test Type</th><th class="px-4 py-3">Ordered By</th><th class="px-4 py-3">Technician</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Actions</th>
                </tr></thead>
                <tbody>
                @forelse($orders as $o)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-4 py-3 font-medium text-blue-600">{{ $o->test_order_id }}</td>
                        <td class="px-4 py-3 text-slate-900">{{ $o->patient_name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $o->test_name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $o->doctor_name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $o->technician_name ?: '—' }}</td>
                        <td class="px-4 py-3"><x-badge :status="$o->status" /></td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <button type="button" x-on:click="openModal('/lab-orders/{{ $o->test_order_id }}')" class="text-slate-400 hover:text-blue-600"><x-icon name="eye" class="h-4 w-4" /></button>
                                @can('lab_order.update')
                                    @if($o->status !== 'completed')
                                        <button type="button" x-on:click="openModal('/lab-orders/{{ $o->test_order_id }}/status')" class="text-slate-400 hover:text-blue-600"><x-icon name="pencil" class="h-4 w-4" /></button>
                                    @endif
                                @endcan
                                @can('lab_result.create')
                                    @if($o->status !== 'completed')
                                        <button type="button" x-on:click="openModal('/lab-results/create/{{ $o->test_order_id }}')" class="text-slate-400 hover:text-green-600"><x-icon name="document" class="h-4 w-4" /></button>
                                    @endif
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">No lab orders.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- Test Results --}}
        <div x-show="tab === 'results'">
            <table class="w-full text-sm">
                <thead><tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
                    <th class="px-4 py-3">Result ID</th><th class="px-4 py-3">Order ID</th><th class="px-4 py-3">Patient</th><th class="px-4 py-3">Test Type</th><th class="px-4 py-3">Result Value</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Entered By</th><th class="px-4 py-3 text-right">Actions</th>
                </tr></thead>
                <tbody>
                @forelse($results as $r)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-4 py-3 font-medium text-green-600">{{ $r->test_result_id }}</td>
                        <td class="px-4 py-3 text-blue-600">{{ $r->test_order_id }}</td>
                        <td class="px-4 py-3 text-slate-900">{{ $r->patient_name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $r->test_name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ \Illuminate\Support\Str::limit($r->result_value, 30) }}</td>
                        <td class="px-4 py-3"><x-badge :status="$r->result_status" /></td>
                        <td class="px-4 py-3 text-slate-600">{{ $r->technician_name ?: '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <button type="button" x-on:click="openModal('/lab-results/{{ $r->test_result_id }}')" class="text-slate-400 hover:text-blue-600"><x-icon name="eye" class="h-4 w-4" /></button>
                                @can('lab_result.create')
                                    <button type="button" x-on:click="openModal('/lab-results/{{ $r->test_result_id }}/edit')" class="text-slate-400 hover:text-blue-600"><x-icon name="pencil" class="h-4 w-4" /></button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-slate-400">No results.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- Lab Reports --}}
        <div x-show="tab === 'reports'">
            <table class="w-full text-sm">
                <thead><tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
                    <th class="px-4 py-3">Report ID</th><th class="px-4 py-3">Order ID</th><th class="px-4 py-3">Patient</th><th class="px-4 py-3">Test Type</th><th class="px-4 py-3">Generated By</th><th class="px-4 py-3">Generated At</th>
                </tr></thead>
                <tbody>
                @forelse($reports as $r)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-4 py-3 font-medium text-purple-600">{{ $r->lab_report_id }}</td>
                        <td class="px-4 py-3 text-blue-600">{{ $r->test_order_id }}</td>
                        <td class="px-4 py-3 text-slate-900">{{ $r->patient_name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $r->test_name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $r->generated_by_name ?: '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $r->generated_at }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">No lab reports generated yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <x-modal name="lab-modal" max-width="lg">
        <div id="lab-modal-body"></div>
    </x-modal>
</div>
@endsection
