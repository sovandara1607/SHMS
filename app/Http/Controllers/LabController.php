<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateLabReportDocumentJob;
use App\Models\LabReport;
use App\Models\LabTestOrder;
use App\Models\LabTestResult;
use App\Models\Doctor;
use App\Models\LabTechnician;
use App\Models\Patient;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LabController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function orders(Request $request)
    {
        $status = $request->query('status', '');

        $orders = DB::table('lab_test_order as o')
            ->join('patient as p', 'p.patient_id', '=', 'o.patient_id')
            ->join('doctor as d', 'd.doctor_id', '=', 'o.doctor_id')
            ->join('staff as s', 's.staff_id', '=', 'd.staff_id')
            ->leftJoin('lab_technician as t', 't.technician_id', '=', 'o.technician_id')
            ->leftJoin('staff as ts', 'ts.staff_id', '=', 't.staff_id')
            ->when($status !== '', fn ($q) => $q->where('o.status', $status))
            ->orderByDesc('o.order_date')
            ->selectRaw("o.*, (p.first_name||' '||p.last_name) as patient_name, (s.first_name||' '||s.last_name) as doctor_name, (ts.first_name||' '||ts.last_name) as technician_name")
            ->limit(200)->get();

        $results = DB::table('lab_test_result as r')
            ->join('lab_test_order as o', 'o.test_order_id', '=', 'r.test_order_id')
            ->join('patient as p', 'p.patient_id', '=', 'o.patient_id')
            ->leftJoin('lab_technician as t', 't.technician_id', '=', 'r.entered_by')
            ->leftJoin('staff as ts', 'ts.staff_id', '=', 't.staff_id')
            ->orderByDesc('r.entered_at')
            ->selectRaw("r.*, o.test_name, o.test_order_id, (p.first_name||' '||p.last_name) as patient_name, (ts.first_name||' '||ts.last_name) as technician_name")
            ->limit(100)->get();

        $reports = DB::table('lab_report as lr')
            ->join('lab_test_order as o', 'o.test_order_id', '=', 'lr.test_order_id')
            ->join('patient as p', 'p.patient_id', '=', 'lr.patient_id')
            ->leftJoin('staff as s', 's.staff_id', '=', 'lr.generated_by')
            ->orderByDesc('lr.generated_at')
            ->selectRaw("lr.*, o.test_name, (p.first_name||' '||p.last_name) as patient_name, (s.first_name||' '||s.last_name) as generated_by_name")
            ->limit(100)->get();

        $stats = [
            'pending'     => LabTestOrder::where('status', 'pending')->count(),
            'in_progress' => LabTestOrder::where('status', 'in_progress')->count(),
            'completed'   => LabTestOrder::where('status', 'completed')->count(),
            // NOT EXISTS instead of whereNotIn(...pluck()): at scale, pluck()
            // inlines every lab_test_result id as a bound parameter and blows
            // past Postgres's 65535-parameter-per-statement limit.
            'pending_results' => DB::table('lab_test_order as o')
                ->where('o.status', 'completed')
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('lab_test_result as r')
                        ->whereColumn('r.test_order_id', 'o.test_order_id');
                })
                ->count(),
        ];

        return view('lab.orders', compact('orders', 'results', 'reports', 'status', 'stats'));
    }

    public function createOrder()
    {
        return view('lab.order-form', [
            'doctors' => Doctor::with('staff')->get(),
            'technicians' => LabTechnician::with('staff')->get(),
        ]);
    }

    public function storeOrder(Request $request)
    {
        $data = $request->validate([
            'patient_id'     => 'required|exists:patient,patient_id',
            'doctor_id'      => 'required|exists:doctor,doctor_id',
            'technician_id'  => 'nullable|exists:lab_technician,technician_id',
            'test_name'      => 'required|string|max:100',
            'priority'       => 'nullable|string',
            'notes'          => 'nullable|string',
        ]);
        unset($data['priority'], $data['notes']);

        $order = LabTestOrder::create($data);
        Cache::forget('dashboard:summary');
        $this->audit->log('lab_order.create', 'lab_test_order', $order->test_order_id);

        return redirect('/lab-orders')->with('success', "Lab order {$order->test_order_id} created.");
    }

    public function showOrder(string $id)
    {
        $order = DB::table('lab_test_order as o')
            ->join('patient as p', 'p.patient_id', '=', 'o.patient_id')
            ->join('doctor as d', 'd.doctor_id', '=', 'o.doctor_id')
            ->join('staff as s', 's.staff_id', '=', 'd.staff_id')
            ->leftJoin('lab_technician as t', 't.technician_id', '=', 'o.technician_id')
            ->leftJoin('staff as ts', 'ts.staff_id', '=', 't.staff_id')
            ->where('o.test_order_id', $id)
            ->selectRaw("o.*, (p.first_name||' '||p.last_name) as patient_name, (s.first_name||' '||s.last_name) as doctor_name, (ts.first_name||' '||ts.last_name) as technician_name")
            ->firstOrFail();

        return view('lab.order-show', compact('order'));
    }

    public function statusForm(string $id)
    {
        $order = LabTestOrder::findOrFail($id);

        return view('lab.order-status-form', compact('order'));
    }

    public function updateOrderStatus(Request $request, string $id)
    {
        $status = $request->input('status', 'in_progress');
        $order = LabTestOrder::findOrFail($id);
        $order->status = $status;
        if ($tech = $this->technicianId()) {
            $order->technician_id = $order->technician_id ?: $tech;
        }
        $order->save();
        Cache::forget('dashboard:summary');
        $this->audit->log('lab_order.update', 'lab_test_order', $id, ['status' => $status]);

        return redirect('/lab-orders')->with('success', 'Order status updated.');
    }

    public function results()
    {
        return redirect('/lab-orders');
    }

    public function resultForm(string $id)
    {
        $order = DB::table('lab_test_order as o')
            ->join('patient as p', 'p.patient_id', '=', 'o.patient_id')
            ->join('doctor as d', 'd.doctor_id', '=', 'o.doctor_id')
            ->join('staff as s', 's.staff_id', '=', 'd.staff_id')
            ->leftJoin('lab_technician as t', 't.technician_id', '=', 'o.technician_id')
            ->leftJoin('staff as ts', 'ts.staff_id', '=', 't.staff_id')
            ->where('o.test_order_id', $id)
            ->selectRaw("o.*, (p.first_name||' '||p.last_name) as patient_name, (s.first_name||' '||s.last_name) as doctor_name, (ts.first_name||' '||ts.last_name) as technician_name")
            ->firstOrFail();

        return view('lab.result-form', ['order' => $order, 'mode' => 'create']);
    }

    public function showResult(string $id)
    {
        $result = DB::table('lab_test_result as r')
            ->join('lab_test_order as o', 'o.test_order_id', '=', 'r.test_order_id')
            ->join('patient as p', 'p.patient_id', '=', 'o.patient_id')
            ->where('r.test_result_id', $id)
            ->selectRaw("r.*, o.test_name, (p.first_name||' '||p.last_name) as patient_name, p.patient_id")
            ->firstOrFail();

        return view('lab.result-show', compact('result'));
    }

    public function editResult(string $id)
    {
        $result = LabTestResult::findOrFail($id);
        $order = DB::table('lab_test_order as o')
            ->join('patient as p', 'p.patient_id', '=', 'o.patient_id')
            ->join('doctor as d', 'd.doctor_id', '=', 'o.doctor_id')
            ->join('staff as s', 's.staff_id', '=', 'd.staff_id')
            ->where('o.test_order_id', $result->test_order_id)
            ->selectRaw("o.*, (p.first_name||' '||p.last_name) as patient_name, (s.first_name||' '||s.last_name) as doctor_name")
            ->firstOrFail();

        return view('lab.result-form', ['order' => $order, 'result' => $result, 'mode' => 'edit']);
    }

    public function updateResult(Request $request, string $id)
    {
        $result = LabTestResult::findOrFail($id);
        $data = $request->validate([
            'result_value'  => 'required|string',
            'result_status' => 'required|string',
            'remarks'       => 'nullable|string',
        ]);
        $result->update($data);
        $this->audit->log('lab_result.update', 'lab_test_result', $result->test_result_id);

        return redirect('/lab-orders')->with('success', 'Result updated.');
    }

    public function enterResult(Request $request)
    {
        $data = $request->validate([
            'test_order_id' => 'required|exists:lab_test_order,test_order_id',
            'result_value'  => 'required|string',
            'result_status' => 'required|string',
            'remarks'       => 'nullable|string',
        ]);

        $result = LabTestResult::create([
            'test_result_id' => 'LRS' . strtoupper(Str::random(8)),
            'test_order_id' => $data['test_order_id'],
            'result_value' => $data['result_value'],
            'result_status' => $data['result_status'],
            'remarks' => $data['remarks'] ?? null,
            'entered_by' => $this->technicianId(),
        ]);
        $order = LabTestOrder::where('test_order_id', $data['test_order_id']);
        $order->update(['status' => 'completed']);

        // The report row itself is created synchronously (Postgres stays the
        // immediately-consistent source of truth for the Lab Reports tab).
        // The MongoDB snapshot and PDF generation/upload are handled by the
        // Central Service's queue worker — see GenerateLabReportDocumentJob.
        $labReport = LabReport::create([
            'test_order_id' => $data['test_order_id'],
            'patient_id' => LabTestOrder::where('test_order_id', $data['test_order_id'])->value('patient_id'),
            'report_content' => $data['result_value'] . ($data['remarks'] ? " — {$data['remarks']}" : ''),
            'generated_by' => Auth::user()->staff_id,
        ]);

        GenerateLabReportDocumentJob::dispatch(
            $labReport->lab_report_id,
            $data['test_order_id'],
            $data['result_value'],
            $data['result_status'],
            $data['remarks'] ?? null,
            Auth::user()->staff_id,
            now()->toIso8601String(),
        );

        $this->audit->log('lab_result.create', 'lab_test_result', $data['test_order_id']);

        return redirect('/lab-orders')->with('success', 'Result entered; order marked completed.');
    }

    public function downloadReport(string $id)
    {
        $report = LabReport::findOrFail($id);
        abort_if(! $report->report_file_path, 404, 'Report PDF is still being generated.');

        $disk = Storage::disk(config('filesystems.documents'));
        abort_if(! $disk->exists($report->report_file_path), 404, 'Report PDF not found.');

        return $disk->download($report->report_file_path, "{$report->test_order_id}-lab-report.pdf");
    }

    public function equipment()
    {
        $rows = DB::table('laboratory_equipment as e')
            ->leftJoin('laboratory as l', 'l.laboratory_id', '=', 'e.laboratory_id')
            ->orderBy('e.equipment_name')
            ->selectRaw('e.*, l.laboratory_name')->get();

        return view('misc.table', [
            'title' => 'Laboratory Equipment',
            'columns' => ['equipment_id' => 'ID', 'equipment_name' => 'Name', 'equipment_type' => 'Type',
                'laboratory_name' => 'Laboratory', 'availability_status' => 'Status', 'last_maintenance_date' => 'Last maintenance'],
            'rows' => $rows,
        ]);
    }

    private function technicianId(): ?string
    {
        return DB::table('lab_technician')->where('staff_id', Auth::user()->staff_id)->value('technician_id');
    }
}
