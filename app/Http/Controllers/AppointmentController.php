<?php

namespace App\Http\Controllers;

use App\DTOs\AppointmentDTO;
use App\Models\Doctor;
use App\Services\AuditLogger;
use App\Services\CentralServiceClient;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class AppointmentController extends Controller
{
    public function __construct(private AuditLogger $audit, private CentralServiceClient $centralService) {}

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $date = $request->query('date');
        $status = $request->query('status', 'all');
        $page = (int) $request->query('page', 1);

        $response = $this->centralService->listAppointments([
            'q' => $q, 'date' => $date, 'status' => $status, 'page' => $page,
        ])->throw();
        $body = $response->json();

        $appointments = new LengthAwarePaginator(
            collect($body['data'])->map(fn (array $a) => (object) $a),
            $body['meta']['total'],
            $body['meta']['per_page'],
            $body['meta']['current_page'],
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('appointment.index', [
            'appointments' => $appointments,
            'q' => $q, 'date' => $date, 'status' => $status, 'stats' => $body['stats'],
            'doctors'  => Doctor::with('staff')->get(),
        ]);
    }

    public function create()
    {
        return view('appointment.form', [
            'appointment' => new AppointmentDTO(),
            'mode' => 'create',
            'selectedPatient' => null,
            'doctors' => Doctor::with('staff')->get(),
        ]);
    }

    public function show(string $id)
    {
        $response = $this->centralService->getAppointment($id);
        abort_if($response->status() === 404, 404);
        $response->throw();

        $appointment = AppointmentDTO::fromArray($response->json());

        return view('appointment.show', compact('appointment'));
    }

    public function edit(string $id)
    {
        $response = $this->centralService->getAppointment($id);
        abort_if($response->status() === 404, 404);
        $response->throw();

        $appointment = AppointmentDTO::fromArray($response->json());

        return view('appointment.form', [
            'appointment' => $appointment,
            'mode' => 'edit',
            'selectedPatient' => $appointment->patient,
            'doctors' => Doctor::with('staff')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['booked_by'] = Auth::user()->staff_id;

        $response = $this->centralService->createAppointment($data);
        if ($response->status() === 409) {
            return back()->with('error', $response->json('message'));
        }
        if ($response->status() === 422) {
            return back()->withErrors($response->json('errors', []))->withInput();
        }
        $response->throw();

        $appointment = $response->json();
        $this->audit->log('appointment.create', 'appointment', $appointment['appointment_id']);
        Cache::forget('dashboard:summary');

        return redirect('/appointments')->with('success', "Appointment {$appointment['appointment_id']} booked.");
    }

    public function update(Request $request, string $id)
    {
        $data = $this->validateData($request);

        $response = $this->centralService->updateAppointment($id, $data);
        if ($response->status() === 404) {
            abort(404);
        }
        if ($response->status() === 409) {
            return back()->with('error', $response->json('message'));
        }
        if ($response->status() === 422) {
            return back()->withErrors($response->json('errors', []))->withInput();
        }
        $response->throw();

        $this->audit->log('appointment.update', 'appointment', $id);
        Cache::forget('dashboard:summary');

        return redirect('/appointments')->with('success', 'Appointment updated.');
    }

    public function cancelForm(string $id)
    {
        $response = $this->centralService->getAppointment($id);
        abort_if($response->status() === 404, 404);
        $response->throw();

        $appointment = AppointmentDTO::fromArray($response->json());

        return view('appointment.cancel', compact('appointment'));
    }

    public function cancel(Request $request, string $id)
    {
        $response = $this->centralService->cancelAppointment($id, [
            'cancellation_reason' => $request->input('cancellation_reason', 'Cancelled by staff'),
        ]);
        abort_if($response->status() === 404, 404);
        $response->throw();

        $appointment = $response->json();
        $this->audit->log('appointment.cancel', 'appointment', $id, ['reason' => $appointment['cancellation_reason']]);
        Cache::forget('dashboard:summary');

        return redirect('/appointments')->with('success', 'Appointment cancelled.');
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
