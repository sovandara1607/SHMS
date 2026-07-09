<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Doctor;
use App\Models\LabTechnician;
use App\Models\Laboratory;
use App\Models\Nurse;
use App\Models\Pharmacist;
use App\Models\Receptionist;
use App\Models\Room;
use App\Models\Staff;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/** Admin-only management & reporting list screens. */
class AdminController extends Controller
{
    private const SUBTYPE_ROLES = ['doctor', 'nurse', 'receptionist', 'pharmacist', 'lab_technician'];

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

        return view('admin.staff', ['rows' => $rows, 'q' => $q]);
    }

    public function createStaff(Request $request)
    {
        return view('admin.staff-form', [
            'mode' => 'create',
            'staff' => new Staff(),
            'lockedRole' => $request->query('role'),
            'redirectTo' => $request->query('role') === 'doctor' ? '/doctors' : '/staff',
            'departments' => Department::orderBy('department_name')->get(),
            'laboratories' => Laboratory::orderBy('laboratory_name')->get(),
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
            'departments' => Department::orderBy('department_name')->get(),
            'laboratories' => Laboratory::orderBy('laboratory_name')->get(),
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
            ->limit(200)->get();

        return view('admin.doctors', ['rows' => $rows, 'q' => $q, 'departments' => Department::orderBy('department_name')->get()]);
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
