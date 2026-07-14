@extends('layouts.app')
@section('content')

<div x-data="{
        tab: 'inventory',
        async openModal(url) {
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const body = document.getElementById('pharmacy-modal-body');
            body.innerHTML = await res.text();
            window.Alpine.initTree(body);
            window.dispatchEvent(new CustomEvent('open-modal', { detail: 'pharmacy-modal' }));
        }
     }"
     x-init="@if($errors->any() && old('_modal_target'))openModal(@js(old('_modal_target')))@endif"
>
    <x-page-header title="Pharmacy & Inventory">
        <x-slot:actions>
            <template x-if="tab === 'inventory'">
                @can('medicine.create')
                    <x-button variant="primary" x-on:click="openModal('/medicines/create')"><x-icon name="plus" class="h-4 w-4" /> Add Medicine</x-button>
                @endcan
            </template>
            <template x-if="tab === 'batches'">
                @can('medicine_batch.create')
                    <x-button variant="primary" x-on:click="openModal('/medicine-batches/create')"><x-icon name="plus" class="h-4 w-4" /> Add Batch</x-button>
                @endcan
            </template>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-stat-card label="Total Medicines" :value="$stats['total']" icon="pill" icon-color="blue" />
        <x-stat-card label="Available Medicines" :value="$stats['available']" icon="pill" icon-color="green" />
        <x-stat-card label="Low Stock Medicines" :value="$stats['low_stock']" icon="clipboard" icon-color="amber" />
        <x-stat-card label="Expired Batches" :value="$stats['expired_batches']" icon="x" icon-color="red" />
    </div>

    <div class="mb-4 flex flex-wrap gap-1.5">
        <button type="button" @click="tab = 'inventory'" :class="tab === 'inventory' ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 border border-slate-200'" class="rounded-lg px-3.5 py-2 text-sm font-medium">Medicine Inventory</button>
        <button type="button" @click="tab = 'batches'" :class="tab === 'batches' ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 border border-slate-200'" class="rounded-lg px-3.5 py-2 text-sm font-medium">Medicine Batches</button>
        <button type="button" @click="tab = 'prescriptions'" :class="tab === 'prescriptions' ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 border border-slate-200'" class="rounded-lg px-3.5 py-2 text-sm font-medium">Prescriptions</button>
        <button type="button" @click="tab = 'dispensing'" :class="tab === 'dispensing' ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 border border-slate-200'" class="rounded-lg px-3.5 py-2 text-sm font-medium">Dispensing Records</button>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        {{-- Medicine Inventory --}}
        <div x-show="tab === 'inventory'">
            <table class="w-full text-sm">
                <thead><tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
                    <th class="px-4 py-3">Medicine ID</th><th class="px-4 py-3">Name</th><th class="px-4 py-3">Type</th><th class="px-4 py-3">Manufacturer</th><th class="px-4 py-3">Unit Price</th><th class="px-4 py-3">Stock</th><th class="px-4 py-3">Status</th>
                </tr></thead>
                <tbody>
                @forelse($medicines as $m)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-4 py-3 font-medium text-blue-600">{{ $m->medicine_id }}</td>
                        <td class="px-4 py-3 text-slate-900">{{ $m->medicine_name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $m->medicine_type ?: '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $m->manufacturer ?: '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">${{ number_format($m->unit_price ?? 0, 2) }}</td>
                        <td class="px-4 py-3 {{ $m->stock_quantity <= 20 ? 'font-semibold text-amber-600' : 'text-slate-600' }}">{{ $m->stock_quantity }}</td>
                        <td class="px-4 py-3"><x-badge :status="$m->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">No medicines.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- Medicine Batches --}}
        <div x-show="tab === 'batches'">
            <table class="w-full text-sm">
                <thead><tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
                    <th class="px-4 py-3">Batch ID</th><th class="px-4 py-3">Medicine</th><th class="px-4 py-3">Batch #</th><th class="px-4 py-3">Manufacture</th><th class="px-4 py-3">Expiry</th><th class="px-4 py-3">Quantity</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Actions</th>
                </tr></thead>
                <tbody>
                @forelse($batches as $b)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-4 py-3 font-medium text-purple-600">{{ $b->batch_id }}</td>
                        <td class="px-4 py-3 text-slate-900">{{ $b->medicine_name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $b->batch_number ?: '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $b->manufacture_date ?: '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $b->expiry_date ?: '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $b->quantity }}</td>
                        <td class="px-4 py-3"><x-badge :status="$b->status" /></td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <button type="button" x-on:click="openModal('/medicine-batches/{{ $b->batch_id }}')" class="text-slate-400 hover:text-blue-600"><x-icon name="eye" class="h-4 w-4" /></button>
                                @can('medicine_batch.update')
                                    <button type="button" x-on:click="openModal('/medicine-batches/{{ $b->batch_id }}/edit')" class="text-slate-400 hover:text-blue-600"><x-icon name="pencil" class="h-4 w-4" /></button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-slate-400">No batches.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- Prescriptions --}}
        <div x-show="tab === 'prescriptions'">
            <table class="w-full text-sm">
                <thead><tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
                    <th class="px-4 py-3">Prescription ID</th><th class="px-4 py-3">Patient</th><th class="px-4 py-3">Doctor</th><th class="px-4 py-3">Medical Record</th><th class="px-4 py-3">Date</th><th class="px-4 py-3">Notes</th><th class="px-4 py-3 text-right">Actions</th>
                </tr></thead>
                <tbody>
                @forelse($prescriptions as $p)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-4 py-3 font-medium text-purple-600">{{ $p->prescription_id }}</td>
                        <td class="px-4 py-3 text-slate-900">{{ $p->patient_name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $p->doctor_name }}</td>
                        <td class="px-4 py-3 text-blue-600">{{ $p->medical_record_id }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $p->prescription_date }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ \Illuminate\Support\Str::limit($p->notes, 30) }}</td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <button type="button" x-on:click="openModal('/prescriptions/{{ $p->prescription_id }}')" class="text-slate-400 hover:text-blue-600"><x-icon name="eye" class="h-4 w-4" /></button>
                                @can('dispensing.create')
                                    <button type="button" x-on:click="openModal('/prescriptions/{{ $p->prescription_id }}/dispense')" class="text-xs font-medium text-blue-600 hover:underline">Dispense</button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">No prescriptions.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- Dispensing Records --}}
        <div x-show="tab === 'dispensing'">
            <table class="w-full text-sm">
                <thead><tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
                    <th class="px-4 py-3">Dispensing ID</th><th class="px-4 py-3">Prescription</th><th class="px-4 py-3">Patient</th><th class="px-4 py-3">Pharmacist</th><th class="px-4 py-3">Date</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Actions</th>
                </tr></thead>
                <tbody>
                @forelse($dispensingRecords as $d)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-4 py-3 font-medium text-green-600">{{ $d->dispensing_id }}</td>
                        <td class="px-4 py-3 text-blue-600">{{ $d->prescription_id }}</td>
                        <td class="px-4 py-3 text-slate-900">{{ $d->patient_name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $d->pharmacist_name ?: '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $d->dispensing_date }}</td>
                        <td class="px-4 py-3"><x-badge :status="$d->status" /></td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" x-on:click="openModal('/dispensing/{{ $d->dispensing_id }}')" class="text-slate-400 hover:text-blue-600"><x-icon name="eye" class="h-4 w-4" /></button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">No dispensing records.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <x-modal name="pharmacy-modal" max-width="lg">
        <div id="pharmacy-modal-body"></div>
    </x-modal>
</div>
@endsection
