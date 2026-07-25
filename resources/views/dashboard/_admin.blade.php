@can('staff.manage')
    <div class="mb-5 flex justify-end">
        <a href="/staff" class="inline-flex items-center gap-1.5 rounded-lg bg-purple-600 px-4 py-2 text-sm font-semibold text-white hover:bg-purple-700">
            <x-icon name="plus" class="h-4 w-4" /> Add Staff
        </a>
    </div>
@endcan

@php
$u = auth()->user();
$reportUrls = [
    'patients'     => $u->hasPermission('patient.view') ? '/patients' : null,
    'staff'        => $u->hasPermission('staff.manage') ? '/staff' : null,
    'appointments' => $u->hasPermission('appointment.view') ? '/appointments' : null,
    'rooms'        => $u->hasPermission('room.view') ? '/rooms' : null,
    'lab_pending'  => $u->hasPermission('lab_order.view') ? '/lab-orders' : null,
    'revenue'      => $u->hasPermission('bill.view') ? '/bills' : null,
];
@endphp

<div class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
    <x-stat-card label="Total Patients" :value="number_format($stats['patients'])" icon="users" icon-color="blue" :badge="$kpiBadges['patients']" :report-url="$reportUrls['patients']" />
    <x-stat-card label="Total Staff" :value="number_format($stats['staff'])" icon="users" icon-color="green" :badge="$kpiBadges['staff']" :report-url="$reportUrls['staff']" />
    <x-stat-card label="Today's Appointments" :value="number_format($stats['appointments'])" icon="calendar" icon-color="purple" :badge="$kpiBadges['appointments']" :report-url="$reportUrls['appointments']" />
    <x-stat-card label="Available Rooms" :value="number_format($stats['rooms'])" icon="bed" icon-color="amber" :badge="$kpiBadges['rooms']" :report-url="$reportUrls['rooms']" />
    <x-stat-card label="Pending Lab Tests" :value="number_format($stats['lab_pending'])" icon="flask" icon-color="red" :badge="$kpiBadges['lab_pending']" :report-url="$reportUrls['lab_pending']" />
    <x-stat-card label="Monthly Revenue" value="${{ number_format($stats['revenue'], 0) }}" icon="card" icon-color="green" :badge="$kpiBadges['revenue']" :report-url="$reportUrls['revenue']" />
</div>

<div class="mb-5 rounded-xl border border-slate-200 bg-white p-5">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <div>
            <p class="font-semibold text-slate-900">Appointments &amp; Lab Orders</p>
            <p class="text-xs text-slate-400">Last 9 months</p>
        </div>
        <div class="flex items-center gap-4 text-xs text-slate-500">
            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full" style="background-color: #2a78d6"></span> Appointments</span>
            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full" style="background-color: #1baf7a"></span> Lab Orders</span>
        </div>
    </div>
    @php $trendMax = max(1, collect($monthlyTrend)->flatMap(fn ($m) => [$m['appointments'], $m['lab_orders']])->max()); @endphp
    <div class="flex h-48 items-end gap-2 sm:gap-4">
        @foreach($monthlyTrend as $m)
            <div class="flex flex-1 flex-col items-center gap-1.5">
                <div class="flex h-40 w-full items-end justify-center gap-1">
                    <div class="w-2.5 rounded-t-sm sm:w-3.5" style="height: {{ max(2, $m['appointments'] / $trendMax * 100) }}%; background-color: #2a78d6" title="{{ $m['label'] }} — Appointments: {{ number_format($m['appointments']) }}"></div>
                    <div class="w-2.5 rounded-t-sm sm:w-3.5" style="height: {{ max(2, $m['lab_orders'] / $trendMax * 100) }}%; background-color: #1baf7a" title="{{ $m['label'] }} — Lab Orders: {{ number_format($m['lab_orders']) }}"></div>
                </div>
                <span class="text-xs text-slate-400">{{ $m['label'] }}</span>
            </div>
        @endforeach
    </div>
</div>

<div class="mb-5 grid grid-cols-1 items-start gap-5 lg:grid-cols-3">
    <div class="rounded-xl border border-slate-200 bg-white p-5">
        <p class="mb-4 font-semibold text-slate-900">Lab Test Breakdown</p>
        @php
            $labTotal = max(1, collect($labBreakdown)->sum('value'));
            $cursor = 0;
            $stops = [];
            foreach ($labBreakdown as $seg) {
                $start = $cursor / $labTotal * 360;
                $cursor += $seg['value'];
                $end = $cursor / $labTotal * 360;
                $stops[] = "{$seg['color']} {$start}deg {$end}deg";
            }
            $gradient = implode(', ', $stops);
        @endphp
        <div class="flex flex-col items-center gap-4">
            <div class="relative h-28 w-28 shrink-0 rounded-full" style="background: conic-gradient({{ $gradient }})">
                <div class="absolute inset-3 rounded-full bg-white"></div>
            </div>
            <div class="w-full min-w-0 space-y-2">
                @foreach($labBreakdown as $seg)
                    <div class="flex items-center gap-2 text-xs">
                        <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $seg['color'] }}"></span>
                        <span class="min-w-0 flex-1 truncate text-slate-600">{{ $seg['label'] }}</span>
                        <span class="shrink-0 font-medium text-slate-900">{{ number_format($seg['value']) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5">
        <p class="mb-4 font-semibold text-slate-900">Next Appointment</p>
        @if($nextAppointment)
            <div class="mb-4 flex items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-100 text-sm font-semibold text-blue-700">
                    {{ strtoupper(substr($nextAppointment['patient_name'], 0, 1)) }}
                </div>
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-slate-900">{{ $nextAppointment['patient_name'] }}</p>
                    <p class="truncate text-xs text-slate-400">{{ $nextAppointment['reason'] ?: 'Appointment' }} &middot; {{ \Illuminate\Support\Str::substr($nextAppointment['appointment_time'], 0, 5) }}</p>
                </div>
            </div>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <div><dt class="text-xs text-slate-400">Patient ID</dt><dd class="font-medium text-slate-900">{{ $nextAppointment['patient_id'] }}</dd></div>
                <div><dt class="text-xs text-slate-400">Last Visit</dt><dd class="font-medium text-slate-900">{{ $nextAppointment['last_visit'] ?? 'First visit' }}</dd></div>
                <div><dt class="text-xs text-slate-400">Gender</dt><dd class="font-medium capitalize text-slate-900">{{ $nextAppointment['gender'] }}</dd></div>
                <div><dt class="text-xs text-slate-400">Age</dt><dd class="font-medium text-slate-900">{{ $nextAppointment['age'] }}</dd></div>
                <div><dt class="text-xs text-slate-400">Blood Type</dt><dd class="font-medium text-slate-900">{{ $nextAppointment['blood_type'] ?: '—' }}</dd></div>
            </dl>
        @else
            <p class="py-4 text-center text-sm text-slate-400">No upcoming appointments.</p>
        @endif
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5">
        <p class="mb-4 font-semibold text-slate-900">Pending Lab Results</p>
        <div class="space-y-3">
            @forelse($pendingLabResults as $r)
                <a href="/lab-orders" class="-mx-2 flex items-center justify-between gap-2 rounded-lg border-b border-slate-50 px-2 pb-3 last:border-0 last:pb-0 hover:bg-slate-50">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-slate-900">{{ $r['patient_name'] }}</p>
                        <p class="truncate text-xs text-slate-400">{{ $r['test_name'] }} &middot; {{ \Illuminate\Support\Str::substr($r['order_date'], 0, 10) }}</p>
                    </div>
                    <x-badge :status="$r['status']" />
                </a>
            @empty
                <p class="py-4 text-center text-sm text-slate-400">No pending lab results.</p>
            @endforelse
        </div>
    </div>
</div>

<div class="mb-5 grid grid-cols-1 gap-5 sm:grid-cols-3">
    <div class="rounded-xl border border-slate-200 bg-white p-5">
        <p class="mb-2 font-semibold text-slate-900">Completion Rate</p>
        <div class="flex items-center gap-2">
            <p class="text-3xl font-bold text-slate-900">{{ $completionRate }}%</p>
            <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-600">Today</span>
        </div>
        <p class="mt-1 text-xs text-slate-400">Appointments completed vs scheduled today</p>
        @php $sparkMax = max(1, max($completionSparkline)); @endphp
        <div class="mt-4 flex h-10 items-end gap-1">
            @foreach($completionSparkline as $v)
                <div class="flex-1 rounded-t-sm bg-blue-200 last:bg-blue-600" style="height: {{ max(8, $v / $sparkMax * 100) }}%" title="{{ $v }}%"></div>
            @endforeach
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5">
        <p class="mb-2 font-semibold text-slate-900">Patients Seen This Month</p>
        <p class="text-3xl font-bold text-slate-900">{{ number_format($patientsThisMonth) }}</p>
        <p class="mt-1 text-xs text-slate-400">Distinct patients with an appointment this month</p>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5">
        <div class="flex items-start justify-between gap-2">
            <div>
                <p class="mb-2 font-semibold text-slate-900">Earning</p>
                <p class="text-3xl font-bold text-slate-900">${{ number_format($stats['revenue'], 0) }}</p>
                <p class="mt-1 text-xs text-slate-400">This month so far</p>
            </div>
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-green-50 text-green-600">
                <x-icon name="card" class="h-5 w-5" />
            </div>
        </div>
    </div>
</div>

<div class="mb-5 rounded-xl border border-slate-200 bg-white p-5" x-data="{ view: 'chart' }">
    <div class="mb-3 flex items-center justify-between">
        <p class="font-semibold text-slate-900">Department Overview</p>
        <div class="flex rounded-lg border border-slate-200 p-0.5 text-xs font-medium">
            <button type="button" x-on:click="view = 'chart'" :class="view === 'chart' ? 'bg-slate-900 text-white' : 'text-slate-500 hover:text-slate-700'" class="rounded-md px-2.5 py-1 transition-colors">Chart</button>
            <button type="button" x-on:click="view = 'table'" :class="view === 'table' ? 'bg-slate-900 text-white' : 'text-slate-500 hover:text-slate-700'" class="rounded-md px-2.5 py-1 transition-colors">Table</button>
        </div>
    </div>

    <div x-show="view === 'chart'">
        @if(count($departments))
            @php $maxDeptValue = max(1, collect($departments)->flatMap(fn ($d) => [$d['staff_count'], $d['available_rooms']])->max()); @endphp
            <div class="mb-3 flex items-center gap-4 text-xs text-slate-500">
                <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full" style="background-color: #2a78d6"></span> Staff Count</span>
                <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full" style="background-color: #1baf7a"></span> Available Rooms</span>
            </div>
            <div class="space-y-3">
                @foreach($departments as $d)
                    <div class="flex items-center gap-3">
                        <div class="w-28 shrink-0 truncate text-sm font-medium text-slate-900" title="{{ $d['department_name'] }}">{{ $d['department_name'] }}</div>
                        <div class="flex-1 space-y-1">
                            <div class="flex items-center gap-2" title="{{ $d['department_name'] }} — Staff Count: {{ $d['staff_count'] }}">
                                <div class="h-2.5 flex-1 rounded-full bg-slate-100">
                                    <div class="h-2.5 rounded-full" style="width: {{ $d['staff_count'] / $maxDeptValue * 100 }}%; background-color: #2a78d6"></div>
                                </div>
                                <span class="w-6 shrink-0 text-right text-xs tabular-nums text-slate-500">{{ $d['staff_count'] }}</span>
                            </div>
                            <div class="flex items-center gap-2" title="{{ $d['department_name'] }} — Available Rooms: {{ $d['available_rooms'] }}">
                                <div class="h-2.5 flex-1 rounded-full bg-slate-100">
                                    <div class="h-2.5 rounded-full" style="width: {{ $d['available_rooms'] / $maxDeptValue * 100 }}%; background-color: #1baf7a"></div>
                                </div>
                                <span class="w-6 shrink-0 text-right text-xs tabular-nums text-slate-500">{{ $d['available_rooms'] }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="py-4 text-center text-sm text-slate-400">No departments yet.</p>
        @endif
    </div>

    <div x-show="view === 'table'" style="display:none">
        <table class="w-full text-sm">
            <thead><tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
                <th class="pb-2">Department Name</th><th class="pb-2">Staff Count</th><th class="pb-2">Available Rooms</th>
            </tr></thead>
            <tbody>
            @forelse($departments as $d)
                <tr class="border-b border-slate-50">
                    <td class="py-2 font-medium text-slate-900">{{ $d['department_name'] }}</td>
                    <td class="py-2 text-slate-600">{{ $d['staff_count'] }}</td>
                    <td class="py-2 text-slate-600">{{ $d['available_rooms'] }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="py-4 text-center text-slate-400">No departments yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="grid grid-cols-1 items-start gap-5 lg:grid-cols-3">
    <div class="rounded-xl border border-slate-200 bg-white p-5 lg:col-span-2">
        <p class="mb-3 font-semibold text-slate-900">Today's Hospital Schedule</p>
        <table class="w-full text-sm">
            <thead><tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
                <th class="pb-2">Time</th><th class="pb-2">Doctor</th><th class="pb-2">Reason</th><th class="pb-2">Status</th>
            </tr></thead>
            <tbody>
            @forelse($todaySchedule as $s)
                <tr class="border-b border-slate-50">
                    <td class="py-2">{{ \Illuminate\Support\Str::substr($s['appointment_time'], 0, 5) }}</td>
                    <td class="py-2 text-slate-900">{{ $s['doctor_name'] }}</td>
                    <td class="py-2 text-slate-600">{{ $s['reason'] ?: '—' }}</td>
                    <td class="py-2"><x-badge :status="$s['status']" /></td>
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
