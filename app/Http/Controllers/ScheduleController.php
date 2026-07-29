<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Services\CentralServiceClient;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ScheduleController extends Controller
{
    public function __construct(private AuditLogger $audit, private CentralServiceClient $centralService) {}

    public function index(Request $request)
    {
        $status = $request->query('status', 'all');
        $role = $request->query('role', 'all');
        $body = $this->centralService->getSchedulePage([
            'status' => $status !== 'all' ? $status : null,
            'role' => $role !== 'all' ? $role : null,
            'page' => (int) $request->query('page', 1),
        ])->throw()->json();

        $rows = collect($body['data'])->map(fn (array $r) => (object) $r);
        $roleDept = $this->roleAndDepartmentFor($rows->pluck('staff_id')->all());
        $rows->each(function ($r) use ($roleDept) {
            $r->role = $roleDept[$r->staff_id]['role'] ?? null;
            $r->department = $roleDept[$r->staff_id]['department'] ?? null;
        });

        $shifts = new LengthAwarePaginator(
            $rows,
            $body['meta']['total'],
            $body['meta']['per_page'],
            $body['meta']['current_page'],
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('schedule.index', [
            'shifts' => $shifts, 'status' => $status, 'role' => $role, 'stats' => $body['stats'],
            'roles' => config('permissions.roles'),
        ]);
    }

    public function create()
    {
        return view('schedule.form', ['mode' => 'create', 'shift' => null]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'staff_id' => 'required|exists:staff,staff_id',
            'shift_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
            'shift_type' => 'required|in:morning,afternoon,night',
        ]);

        $response = $this->centralService->createShift($data);
        if ($response->status() === 422) {
            return back()->withErrors($response->json('errors', []))->withInput();
        }
        if ($response->status() === 409) {
            return back()->with('error', $response->json('message'))->withInput();
        }
        $response->throw();

        $shift = $response->json();
        $this->audit->log('schedule.create', 'staff_shift', $shift['shift_id']);

        return redirect('/schedule')->with('success', "Shift {$shift['shift_id']} assigned.");
    }

    public function edit(string $id)
    {
        $response = $this->centralService->getShift($id);
        abort_if($response->status() === 404, 404);
        $response->throw();

        return view('schedule.form', ['mode' => 'edit', 'shift' => (object) $response->json()]);
    }

    public function show(string $id)
    {
        $response = $this->centralService->getShift($id);
        abort_if($response->status() === 404, 404);
        $response->throw();

        $shift = (object) $response->json();
        $roleDept = $this->roleAndDepartmentFor([$shift->staff_id]);
        $shift->role = $roleDept[$shift->staff_id]['role'] ?? null;
        $shift->department = $roleDept[$shift->staff_id]['department'] ?? null;

        return view('schedule.show', ['shift' => $shift]);
    }

    public function update(Request $request, string $id)
    {
        $data = $request->validate([
            'shift_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
            'shift_type' => 'required|in:morning,afternoon,night',
            'status' => 'required|in:scheduled,completed,cancelled,on_leave',
        ]);

        $response = $this->centralService->updateShift($id, $data);
        abort_if($response->status() === 404, 404);
        if ($response->status() === 422) {
            return back()->withErrors($response->json('errors', []))->withInput();
        }
        if ($response->status() === 409) {
            return back()->with('error', $response->json('message'))->withInput();
        }
        $response->throw();

        $this->audit->log('schedule.update', 'staff_shift', $id);

        return redirect('/schedule')->with('success', 'Shift updated.');
    }

    public function cancel(string $id)
    {
        $response = $this->centralService->cancelShift($id);
        abort_if($response->status() === 404, 404);
        $response->throw();

        $this->audit->log('schedule.cancel', 'staff_shift', $id);

        return redirect('/schedule')->with('success', 'Shift cancelled.');
    }

    /** @return array<string, array{role: ?string, department: ?string}> */
    private function roleAndDepartmentFor(array $staffIds): array
    {
        $staffIds = array_values(array_unique(array_filter($staffIds)));
        if (empty($staffIds)) {
            return [];
        }

        return DB::table('staff as s')
            ->join('users as u', 'u.staff_id', '=', 's.staff_id')
            ->leftJoin('doctor as d', 'd.staff_id', '=', 's.staff_id')
            ->leftJoin('nurse as n', 'n.staff_id', '=', 's.staff_id')
            ->leftJoin('department as dept_doc', 'dept_doc.department_id', '=', 'd.department_id')
            ->leftJoin('department as dept_nurse', 'dept_nurse.department_id', '=', 'n.department_id')
            ->whereIn('s.staff_id', $staffIds)
            ->selectRaw("s.staff_id, u.role, COALESCE(dept_doc.department_name, dept_nurse.department_name) as department_name")
            ->get()
            ->keyBy('staff_id')
            ->map(fn ($r) => ['role' => $r->role, 'department' => $r->department_name])
            ->all();
    }
}
