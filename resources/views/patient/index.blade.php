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
    <x-page-header title="Patient Management" :subtitle="$patients->total() . ' total patients registered'">
        <x-slot:actions>
            <x-button variant="secondary" :href="'/patients/export?' . http_build_query(['q' => $q, 'status' => $status])"><x-icon name="document" class="h-4 w-4" /> Export</x-button>
            @can('patient.create')
                <x-button variant="primary" x-on:click="openPatientModal('/patients/create')"><x-icon name="plus" class="h-4 w-4" /> Add Patient</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 flex flex-wrap items-center gap-2">
        <form method="get" action="/patients" class="relative flex-1 min-w-[240px]">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input type="text" name="q" value="{{ $q }}" placeholder="Search by name or patient ID..."
                   class="w-full rounded-lg border border-manila/60 bg-paper-card py-2.5 pl-9 pr-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
            <input type="hidden" name="status" value="{{ $status }}">
        </form>
        <div class="flex flex-wrap gap-1.5">
            @foreach($filters as $value => $label)
                <a href="/patients?status={{ $value }}&q={{ urlencode($q) }}"
                   class="rounded-lg px-3 py-2 text-sm font-medium {{ $status === $value ? 'bg-blue-600 text-white shadow-well' : 'bg-paper-card text-slate-600 border border-manila/60 shadow-emboss' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    {{-- Mobile: stacked cards (the 8-column table is unusable at phone widths) --}}
    <div class="divide-y divide-slate-100 rounded-xl border border-manila/60 bg-paper-card sm:hidden">
        @forelse($patients as $p)
            @php($age = $p->date_of_birth ? \Carbon\Carbon::parse($p->date_of_birth)->age : null)
            <div class="p-4">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex min-w-0 items-center gap-2.5">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-semibold text-blue-700">
                            {{ strtoupper(substr($p->first_name, 0, 1) . substr($p->last_name, 0, 1)) }}
                        </div>
                        <div class="min-w-0">
                            <div class="truncate font-medium text-slate-900">{{ $p->fullName() }}</div>
                            <div class="text-xs font-medium text-blue-600">{{ $p->patient_id }}</div>
                        </div>
                    </div>
                    <x-badge :status="$p->patient_status" />
                </div>
                <div class="mt-3 grid grid-cols-2 gap-x-3 gap-y-1.5 text-sm">
                    <div><span class="text-slate-400">Gender</span> <span class="text-slate-700">{{ ucfirst($p->gender ?? '—') }}</span></div>
                    <div><span class="text-slate-400">Blood</span> <span class="font-semibold text-red-600">{{ $p->blood_type ?: '—' }}</span></div>
                    <div class="col-span-2"><span class="text-slate-400">DOB</span> <span class="text-slate-700">{{ $p->date_of_birth ?? '—' }}</span>{{ $age !== null ? " · {$age} yrs" : '' }}</div>
                    <div class="col-span-2"><span class="text-slate-400">Phone</span> <span class="text-slate-700">{{ $p->phone_number ?: '—' }}</span></div>
                </div>
                <div class="mt-3 flex items-center justify-end gap-4 border-t border-slate-50 pt-3">
                    <button type="button" x-on:click="openPatientModal('/patients/{{ $p->patient_id }}')" class="flex items-center gap-1.5 text-sm font-medium text-slate-500">
                        <x-icon name="eye" class="h-4 w-4" /> View
                    </button>
                    @can('patient.update')
                        <button type="button" x-on:click="openPatientModal('/patients/{{ $p->patient_id }}/edit')" class="flex items-center gap-1.5 text-sm font-medium text-slate-500">
                            <x-icon name="pencil" class="h-4 w-4" /> Edit
                        </button>
                    @endcan
                </div>
            </div>
        @empty
            <div class="p-8 text-center text-slate-400">No patients found.</div>
        @endforelse
        <x-pagination :paginator="$patients" />
    </div>

    {{-- sm+: full table --}}
    <div class="hidden overflow-x-auto rounded-xl border border-manila/60 bg-paper-card sm:block">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
                    <th class="px-4 py-3">Patient ID</th>
                    <th class="px-4 py-3">Patient Name</th>
                    <th class="px-4 py-3">Gender</th>
                    <th class="px-4 py-3">Date of Birth / Age</th>
                    <th class="px-4 py-3">Phone</th>
                    <th class="px-4 py-3">Blood</th>
                    <th class="px-4 py-3">Insurance</th>
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
                    <td class="px-4 py-3">
                        <div class="text-slate-900">{{ $p->date_of_birth ?? '—' }}</div>
                        @if($age !== null)
                            <div class="text-xs text-slate-400">{{ $age }} yrs</div>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-slate-600">{{ $p->phone_number ?: '—' }}</td>
                    <td class="px-4 py-3 font-semibold text-red-600">{{ $p->blood_type ?: '—' }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $p->insurance_provider ?: '—' }}</td>
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
                <tr><td colspan="9" class="px-4 py-8 text-center text-slate-400">No patients found.</td></tr>
            @endforelse
            </tbody>
        </table>
        <x-pagination :paginator="$patients" />
    </div>

    <x-modal name="patient-modal" max-width="5xl">
        <div id="patient-modal-body"></div>
    </x-modal>
</div>
@endsection
