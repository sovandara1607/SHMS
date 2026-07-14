<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\Nurse;
use App\Models\Patient;
use App\Models\PatientInsurance;
use App\Models\StaffShift;
use App\Services\AuditLogger;
use App\Services\RoomAssignmentService;
use Illuminate\Http\Request;

class PatientController extends Controller
{
    public function __construct(private AuditLogger $audit, private RoomAssignmentService $roomAssignments) {}

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status', 'all');
        $user = $request->user();

        $patients = Patient::query()
            ->when($q !== '', function ($query) use ($q) {
                $like = '%' . $q . '%';
                $query->where('patient_id', 'ilike', $like)
                    ->orWhereRaw("(first_name || ' ' || last_name) ilike ?", [$like])
                    ->orWhere('phone_number', 'ilike', $like)
                    ->orWhere('email', 'ilike', $like);
            })
            ->when($status !== 'all', fn ($query) => $query->where('patient_status', $status))
            ->when($user->role === 'doctor', function ($query) use ($user) {
                $doctorId = $user->staff?->doctor?->doctor_id;
                $query->whereHas('doctorAssignments', fn ($q) => $q
                    ->where('doctor_id', $doctorId)
                    ->where('status', 'active'));
            })
            ->orderByDesc('created_at')
            ->limit(200)->get();

        return view('patient.index', compact('patients', 'q', 'status'));
    }

    /**
     * Lightweight JSON lookup backing the patient-picker used by
     * appointment/billing/vitals/medical-record forms — never dump the full
     * patient table into a page (it's seeded to 1M+ rows for scale testing).
     */
    public function search(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json([]);
        }

        $like = '%' . $q . '%';
        $patients = Patient::query()
            ->where('patient_id', 'ilike', $like)
            ->orWhereRaw("(first_name || ' ' || last_name) ilike ?", [$like])
            ->orderBy('last_name')
            ->limit(20)
            ->get(['patient_id', 'first_name', 'last_name', 'patient_status']);

        return response()->json($patients->map(fn (Patient $p) => [
            'id' => $p->patient_id,
            'label' => $p->fullName() . ' (' . $p->patient_id . ')',
            'name' => $p->fullName(),
            'status' => $p->patient_status,
        ]));
    }

    public function show(string $id)
    {
        $patient = Patient::with([
            'insurance',
            'appointments' => fn ($q) => $q->with('doctor.staff')->orderByDesc('appointment_date'),
            'doctorAssignments' => fn ($q) => $q->with('doctor.staff', 'assignedByStaff')->orderByDesc('assigned_at'),
            'nurseAssignments' => fn ($q) => $q->with('nurse.staff', 'shift', 'assignedByStaff')->orderByDesc('assigned_at'),
            'roomAssignments' => fn ($q) => $q->with('room', 'bed')->orderByDesc('assigned_at'),
            'medicalRecords' => fn ($q) => $q->with('doctor.staff')->orderByDesc('created_at'),
            'bills' => fn ($q) => $q->orderByDesc('bill_date'),
        ])->findOrFail($id);

        $user = request()->user();
        if ($user->role === 'doctor') {
            $doctorId = $user->staff?->doctor?->doctor_id;
            $isAssigned = $patient->doctorAssignments->contains(
                fn ($a) => $a->doctor_id === $doctorId && $a->status === 'active'
            );
            abort_unless($isAssigned, 403, 'You are not assigned to this patient.');
        }

        // Frequently-viewed patient cache (Redis, 5 min).
        cache()->put('patient:viewed:' . $patient->patient_id, $patient->toJson(), 300);

        $doctors = Doctor::with('staff')->get();
        $nurses = Nurse::with('staff')->get();
        $shifts = StaffShift::orderByDesc('shift_date')->limit(50)->get();

        return view('patient.show', compact('patient', 'doctors', 'nurses', 'shifts'));
    }

    public function create()
    {
        return view('patient.form', ['patient' => new Patient(), 'insurance' => new PatientInsurance(), 'mode' => 'create']);
    }

    public function store(Request $request)
    {
        [$patientData, $insuranceData] = $this->validateData($request);
        $patient = Patient::create($patientData);
        if ($insuranceData['insurance_provider'] ?? null) {
            PatientInsurance::create($insuranceData + ['patient_id' => $patient->patient_id]);
        }
        $this->audit->log('patient.create', 'patient', $patient->patient_id);

        return redirect('/patients')->with('success', "Patient {$patient->patient_id} registered.");
    }

    public function edit(string $id)
    {
        $patient = Patient::findOrFail($id);
        $insurance = $patient->insurance()->latest('start_date')->first() ?? new PatientInsurance();

        return view('patient.form', ['patient' => $patient, 'insurance' => $insurance, 'mode' => 'edit']);
    }

    public function update(Request $request, string $id)
    {
        $patient = Patient::findOrFail($id);
        [$patientData, $insuranceData] = $this->validateData($request);
        $patient->update($patientData);

        if ($insuranceData['insurance_provider'] ?? null) {
            $insurance = $patient->insurance()->latest('start_date')->first() ?? new PatientInsurance(['patient_id' => $patient->patient_id]);
            $insurance->fill($insuranceData);
            $insurance->patient_id = $patient->patient_id;
            $insurance->save();
        }

        $this->audit->log('patient.update', 'patient', $patient->patient_id);

        return redirect('/patients')->with('success', 'Patient updated.');
    }

    public function discharge(string $id)
    {
        $patient = Patient::findOrFail($id);
        $patient->update(['patient_status' => 'discharged']);
        $this->roomAssignments->releaseForPatient($patient->patient_id);
        $this->audit->log('patient.discharge', 'patient', $patient->patient_id);

        return redirect('/patients')->with('success', 'Patient discharged; bed released.')->with('reopen_patient', $patient->patient_id);
    }

    /** @return array{0: array, 1: array} */
    private function validateData(Request $request): array
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'gender'     => 'nullable|in:male,female,other',
            'date_of_birth' => 'nullable|date',
            'phone_number'  => 'nullable|string|max:20',
            'email'         => 'nullable|email|max:100',
            'address'       => 'nullable|string|max:255',
            'blood_type'    => 'nullable|string|max:5',
            'allergy'       => 'nullable|string',
            'emergency_contact_name'  => 'nullable|string|max:100',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'patient_status' => 'nullable|in:active,admitted,icu,discharged,inactive',
            'insurance_provider' => 'nullable|string|max:100',
            'policy_number'      => 'nullable|string|max:100',
            'coverage_details'   => 'nullable|string',
            'policy_start'       => 'nullable|date',
            'policy_end'         => 'nullable|date',
        ]);

        $insurance = [
            'insurance_provider' => $data['insurance_provider'] ?? null,
            'policy_number'      => $data['policy_number'] ?? null,
            'coverage_details'   => $data['coverage_details'] ?? null,
            'start_date'         => $data['policy_start'] ?? null,
            'end_date'           => $data['policy_end'] ?? null,
        ];

        $patient = collect($data)->except(['insurance_provider', 'policy_number', 'coverage_details', 'policy_start', 'policy_end'])->all();
        if (empty($patient['patient_status'])) {
            unset($patient['patient_status']);
        }

        return [$patient, $insurance];
    }
}
