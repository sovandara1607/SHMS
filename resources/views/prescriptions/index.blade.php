@extends('layouts.app')
@section('content')

<x-page-header title="Prescriptions" :subtitle="$rows->total() . ' prescriptions total'" />

<div class="overflow-x-auto rounded-xl border border-manila/60 bg-paper-card">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
                <th class="px-4 py-3">Prescription ID</th>
                <th class="px-4 py-3">Patient</th>
                <th class="px-4 py-3">Doctor</th>
                <th class="px-4 py-3">Date</th>
                <th class="px-4 py-3">Notes</th>
                <th class="px-4 py-3 text-right">Actions</th>
            </tr>
        </thead>
        <tbody>
        @forelse($rows as $r)
            <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50/50">
                <td class="px-4 py-3 font-medium text-purple-600">{{ $r->prescription_id }}</td>
                <td class="px-4 py-3 text-slate-900">{{ $r->patient_name }}</td>
                <td class="px-4 py-3 text-slate-600">{{ $r->doctor_name ?? '—' }}</td>
                <td class="px-4 py-3 text-slate-600">{{ $r->prescription_date }}</td>
                <td class="px-4 py-3 text-slate-600">{{ \Illuminate\Support\Str::limit($r->notes, 40) }}</td>
                <td class="px-4 py-3">
                    <div class="flex justify-end items-center gap-3">
                        <a href="/prescriptions/{{ $r->prescription_id }}" title="View" class="text-slate-400 hover:text-blue-600">
                            <x-icon name="eye" class="h-4 w-4" />
                        </a>
                        @can('dispensing.create')
                            <a href="/prescriptions/{{ $r->prescription_id }}/dispense" class="text-sm font-medium text-blue-600 hover:underline">Dispense</a>
                        @endcan
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">No prescriptions yet.</td></tr>
        @endforelse
        </tbody>
    </table>
    <x-pagination :paginator="$rows" />
</div>
@endsection
