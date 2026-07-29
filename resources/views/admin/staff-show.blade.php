<x-modal-header title="Staff Details" :subtitle="$staff->staff_id" />

<div class="px-6 py-5">
    <div class="mb-4 flex items-center gap-3">
        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-blue-100 text-sm font-semibold text-blue-700">
            {{ strtoupper(substr($staff->first_name, 0, 1) . substr($staff->last_name, 0, 1)) }}
        </div>
        <div>
            <p class="font-semibold text-slate-900">{{ $staff->full_name }}</p>
            <p class="text-sm text-slate-400">{{ $staff->title ?: '—' }}</p>
        </div>
    </div>
    <div class="grid grid-cols-2 gap-y-3 text-sm">
        <div><p class="text-xs text-slate-500">Email</p><p class="text-slate-900">{{ $staff->email ?: '—' }}</p></div>
        <div><p class="text-xs text-slate-500">Role</p><p class="text-slate-900">{{ $staff->role ? ucwords(str_replace('_', ' ', $staff->role)) : '—' }}</p></div>
        <div><p class="text-xs text-slate-500">Department</p><p class="text-slate-900">{{ $staff->department_name ?: '—' }}</p></div>
        <div><p class="text-xs text-slate-500">Specialization / Unit</p><p class="text-slate-900">{{ $staff->specialization_unit ?: '—' }}</p></div>
        <div><p class="text-xs text-slate-500">Gender</p><p class="text-slate-900">{{ $staff->gender ? ucfirst($staff->gender) : '—' }}</p></div>
        <div><p class="text-xs text-slate-500">Phone</p><p class="text-slate-900">{{ $staff->phone_number ?: '—' }}</p></div>
        <div><p class="text-xs text-slate-500">Hire Date</p><p class="text-slate-900">{{ $staff->hire_date ?: '—' }}</p></div>
        <div><p class="text-xs text-slate-500">Employment Status</p><p class="text-slate-900">{{ $staff->employment_type ? ucwords(str_replace('_', ' ', $staff->employment_type)) : '—' }}</p></div>
        <div><p class="text-xs text-slate-500">Account Status</p><p class="mt-0.5"><x-badge :status="$staff->status" /></p></div>
    </div>
</div>
<div class="flex justify-end border-t border-slate-100 px-6 py-4">
    <x-button variant="secondary" x-on:click="show = false">Close</x-button>
</div>
