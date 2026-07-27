<?php

namespace App\Http\Controllers;

use App\Services\CentralServiceClient;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Read-only list pages (schedule, treatment, prescription, procedure,
 * reports) plus profile/settings that share the generic table view.
 */
class PageController extends Controller
{
    public function __construct(private CentralServiceClient $centralService) {}

    public function schedule(Request $request)
    {
        $body = $this->centralService->getSchedulePage(['page' => (int) $request->query('page', 1)])->throw()->json();
        $rows = $this->paginatorFrom($body, $request);

        return $this->table('Schedule Management',
            ['shift_id' => 'Shift', 'staff_name' => 'Staff', 'shift_date' => 'Date', 'start_time' => 'Start', 'end_time' => 'End', 'shift_type' => 'Type', 'status' => 'Status'], $rows);
    }

    public function prescriptions(Request $request)
    {
        $body = $this->centralService->getPharmacyOverview(['prescriptions_page' => (int) $request->query('page', 1)])->throw()->json();
        $rows = $this->paginatorFrom($body['prescriptions'], $request);

        return $this->table('Prescriptions',
            ['prescription_id' => 'ID', 'patient_name' => 'Patient', 'prescription_date' => 'Date', 'notes' => 'Notes'], $rows);
    }

    public function procedures()
    {
        $rows = DB::table('medical_procedure as mp')
            ->join('patient as p', 'p.patient_id', '=', 'mp.patient_id')
            ->orderByDesc('mp.procedure_date')
            ->selectRaw("mp.*, (p.first_name||' '||p.last_name) as patient_name")->paginate(20)->withQueryString();

        return $this->table('Medical Procedures',
            ['procedure_id' => 'ID', 'patient_name' => 'Patient', 'procedure_name' => 'Procedure', 'outcome' => 'Outcome', 'procedure_date' => 'Date'], $rows);
    }

    public function medicalReports(Request $request)
    {
        $body = $this->centralService->listMedicalReports(['page' => (int) $request->query('page', 1)])->throw()->json();
        $rows = $this->paginatorFrom($body, $request);

        return $this->table('Medical Reports',
            ['report_id' => 'ID', 'patient_name' => 'Patient', 'report_type' => 'Type', 'generated_at' => 'Generated'], $rows);
    }

    public function labReports(Request $request)
    {
        $body = $this->centralService->getLabOverview(['reports_page' => (int) $request->query('page', 1)])->throw()->json();
        $rows = $this->paginatorFrom($body['reports'], $request);

        return $this->table('Lab Reports',
            ['lab_report_id' => 'ID', 'patient_name' => 'Patient', 'test_order_id' => 'Order', 'generated_at' => 'Generated'], $rows);
    }

    public function profile()
    {
        $profile = DB::table('users as u')
            ->join('staff as s', 's.staff_id', '=', 'u.staff_id')
            ->where('u.user_id', Auth::id())
            ->selectRaw('u.user_id, u.email, u.role, s.*')->first();

        return view('misc.profile', ['profile' => $profile]);
    }

    public function settings()
    {
        return view('misc.settings');
    }

    private function table(string $title, array $columns, $rows)
    {
        return view('misc.table', compact('title', 'columns', 'rows'));
    }

    private function paginatorFrom(array $body, Request $request): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            collect($body['data'])->map(fn (array $r) => (object) $r),
            $body['meta']['total'],
            $body['meta']['per_page'],
            $body['meta']['current_page'],
            ['path' => $request->url(), 'query' => $request->query()],
        );
    }
}
