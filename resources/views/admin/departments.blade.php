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
    <x-page-header title="Department Management" :subtitle="$stats['total'] . ' departments · ' . $stats['active'] . ' Active · ' . $stats['inactive'] . ' Inactive'">
        <x-slot:actions>
            <x-button variant="primary" x-on:click="openModal('/departments/create')"><x-icon name="plus" class="h-4 w-4" /> Add Department</x-button>
        </x-slot:actions>
    </x-page-header>

    <form method="get" action="/departments" class="relative mb-4 max-w-md">
        <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
        <input type="text" name="q" value="{{ $q }}" placeholder="Search by name or type..."
               class="w-full rounded-lg border border-manila/60 bg-paper-card py-2.5 pl-9 pr-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
    </form>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($departments as $d)
            <div class="shadow-paper rounded-xl border border-manila/60 bg-paper-card p-4">
                <div class="mb-3 flex items-start justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-50">
                            <x-icon name="building" class="h-4.5 w-4.5 text-blue-600" />
                        </div>
                        <div>
                            <p class="font-semibold text-slate-900">{{ $d->department_name }}</p>
                            <p class="text-xs text-slate-400">{{ $d->department_id }}</p>
                        </div>
                    </div>
                    <x-badge :status="$d->status" />
                </div>
                <p class="mb-3 text-sm text-slate-600">{{ $d->description ?: '—' }}</p>
                <div class="mb-3 grid grid-cols-2 gap-y-1.5 text-sm">
                    <span class="text-slate-500">Department Head</span><span class="text-right text-slate-900">{{ $d->head_name ?? '—' }}</span>
                    <span class="text-slate-500">Capacity</span><span class="text-right text-slate-900">{{ $d->capacity ?? '—' }}</span>
                </div>
                <div class="flex justify-end border-t border-slate-100 pt-3">
                    <button type="button" x-on:click="openModal('/departments/{{ $d->department_id }}/edit')" class="text-sm font-medium text-blue-600 hover:underline">Edit</button>
                </div>
            </div>
        @empty
            <p class="col-span-full px-4 py-8 text-center text-slate-400">No departments found.</p>
        @endforelse
    </div>

    <x-modal name="dept-modal" max-width="lg">
        <div id="dept-modal-body"></div>
    </x-modal>
</div>
@endsection
