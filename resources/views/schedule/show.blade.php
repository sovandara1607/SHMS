<x-modal-header title="Shift Details" :subtitle="$shift->shift_id" />

<div class="px-6 py-5">
    <div class="grid grid-cols-2 gap-y-2 text-sm">
        <div><p class="text-xs text-slate-500">Staff ID</p><p class="font-medium text-slate-900">{{ $shift->staff_id }}</p></div>
        <div><p class="text-xs text-slate-500">Staff Name</p><p class="text-slate-900">{{ $shift->staff_name }}</p></div>
        <div><p class="text-xs text-slate-500">Role</p><p class="text-slate-900">{{ $shift->role ? ucfirst(str_replace('_', ' ', $shift->role)) : '—' }}</p></div>
        <div><p class="text-xs text-slate-500">Department</p><p class="text-slate-900">{{ $shift->department ?? '—' }}</p></div>
        <div><p class="text-xs text-slate-500">Shift Date</p><p class="text-slate-900">{{ $shift->shift_date }}</p></div>
        <div><p class="text-xs text-slate-500">Shift Type</p><p class="text-slate-900">{{ ucfirst($shift->shift_type) }}</p></div>
        <div><p class="text-xs text-slate-500">Start Time</p><p class="text-slate-900">{{ $shift->start_time }}</p></div>
        <div><p class="text-xs text-slate-500">End Time</p><p class="text-slate-900">{{ $shift->end_time }}</p></div>
        <div><p class="text-xs text-slate-500">Status</p><p class="mt-0.5"><x-badge :status="$shift->status" /></p></div>
    </div>
</div>
<div class="flex justify-end border-t border-slate-100 px-6 py-4">
    <x-button variant="secondary" x-on:click="show = false">Close</x-button>
</div>
