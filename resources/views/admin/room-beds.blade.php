<x-modal-header :title="'Beds — Room ' . ($room->room_number ?: $room->room_id)" :subtitle="($room->department?->department_name ?: 'No department') . ' · ' . ucfirst($room->room_type ?: 'general')" />

<div class="max-h-[65vh] overflow-auto px-6 py-5">
    <table class="w-full min-w-[480px] text-sm">
        <thead><tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
            <th class="pb-2">Bed</th><th class="pb-2">Status</th><th class="pb-2">Patient</th><th class="pb-2">Since</th><th class="pb-2 text-right">Action</th>
        </tr></thead>
        <tbody>
        @forelse($beds as $bed)
            <tr class="border-b border-slate-50">
                <td class="py-2 font-medium text-slate-900">{{ $bed->bed_number ?: $bed->bed_id }}</td>
                <td class="py-2"><x-badge :status="$bed->status" /></td>
                <td class="py-2 text-slate-600">{{ $bed->patient_name ?: '—' }}</td>
                <td class="py-2 text-slate-600">{{ $bed->assigned_at ?: '—' }}</td>
                <td class="py-2 text-right">
                    @can('room.assign')
                        @if($bed->status === 'available')
                            <button type="button" class="text-sm font-medium text-blue-600 hover:underline"
                                    x-on:click="openModal('/beds/{{ $bed->bed_id }}/assign')">Assign Patient</button>
                        @elseif($bed->status === 'occupied' && $bed->room_assignment_id)
                            <form method="post" action="/room-assignments/{{ $bed->room_assignment_id }}/release" onsubmit="return confirm('Release this bed?')">
                                @csrf
                                <button type="submit" class="text-sm font-medium text-red-600 hover:underline">Release</button>
                            </form>
                        @endif
                    @endcan
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="py-4 text-center text-slate-400">No beds in this room yet.</td></tr>
        @endforelse
        </tbody>
    </table>

    @can('staff.manage')
        <form method="post" action="/rooms/{{ $room->room_id }}/beds" class="mt-4 flex items-end gap-2 border-t border-slate-100 pt-4">
            @csrf
            <div class="flex-1">
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Add Bed — Bed Number</label>
                <input name="bed_number" placeholder="e.g. 5" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
            </div>
            <x-button variant="secondary" type="submit">Add Bed</x-button>
        </form>
    @endcan
</div>
<div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4">
    <x-button variant="secondary" x-on:click="show = false">Close</x-button>
</div>
