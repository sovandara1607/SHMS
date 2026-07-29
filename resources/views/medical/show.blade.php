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

    <p id="adjustment-history" class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Adjustment History</p>
    <div class="overflow-x-auto">
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
    </div>

    <div class="mb-2 flex items-center justify-between">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Prescriptions</p>
        @can('prescription.create')
            <button type="button" class="text-sm font-medium text-blue-600 hover:underline"
                    x-on:click="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'prescribe' }))">+ Add Prescription</button>
        @endcan
    </div>
    @forelse($prescriptions as $pr)
        <div class="mb-3 rounded-lg border border-slate-100 px-4 py-3">
            <div class="mb-1.5 flex items-center justify-between text-sm">
                <span class="font-medium text-slate-900">{{ $pr->prescription_id }}</span>
                <span class="flex items-center gap-3">
                    <span class="text-slate-400">{{ $pr->prescription_date }}</span>
                    @can('prescription.view')
                        <a href="/prescriptions/{{ $pr->prescription_id }}" class="text-xs font-medium text-blue-600 hover:underline">View in Pharmacy</a>
                    @endcan
                </span>
            </div>
            <ul class="list-disc pl-5 text-sm text-slate-700">
                @foreach($pr->items as $item)
                    <li>{{ $item->medicine?->medicine_name ?? $item->medicine_id }}
                        @if($item->dosage) — {{ $item->dosage }}@endif
                        @if($item->frequency), {{ $item->frequency }}@endif
                        @if($item->duration) for {{ $item->duration }}@endif
                        @if($item->quantity) (qty {{ $item->quantity }})@endif
                    </li>
                @endforeach
            </ul>
            @if($pr->notes)<p class="mt-1.5 text-xs text-slate-500">{{ $pr->notes }}</p>@endif
        </div>
    @empty
        <p class="mb-4 text-sm text-slate-400">No prescriptions for this record yet.</p>
    @endforelse

    <div class="mb-2 flex items-center justify-between">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Vital Signs</p>
        @can('vital_signs.create')
            <button type="button" class="text-sm font-medium text-blue-600 hover:underline"
                    x-on:click="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'record-vitals' }))">+ Record Vitals</button>
        @endcan
    </div>
    @forelse($vitalSigns as $v)
        <div class="mb-3 rounded-lg border border-slate-100 px-4 py-3">
            <div class="mb-1.5 flex items-center justify-between text-sm">
                <span class="font-medium text-slate-900">{{ $v->vital_sign_id }}</span>
                <span class="text-slate-400">{{ $v->recorded_at }}</span>
            </div>
            <div class="grid grid-cols-3 gap-y-1 text-sm text-slate-700">
                <span>Temp: {{ $v->temperature ?? '—' }}{{ $v->temperature ? '°C' : '' }}</span>
                <span>BP: {{ $v->blood_pressure ?: '—' }}</span>
                <span>HR: {{ $v->heart_rate ?? '—' }}{{ $v->heart_rate ? ' bpm' : '' }}</span>
                <span>Height: {{ $v->height ?? '—' }}</span>
                <span>Weight: {{ $v->weight ?? '—' }}</span>
            </div>
        </div>
    @empty
        <p class="mb-4 text-sm text-slate-400">No vital signs recorded for this record yet.</p>
    @endforelse

    <div class="mb-2 flex items-center justify-between">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Reports</p>
        @can('medical_report.create')
            <button type="button" class="text-sm font-medium text-blue-600 hover:underline"
                    x-on:click="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'generate-report' }))">+ Generate Report</button>
        @endcan
    </div>
    @forelse($reports as $r)
        <div class="mb-3 rounded-lg border border-slate-100 px-4 py-3">
            <div class="mb-1.5 flex items-center justify-between text-sm">
                <span class="font-medium text-slate-900">{{ $r->report_id }} — {{ $r->report_type }}</span>
                <span class="text-slate-400">{{ $r->generated_at }}</span>
            </div>
            <p class="whitespace-pre-line text-sm text-slate-700">{{ $r->report_content }}</p>
            <div class="mt-2 flex items-center gap-3">
                @if($r->report_file_path ?? null)
                    <a href="/medical-reports/{{ $r->report_id }}/download" download class="text-xs font-medium text-blue-600 hover:underline">Download PDF</a>
                @else
                    <span class="text-xs text-slate-400">Generating PDF…</span>
                @endif
                @can('medical_report.create')
                    <form action="/medical-reports/{{ $r->report_id }}/regenerate" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="text-xs font-medium text-slate-500 hover:underline">Regenerate</button>
                    </form>
                @endcan
            </div>
        </div>
    @empty
        <p class="mb-4 text-sm text-slate-400">No reports generated for this record yet.</p>
    @endforelse

    <div class="mb-2 flex items-center justify-between">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Procedures</p>
        @can('procedure.create')
            <button type="button" class="text-sm font-medium text-blue-600 hover:underline"
                    x-on:click="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'add-procedure' }))">+ Add Procedure</button>
        @endcan
    </div>
    @forelse($procedures as $proc)
        <div class="mb-3 rounded-lg border border-slate-100 px-4 py-3">
            <div class="mb-1.5 flex items-center justify-between text-sm">
                <span class="font-medium text-slate-900">{{ $proc->procedure_id }} — {{ $proc->procedure_name }}</span>
                <span class="text-slate-400">{{ $proc->procedure_date }}</span>
            </div>
            @if($proc->procedure_details)<p class="text-sm text-slate-700">{{ $proc->procedure_details }}</p>@endif
            @if($proc->outcome)<p class="mt-1.5 text-xs text-slate-500">Outcome: {{ $proc->outcome }}</p>@endif
        </div>
    @empty
        <p class="mb-4 text-sm text-slate-400">No procedures recorded for this record yet.</p>
    @endforelse

    @can('medical_record.adjust')
        <x-button variant="primary" x-on:click="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'adjust-record' }))">Adjust Medical Record</x-button>
    @endcan
</div>

<div class="flex justify-end border-t border-slate-100 px-6 py-4">
    <x-button variant="secondary" x-on:click="show = false">Close</x-button>
</div>

<x-modal name="adjust-record" max-width="lg">
    <x-modal-header title="Edit Medical Record" :subtitle="$record->medical_record_id . ' · ' . ($record->patient?->fullName() ?? $record->patient_id)" icon="pencil" icon-color="amber" />
    <div class="max-h-[70vh] overflow-y-auto px-6 py-5">
        <div class="mb-5 flex items-start gap-2 rounded-lg bg-blue-50 px-4 py-3 text-sm text-blue-800">
            <x-icon name="info" class="mt-0.5 h-4 w-4 shrink-0" />
            <span>This edit will create a new version and keep the original medical record unchanged.</span>
        </div>

        @php($age = $record->patient?->date_of_birth ? \Carbon\Carbon::parse($record->patient->date_of_birth)->age . ' yrs' : '—')
        <div class="mb-5 rounded-lg bg-slate-50 px-4 py-3">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Patient Information</p>
            <div class="grid grid-cols-2 gap-y-2 text-sm">
                <div><p class="text-xs text-slate-500">Patient ID</p><p class="font-medium text-slate-900">{{ $record->patient_id }}</p></div>
                <div><p class="text-xs text-slate-500">Patient Name</p><p class="text-slate-900">{{ $record->patient?->fullName() ?? '—' }}</p></div>
                <div><p class="text-xs text-slate-500">Age</p><p class="text-slate-900">{{ $age }}</p></div>
                <div><p class="text-xs text-slate-500">Gender</p><p class="text-slate-900">{{ $record->patient?->gender ? ucfirst($record->patient->gender) : '—' }}</p></div>
                <div><p class="text-xs text-slate-500">Phone</p><p class="text-slate-900">{{ $record->patient?->phone_number ?: '—' }}</p></div>
            </div>
        </div>

        <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Edit Fields</p>
        <form method="post" action="/medical-records/{{ $record->medical_record_id }}/adjust" class="space-y-4">
            @csrf
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Symptoms</label>
                <textarea name="symptoms" rows="2" placeholder="Describe patient symptoms..." class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">{{ $record->symptoms }}</textarea>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Diagnosis *</label>
                <textarea name="diagnosis" rows="2" placeholder="Enter ICD codes and diagnosis description..." class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">{{ $record->diagnosis }}</textarea>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Treatment Notes</label>
                <textarea name="treatment_notes" rows="2" placeholder="Describe the treatment plan and notes..." class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">{{ $record->treatment_notes }}</textarea>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-red-700">Reason for Adjustment *</label>
                <textarea name="reason" rows="2" required placeholder="Explain why this record is being adjusted..." class="w-full rounded-lg border border-red-200 px-3.5 py-2.5 text-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-500/20"></textarea>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <x-button variant="secondary" type="button" x-on:click="show = false">Cancel</x-button>
                <button type="submit" class="rounded-lg bg-amber-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-amber-600">Save New Version</button>
            </div>
        </form>
    </div>
</x-modal>

<x-modal name="prescribe" max-width="xl">
    <x-modal-header title="Add Prescription" :subtitle="'Patient: ' . ($record->patient?->fullName() ?? $record->patient_id)" />
    <form method="post" action="/medical-records/{{ $record->medical_record_id }}/prescriptions" class="max-h-[65vh] overflow-y-auto space-y-4 px-6 py-5"
          x-data="{ items: [{ medicine_id: '', dosage: '', frequency: '', duration: '', quantity: '' }] }">
        @csrf
        <template x-for="(item, i) in items" :key="i">
            <div class="space-y-3 rounded-lg bg-slate-50 p-4">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Medicine <span x-text="i + 1"></span></p>
                    <button type="button" x-show="items.length > 1" x-transition.opacity x-on:click="items.splice(i, 1)" class="text-xs font-medium text-red-600 hover:underline">Remove</button>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Medicine *</label>
                    <select :name="`items[${i}][medicine_id]`" x-model="item.medicine_id" required
                            class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                        <option value="">— select —</option>
                        @foreach($medicines as $m)
                            <option value="{{ $m->medicine_id }}">{{ $m->medicine_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Dosage</label>
                        <input :name="`items[${i}][dosage]`" x-model="item.dosage" placeholder="e.g. 500mg"
                               class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Frequency</label>
                        <input :name="`items[${i}][frequency]`" x-model="item.frequency" placeholder="e.g. 3x daily"
                               class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Duration</label>
                        <input :name="`items[${i}][duration]`" x-model="item.duration" placeholder="e.g. 7 days"
                               class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Quantity</label>
                        <input type="number" min="1" :name="`items[${i}][quantity]`" x-model="item.quantity"
                               class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                    </div>
                </div>
            </div>
        </template>
        <button type="button" x-on:click="items.push({ medicine_id: '', dosage: '', frequency: '', duration: '', quantity: '' })"
                class="text-sm font-medium text-blue-600 hover:underline">+ Add another medicine</button>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Notes</label>
            <textarea name="notes" rows="2" placeholder="Additional instructions for the patient/pharmacist..."
                      class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"></textarea>
        </div>
        <div class="flex justify-end gap-2 pt-2">
            <x-button variant="secondary" type="button" x-on:click="show = false">Cancel</x-button>
            <x-button variant="primary" type="submit">Save Prescription</x-button>
        </div>
    </form>
</x-modal>

<x-modal name="record-vitals" max-width="lg">
    <x-modal-header title="Record Vitals" :subtitle="'Patient: ' . ($record->patient?->fullName() ?? $record->patient_id)" />
    <form method="post" action="/medical-records/{{ $record->medical_record_id }}/vital-signs" class="space-y-4 px-6 py-5">
        @csrf
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Temperature (°C)</label>
                <input name="temperature" placeholder="e.g. 37.2" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Blood Pressure</label>
                <input name="blood_pressure" placeholder="e.g. 120/80" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
            </div>
        </div>
        <div class="grid grid-cols-3 gap-4">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Heart Rate</label>
                <input name="heart_rate" placeholder="e.g. 72" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Height (cm)</label>
                <input name="height" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Weight (kg)</label>
                <input name="weight" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
            </div>
        </div>
        <div class="flex justify-end gap-2 pt-2">
            <x-button variant="secondary" type="button" x-on:click="show = false">Cancel</x-button>
            <x-button variant="primary" type="submit">Save Vitals</x-button>
        </div>
    </form>
</x-modal>

<x-modal name="generate-report" max-width="lg">
    <x-modal-header title="Generate Report" :subtitle="'Patient: ' . ($record->patient?->fullName() ?? $record->patient_id)" />
    <form method="post" action="/medical-records/{{ $record->medical_record_id }}/reports" class="space-y-4 px-6 py-5">
        @csrf
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Report Type *</label>
            <select name="report_type" required class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                <option value="">— select —</option>
                <option>Progress Report</option>
                <option>Discharge Summary</option>
                <option>Referral Letter</option>
                <option>Diagnostic Summary</option>
                <option>Consultation Report</option>
            </select>
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Content *</label>
            <textarea name="report_content" rows="6" required placeholder="Summary, findings, recommendations..."
                      class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">{{ $record->diagnosis }}</textarea>
        </div>
        <div class="flex justify-end gap-2 pt-2">
            <x-button variant="secondary" type="button" x-on:click="show = false">Cancel</x-button>
            <x-button variant="primary" type="submit">Generate Report</x-button>
        </div>
    </form>
</x-modal>

<x-modal name="add-procedure" max-width="lg">
    <x-modal-header title="Add Procedure" :subtitle="'Patient: ' . ($record->patient?->fullName() ?? $record->patient_id)" />
    <form method="post" action="/medical-records/{{ $record->medical_record_id }}/procedures" class="space-y-4 px-6 py-5">
        @csrf
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Procedure Name *</label>
            <input name="procedure_name" required placeholder="e.g. Wound Dressing, Suturing, X-Ray"
                   class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Procedure Date</label>
            <input type="date" name="procedure_date" value="{{ now()->toDateString() }}"
                   class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Details</label>
            <textarea name="procedure_details" rows="3" placeholder="What was performed..."
                      class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"></textarea>
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Outcome</label>
            <textarea name="outcome" rows="2" placeholder="Result / patient response..."
                      class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"></textarea>
        </div>
        <div class="flex justify-end gap-2 pt-2">
            <x-button variant="secondary" type="button" x-on:click="show = false">Cancel</x-button>
            <x-button variant="primary" type="submit">Save Procedure</x-button>
        </div>
    </form>
</x-modal>
