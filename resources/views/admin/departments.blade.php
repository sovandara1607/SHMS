@extends('layouts.app')
@section('content')

<div x-data="{
        async openModal(url) {
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const body = document.getElementById('dept-modal-body');
            body.innerHTML = await res.text();
            window.Alpine.initTree(body);
            window.dispatchEvent(new CustomEvent('open-modal', { detail: 'dept-modal' }));
        }
     }"
     x-init="@if($errors->any() && old('_modal_target'))openModal(@js(old('_modal_target')))@endif"
>
    <x-page-header title="Department Management">
        <x-slot:actions>
            <x-button variant="primary" x-on:click="openModal('/departments/create')"><x-icon name="plus" class="h-4 w-4" /> Add Department</x-button>
        </x-slot:actions>
    </x-page-header>

    <form method="get" action="/departments" class="relative mb-4 max-w-md">
        <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
        <input type="text" name="q" value="{{ $q }}" placeholder="Search by name or type..."
               class="w-full rounded-lg border border-slate-200 bg-white py-2.5 pl-9 pr-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
    </form>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="w-full text-sm">
            <thead><tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
                <th class="px-4 py-3">ID</th><th class="px-4 py-3">Name</th><th class="px-4 py-3">Description</th><th class="px-4 py-3">Capacity</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Actions</th>
            </tr></thead>
            <tbody>
            @forelse($departments as $d)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-4 py-3 font-medium text-blue-600">{{ $d->department_id }}</td>
                    <td class="px-4 py-3 text-slate-900">{{ $d->department_name }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ \Illuminate\Support\Str::limit($d->description, 40) }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $d->capacity ?? '—' }}</td>
                    <td class="px-4 py-3"><x-badge :status="$d->status" /></td>
                    <td class="px-4 py-3 text-right">
                        <button type="button" x-on:click="openModal('/departments/{{ $d->department_id }}/edit')" class="text-slate-400 hover:text-blue-600"><x-icon name="pencil" class="h-4 w-4" /></button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">No departments found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <x-modal name="dept-modal" max-width="lg">
        <div id="dept-modal-body"></div>
    </x-modal>
</div>
@endsection
