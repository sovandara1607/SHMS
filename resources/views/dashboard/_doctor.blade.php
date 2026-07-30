@php($actions = [
    ['label' => 'New Prescription', 'href' => '/prescriptions', 'cap' => 'prescription.view'],
    ['label' => 'Order Lab Test', 'href' => '/lab-orders', 'cap' => 'lab_order.create'],
    ['label' => 'View Patients', 'href' => '/patients', 'cap' => 'patient.view'],
])

<div class="mb-5 flex flex-wrap gap-2">
    @foreach($actions as $a)
        @can($a['cap'])
            <a href="{{ $a['href'] }}" class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                <x-icon name="plus" class="h-4 w-4" /> {{ $a['label'] }}
            </a>
        @endcan
    @endforeach
</div>

<div class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
    <x-stat-card label="My Patients" :value="number_format($stats['my_patients'])" icon="users" icon-color="blue" :badge="$kpiBadges['my_patients']" :report-url="$reportUrls['my_patients']" />
    <x-stat-card label="Today's Consults" :value="number_format($stats['today_consults'])" icon="clipboard" icon-color="green" :badge="$kpiBadges['today_consults']" :report-url="$reportUrls['today_consults']" />
    <x-stat-card label="Pending Reports" :value="number_format($stats['pending_reports'])" icon="document" icon-color="amber" :badge="$kpiBadges['pending_reports']" :report-url="$reportUrls['pending_reports']" />
    <x-stat-card label="Critical Cases" :value="number_format($stats['critical_cases'])" icon="x" icon-color="red" :badge="$kpiBadges['critical_cases']" :report-url="$reportUrls['critical_cases']" />
</div>

<div class="grid grid-cols-1 items-start gap-5 lg:grid-cols-2">
    <div class="shadow-paper rounded-xl border border-manila/60 bg-paper-card p-5">
        <p class="mb-3 font-semibold text-slate-900">Today's Patients</p>
        <div class="space-y-3">
            @forelse($todayPatients as $p)
                <div class="flex items-center justify-between border-b border-slate-50 pb-2 last:border-0">
                    <div>
                        <p class="text-sm font-medium text-slate-900">{{ $p['patient_name'] }}</p>
                        <p class="text-xs text-slate-400">{{ $p['reason'] ?: 'Consultation' }} &middot; {{ \Illuminate\Support\Str::substr($p['appointment_time'], 0, 5) }}</p>
                    </div>
                    <x-badge :status="$p['patient_status']" />
                </div>
            @empty
                <p class="text-sm text-slate-400">No patients scheduled today.</p>
            @endforelse
        </div>
    </div>

    <div class="shadow-paper rounded-xl border border-manila/60 bg-paper-card p-5">
        <p class="mb-3 font-semibold text-slate-900">Pending Lab Results</p>
        <div class="space-y-3">
            @forelse($pendingLabResults as $r)
                <div class="flex items-center justify-between border-b border-slate-50 pb-2 last:border-0">
                    <div>
                        <p class="text-sm font-medium text-slate-900">{{ $r['test_name'] }}</p>
                        <p class="text-xs text-slate-400">{{ $r['patient_name'] }}</p>
                    </div>
                    <x-badge :status="$r['status']" />
                </div>
            @empty
                <p class="text-sm text-slate-400">No pending lab results.</p>
            @endforelse
        </div>
    </div>
</div>
