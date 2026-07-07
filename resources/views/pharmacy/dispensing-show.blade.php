<x-modal-header title="Dispensing Details" :subtitle="$record->dispensing_id . ' · ' . $record->patient_name" />
<div class="space-y-3 px-6 py-5 text-sm">
    <div class="flex justify-between"><span class="text-slate-500">Dispensing ID</span><span class="font-medium text-slate-900">{{ $record->dispensing_id }}</span></div>
    <div class="flex justify-between"><span class="text-slate-500">Prescription ID</span><span class="text-blue-600">{{ $record->prescription_id }}</span></div>
    <div class="flex justify-between"><span class="text-slate-500">Patient Name</span><span class="text-slate-900">{{ $record->patient_name }}</span></div>
    <div class="flex justify-between"><span class="text-slate-500">Pharmacist</span><span class="text-slate-900">{{ $record->pharmacist_name ?: '—' }}</span></div>
    <div class="flex justify-between"><span class="text-slate-500">Dispensing Date</span><span class="text-slate-900">{{ $record->dispensing_date }}</span></div>
    <div class="flex justify-between"><span class="text-slate-500">Status</span><x-badge :status="$record->status" /></div>

    <p class="pt-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Dispensing Items</p>
    <table class="w-full text-sm">
        <thead><tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
            <th class="pb-2">Medicine</th><th class="pb-2">Batch</th><th class="pb-2">Qty Dispensed</th>
        </tr></thead>
        <tbody>
        @foreach($items as $item)
            <tr class="border-b border-slate-50">
                <td class="py-2">{{ $item->medicine_name }}</td>
                <td class="py-2">{{ $item->batch_number ?: '—' }}</td>
                <td class="py-2">{{ $item->quantity_dispensed }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<div class="flex justify-end border-t border-slate-100 px-6 py-4">
    <x-button variant="secondary" x-on:click="show = false">Close</x-button>
</div>
