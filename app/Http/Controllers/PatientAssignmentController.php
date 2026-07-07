<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\PatientDoctorAssignment;
use App\Models\PatientNurseAssignment;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

/**
 * Long-term / special-care doctor and nurse assignments for admitted
 * patients (distinct from per-visit Appointments). Mirrors the "Doctor
 * Assignments" / "Nurse Assignments" tabs on the patient detail modal.
 */
class PatientAssignmentController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function storeDoctor(Request $request, string $patientId)
    {
        $patient = Patient::findOrFail($patientId);
        $data = $request->validate([
            'doctor_id' => 'required|string|exists:doctor,doctor_id',
            'role'      => 'nullable|in:main_doctor,consultant,specialist',
        ]);

        $assignment = PatientDoctorAssignment::create([
            'patient_id'  => $patient->patient_id,
            'doctor_id'   => $data['doctor_id'],
            'role'        => $data['role'] ?? null,
            'assigned_by' => $request->user()->staff_id,
        ]);

        $this->audit->log('patient_doctor_assignment.create', 'patient', $patient->patient_id, ['assignment_id' => $assignment->assignment_id]);

        return redirect('/patients')->with('success', 'Doctor assigned.')->with('reopen_patient', $patient->patient_id);
    }

    public function endDoctor(string $id)
    {
        $assignment = PatientDoctorAssignment::findOrFail($id);
        $assignment->update(['status' => 'completed', 'ended_at' => now()]);
        $this->audit->log('patient_doctor_assignment.end', 'patient', $assignment->patient_id, ['assignment_id' => $assignment->assignment_id]);

        return redirect('/patients')->with('success', 'Doctor assignment ended.')->with('reopen_patient', $assignment->patient_id);
    }

    public function storeNurse(Request $request, string $patientId)
    {
        $patient = Patient::findOrFail($patientId);
        $data = $request->validate([
            'nurse_id' => 'required|string|exists:nurse,nurse_id',
            'shift_id' => 'nullable|string|exists:staff_shift,shift_id',
        ]);

        $assignment = PatientNurseAssignment::create([
            'patient_id'  => $patient->patient_id,
            'nurse_id'    => $data['nurse_id'],
            'shift_id'    => $data['shift_id'] ?? null,
            'assigned_by' => $request->user()->staff_id,
        ]);

        $this->audit->log('patient_nurse_assignment.create', 'patient', $patient->patient_id, ['assignment_id' => $assignment->assignment_id]);

        return redirect('/patients')->with('success', 'Nurse assigned.')->with('reopen_patient', $patient->patient_id);
    }

    public function endNurse(string $id)
    {
        $assignment = PatientNurseAssignment::findOrFail($id);
        $assignment->update(['status' => 'completed', 'ended_at' => now()]);
        $this->audit->log('patient_nurse_assignment.end', 'patient', $assignment->patient_id, ['assignment_id' => $assignment->assignment_id]);

        return redirect('/patients')->with('success', 'Nurse assignment ended.')->with('reopen_patient', $assignment->patient_id);
    }
}
