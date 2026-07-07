@extends('layouts.app')
@section('content')

<div x-data="{
        async openModal(url) {
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const body = document.getElementById('medical-modal-body');
            body.innerHTML = await res.text();
            window.Alpine.initTree(body);
            window.dispatchEvent(new CustomEvent('open-modal', { detail: 'medical-modal' }));
        }
     }"
     x-init="@if($errors->any() && old('_modal_target'))openModal(@js(old('_modal_target')))@elseif(session('reopen_record'))openModal(@js('/medical-records/' . session('reopen_record')))@endif"
>
    <x-page-header title="Medical Records" :subtitle="$records->count() . ' records total'">
        <x-slot:actions>
            @can('medical_record.create')
                <x-button variant="primary" x-on:click="openModal('/medical-records/create')"><x-icon name="plus" class="h-4 w-4" /> Add Medical Record</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 flex flex-wrap items-center gap-2">
        <form method="get" action="/medical-records" class="relative flex-1 min-w-[240px]">
            <input type="hidden" name="doctor_id" value="{{ $doctorId }}">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input type="text" name="q" value="{{ $q }}" placeholder="Search by medical record ID, patient ID, patient name, or doctor name..."
                   class="w-full rounded-lg border border-slate-200 bg-white py-2.5 pl-9 pr-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </form>
        <select onchange="location.href='/medical-records?q={{ urlencode($q) }}&doctor_id='+this.value" class="rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm">
            <option value="">All Doctors</option>
            @foreach($doctors as $d)
                <option value="{{ $d->doctor_id }}" @selected($doctorId === $d->doctor_id)>{{ $d->name() }}</option>
            @endforeach
        </select>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
                    <th class="px-4 py-3">Medical Record ID</th>
                    <th class="px-4 py-3">Patient ID</th>
                    <th class="px-4 py-3">Patient Name</th>
                    <th class="px-4 py-3">Doctor</th>
                    <th class="px-4 py-3">Diagnosis</th>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Version</th>
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
                    <td class="px-4 py-3 text-slate-600">{{ \Illuminate\Support\Str::limit($r->diagnosis, 40) }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $r->created_at }}</td>
                    <td class="px-4 py-3">
                        <span class="text-xs font-semibold {{ $v > 1 ? 'text-amber-600' : 'text-slate-400' }}">{{ $v > 1 ? "v$v" : 'v1 — Original' }}</span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex justify-end gap-2">
                            <button type="button" x-on:click="openModal('/medical-records/{{ $r->medical_record_id }}')" class="text-slate-400 hover:text-blue-600">
                                <x-icon name="eye" class="h-4 w-4" />
                            </button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="px-4 py-8 text-center text-slate-400">No records.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <x-modal name="medical-modal" max-width="2xl">
        <div id="medical-modal-body"></div>
    </x-modal>
</div>
@endsection
