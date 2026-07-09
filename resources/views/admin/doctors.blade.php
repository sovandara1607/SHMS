@extends('layouts.app')
@section('content')

<div x-data="{
        async openModal(url) {
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const body = document.getElementById('doctor-modal-body');
            body.innerHTML = await res.text();
            window.Alpine.initTree(body);
            window.dispatchEvent(new CustomEvent('open-modal', { detail: 'doctor-modal' }));
        }
     }"
     x-init="@if($errors->any() && old('_modal_target'))openModal(@js(old('_modal_target')))@endif"
>
    <x-page-header title="Doctor Management">
        <x-slot:actions>
            @can('staff.manage')
                <x-button variant="primary" x-on:click="openModal('/staff/create?role=doctor')"><x-icon name="plus" class="h-4 w-4" /> Add Doctor</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <form method="get" action="/doctors" class="relative mb-4 max-w-md">
        <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
        <input type="text" name="q" value="{{ $q }}" placeholder="Search by name, ID, or specialization..."
               class="w-full rounded-lg border border-slate-200 bg-white py-2.5 pl-9 pr-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
    </form>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <table class="w-full text-sm">
            <thead><tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
                <th class="px-4 py-3">ID</th><th class="px-4 py-3">Name</th><th class="px-4 py-3">Specialization</th>
                <th class="px-4 py-3">Department</th><th class="px-4 py-3">License</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Actions</th>
            </tr></thead>
            <tbody>
            @forelse($rows as $r)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-4 py-3 font-medium text-blue-600">{{ $r->doctor_id }}</td>
                    <td class="px-4 py-3 text-slate-900">{{ $r->full_name }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $r->specialization ?: '—' }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $r->department_name ?: '—' }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $r->license_number ?: '—' }}</td>
                    <td class="px-4 py-3"><x-badge :status="$r->status" /></td>
                    <td class="px-4 py-3 text-right">
                        @can('staff.manage')
                            <button type="button" x-on:click="openModal('/staff/{{ $r->staff_id }}/edit')" class="text-slate-400 hover:text-blue-600" title="Edit"><x-icon name="pencil" class="h-4 w-4" /></button>
                            @if($r->status === 'active')
                                <form method="post" action="/staff/{{ $r->staff_id }}/deactivate" class="inline" onsubmit="return confirm('Deactivate Dr. {{ $r->full_name }}? They will no longer be able to log in.')">
                                    @csrf
                                    <button type="submit" class="ml-2 text-slate-400 hover:text-red-600" title="Deactivate"><x-icon name="x" class="h-4 w-4" /></button>
                                </form>
                            @else
                                <form method="post" action="/staff/{{ $r->staff_id }}/reactivate" class="inline">
                                    @csrf
                                    <button type="submit" class="ml-2 text-slate-400 hover:text-green-600" title="Reactivate"><x-icon name="check" class="h-4 w-4" /></button>
                                </form>
                            @endif
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">No doctors found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <x-modal name="doctor-modal" max-width="lg">
        <div id="doctor-modal-body"></div>
    </x-modal>
</div>
@endsection
