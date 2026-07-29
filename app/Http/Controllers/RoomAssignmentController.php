<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Services\CentralServiceClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Bed-level view + patient room/bed assignment, nested under Room & Bed Management. */
class RoomAssignmentController extends Controller
{
    public function __construct(private AuditLogger $audit, private CentralServiceClient $centralService) {}

    public function beds(string $roomId)
    {
        $response = $this->centralService->getRoomBeds($roomId);
        abort_if($response->status() === 404, 404);
        $body = $response->throw()->json();

        $room = (object) $body['room'];
        $room->department = $room->department ? (object) $room->department : null;
        $beds = collect($body['beds'])->map(fn (array $b) => (object) $b);

        return view('admin.room-beds', compact('room', 'beds'));
    }

    public function storeBed(Request $request, string $roomId)
    {
        $data = $request->validate(['bed_number' => 'nullable|string|max:100']);

        $response = $this->centralService->createBed($roomId, $data);
        abort_if($response->status() === 404, 404);
        $response->throw();

        $this->audit->log('bed.create', 'bed', $roomId);

        return redirect('/rooms')->with('success', 'Bed added.')->with('reopen_room_beds', $roomId);
    }

    public function assignForm(Request $request, string $bedId)
    {
        $response = $this->centralService->getAssignBedData($bedId);
        abort_if($response->status() === 404, 404);
        abort_if($response->status() === 409, 409, $response->json('message'));
        $body = $response->throw()->json();

        $bed = (object) $body;
        $bed->room = (object) $body['room'];

        return view('admin.bed-assign-form', ['bed' => $bed, 'from' => $request->query('from')]);
    }

    public function assign(Request $request, string $bedId)
    {
        $data = $request->validate(['patient_id' => 'required|exists:patient,patient_id']);
        $data['assigned_by'] = Auth::user()->staff_id;

        $response = $this->centralService->assignBed($bedId, $data);
        if ($response->status() === 404) {
            abort(404);
        }
        if ($response->status() === 409) {
            return back()->with('error', $response->json('message'));
        }
        $response->throw();

        $result = $response->json();
        $this->audit->log('room_assignment.create', 'room_assignment', $result['assignment_id'], [
            'patient_id' => $data['patient_id'], 'room_id' => $result['room_id'], 'bed_id' => $result['bed_id'],
        ]);

        $redirect = back()->with('success', 'Patient assigned to bed ' . ($result['bed_number'] ?: $result['bed_id']) . '.');

        // Only the per-room Beds modal (Rooms tab) wants to reopen after this —
        // the Beds tab already shows the updated row inline, so popping that
        // modal open there would just be a jarring, out-of-context flicker.
        if ($request->input('from') !== 'beds_tab') {
            $redirect->with('reopen_room_beds', $result['room_id']);
        }

        return $redirect;
    }

    public function release(Request $request, string $id)
    {
        $response = $this->centralService->releaseRoomAssignment($id);
        abort_if($response->status() === 404, 404);
        if ($response->status() === 409) {
            return back()->with('error', $response->json('message'));
        }
        $response->throw();

        $result = $response->json();
        $this->audit->log('room_assignment.release', 'room_assignment', $id);

        $redirect = back()->with('success', 'Bed released.');

        // Released from the Rooms & Beds modal or the patient detail modal —
        // flash both reopen keys; only the page the user actually lands on
        // reads its own. Skipped entirely from the Room Assignments tab,
        // which already shows the updated row inline with no modal to reopen.
        if ($request->input('from') !== 'assignments_tab') {
            $redirect->with('reopen_room_beds', $result['room_id'])
                ->with('reopen_patient', $result['patient_id']);
        }

        return $redirect;
    }
}
