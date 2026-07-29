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
use Illuminate\Support\Facades\Validator;

class AppointmentController extends Controller
{
    public function __construct(private AuditLogger $audit, private CentralServiceClient $centralService) {}

    public function bookedSlots(Request $request)
    {
        $doctorId = $request->query('doctor_id');
        $date = $request->query('date');
        if (! $doctorId || ! $date) {
            return response()->json([]);
        }

        return response()->json($this->centralService->bookedSlots($doctorId, $date)->json());
    }

    public function search(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json([]);
        }

        return response()->json($this->centralService->searchAppointments($q)->json());
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $date = $request->query('date');
        $status = $request->query('status', 'all');
        $page = (int) $request->query('page', 1);
        $month = $request->query('month', now()->format('Y-m'));

        $response = $this->centralService->listAppointments([
            'q' => $q, 'date' => $date, 'status' => $status, 'page' => $page, 'month' => $month,
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
            'month' => $month,
            'calendarDays' => $this->buildCalendarGrid($month, $body['calendar'] ?? []),
        ]);
    }

    /**
     * @param array<string, int> $counts appointment_date => count for the month
     * @return array<int, array{date: ?string, day: ?int, count: int}> 6x7 grid, padded with null-date cells
     */
    private function buildCalendarGrid(string $month, array $counts): array
    {
        $first = \Carbon\Carbon::parse($month . '-01');
        $leadingBlanks = $first->dayOfWeek; // 0 (Sun) .. 6 (Sat)
        $daysInMonth = $first->daysInMonth;

        $cells = array_fill(0, $leadingBlanks, ['date' => null, 'day' => null, 'count' => 0]);
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = $first->copy()->day($day)->toDateString();
            $cells[] = ['date' => $date, 'day' => $day, 'count' => $counts[$date] ?? 0];
        }
        while (count($cells) % 7 !== 0) {
            $cells[] = ['date' => null, 'day' => null, 'count' => 0];
        }

        return $cells;
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

    public function complete(string $id)
    {
        $response = $this->centralService->completeAppointment($id);
        abort_if($response->status() === 404, 404);
        if ($response->status() === 409) {
            return back()->with('error', $response->json('message'));
        }
        $response->throw();

        $this->audit->log('appointment.complete', 'appointment', $id);
        Cache::forget('dashboard:summary');

        return redirect('/appointments')->with('success', 'Appointment marked as completed.');
    }

    private function validateData(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'patient_id'       => 'required|exists:patient,patient_id',
            'doctor_id'        => 'required|exists:doctor,doctor_id',
            'appointment_date' => 'required|date|after_or_equal:today',
            'appointment_time' => 'required|date_format:H:i',
            'reason'           => 'nullable|string',
        ]);

        $validator->after(function ($validator) use ($request) {
            $date = $request->input('appointment_date');
            $time = $request->input('appointment_time');
            if (! $date || ! $time) {
                return;
            }
            if ($date === now()->toDateString() && $time < now()->format('H:i')) {
                $validator->errors()->add('appointment_time', 'The appointment time cannot be in the past.');
            }
        });

        return $validator->validate();
    }
}
