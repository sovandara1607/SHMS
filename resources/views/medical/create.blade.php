@if(! $patient)
    <x-modal-header title="Add Medical Record" subtitle="Step 1 of 2 — Search and select a patient" />
    <div class="max-h-[65vh] overflow-y-auto px-6 py-5">
        <input type="text" placeholder="Search by patient name or patient ID..."
               class="mb-4 w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        <table class="w-full text-sm">
            <thead><tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
                <th class="pb-2">Patient ID</th><th class="pb-2">Patient Name</th><th class="pb-2">Status</th><th class="pb-2"></th>
            </tr></thead>
            <tbody>
            @foreach($patients as $p)
                <tr class="border-b border-slate-50">
                    <td class="py-2 font-medium text-blue-600">{{ $p->patient_id }}</td>
                    <td class="py-2 text-slate-900">{{ $p->fullName() }}</td>
                    <td class="py-2"><x-badge :status="$p->patient_status" /></td>
                    <td class="py-2 text-right">
                        <button type="button" class="text-sm font-medium text-blue-600 hover:underline"
                                x-on:click="openModal('/medical-records/create?patient_id={{ $p->patient_id }}')">Select &rarr;</button>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@else
    <x-modal-header title="Add Medical Record" :subtitle="'Patient: ' . $patient->fullName()" />
    <form id="medical-record-form" method="post" action="/medical-records" class="max-h-[65vh] overflow-y-auto px-6 py-5">
        @csrf
        <input type="hidden" name="patient_id" value="{{ $patient->patient_id }}">
        <input type="hidden" name="_modal_target" value="/medical-records/create?patient_id={{ $patient->patient_id }}">

        <div class="mb-4 rounded-lg bg-slate-50 px-4 py-3">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Patient Information</p>
            <div class="grid grid-cols-2 gap-y-1 text-sm">
                <span class="text-slate-500">Patient ID</span><span class="text-right font-medium text-slate-900">{{ $patient->patient_id }}</span>
                <span class="text-slate-500">Patient Name</span><span class="text-right text-slate-900">{{ $patient->fullName() }}</span>
            </div>
            <p class="mt-2 text-xs"><a href="#" x-on:click.prevent="openModal('/medical-records/create')" class="font-medium text-blue-600 hover:underline">Change Patient</a></p>
        </div>

        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Medical Record</p>
        <div class="mb-4">
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Doctor *</label>
            <select name="doctor_id" required class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                <option value="">— select —</option>
                @foreach($doctors as $d)
                    <option value="{{ $d->doctor_id }}" @selected(old('doctor_id') === $d->doctor_id)>{{ $d->name() }}</option>
                @endforeach
            </select>
        </div>
        <div class="mb-4">
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Symptoms</label>
            <textarea name="symptoms" rows="2" placeholder="Describe patient symptoms..."
                      class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">{{ old('symptoms') }}</textarea>
        </div>
        <div class="mb-4">
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Diagnosis *</label>
            <textarea name="diagnosis" rows="2" required placeholder="Enter diagnosis description..."
                      class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">{{ old('diagnosis') }}</textarea>
        </div>
        <div class="mb-4">
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Treatment Notes</label>
            <textarea name="treatment_notes" rows="3" placeholder="Describe the treatment plan and notes..."
                      class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">{{ old('treatment_notes') }}</textarea>
        </div>

        <p class="mb-2 mt-5 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-slate-400">Vital Signs</p>
        <p class="mb-3 text-xs text-slate-400">Stored in the vital_signs table, linked to this record. All fields are optional.</p>
        <div class="grid grid-cols-3 gap-4">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Temperature</label>
                <input name="temperature" placeholder="e.g. 37.2" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Blood Pressure</label>
                <input name="blood_pressure" placeholder="e.g. 120/80" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Heart Rate</label>
                <input name="heart_rate" placeholder="e.g. 72" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
            </div>
        </div>
        <div class="mt-4 grid grid-cols-3 gap-4">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Height (cm)</label>
                <input name="height" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Weight (kg)</label>
                <input name="weight" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
            </div>
        </div>
    </form>
    <div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4">
        <x-button variant="secondary" x-on:click="show = false">Cancel</x-button>
        <x-button variant="primary" type="submit" form="medical-record-form">Create Medical Record</x-button>
    </div>
@endif
