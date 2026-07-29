@extends('layouts.app')
@section('content')

<div x-data="{ recordOpen: false }" x-init="@if($errors->any())recordOpen = true @endif">
    <x-page-header title="Vital Signs" :subtitle="$vitals->total() . ' entries total'">
        <x-slot:actions>
            @can('vital_signs.create')
                <x-button variant="primary" x-on:click="recordOpen = true"><x-icon name="plus" class="h-4 w-4" /> Record Vitals</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="overflow-x-auto rounded-xl border border-manila/60 bg-paper-card">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
                    <th class="px-4 py-3">ID</th>
                    <th class="px-4 py-3">Patient</th>
                    <th class="px-4 py-3">Temperature</th>
                    <th class="px-4 py-3">Blood Pressure</th>
                    <th class="px-4 py-3">Heart Rate</th>
                    <th class="px-4 py-3">Height</th>
                    <th class="px-4 py-3">Weight</th>
                    <th class="px-4 py-3">Recorded At</th>
                </tr>
            </thead>
            <tbody>
            @forelse($vitals as $v)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-4 py-3 font-medium text-purple-600">{{ $v->vital_sign_id }}</td>
                    <td class="px-4 py-3 text-slate-900">{{ $v->patient_name }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $v->temperature ?? '—' }}{{ $v->temperature ? '°C' : '' }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $v->blood_pressure ?: '—' }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $v->heart_rate ?? '—' }}{{ $v->heart_rate ? ' bpm' : '' }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $v->height ?? '—' }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $v->weight ?? '—' }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $v->recorded_at }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="px-4 py-8 text-center text-slate-400">No vital signs recorded.</td></tr>
            @endforelse
            </tbody>
        </table>
        <x-pagination :paginator="$vitals" />
    </div>

    <div x-show="recordOpen"
         x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         style="display: none;" class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 px-4">
        <div x-on:click.outside="recordOpen = false"
             x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
             class="w-full max-w-lg rounded-xl bg-paper-card shadow-xl">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <p class="font-semibold text-slate-900">Record Vitals</p>
                <button type="button" x-on:click="recordOpen = false" class="text-slate-400 hover:text-slate-600">
                    <x-icon name="x" class="h-5 w-5" />
                </button>
            </div>
            <form method="post" action="/vital-signs" class="space-y-4 px-6 py-5">
                @csrf
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Patient *</label>
                    <x-patient-picker :required="true" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Temperature (°C)</label>
                        <input name="temperature" placeholder="e.g. 37.2" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Blood Pressure</label>
                        <input name="blood_pressure" placeholder="e.g. 120/80" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Heart Rate</label>
                        <input name="heart_rate" placeholder="e.g. 72" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Height (cm)</label>
                        <input name="height" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Weight (kg)</label>
                        <input name="weight" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <x-button variant="secondary" type="button" x-on:click="recordOpen = false">Cancel</x-button>
                    <x-button variant="primary" type="submit">Save Vitals</x-button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
