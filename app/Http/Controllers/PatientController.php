<?php

namespace App\Http\Controllers;

use App\DTOs\NamedEntity;
use App\DTOs\PatientDTO;
use App\DTOs\PatientInsuranceDTO;
use App\Models\Doctor;
use App\Models\Nurse;
use App\Services\AuditLogger;
use App\Services\CentralServiceClient;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class PatientController extends Controller
{
    public function __construct(
        private AuditLogger $audit,
        private CentralServiceClient $centralService,
    ) {}

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status', 'all');
        $page = (int) $request->query('page', 1);
        $user = $request->user();

        $params = ['q' => $q, 'status' => $status, 'page' => $page];
        if ($user->role === 'doctor') {
            $params['assigned_doctor_id'] = $user->staff?->doctor?->doctor_id;
        }

        $response = $this->centralService->listPatients($params)->throw();
        $body = $response->json();

        $patients = new LengthAwarePaginator(
            collect($body['data'])->map(fn (array $p) => PatientDTO::fromArray($p)),
            $body['meta']['total'],
            $body['meta']['per_page'],
            $body['meta']['current_page'],
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('patient.index', compact('patients', 'q', 'status'));
    }

    /**
     * Mirrors index()'s filters exactly (including the doctor's
     * assigned-patients scoping) so the export matches what's on screen.
     * Queries Postgres directly rather than round-tripping every page
     * through central-service — the patient table can run into the
     * hundreds of thousands of rows, so this streams via cursor() instead
     * of loading everything into memory at once.
     */
    public function exportPatients(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status', 'all');
        $user = $request->user();

        $query = \App\Models\Patient::query()
            ->addSelect(['insurance_provider' => \App\Models\PatientInsurance::selectRaw('insurance_provider')
                ->whereColumn('patient_id', 'patient.patient_id')
                ->where('status', 'active')
                ->orderByDesc('start_date')
                ->limit(1),
            ])
            ->when($q !== '', function ($query) use ($q) {
                $like = '%' . $q . '%';
                $query->where(function ($sub) use ($like) {
                    $sub->where('patient_id', 'ilike', $like)
                        ->orWhereRaw("(first_name || ' ' || last_name) ilike ?", [$like]);
                });
            })
            ->when($status !== 'all', fn ($query) => $query->where('patient_status', $status))
            ->when($user->role === 'doctor', function ($query) use ($user) {
                $doctorId = $user->staff?->doctor?->doctor_id;
                $query->whereHas('doctorAssignments', fn ($q) => $q
                    ->where('doctor_id', $doctorId)
                    ->where('status', 'active'));
            })
            ->orderByDesc('created_at');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Patient ID', 'Name', 'Gender', 'Date of Birth', 'Phone', 'Blood Type', 'Insurance', 'Status']);
            foreach ($query->cursor() as $p) {
                fputcsv($out, [
                    $p->patient_id, $p->fullName(), $p->gender ? ucfirst($p->gender) : '',
                    $p->date_of_birth, $p->phone_number, $p->blood_type,
                    $p->insurance_provider, ucfirst($p->patient_status),
                ]);
            }
            fclose($out);
        }, 'patients-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
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

        return response()->json($this->centralService->searchPatients($q)->json());
    }

    public function show(string $id)
    {
        $response = $this->centralService->getPatient($id);
        abort_if($response->status() === 404, 404);
        $response->throw();

        $patient = PatientDTO::fromArray($response->json());

        $appointments = $this->centralService->listAppointments(['patient_id' => $id, 'per_page' => 100])->throw();
        $patient->appointments = collect($appointments->json('data'))->map(function (array $a) {
            $obj = (object) $a;
            $obj->doctor = isset($a['doctor_name']) ? new NamedEntity($a['doctor_name']) : null;

            return $obj;
        });

        $medicalRecords = $this->centralService->listMedicalRecords(['patient_id' => $id, 'per_page' => 100])->throw();
        $patient->medicalRecords = collect($medicalRecords->json('data'))->map(function (array $r) {
            $obj = (object) $r;
            $obj->doctor = isset($r['doctor_name']) ? new NamedEntity($r['doctor_name']) : null;

            return $obj;
        });

        $roomAssignments = $this->centralService->getRoomAssignmentsForPatient($id)->throw();
        $patient->roomAssignments = collect($roomAssignments->json())->map(function (array $ra) {
            $obj = (object) $ra;
            $obj->room = $ra['room'] ? (object) $ra['room'] : null;
            $obj->bed = $ra['bed'] ? (object) $ra['bed'] : null;

            return $obj;
        });

        $bills = $this->centralService->listBills(['patient_id' => $id, 'bills_page' => 1])->throw();
        $patient->bills = collect($bills->json('bills.data'))->map(fn (array $b) => (object) $b);

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
        $shifts = collect($this->centralService->listStaffShifts()->throw()->json())->map(fn (array $s) => (object) $s);

        return view('patient.show', compact('patient', 'doctors', 'nurses', 'shifts'));
    }

    public function create()
    {
        return view('patient.form', ['patient' => new PatientDTO(), 'insurance' => new PatientInsuranceDTO(), 'mode' => 'create']);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $response = $this->centralService->createPatient($data);
        if ($response->status() === 422) {
            return back()->withErrors($response->json('errors', []))->withInput();
        }
        $response->throw();

        $patient = $response->json();
        $this->audit->log('patient.create', 'patient', $patient['patient_id']);

        return redirect('/patients')->with('success', "Patient {$patient['patient_id']} registered.");
    }

    public function edit(string $id)
    {
        $response = $this->centralService->getPatient($id);
        abort_if($response->status() === 404, 404);
        $response->throw();

        $patient = PatientDTO::fromArray($response->json());
        $insurance = $patient->insurance->sortByDesc('start_date')->first() ?? new PatientInsuranceDTO();

        return view('patient.form', ['patient' => $patient, 'insurance' => $insurance, 'mode' => 'edit']);
    }

    /**
     * Applies the edit directly to the patient row and logs it as a
     * patient_adjustment entry with a mandatory reason, so the Patient
     * Information page always shows the latest values while the full
     * edit history stays visible in Adjustment History.
     */
    public function adjust(Request $request, string $id)
    {
        $data = $request->validate($this->validationRules() + ['reason' => 'required|string']);
        $data['adjusted_by'] = $request->user()->staff_id;

        $response = $this->centralService->adjustPatient($id, $data);
        if ($response->status() === 404) {
            abort(404);
        }
        if ($response->status() === 422) {
            return back()->withErrors($response->json('errors', []))->withInput();
        }
        $response->throw();

        $this->audit->log('patient.adjust', 'patient', $id, ['reason' => $data['reason']]);

        return redirect('/patients')->with('success', 'Patient information updated.')->with('reopen_patient', $id);
    }

    public function discharge(string $id)
    {
        $response = $this->centralService->dischargePatient($id);
        abort_if($response->status() === 404, 404);
        $response->throw();

        $this->centralService->releaseRoomForPatient($id)->throw();
        $this->audit->log('patient.discharge', 'patient', $id);

        return redirect('/patients')->with('success', 'Patient discharged; bed released.')->with('reopen_patient', $id);
    }

    private function validateData(Request $request): array
    {
        return $request->validate($this->validationRules());
    }

    private function validationRules(): array
    {
        return [
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
        ];
    }
}
