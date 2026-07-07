<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Bill;
use App\Models\Department;
use App\Models\LabTestOrder;
use App\Models\LaboratoryEquipment;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\Nurse;
use App\Models\Patient;
use App\Models\PatientNurseAssignment;
use App\Models\Room;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $role = in_array($user->role, ['super_admin', 'admin'], true) ? 'admin' : $user->role;

        $data = match ($role) {
            'admin'          => $this->adminData(),
            'doctor'         => $this->doctorData($user),
            'nurse'          => $this->nurseData($user),
            'pharmacist'     => $this->pharmacistData(),
            'receptionist'   => $this->receptionistData(),
            'lab_technician' => $this->labTechnicianData(),
            default          => [],
        };

        return view('dashboard.index', array_merge($data, ['role' => $role]));
    }

    private function adminData(): array
    {
        $today = now()->toDateString();

        $stats = [
            'patients'     => Patient::where('patient_status', '<>', 'discharged')->count(),
            'staff'        => DB::table('staff')->where('status', 'active')->count(),
            'appointments' => Appointment::where('appointment_date', $today)->count(),
            'rooms'        => Room::where('status', 'available')->count(),
            'lab_pending'  => LabTestOrder::where('status', 'pending')->count(),
            'revenue'      => (float) DB::table('payment')->whereMonth('payment_date', now()->month)->sum('amount_paid'),
        ];

        $departments = DB::table('department as d')
            ->leftJoin('room as r', 'r.department_id', '=', 'd.department_id')
            ->selectRaw("d.department_name,
                (select count(*) from doctor where department_id = d.department_id) +
                (select count(*) from nurse where department_id = d.department_id) as staff_count,
                count(distinct r.room_id) filter (where r.status = 'available') as available_rooms")
            ->groupBy('d.department_id', 'd.department_name')
            ->orderBy('d.department_name')->limit(6)->get();

        $todaySchedule = DB::table('appointment as a')
            ->join('doctor as d', 'd.doctor_id', '=', 'a.doctor_id')
            ->join('staff as s', 's.staff_id', '=', 'd.staff_id')
            ->where('a.appointment_date', $today)
            ->orderBy('a.appointment_time')
            ->selectRaw("a.appointment_time, (s.first_name||' '||s.last_name) as doctor_name, a.reason, a.status")
            ->limit(6)->get();

        $operations = [
            'active_doctors'   => DB::table('doctor as d')->join('staff as s', 's.staff_id', '=', 'd.staff_id')->where('s.status', 'active')->count(),
            'occupied_beds'    => DB::table('bed')->where('status', 'occupied')->count(),
            'pending_labs'     => LabTestOrder::whereIn('status', ['pending', 'in_progress'])->count(),
            'unpaid_bills'     => Bill::where('status', '<>', 'paid')->count(),
        ];

        return compact('stats', 'departments', 'todaySchedule', 'operations');
    }

    private function doctorData($user): array
    {
        $doctorId = DB::table('doctor')->where('staff_id', $user->staff_id)->value('doctor_id');
        $today = now()->toDateString();

        $stats = [
            'my_patients'   => Appointment::where('doctor_id', $doctorId)->distinct()->count('patient_id'),
            'today_consults' => Appointment::where('doctor_id', $doctorId)->where('appointment_date', $today)->count(),
            'pending_reports' => DB::table('medical_report')->where('generated_by', $user->staff_id)->count(),
            'critical_cases' => Patient::where('patient_status', 'icu')->count(),
        ];

        $todayPatients = DB::table('appointment as a')
            ->join('patient as p', 'p.patient_id', '=', 'a.patient_id')
            ->where('a.doctor_id', $doctorId)->where('a.appointment_date', $today)
            ->orderBy('a.appointment_time')
            ->selectRaw("(p.first_name||' '||p.last_name) as patient_name, a.appointment_time, a.reason, p.patient_status")
            ->limit(6)->get();

        $pendingLabResults = DB::table('lab_test_order as o')
            ->join('patient as p', 'p.patient_id', '=', 'o.patient_id')
            ->where('o.doctor_id', $doctorId)->whereIn('o.status', ['pending', 'in_progress'])
            ->orderBy('o.order_date')
            ->selectRaw("o.test_name, (p.first_name||' '||p.last_name) as patient_name, o.status")
            ->limit(6)->get();

        return compact('stats', 'todayPatients', 'pendingLabResults');
    }

    private function nurseData($user): array
    {
        $nurseId = Nurse::where('staff_id', $user->staff_id)->value('nurse_id');

        $stats = [
            'assigned_patients' => PatientNurseAssignment::where('nurse_id', $nurseId)->where('status', 'active')->distinct()->count('patient_id'),
            'vitals_due'        => DB::table('patient_nurse_assignment')->where('nurse_id', $nurseId)->where('status', 'active')->count(),
            'medications_due'   => DB::table('prescription_item')->count(),
            'icu_watch'         => Patient::where('patient_status', 'icu')->count(),
        ];

        $vitalsRound = PatientNurseAssignment::where('nurse_id', $nurseId)->where('status', 'active')
            ->with('patient')->limit(5)->get();

        $medicationSchedule = DB::table('prescription_item as pi')
            ->join('prescription as pr', 'pr.prescription_id', '=', 'pi.prescription_id')
            ->join('patient as p', 'p.patient_id', '=', 'pr.patient_id')
            ->join('medicine as m', 'm.medicine_id', '=', 'pi.medicine_id')
            ->selectRaw("m.medicine_name, pi.dosage, pi.frequency, (p.first_name||' '||p.last_name) as patient_name")
            ->limit(5)->get();

        return compact('stats', 'vitalsRound', 'medicationSchedule');
    }

    private function pharmacistData(): array
    {
        $stats = [
            'total_medicines'  => Medicine::count(),
            'low_stock'        => Medicine::where('stock_quantity', '<=', 20)->count(),
            'expired'          => MedicineBatch::where('status', 'expired')->count(),
            'dispensed_today'  => DB::table('dispensing_record')->where('dispensing_date', now()->toDateString())->count(),
        ];

        $stockAlerts = Medicine::where('stock_quantity', '<=', 20)->orWhere('status', 'unavailable')->limit(5)->get();

        $recentDispensing = DB::table('dispensing_record as dr')
            ->join('patient as p', 'p.patient_id', '=', 'dr.patient_id')
            ->orderByDesc('dr.dispensing_date')
            ->selectRaw("(p.first_name||' '||p.last_name) as patient_name, dr.dispensing_date, dr.status")
            ->limit(5)->get();

        return compact('stats', 'stockAlerts', 'recentDispensing');
    }

    private function receptionistData(): array
    {
        $today = now()->toDateString();

        $stats = [
            'checkins_today'      => Appointment::where('appointment_date', $today)->count(),
            'pending_appointments' => Appointment::where('status', 'scheduled')->count(),
            'available_rooms'     => Room::where('status', 'available')->count(),
            'unpaid_bills'        => Bill::where('status', '<>', 'paid')->count(),
        ];

        $upcomingAppointments = DB::table('appointment as a')
            ->join('patient as p', 'p.patient_id', '=', 'a.patient_id')
            ->join('doctor as d', 'd.doctor_id', '=', 'a.doctor_id')
            ->join('staff as s', 's.staff_id', '=', 'd.staff_id')
            ->where('a.status', 'scheduled')->where('a.appointment_date', '>=', $today)
            ->orderBy('a.appointment_date')->orderBy('a.appointment_time')
            ->selectRaw("(p.first_name||' '||p.last_name) as patient_name, (s.first_name||' '||s.last_name) as doctor_name, a.appointment_date, a.appointment_time, a.status")
            ->limit(5)->get();

        $outstandingBills = DB::table('bill as b')
            ->join('patient as p', 'p.patient_id', '=', 'b.patient_id')
            ->where('b.status', '<>', 'paid')
            ->orderByDesc('b.total_amount')
            ->selectRaw("(p.first_name||' '||p.last_name) as patient_name, b.total_amount, b.status")
            ->limit(5)->get();

        return compact('stats', 'upcomingAppointments', 'outstandingBills');
    }

    private function labTechnicianData(): array
    {
        $stats = [
            'pending'     => LabTestOrder::where('status', 'pending')->count(),
            'in_progress' => LabTestOrder::where('status', 'in_progress')->count(),
            'completed_today' => LabTestOrder::where('status', 'completed')->whereDate('order_date', now()->toDateString())->count(),
            'equipment_issues' => LaboratoryEquipment::where('availability_status', 'maintenance')->count(),
        ];

        $activeQueue = DB::table('lab_test_order as o')
            ->join('patient as p', 'p.patient_id', '=', 'o.patient_id')
            ->whereIn('o.status', ['pending', 'in_progress'])
            ->orderBy('o.order_date')
            ->selectRaw("o.test_order_id, o.test_name, (p.first_name||' '||p.last_name) as patient_name, o.status")
            ->limit(5)->get();

        $equipmentStatus = LaboratoryEquipment::orderBy('equipment_name')->limit(5)->get();

        return compact('stats', 'activeQueue', 'equipmentStatus');
    }
}
