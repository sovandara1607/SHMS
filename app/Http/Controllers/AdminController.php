<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\LabTechnician;
use App\Models\Nurse;
use App\Models\Pharmacist;
use App\Models\Receptionist;
use App\Models\Staff;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\CentralServiceClient;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/** Admin-only management & reporting list screens. */
class AdminController extends Controller
{
    private const SUBTYPE_ROLES = ['doctor', 'nurse', 'receptionist', 'pharmacist', 'lab_technician'];

    public function __construct(private AuditLogger $audit, private CentralServiceClient $centralService) {}

    public function staff(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $role = $request->query('role');
        $status = $request->query('status');

        $rows = $this->staffQuery($q, $role, $status)
            ->paginate(20)
            ->withQueryString();

        return view('admin.staff', ['rows' => $rows, 'q' => $q, 'role' => $role, 'status' => $status]);
    }

    public function exportStaff(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $role = $request->query('role');
        $status = $request->query('status');

        $rows = $this->staffQuery($q, $role, $status)->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Staff ID', 'Name', 'Title', 'Email', 'Role', 'Department', 'Specialization / Unit', 'Account Status', 'Employment Status']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->staff_id, $r->full_name, $r->title, $r->email,
                    $r->role ? ucwords(str_replace('_', ' ', $r->role)) : '',
                    $r->department_name, $r->specialization_unit, ucfirst($r->status),
                    $r->employment_type ? ucwords(str_replace('_', ' ', $r->employment_type)) : '',
                ]);
            }
            fclose($out);
        }, 'staff-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
    }

    public function showStaff(string $id)
    {
        $row = $this->staffQuery('', null, null)->where('s.staff_id', $id)->first();
        abort_if(! $row, 404);

        return view('admin.staff-show', ['staff' => $row]);
    }

    private function staffQuery(string $q, ?string $role, ?string $status)
    {
        return DB::table('staff as s')
            ->leftJoin('users as u', 'u.staff_id', '=', 's.staff_id')
            ->leftJoin('doctor as doc', 'doc.staff_id', '=', 's.staff_id')
            ->leftJoin('nurse as nur', 'nur.staff_id', '=', 's.staff_id')
            ->leftJoin('receptionist as rec', 'rec.staff_id', '=', 's.staff_id')
            ->leftJoin('pharmacist as phm', 'phm.staff_id', '=', 's.staff_id')
            ->leftJoin('lab_technician as lt', 'lt.staff_id', '=', 's.staff_id')
            ->leftJoin('department as dept', function ($join) {
                $join->on('dept.department_id', '=', DB::raw('COALESCE(doc.department_id, nur.department_id, s.department_id)'));
            })
            ->when($q !== '', function ($query) use ($q) {
                $like = '%' . $q . '%';
                $query->where(function ($sub) use ($like) {
                    $sub->where('s.staff_id', 'ilike', $like)
                        ->orWhereRaw("(s.first_name||' '||s.last_name) ilike ?", [$like])
                        ->orWhere('u.email', 'ilike', $like)
                        ->orWhere('u.role', 'ilike', $like)
                        ->orWhere('dept.department_name', 'ilike', $like);
                });
            })
            ->when($role, fn ($query) => $query->where('u.role', $role))
            ->when($status, fn ($query) => $query->where('s.status', $status))
            ->orderByDesc('s.created_at')
            ->selectRaw("s.staff_id, s.first_name, s.last_name, (s.first_name||' '||s.last_name) as full_name,
                         s.title, s.status, s.employment_type, s.gender, s.phone_number, s.hire_date, u.role, u.email, dept.department_name,
                         COALESCE(doc.specialization, nur.ward_name, phm.pharmacy_unit, lt.skill_area, rec.counter_number) as specialization_unit");
    }

    /** Backs the staff-picker used by the Assign Shift form. */
    public function staffSearch(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json([]);
        }

        $like = '%' . strtolower($q) . '%';
        $rows = DB::table('staff as s')
            ->join('users as u', 'u.staff_id', '=', 's.staff_id')
            ->leftJoin('doctor as d', 'd.staff_id', '=', 's.staff_id')
            ->leftJoin('nurse as n', 'n.staff_id', '=', 's.staff_id')
            ->leftJoin('department as dept_doc', 'dept_doc.department_id', '=', 'd.department_id')
            ->leftJoin('department as dept_nurse', 'dept_nurse.department_id', '=', 'n.department_id')
            ->where('s.status', 'active')
            ->where(function ($query) use ($like) {
                $query->whereRaw('LOWER(s.staff_id) LIKE ?', [$like])
                    ->orWhereRaw("LOWER(s.first_name||' '||s.last_name) LIKE ?", [$like]);
            })
            ->orderBy('s.last_name')
            ->limit(20)
            ->selectRaw("s.staff_id, (s.first_name||' '||s.last_name) as name, u.role, COALESCE(dept_doc.department_name, dept_nurse.department_name) as department_name")
            ->get();

        return response()->json($rows->map(fn ($r) => [
            'id' => $r->staff_id,
            'label' => $r->name . ' (' . $r->staff_id . ')',
            'name' => $r->name,
            'role' => $r->role,
            'department' => $r->department_name,
        ]));
    }

    public function createStaff(Request $request)
    {
        return view('admin.staff-form', [
            'mode' => 'create',
            'staff' => new Staff(),
            'lockedRole' => $request->query('role'),
            'redirectTo' => $request->query('role') === 'doctor' ? '/doctors' : '/staff',
            'departments' => $this->departmentsForDropdown(),
            'laboratories' => $this->laboratoriesForDropdown(),
        ]);
    }

    public function storeStaff(Request $request)
    {
        $data = $this->validateStaff($request, isCreate: true);

        DB::transaction(function () use ($data) {
            $staff = Staff::create([
                'first_name' => $data['first_name'], 'last_name' => $data['last_name'],
                'gender' => $data['gender'] ?? null, 'phone_number' => $data['phone_number'] ?? null,
                'address' => $data['address'] ?? null, 'hire_date' => $data['hire_date'] ?? null,
                'status' => $data['status'] ?? 'active',
                'title' => $data['title'] ?? null, 'employment_type' => $data['employment_type'] ?? null,
                'department_id' => $data['department_id'] ?? null,
            ]);
            User::create([
                'staff_id' => $staff->staff_id, 'email' => $data['email'],
                'password_hash' => Hash::make($data['password']), 'role' => $data['role'],
                'status' => $data['status'] ?? 'active',
            ]);
            $this->createSubtype($data['role'], $staff->staff_id, $data);
            $this->audit->log('staff.create', 'staff', $staff->staff_id, ['role' => $data['role']]);
        });

        return redirect($data['redirect_to'])->with('success', 'Staff member added.');
    }

    public function editStaff(string $id)
    {
        $staff = Staff::with('user')->findOrFail($id);
        $role = $staff->user?->role;
        $subtype = match ($role) {
            'doctor' => Doctor::where('staff_id', $id)->first(),
            'nurse' => Nurse::where('staff_id', $id)->first(),
            'receptionist' => Receptionist::where('staff_id', $id)->first(),
            'pharmacist' => Pharmacist::where('staff_id', $id)->first(),
            'lab_technician' => LabTechnician::where('staff_id', $id)->first(),
            default => null,
        };

        return view('admin.staff-form', [
            'mode' => 'edit', 'staff' => $staff, 'subtype' => $subtype, 'role' => $role,
            'lockedRole' => null, 'redirectTo' => $role === 'doctor' ? '/doctors' : '/staff',
            'departments' => $this->departmentsForDropdown(),
            'laboratories' => $this->laboratoriesForDropdown(),
        ]);
    }

    public function updateStaff(Request $request, string $id)
    {
        $staff = Staff::with('user')->findOrFail($id);
        $role = $staff->user?->role;
        $data = $this->validateStaff($request, isCreate: false, currentUserId: $staff->user?->user_id);

        DB::transaction(function () use ($staff, $role, $data) {
            $staff->update([
                'first_name' => $data['first_name'], 'last_name' => $data['last_name'],
                'gender' => $data['gender'] ?? null, 'phone_number' => $data['phone_number'] ?? null,
                'address' => $data['address'] ?? null, 'hire_date' => $data['hire_date'] ?? null,
                'title' => $data['title'] ?? null, 'employment_type' => $data['employment_type'] ?? null,
                'department_id' => $data['department_id'] ?? null,
            ]);

            $user = $staff->user ?? new User(['staff_id' => $staff->staff_id, 'role' => $role ?? 'receptionist']);
            $user->email = $data['email'];
            if (! empty($data['password'])) {
                $user->password_hash = Hash::make($data['password']);
            }
            $user->save();

            if ($role && in_array($role, self::SUBTYPE_ROLES, true)) {
                $this->updateSubtype($role, $staff->staff_id, $data);
            }
            $this->audit->log('staff.update', 'staff', $staff->staff_id);
        });

        return redirect($data['redirect_to'])->with('success', 'Staff member updated.');
    }

    public function deactivateStaff(string $id)
    {
        $staff = Staff::with('user')->findOrFail($id);
        $staff->update(['status' => 'inactive']);
        $staff->user?->update(['status' => 'inactive']);
        $this->audit->log('staff.deactivate', 'staff', $staff->staff_id);

        return back()->with('success', 'Staff member deactivated.');
    }

    public function reactivateStaff(string $id)
    {
        $staff = Staff::with('user')->findOrFail($id);
        $staff->update(['status' => 'active']);
        $staff->user?->update(['status' => 'active']);
        $this->audit->log('staff.reactivate', 'staff', $staff->staff_id);

        return back()->with('success', 'Staff member reactivated.');
    }

    private function createSubtype(string $role, string $staffId, array $data): void
    {
        match ($role) {
            'doctor' => Doctor::create([
                'staff_id' => $staffId, 'department_id' => $data['doctor_department_id'] ?? null,
                'specialization' => $data['doctor_specialization'] ?? null, 'license_number' => $data['doctor_license_number'] ?? null,
            ]),
            'nurse' => Nurse::create([
                'staff_id' => $staffId, 'department_id' => $data['nurse_department_id'] ?? null,
                'ward_name' => $data['nurse_ward_name'] ?? null,
            ]),
            'receptionist' => Receptionist::create([
                'staff_id' => $staffId, 'counter_number' => $data['receptionist_counter_number'] ?? null,
            ]),
            'pharmacist' => Pharmacist::create([
                'staff_id' => $staffId, 'license_number' => $data['pharmacist_license_number'] ?? null,
                'pharmacy_unit' => $data['pharmacist_pharmacy_unit'] ?? null,
            ]),
            'lab_technician' => LabTechnician::create([
                'staff_id' => $staffId, 'laboratory_id' => $data['labtech_laboratory_id'] ?? null,
                'skill_area' => $data['labtech_skill_area'] ?? null,
            ]),
            default => null,
        };
    }

    private function updateSubtype(string $role, string $staffId, array $data): void
    {
        match ($role) {
            'doctor' => Doctor::where('staff_id', $staffId)->update([
                'department_id' => $data['doctor_department_id'] ?? null, 'specialization' => $data['doctor_specialization'] ?? null,
                'license_number' => $data['doctor_license_number'] ?? null,
            ]),
            'nurse' => Nurse::where('staff_id', $staffId)->update([
                'department_id' => $data['nurse_department_id'] ?? null, 'ward_name' => $data['nurse_ward_name'] ?? null,
            ]),
            'receptionist' => Receptionist::where('staff_id', $staffId)->update([
                'counter_number' => $data['receptionist_counter_number'] ?? null,
            ]),
            'pharmacist' => Pharmacist::where('staff_id', $staffId)->update([
                'license_number' => $data['pharmacist_license_number'] ?? null, 'pharmacy_unit' => $data['pharmacist_pharmacy_unit'] ?? null,
            ]),
            'lab_technician' => LabTechnician::where('staff_id', $staffId)->update([
                'laboratory_id' => $data['labtech_laboratory_id'] ?? null, 'skill_area' => $data['labtech_skill_area'] ?? null,
            ]),
            default => null,
        };
    }

    private function validateStaff(Request $request, bool $isCreate, ?string $currentUserId = null): array
    {
        $rules = [
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'gender'     => 'nullable|in:male,female,other',
            'phone_number' => 'nullable|string|max:100',
            'address'      => 'nullable|string|max:255',
            'hire_date'    => 'nullable|date',
            'email'    => ['required', 'email', 'max:100', Rule::unique('users', 'email')->ignore($currentUserId, 'user_id')],
            'password' => $isCreate ? 'required|string|min:8' : 'nullable|string|min:8',
            'status'   => 'nullable|in:active,inactive',
            'title'    => 'nullable|string|max:150',
            'employment_type' => 'nullable|in:full_time,part_time',
            'department_id'   => 'nullable|exists:department,department_id',
            'redirect_to' => 'nullable|string',
            'doctor_department_id'  => 'nullable|exists:department,department_id',
            'doctor_specialization' => 'nullable|string|max:100',
            'doctor_license_number' => 'nullable|string|max:100',
            'nurse_department_id'   => 'nullable|exists:department,department_id',
            'nurse_ward_name'       => 'nullable|string|max:100',
            'receptionist_counter_number' => 'nullable|string|max:100',
            'pharmacist_license_number'   => 'nullable|string|max:100',
            'pharmacist_pharmacy_unit'    => 'nullable|string|max:100',
            'labtech_laboratory_id' => 'nullable|exists:laboratory,laboratory_id',
            'labtech_skill_area'    => 'nullable|string|max:100',
        ];
        if ($isCreate) {
            $rules['role'] = ['required', Rule::in(array_keys(config('permissions.roles')))];
        }

        $data = $request->validate($rules);
        $data['redirect_to'] = $data['redirect_to'] ?? '/staff';

        return $data;
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
            ->selectRaw("d.doctor_id, d.staff_id, (s.first_name||' '||s.last_name) as full_name, d.specialization, dep.department_id, dep.department_name, d.license_number, s.status")
            ->paginate(20)
            ->withQueryString();

        return view('admin.doctors', ['rows' => $rows, 'q' => $q, 'departments' => $this->departmentsForDropdown()]);
    }

    public function departments(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $departments = collect($this->centralService->listDepartments(['q' => $q])->throw()->json())
            ->map(fn (array $d) => (object) $d);

        $heads = DB::table('staff')
            ->whereIn('staff_id', $departments->pluck('head_staff_id')->filter()->all())
            ->selectRaw("staff_id, (first_name||' '||last_name) as full_name")
            ->pluck('full_name', 'staff_id');
        $departments->each(fn ($d) => $d->head_name = $heads[$d->head_staff_id] ?? null);

        $stats = [
            'total'    => $departments->count(),
            'active'   => $departments->where('status', 'active')->count(),
            'inactive' => $departments->where('status', 'inactive')->count(),
        ];

        return view('admin.departments', compact('departments', 'q', 'stats'));
    }

    public function createDepartment()
    {
        $department = (object) ['department_name' => null, 'description' => null, 'capacity' => null, 'status' => null];

        return view('admin.department-form', ['department' => $department, 'mode' => 'create']);
    }

    public function storeDepartment(Request $request)
    {
        $data = $this->validateDepartment($request);
        $response = $this->centralService->createDepartment($data)->throw();
        $dept = $response->json();
        $this->audit->log('department.create', 'department', $dept['department_id']);

        return redirect('/departments')->with('success', "Department {$dept['department_id']} created.");
    }

    public function editDepartment(string $id)
    {
        $response = $this->centralService->getDepartment($id);
        abort_if($response->status() === 404, 404);
        $department = (object) $response->throw()->json();

        return view('admin.department-form', ['department' => $department, 'mode' => 'edit']);
    }

    public function updateDepartment(Request $request, string $id)
    {
        $response = $this->centralService->updateDepartment($id, $this->validateDepartment($request));
        abort_if($response->status() === 404, 404);
        $response->throw();

        $this->audit->log('department.update', 'department', $id);

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
        $body = $this->centralService->listRooms([
            'q' => $q, 'page' => (int) $request->query('page', 1),
        ])->throw()->json();

        $rooms = new LengthAwarePaginator(
            collect($body['data'])->map(fn (array $r) => (object) $r),
            $body['meta']['total'],
            $body['meta']['per_page'],
            $body['meta']['current_page'],
            ['path' => $request->url(), 'query' => $request->query(), 'pageName' => 'page'],
        );

        $bedQ = trim((string) $request->query('bed_q', ''));
        $bedStatus = $request->query('bed_status');
        $bedsBody = $this->centralService->listAllBeds([
            'q' => $bedQ, 'status' => $bedStatus, 'page' => (int) $request->query('beds_page', 1),
        ])->throw()->json();
        $beds = new LengthAwarePaginator(
            collect($bedsBody['data'])->map(fn (array $b) => (object) $b),
            $bedsBody['meta']['total'],
            $bedsBody['meta']['per_page'],
            $bedsBody['meta']['current_page'],
            ['path' => $request->url(), 'query' => $request->query(), 'pageName' => 'beds_page'],
        );

        $assignmentsBody = $this->centralService->listRoomAssignments(['page' => (int) $request->query('assignments_page', 1)])->throw()->json();
        $assignments = new LengthAwarePaginator(
            collect($assignmentsBody['data'])->map(fn (array $a) => (object) $a),
            $assignmentsBody['meta']['total'],
            $assignmentsBody['meta']['per_page'],
            $assignmentsBody['meta']['current_page'],
            ['path' => $request->url(), 'query' => $request->query(), 'pageName' => 'assignments_page'],
        );

        return view('admin.rooms', [
            'rooms' => $rooms, 'beds' => $beds, 'assignments' => $assignments,
            'stats' => $body['stats'], 'q' => $q, 'bedQ' => $bedQ, 'bedStatus' => $bedStatus,
            'departments' => $this->departmentsForDropdown(),
        ]);
    }

    public function createRoom()
    {
        $room = (object) ['room_number' => null, 'floor_number' => null, 'room_type' => null, 'department_id' => null, 'status' => null, 'rate_per_day' => null];

        return view('admin.room-form', ['room' => $room, 'mode' => 'create', 'departments' => $this->departmentsForDropdown()]);
    }

    public function storeRoom(Request $request)
    {
        $data = $this->validateRoom($request);
        $response = $this->centralService->createRoom($data)->throw();
        $room = $response->json();
        $this->audit->log('room.create', 'room', $room['room_id']);

        return redirect('/rooms')->with('success', "Room {$room['room_id']} added.");
    }

    public function editRoom(string $id)
    {
        $response = $this->centralService->getRoom($id);
        abort_if($response->status() === 404, 404);
        $room = (object) $response->throw()->json();

        return view('admin.room-form', ['room' => $room, 'mode' => 'edit', 'departments' => $this->departmentsForDropdown()]);
    }

    public function updateRoom(Request $request, string $id)
    {
        $response = $this->centralService->updateRoom($id, $this->validateRoom($request));
        abort_if($response->status() === 404, 404);
        $response->throw();

        $this->audit->log('room.update', 'room', $id);

        return redirect('/rooms')->with('success', 'Room updated.');
    }

    private function validateRoom(Request $request): array
    {
        return $request->validate([
            'department_id' => 'nullable|exists:department,department_id',
            'room_number'   => 'nullable|string|max:100',
            'room_type'     => 'nullable|in:general,private,icu,emergency,operating_room',
            'floor_number'  => 'nullable|integer',
            'status'        => 'nullable|in:available,occupied,maintenance',
            'rate_per_day'  => 'nullable|numeric',
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

    private function departmentsForDropdown()
    {
        return collect($this->centralService->listDepartments()->throw()->json())->map(fn (array $d) => (object) $d);
    }

    private function laboratoriesForDropdown()
    {
        return collect($this->centralService->listAllLaboratories()->throw()->json())->map(fn (array $l) => (object) $l);
    }
}
