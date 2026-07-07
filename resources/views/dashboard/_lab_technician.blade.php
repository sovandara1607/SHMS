<div class="mb-5 flex flex-wrap gap-2">
    @can('lab_order.view')
        <a href="/lab-orders" class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
            <x-icon name="eye" class="h-4 w-4" /> View Lab Orders
        </a>
    @endcan
    @can('patient.view')
        <a href="/patients" class="inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700">
            <x-icon name="plus" class="h-4 w-4" /> View Patients
        </a>
    @endcan
</div>

<div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
    <x-stat-card label="Pending Tests" :value="$stats['pending']" icon="clipboard" icon-color="amber" />
    <x-stat-card label="In Progress" :value="$stats['in_progress']" icon="flask" icon-color="blue" />
    <x-stat-card label="Completed Today" :value="$stats['completed_today']" icon="clipboard" icon-color="green" />
    <x-stat-card label="Equipment Issues" :value="$stats['equipment_issues']" icon="x" icon-color="red" />
</div>

<div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
    <div class="rounded-xl border border-slate-200 bg-white p-5">
        <p class="mb-3 font-semibold text-slate-900">Active Lab Queue</p>
        <div class="space-y-3">
            @forelse($activeQueue as $q)
                <div class="flex items-center justify-between border-b border-slate-50 pb-2 last:border-0">
                    <div>
                        <p class="text-sm font-medium text-slate-900">{{ $q->test_name }} &middot; {{ $q->patient_name }}</p>
                        <p class="text-xs text-slate-400">{{ $q->test_order_id }}</p>
                    </div>
                    <x-badge :status="$q->status" />
                </div>
            @empty
                <p class="text-sm text-slate-400">No active lab orders.</p>
            @endforelse
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5">
        <p class="mb-3 font-semibold text-slate-900">Equipment Status</p>
        <div class="space-y-3">
            @forelse($equipmentStatus as $e)
                <div class="flex items-center justify-between border-b border-slate-50 pb-2 last:border-0">
                    <p class="text-sm font-medium text-slate-900">{{ $e->equipment_name }}</p>
                    <x-badge :status="$e->availability_status === 'available' ? 'ok' : $e->availability_status" />
                </div>
            @empty
                <p class="text-sm text-slate-400">No equipment on record.</p>
            @endforelse
        </div>
    </div>
</div>
