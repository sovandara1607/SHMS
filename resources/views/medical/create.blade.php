@if(! $patient)
    <x-modal-header title="Add Medical Record" subtitle="Step 1 of 2 — Search and select a patient" icon="document" />
    <div
        x-data="{
            query: '',
            results: [],
            search() {
                if (this.query.length < 2) { this.results = []; return; }
                fetch('/patients/search?q=' + encodeURIComponent(this.query))
                    .then(r => r.json())
                    .then(data => { this.results = data; });
            },
            age(dob) {
                if (!dob) return '—';
                const d = new Date(dob), now = new Date();
                let age = now.getFullYear() - d.getFullYear();
                if (now.getMonth() < d.getMonth() || (now.getMonth() === d.getMonth() && now.getDate() < d.getDate())) age--;
                return age + ' yrs';
            },
        }"
        class="max-h-[65vh] overflow-y-auto px-6 py-5"
    >
        <input type="text" x-model="query" @input.debounce.300ms="search()" autocomplete="off"
               placeholder="Search by patient name or patient ID..."
               class="mb-4 w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
                    <th class="pb-2">Patient ID</th><th class="pb-2">Patient Name</th><th class="pb-2">Gender</th><th class="pb-2">Age</th><th class="pb-2">Phone</th><th class="pb-2">Status</th><th class="pb-2"></th>
                </tr></thead>
                <tbody>
                <template x-for="p in results" :key="p.id">
                    <tr class="border-b border-slate-50">
                        <td class="py-2 font-medium text-blue-600" x-text="p.id"></td>
                        <td class="py-2 text-slate-900">
                            <div class="flex items-center gap-2">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                                    <x-icon name="users" class="h-3.5 w-3.5" />
                                </span>
                                <span x-text="p.name"></span>
                            </div>
                        </td>
                        <td class="py-2 text-slate-600" x-text="p.gender ? (p.gender.charAt(0).toUpperCase() + p.gender.slice(1)) : '—'"></td>
                        <td class="py-2 text-slate-600" x-text="age(p.date_of_birth)"></td>
                        <td class="py-2 text-slate-600" x-text="p.phone_number || '—'"></td>
                        <td class="py-2">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold"
                                  :class="({active: 'bg-green-50 text-green-700', admitted: 'bg-blue-50 text-blue-700', icu: 'bg-red-50 text-red-700', discharged: 'bg-purple-50 text-purple-700', inactive: 'bg-slate-100 text-slate-500'})[p.status] || 'bg-slate-100 text-slate-600'"
                                  x-text="p.status.charAt(0).toUpperCase() + p.status.slice(1)"></span>
                        </td>
                        <td class="py-2 text-right">
                            <button type="button" class="text-sm font-medium text-blue-600 hover:underline"
                                    x-on:click="openModal('/medical-records/create?patient_id=' + p.id)">Select &rarr;</button>
                        </td>
                    </tr>
                </template>
                <tr x-show="query.length >= 2 && results.length === 0" style="display: none;">
                    <td colspan="7" class="py-4 text-center text-slate-400">No patients found.</td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
@else
    <x-modal-header title="Add Medical Record" :subtitle="'Patient: ' . $patient->fullName()" icon="document" />
    <form id="medical-record-form" method="post" action="/medical-records" class="max-h-[65vh] overflow-y-auto px-6 py-5">
        @csrf
        <input type="hidden" name="patient_id" value="{{ $patient->patient_id }}">
        <input type="hidden" name="_modal_target" value="/medical-records/create?patient_id={{ $patient->patient_id }}">

        @php($age = $patient->date_of_birth ? \Carbon\Carbon::parse($patient->date_of_birth)->age . ' yrs' : '—')
        <div class="mb-5 rounded-lg bg-slate-50 px-4 py-3">
            <div class="mb-2 flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Patient Information</p>
                <a href="#" x-on:click.prevent="openModal('/medical-records/create')" class="text-xs font-medium text-blue-600 hover:underline">Change Patient</a>
            </div>
            <div class="grid grid-cols-2 gap-y-2 text-sm">
                <div><p class="text-xs text-slate-500">Patient ID</p><p class="font-medium text-slate-900">{{ $patient->patient_id }}</p></div>
                <div><p class="text-xs text-slate-500">Patient Name</p><p class="text-slate-900">{{ $patient->fullName() }}</p></div>
                <div><p class="text-xs text-slate-500">Age</p><p class="text-slate-900">{{ $age }}</p></div>
                <div><p class="text-xs text-slate-500">Gender</p><p class="text-slate-900">{{ $patient->gender ? ucfirst($patient->gender) : '—' }}</p></div>
                <div><p class="text-xs text-slate-500">Phone</p><p class="text-slate-900">{{ $patient->phone_number ?: '—' }}</p></div>
            </div>
        </div>

        <p class="mb-3 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-slate-400">
            <x-icon name="document" class="h-3.5 w-3.5 text-blue-500" /> Medical Record
        </p>
        <div class="mb-4">
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Doctor *</label>
            <select name="doctor_id" required class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                <option value="">e.g. Dr. Emily Martinez</option>
                @foreach($doctors as $d)
                    <option value="{{ $d->doctor_id }}" @selected(old('doctor_id') === $d->doctor_id)>{{ $d->name() }}</option>
                @endforeach
            </select>
        </div>
        <div class="mb-4">
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Related Appointment</label>
            <input name="appointment_id" value="{{ old('appointment_id') }}" placeholder="e.g. APT0312"
                   class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
        <div class="mb-4">
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Symptoms</label>
            <textarea name="symptoms" rows="2" placeholder="Describe patient symptoms..."
                      class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">{{ old('symptoms') }}</textarea>
        </div>
        <div class="mb-4">
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Diagnosis *</label>
            <textarea name="diagnosis" rows="2" required placeholder="Enter ICD codes and diagnosis description..."
                      class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">{{ old('diagnosis') }}</textarea>
        </div>
        <div class="mb-5">
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Treatment Notes</label>
            <textarea name="treatment_notes" rows="3" placeholder="Describe the treatment plan and notes..."
                      class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">{{ old('treatment_notes') }}</textarea>
        </div>

        <p class="mb-1 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-slate-400">
            <x-icon name="pulse" class="h-3.5 w-3.5 text-teal-500" /> Vital Signs
        </p>
        <p class="mb-3 text-xs text-slate-400">Stored in the <code class="rounded bg-slate-100 px-1 py-0.5">vital_signs</code> table, linked to this record by <code class="rounded bg-slate-100 px-1 py-0.5">medical_record_id</code>. All fields are optional.</p>
        <div class="mb-5 grid grid-cols-3 gap-4">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Temperature</label>
                <div class="relative">
                    <input name="temperature" placeholder="e.g. 37.2" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 pr-10 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                    <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400">°C</span>
                </div>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Blood Pressure</label>
                <div class="relative">
                    <input name="blood_pressure" placeholder="e.g. 120/80" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 pr-14 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                    <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400">mmHg</span>
                </div>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Heart Rate</label>
                <div class="relative">
                    <input name="heart_rate" placeholder="e.g. 72" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 pr-12 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                    <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400">bpm</span>
                </div>
            </div>
        </div>

        <div class="rounded-lg bg-slate-50 px-4 py-3">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">System-Generated</p>
            <div class="grid grid-cols-2 gap-y-1 text-sm">
                <div><p class="text-xs text-slate-500">Created By</p><p class="text-slate-900">{{ auth()->user()->staff?->fullName() ?? auth()->user()->email }} <span class="text-xs text-slate-400">(auto)</span></p></div>
                <div><p class="text-xs text-slate-500">Created At</p><p class="text-slate-900">{{ now()->toDateString() }} <span class="text-xs text-slate-400">(auto)</span></p></div>
            </div>
        </div>
    </form>
    <div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4">
        <x-button variant="secondary" x-on:click="show = false">Cancel</x-button>
        <x-button variant="primary" type="submit" form="medical-record-form">Add Record</x-button>
    </div>
@endif
