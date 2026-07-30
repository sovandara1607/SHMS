<?php

namespace App\Http\Controllers;

use App\DTOs\MedicalRecordDTO;
use App\DTOs\PatientDTO;
use App\Models\Doctor;
use App\Services\AuditLogger;
use App\Services\CentralServiceBus;
use App\Services\CentralServiceClient;
use App\Support\DropdownCache;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MedicalRecordController extends Controller
{
    public function __construct(
        private AuditLogger $audit,
        private CentralServiceBus $bus,
        private CentralServiceClient $centralService,
    ) {}

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $doctorId = $request->query('doctor_id');
        $date = $request->query('date');
        $page = (int) $request->query('page', 1);

        $response = $this->centralService->listMedicalRecords([
            'q' => $q, 'doctor_id' => $doctorId, 'date' => $date, 'page' => $page,
        ])->throw();
        $body = $response->json();

        $records = new LengthAwarePaginator(
            collect($body['data'])->map(fn (array $r) => (object) $r),
            $body['meta']['total'],
            $body['meta']['per_page'],
            $body['meta']['current_page'],
            ['path' => $request->url(), 'query' => $request->query()],
        );

        $versionCounts = DB::connection('mongodb')->table('medical_record_versions')
            ->whereIn('medical_record_id', $records->pluck('medical_record_id'))
            ->get()->groupBy('medical_record_id')->map->count();

        $doctorsDebug = DropdownCache::remember('doctors-with-staff', fn () => Doctor::with('staff')->get()->map(fn ($d) => (object) [
                'doctor_id' => $d->doctor_id,
                'staff_id' => $d->staff_id,
                'specialization' => $d->specialization,
                'name' => $d->name(),
                'first_name' => $d->staff?->first_name,
                'last_name' => $d->staff?->last_name,
            ]));
        \Log::info('CTRL doctors', ['items' => $doctorsDebug->map(fn ($d) => is_object($d) ? get_class($d) : gettype($d).':'.var_export($d, true))->all()]);

        return view('medical.index', [
            'records' => $records, 'q' => $q, 'doctorId' => $doctorId, 'date' => $date,
            'doctors' => $doctorsDebug,
            'versionCounts' => $versionCounts,
        ]);
    }

    /** Distinct from show(): a timeline of the record's MongoDB version snapshots, not a re-display of the current record. */
    public function history(string $id)
    {
        $response = $this->centralService->getMedicalRecord($id);
        abort_if($response->status() === 404, 404);
        $response->throw();
        $body = $response->json();
        $record = MedicalRecordDTO::fromArray($body);

        $versions = DB::connection('mongodb')->table('medical_record_versions')
            ->where('medical_record_id', $id)->orderByDesc('version')->get();

        return view('medical.history', [
            'record' => $record,
            'versions' => $versions,
            'doctors' => DropdownCache::remember('doctors-with-staff', fn () => Doctor::with('staff')->get()->map(fn ($d) => (object) [
                'doctor_id' => $d->doctor_id,
                'staff_id' => $d->staff_id,
                'specialization' => $d->specialization,
                'name' => $d->name(),
                'first_name' => $d->staff?->first_name,
                'last_name' => $d->staff?->last_name,
            ])),
        ]);
    }

    public function show(string $id)
    {
        $response = $this->centralService->getMedicalRecord($id);
        abort_if($response->status() === 404, 404);
        $response->throw();
        $body = $response->json();

        $record = MedicalRecordDTO::fromArray($body);

        $versions = DB::connection('mongodb')->table('medical_record_versions')
            ->where('medical_record_id', $record->medical_record_id)->orderBy('version')->get();

        $adjustments = collect($body['adjustments'])->map(fn (array $a) => (object) $a);
        $prescriptions = collect($body['prescriptions'])->map(function (array $pr) {
            $pr = (object) $pr;
            $pr->items = collect($pr->items)->map(function (array $item) {
                $item = (object) $item;
                $item->medicine = $item->medicine_name !== null ? (object) ['medicine_name' => $item->medicine_name] : null;

                return $item;
            });

            return $pr;
        });
        $reports = collect($body['reports'] ?? [])->map(fn (array $r) => (object) $r);
        $procedures = collect($body['procedures'] ?? [])->map(fn (array $p) => (object) $p);
        $vitalSigns = collect($body['vital_signs'] ?? [])->map(fn (array $v) => (object) $v);

        $medicines = collect($this->centralService->listAllMedicines()->throw()->json())
            ->map(fn (array $m) => (object) $m);

        return view('medical.show', [
            'record' => $record,
            'adjustments' => $adjustments,
            'versions' => $versions,
            'prescriptions' => $prescriptions,
            'medicines' => $medicines,
            'reports' => $reports,
            'procedures' => $procedures,
            'vitalSigns' => $vitalSigns,
        ]);
    }

    public function create(Request $request)
    {
        $patient = null;
        if ($request->query('patient_id')) {
            $patientResponse = $this->centralService->getPatient($request->query('patient_id'));
            if ($patientResponse->successful()) {
                $patient = PatientDTO::fromArray($patientResponse->json());
            }
        }

        $medicines = collect($this->centralService->listAllMedicines()->throw()->json())
            ->map(fn (array $m) => (object) $m);

        return view('medical.create', [
            'patient' => $patient,
            'doctors'  => DropdownCache::remember('doctors-with-staff', fn () => Doctor::with('staff')->get()->map(fn ($d) => (object) [
                'doctor_id' => $d->doctor_id,
                'staff_id' => $d->staff_id,
                'specialization' => $d->specialization,
                'name' => $d->name(),
                'first_name' => $d->staff?->first_name,
                'last_name' => $d->staff?->last_name,
            ])),
            'medicines' => $medicines,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'patient_id' => 'required|exists:patient,patient_id',
            'doctor_id'  => 'required|exists:doctor,doctor_id',
            'appointment_id' => 'nullable|exists:appointment,appointment_id',
            'symptoms'   => 'nullable|string',
            'diagnosis'  => 'required|string',
            'treatment_notes' => 'nullable|string',
            'temperature'    => 'nullable|numeric',
            'blood_pressure' => 'nullable|string|max:20',
            'heart_rate'     => 'nullable|integer',
            'height'         => 'nullable|numeric',
            'weight'         => 'nullable|numeric',
        ]);
        // Optional inline prescription, filled in the same "document the visit" form.
        // Rows are nullable so an untouched template row doesn't fail validation —
        // only rows the doctor actually picked a medicine for get submitted below.
        $prescriptionData = $request->validate([
            'prescription_notes'          => 'nullable|string',
            'items'                       => 'nullable|array',
            'items.*.medicine_id'         => 'nullable|exists:medicine,medicine_id',
            'items.*.dosage'              => 'nullable|string|max:100',
            'items.*.frequency'           => 'nullable|string|max:100',
            'items.*.duration'            => 'nullable|string|max:100',
            'items.*.quantity'            => 'nullable|integer|min:1',
        ]);
        $data['created_by'] = Auth::user()->staff_id;

        $response = $this->centralService->createMedicalRecord($data)->throw();
        $record = $response->json();

        // Immutable original version mirrored to MongoDB via central-service.
        $vitalFields = ['temperature', 'blood_pressure', 'heart_rate', 'height', 'weight'];
        $recordSnapshot = collect($data)->except($vitalFields)->all();
        $this->bus->publish('sync_medical_record_version', [
            'medicalRecordId' => $record['medical_record_id'],
            'version' => 1,
            'type' => 'original',
            'snapshot' => $recordSnapshot,
            'actorStaffId' => Auth::user()->staff_id,
            'reason' => null,
            'createdAt' => now()->toIso8601String(),
        ]);
        $this->audit->log('medical_record.create', 'medical_record', $record['medical_record_id']);

        $prescribedItems = collect($prescriptionData['items'] ?? [])->filter(fn ($item) => filled($item['medicine_id'] ?? null))->values();
        $prescriptionWarning = null;
        if ($prescribedItems->isNotEmpty()) {
            $prescriptionResponse = $this->centralService->createPrescription($record['medical_record_id'], [
                'notes' => $prescriptionData['prescription_notes'] ?? null,
                'items' => $prescribedItems->all(),
            ]);
            if ($prescriptionResponse->successful()) {
                $prescription = $prescriptionResponse->json();
                $this->audit->log('prescription.create', 'prescription', $prescription['prescription_id'], [
                    'medical_record_id' => $record['medical_record_id'], 'items' => $prescribedItems->count(),
                ]);
            } else {
                $prescriptionWarning = 'The record was created, but the prescription could not be saved — add it from the record\'s page.';
            }
        }

        $redirect = redirect('/medical-records')->with('success', "Medical record {$record['medical_record_id']} created.");
        if ($prescriptionWarning) {
            $redirect->with('warning', $prescriptionWarning);
        }

        return $redirect;
    }

    public function adjust(Request $request, string $id)
    {
        $data = $request->validate([
            'symptoms'  => 'nullable|string',
            'diagnosis' => 'nullable|string',
            'treatment_notes' => 'nullable|string',
            'reason'    => 'required|string',  // mandatory: who/why/when
        ]);
        $data['adjusted_by'] = Auth::user()->staff_id;

        $response = $this->centralService->adjustMedicalRecord($id, $data);
        abort_if($response->status() === 404, 404);
        $response->throw();

        // This count()+1 is advisory only — two adjustments issued close together
        // could both read the same count before either's job has written. The
        // authoritative version number is assigned atomically by
        // SyncMedicalRecordVersionJob (central-service) at actual-write time via
        // a Mongo counter document, which is what the unique (medical_record_id,
        // version) index now enforces.
        $version = DB::connection('mongodb')->table('medical_record_versions')
            ->where('medical_record_id', $id)->count() + 1;
        $this->bus->publish('sync_medical_record_version', [
            'medicalRecordId' => $id,
            'version' => $version,
            'type' => 'adjustment',
            'snapshot' => collect($data)->except('adjusted_by')->all(),
            'actorStaffId' => Auth::user()->staff_id,
            'reason' => $data['reason'],
            'createdAt' => now()->toIso8601String(),
        ]);
        $this->audit->log('medical_record.adjust', 'medical_record', $id, ['reason' => $data['reason']]);

        return redirect('/medical-records')->with('success', 'Adjustment recorded; original version preserved.')->with('reopen_record', $id);
    }
}
