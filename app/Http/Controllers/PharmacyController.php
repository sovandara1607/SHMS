<?php

namespace App\Http\Controllers;

use App\Models\Pharmacist;
use App\Services\AuditLogger;
use App\Services\CentralServiceClient;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class PharmacyController extends Controller
{
    public function __construct(private AuditLogger $audit, private CentralServiceClient $centralService) {}

    public function medicines(Request $request)
    {
        $body = $this->centralService->getPharmacyOverview([
            'q' => trim((string) $request->query('q', '')),
            'inventory_page' => (int) $request->query('inventory_page', 1),
            'batches_page' => (int) $request->query('batches_page', 1),
            'prescriptions_page' => (int) $request->query('prescriptions_page', 1),
            'dispensing_page' => (int) $request->query('dispensing_page', 1),
        ])->throw()->json();

        $q = trim((string) $request->query('q', ''));
        $medicines = $this->paginatorFrom($body['medicines'], $request, 'inventory_page');
        $batches = $this->paginatorFrom($body['batches'], $request, 'batches_page');
        $prescriptions = $this->paginatorFrom($body['prescriptions'], $request, 'prescriptions_page');
        $dispensingRecords = $this->paginatorFrom($body['dispensing_records'], $request, 'dispensing_page');
        $stats = $body['stats'];

        return view('pharmacy.medicines', compact('medicines', 'q', 'batches', 'prescriptions', 'dispensingRecords', 'stats'));
    }

    public function createMedicine()
    {
        return view('pharmacy.medicine-form');
    }

    public function storeMedicine(Request $request)
    {
        $data = $request->validate([
            'medicine_name' => 'required|string|max:100',
            'medicine_type' => 'nullable|string|max:50',
            'manufacturer'  => 'nullable|string|max:100',
            'unit_price'    => 'nullable|numeric',
            'stock_quantity' => 'nullable|integer',
        ]);

        $response = $this->centralService->createMedicine($data)->throw();
        $medicine = $response->json();

        Cache::forget('medicine:lowstock');
        $this->audit->log('medicine.create', 'medicine', $medicine['medicine_id']);

        return redirect('/medicines')->with('success', "Medicine {$medicine['medicine_id']} added.");
    }

    public function batches()
    {
        $body = $this->centralService->listMedicineBatches()->throw()->json();
        $batches = collect($body['batches'])->map(fn (array $b) => (object) $b);
        $expiring = collect($body['expiring'])->map(fn (array $b) => (object) $b);

        return view('pharmacy.batches', compact('batches', 'expiring'));
    }

    public function createBatch()
    {
        $medicines = collect($this->centralService->listAllMedicines()->throw()->json())
            ->map(fn (array $m) => (object) $m);

        return view('pharmacy.batch-form', ['medicines' => $medicines]);
    }

    public function storeBatch(Request $request)
    {
        $data = $request->validate([
            'medicine_id'      => 'required|exists:medicine,medicine_id',
            'batch_number'     => 'nullable|string|max:100',
            'manufacture_date' => 'nullable|date',
            'expiry_date'      => 'nullable|date',
            'quantity'         => 'required|integer|min:0',
            'status'           => 'nullable|in:valid,expired,damaged',
        ]);

        $response = $this->centralService->createMedicineBatch($data)->throw();
        $batch = $response->json();
        $this->audit->log('medicine_batch.create', 'medicine_batch', $batch['batch_id']);

        return redirect('/medicines')->with('success', "Batch {$batch['batch_id']} added.");
    }

    public function showBatch(string $id)
    {
        $response = $this->centralService->getMedicineBatch($id);
        abort_if($response->status() === 404, 404);
        $body = $response->throw()->json();
        $batch = (object) $body['batch'];
        $medicine = $body['medicine'] ? (object) $body['medicine'] : null;

        return view('pharmacy.batch-show', compact('batch', 'medicine'));
    }

    public function editBatch(string $id)
    {
        $response = $this->centralService->getMedicineBatch($id);
        abort_if($response->status() === 404, 404);
        $batch = (object) $response->throw()->json('batch');

        $medicines = collect($this->centralService->listAllMedicines()->throw()->json())
            ->map(fn (array $m) => (object) $m);

        return view('pharmacy.batch-form', ['batch' => $batch, 'mode' => 'edit', 'medicines' => $medicines]);
    }

    public function updateBatch(Request $request, string $id)
    {
        $data = $request->validate([
            'manufacture_date' => 'nullable|date',
            'expiry_date'      => 'nullable|date',
            'quantity'         => 'required|integer|min:0',
            'status'           => 'nullable|in:valid,expired,damaged',
        ]);

        $response = $this->centralService->updateMedicineBatch($id, $data);
        abort_if($response->status() === 404, 404);
        $response->throw();

        $this->audit->log('medicine_batch.update', 'medicine_batch', $id);

        return redirect('/medicines')->with('success', 'Batch updated.');
    }

    public function dispensing(Request $request)
    {
        $body = $this->centralService->getDispensingPage(['page' => (int) $request->query('page', 1)])->throw()->json();

        $records = $this->paginatorFrom($body['records'], $request, 'page');
        $prescriptions = collect($body['prescriptions'])->map(fn (array $p) => (object) $p);
        $medicines = collect($body['medicines'])->map(fn (array $m) => (object) $m);

        return view('pharmacy.dispensing', compact('records', 'prescriptions', 'medicines'));
    }

    public function showPrescription(string $id)
    {
        $response = $this->centralService->getPrescriptionForPharmacy($id);
        abort_if($response->status() === 404, 404);
        $body = $response->throw()->json();
        $prescription = (object) $body['prescription'];
        $items = collect($body['items'])->map(fn (array $i) => (object) $i);

        return view('pharmacy.prescription-show', compact('prescription', 'items'));
    }

    public function dispenseForm(string $id)
    {
        $response = $this->centralService->getDispenseFormData($id);
        abort_if($response->status() === 404, 404);
        $body = $response->throw()->json();
        $prescription = (object) $body['prescription'];
        $items = collect($body['items'])->map(fn (array $i) => (object) $i);

        return view('pharmacy.dispense-form', compact('prescription', 'items'));
    }

    public function showDispensing(string $id)
    {
        $response = $this->centralService->getDispensingRecord($id);
        abort_if($response->status() === 404, 404);
        $body = $response->throw()->json();
        $record = (object) $body['record'];
        $items = collect($body['items'])->map(fn (array $i) => (object) $i);

        return view('pharmacy.dispensing-show', compact('record', 'items'));
    }

    public function dispense(Request $request)
    {
        $data = $request->validate([
            'prescription_id' => 'required|exists:prescription,prescription_id',
            'patient_id'      => 'required|exists:patient,patient_id',
        ]);
        $data['pharmacist_id'] = Pharmacist::where('staff_id', Auth::user()->staff_id)->value('pharmacist_id');

        $response = $this->centralService->dispenseMedicine($data);
        if ($response->status() === 422) {
            return redirect('/medicines')->with('error', $response->json('message'));
        }
        $response->throw();

        $dispensing = $response->json();
        $this->audit->log('dispensing.create', 'dispensing_record', $dispensing['dispensing_id'], ['prescription' => $data['prescription_id']]);
        Cache::forget('medicine:lowstock');

        return redirect('/medicines')->with('success', 'Medicine dispensed; inventory updated.');
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
