@php($latestVersion = $versions->max('version') ?? 1)

<x-modal-header :title="$record->medical_record_id" :subtitle="'Patient: ' . ($record->patient?->fullName() ?? $record->patient_id)" />

<div class="max-h-[65vh] overflow-y-auto px-6 py-5">
    <div class="mb-4 rounded-lg bg-slate-50 px-4 py-3">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Patient Information</p>
        <div class="grid grid-cols-3 gap-y-1 text-sm">
            <span class="text-slate-500">Patient ID</span><span class="text-slate-900">{{ $record->patient_id }}</span>
            <span class="text-slate-500">Patient Name</span><span class="text-slate-900">{{ $record->patient?->fullName() }}</span>
            <span class="text-slate-500">Age</span><span class="text-slate-900">{{ $record->patient?->date_of_birth ? \Carbon\Carbon::parse($record->patient->date_of_birth)->age . ' yrs' : '—' }}</span>
        </div>
    </div>
    <div class="mb-4 rounded-lg bg-slate-50 px-4 py-3">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Record Information</p>
        <div class="grid grid-cols-2 gap-y-1 text-sm">
            <span class="text-slate-500">Doctor</span><span class="text-slate-900">{{ $record->doctor?->name() }}</span>
            <span class="text-slate-500">Related Appointment</span><span class="text-slate-900">{{ $record->appointment_id ?? '—' }}</span>
            <span class="text-slate-500">Created At</span><span class="text-slate-900">{{ $record->created_at }}</span>
            <span class="text-slate-500">Latest Version</span><span class="text-amber-600 font-medium">v{{ $latestVersion }}</span>
        </div>
    </div>

    <p class="mb-1 text-xs font-semibold uppercase tracking-wider text-slate-400">Symptoms</p>
    <p class="mb-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-slate-800">{{ $record->symptoms ?: '—' }}</p>

    <p class="mb-1 text-xs font-semibold uppercase tracking-wider text-slate-400">Diagnosis</p>
    <p class="mb-4 rounded-lg bg-purple-50 px-4 py-3 text-sm text-slate-800">{{ $record->diagnosis ?: '—' }}</p>

    <p class="mb-1 text-xs font-semibold uppercase tracking-wider text-slate-400">Treatment Notes</p>
    <p class="mb-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-slate-800">{{ $record->treatment_notes ?: '—' }}</p>

    <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Adjustment History</p>
    <table class="mb-4 w-full text-sm">
        <thead><tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
            <th class="pb-2">Adjusted At</th><th class="pb-2">By</th><th class="pb-2">Diagnosis</th><th class="pb-2">Reason</th>
        </tr></thead>
        <tbody>
        @forelse($adjustments as $a)
            <tr class="border-b border-slate-50">
                <td class="py-2">{{ $a->adjusted_at }}</td>
                <td class="py-2">{{ $a->adjusted_by }}</td>
                <td class="py-2">{{ $a->diagnosis ?: '—' }}</td>
                <td class="py-2">{{ $a->reason }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="py-3 text-center text-slate-400">No adjustments — original preserved.</td></tr>
        @endforelse
        </tbody>
    </table>

    @can('medical_record.adjust')
        <x-button variant="primary" x-on:click="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'adjust-record' }))">Adjust Medical Record</x-button>
    @endcan
</div>

<div class="flex justify-end border-t border-slate-100 px-6 py-4">
    <x-button variant="secondary" x-on:click="show = false">Close</x-button>
</div>

<x-modal name="adjust-record" max-width="lg">
    <x-modal-header title="Adjust Medical Record" subtitle="A reason is mandatory. The original record is preserved as a new version." />
    <form method="post" action="/medical-records/{{ $record->medical_record_id }}/adjust" class="space-y-4 px-6 py-5">
        @csrf
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Symptoms</label>
            <textarea name="symptoms" rows="2" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">{{ $record->symptoms }}</textarea>
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Diagnosis</label>
            <textarea name="diagnosis" rows="2" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">{{ $record->diagnosis }}</textarea>
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Treatment Notes</label>
            <textarea name="treatment_notes" rows="2" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">{{ $record->treatment_notes }}</textarea>
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-red-700">Reason for Adjustment *</label>
            <input name="reason" required class="w-full rounded-lg border border-red-200 px-3.5 py-2.5 text-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-500/20">
        </div>
        <div class="flex justify-end gap-2 pt-2">
            <x-button variant="secondary" type="button" x-on:click="show = false">Cancel</x-button>
            <x-button variant="primary" type="submit">Save Adjustment</x-button>
        </div>
    </form>
</x-modal>
