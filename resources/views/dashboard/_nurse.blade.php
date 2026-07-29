@can('room.view')
    <div class="mb-5">
        <a href="/rooms" class="inline-flex items-center gap-1.5 rounded-lg bg-orange-500 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-600">
            <x-icon name="plus" class="h-4 w-4" /> Room Status
        </a>
    </div>
@endcan

<div class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
    <x-stat-card label="Assigned Patients" :value="number_format($stats['assigned_patients'])" icon="users" icon-color="red" :badge="$kpiBadges['assigned_patients']" :report-url="$reportUrls['assigned_patients']" />
    <x-stat-card label="Vitals Due" :value="number_format($stats['vitals_due'])" icon="clipboard" icon-color="blue" :badge="$kpiBadges['vitals_due']" :report-url="$reportUrls['vitals_due']" />
    <x-stat-card label="Medications Due" :value="number_format($stats['medications_due'])" icon="pill" icon-color="purple" :badge="$kpiBadges['medications_due']" :report-url="$reportUrls['medications_due']" />
    <x-stat-card label="ICU Watch" :value="number_format($stats['icu_watch'])" icon="x" icon-color="red" :badge="$kpiBadges['icu_watch']" :report-url="$reportUrls['icu_watch']" />
</div>

<div class="grid grid-cols-1 items-start gap-5 lg:grid-cols-2">
    <div class="shadow-paper rounded-xl border border-manila/60 bg-paper-card p-5">
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

    <div class="shadow-paper rounded-xl border border-manila/60 bg-paper-card p-5">
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
