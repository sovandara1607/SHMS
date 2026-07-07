<x-modal-header title="Prescription Details" :subtitle="$prescription->prescription_id . ' · ' . $prescription->patient_name" />
<div class="space-y-3 px-6 py-5 text-sm">
    <div class="flex justify-between"><span class="text-slate-500">Prescription ID</span><span class="font-medium text-slate-900">{{ $prescription->prescription_id }}</span></div>
    <div class="flex justify-between"><span class="text-slate-500">Patient Name</span><span class="text-slate-900">{{ $prescription->patient_name }}</span></div>
    <div class="flex justify-between"><span class="text-slate-500">Doctor</span><span class="text-slate-900">{{ $prescription->doctor_name }}</span></div>
    <div class="flex justify-between"><span class="text-slate-500">Medical Record</span><span class="text-blue-600">{{ $prescription->medical_record_id }}</span></div>
    <div class="flex justify-between"><span class="text-slate-500">Date</span><span class="text-slate-900">{{ $prescription->prescription_date }}</span></div>
    <div class="flex justify-between"><span class="text-slate-500">Notes</span><span class="text-slate-900">{{ $prescription->notes ?: '—' }}</span></div>

    <p class="pt-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Prescription Items</p>
    <table class="w-full text-sm">
        <thead><tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
            <th class="pb-2">Medicine</th><th class="pb-2">Dosage</th><th class="pb-2">Frequency</th><th class="pb-2">Duration</th><th class="pb-2">Qty</th>
        </tr></thead>
        <tbody>
        @foreach($items as $item)
            <tr class="border-b border-slate-50">
                <td class="py-2">{{ $item->medicine_name }}</td>
                <td class="py-2">{{ $item->dosage }}</td>
                <td class="py-2">{{ $item->frequency }}</td>
                <td class="py-2">{{ $item->duration }}</td>
                <td class="py-2">{{ $item->quantity }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<div class="flex justify-end border-t border-slate-100 px-6 py-4">
    <x-button variant="secondary" x-on:click="show = false">Close</x-button>
</div>
