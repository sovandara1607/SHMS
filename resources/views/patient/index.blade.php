@extends('layouts.app')
@section('content')

@php
$filters = ['all' => 'All', 'active' => 'Active', 'admitted' => 'Admitted', 'icu' => 'ICU', 'discharged' => 'Discharged', 'inactive' => 'Inactive'];
@endphp

<div x-data="{
        async openPatientModal(url) {
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const body = document.getElementById('patient-modal-body');
            body.innerHTML = await res.text();
            window.Alpine.initTree(body);
            window.dispatchEvent(new CustomEvent('open-modal', { detail: 'patient-modal' }));
        }
     }"
     x-init="@if($errors->any() && old('_modal_target'))openPatientModal(@js(old('_modal_target')))@elseif(session('reopen_patient'))openPatientModal(@js('/patients/' . session('reopen_patient')))@endif"
>
    <x-page-header title="Patient Management" :subtitle="$patients->count() . ' total patients registered'">
        <x-slot:actions>
            <x-button variant="secondary"><x-icon name="document" class="h-4 w-4" /> Export</x-button>
            @can('patient.create')
                <x-button variant="primary" x-on:click="openPatientModal('/patients/create')"><x-icon name="plus" class="h-4 w-4" /> Add Patient</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 flex flex-wrap items-center gap-2">
        <form method="get" action="/patients" class="relative flex-1 min-w-[240px]">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input type="text" name="q" value="{{ $q }}" placeholder="Search by name or patient ID..."
                   class="w-full rounded-lg border border-slate-200 bg-white py-2.5 pl-9 pr-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
            <input type="hidden" name="status" value="{{ $status }}">
        </form>
        <div class="flex flex-wrap gap-1.5">
            @foreach($filters as $value => $label)
                <a href="/patients?status={{ $value }}&q={{ urlencode($q) }}"
                   class="rounded-lg px-3 py-2 text-sm font-medium {{ $status === $value ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 border border-slate-200' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
                    <th class="px-4 py-3">Patient ID</th>
                    <th class="px-4 py-3">Patient Name</th>
                    <th class="px-4 py-3">Gender</th>
                    <th class="px-4 py-3">Date of Birth / Age</th>
                    <th class="px-4 py-3">Phone</th>
                    <th class="px-4 py-3">Blood</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($patients as $p)
                @php($age = $p->date_of_birth ? \Carbon\Carbon::parse($p->date_of_birth)->age : null)
                <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50/50">
                    <td class="px-4 py-3 font-medium text-blue-600">{{ $p->patient_id }}</td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2.5">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 text-xs font-semibold text-blue-700">
                                {{ strtoupper(substr($p->first_name, 0, 1) . substr($p->last_name, 0, 1)) }}
                            </div>
                            <div>
                                <div class="font-medium text-slate-900">{{ $p->fullName() }}</div>
                                <div class="text-xs text-slate-400">{{ $p->email }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-slate-600">{{ ucfirst($p->gender ?? '—') }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $p->date_of_birth ?? '—' }}{{ $age !== null ? " ($age yrs)" : '' }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $p->phone_number ?: '—' }}</td>
                    <td class="px-4 py-3 font-semibold text-red-600">{{ $p->blood_type ?: '—' }}</td>
                    <td class="px-4 py-3"><x-badge :status="$p->patient_status" /></td>
                    <td class="px-4 py-3">
                        <div class="flex justify-end gap-2">
                            <button type="button" x-on:click="openPatientModal('/patients/{{ $p->patient_id }}')" class="text-slate-400 hover:text-blue-600">
                                <x-icon name="eye" class="h-4 w-4" />
                            </button>
                            @can('patient.update')
                                <button type="button" x-on:click="openPatientModal('/patients/{{ $p->patient_id }}/edit')" class="text-slate-400 hover:text-blue-600">
                                    <x-icon name="pencil" class="h-4 w-4" />
                                </button>
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="px-4 py-8 text-center text-slate-400">No patients found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <x-modal name="patient-modal" max-width="3xl">
        <div id="patient-modal-body"></div>
    </x-modal>
</div>
@endsection
