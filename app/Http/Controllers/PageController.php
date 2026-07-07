<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Read-only list pages (schedule, treatment, prescription, procedure,
 * reports) plus profile/settings that share the generic table view.
 */
class PageController extends Controller
{
    public function schedule()
    {
        $rows = DB::table('staff_shift as sh')
            ->join('staff as s', 's.staff_id', '=', 'sh.staff_id')
            ->orderByDesc('sh.shift_date')
            ->selectRaw("sh.*, (s.first_name||' '||s.last_name) as staff_name")->limit(200)->get();

        return $this->table('Schedule Management',
            ['shift_id' => 'Shift', 'staff_name' => 'Staff', 'shift_date' => 'Date', 'start_time' => 'Start', 'end_time' => 'End', 'shift_type' => 'Type', 'status' => 'Status'], $rows);
    }

    public function treatments()
    {
        $rows = DB::table('treatment_plan as tp')
            ->join('doctor as d', 'd.doctor_id', '=', 'tp.doctor_id')
            ->join('staff as s', 's.staff_id', '=', 'd.staff_id')
            ->orderByDesc('tp.start_date')
            ->selectRaw("tp.*, (s.first_name||' '||s.last_name) as doctor_name")->limit(200)->get();

        return $this->table('Treatment Management',
            ['treatment_plan_id' => 'Plan', 'doctor_name' => 'Doctor', 'diagnosis_summary' => 'Diagnosis', 'recommended_care' => 'Care', 'status' => 'Status'], $rows);
    }

    public function prescriptions()
    {
        $rows = DB::table('prescription as pr')
            ->join('patient as p', 'p.patient_id', '=', 'pr.patient_id')
            ->orderByDesc('pr.prescription_date')
            ->selectRaw("pr.*, (p.first_name||' '||p.last_name) as patient_name")->limit(200)->get();

        return $this->table('Prescriptions',
            ['prescription_id' => 'ID', 'patient_name' => 'Patient', 'prescription_date' => 'Date', 'notes' => 'Notes'], $rows);
    }

    public function procedures()
    {
        $rows = DB::table('medical_procedure as mp')
            ->join('patient as p', 'p.patient_id', '=', 'mp.patient_id')
            ->orderByDesc('mp.procedure_date')
            ->selectRaw("mp.*, (p.first_name||' '||p.last_name) as patient_name")->limit(200)->get();

        return $this->table('Medical Procedures',
            ['procedure_id' => 'ID', 'patient_name' => 'Patient', 'procedure_name' => 'Procedure', 'outcome' => 'Outcome', 'procedure_date' => 'Date'], $rows);
    }

    public function medicalReports()
    {
        $rows = DB::table('medical_report as mr')
            ->join('patient as p', 'p.patient_id', '=', 'mr.patient_id')
            ->orderByDesc('mr.generated_at')
            ->selectRaw("mr.*, (p.first_name||' '||p.last_name) as patient_name")->limit(200)->get();

        return $this->table('Medical Reports',
            ['report_id' => 'ID', 'patient_name' => 'Patient', 'report_type' => 'Type', 'generated_at' => 'Generated'], $rows);
    }

    public function labReports()
    {
        $rows = DB::table('lab_report as lr')
            ->join('patient as p', 'p.patient_id', '=', 'lr.patient_id')
            ->orderByDesc('lr.generated_at')
            ->selectRaw("lr.*, (p.first_name||' '||p.last_name) as patient_name")->limit(200)->get();

        return $this->table('Lab Reports',
            ['lab_report_id' => 'ID', 'patient_name' => 'Patient', 'test_order_id' => 'Order', 'generated_at' => 'Generated'], $rows);
    }

    public function profile()
    {
        $profile = DB::table('users as u')
            ->join('staff as s', 's.staff_id', '=', 'u.staff_id')
            ->where('u.user_id', Auth::id())
            ->selectRaw('u.user_id, u.email, u.role, s.*')->first();

        return view('misc.profile', ['profile' => $profile]);
    }

    public function settings()
    {
        return view('misc.settings');
    }

    private function table(string $title, array $columns, $rows)
    {
        return view('misc.table', compact('title', 'columns', 'rows'));
    }
}
