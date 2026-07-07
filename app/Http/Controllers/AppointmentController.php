<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AppointmentController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $date = $request->query('date');
        $status = $request->query('status', 'all');

        $appointments = DB::table('appointment as a')
            ->join('patient as p', 'p.patient_id', '=', 'a.patient_id')
            ->join('doctor as d', 'd.doctor_id', '=', 'a.doctor_id')
            ->join('staff as s', 's.staff_id', '=', 'd.staff_id')
            ->when($q !== '', function ($query) use ($q) {
                $like = '%' . $q . '%';
                $query->where('a.appointment_id', 'ilike', $like)
                    ->orWhereRaw("(p.first_name || ' ' || p.last_name) ilike ?", [$like])
                    ->orWhereRaw("(s.first_name || ' ' || s.last_name) ilike ?", [$like]);
            })
            ->when($date, fn ($query) => $query->where('a.appointment_date', $date))
            ->when($status !== 'all', fn ($query) => $query->where('a.status', $status))
            ->orderByDesc('a.appointment_date')->orderByDesc('a.appointment_time')
            ->selectRaw("a.*, (p.first_name||' '||p.last_name) as patient_name, p.patient_id as patient_id, (s.first_name||' '||s.last_name) as doctor_name")
            ->limit(200)->get();

        $today = now()->toDateString();
        $stats = [
            'today'     => Appointment::where('appointment_date', $today)->count(),
            'this_week' => Appointment::whereBetween('appointment_date', [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()])->count(),
            'scheduled' => Appointment::where('status', 'scheduled')->count(),
            'cancelled' => Appointment::where('status', 'cancelled')->count(),
        ];

        return view('appointment.index', [
            'appointments' => $appointments,
            'q' => $q, 'date' => $date, 'status' => $status, 'stats' => $stats,
            'patients' => Patient::orderBy('last_name')->get(),
            'doctors'  => Doctor::with('staff')->get(),
        ]);
    }

    public function create()
    {
        return view('appointment.form', [
            'appointment' => new Appointment(),
            'mode' => 'create',
            'patients' => Patient::orderBy('last_name')->get(),
            'doctors' => Doctor::with('staff')->get(),
        ]);
    }

    public function show(string $id)
    {
        $appointment = Appointment::with(['patient', 'doctor.staff', 'bookedByStaff'])->findOrFail($id);

        return view('appointment.show', compact('appointment'));
    }

    public function edit(string $id)
    {
        return view('appointment.form', [
            'appointment' => Appointment::findOrFail($id),
            'mode' => 'edit',
            'patients' => Patient::orderBy('last_name')->get(),
            'doctors' => Doctor::with('staff')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        if ($this->slotTaken($data['doctor_id'], $data['appointment_date'], $data['appointment_time'])) {
            return back()->with('error', 'That doctor already has a scheduled appointment in this slot.');
        }

        $data['booked_by'] = Auth::user()->staff_id;
        $appt = Appointment::create($data);
        $this->audit->log('appointment.create', 'appointment', $appt->appointment_id);
        Cache::forget('dashboard:summary');

        return redirect('/appointments')->with('success', "Appointment {$appt->appointment_id} booked.");
    }

    public function update(Request $request, string $id)
    {
        $appointment = Appointment::findOrFail($id);
        $data = $this->validateData($request);

        if ($this->slotTaken($data['doctor_id'], $data['appointment_date'], $data['appointment_time'], $appointment->appointment_id)) {
            return back()->with('error', 'That doctor already has a scheduled appointment in this slot.');
        }

        $appointment->update($data);
        $this->audit->log('appointment.update', 'appointment', $appointment->appointment_id);
        Cache::forget('dashboard:summary');

        return redirect('/appointments')->with('success', 'Appointment updated.');
    }

    public function cancelForm(string $id)
    {
        $appointment = Appointment::with(['patient', 'doctor.staff'])->findOrFail($id);

        return view('appointment.cancel', compact('appointment'));
    }

    public function cancel(Request $request, string $id)
    {
        $appt = Appointment::findOrFail($id);
        $appt->update([
            'status' => 'cancelled',
            'cancellation_reason' => $request->input('cancellation_reason', 'Cancelled by staff'),
        ]);
        $this->audit->log('appointment.cancel', 'appointment', $id, ['reason' => $appt->cancellation_reason]);
        Cache::forget('dashboard:summary');

        return redirect('/appointments')->with('success', 'Appointment cancelled.');
    }

    private function slotTaken(string $doctorId, string $date, string $time, ?string $exceptId = null): bool
    {
        return Appointment::where('doctor_id', $doctorId)
            ->where('appointment_date', $date)
            ->where('appointment_time', $time)
            ->where('status', 'scheduled')
            ->when($exceptId, fn ($q) => $q->where('appointment_id', '!=', $exceptId))
            ->exists();
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'patient_id'       => 'required|exists:patient,patient_id',
            'doctor_id'        => 'required|exists:doctor,doctor_id',
            'appointment_date' => 'required|date',
            'appointment_time' => 'required',
            'reason'           => 'nullable|string',
        ]);
    }
}
