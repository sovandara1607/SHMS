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
use Illuminate\Support\Facades\Cache;
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
            'pharmacist'     => $this->pharmacistData($user),
            'receptionist'   => $this->receptionistData($user),
            'lab_technician' => $this->labTechnicianData($user),
            default          => [],
        };

        return view('dashboard.index', array_merge($data, ['role' => $role]));
    }

    private function adminData(): array
    {
        // The heaviest dashboard (cross-department aggregates + several
        // joined lists), so it's the one cached; AppointmentController and
        // LabController bust this key when appointments/lab results change.
        return Cache::remember('dashboard:summary', 60, function () {
            $today = now()->toDateString();

            $stats = [
                'patients'     => Patient::where('patient_status', '<>', 'discharged')->count(),
                'staff'        => DB::table('staff')->where('status', 'active')->count(),
                'appointments' => Appointment::where('appointment_date', $today)->count(),
                'rooms'        => Room::where('status', 'available')->count(),
                'lab_pending'  => LabTestOrder::where('status', 'pending')->count(),
                'revenue'      => (float) DB::table('payment')->whereMonth('payment_date', now()->month)->sum('amount_paid'),
            ];

            // KPI trend/ratio badges: a real day-over-day % where the seed
            // data actually spans distinct days (appointments), and a real
            // proportion-of-total badge everywhere else. The seed data for
            // patients/staff/payments was bulk-inserted within the same few
            // days, so any month-over-month comparison for those would just
            // reflect the seeding, not a real change — shown as a ratio
            // instead of a fabricated trend percentage.
            $totalStaff = DB::table('staff')->count();
            $totalRooms = DB::table('room')->count();
            $totalLabOrders = LabTestOrder::count();
            $apptYesterday = Appointment::where('appointment_date', now()->subDay()->toDateString())->count();

            $kpiBadges = [
                'patients'     => ['type' => 'none'],
                'staff'        => ['type' => 'ratio', 'text' => "{$stats['staff']} of {$totalStaff} active"],
                'appointments' => $apptYesterday > 0
                    ? ['type' => 'trend', 'direction' => $stats['appointments'] >= $apptYesterday ? 'up' : 'down', 'text' => sprintf('%+.1f%% vs yesterday', ($stats['appointments'] - $apptYesterday) / $apptYesterday * 100)]
                    : ['type' => 'none'],
                'rooms'        => ['type' => 'ratio', 'text' => "{$stats['rooms']} of {$totalRooms} total"],
                'lab_pending'  => ['type' => 'ratio', 'text' => $totalLabOrders > 0 ? round($stats['lab_pending'] / $totalLabOrders * 100) . '% of all orders' : 'n/a'],
                'revenue'      => ['type' => 'none'],
            ];

            // Monthly Appointments vs Lab Orders — last 9 months, both plain
            // counts on one shared axis (never dollars mixed with counts).
            $monthlyTrend = collect(range(8, 0))->map(function ($monthsAgo) {
                $date = now()->subMonthsNoOverflow($monthsAgo);
                return [
                    'label'        => $date->format('M'),
                    'appointments' => DB::table('appointment')->whereYear('appointment_date', $date->year)->whereMonth('appointment_date', $date->month)->count(),
                    'lab_orders'   => DB::table('lab_test_order')->whereYear('order_date', $date->year)->whereMonth('order_date', $date->month)->count(),
                ];
            })->all();

            // Lab Test Breakdown donut — top 3 test types by volume + Others.
            $labTop = DB::table('lab_test_order')->select('test_name', DB::raw('count(*) as c'))
                ->groupBy('test_name')->orderByDesc('c')->limit(3)->get();
            $labDonutColors = ['#2a78d6', '#1baf7a', '#eda100', '#e34948'];
            $labBreakdown = $labTop->values()->map(fn ($row, $i) => ['label' => $row->test_name, 'value' => (int) $row->c, 'color' => $labDonutColors[$i]])->all();
            $labBreakdown[] = ['label' => 'Others', 'value' => max(0, $totalLabOrders - $labTop->sum('c')), 'color' => $labDonutColors[3]];

            // Next Appointment — the next scheduled appointment from now on,
            // with the patient's real profile fields (no height/weight on
            // this schema, so blood type + status fill those slots instead).
            $nowTime = now()->format('H:i:s');
            $nextAppointment = DB::table('appointment as a')
                ->join('patient as p', 'p.patient_id', '=', 'a.patient_id')
                ->where('a.status', 'scheduled')
                ->where(function ($q) use ($today, $nowTime) {
                    $q->where('a.appointment_date', '>', $today)
                        ->orWhere(fn ($q2) => $q2->where('a.appointment_date', $today)->where('a.appointment_time', '>=', $nowTime));
                })
                ->orderBy('a.appointment_date')->orderBy('a.appointment_time')
                ->selectRaw("p.patient_id, (p.first_name||' '||p.last_name) as patient_name, p.gender, p.date_of_birth, p.blood_type, p.patient_status, a.reason, a.appointment_date, a.appointment_time")
                ->first();
            $nextAppointment = $nextAppointment ? (array) $nextAppointment : null;

            if ($nextAppointment) {
                $nextAppointment['age'] = \Carbon\Carbon::parse($nextAppointment['date_of_birth'])->age;
                $nextAppointment['last_visit'] = DB::table('appointment')
                    ->where('patient_id', $nextAppointment['patient_id'])->where('appointment_date', '<', $today)
                    ->orderByDesc('appointment_date')->value('appointment_date');
            }

            // Pending Lab Results needing review — oldest first.
            $pendingLabResults = DB::table('lab_test_order as o')
                ->join('patient as p', 'p.patient_id', '=', 'o.patient_id')
                ->whereIn('o.status', ['pending', 'in_progress'])
                ->orderBy('o.order_date')
                ->selectRaw("o.test_order_id, o.test_name, (p.first_name||' '||p.last_name) as patient_name, o.order_date, o.status")
                ->limit(4)->get()
                ->map(fn ($row) => (array) $row)->all();

            // Completion Rate — today's rate + a real 7-day sparkline.
            $todayCompleted = Appointment::where('appointment_date', $today)->where('status', 'completed')->count();
            $completionRate = $stats['appointments'] > 0 ? (int) round($todayCompleted / $stats['appointments'] * 100) : 0;
            $completionSparkline = collect(range(6, 0))->map(function ($daysAgo) {
                $date = now()->subDays($daysAgo)->toDateString();
                $total = DB::table('appointment')->where('appointment_date', $date)->count();
                $completed = DB::table('appointment')->where('appointment_date', $date)->where('status', 'completed')->count();
                return $total > 0 ? (int) round($completed / $total * 100) : 0;
            })->all();

            // Distinct patients actually seen this month (via appointments) —
            // a genuinely different, meaningful number from "Total Patients"
            // (which is a lifetime headcount, not a monthly figure).
            $patientsThisMonth = DB::table('appointment')
                ->whereMonth('appointment_date', now()->month)->whereYear('appointment_date', now()->year)
                ->distinct('patient_id')->count('patient_id');

            $departments = DB::table('department as d')
                ->leftJoin('room as r', 'r.department_id', '=', 'd.department_id')
                ->selectRaw("d.department_name,
                    (select count(*) from doctor where department_id = d.department_id) +
                    (select count(*) from nurse where department_id = d.department_id) as staff_count,
                    count(distinct r.room_id) filter (where r.status = 'available') as available_rooms")
                ->groupBy('d.department_id', 'd.department_name')
                ->orderBy('d.department_name')->limit(6)->get()
                ->map(fn ($row) => (array) $row)->all();

            $todaySchedule = DB::table('appointment as a')
                ->join('doctor as d', 'd.doctor_id', '=', 'a.doctor_id')
                ->join('staff as s', 's.staff_id', '=', 'd.staff_id')
                ->where('a.appointment_date', $today)
                ->orderBy('a.appointment_time')
                ->selectRaw("a.appointment_time, (s.first_name||' '||s.last_name) as doctor_name, a.reason, a.status")
                ->limit(6)->get()
                ->map(fn ($row) => (array) $row)->all();

            $operations = [
                'active_doctors'   => DB::table('doctor as d')->join('staff as s', 's.staff_id', '=', 'd.staff_id')->where('s.status', 'active')->count(),
                'occupied_beds'    => DB::table('bed')->where('status', 'occupied')->count(),
                'pending_labs'     => LabTestOrder::whereIn('status', ['pending', 'in_progress'])->count(),
                'unpaid_bills'     => Bill::where('status', '<>', 'paid')->count(),
            ];

            return compact(
                'stats', 'kpiBadges', 'monthlyTrend', 'labBreakdown', 'nextAppointment',
                'pendingLabResults', 'completionRate', 'completionSparkline', 'patientsThisMonth',
                'departments', 'todaySchedule', 'operations'
            );
        });
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

        // Real trend/ratio badges only where the underlying data actually
        // supports a genuine comparison — see adminData() for the same rule.
        $consultsYesterday = Appointment::where('doctor_id', $doctorId)->where('appointment_date', now()->subDay()->toDateString())->count();
        $activePatientTotal = Patient::whereIn('patient_status', ['active', 'admitted', 'icu'])->count();

        $kpiBadges = [
            'my_patients'     => ['type' => 'none'],
            'today_consults'  => $consultsYesterday > 0
                ? ['type' => 'trend', 'direction' => $stats['today_consults'] >= $consultsYesterday ? 'up' : 'down', 'text' => sprintf('%+.1f%% vs yesterday', ($stats['today_consults'] - $consultsYesterday) / $consultsYesterday * 100)]
                : ['type' => 'none'],
            'pending_reports' => ['type' => 'none'],
            'critical_cases'  => $activePatientTotal > 0 ? ['type' => 'ratio', 'text' => round($stats['critical_cases'] / $activePatientTotal * 100) . '% of active patients'] : ['type' => 'none'],
        ];

        $reportUrls = [
            'my_patients'     => $user->hasPermission('patient.view') ? '/patients' : null,
            'today_consults'  => $user->hasPermission('appointment.view') ? '/appointments' : null,
            'pending_reports' => $user->hasPermission('medical_report.view') ? '/medical-reports' : null,
            'critical_cases'  => $user->hasPermission('patient.view') ? '/patients' : null,
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

        return compact('stats', 'kpiBadges', 'reportUrls', 'todayPatients', 'pendingLabResults');
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

        $activePatientTotal = Patient::whereIn('patient_status', ['active', 'admitted', 'icu'])->count();

        $kpiBadges = [
            'assigned_patients' => ['type' => 'none'],
            'vitals_due'        => ['type' => 'none'],
            'medications_due'   => ['type' => 'none'],
            'icu_watch'         => $activePatientTotal > 0 ? ['type' => 'ratio', 'text' => round($stats['icu_watch'] / $activePatientTotal * 100) . '% of active patients'] : ['type' => 'none'],
        ];

        $reportUrls = [
            'assigned_patients' => $user->hasPermission('patient.view') ? '/patients' : null,
            'vitals_due'        => $user->hasPermission('vital_signs.view') ? '/vital-signs' : null,
            'medications_due'   => $user->hasPermission('prescription.view') ? '/prescriptions' : null,
            'icu_watch'         => $user->hasPermission('patient.view') ? '/patients' : null,
        ];

        $vitalsRound = PatientNurseAssignment::where('nurse_id', $nurseId)->where('status', 'active')
            ->with('patient')->limit(5)->get();

        $medicationSchedule = DB::table('prescription_item as pi')
            ->join('prescription as pr', 'pr.prescription_id', '=', 'pi.prescription_id')
            ->join('patient as p', 'p.patient_id', '=', 'pr.patient_id')
            ->join('medicine as m', 'm.medicine_id', '=', 'pi.medicine_id')
            ->selectRaw("m.medicine_name, pi.dosage, pi.frequency, (p.first_name||' '||p.last_name) as patient_name")
            ->limit(5)->get();

        return compact('stats', 'kpiBadges', 'reportUrls', 'vitalsRound', 'medicationSchedule');
    }

    private function pharmacistData($user): array
    {
        $stats = [
            'total_medicines'  => Medicine::count(),
            'low_stock'        => Medicine::where('stock_quantity', '<=', 20)->count(),
            'expired'          => MedicineBatch::where('status', 'expired')->count(),
            'dispensed_today'  => DB::table('dispensing_record')->where('dispensing_date', now()->toDateString())->count(),
        ];

        $totalBatches = MedicineBatch::count();
        $dispensedYesterday = DB::table('dispensing_record')->where('dispensing_date', now()->subDay()->toDateString())->count();

        $kpiBadges = [
            'total_medicines' => ['type' => 'none'],
            'low_stock'       => $stats['total_medicines'] > 0 ? ['type' => 'ratio', 'text' => round($stats['low_stock'] / $stats['total_medicines'] * 100) . '% of medicines'] : ['type' => 'none'],
            'expired'         => $totalBatches > 0 ? ['type' => 'ratio', 'text' => round($stats['expired'] / $totalBatches * 100) . '% of all batches'] : ['type' => 'none'],
            'dispensed_today' => $dispensedYesterday > 0
                ? ['type' => 'trend', 'direction' => $stats['dispensed_today'] >= $dispensedYesterday ? 'up' : 'down', 'text' => sprintf('%+.1f%% vs yesterday', ($stats['dispensed_today'] - $dispensedYesterday) / $dispensedYesterday * 100)]
                : ['type' => 'none'],
        ];

        $reportUrls = [
            'total_medicines' => $user->hasPermission('medicine.view') ? '/medicines' : null,
            'low_stock'       => $user->hasPermission('medicine.view') ? '/medicines' : null,
            'expired'         => $user->hasPermission('medicine_batch.view') ? '/medicine-batches' : null,
            'dispensed_today' => $user->hasPermission('dispensing.view') ? '/dispensing' : null,
        ];

        $stockAlerts = Medicine::where('stock_quantity', '<=', 20)->orWhere('status', 'unavailable')->limit(5)->get();

        $recentDispensing = DB::table('dispensing_record as dr')
            ->join('patient as p', 'p.patient_id', '=', 'dr.patient_id')
            ->orderByDesc('dr.dispensing_date')
            ->selectRaw("(p.first_name||' '||p.last_name) as patient_name, dr.dispensing_date, dr.status")
            ->limit(5)->get();

        return compact('stats', 'kpiBadges', 'reportUrls', 'stockAlerts', 'recentDispensing');
    }

    private function receptionistData($user): array
    {
        $today = now()->toDateString();

        $stats = [
            'checkins_today'      => Appointment::where('appointment_date', $today)->count(),
            'pending_appointments' => Appointment::where('status', 'scheduled')->count(),
            'available_rooms'     => Room::where('status', 'available')->count(),
            'unpaid_bills'        => Bill::where('status', '<>', 'paid')->count(),
        ];

        $checkinsYesterday = Appointment::where('appointment_date', now()->subDay()->toDateString())->count();
        $totalRooms = Room::count();
        $totalAppointments = Appointment::count();
        $totalBills = Bill::count();

        $kpiBadges = [
            'checkins_today'      => $checkinsYesterday > 0
                ? ['type' => 'trend', 'direction' => $stats['checkins_today'] >= $checkinsYesterday ? 'up' : 'down', 'text' => sprintf('%+.1f%% vs yesterday', ($stats['checkins_today'] - $checkinsYesterday) / $checkinsYesterday * 100)]
                : ['type' => 'none'],
            'pending_appointments' => $totalAppointments > 0 ? ['type' => 'ratio', 'text' => round($stats['pending_appointments'] / $totalAppointments * 100) . '% of all appointments'] : ['type' => 'none'],
            'available_rooms'     => ['type' => 'ratio', 'text' => "{$stats['available_rooms']} of {$totalRooms} total"],
            'unpaid_bills'        => $totalBills > 0 ? ['type' => 'ratio', 'text' => round($stats['unpaid_bills'] / $totalBills * 100) . '% of all bills'] : ['type' => 'none'],
        ];

        $reportUrls = [
            'checkins_today'      => $user->hasPermission('appointment.view') ? '/appointments' : null,
            'pending_appointments' => $user->hasPermission('appointment.view') ? '/appointments' : null,
            'available_rooms'     => $user->hasPermission('room.view') ? '/rooms' : null,
            'unpaid_bills'        => $user->hasPermission('bill.view') ? '/bills' : null,
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

        return compact('stats', 'kpiBadges', 'reportUrls', 'upcomingAppointments', 'outstandingBills');
    }

    private function labTechnicianData($user): array
    {
        $stats = [
            'pending'     => LabTestOrder::where('status', 'pending')->count(),
            'in_progress' => LabTestOrder::where('status', 'in_progress')->count(),
            'completed_today' => LabTestOrder::where('status', 'completed')->whereDate('order_date', now()->toDateString())->count(),
            'equipment_issues' => LaboratoryEquipment::where('availability_status', 'maintenance')->count(),
        ];

        $totalLabOrders = LabTestOrder::count();
        $totalEquipment = LaboratoryEquipment::count();
        $completedYesterday = LabTestOrder::where('status', 'completed')->whereDate('order_date', now()->subDay()->toDateString())->count();

        $kpiBadges = [
            'pending'          => $totalLabOrders > 0 ? ['type' => 'ratio', 'text' => round($stats['pending'] / $totalLabOrders * 100) . '% of all orders'] : ['type' => 'none'],
            'in_progress'      => $totalLabOrders > 0 ? ['type' => 'ratio', 'text' => round($stats['in_progress'] / $totalLabOrders * 100) . '% of all orders'] : ['type' => 'none'],
            'completed_today'  => $completedYesterday > 0
                ? ['type' => 'trend', 'direction' => $stats['completed_today'] >= $completedYesterday ? 'up' : 'down', 'text' => sprintf('%+.1f%% vs yesterday', ($stats['completed_today'] - $completedYesterday) / $completedYesterday * 100)]
                : ['type' => 'none'],
            'equipment_issues' => $totalEquipment > 0 ? ['type' => 'ratio', 'text' => "{$stats['equipment_issues']} of {$totalEquipment} total"] : ['type' => 'none'],
        ];

        $reportUrls = [
            'pending'          => $user->hasPermission('lab_order.view') ? '/lab-orders' : null,
            'in_progress'      => $user->hasPermission('lab_order.view') ? '/lab-orders' : null,
            'completed_today'  => $user->hasPermission('lab_order.view') ? '/lab-orders' : null,
            'equipment_issues' => $user->hasPermission('lab_equipment.view') ? '/lab-equipment' : null,
        ];

        $activeQueue = DB::table('lab_test_order as o')
            ->join('patient as p', 'p.patient_id', '=', 'o.patient_id')
            ->whereIn('o.status', ['pending', 'in_progress'])
            ->orderBy('o.order_date')
            ->selectRaw("o.test_order_id, o.test_name, (p.first_name||' '||p.last_name) as patient_name, o.status")
            ->limit(5)->get();

        $equipmentStatus = LaboratoryEquipment::orderBy('equipment_name')->limit(5)->get();

        return compact('stats', 'kpiBadges', 'reportUrls', 'activeQueue', 'equipmentStatus');
    }
}
