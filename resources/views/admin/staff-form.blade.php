@php
$subtype = $subtype ?? null;
$action = $mode === 'create' ? '/staff' : '/staff/' . $staff->staff_id;
$target = $mode === 'create' ? ('/staff/create' . ($lockedRole ? '?role=' . $lockedRole : '')) : '/staff/' . $staff->staff_id . '/edit';
$initialRole = old('role', $lockedRole ?? ($role ?? ''));
$roleLabels = config('permissions.roles');
@endphp

<x-modal-header :title="$mode === 'create' ? ($lockedRole === 'doctor' ? 'Add Doctor' : 'Add Staff') : 'Edit Staff'" />

<form id="staff-form" method="post" action="{{ $action }}" class="max-h-[70vh] overflow-y-auto space-y-4 px-6 py-5"
      x-data="{ role: @js($initialRole) }">
    @csrf
    @if($mode === 'edit')@method('PUT')@endif
    <input type="hidden" name="_modal_target" value="{{ $target }}">
    <input type="hidden" name="redirect_to" value="{{ $redirectTo }}">

    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Personal Information</p>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">First Name *</label>
            <input name="first_name" value="{{ old('first_name', $staff->first_name) }}" required
                   class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Last Name *</label>
            <input name="last_name" value="{{ old('last_name', $staff->last_name) }}" required
                   class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
    </div>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Gender</label>
            <select name="gender" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                <option value="">— select —</option>
                @foreach(['male', 'female', 'other'] as $g)
                    <option value="{{ $g }}" @selected(old('gender', $staff->gender) === $g)>{{ ucfirst($g) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Hire Date</label>
            <input type="date" name="hire_date" value="{{ old('hire_date', $staff->hire_date) }}"
                   class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
    </div>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Phone Number</label>
            <input name="phone_number" value="{{ old('phone_number', $staff->phone_number) }}"
                   class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Address</label>
            <input name="address" value="{{ old('address', $staff->address) }}"
                   class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
    </div>

    <p class="mt-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Login &amp; Role</p>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Email *</label>
            <input type="email" name="email" value="{{ old('email', $staff->user?->email ?? '') }}" required
                   class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">
                {{ $mode === 'create' ? 'Password *' : 'New Password' }}
            </label>
            <input type="password" name="password" placeholder="{{ $mode === 'edit' ? 'Leave blank to keep current' : '' }}" {{ $mode === 'create' ? 'required' : '' }}
                   class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
    </div>

    @if($mode === 'create' && ! $lockedRole)
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Role *</label>
            <select name="role" x-model="role" required class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                <option value="">— select —</option>
                @foreach($roleLabels as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    @elseif($mode === 'create')
        <input type="hidden" name="role" value="{{ $lockedRole }}">
        <p class="text-sm text-slate-600">Role: <span class="font-medium text-slate-900">{{ $roleLabels[$lockedRole] ?? $lockedRole }}</span></p>
    @else
        <p class="text-sm text-slate-600">Role: <span class="font-medium text-slate-900">{{ $roleLabels[$role] ?? ($role ?? '—') }}</span> <span class="text-xs text-slate-400">(role cannot be changed after creation)</span></p>
    @endif

    {{-- Role-specific fields. Field names are prefixed per role (doctor_*, nurse_*, ...)
         even though every block shares the same underlying column names (department_id,
         license_number, ...) — all blocks are present in the DOM at once (only hidden via
         x-show), so two <select name="department_id"> would both be submitted and PHP
         silently keeps the last one, wiping out whichever role's value came first. --}}
    <div x-show="role === 'doctor'" class="space-y-4 rounded-lg bg-slate-50 p-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Doctor Details</p>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Department</label>
                <select name="doctor_department_id" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                    <option value="">— none —</option>
                    @foreach($departments as $d)
                        <option value="{{ $d->department_id }}" @selected(old('doctor_department_id', $subtype?->department_id ?? null) === $d->department_id)>{{ $d->department_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Specialization</label>
                <input name="doctor_specialization" value="{{ old('doctor_specialization', $subtype?->specialization ?? '') }}"
                       class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
            </div>
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">License Number</label>
            <input name="doctor_license_number" value="{{ old('doctor_license_number', $subtype?->license_number ?? '') }}"
                   class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
    </div>

    <div x-show="role === 'nurse'" class="space-y-4 rounded-lg bg-slate-50 p-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Nurse Details</p>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Department</label>
                <select name="nurse_department_id" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                    <option value="">— none —</option>
                    @foreach($departments as $d)
                        <option value="{{ $d->department_id }}" @selected(old('nurse_department_id', $subtype?->department_id ?? null) === $d->department_id)>{{ $d->department_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Ward</label>
                <input name="nurse_ward_name" value="{{ old('nurse_ward_name', $subtype?->ward_name ?? '') }}"
                       class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
            </div>
        </div>
    </div>

    <div x-show="role === 'receptionist'" class="space-y-4 rounded-lg bg-slate-50 p-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Receptionist Details</p>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Counter Number</label>
            <input name="receptionist_counter_number" value="{{ old('receptionist_counter_number', $subtype?->counter_number ?? '') }}"
                   class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
    </div>

    <div x-show="role === 'pharmacist'" class="space-y-4 rounded-lg bg-slate-50 p-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Pharmacist Details</p>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">License Number</label>
                <input name="pharmacist_license_number" value="{{ old('pharmacist_license_number', $subtype?->license_number ?? '') }}"
                       class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Pharmacy Unit</label>
                <input name="pharmacist_pharmacy_unit" value="{{ old('pharmacist_pharmacy_unit', $subtype?->pharmacy_unit ?? '') }}"
                       class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
            </div>
        </div>
    </div>

    <div x-show="role === 'lab_technician'" class="space-y-4 rounded-lg bg-slate-50 p-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Lab Technician Details</p>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Laboratory</label>
                <select name="labtech_laboratory_id" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                    <option value="">— none —</option>
                    @foreach($laboratories as $lab)
                        <option value="{{ $lab->laboratory_id }}" @selected(old('labtech_laboratory_id', $subtype?->laboratory_id ?? null) === $lab->laboratory_id)>{{ $lab->laboratory_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Skill Area</label>
                <input name="labtech_skill_area" value="{{ old('labtech_skill_area', $subtype?->skill_area ?? '') }}"
                       class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
            </div>
        </div>
    </div>
</form>
<div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4">
    <x-button variant="secondary" x-on:click="show = false">Cancel</x-button>
    <x-button variant="primary" type="submit" form="staff-form">{{ $mode === 'create' ? 'Add Staff' : 'Save Changes' }}</x-button>
</div>
