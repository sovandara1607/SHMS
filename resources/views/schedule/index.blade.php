@extends('layouts.app')
@section('content')

<div x-data="{
        async openModal(url) {
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const body = document.getElementById('schedule-modal-body');
            body.innerHTML = await res.text();
            window.Alpine.initTree(body);
            window.dispatchEvent(new CustomEvent('open-modal', { detail: 'schedule-modal' }));
        }
     }"
     x-init="@if($errors->any() && old('_modal_target'))openModal(@js(old('_modal_target')))@endif"
>
    <div class="mb-5 flex flex-wrap gap-1.5">
        @can('staff.manage')
            <a href="/staff" class="rounded-lg px-3.5 py-2 text-sm font-medium text-slate-500 hover:text-slate-700">Staff List</a>
        @endcan
        @can('schedule.view')
            <a href="/schedule" class="rounded-lg bg-paper-card px-3.5 py-2 text-sm font-medium text-slate-900 shadow-sm">Staff Schedule / Shift</a>
        @endcan
    </div>

    <x-page-header title="Schedule Management" :subtitle="$shifts->total() . ' shifts total'">
        <x-slot:actions>
            @can('schedule.manage')
                <x-button variant="primary" x-on:click="openModal('/schedule/create')"><x-icon name="plus" class="h-4 w-4" /> Assign Shift</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="shadow-paper rounded-xl border border-manila/60 bg-paper-card p-4">
            <p class="text-2xl font-semibold text-blue-600">{{ $stats['scheduled'] }}</p>
            <p class="text-xs text-slate-500">Scheduled</p>
        </div>
        <div class="shadow-paper rounded-xl border border-manila/60 bg-paper-card p-4">
            <p class="text-2xl font-semibold text-green-600">{{ $stats['completed'] }}</p>
            <p class="text-xs text-slate-500">Completed</p>
        </div>
        <div class="shadow-paper rounded-xl border border-manila/60 bg-paper-card p-4">
            <p class="text-2xl font-semibold text-amber-600">{{ $stats['on_leave'] }}</p>
            <p class="text-xs text-slate-500">On Leave</p>
        </div>
        <div class="shadow-paper rounded-xl border border-manila/60 bg-paper-card p-4">
            <p class="text-2xl font-semibold text-red-600">{{ $stats['cancelled'] }}</p>
            <p class="text-xs text-slate-500">Cancelled</p>
        </div>
    </div>

    <div class="mb-2 flex flex-wrap gap-2">
        @foreach(['all' => 'All', 'scheduled' => 'Scheduled', 'completed' => 'Completed', 'on_leave' => 'On Leave', 'cancelled' => 'Cancelled'] as $value => $label)
            <a href="/schedule?status={{ $value }}&role={{ $role }}"
               class="rounded-full px-3.5 py-1.5 text-sm font-medium {{ $status === $value ? 'bg-blue-600 text-white shadow-well' : 'bg-paper-card text-slate-600 border border-manila/60 shadow-emboss' }}">{{ $label }}</a>
        @endforeach
    </div>

    <div class="mb-4 flex flex-wrap gap-2">
        @foreach(['all' => 'All'] + $roles as $value => $label)
            <a href="/schedule?status={{ $status }}&role={{ $value }}"
               class="rounded-full px-3.5 py-1.5 text-sm font-medium {{ $role === $value ? 'bg-slate-700 text-white shadow-well' : 'bg-paper-card text-slate-600 border border-manila/60 shadow-emboss' }}">{{ $label }}</a>
        @endforeach
    </div>

    <div class="overflow-x-auto rounded-xl border border-manila/60 bg-paper-card">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
                    <th class="px-4 py-3">Shift ID</th>
                    <th class="px-4 py-3">Staff ID</th>
                    <th class="px-4 py-3">Staff Name</th>
                    <th class="px-4 py-3">Role</th>
                    <th class="px-4 py-3">Department</th>
                    <th class="px-4 py-3">Shift Date</th>
                    <th class="px-4 py-3">Start</th>
                    <th class="px-4 py-3">End</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($shifts as $s)
                <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50/50">
                    <td class="px-4 py-3 font-medium text-purple-600">{{ $s->shift_id }}</td>
                    <td class="px-4 py-3 text-blue-600">{{ $s->staff_id }}</td>
                    <td class="px-4 py-3 text-slate-900">{{ $s->staff_name }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $s->role ? ucfirst(str_replace('_', ' ', $s->role)) : '—' }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $s->department ?? '—' }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $s->shift_date }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $s->start_time }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $s->end_time }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ ucfirst($s->shift_type) }}</td>
                    <td class="px-4 py-3"><x-badge :status="$s->status" /></td>
                    <td class="px-4 py-3">
                        <div class="flex justify-end items-center gap-3">
                            <button type="button" x-on:click="openModal('/schedule/{{ $s->shift_id }}')" title="View" class="text-slate-400 hover:text-blue-600">
                                <x-icon name="eye" class="h-4 w-4" />
                            </button>
                            @can('schedule.manage')
                                @if($s->status === 'scheduled')
                                    <button type="button" x-on:click="openModal('/schedule/{{ $s->shift_id }}/edit')" title="Edit" class="text-slate-400 hover:text-blue-600">
                                        <x-icon name="pencil" class="h-4 w-4" />
                                    </button>
                                    <form method="post" action="/schedule/{{ $s->shift_id }}/cancel" onsubmit="return confirm('Cancel shift {{ $s->shift_id }} for {{ $s->staff_name }}?')">
                                        @csrf
                                        <button type="submit" title="Cancel" class="text-slate-400 hover:text-red-600">
                                            <x-icon name="x" class="h-4 w-4" />
                                        </button>
                                    </form>
                                @endif
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="11" class="px-4 py-8 text-center text-slate-400">No shifts.</td></tr>
            @endforelse
            </tbody>
        </table>
        <x-pagination :paginator="$shifts" />
    </div>

    <x-modal name="schedule-modal" max-width="lg">
        <div id="schedule-modal-body"></div>
    </x-modal>
</div>
@endsection
