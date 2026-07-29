@extends('layouts.app')
@section('content')

@php
$filters = ['all' => 'All', 'scheduled' => 'Scheduled', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
$monthLabel = \Carbon\Carbon::parse($month . '-01')->format('F Y');
$prevMonth = \Carbon\Carbon::parse($month . '-01')->subMonthNoOverflow()->format('Y-m');
$nextMonth = \Carbon\Carbon::parse($month . '-01')->addMonthNoOverflow()->format('Y-m');
$today = now()->toDateString();
@endphp

<div x-data="{
        async openModal(url) {
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const body = document.getElementById('appointment-modal-body');
            body.innerHTML = await res.text();
            window.Alpine.initTree(body);
            window.dispatchEvent(new CustomEvent('open-modal', { detail: 'appointment-modal' }));
        }
     }"
     x-init="@if($errors->any() && old('_modal_target'))openModal(@js(old('_modal_target')))@endif"
>
    <x-page-header title="Appointment Management">
        <x-slot:actions>
            @can('appointment.create')
                <x-button variant="primary" x-on:click="openModal('/appointments/create')"><x-icon name="plus" class="h-4 w-4" /> Create Appointment</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 flex flex-wrap gap-1.5">
        @foreach($filters as $value => $label)
            <a href="/appointments?status={{ $value }}&month={{ $month }}"
               class="rounded-lg px-3 py-2 text-sm font-medium {{ $status === $value ? 'bg-blue-600 text-white shadow-well' : 'bg-paper-card text-slate-600 border border-manila/60 shadow-emboss' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-[300px_1fr]">
        <div>
            <div class="mb-4 shadow-paper rounded-xl border border-manila/60 bg-paper-card p-4">
                <div class="mb-3 flex items-center justify-between">
                    <p class="font-semibold text-slate-900">{{ $monthLabel }}</p>
                    <div class="flex gap-1">
                        <a href="/appointments?status={{ $status }}&month={{ $prevMonth }}" class="flex h-7 w-7 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-50">
                            <x-icon name="chevron-down" class="h-4 w-4 rotate-90" />
                        </a>
                        <a href="/appointments?status={{ $status }}&month={{ $nextMonth }}" class="flex h-7 w-7 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-50">
                            <x-icon name="chevron-down" class="h-4 w-4 -rotate-90" />
                        </a>
                    </div>
                </div>
                <div class="grid grid-cols-7 gap-1 text-center text-xs text-slate-400">
                    @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $dow)
                        <span class="py-1">{{ $dow }}</span>
                    @endforeach
                </div>
                <div class="grid grid-cols-7 gap-1 text-center text-sm">
                    @foreach($calendarDays as $cell)
                        @if(is_null($cell['day']))
                            <span></span>
                        @else
                            <a href="/appointments?status={{ $status }}&month={{ $month }}&date={{ $cell['date'] }}"
                               class="relative flex h-8 w-8 items-center justify-center justify-self-center rounded-full {{ $date === $cell['date'] ? 'bg-blue-600 text-white' : ($cell['date'] === $today ? 'text-blue-600 font-semibold' : 'text-slate-700 hover:bg-slate-50') }}">
                                {{ $cell['day'] }}
                                @if($cell['count'] > 0)
                                    <span class="absolute bottom-0.5 h-1 w-1 rounded-full {{ $date === $cell['date'] ? 'bg-white' : 'bg-blue-500' }}"></span>
                                @endif
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <x-stat-card label="Today" :value="$stats['today']" icon="calendar" icon-color="blue" />
                <x-stat-card label="This Week" :value="$stats['this_week']" icon="calendar" icon-color="purple" />
                <x-stat-card label="Scheduled" :value="$stats['scheduled']" icon="clipboard" icon-color="green" />
                <x-stat-card label="Cancelled" :value="$stats['cancelled']" icon="x" icon-color="red" />
            </div>
        </div>

        <div>
            <div class="mb-3 flex items-center justify-between">
                <p class="font-semibold text-slate-900">Appointment List</p>
                <span class="text-sm text-slate-400">{{ $appointments->total() }} appointments</span>
            </div>
            <div class="mb-4 flex flex-wrap items-center gap-2">
                <form method="get" action="/appointments" class="relative flex-1 min-w-[200px]">
                    <input type="hidden" name="status" value="{{ $status }}">
                    <input type="hidden" name="month" value="{{ $month }}">
                    <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input type="text" name="q" value="{{ $q }}" placeholder="Patient / doctor / ID"
                           class="w-full rounded-lg border border-manila/60 bg-paper-card py-2.5 pl-9 pr-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                </form>
                @if($date)
                    <a href="/appointments?status={{ $status }}&month={{ $month }}" class="rounded-lg border border-manila/60 bg-paper-card px-3 py-2.5 text-sm text-slate-500 hover:text-slate-700">Clear date</a>
                @endif
            </div>

            <div class="overflow-x-auto rounded-xl border border-manila/60 bg-paper-card">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
                            <th class="px-4 py-3">Appointment ID</th>
                            <th class="px-4 py-3">Patient Name</th>
                            <th class="px-4 py-3">Doctor</th>
                            <th class="px-4 py-3">Booked By</th>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Time</th>
                            <th class="px-4 py-3">Reason</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($appointments as $a)
                        <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50/50">
                            <td class="px-4 py-3 font-medium text-blue-600">{{ $a->appointment_id }}</td>
                            <td class="px-4 py-3">
                                <p class="text-slate-900">{{ $a->patient_name }}</p>
                                <p class="text-xs text-slate-400">{{ $a->patient_id }}</p>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $a->doctor_name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $a->booked_by_name ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $a->appointment_date }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ \Illuminate\Support\Str::substr($a->appointment_time, 0, 5) }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $a->reason ?: '—' }}</td>
                            <td class="px-4 py-3"><x-badge :status="$a->status" /></td>
                            <td class="px-4 py-3">
                                <div class="flex flex-col items-end gap-0.5">
                                    <div class="flex justify-end gap-2">
                                        <button type="button" x-on:click="openModal('/appointments/{{ $a->appointment_id }}')" class="text-slate-400 hover:text-blue-600">
                                            <x-icon name="eye" class="h-4 w-4" />
                                        </button>
                                        @if($a->status === 'scheduled')
                                            @can('appointment.update')
                                                <button type="button" x-on:click="openModal('/appointments/{{ $a->appointment_id }}/edit')" class="text-slate-400 hover:text-blue-600">
                                                    <x-icon name="pencil" class="h-4 w-4" />
                                                </button>
                                            @endcan
                                            @can('appointment.cancel')
                                                <button type="button" x-on:click="openModal('/appointments/{{ $a->appointment_id }}/cancel')" class="text-slate-400 hover:text-red-600">
                                                    <x-icon name="x" class="h-4 w-4" />
                                                </button>
                                            @endcan
                                            @can('appointment.complete')
                                                <form method="post" action="/appointments/{{ $a->appointment_id }}/complete" onsubmit="return confirm('Mark this appointment as completed?')">
                                                    @csrf
                                                    <button type="submit" class="text-slate-400 hover:text-green-600" title="Mark as Completed">
                                                        <x-icon name="check" class="h-4 w-4" />
                                                    </button>
                                                </form>
                                            @endcan
                                        @endif
                                    </div>
                                    @if($a->status !== 'scheduled')
                                        <span class="text-[11px] text-slate-400">View only</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-8 text-center text-slate-400">No appointments.</td></tr>
                    @endforelse
                    </tbody>
                </table>
                <x-pagination :paginator="$appointments" />
            </div>
        </div>
    </div>

    <x-modal name="appointment-modal" max-width="lg">
        <div id="appointment-modal-body"></div>
    </x-modal>
</div>
@endsection
