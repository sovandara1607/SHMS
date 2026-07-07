@php
$initials = strtoupper(substr($patient->first_name, 0, 1) . substr($patient->last_name, 0, 1));
$age = $patient->date_of_birth ? \Carbon\Carbon::parse($patient->date_of_birth)->age : null;
@endphp

<div x-data="{ tab: 'basic' }">
    <div class="flex items-start justify-between border-b border-slate-100 px-6 py-4">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-600 text-sm font-semibold text-white">{{ $initials }}</div>
            <div>
                <div class="flex items-center gap-2">
                    <span class="font-semibold text-slate-900">{{ $patient->fullName() }}</span>
                    <x-badge :status="$patient->patient_status" />
                </div>
                <p class="text-xs text-slate-500">{{ $patient->patient_id }} &middot; {{ $age !== null ? $age.' yrs' : '—' }} &middot; {{ ucfirst($patient->gender ?? '—') }} &middot; {{ $patient->blood_type ?: '—' }}</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            @can('patient.update')
                <x-button variant="primary" x-on:click="openPatientModal('/patients/{{ $patient->patient_id }}/edit')">Edit Patient</x-button>
            @endcan
            <button type="button" x-on:click="show = false" class="text-slate-400 hover:text-slate-600">
                <x-icon name="x" class="h-5 w-5" />
            </button>
        </div>
    </div>

    <div class="flex flex-wrap gap-1 border-b border-slate-100 px-6 pt-3 text-sm">
        @foreach([
            'basic' => 'Basic Information',
            'appointments' => 'Appointments',
            'doctors' => 'Doctor Assignments',
            'nurses' => 'Nurse Assignments',
            'rooms' => 'Room Assignments',
            'records' => 'Medical Records',
            'billing' => 'Billing History',
        ] as $key => $label)
            <button type="button" @click="tab = '{{ $key }}'"
                    :class="tab === '{{ $key }}' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-700'"
                    class="-mb-px border-b-2 px-3 pb-2.5 font-medium">{{ $label }}</button>
        @endforeach
    </div>

    <div class="max-h-[65vh] overflow-y-auto px-6 py-5">
        {{-- Basic Information --}}
        <div x-show="tab === 'basic'" class="space-y-5">
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Personal Information</p>
                <dl class="grid grid-cols-2 gap-y-2 text-sm">
                    <dt class="text-slate-500">Full Name</dt><dd class="text-right font-medium text-slate-900">{{ $patient->fullName() }}</dd>
                    <dt class="text-slate-500">Gender</dt><dd class="text-right text-slate-900">{{ ucfirst($patient->gender ?? '—') }}</dd>
                    <dt class="text-slate-500">Date of Birth</dt><dd class="text-right text-slate-900">{{ $patient->date_of_birth ?? '—' }}{{ $age !== null ? " ($age yrs)" : '' }}</dd>
                    <dt class="text-slate-500">Phone</dt><dd class="text-right text-slate-900">{{ $patient->phone_number ?: '—' }}</dd>
                    <dt class="text-slate-500">Email</dt><dd class="text-right text-slate-900">{{ $patient->email ?: '—' }}</dd>
                    <dt class="text-slate-500">Address</dt><dd class="text-right text-slate-900">{{ $patient->address ?: '—' }}</dd>
                </dl>
            </div>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Medical Information</p>
                <dl class="grid grid-cols-2 gap-y-2 text-sm">
                    <dt class="text-slate-500">Blood Type</dt><dd class="text-right font-medium text-red-600">{{ $patient->blood_type ?: '—' }}</dd>
                    <dt class="text-slate-500">Allergies</dt><dd class="text-right text-slate-900">{{ $patient->allergy ?: '—' }}</dd>
                </dl>
            </div>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Emergency Contact</p>
                <dl class="grid grid-cols-2 gap-y-2 text-sm">
                    <dt class="text-slate-500">Contact Name</dt><dd class="text-right text-slate-900">{{ $patient->emergency_contact_name ?: '—' }}</dd>
                    <dt class="text-slate-500">Contact Phone</dt><dd class="text-right text-slate-900">{{ $patient->emergency_contact_phone ?: '—' }}</dd>
                </dl>
            </div>
            @if($patient->insurance->isNotEmpty())
                @php($ins = $patient->insurance->first())
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Insurance Information</p>
                    <dl class="grid grid-cols-2 gap-y-2 text-sm">
                        <dt class="text-slate-500">Provider</dt><dd class="text-right text-slate-900">{{ $ins->insurance_provider ?: '—' }}</dd>
                        <dt class="text-slate-500">Policy Number</dt><dd class="text-right text-slate-900">{{ $ins->policy_number ?: '—' }}</dd>
                        <dt class="text-slate-500">Coverage</dt><dd class="text-right text-slate-900">{{ $ins->coverage_details ?: '—' }}</dd>
                        <dt class="text-slate-500">Policy Period</dt><dd class="text-right text-slate-900">{{ $ins->start_date ?? '—' }} &rarr; {{ $ins->end_date ?? '—' }}</dd>
                    </dl>
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
        <div x-show="tab === 'appointments'">
            <p class="mb-3 text-xs text-slate-400">Outpatient and consultant visits. Manage appointments on the Appointments page.</p>
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

        {{-- Doctor Assignments --}}
        <div x-show="tab === 'doctors'">
            <div class="mb-3 flex items-center justify-between">
                <p class="text-xs text-slate-400">Long-term or special care only — Main Doctor, Consultant, or Specialist for admitted patients.</p>
                @can('patient.update')
                    <x-button variant="primary" x-on:click="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'add-doctor-assignment' }))">+ Add Doctor Assignment</x-button>
                @endcan
            </div>
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
                            @if($da->status === 'active')
                                <form method="post" action="/doctor-assignments/{{ $da->assignment_id }}/end" onsubmit="return confirm('End this assignment?')">
                                    @csrf
                                    <button class="text-xs font-medium text-red-600 hover:underline">End</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-4 text-center text-slate-400">No doctor assignments.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- Nurse Assignments --}}
        <div x-show="tab === 'nurses'">
            <div class="mb-3 flex items-center justify-between">
                <p class="text-xs text-slate-400">Assigned when patient is admitted or needs shift-based nursing care.</p>
                @can('patient.update')
                    <x-button variant="primary" x-on:click="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'add-nurse-assignment' }))">+ Add Nurse Assignment</x-button>
                @endcan
            </div>
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
                            @if($na->status === 'active')
                                <form method="post" action="/nurse-assignments/{{ $na->assignment_id }}/end" onsubmit="return confirm('End this assignment?')">
                                    @csrf
                                    <button class="text-xs font-medium text-red-600 hover:underline">End</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-4 text-center text-slate-400">No nurse assignments.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- Room Assignments --}}
        <div x-show="tab === 'rooms'">
            <p class="mb-3 text-xs text-slate-400">Room and bed assignment history. Manage room assignments on the Rooms page.</p>
            <table class="w-full text-sm">
                <thead><tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
                    <th class="pb-2">Room</th><th class="pb-2">Type</th><th class="pb-2">Bed</th><th class="pb-2">Admitted At</th><th class="pb-2">Discharged At</th><th class="pb-2">Status</th>
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
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-4 text-center text-slate-400">No room assignments.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- Medical Records --}}
        <div x-show="tab === 'records'">
            <p class="mb-3 text-xs text-slate-400">Medical records linked to this patient. Manage records on the Medical Records page.</p>
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

        {{-- Billing History --}}
        <div x-show="tab === 'billing'">
            <p class="mb-3 text-xs text-slate-400">Billing records for this patient. Manage billing on the Billing &amp; Payments page.</p>
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
                    <option value="{{ $doc->doctor_id }}">{{ $doc->name() }}@if($doc->specialization) — {{ $doc->specialization }}@endif</option>
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
                    <option value="{{ $n->nurse_id }}">{{ $n->name() }}@if($n->ward_name) — {{ $n->ward_name }}@endif</option>
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
