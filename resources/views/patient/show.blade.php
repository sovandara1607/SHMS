@php
$initials = strtoupper(substr($patient->first_name, 0, 1) . substr($patient->last_name, 0, 1));
$age = $patient->date_of_birth ? \Carbon\Carbon::parse($patient->date_of_birth)->age : null;
@endphp

<div x-data="{ tab: 'basic' }" class="flex h-[80vh] flex-col">
    {{-- Stacks on mobile — identity row, then the action row below it —
         instead of squeezing the name, action button, and close icon into
         one row where none of them had enough width to render cleanly. --}}
    <div class="flex shrink-0 flex-col gap-3 border-b border-slate-100 px-4 py-4 sm:flex-row sm:items-start sm:justify-between sm:px-6">
        <div class="flex items-start justify-between gap-3">
            <div class="flex min-w-0 items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-600 text-sm font-semibold text-white">{{ $initials }}</div>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-semibold text-slate-900">{{ $patient->fullName() }}</span>
                        <x-badge :status="$patient->patient_status" />
                    </div>
                    <p class="text-xs text-slate-500">{{ $patient->patient_id }} &middot; {{ $age !== null ? $age.' yrs' : '—' }} &middot; {{ ucfirst($patient->gender ?? '—') }} &middot; {{ $patient->blood_type ?: '—' }}</p>
                </div>
            </div>
            <button type="button" x-on:click="show = false" class="shrink-0 text-slate-400 hover:text-slate-600 sm:hidden">
                <x-icon name="x" class="h-5 w-5" />
            </button>
        </div>
        <div class="flex items-center gap-2">
            @can('patient.update')
                <x-button variant="primary" class="w-full sm:w-auto" x-on:click="openPatientModal('/patients/{{ $patient->patient_id }}/edit')">Adjust Patient Info</x-button>
            @endcan
            <button type="button" x-on:click="show = false" class="hidden shrink-0 text-slate-400 hover:text-slate-600 sm:block">
                <x-icon name="x" class="h-5 w-5" />
            </button>
        </div>
    </div>

    <div class="flex min-h-0 flex-1 flex-col sm:flex-row">
        {{-- Section nav: horizontal scrolling strip on mobile, vertical icon rail from sm: up --}}
        <nav class="flex shrink-0 gap-1 overflow-x-auto border-b border-slate-100 px-3 py-2 sm:w-52 sm:flex-col sm:gap-0.5 sm:overflow-x-visible sm:overflow-y-auto sm:border-b-0 sm:border-r sm:p-3">
            @foreach([
                'basic' => ['Basic Information', 'clipboard', null],
                'appointments' => ['Appointments', 'calendar', $patient->appointments->count()],
                'doctors' => ['Doctor Assignments', 'users', $patient->doctorAssignments->count()],
                'nurses' => ['Nurse Assignments', 'users', $patient->nurseAssignments->count()],
                'rooms' => ['Room Assignments', 'bed', $patient->roomAssignments->count()],
                'records' => ['Medical Records', 'document', $patient->medicalRecords->count()],
                'billing' => ['Billing History', 'card', $patient->bills->count()],
                'history' => ['Adjustment History', 'eye', $patient->adjustments->count()],
            ] as $key => [$label, $icon, $count])
                <button type="button" @click="tab = '{{ $key }}'"
                        :class="tab === '{{ $key }}' ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50'"
                        class="flex shrink-0 items-center gap-2.5 whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium sm:w-full">
                    <x-icon :name="$icon" class="h-4 w-4 shrink-0" />
                    <span class="sm:flex-1 sm:text-left">{{ $label }}</span>
                    @if($count)
                        <span :class="tab === '{{ $key }}' ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-500'"
                              class="rounded-full px-1.5 py-0.5 text-xs font-semibold">{{ $count }}</span>
                    @endif
                </button>
            @endforeach
        </nav>

        <div class="min-h-0 flex-1 overflow-y-auto px-6 py-5">
        {{-- Basic Information --}}
        <div x-show="tab === 'basic'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="space-y-5">
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Personal Information</p>
                <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                    <div class="cursor-not-allowed rounded-lg border border-slate-100 bg-slate-50/60 px-3 py-2 opacity-80" title="Not directly editable — use Adjust Patient Info"><p class="text-xs text-slate-500">Full Name</p><p class="mt-0.5 text-sm font-medium text-slate-900">{{ $patient->fullName() }}</p></div>
                    <div class="cursor-not-allowed rounded-lg border border-slate-100 bg-slate-50/60 px-3 py-2 opacity-80" title="Not directly editable — use Adjust Patient Info"><p class="text-xs text-slate-500">Gender</p><p class="mt-0.5 text-sm text-slate-900">{{ ucfirst($patient->gender ?? '—') }}</p></div>
                    <div class="cursor-not-allowed rounded-lg border border-slate-100 bg-slate-50/60 px-3 py-2 opacity-80" title="Not directly editable — use Adjust Patient Info"><p class="text-xs text-slate-500">Date of Birth</p><p class="mt-0.5 text-sm text-slate-900">{{ $patient->date_of_birth ?? '—' }}{{ $age !== null ? " ($age yrs)" : '' }}</p></div>
                    <div class="cursor-not-allowed rounded-lg border border-slate-100 bg-slate-50/60 px-3 py-2 opacity-80" title="Not directly editable — use Adjust Patient Info"><p class="text-xs text-slate-500">Phone</p><p class="mt-0.5 text-sm text-slate-900">{{ $patient->phone_number ?: '—' }}</p></div>
                    <div class="cursor-not-allowed rounded-lg border border-slate-100 bg-slate-50/60 px-3 py-2 opacity-80" title="Not directly editable — use Adjust Patient Info"><p class="text-xs text-slate-500">Email</p><p class="mt-0.5 truncate text-sm text-slate-900">{{ $patient->email ?: '—' }}</p></div>
                    <div class="cursor-not-allowed rounded-lg border border-slate-100 bg-slate-50/60 px-3 py-2 opacity-80" title="Not directly editable — use Adjust Patient Info"><p class="text-xs text-slate-500">Address</p><p class="mt-0.5 text-sm text-slate-900">{{ $patient->address ?: '—' }}</p></div>
                </div>
            </div>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Medical Information</p>
                <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                    <div class="cursor-not-allowed rounded-lg border border-slate-100 bg-slate-50/60 px-3 py-2 opacity-80" title="Not directly editable — use Adjust Patient Info"><p class="text-xs text-slate-500">Blood Type</p><p class="mt-0.5 text-sm font-semibold text-red-600">{{ $patient->blood_type ?: '—' }}</p></div>
                    <div class="cursor-not-allowed rounded-lg border border-slate-100 bg-slate-50/60 px-3 py-2 opacity-80" title="Not directly editable — use Adjust Patient Info"><p class="text-xs text-slate-500">Allergies</p><p class="mt-0.5 text-sm text-slate-900">{{ $patient->allergy ?: '—' }}</p></div>
                </div>
            </div>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Emergency Contact</p>
                <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                    <div class="cursor-not-allowed rounded-lg border border-slate-100 bg-slate-50/60 px-3 py-2 opacity-80" title="Not directly editable — use Adjust Patient Info"><p class="text-xs text-slate-500">Contact Name</p><p class="mt-0.5 text-sm text-slate-900">{{ $patient->emergency_contact_name ?: '—' }}</p></div>
                    <div class="cursor-not-allowed rounded-lg border border-slate-100 bg-slate-50/60 px-3 py-2 opacity-80" title="Not directly editable — use Adjust Patient Info"><p class="text-xs text-slate-500">Contact Phone</p><p class="mt-0.5 text-sm text-slate-900">{{ $patient->emergency_contact_phone ?: '—' }}</p></div>
                </div>
            </div>
            @if($patient->insurance->isNotEmpty())
                @php($ins = $patient->insurance->first())
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Insurance Information</p>
                    <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                        <div class="cursor-not-allowed rounded-lg border border-slate-100 bg-slate-50/60 px-3 py-2 opacity-80" title="Not directly editable — use Adjust Patient Info"><p class="text-xs text-slate-500">Provider</p><p class="mt-0.5 text-sm text-slate-900">{{ $ins->insurance_provider ?: '—' }}</p></div>
                        <div class="cursor-not-allowed rounded-lg border border-slate-100 bg-slate-50/60 px-3 py-2 opacity-80" title="Not directly editable — use Adjust Patient Info"><p class="text-xs text-slate-500">Policy Number</p><p class="mt-0.5 text-sm text-slate-900">{{ $ins->policy_number ?: '—' }}</p></div>
                        <div class="cursor-not-allowed rounded-lg border border-slate-100 bg-slate-50/60 px-3 py-2 opacity-80" title="Not directly editable — use Adjust Patient Info"><p class="text-xs text-slate-500">Coverage</p><p class="mt-0.5 text-sm text-slate-900">{{ $ins->coverage_details ?: '—' }}</p></div>
                        <div class="cursor-not-allowed rounded-lg border border-slate-100 bg-slate-50/60 px-3 py-2 opacity-80" title="Not directly editable — use Adjust Patient Info"><p class="text-xs text-slate-500">Policy Period</p><p class="mt-0.5 text-sm text-slate-900">{{ $ins->start_date ?? '—' }} &rarr; {{ $ins->end_date ?? '—' }}</p></div>
                    </div>
                </div>
            @endif
            @if(auth()->user()->hasPermission('patient.discharge') && $patient->patient_status !== 'discharged')
                <form method="post" action="/patients/{{ $patient->patient_id }}/discharge" onsubmit="return confirm('Discharge this patient?')">
                    @csrf
                    <x-button variant="danger" type="submit">Discharge Patient</x-button>
                </form>
            @endif
        </div>

        {{-- Appointments --}}
        <div x-show="tab === 'appointments'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
            <p class="mb-3 text-xs text-slate-400">Outpatient and consultant visits. Manage appointments on the Appointments page.</p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
                        <th class="pb-2">Doctor</th><th class="pb-2">Date</th><th class="pb-2">Time</th><th class="pb-2">Reason</th><th class="pb-2">Status</th>
                    </tr></thead>
                    <tbody>
                    @forelse($patient->appointments as $a)
                        <tr class="border-b border-slate-50">
                            <td class="py-2">{{ $a->doctor?->name() ?? '—' }}</td>
                            <td class="py-2">{{ $a->appointment_date }}</td>
                            <td class="py-2">{{ $a->appointment_time }}</td>
                            <td class="py-2">{{ $a->reason ?: '—' }}</td>
                            <td class="py-2"><x-badge :status="$a->status" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-4 text-center text-slate-400">No appointments.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Doctor Assignments --}}
        <div x-show="tab === 'doctors'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <p class="text-xs text-slate-400">Long-term or special care only — Main Doctor, Consultant, or Specialist for admitted patients.</p>
                @can('patient.update')
                    <x-button variant="primary" x-on:click="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'add-doctor-assignment' }))">+ Add Doctor Assignment</x-button>
                @endcan
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
                        <th class="pb-2">Doctor</th><th class="pb-2">Role</th><th class="pb-2">Assigned By</th><th class="pb-2">Assigned At</th><th class="pb-2">Status</th><th class="pb-2"></th>
                    </tr></thead>
                    <tbody>
                    @forelse($patient->doctorAssignments as $da)
                        <tr class="border-b border-slate-50">
                            <td class="py-2">{{ $da->doctor?->name() ?? '—' }}</td>
                            <td class="py-2"><x-badge :status="$da->role" /></td>
                            <td class="py-2">{{ $da->assignedByStaff?->fullName() ?? '—' }}</td>
                            <td class="py-2">{{ $da->assigned_at }}</td>
                            <td class="py-2"><x-badge :status="$da->status" /></td>
                            <td class="py-2 text-right">
                                @can('patient.update')
                                    @if($da->status === 'active')
                                        <form method="post" action="/doctor-assignments/{{ $da->assignment_id }}/end" onsubmit="return confirm('End this assignment?')">
                                            @csrf
                                            <button class="text-xs font-medium text-red-600 hover:underline">End</button>
                                        </form>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-4 text-center text-slate-400">No doctor assignments.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Nurse Assignments --}}
        <div x-show="tab === 'nurses'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <p class="text-xs text-slate-400">Assigned when patient is admitted or needs shift-based nursing care.</p>
                @can('patient.update')
                    <x-button variant="primary" x-on:click="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'add-nurse-assignment' }))">+ Add Nurse Assignment</x-button>
                @endcan
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
                        <th class="pb-2">Nurse</th><th class="pb-2">Shift</th><th class="pb-2">Assigned By</th><th class="pb-2">Assigned At</th><th class="pb-2">Status</th><th class="pb-2"></th>
                    </tr></thead>
                    <tbody>
                    @forelse($patient->nurseAssignments as $na)
                        <tr class="border-b border-slate-50">
                            <td class="py-2">{{ $na->nurse?->name() ?? '—' }}</td>
                            <td class="py-2">{{ $na->shift ? "{$na->shift->shift_type} ({$na->shift->start_time}-{$na->shift->end_time})" : '—' }}</td>
                            <td class="py-2">{{ $na->assignedByStaff?->fullName() ?? '—' }}</td>
                            <td class="py-2">{{ $na->assigned_at }}</td>
                            <td class="py-2"><x-badge :status="$na->status" /></td>
                            <td class="py-2 text-right">
                                @can('patient.update')
                                    @if($na->status === 'active')
                                        <form method="post" action="/nurse-assignments/{{ $na->assignment_id }}/end" onsubmit="return confirm('End this assignment?')">
                                            @csrf
                                            <button class="text-xs font-medium text-red-600 hover:underline">End</button>
                                        </form>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-4 text-center text-slate-400">No nurse assignments.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Room Assignments --}}
        <div x-show="tab === 'rooms'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
            <p class="mb-3 text-xs text-slate-400">
                Room and bed assignment history.
                @can('room.assign')
                    Assign a bed from <a href="/rooms" class="font-medium text-blue-600 hover:underline">Rooms &amp; Beds</a>.
                @endcan
            </p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
                        <th class="pb-2">Room</th><th class="pb-2">Type</th><th class="pb-2">Bed</th><th class="pb-2">Admitted At</th><th class="pb-2">Discharged At</th><th class="pb-2">Status</th><th class="pb-2"></th>
                    </tr></thead>
                    <tbody>
                    @forelse($patient->roomAssignments as $ra)
                        <tr class="border-b border-slate-50">
                            <td class="py-2">{{ $ra->room?->room_number ?? '—' }}</td>
                            <td class="py-2">{{ $ra->room?->room_type ?? '—' }}</td>
                            <td class="py-2">{{ $ra->bed?->bed_number ?? '—' }}</td>
                            <td class="py-2">{{ $ra->assigned_at }}</td>
                            <td class="py-2">{{ $ra->released_at ?? '—' }}</td>
                            <td class="py-2"><x-badge :status="$ra->status" /></td>
                            <td class="py-2 text-right">
                                @can('room.assign')
                                    @if($ra->status === 'active')
                                        <form method="post" action="/room-assignments/{{ $ra->room_assignment_id }}/release" onsubmit="return confirm('Release this bed?')">
                                            @csrf
                                            <button type="submit" class="text-sm font-medium text-red-600 hover:underline">Release</button>
                                        </form>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-4 text-center text-slate-400">No room assignments.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Medical Records --}}
        <div x-show="tab === 'records'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
            <p class="mb-3 text-xs text-slate-400">Medical records linked to this patient. Manage records on the Medical Records page.</p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
                        <th class="pb-2">Record</th><th class="pb-2">Doctor</th><th class="pb-2">Date</th><th class="pb-2">Diagnosis</th>
                    </tr></thead>
                    <tbody>
                    @forelse($patient->medicalRecords as $mr)
                        <tr class="border-b border-slate-50">
                            <td class="py-2 font-medium text-blue-600">{{ $mr->medical_record_id }}</td>
                            <td class="py-2">{{ $mr->doctor?->name() ?? '—' }}</td>
                            <td class="py-2">{{ $mr->created_at }}</td>
                            <td class="py-2">{{ $mr->diagnosis ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-4 text-center text-slate-400">No medical records.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Billing History --}}
        <div x-show="tab === 'billing'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
            <p class="mb-3 text-xs text-slate-400">Billing records for this patient. Manage billing on the Billing &amp; Payments page.</p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
                        <th class="pb-2">Bill</th><th class="pb-2">Date</th><th class="pb-2">Amount</th><th class="pb-2">Status</th>
                    </tr></thead>
                    <tbody>
                    @forelse($patient->bills as $bill)
                        <tr class="border-b border-slate-50">
                            <td class="py-2 font-medium text-blue-600">{{ $bill->bill_id }}</td>
                            <td class="py-2">{{ $bill->bill_date }}</td>
                            <td class="py-2">${{ number_format($bill->total_amount, 2) }}</td>
                            <td class="py-2"><x-badge :status="$bill->status" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-4 text-center text-slate-400">No billing history.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Adjustment History --}}
        <div x-show="tab === 'history'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
            <p class="mb-3 text-xs text-slate-400">The original patient record is never overwritten &mdash; every edit is logged here instead.</p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
                        <th class="pb-2">Adjusted At</th><th class="pb-2">By</th><th class="pb-2">Changes</th><th class="pb-2">Reason</th>
                    </tr></thead>
                    <tbody>
                    @forelse($patient->adjustments as $adj)
                        <tr class="border-b border-slate-50">
                            <td class="py-2 align-top">{{ $adj->adjusted_at }}</td>
                            <td class="py-2 align-top">{{ $adj->adjusted_by_name ?? $adj->adjusted_by }}</td>
                            <td class="py-2 align-top">
                                @forelse($adj->changes as $c)
                                    <p class="text-xs text-slate-600">{{ $c }}</p>
                                @empty
                                    <span class="text-slate-400">&mdash;</span>
                                @endforelse
                            </td>
                            <td class="py-2 align-top">{{ $adj->reason }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-4 text-center text-slate-400">No adjustments — original preserved.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        </div>
    </div>
</div>

{{-- Nested: Add Doctor Assignment --}}
<x-modal name="add-doctor-assignment" max-width="sm">
    <x-modal-header title="Add Doctor Assignment" />
    <form method="post" action="/patients/{{ $patient->patient_id }}/doctor-assignments" class="space-y-4 px-6 py-5">
        @csrf
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Doctor *</label>
            <select name="doctor_id" required class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                <option value="">— select —</option>
                @foreach($doctors as $doc)
                    <option value="{{ $doc->doctor_id }}">{{ $doc->name }}@if($doc->specialization) — {{ $doc->specialization }}@endif</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Doctor Role *</label>
            <select name="role" required class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                <option value="main_doctor">Main Doctor</option>
                <option value="consultant">Consultant</option>
                <option value="specialist">Specialist</option>
            </select>
        </div>
        <div class="rounded-lg bg-blue-50 px-4 py-3 text-xs text-blue-700">
            <p class="font-semibold uppercase tracking-wider">System-Generated</p>
            <p class="mt-1">Assigned By: {{ auth()->user()->displayName() }}</p>
            <p>Assigned At: {{ now()->format('Y-m-d H:i') }}</p>
        </div>
        <div class="flex justify-end gap-2 pt-2">
            <x-button variant="secondary" type="button" x-on:click="show = false">Cancel</x-button>
            <x-button variant="primary" type="submit">Add Assignment</x-button>
        </div>
    </form>
</x-modal>

{{-- Nested: Add Nurse Assignment --}}
<x-modal name="add-nurse-assignment" max-width="sm">
    <x-modal-header title="Add Nurse Assignment" />
    <form method="post" action="/patients/{{ $patient->patient_id }}/nurse-assignments" class="space-y-4 px-6 py-5">
        @csrf
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Nurse *</label>
            <select name="nurse_id" required class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                <option value="">— select —</option>
                @foreach($nurses as $n)
                    <option value="{{ $n->nurse_id }}">{{ $n->name }}@if($n->ward_name) — {{ $n->ward_name }}@endif</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Shift</label>
            <select name="shift_id" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                <option value="">— none —</option>
                @foreach($shifts as $s)
                    <option value="{{ $s->shift_id }}">{{ $s->shift_date }} — {{ ucfirst($s->shift_type) }} ({{ $s->start_time }}-{{ $s->end_time }})</option>
                @endforeach
            </select>
        </div>
        <div class="rounded-lg bg-blue-50 px-4 py-3 text-xs text-blue-700">
            <p class="font-semibold uppercase tracking-wider">System-Generated</p>
            <p class="mt-1">Assigned By: {{ auth()->user()->displayName() }}</p>
            <p>Assigned At: {{ now()->format('Y-m-d H:i') }}</p>
        </div>
        <div class="flex justify-end gap-2 pt-2">
            <x-button variant="secondary" type="button" x-on:click="show = false">Cancel</x-button>
            <x-button variant="primary" type="submit">Add Assignment</x-button>
        </div>
    </form>
</x-modal>
