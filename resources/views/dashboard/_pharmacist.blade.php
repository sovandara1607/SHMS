<div class="mb-5 flex flex-wrap gap-2">
    @can('medicine.create')
        <a href="/medicines" class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
            <x-icon name="plus" class="h-4 w-4" /> Add Medicine
        </a>
    @endcan
    @can('patient.view')
        <a href="/patients" class="inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700">
            <x-icon name="plus" class="h-4 w-4" /> View Patients
        </a>
    @endcan
</div>

<div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
    <x-stat-card label="Total Medicines" :value="$stats['total_medicines']" icon="pill" icon-color="blue" />
    <x-stat-card label="Low Stock Alerts" :value="$stats['low_stock']" icon="clipboard" icon-color="amber" />
    <x-stat-card label="Expired Items" :value="$stats['expired']" icon="x" icon-color="red" />
    <x-stat-card label="Dispensed Today" :value="$stats['dispensed_today']" icon="pill" icon-color="green" />
</div>

<div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
    <div class="rounded-xl border border-slate-200 bg-white p-5">
        <p class="mb-3 font-semibold text-slate-900">Stock Alerts</p>
        <div class="space-y-3">
            @forelse($stockAlerts as $m)
                <div class="flex items-center justify-between border-b border-slate-50 pb-2 last:border-0">
                    <div>
                        <p class="text-sm font-medium text-slate-900">{{ $m->medicine_name }}</p>
                        <p class="text-xs text-slate-400">{{ $m->medicine_type }} &middot; {{ $m->stock_quantity }} left</p>
                    </div>
                    <x-badge :status="$m->stock_quantity == 0 ? 'expired' : 'low'" />
                </div>
            @empty
                <p class="text-sm text-slate-400">No stock alerts.</p>
            @endforelse
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5">
        <p class="mb-3 font-semibold text-slate-900">Recent Dispensing</p>
        <div class="space-y-3">
            @forelse($recentDispensing as $d)
                <div class="flex items-center justify-between border-b border-slate-50 pb-2 last:border-0">
                    <p class="text-sm font-medium text-slate-900">{{ $d->patient_name }}</p>
                    <x-badge :status="$d->status" />
                </div>
            @empty
                <p class="text-sm text-slate-400">No dispensing activity yet.</p>
            @endforelse
        </div>
    </div>
</div>
