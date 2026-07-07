@extends('layouts.app')
@section('content')

<div x-data="{
        async openModal(url) {
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const body = document.getElementById('room-modal-body');
            body.innerHTML = await res.text();
            window.Alpine.initTree(body);
            window.dispatchEvent(new CustomEvent('open-modal', { detail: 'room-modal' }));
        }
     }"
     x-init="@if($errors->any() && old('_modal_target'))openModal(@js(old('_modal_target')))@endif"
>
    <x-page-header title="Room &amp; Bed Management">
        <x-slot:actions>
            @can('staff.manage')
                <x-button variant="primary" x-on:click="openModal('/rooms/create')"><x-icon name="plus" class="h-4 w-4" /> Add Room</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <form method="get" action="/rooms" class="relative mb-4 max-w-md">
        <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
        <input type="text" name="q" value="{{ $q }}" placeholder="Search by room ID or number..."
               class="w-full rounded-lg border border-slate-200 bg-white py-2.5 pl-9 pr-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
    </form>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <table class="w-full text-sm">
            <thead><tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
                <th class="px-4 py-3">Room</th><th class="px-4 py-3">No.</th><th class="px-4 py-3">Type</th><th class="px-4 py-3">Floor</th><th class="px-4 py-3">Department</th><th class="px-4 py-3">Beds</th><th class="px-4 py-3">Free</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Actions</th>
            </tr></thead>
            <tbody>
            @forelse($rooms as $r)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-4 py-3 font-medium text-blue-600">{{ $r->room_id }}</td>
                    <td class="px-4 py-3 text-slate-900">{{ $r->room_number ?: '—' }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $r->room_type ? ucfirst($r->room_type) : '—' }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $r->floor_number ?? '—' }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $r->department_name ?: '—' }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $r->bed_count }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $r->beds_available }}</td>
                    <td class="px-4 py-3"><x-badge :status="$r->status" /></td>
                    <td class="px-4 py-3 text-right">
                        @can('staff.manage')
                            <button type="button" x-on:click="openModal('/rooms/{{ $r->room_id }}/edit')" class="text-slate-400 hover:text-blue-600"><x-icon name="pencil" class="h-4 w-4" /></button>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="px-4 py-8 text-center text-slate-400">No rooms found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <x-modal name="room-modal" max-width="lg">
        <div id="room-modal-body"></div>
    </x-modal>
</div>
@endsection
