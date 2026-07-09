<?php

namespace App\Http\Controllers;

use App\Models\VitalSign;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClinicalController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function vitalSigns()
    {
        $vitals = DB::table('vital_signs as v')
            ->join('patient as p', 'p.patient_id', '=', 'v.patient_id')
            ->orderByDesc('v.recorded_at')
            ->selectRaw("v.*, (p.first_name||' '||p.last_name) as patient_name")
            ->limit(100)->get();

        return view('medical.vitals', ['vitals' => $vitals]);
    }

    public function storeVitals(Request $request)
    {
        $data = $request->validate([
            'patient_id'     => 'required|exists:patient,patient_id',
            'temperature'    => 'nullable|numeric',
            'blood_pressure' => 'nullable|string|max:20',
            'heart_rate'     => 'nullable|integer',
            'height'         => 'nullable|numeric',
            'weight'         => 'nullable|numeric',
        ]);
        $data['vital_sign_id'] = 'VS' . strtoupper(Str::random(8));
        $data['recorded_by'] = Auth::user()->staff_id;
        VitalSign::create($data);
        $this->audit->log('vital_signs.create', 'vital_signs', $data['patient_id']);
        return redirect('/vital-signs')->with('success', 'Vital signs recorded.');
    }
}
