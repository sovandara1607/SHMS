<?php

namespace App\Http\Controllers;

use App\Models\Bed;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Services\AuditLogger;
use App\Services\RoomAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Bed-level view + patient room/bed assignment, nested under Room & Bed Management. */
class RoomAssignmentController extends Controller
{
    public function __construct(private AuditLogger $audit, private RoomAssignmentService $roomAssignments) {}

    public function beds(string $roomId)
    {
        $room = Room::with('department')->findOrFail($roomId);

        $beds = DB::table('bed as b')
            ->leftJoin('room_assignment as ra', function ($join) {
                $join->on('ra.bed_id', '=', 'b.bed_id')->where('ra.status', 'active');
            })
            ->leftJoin('patient as p', 'p.patient_id', '=', 'ra.patient_id')
            ->where('b.room_id', $roomId)
            ->orderBy('b.bed_number')
            ->selectRaw("b.*, ra.room_assignment_id, ra.patient_id, ra.assigned_at,
                         (p.first_name||' '||p.last_name) as patient_name")
            ->get();

        return view('admin.room-beds', ['room' => $room, 'beds' => $beds]);
    }

    public function storeBed(Request $request, string $roomId)
    {
        $room = Room::findOrFail($roomId);
        $data = $request->validate(['bed_number' => 'nullable|string|max:100']);
        Bed::create(['room_id' => $room->room_id, 'bed_number' => $data['bed_number'] ?? null]);
        $this->audit->log('bed.create', 'bed', $room->room_id);

        return redirect('/rooms')->with('success', 'Bed added.')->with('reopen_room_beds', $room->room_id);
    }

    public function assignForm(string $bedId)
    {
        $bed = Bed::with('room')->findOrFail($bedId);
        abort_if($bed->status !== 'available', 409, 'This bed is not available.');

        return view('admin.bed-assign-form', ['bed' => $bed]);
    }

    public function assign(Request $request, string $bedId)
    {
        $bed = Bed::with('room')->lockForUpdate()->findOrFail($bedId);
        abort_if($bed->status !== 'available', 409, 'This bed is not available.');

        $data = $request->validate(['patient_id' => 'required|exists:patient,patient_id']);

        DB::transaction(function () use ($bed, $data) {
            $assignment = RoomAssignment::create([
                'patient_id' => $data['patient_id'],
                'room_id' => $bed->room_id,
                'bed_id' => $bed->bed_id,
                'assigned_by' => Auth::user()->staff_id,
                'status' => 'active',
            ]);
            $bed->update(['status' => 'occupied']);
            $bed->room->update(['status' => 'occupied']);
            $this->audit->log('room_assignment.create', 'room_assignment', $assignment->room_assignment_id, [
                'patient_id' => $data['patient_id'], 'room_id' => $bed->room_id, 'bed_id' => $bed->bed_id,
            ]);
        });

        return redirect('/rooms')->with('success', 'Patient assigned to bed ' . ($bed->bed_number ?: $bed->bed_id) . '.')
            ->with('reopen_room_beds', $bed->room_id);
    }

    public function release(string $id)
    {
        $assignment = RoomAssignment::findOrFail($id);
        abort_if($assignment->status !== 'active', 409, 'This assignment is already released.');

        $roomId = $assignment->room_id;
        $patientId = $assignment->patient_id;
        $this->roomAssignments->release($assignment);

        // Released from either the Rooms & Beds modal or the patient detail modal —
        // flash both reopen keys; only the page the user actually lands on reads its own.
        return back()->with('success', 'Bed released.')
            ->with('reopen_room_beds', $roomId)
            ->with('reopen_patient', $patientId);
    }
}
