@php($actions = [
    ['label' => 'Register Patient', 'href' => '/patients', 'color' => 'bg-blue-600 hover:bg-blue-700', 'cap' => 'patient.create'],
    ['label' => 'Book Appointment', 'href' => '/appointments', 'color' => 'bg-green-600 hover:bg-green-700', 'cap' => 'appointment.create'],
    ['label' => 'Assign Room', 'href' => '/rooms', 'color' => 'bg-amber-500 hover:bg-amber-600', 'cap' => 'room.assign'],
    ['label' => 'Create Bill', 'href' => '/bills', 'color' => 'bg-red-600 hover:bg-red-700', 'cap' => 'bill.create'],
])

<div class="mb-5 flex flex-wrap gap-2">
    @foreach($actions as $a)
        @can($a['cap'])
            <a href="{{ $a['href'] }}" class="inline-flex items-center gap-1.5 rounded-lg {{ $a['color'] }} px-4 py-2 text-sm font-semibold text-white">
                <x-icon name="plus" class="h-4 w-4" /> {{ $a['label'] }}
            </a>
        @endcan
    @endforeach
</div>

<div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
    <x-stat-card label="Today's Check-ins" :value="$stats['checkins_today']" icon="users" icon-color="green" />
    <x-stat-card label="Pending Appointments" :value="$stats['pending_appointments']" icon="calendar" icon-color="blue" />
    <x-stat-card label="Available Rooms" :value="$stats['available_rooms']" icon="bed" icon-color="amber" />
    <x-stat-card label="Unpaid Bills" :value="$stats['unpaid_bills']" icon="card" icon-color="red" />
</div>

<div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
    <div class="rounded-xl border border-slate-200 bg-white p-5">
        <p class="mb-3 font-semibold text-slate-900">Upcoming Appointments</p>
        <div class="space-y-3">
            @forelse($upcomingAppointments as $a)
                <div class="flex items-center justify-between border-b border-slate-50 pb-2 last:border-0">
                    <div>
                        <p class="text-sm font-medium text-slate-900">{{ $a->patient_name }}</p>
                        <p class="text-xs text-slate-400">{{ $a->doctor_name }} &middot; {{ $a->appointment_date }}</p>
                    </div>
                    <x-badge :status="$a->status" />
                </div>
            @empty
                <p class="text-sm text-slate-400">No upcoming appointments.</p>
            @endforelse
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5">
        <p class="mb-3 font-semibold text-slate-900">Outstanding Bills</p>
        <div class="space-y-3">
            @forelse($outstandingBills as $b)
                <div class="flex items-center justify-between border-b border-slate-50 pb-2 last:border-0">
                    <p class="text-sm font-medium text-slate-900">{{ $b->patient_name }} &middot; ${{ number_format((float) $b->total_amount, 2) }}</p>
                    <x-badge :status="$b->status" />
                </div>
            @empty
                <p class="text-sm text-slate-400">No outstanding bills.</p>
            @endforelse
        </div>
    </div>
</div>
