@extends('layouts.app')
@section('content')

<div x-data="{
        async openModal(url, action = null) {
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const body = document.getElementById('medical-modal-body');
            body.innerHTML = await res.text();
            window.Alpine.initTree(body);
            window.dispatchEvent(new CustomEvent('open-modal', { detail: 'medical-modal' }));
            if (action === 'adjust') {
                setTimeout(() => window.dispatchEvent(new CustomEvent('open-modal', { detail: 'adjust-record' })), 50);
            }
        }
     }"
     x-init="@if($errors->any() && old('_modal_target'))openModal(@js(old('_modal_target')))@elseif(session('reopen_record'))openModal(@js('/medical-records/' . session('reopen_record')))@endif"
>
    <x-page-header title="Medical Records" :subtitle="$records->total() . ' records total'">
        <x-slot:actions>
            @can('medical_record.create')
                <x-button variant="primary" x-on:click="openModal('/medical-records/create')"><x-icon name="plus" class="h-4 w-4" /> Add Medical Record</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 flex flex-wrap items-center gap-2">
        <form method="get" action="/medical-records" class="relative flex-1 min-w-[240px]">
            <input type="hidden" name="doctor_id" value="{{ $doctorId }}">
            <input type="hidden" name="date" value="{{ $date }}">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input type="text" name="q" value="{{ $q }}" placeholder="Search by medical record ID, patient ID, patient name, or doctor name..."
                   class="w-full rounded-lg border border-manila/60 bg-paper-card py-2.5 pl-9 pr-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </form>
        @if($q || $doctorId || $date)
            <a href="/medical-records" title="Clear filters" class="rounded-lg border border-manila/60 bg-paper-card p-2.5 text-slate-400 hover:text-slate-600">
                <x-icon name="x" class="h-4 w-4" />
            </a>
        @endif
        <select onchange="location.href='/medical-records?q={{ urlencode($q) }}&date={{ urlencode($date ?? '') }}&doctor_id='+this.value" class="rounded-lg border border-manila/60 bg-paper-card px-3 py-2.5 text-sm">
            <option value="">All Doctors</option>
            @foreach($doctors as $d)
                <option value="{{ $d->doctor_id }}" @selected($doctorId === $d->doctor_id)>{{ $d->name() }}</option>
            @endforeach
        </select>
        <input type="date" value="{{ $date }}"
               onchange="location.href='/medical-records?q={{ urlencode($q) }}&doctor_id={{ urlencode($doctorId ?? '') }}&date='+this.value"
               class="rounded-lg border border-manila/60 bg-paper-card px-3 py-2.5 text-sm">
    </div>

    <div class="overflow-x-auto rounded-xl border border-manila/60 bg-paper-card">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
                    <th class="px-4 py-3">Medical Record ID</th>
                    <th class="px-4 py-3">Patient ID</th>
                    <th class="px-4 py-3">Patient Name</th>
                    <th class="px-4 py-3">Doctor</th>
                    <th class="px-4 py-3">Related Appointment</th>
                    <th class="px-4 py-3">Diagnosis Summary</th>
                    <th class="px-4 py-3">Created By</th>
                    <th class="px-4 py-3">Created At</th>
                    <th class="px-4 py-3">Latest Version</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($records as $r)
                @php($v = $versionCounts[$r->medical_record_id] ?? 1)
                <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50/50">
                    <td class="px-4 py-3 font-medium text-purple-600">{{ $r->medical_record_id }}</td>
                    <td class="px-4 py-3 text-blue-600">{{ $r->patient_id }}</td>
                    <td class="px-4 py-3 text-slate-900">{{ $r->patient_name }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $r->doctor_name }}</td>
                    <td class="px-4 py-3 text-teal-600">{{ $r->appointment_id ?? '—' }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ \Illuminate\Support\Str::limit($r->diagnosis, 40) }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $r->created_by_name ?? $r->created_by ?? '—' }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $r->created_at }}</td>
                    <td class="px-4 py-3">
                        <span class="text-xs font-semibold {{ $v > 1 ? 'text-amber-600' : 'text-slate-400' }}">{{ $v > 1 ? "v$v" : 'v1 — Original' }}</span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex justify-end items-center gap-3">
                            <button type="button" x-on:click="openModal('/medical-records/{{ $r->medical_record_id }}')" title="View" class="text-slate-400 hover:text-blue-600">
                                <x-icon name="eye" class="h-4 w-4" />
                            </button>
                            @can('medical_record.adjust')
                                <button type="button" x-on:click="openModal('/medical-records/{{ $r->medical_record_id }}', 'adjust')" title="Adjust" class="text-slate-400 hover:text-blue-600">
                                    <x-icon name="pencil" class="h-4 w-4" />
                                </button>
                            @endcan
                            <button type="button" x-on:click="openModal('/medical-records/{{ $r->medical_record_id }}/history')" title="History" class="flex items-center gap-1 text-slate-400 hover:text-blue-600">
                                <x-icon name="clock" class="h-4 w-4" /> History
                            </button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="px-4 py-8 text-center text-slate-400">No records.</td></tr>
            @endforelse
            </tbody>
        </table>
        <x-pagination :paginator="$records" />
    </div>

    <x-modal name="medical-modal" max-width="2xl">
        <div id="medical-modal-body"></div>
    </x-modal>
</div>
@endsection
