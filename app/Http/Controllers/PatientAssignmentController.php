<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Services\CentralServiceClient;
use Illuminate\Http\Request;

/**
 * Long-term / special-care doctor and nurse assignments for admitted
 * patients (distinct from per-visit Appointments). Mirrors the "Doctor
 * Assignments" / "Nurse Assignments" tabs on the patient detail modal.
 */
class PatientAssignmentController extends Controller
{
    public function __construct(private AuditLogger $audit, private CentralServiceClient $centralService) {}

    public function storeDoctor(Request $request, string $patientId)
    {
        $data = $request->validate([
            'doctor_id' => 'required|string|exists:doctor,doctor_id',
            'role'      => 'nullable|in:main_doctor,consultant,specialist',
        ]);
        $data['assigned_by'] = $request->user()->staff_id;

        $response = $this->centralService->createDoctorAssignment($patientId, $data);
        if ($response->status() === 404) {
            abort(404);
        }
        if ($response->status() === 422) {
            return back()->withErrors($response->json('errors', []))->withInput();
        }
        $response->throw();

        $assignment = $response->json();
        $this->audit->log('patient_doctor_assignment.create', 'patient', $patientId, ['assignment_id' => $assignment['assignment_id']]);

        return redirect('/patients')->with('success', 'Doctor assigned.')->with('reopen_patient', $patientId);
    }

    public function endDoctor(string $id)
    {
        $response = $this->centralService->endDoctorAssignment($id);
        abort_if($response->status() === 404, 404);
        $response->throw();

        $assignment = $response->json();
        $this->audit->log('patient_doctor_assignment.end', 'patient', $assignment['patient_id'], ['assignment_id' => $assignment['assignment_id']]);

        return redirect('/patients')->with('success', 'Doctor assignment ended.')->with('reopen_patient', $assignment['patient_id']);
    }

    public function storeNurse(Request $request, string $patientId)
    {
        $data = $request->validate([
            'nurse_id' => 'required|string|exists:nurse,nurse_id',
            'shift_id' => 'nullable|string|exists:staff_shift,shift_id',
        ]);
        $data['assigned_by'] = $request->user()->staff_id;

        $response = $this->centralService->createNurseAssignment($patientId, $data);
        if ($response->status() === 404) {
            abort(404);
        }
        if ($response->status() === 422) {
            return back()->withErrors($response->json('errors', []))->withInput();
        }
        $response->throw();

        $assignment = $response->json();
        $this->audit->log('patient_nurse_assignment.create', 'patient', $patientId, ['assignment_id' => $assignment['assignment_id']]);

        return redirect('/patients')->with('success', 'Nurse assigned.')->with('reopen_patient', $patientId);
    }

    public function endNurse(string $id)
    {
        $response = $this->centralService->endNurseAssignment($id);
        abort_if($response->status() === 404, 404);
        $response->throw();

        $assignment = $response->json();
        $this->audit->log('patient_nurse_assignment.end', 'patient', $assignment['patient_id'], ['assignment_id' => $assignment['assignment_id']]);

        return redirect('/patients')->with('success', 'Nurse assignment ended.')->with('reopen_patient', $assignment['patient_id']);
    }
}
