<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\LabReport;
use App\Models\LabTechnician;
use App\Services\AuditLogger;
use App\Services\CentralServiceBus;
use App\Services\CentralServiceClient;
use App\Support\DropdownCache;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LabController extends Controller
{
    public function __construct(
        private AuditLogger $audit,
        private CentralServiceBus $bus,
        private CentralServiceClient $centralService,
    ) {}

    public function orders(Request $request)
    {
        $status = $request->query('status', '');
        $q = trim((string) $request->query('q', ''));

        $body = $this->centralService->getLabOverview([
            'status' => $status,
            'q' => $q,
            'orders_page' => (int) $request->query('orders_page', 1),
            'results_page' => (int) $request->query('results_page', 1),
            'reports_page' => (int) $request->query('reports_page', 1),
        ])->throw()->json();

        $orders = $this->paginatorFrom($body['orders'], $request, 'orders_page');
        $results = $this->paginatorFrom($body['results'], $request, 'results_page');
        $reports = $this->paginatorFrom($body['reports'], $request, 'reports_page');
        $stats = $body['stats'];

        return view('lab.orders', compact('orders', 'results', 'reports', 'status', 'q', 'stats'));
    }

    public function createOrder()
    {
        return view('lab.order-form', [
            'doctors' => DropdownCache::remember('doctors-with-staff', fn () => Doctor::with('staff')->get()->map(fn ($d) => (object) [
                'doctor_id' => $d->doctor_id,
                'staff_id' => $d->staff_id,
                'specialization' => $d->specialization,
                'name' => $d->name(),
                'first_name' => $d->staff?->first_name,
                'last_name' => $d->staff?->last_name,
            ])),
            'technicians' => DropdownCache::remember('technicians-with-staff', fn () => LabTechnician::with('staff')->get()->map(fn ($t) => (object) [
                'technician_id' => $t->technician_id,
                'staff_id' => $t->staff_id,
                'name' => $t->name(),
            ])),
        ]);
    }

    public function storeOrder(Request $request)
    {
        $data = $request->validate([
            'patient_id'     => 'required|exists:patient,patient_id',
            'doctor_id'      => 'required|exists:doctor,doctor_id',
            'technician_id'  => 'nullable|exists:lab_technician,technician_id',
            'test_name'      => 'required|string|max:100',
            'priority'       => 'nullable|in:routine,urgent,stat',
            'notes'          => 'nullable|string',
        ]);

        $response = $this->centralService->createLabOrder($data)->throw();
        $order = $response->json();

        Cache::forget('dashboard:summary');
        $this->audit->log('lab_order.create', 'lab_test_order', $order['test_order_id']);

        return redirect('/lab-orders')->with('success', "Lab order {$order['test_order_id']} created.");
    }

    public function showOrder(string $id)
    {
        $response = $this->centralService->getLabOrder($id);
        abort_if($response->status() === 404, 404);
        $order = (object) $response->throw()->json();

        return view('lab.order-show', compact('order'));
    }

    public function statusForm(string $id)
    {
        $response = $this->centralService->getLabOrder($id);
        abort_if($response->status() === 404, 404);
        $order = (object) $response->throw()->json();

        return view('lab.order-status-form', compact('order'));
    }

    public function updateOrderStatus(Request $request, string $id)
    {
        $data = $request->validate(['status' => 'required|in:pending,in_progress,completed,cancelled']);
        $data['resolved_technician_id'] = $this->technicianId();

        $response = $this->centralService->updateLabOrderStatus($id, $data);
        abort_if($response->status() === 404, 404);
        $response->throw();

        Cache::forget('dashboard:summary');
        $this->audit->log('lab_order.update', 'lab_test_order', $id, ['status' => $data['status']]);

        return redirect('/lab-orders')->with('success', 'Order status updated.');
    }

    public function results()
    {
        return redirect('/lab-orders');
    }

    public function resultForm(string $id)
    {
        $response = $this->centralService->getLabOrder($id);
        abort_if($response->status() === 404, 404);
        $order = (object) $response->throw()->json();

        return view('lab.result-form', ['order' => $order, 'mode' => 'create']);
    }

    public function showResult(string $id)
    {
        $response = $this->centralService->getLabResult($id);
        abort_if($response->status() === 404, 404);
        $body = $response->throw()->json();
        unset($body['order']);
        $result = (object) $body;

        return view('lab.result-show', compact('result'));
    }

    public function editResult(string $id)
    {
        $response = $this->centralService->getLabResult($id);
        abort_if($response->status() === 404, 404);
        $body = $response->throw()->json();
        $order = (object) $body['order'];
        unset($body['order']);
        $result = (object) $body;

        return view('lab.result-form', ['order' => $order, 'result' => $result, 'mode' => 'edit']);
    }

    public function updateResult(Request $request, string $id)
    {
        $data = $request->validate([
            'result_value'  => 'required|string',
            'result_status' => 'required|string',
            'remarks'       => 'nullable|string',
        ]);

        $response = $this->centralService->updateLabResult($id, $data);
        abort_if($response->status() === 404, 404);
        $response->throw();

        $this->audit->log('lab_result.update', 'lab_test_result', $id);

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
        $data['entered_by'] = $this->technicianId();
        $data['generated_by'] = Auth::user()->staff_id;

        $response = $this->centralService->enterLabResult($data);
        if ($response->status() === 409) {
            return redirect('/lab-orders')->with('error', $response->json('message'));
        }
        $response->throw();
        $result = $response->json();

        // The report row itself is created synchronously by central-service
        // (Postgres stays the immediately-consistent source of truth for the
        // Lab Reports tab). The MongoDB snapshot and PDF generation/upload
        // are handled by central-service, published here over the shared
        // Redis bus.
        $this->bus->publish('generate_lab_report_document', [
            'labReportId' => $result['lab_report_id'],
            'testOrderId' => $data['test_order_id'],
            'resultValue' => $data['result_value'],
            'resultStatus' => $data['result_status'],
            'remarks' => $data['remarks'] ?? null,
            'enteredByStaffId' => Auth::user()->staff_id,
            'createdAt' => now()->toIso8601String(),
        ]);

        $this->audit->log('lab_result.create', 'lab_test_result', $data['test_order_id']);

        return redirect('/lab-orders')->with('success', 'Result entered; order marked completed.');
    }

    public function downloadReport(string $id)
    {
        // Local read-only lookup: the file itself lives on Database-final's
        // disk and must stream from here, and this table is shared physical
        // storage central-service also writes to — same justified exception
        // as the existing regenerateReport() below.
        $report = LabReport::findOrFail($id);
        abort_if(! $report->report_file_path, 404, 'Report PDF is still being generated.');

        $disk = Storage::disk(config('filesystems.documents'));
        abort_if(! $disk->exists($report->report_file_path), 404, 'Report PDF not found.');

        return $disk->download($report->report_file_path, "{$report->test_order_id}-lab-report.pdf");
    }

    /**
     * Synchronous fallback for when the async PDF generation over the bus
     * failed or was never picked up (e.g. central-service was down): calls
     * central-service's REST API directly so the staff member gets an
     * immediate result instead of waiting on the queue.
     */
    public function regenerateReport(string $id)
    {
        LabReport::findOrFail($id);

        $response = $this->centralService->regenerateLabReport($id);

        if (in_array($response->status(), [404, 422], true)) {
            return back()->with('error', 'Could not regenerate report: '.$response->json('message'));
        }
        if ($response->failed()) {
            report(new \RuntimeException("Lab report regenerate failed for {$id}: HTTP {$response->status()} - {$response->body()}"));

            return back()->with('error', 'Could not regenerate the report right now. Please try again.');
        }

        $this->audit->log('lab_report.regenerate', 'lab_report', $id);

        return back()->with('success', 'Report PDF regenerated.');
    }

    public function equipment()
    {
        $rows = collect($this->centralService->getLabEquipment()->throw()->json())->map(fn (array $r) => (object) $r);

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

    private function paginatorFrom(array $body, Request $request, string $pageName): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            collect($body['data'])->map(fn (array $r) => (object) $r),
            $body['meta']['total'],
            $body['meta']['per_page'],
            $body['meta']['current_page'],
            ['path' => $request->url(), 'query' => $request->query(), 'pageName' => $pageName],
        );
    }
}
