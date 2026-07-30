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
use Illuminate\Support\Facades\Log;
use Predis\PredisException;

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

    /**
     * Cache is a fast-path optimization, not a hard dependency — if the
     * cache Redis connection is unavailable, fall back to computing
     * uncached rather than 500ing the dashboard (see analysis.md Part 8).
     *
     * Cache::flexible() instead of Cache::remember(): remember() has a hard
     * cutoff — the instant a key expires, every concurrent request that
     * lands in that window independently recomputes the full query set
     * (a cache stampede), which spikes load at exactly the moment the
     * cache exists to prevent that. flexible([60, 120], ...) keeps the same
     * 60s "fresh" window as before, but for the next 60s after that
     * (60-120s old) it serves the still-reasonably-fresh stale value
     * immediately and defers exactly one background refresh (guarded by
     * flexible()'s own internal lock) — so N concurrent requests during
     * that window get 1 recompute, not N. Only a truly cold key (never
     * cached, or just busted via Cache::forget()) still computes
     * synchronously on the requesting thread, same as remember() always did.
     */
    private function cachedRoleData(string $key, callable $compute): array
    {
        try {
            return Cache::flexible($key, [60, 120], $compute);
        } catch (PredisException $e) {
            Log::warning('dashboard: cache unavailable, computing uncached', ['error' => $e->getMessage(), 'key' => $key]);

            return $compute();
        }
    }

    private function adminData(): array
    {
        // The heaviest dashboard (cross-department aggregates + several
        // joined lists); AppointmentController and LabController bust this
        // key when appointments/lab results change.
        return $this->cachedRoleData('dashboard:summary', fn () => $this->computeAdminSummary());
    }

    private function computeAdminSummary(): array
    {
        $today = now()->toDateString();

        // Each of these tables gets counted with several different filters
        // across this method (active vs total staff, available vs total
        // rooms, pending/in_progress/total lab orders, today/yesterday/
        // completed appointments) — one FILTER query per table instead of
        // a separate count() per filter (see analysis.md §4.8). This alone
        // is most of the ~54-query cost the admin dashboard used to have.
        $staffCounts = DB::table('staff')->selectRaw(
            "count(*) as total, count(*) filter (where status = 'active') as active"
        )->first();

        $roomCounts = DB::table('room')->selectRaw(
            "count(*) as total, count(*) filter (where status = 'available') as available"
        )->first();

        $labCounts = DB::table('lab_test_order')->selectRaw(
            "count(*) as total,
             count(*) filter (where status = 'pending') as pending,
             count(*) filter (where status in ('pending', 'in_progress')) as pending_or_in_progress"
        )->first();

        $apptCounts = DB::table('appointment')->selectRaw(
            'count(*) filter (where appointment_date = ?) as today,
             count(*) filter (where appointment_date = ?) as yesterday,
             count(*) filter (where appointment_date = ? and status = ?) as today_completed',
            [$today, now()->subDay()->toDateString(), $today, 'completed']
        )->first();

        $stats = [
            'patients'     => Patient::where('patient_status', '<>', 'discharged')->count(),
            'staff'        => (int) $staffCounts->active,
            'appointments' => (int) $apptCounts->today,
            'rooms'        => (int) $roomCounts->available,
            'lab_pending'  => (int) $labCounts->pending,
            'revenue'      => (float) DB::table('payment')->whereMonth('payment_date', now()->month)->sum('amount_paid'),
        ];

        // KPI trend/ratio badges: a real day-over-day % where the seed
        // data actually spans distinct days (appointments), and a real
        // proportion-of-total badge everywhere else. The seed data for
        // patients/staff/payments was bulk-inserted within the same few
        // days, so any month-over-month comparison for those would just
        // reflect the seeding, not a real change — shown as a ratio
        // instead of a fabricated trend percentage.
        $totalStaff = (int) $staffCounts->total;
        $totalRooms = (int) $roomCounts->total;
        $totalLabOrders = (int) $labCounts->total;
        $apptYesterday = (int) $apptCounts->yesterday;

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
        // One grouped query per table instead of 2 queries × 9 months —
        // this pair of loops used to be the single biggest chunk of the
        // dashboard's ~54 queries (see analysis.md §4.8).
        $trendStart = now()->subMonthsNoOverflow(8)->startOfMonth()->toDateString();
        $monthKeySql = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%%Y-%%m', %s)"
            : "to_char(%s, 'YYYY-MM')";

        $apptByMonth = DB::table('appointment')
            ->where('appointment_date', '>=', $trendStart)
            ->selectRaw(sprintf($monthKeySql, 'appointment_date') . ' as ym, count(*) as c')
            ->groupBy('ym')->pluck('c', 'ym');

        $labByMonth = DB::table('lab_test_order')
            ->where('order_date', '>=', $trendStart)
            ->selectRaw(sprintf($monthKeySql, 'order_date') . ' as ym, count(*) as c')
            ->groupBy('ym')->pluck('c', 'ym');

        $monthlyTrend = collect(range(8, 0))->map(function ($monthsAgo) use ($apptByMonth, $labByMonth) {
            $date = now()->subMonthsNoOverflow($monthsAgo);
            $ym = $date->format('Y-m');
            return [
                'label'        => $date->format('M'),
                'appointments' => (int) ($apptByMonth[$ym] ?? 0),
                'lab_orders'   => (int) ($labByMonth[$ym] ?? 0),
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

        // Completion Rate — today's rate (already fetched in $apptCounts
        // above) + a real 7-day sparkline, now one grouped query instead of
        // 2 queries × 7 days.
        $todayCompleted = (int) $apptCounts->today_completed;
        $completionRate = $stats['appointments'] > 0 ? (int) round($todayCompleted / $stats['appointments'] * 100) : 0;

        $sparklineStart = now()->subDays(6)->toDateString();
        $sparklineByDate = DB::table('appointment')
            ->where('appointment_date', '>=', $sparklineStart)
            ->selectRaw("appointment_date, count(*) as total, count(*) filter (where status = 'completed') as completed")
            ->groupBy('appointment_date')
            ->get()->keyBy(fn ($row) => (string) $row->appointment_date);

        $completionSparkline = collect(range(6, 0))->map(function ($daysAgo) use ($sparklineByDate) {
            $date = now()->subDays($daysAgo)->toDateString();
            $row = $sparklineByDate->get($date);
            $total = $row ? (int) $row->total : 0;
            $completed = $row ? (int) $row->completed : 0;
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
            'pending_labs'     => (int) $labCounts->pending_or_in_progress,
            'unpaid_bills'     => Bill::where('status', '<>', 'paid')->count(),
        ];

        return compact(
            'stats', 'kpiBadges', 'monthlyTrend', 'labBreakdown', 'nextAppointment',
            'pendingLabResults', 'completionRate', 'completionSparkline', 'patientsThisMonth',
            'departments', 'todaySchedule', 'operations'
        );
    }

    private function doctorData($user): array
    {
        return $this->cachedRoleData("dashboard:doctor:{$user->staff_id}", fn () => $this->computeDoctorData($user));
    }

    private function computeDoctorData($user): array
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

        // ->map(fn ($row) => (array) $row)->all() on every list below: this
        // whole method's return value goes through cachedRoleData()'s
        // Cache::flexible(), and config/cache.php's serializable_classes
        // => false makes unserialize() refuse to reconstruct ANY object on
        // a cache hit (not just Eloquent models — plain stdClass query rows
        // too), corrupting into __PHP_Incomplete_Class. Only plain arrays
        // survive that round trip — see DropdownCache.php's docblock and
        // computeAdminSummary() above, which already does this correctly.
        $todayPatients = DB::table('appointment as a')
            ->join('patient as p', 'p.patient_id', '=', 'a.patient_id')
            ->where('a.doctor_id', $doctorId)->where('a.appointment_date', $today)
            ->orderBy('a.appointment_time')
            ->selectRaw("(p.first_name||' '||p.last_name) as patient_name, a.appointment_time, a.reason, p.patient_status")
            ->limit(6)->get()
            ->map(fn ($row) => (array) $row)->all();

        $pendingLabResults = DB::table('lab_test_order as o')
            ->join('patient as p', 'p.patient_id', '=', 'o.patient_id')
            ->where('o.doctor_id', $doctorId)->whereIn('o.status', ['pending', 'in_progress'])
            ->orderBy('o.order_date')
            ->selectRaw("o.test_name, (p.first_name||' '||p.last_name) as patient_name, o.status")
            ->limit(6)->get()
            ->map(fn ($row) => (array) $row)->all();

        return compact('stats', 'kpiBadges', 'reportUrls', 'todayPatients', 'pendingLabResults');
    }

    private function nurseData($user): array
    {
        return $this->cachedRoleData("dashboard:nurse:{$user->staff_id}", fn () => $this->computeNurseData($user));
    }

    private function computeNurseData($user): array
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

        // Resolved to a flat array (patient_name pre-computed from the
        // relation) rather than caching live Eloquent models with a loaded
        // relation — see the note on $todayPatients in computeDoctorData().
        $vitalsRound = PatientNurseAssignment::where('nurse_id', $nurseId)->where('status', 'active')
            ->with('patient')->limit(5)->get()
            ->map(fn ($v) => ['status' => $v->status, 'patient_name' => $v->patient?->fullName() ?? '—'])->all();

        $medicationSchedule = DB::table('prescription_item as pi')
            ->join('prescription as pr', 'pr.prescription_id', '=', 'pi.prescription_id')
            ->join('patient as p', 'p.patient_id', '=', 'pr.patient_id')
            ->join('medicine as m', 'm.medicine_id', '=', 'pi.medicine_id')
            ->selectRaw("m.medicine_name, pi.dosage, pi.frequency, (p.first_name||' '||p.last_name) as patient_name")
            ->limit(5)->get()
            ->map(fn ($row) => (array) $row)->all();

        return compact('stats', 'kpiBadges', 'reportUrls', 'vitalsRound', 'medicationSchedule');
    }

    private function pharmacistData($user): array
    {
        return $this->cachedRoleData("dashboard:pharmacist:{$user->staff_id}", fn () => $this->computePharmacistData($user));
    }

    private function computePharmacistData($user): array
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

        // ->toArray(), not (array) cast: Medicine is an Eloquent model, and
        // a bare (array) cast on an object with protected properties
        // produces mangled/unusable keys — only safe on stdClass (the
        // DB::table()->get() rows elsewhere in this file).
        $stockAlerts = Medicine::where('stock_quantity', '<=', 20)->orWhere('status', 'unavailable')->limit(5)->get()
            ->map(fn ($row) => $row->toArray())->all();

        $recentDispensing = DB::table('dispensing_record as dr')
            ->join('patient as p', 'p.patient_id', '=', 'dr.patient_id')
            ->orderByDesc('dr.dispensing_date')
            ->selectRaw("(p.first_name||' '||p.last_name) as patient_name, dr.dispensing_date, dr.status")
            ->limit(5)->get()
            ->map(fn ($row) => (array) $row)->all();

        return compact('stats', 'kpiBadges', 'reportUrls', 'stockAlerts', 'recentDispensing');
    }

    private function receptionistData($user): array
    {
        return $this->cachedRoleData("dashboard:receptionist:{$user->staff_id}", fn () => $this->computeReceptionistData($user));
    }

    private function computeReceptionistData($user): array
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
            ->limit(5)->get()
            ->map(fn ($row) => (array) $row)->all();

        $outstandingBills = DB::table('bill as b')
            ->join('patient as p', 'p.patient_id', '=', 'b.patient_id')
            ->where('b.status', '<>', 'paid')
            ->orderByDesc('b.total_amount')
            ->selectRaw("(p.first_name||' '||p.last_name) as patient_name, b.total_amount, b.status")
            ->limit(5)->get()
            ->map(fn ($row) => (array) $row)->all();

        return compact('stats', 'kpiBadges', 'reportUrls', 'upcomingAppointments', 'outstandingBills');
    }

    private function labTechnicianData($user): array
    {
        return $this->cachedRoleData("dashboard:lab_technician:{$user->staff_id}", fn () => $this->computeLabTechnicianData($user));
    }

    private function computeLabTechnicianData($user): array
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
            ->limit(5)->get()
            ->map(fn ($row) => (array) $row)->all();

        // ->toArray(), not (array) cast — LaboratoryEquipment is an
        // Eloquent model (see the note on $stockAlerts above).
        $equipmentStatus = LaboratoryEquipment::orderBy('equipment_name')->limit(5)->get()
            ->map(fn ($row) => $row->toArray())->all();

        return compact('stats', 'kpiBadges', 'reportUrls', 'activeQueue', 'equipmentStatus');
    }
}
