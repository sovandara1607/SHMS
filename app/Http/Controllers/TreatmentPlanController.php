<?php

namespace App\Http\Controllers;

use App\Models\MedicalRecord;
use App\Models\TreatmentPlan;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

/**
 * Doctor-side treatment plans. Like Prescriptions, the form is embedded
 * directly in the medical record show view rather than a separate page —
 * a treatment plan always hangs off the medical record it was decided in.
 */
class TreatmentPlanController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function store(Request $request, string $recordId)
    {
        $record = MedicalRecord::findOrFail($recordId);

        $data = $request->validate([
            'diagnosis_summary' => 'required|string',
            'clinical_notes'    => 'nullable|string',
            'recommended_care'  => 'nullable|string',
            'start_date'        => 'nullable|date',
            'end_date'          => 'nullable|date|after_or_equal:start_date',
        ]);

        $plan = TreatmentPlan::create($data + [
            'medical_record_id' => $record->medical_record_id,
            'doctor_id'         => $record->doctor_id,
        ]);

        $this->audit->log('treatment_plan.create', 'treatment_plan', $plan->treatment_plan_id, [
            'medical_record_id' => $record->medical_record_id,
        ]);

        return redirect('/medical-records')
            ->with('success', "Treatment plan {$plan->treatment_plan_id} created.")
            ->with('reopen_record', $record->medical_record_id);
    }

    public function updateStatus(Request $request, string $id)
    {
        $plan = TreatmentPlan::findOrFail($id);
        $data = $request->validate([
            'status' => 'required|in:active,completed,cancelled',
        ]);

        $plan->update([
            'status'   => $data['status'],
            'end_date' => $data['status'] === 'active' ? $plan->end_date : ($plan->end_date ?? now()->toDateString()),
        ]);

        $this->audit->log('treatment_plan.status', 'treatment_plan', $plan->treatment_plan_id, ['status' => $data['status']]);

        return redirect('/medical-records')
            ->with('success', "Treatment plan {$plan->treatment_plan_id} marked {$data['status']}.")
            ->with('reopen_record', $plan->medical_record_id);
    }
}
