<x-modal-header title="Dispense Medicine" :subtitle="$prescription->prescription_id . ' · ' . $prescription->patient_name" />
<form method="post" action="/dispensing" class="px-6 py-5">
    @csrf
    <input type="hidden" name="prescription_id" value="{{ $prescription->prescription_id }}">
    <input type="hidden" name="patient_id" value="{{ $prescription->patient_id }}">

    <table class="mb-4 w-full text-sm">
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
                <td class="py-2 font-medium">{{ $item->quantity }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="rounded-lg bg-blue-50 px-4 py-3 text-xs text-blue-700">
        <p class="font-semibold uppercase tracking-wider">System-Generated</p>
        <p class="mt-1">Pharmacist: {{ auth()->user()->displayName() }}</p>
        <p>Dispensing Date: {{ now()->format('Y-m-d') }}</p>
    </div>

    <div class="mt-4 flex justify-end gap-2">
        <x-button variant="secondary" type="button" x-on:click="show = false">Cancel</x-button>
        <x-button variant="success" type="submit">Confirm Dispensing</x-button>
    </div>
</form>
