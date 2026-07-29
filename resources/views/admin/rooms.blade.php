@extends('layouts.app')
@section('content')

@php
$roomTypeLabels = ['general' => 'Standard', 'private' => 'Premium', 'icu' => 'ICU', 'emergency' => 'Trauma', 'operating_room' => 'OR'];
@endphp

<div x-data="{
        tab: @js(request()->query('tab', 'rooms')),
        async openModal(url) {
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const body = document.getElementById('room-modal-body');
            body.innerHTML = await res.text();
            window.Alpine.initTree(body);
            window.dispatchEvent(new CustomEvent('open-modal', { detail: 'room-modal' }));
        }
     }"
     x-init="@if($errors->any() && old('_modal_target'))openModal(@js(old('_modal_target')))@elseif(session('reopen_room_beds'))openModal(@js('/rooms/' . session('reopen_room_beds') . '/beds'))@endif"
>
    <x-page-header title="Room & Bed Management">
        <x-slot:actions>
            @can('staff.manage')
                <x-button variant="primary" x-on:click="openModal('/rooms/create')"><x-icon name="plus" class="h-4 w-4" /> Add Room</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-stat-card label="Total Rooms" :value="$stats['total_rooms']" icon="building" icon-color="blue" />
        <x-stat-card label="Total Beds" :value="$stats['total_beds']" icon="bed" icon-color="purple" />
        <x-stat-card label="Available Beds" :value="$stats['available_beds']" icon="bed" icon-color="green" />
        <x-stat-card label="Active Assignments" :value="$stats['active_assignments']" icon="clipboard" icon-color="amber" />
    </div>

    <div class="mb-4 flex flex-wrap gap-1.5">
        <button type="button" @click="tab = 'rooms'" :class="tab === 'rooms' ? 'bg-blue-600 text-white shadow-well' : 'bg-paper-card text-slate-600 border border-manila/60 shadow-emboss'" class="rounded-lg px-3.5 py-2 text-sm font-medium">Rooms</button>
        <button type="button" @click="tab = 'beds'" :class="tab === 'beds' ? 'bg-blue-600 text-white shadow-well' : 'bg-paper-card text-slate-600 border border-manila/60 shadow-emboss'" class="rounded-lg px-3.5 py-2 text-sm font-medium">Beds</button>
        <button type="button" @click="tab = 'assignments'" :class="tab === 'assignments' ? 'bg-blue-600 text-white shadow-well' : 'bg-paper-card text-slate-600 border border-manila/60 shadow-emboss'" class="rounded-lg px-3.5 py-2 text-sm font-medium">Room Assignments</button>
    </div>

    {{-- Rooms --}}
    <div x-show="tab === 'rooms'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
        <form method="get" action="/rooms" class="relative mb-4 max-w-md">
            <input type="hidden" name="tab" value="rooms">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input type="text" name="q" value="{{ $q }}" placeholder="Search by room ID or number..."
                   class="w-full rounded-lg border border-manila/60 bg-paper-card py-2.5 pl-9 pr-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </form>

        <div class="overflow-x-auto rounded-xl border border-manila/60 bg-paper-card">
            <table class="w-full text-sm">
                <thead><tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
                    <th class="px-4 py-3">Room</th><th class="px-4 py-3">No.</th><th class="px-4 py-3">Type</th><th class="px-4 py-3">Floor</th><th class="px-4 py-3">Department</th><th class="px-4 py-3">Rate/Day</th><th class="px-4 py-3">Beds</th><th class="px-4 py-3">Free</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Actions</th>
                </tr></thead>
                <tbody>
                @forelse($rooms as $r)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-4 py-3 font-medium text-blue-600">{{ $r->room_id }}</td>
                        <td class="px-4 py-3 text-slate-900">{{ $r->room_number ?: '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $r->room_type ? ($roomTypeLabels[$r->room_type] ?? ucfirst($r->room_type)) : '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $r->floor_number ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $r->department_name ?: '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ isset($r->rate_per_day) ? '$' . number_format($r->rate_per_day, 2) : '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $r->bed_count }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $r->beds_available }}</td>
                        <td class="px-4 py-3"><x-badge :status="$r->status" /></td>
                        <td class="px-4 py-3 text-right">
                            @can('room.assign')
                                <button type="button" x-on:click="openModal('/rooms/{{ $r->room_id }}/beds')" class="text-sm font-medium text-blue-600 hover:underline">Beds</button>
                            @endcan
                            @can('staff.manage')
                                <button type="button" x-on:click="openModal('/rooms/{{ $r->room_id }}/edit')" class="ml-2 text-slate-400 hover:text-blue-600"><x-icon name="pencil" class="h-4 w-4" /></button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="px-4 py-8 text-center text-slate-400">No rooms found.</td></tr>
                @endforelse
                </tbody>
            </table>
            <x-pagination :paginator="$rooms" />
        </div>
    </div>

    {{-- Beds --}}
    <div x-show="tab === 'beds'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
        <form method="get" action="/rooms" class="mb-4 flex flex-wrap items-center gap-3">
            <input type="hidden" name="tab" value="beds">
            <div class="relative max-w-md flex-1">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input type="text" name="bed_q" value="{{ $bedQ }}" placeholder="Search by bed ID or room number..."
                       class="w-full rounded-lg border border-manila/60 bg-paper-card py-2.5 pl-9 pr-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
            </div>
            <div class="flex flex-wrap items-center gap-1.5">
                @foreach(['' => 'All', 'available' => 'Available', 'occupied' => 'Occupied', 'maintenance' => 'Maintenance'] as $value => $label)
                    <button type="submit" name="bed_status" value="{{ $value }}"
                            class="rounded-full px-3 py-1.5 text-sm font-medium {{ (string) $bedStatus === (string) $value ? 'bg-blue-600 text-white shadow-well' : 'bg-paper-card text-slate-600 border border-manila/60 shadow-emboss' }}">{{ $label }}</button>
                @endforeach
            </div>
        </form>

        <div class="overflow-x-auto rounded-xl border border-manila/60 bg-paper-card">
            <table class="w-full text-sm">
                <thead><tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
                    <th class="px-4 py-3">Bed ID</th><th class="px-4 py-3">Room No.</th><th class="px-4 py-3">Bed No.</th><th class="px-4 py-3">Bed Type</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Actions</th>
                </tr></thead>
                <tbody>
                @forelse($beds as $b)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-4 py-3 font-medium text-slate-500">R-{{ $b->room_number ?: $b->room_id }}-{{ $b->bed_number ?: $b->bed_id }}</td>
                        <td class="px-4 py-3 text-slate-900">Room {{ $b->room_number ?: $b->room_id }}</td>
                        <td class="px-4 py-3 font-medium text-blue-600">{{ $b->bed_number ?: '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $b->room_type ? ($roomTypeLabels[$b->room_type] ?? ucfirst($b->room_type)) : '—' }}</td>
                        <td class="px-4 py-3"><x-badge :status="$b->status" /></td>
                        <td class="px-4 py-3 text-right">
                            @can('room.assign')
                                @if($b->status === 'available')
                                    <button type="button" x-on:click="openModal('/beds/{{ $b->bed_id }}/assign?from=beds_tab')" class="inline-flex items-center gap-1 text-sm font-medium text-blue-600 hover:underline">
                                        <x-icon name="users" class="h-3.5 w-3.5" /> Assign
                                    </button>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">No beds found.</td></tr>
                @endforelse
                </tbody>
            </table>
            <x-pagination :paginator="$beds" />
        </div>
    </div>

    {{-- Room Assignments --}}
    <div x-show="tab === 'assignments'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
        <div class="overflow-x-auto rounded-xl border border-manila/60 bg-paper-card">
            <table class="w-full text-sm">
                <thead><tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
                    <th class="px-4 py-3">Assignment</th><th class="px-4 py-3">Room</th><th class="px-4 py-3">Bed</th><th class="px-4 py-3">Patient</th><th class="px-4 py-3">Admitted</th><th class="px-4 py-3">Discharged</th><th class="px-4 py-3">Assigned By</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Actions</th>
                </tr></thead>
                <tbody>
                @forelse($assignments as $a)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-4 py-3 font-medium text-purple-600">{{ $a->room_assignment_id }}</td>
                        <td class="px-4 py-3 text-slate-900">{{ $a->room_number ?: $a->room_id }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $a->bed_number ?: '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $a->patient_name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $a->assigned_at }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $a->released_at ?: '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $a->assigned_by_name ?: '—' }}</td>
                        <td class="px-4 py-3"><x-badge :status="$a->status" /></td>
                        <td class="px-4 py-3 text-right">
                            @can('room.assign')
                                @if($a->status === 'active')
                                    <form method="post" action="/room-assignments/{{ $a->room_assignment_id }}/release" onsubmit="return confirm('Discharge {{ $a->patient_name }} from {{ $a->room_number ?: $a->room_id }}?')">
                                        @csrf
                                        <input type="hidden" name="from" value="assignments_tab">
                                        <button type="submit" class="text-sm font-medium text-red-600 hover:underline">Discharge</button>
                                    </form>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-4 py-8 text-center text-slate-400">No room assignments found.</td></tr>
                @endforelse
                </tbody>
            </table>
            <x-pagination :paginator="$assignments" />
        </div>
    </div>

    <x-modal name="room-modal" max-width="lg">
        <div id="room-modal-body"></div>
    </x-modal>
</div>
@endsection
