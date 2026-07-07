@can('staff.manage')
    <div class="mb-5 flex justify-end">
        <a href="/staff" class="inline-flex items-center gap-1.5 rounded-lg bg-purple-600 px-4 py-2 text-sm font-semibold text-white hover:bg-purple-700">
            <x-icon name="plus" class="h-4 w-4" /> Add Staff
        </a>
    </div>
@endcan

<div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
    <x-stat-card label="Total Patients" :value="$stats['patients']" icon="users" icon-color="blue" />
    <x-stat-card label="Total Staff" :value="$stats['staff']" icon="users" icon-color="green" />
    <x-stat-card label="Today's Appointments" :value="$stats['appointments']" icon="calendar" icon-color="purple" />
    <x-stat-card label="Available Rooms" :value="$stats['rooms']" icon="bed" icon-color="amber" />
    <x-stat-card label="Pending Lab Tests" :value="$stats['lab_pending']" icon="flask" icon-color="red" />
    <x-stat-card label="Monthly Revenue" value="${{ number_format($stats['revenue'], 0) }}" icon="card" icon-color="green" />
</div>

<div class="mb-5 rounded-xl border border-slate-200 bg-white p-5">
    <p class="mb-3 font-semibold text-slate-900">Department Overview</p>
    <table class="w-full text-sm">
        <thead><tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
            <th class="pb-2">Department Name</th><th class="pb-2">Staff Count</th><th class="pb-2">Available Rooms</th>
        </tr></thead>
        <tbody>
        @forelse($departments as $d)
            <tr class="border-b border-slate-50">
                <td class="py-2 font-medium text-slate-900">{{ $d->department_name }}</td>
                <td class="py-2 text-slate-600">{{ $d->staff_count }}</td>
                <td class="py-2 text-slate-600">{{ $d->available_rooms }}</td>
            </tr>
        @empty
            <tr><td colspan="3" class="py-4 text-center text-slate-400">No departments yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
    <div class="rounded-xl border border-slate-200 bg-white p-5 lg:col-span-2">
        <p class="mb-3 font-semibold text-slate-900">Today's Hospital Schedule</p>
        <table class="w-full text-sm">
            <thead><tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
                <th class="pb-2">Time</th><th class="pb-2">Doctor</th><th class="pb-2">Reason</th><th class="pb-2">Status</th>
            </tr></thead>
            <tbody>
            @forelse($todaySchedule as $s)
                <tr class="border-b border-slate-50">
                    <td class="py-2">{{ \Illuminate\Support\Str::substr($s->appointment_time, 0, 5) }}</td>
                    <td class="py-2 text-slate-900">{{ $s->doctor_name }}</td>
                    <td class="py-2 text-slate-600">{{ $s->reason ?: '—' }}</td>
                    <td class="py-2"><x-badge :status="$s->status" /></td>
                </tr>
            @empty
                <tr><td colspan="4" class="py-4 text-center text-slate-400">No appointments today.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5">
        <p class="mb-3 font-semibold text-slate-900">Operations Summary</p>
        <dl class="space-y-3 text-sm">
            <div class="flex justify-between"><dt class="text-slate-500">Active Doctors Today</dt><dd class="font-medium text-slate-900">{{ $operations['active_doctors'] }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Occupied Beds</dt><dd class="font-medium text-slate-900">{{ $operations['occupied_beds'] }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Pending Lab Tests</dt><dd class="font-medium text-slate-900">{{ $operations['pending_labs'] }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Pending Payments</dt><dd class="font-medium text-slate-900">{{ $operations['unpaid_bills'] }}</dd></div>
        </dl>
    </div>
</div>
