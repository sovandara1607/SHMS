@can('room.view')
    <div class="mb-5">
        <a href="/rooms" class="inline-flex items-center gap-1.5 rounded-lg bg-orange-500 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-600">
            <x-icon name="plus" class="h-4 w-4" /> Room Status
        </a>
    </div>
@endcan

<div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
    <x-stat-card label="Assigned Patients" :value="$stats['assigned_patients']" icon="users" icon-color="red" />
    <x-stat-card label="Vitals Due" :value="$stats['vitals_due']" icon="clipboard" icon-color="blue" />
    <x-stat-card label="Medications Due" :value="$stats['medications_due']" icon="pill" icon-color="purple" />
    <x-stat-card label="ICU Watch" :value="$stats['icu_watch']" icon="x" icon-color="red" />
</div>

<div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
    <div class="rounded-xl border border-slate-200 bg-white p-5">
        <p class="mb-3 font-semibold text-slate-900">Vitals Round (Next Up)</p>
        <div class="space-y-3">
            @forelse($vitalsRound as $v)
                <div class="flex items-center justify-between border-b border-slate-50 pb-2 last:border-0">
                    <p class="text-sm font-medium text-slate-900">{{ $v->patient?->fullName() ?? '—' }}</p>
                    <x-badge :status="$v->status" />
                </div>
            @empty
                <p class="text-sm text-slate-400">No vitals due.</p>
            @endforelse
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5">
        <p class="mb-3 font-semibold text-slate-900">Medication Schedule</p>
        <div class="space-y-3">
            @forelse($medicationSchedule as $m)
                <div class="flex items-center justify-between border-b border-slate-50 pb-2 last:border-0">
                    <div>
                        <p class="text-sm font-medium text-slate-900">{{ $m->medicine_name }} &middot; {{ $m->patient_name }}</p>
                        <p class="text-xs text-slate-400">{{ $m->dosage }} &middot; {{ $m->frequency }}</p>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-400">No medications scheduled.</p>
            @endforelse
        </div>
    </div>
</div>
