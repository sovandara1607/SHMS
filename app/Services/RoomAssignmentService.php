<?php

namespace App\Services;

use App\Models\Bed;
use App\Models\Room;
use App\Models\RoomAssignment;
use Illuminate\Support\Facades\DB;

/** Shared by RoomAssignmentController (manual release) and PatientController::discharge() (auto-release). */
class RoomAssignmentService
{
    public function __construct(private AuditLogger $audit) {}

    public function release(RoomAssignment $assignment): void
    {
        if ($assignment->status !== 'active') {
            return;
        }

        DB::transaction(function () use ($assignment) {
            $assignment->update(['status' => 'completed', 'released_at' => now()]);

            if ($assignment->bed_id) {
                Bed::where('bed_id', $assignment->bed_id)->update(['status' => 'available']);
            }

            $stillOccupied = RoomAssignment::where('room_id', $assignment->room_id)
                ->where('status', 'active')->exists();
            if (! $stillOccupied) {
                Room::where('room_id', $assignment->room_id)->update(['status' => 'available']);
            }

            $this->audit->log('room_assignment.release', 'room_assignment', $assignment->room_assignment_id);
        });
    }

    /** Release whichever bed a patient currently occupies, if any (called on discharge). */
    public function releaseForPatient(string $patientId): void
    {
        $assignment = RoomAssignment::where('patient_id', $patientId)->where('status', 'active')->first();
        if ($assignment) {
            $this->release($assignment);
        }
    }
}
