<?php

namespace App\Http\Controllers;

use App\Jobs\SyncMedicalRecordVersionJob;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\MedicalRecordAdjustment;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\VitalSign;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MedicalRecordController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $doctorId = $request->query('doctor_id');

        $records = DB::table('medical_record as mr')
            ->join('patient as p', 'p.patient_id', '=', 'mr.patient_id')
            ->join('doctor as d', 'd.doctor_id', '=', 'mr.doctor_id')
            ->join('staff as s', 's.staff_id', '=', 'd.staff_id')
            ->when($q !== '', function ($query) use ($q) {
                $like = '%' . $q . '%';
                $query->where('mr.medical_record_id', 'ilike', $like)
                    ->orWhere('mr.patient_id', 'ilike', $like)
                    ->orWhereRaw("(p.first_name||' '||p.last_name) ilike ?", [$like])
                    ->orWhereRaw("(s.first_name||' '||s.last_name) ilike ?", [$like]);
            })
            ->when($doctorId, fn ($query) => $query->where('mr.doctor_id', $doctorId))
            ->orderByDesc('mr.created_at')
            ->selectRaw("mr.*, (p.first_name||' '||p.last_name) as patient_name, (s.first_name||' '||s.last_name) as doctor_name")
            ->limit(200)->get();

        $versionCounts = DB::connection('mongodb')->table('medical_record_versions')
            ->whereIn('medical_record_id', $records->pluck('medical_record_id'))
            ->get()->groupBy('medical_record_id')->map->count();

        return view('medical.index', [
            'records' => $records, 'q' => $q, 'doctorId' => $doctorId,
            'doctors' => Doctor::with('staff')->get(),
            'versionCounts' => $versionCounts,
        ]);
    }

    public function show(string $id)
    {
        $record = MedicalRecord::with('patient', 'doctor.staff')->findOrFail($id);
        cache()->put('mr:viewed:' . Auth::id(), $record->medical_record_id, 600);

        $versions = DB::connection('mongodb')->table('medical_record_versions')
            ->where('medical_record_id', $record->medical_record_id)->orderBy('version')->get();

        return view('medical.show', [
            'record' => $record,
            'adjustments' => $record->adjustments()->orderByDesc('adjusted_at')->get(),
            'versions' => $versions,
            'prescriptions' => $record->prescriptions()->with('items.medicine')->orderByDesc('prescription_date')->get(),
            'medicines' => Medicine::orderBy('medicine_name')->get(),
        ]);
    }

    public function create(Request $request)
    {
        $patient = $request->query('patient_id') ? Patient::find($request->query('patient_id')) : null;

        return view('medical.create', [
            'patient' => $patient,
            'doctors'  => Doctor::with('staff')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'patient_id' => 'required|exists:patient,patient_id',
            'doctor_id'  => 'required|exists:doctor,doctor_id',
            'symptoms'   => 'nullable|string',
            'diagnosis'  => 'required|string',
            'treatment_notes' => 'nullable|string',
            'temperature'    => 'nullable|numeric',
            'blood_pressure' => 'nullable|string|max:20',
            'heart_rate'     => 'nullable|integer',
            'height'         => 'nullable|numeric',
            'weight'         => 'nullable|numeric',
        ]);

        $vitalFields = ['temperature', 'blood_pressure', 'heart_rate', 'height', 'weight'];
        $vitals = collect($data)->only($vitalFields)->filter()->all();
        $recordData = collect($data)->except($vitalFields)->all();
        $recordData['created_by'] = Auth::user()->staff_id;
        $record = MedicalRecord::create($recordData);

        if (! empty($vitals)) {
            VitalSign::create($vitals + [
                'patient_id' => $record->patient_id,
                'medical_record_id' => $record->medical_record_id,
                'recorded_by' => Auth::user()->staff_id,
            ]);
        }

        // Immutable original version mirrored to MongoDB via the Central Service.
        SyncMedicalRecordVersionJob::dispatch(
            $record->medical_record_id, 1, 'original', $recordData,
            Auth::user()->staff_id, null, now()->toIso8601String(),
        );
        $this->audit->log('medical_record.create', 'medical_record', $record->medical_record_id);

        return redirect('/medical-records')->with('success', "Medical record {$record->medical_record_id} created.");
    }

    public function adjust(Request $request, string $id)
    {
        $record = MedicalRecord::findOrFail($id);
        $data = $request->validate([
            'symptoms'  => 'nullable|string',
            'diagnosis' => 'nullable|string',
            'treatment_notes' => 'nullable|string',
            'reason'    => 'required|string',  // mandatory: who/why/when
        ]);

        // The original is never overwritten; the amendment is appended.
        MedicalRecordAdjustment::create([
            'adjustment_id' => 'ADJ' . strtoupper(Str::random(8)),
            'medical_record_id' => $record->medical_record_id,
            'symptoms' => $data['symptoms'] ?? null,
            'diagnosis' => $data['diagnosis'] ?? null,
            'treatment_notes' => $data['treatment_notes'] ?? null,
            'adjusted_by' => Auth::user()->staff_id,
            'reason' => $data['reason'],
        ]);

        $version = DB::connection('mongodb')->table('medical_record_versions')
            ->where('medical_record_id', $record->medical_record_id)->count() + 1;
        SyncMedicalRecordVersionJob::dispatch(
            $record->medical_record_id, $version, 'adjustment', $data,
            Auth::user()->staff_id, $data['reason'], now()->toIso8601String(),
        );
        $this->audit->log('medical_record.adjust', 'medical_record', $record->medical_record_id, ['reason' => $data['reason']]);

        return redirect('/medical-records')->with('success', 'Adjustment recorded; original version preserved.')->with('reopen_record', $record->medical_record_id);
    }
}
