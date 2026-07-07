<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Room;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Admin-only management & reporting list screens. */
class AdminController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function staff(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $rows = DB::table('staff as s')
            ->leftJoin('users as u', 'u.staff_id', '=', 's.staff_id')
            ->when($q !== '', function ($query) use ($q) {
                $like = '%' . $q . '%';
                $query->where('s.staff_id', 'ilike', $like)
                    ->orWhereRaw("(s.first_name||' '||s.last_name) ilike ?", [$like]);
            })
            ->orderByDesc('s.created_at')
            ->selectRaw("s.staff_id, (s.first_name||' '||s.last_name) as full_name, u.role, u.email, s.status")
            ->limit(200)->get();

        return view('misc.table', [
            'title' => 'Staff Management', 'search' => $q, 'searchAction' => '/staff',
            'columns' => ['staff_id' => 'ID', 'full_name' => 'Name', 'role' => 'Role', 'email' => 'Email', 'status' => 'Status'],
            'rows' => $rows,
        ]);
    }

    public function doctors(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $rows = DB::table('doctor as d')
            ->join('staff as s', 's.staff_id', '=', 'd.staff_id')
            ->leftJoin('department as dep', 'dep.department_id', '=', 'd.department_id')
            ->when($q !== '', function ($query) use ($q) {
                $like = '%' . $q . '%';
                $query->where('d.doctor_id', 'ilike', $like)
                    ->orWhereRaw("(s.first_name||' '||s.last_name) ilike ?", [$like])
                    ->orWhere('d.specialization', 'ilike', $like);
            })
            ->orderBy('s.last_name')
            ->selectRaw("d.doctor_id, (s.first_name||' '||s.last_name) as full_name, d.specialization, dep.department_name, d.license_number, s.status")
            ->limit(200)->get();

        return view('misc.table', [
            'title' => 'Doctor Management', 'search' => $q, 'searchAction' => '/doctors',
            'columns' => ['doctor_id' => 'ID', 'full_name' => 'Name', 'specialization' => 'Specialization',
                'department_name' => 'Department', 'license_number' => 'License', 'status' => 'Status'],
            'rows' => $rows,
        ]);
    }

    public function departments(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $departments = DB::table('department')
            ->when($q !== '', fn ($query) => $query->where('department_id', 'ilike', "%$q%")->orWhere('department_name', 'ilike', "%$q%"))
            ->orderBy('department_name')->get();

        return view('admin.departments', compact('departments', 'q'));
    }

    public function createDepartment()
    {
        return view('admin.department-form', ['department' => new Department(), 'mode' => 'create']);
    }

    public function storeDepartment(Request $request)
    {
        $data = $this->validateDepartment($request);
        $dept = Department::create($data);
        $this->audit->log('department.create', 'department', $dept->department_id);

        return redirect('/departments')->with('success', "Department {$dept->department_id} created.");
    }

    public function editDepartment(string $id)
    {
        return view('admin.department-form', ['department' => Department::findOrFail($id), 'mode' => 'edit']);
    }

    public function updateDepartment(Request $request, string $id)
    {
        $dept = Department::findOrFail($id);
        $dept->update($this->validateDepartment($request));
        $this->audit->log('department.update', 'department', $dept->department_id);

        return redirect('/departments')->with('success', 'Department updated.');
    }

    private function validateDepartment(Request $request): array
    {
        return $request->validate([
            'department_name' => 'required|string|max:100',
            'description'     => 'nullable|string',
            'capacity'        => 'nullable|integer|min:0',
            'status'          => 'nullable|in:active,inactive',
        ]);
    }

    public function rooms(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $rooms = DB::table('room as r')
            ->leftJoin('department as dep', 'dep.department_id', '=', 'r.department_id')
            ->leftJoin('bed as b', 'b.room_id', '=', 'r.room_id')
            ->when($q !== '', fn ($query) => $query->where('r.room_id', 'ilike', "%$q%")->orWhere('r.room_number', 'ilike', "%$q%"))
            ->groupBy('r.room_id', 'r.room_number', 'r.room_type', 'r.floor_number', 'dep.department_name', 'r.status')
            ->orderBy('r.floor_number')->orderBy('r.room_number')
            ->selectRaw("r.room_id, r.room_number, r.room_type, r.floor_number, dep.department_name,
                         count(b.bed_id) as bed_count, count(*) filter (where b.status='available') as beds_available, r.status")
            ->limit(200)->get();

        return view('admin.rooms', ['rooms' => $rooms, 'q' => $q, 'departments' => Department::orderBy('department_name')->get()]);
    }

    public function createRoom()
    {
        return view('admin.room-form', ['room' => new Room(), 'mode' => 'create', 'departments' => Department::orderBy('department_name')->get()]);
    }

    public function storeRoom(Request $request)
    {
        $data = $this->validateRoom($request);
        $room = Room::create($data);
        $this->audit->log('room.create', 'room', $room->room_id);

        return redirect('/rooms')->with('success', "Room {$room->room_id} added.");
    }

    public function editRoom(string $id)
    {
        return view('admin.room-form', ['room' => Room::findOrFail($id), 'mode' => 'edit', 'departments' => Department::orderBy('department_name')->get()]);
    }

    public function updateRoom(Request $request, string $id)
    {
        $room = Room::findOrFail($id);
        $room->update($this->validateRoom($request));
        $this->audit->log('room.update', 'room', $room->room_id);

        return redirect('/rooms')->with('success', 'Room updated.');
    }

    private function validateRoom(Request $request): array
    {
        return $request->validate([
            'department_id' => 'nullable|exists:department,department_id',
            'room_number'   => 'nullable|string|max:100',
            'room_type'     => 'nullable|in:general,private,icu,emergency',
            'floor_number'  => 'nullable|integer',
            'status'        => 'nullable|in:available,occupied,maintenance',
        ]);
    }

    public function reports()
    {
        $report = [
            'Patients (active)'    => DB::table('patient')->where('patient_status', '<>', 'discharged')->count(),
            'Appointments total'   => DB::table('appointment')->count(),
            'Medical records'      => DB::table('medical_record')->count(),
            'Prescriptions'        => DB::table('prescription')->count(),
            'Lab orders'           => DB::table('lab_test_order')->count(),
            'Revenue collected'    => DB::table('payment')->sum('amount_paid'),
            'Outstanding (unpaid)' => DB::table('bill')->where('status', '<>', 'paid')->sum('total_amount'),
        ];
        $rows = collect($report)->map(fn ($v, $k) => ['metric' => $k, 'value' => $v])->values();

        return view('misc.table', [
            'title' => 'Reports', 'intro' => 'Hospital-wide operational summary.',
            'columns' => ['metric' => 'Metric', 'value' => 'Value'], 'rows' => $rows,
        ]);
    }
}
