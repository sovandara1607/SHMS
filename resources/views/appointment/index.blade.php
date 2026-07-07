@extends('layouts.app')
@section('content')

@php
$filters = ['all' => 'All', 'scheduled' => 'Scheduled', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
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

    <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-stat-card label="Today" :value="$stats['today']" icon="calendar" icon-color="blue" />
        <x-stat-card label="This Week" :value="$stats['this_week']" icon="calendar" icon-color="purple" />
        <x-stat-card label="Scheduled" :value="$stats['scheduled']" icon="clipboard" icon-color="green" />
        <x-stat-card label="Cancelled" :value="$stats['cancelled']" icon="x" icon-color="red" />
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-2">
        <div class="flex flex-wrap gap-1.5">
            @foreach($filters as $value => $label)
                <a href="/appointments?status={{ $value }}"
                   class="rounded-lg px-3 py-2 text-sm font-medium {{ $status === $value ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 border border-slate-200' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
        <form method="get" action="/appointments" class="relative flex-1 min-w-[200px]">
            <input type="hidden" name="status" value="{{ $status }}">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input type="text" name="q" value="{{ $q }}" placeholder="Patient / doctor / ID"
                   class="w-full rounded-lg border border-slate-200 bg-white py-2.5 pl-9 pr-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </form>
        <input type="date" name="date" value="{{ $date }}" onchange="location.href='/appointments?status={{ $status }}&date='+this.value"
               class="rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
                    <th class="px-4 py-3">Appointment ID</th>
                    <th class="px-4 py-3">Patient</th>
                    <th class="px-4 py-3">Doctor</th>
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
                    <td class="px-4 py-3 text-slate-900">{{ $a->patient_name }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $a->doctor_name }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $a->appointment_date }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ \Illuminate\Support\Str::substr($a->appointment_time, 0, 5) }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $a->reason ?: '—' }}</td>
                    <td class="px-4 py-3"><x-badge :status="$a->status" /></td>
                    <td class="px-4 py-3">
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
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="px-4 py-8 text-center text-slate-400">No appointments.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <x-modal name="appointment-modal" max-width="lg">
        <div id="appointment-modal-body"></div>
    </x-modal>
</div>
@endsection
