@php
$action = $mode === 'create' ? '/patients' : '/patients/' . $patient->patient_id;
$target = $mode === 'create' ? '/patients/create' : '/patients/' . $patient->patient_id . '/edit';
$statuses = ['active' => 'Active', 'admitted' => 'Admitted', 'icu' => 'ICU', 'discharged' => 'Discharged', 'inactive' => 'Inactive'];
$currentStatus = old('patient_status', $patient->patient_status ?? 'active');
@endphp

<x-modal-header :title="$mode === 'create' ? 'Add New Patient' : 'Edit Patient Information'" />

<form id="patient-form" method="post" action="{{ $action }}" class="max-h-[75vh] overflow-y-auto px-6 py-5">
    @csrf
    @if($mode === 'edit')@method('PUT')@endif
    <input type="hidden" name="_modal_target" value="{{ $target }}">

    <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Personal Information</p>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">First Name *</label>
            <input name="first_name" value="{{ old('first_name', $patient->first_name) }}" required
                   class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Last Name *</label>
            <input name="last_name" value="{{ old('last_name', $patient->last_name) }}" required
                   class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
    </div>

    <div class="mt-4 grid grid-cols-2 gap-4">
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Gender</label>
            <select name="gender" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                <option value="">— select —</option>
                @foreach(['male', 'female', 'other'] as $g)
                    <option value="{{ $g }}" @selected(old('gender', $patient->gender) === $g)>{{ ucfirst($g) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Date of Birth</label>
            <input type="date" name="date_of_birth" value="{{ old('date_of_birth', $patient->date_of_birth) }}"
                   class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
    </div>

    <div class="mt-4 grid grid-cols-2 gap-4">
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Phone</label>
            <input name="phone_number" value="{{ old('phone_number', $patient->phone_number) }}" placeholder="+1 (555) 000-0000"
                   class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Email</label>
            <input type="email" name="email" value="{{ old('email', $patient->email) }}" placeholder="patient@mail.com"
                   class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
    </div>

    <div class="mt-4">
        <label class="mb-1.5 block text-sm font-medium text-slate-700">Address</label>
        <input name="address" value="{{ old('address', $patient->address) }}" placeholder="123 Main St, City, State ZIP"
               class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
    </div>

    <p class="mb-2 mt-5 text-xs font-semibold uppercase tracking-wider text-slate-400">Medical Information</p>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Blood Type</label>
            <input name="blood_type" value="{{ old('blood_type', $patient->blood_type) }}" placeholder="e.g. O+"
                   class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Allergies</label>
            <input name="allergy" value="{{ old('allergy', $patient->allergy) }}" placeholder="Penicillin, Latex, None"
                   class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
    </div>

    <p class="mb-2 mt-5 text-xs font-semibold uppercase tracking-wider text-slate-400">Emergency Contact</p>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Contact Name</label>
            <input name="emergency_contact_name" value="{{ old('emergency_contact_name', $patient->emergency_contact_name) }}" placeholder="Full name"
                   class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Contact Phone</label>
            <input name="emergency_contact_phone" value="{{ old('emergency_contact_phone', $patient->emergency_contact_phone) }}" placeholder="+1 (555) 000-0000"
                   class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
    </div>

    <p class="mb-2 mt-5 text-xs font-semibold uppercase tracking-wider text-slate-400">Insurance Information</p>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Insurance Provider</label>
            <input name="insurance_provider" value="{{ old('insurance_provider', $insurance->insurance_provider) }}" placeholder="BlueCross"
                   class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Policy Number</label>
            <input name="policy_number" value="{{ old('policy_number', $insurance->policy_number) }}" placeholder="BC-9921-A"
                   class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
    </div>
    <div class="mt-4">
        <label class="mb-1.5 block text-sm font-medium text-slate-700">Coverage Details</label>
        <input name="coverage_details" value="{{ old('coverage_details', $insurance->coverage_details) }}" placeholder="Plan B — 80% inpatient"
               class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
    </div>
    <div class="mt-4 grid grid-cols-2 gap-4">
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Policy Start</label>
            <input type="date" name="policy_start" value="{{ old('policy_start', $insurance->start_date) }}"
                   class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Policy End</label>
            <input type="date" name="policy_end" value="{{ old('policy_end', $insurance->end_date) }}"
                   class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
    </div>

    <p class="mb-2 mt-5 text-xs font-semibold uppercase tracking-wider text-slate-400">Status</p>
    <div class="flex flex-wrap gap-2" x-data="{ status: '{{ $currentStatus }}' }">
        <input type="hidden" name="patient_status" x-model="status">
        @foreach($statuses as $value => $label)
            <button type="button" @click="status = '{{ $value }}'"
                    :class="status === '{{ $value }}' ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 border border-slate-200'"
                    class="rounded-lg px-3.5 py-2 text-sm font-medium transition">{{ $label }}</button>
        @endforeach
    </div>
</form>

<div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4">
    <x-button variant="secondary" x-on:click="show = false">Cancel</x-button>
    <x-button variant="primary" type="submit" form="patient-form">
        {{ $mode === 'create' ? 'Add Patient' : 'Save Changes' }}
    </x-button>
</div>
