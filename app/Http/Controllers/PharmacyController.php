<?php

namespace App\Http\Controllers;

use App\Models\DispensingItem;
use App\Models\DispensingRecord;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\Pharmacist;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PharmacyController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function medicines(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $medicines = Medicine::query()
            ->when($q !== '', function ($query) use ($q) {
                $like = '%' . $q . '%';
                $query->where('medicine_id', 'ilike', $like)
                    ->orWhere('medicine_name', 'ilike', $like)
                    ->orWhere('manufacturer', 'ilike', $like);
            })
            ->orderBy('medicine_name')->limit(200)->get();

        $batches = DB::table('medicine_batch as b')
            ->join('medicine as m', 'm.medicine_id', '=', 'b.medicine_id')
            ->orderBy('b.expiry_date')
            ->selectRaw('b.*, m.medicine_name')->get();

        $prescriptions = DB::table('prescription as pr')
            ->join('patient as p', 'p.patient_id', '=', 'pr.patient_id')
            ->join('doctor as d', 'd.doctor_id', '=', 'pr.doctor_id')
            ->join('staff as s', 's.staff_id', '=', 'd.staff_id')
            ->orderByDesc('pr.prescription_date')
            ->selectRaw("pr.*, (p.first_name||' '||p.last_name) as patient_name, (s.first_name||' '||s.last_name) as doctor_name")
            ->limit(100)->get();

        $dispensingRecords = DB::table('dispensing_record as dr')
            ->join('patient as p', 'p.patient_id', '=', 'dr.patient_id')
            ->leftJoin('pharmacist as ph', 'ph.pharmacist_id', '=', 'dr.pharmacist_id')
            ->leftJoin('staff as s', 's.staff_id', '=', 'ph.staff_id')
            ->orderByDesc('dr.dispensing_date')
            ->selectRaw("dr.*, (p.first_name||' '||p.last_name) as patient_name, (s.first_name||' '||s.last_name) as pharmacist_name")
            ->limit(100)->get();

        $stats = [
            'total'   => Medicine::count(),
            'available' => Medicine::where('status', 'available')->count(),
            'low_stock' => Medicine::where('stock_quantity', '<=', 20)->count(),
            'expired_batches' => MedicineBatch::where('status', 'expired')->count(),
        ];

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
        $med = Medicine::create($data);
        Cache::forget('medicine:lowstock');
        $this->audit->log('medicine.create', 'medicine', $med->medicine_id);

        return redirect('/medicines')->with('success', "Medicine {$med->medicine_id} added.");
    }

    public function batches()
    {
        $batches = DB::table('medicine_batch as b')
            ->join('medicine as m', 'm.medicine_id', '=', 'b.medicine_id')
            ->orderBy('b.expiry_date')
            ->selectRaw('b.*, m.medicine_name')->get();

        $expiring = DB::table('medicine_batch as b')
            ->join('medicine as m', 'm.medicine_id', '=', 'b.medicine_id')
            ->whereRaw("b.expiry_date <= (CURRENT_DATE + interval '30 day')")
            ->where('b.status', 'valid')
            ->orderBy('b.expiry_date')
            ->selectRaw('b.*, m.medicine_name')->get();

        return view('pharmacy.batches', compact('batches', 'expiring'));
    }

    public function createBatch()
    {
        return view('pharmacy.batch-form', ['medicines' => Medicine::orderBy('medicine_name')->get()]);
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
        $batch = MedicineBatch::create($data);
        $this->audit->log('medicine_batch.create', 'medicine_batch', $batch->batch_id);

        return redirect('/medicines')->with('success', "Batch {$batch->batch_id} added.");
    }

    public function showBatch(string $id)
    {
        $batch = MedicineBatch::with([])->findOrFail($id);
        $medicine = Medicine::find($batch->medicine_id);

        return view('pharmacy.batch-show', compact('batch', 'medicine'));
    }

    public function editBatch(string $id)
    {
        $batch = MedicineBatch::findOrFail($id);

        return view('pharmacy.batch-form', ['batch' => $batch, 'mode' => 'edit', 'medicines' => Medicine::orderBy('medicine_name')->get()]);
    }

    public function updateBatch(Request $request, string $id)
    {
        $batch = MedicineBatch::findOrFail($id);
        $data = $request->validate([
            'manufacture_date' => 'nullable|date',
            'expiry_date'      => 'nullable|date',
            'quantity'         => 'required|integer|min:0',
            'status'           => 'nullable|in:valid,expired,damaged',
        ]);
        $batch->update($data);
        $this->audit->log('medicine_batch.update', 'medicine_batch', $batch->batch_id);

        return redirect('/medicines')->with('success', 'Batch updated.');
    }

    public function dispensing()
    {
        $records = DB::table('dispensing_record as dr')
            ->join('patient as p', 'p.patient_id', '=', 'dr.patient_id')
            ->orderByDesc('dr.dispensing_date')
            ->selectRaw("dr.*, (p.first_name||' '||p.last_name) as patient_name")
            ->limit(100)->get();

        $prescriptions = DB::table('prescription as pr')
            ->join('patient as p', 'p.patient_id', '=', 'pr.patient_id')
            ->orderByDesc('pr.prescription_date')
            ->selectRaw("pr.prescription_id, pr.patient_id, (p.first_name||' '||p.last_name) as patient_name")
            ->limit(100)->get();

        return view('pharmacy.dispensing', [
            'records' => $records,
            'prescriptions' => $prescriptions,
            'medicines' => Medicine::orderBy('medicine_name')->get(),
        ]);
    }

    public function showPrescription(string $id)
    {
        $prescription = DB::table('prescription as pr')
            ->join('patient as p', 'p.patient_id', '=', 'pr.patient_id')
            ->join('doctor as d', 'd.doctor_id', '=', 'pr.doctor_id')
            ->join('staff as s', 's.staff_id', '=', 'd.staff_id')
            ->where('pr.prescription_id', $id)
            ->selectRaw("pr.*, (p.first_name||' '||p.last_name) as patient_name, (s.first_name||' '||s.last_name) as doctor_name")
            ->firstOrFail();

        $items = DB::table('prescription_item as pi')
            ->join('medicine as m', 'm.medicine_id', '=', 'pi.medicine_id')
            ->where('pi.prescription_id', $id)
            ->selectRaw('pi.*, m.medicine_name')->get();

        return view('pharmacy.prescription-show', compact('prescription', 'items'));
    }

    public function dispenseForm(string $id)
    {
        $prescription = DB::table('prescription as pr')
            ->join('patient as p', 'p.patient_id', '=', 'pr.patient_id')
            ->where('pr.prescription_id', $id)
            ->selectRaw("pr.*, (p.first_name||' '||p.last_name) as patient_name")
            ->firstOrFail();

        $items = DB::table('prescription_item as pi')
            ->join('medicine as m', 'm.medicine_id', '=', 'pi.medicine_id')
            ->where('pi.prescription_id', $id)
            ->selectRaw('pi.*, m.medicine_name')->get();

        return view('pharmacy.dispense-form', compact('prescription', 'items'));
    }

    public function showDispensing(string $id)
    {
        $record = DB::table('dispensing_record as dr')
            ->join('patient as p', 'p.patient_id', '=', 'dr.patient_id')
            ->leftJoin('pharmacist as ph', 'ph.pharmacist_id', '=', 'dr.pharmacist_id')
            ->leftJoin('staff as s', 's.staff_id', '=', 'ph.staff_id')
            ->where('dr.dispensing_id', $id)
            ->selectRaw("dr.*, (p.first_name||' '||p.last_name) as patient_name, (s.first_name||' '||s.last_name) as pharmacist_name")
            ->firstOrFail();

        $items = DB::table('dispensing_item as di')
            ->join('medicine as m', 'm.medicine_id', '=', 'di.medicine_id')
            ->leftJoin('medicine_batch as b', 'b.batch_id', '=', 'di.batch_id')
            ->where('di.dispensing_id', $id)
            ->selectRaw('di.*, m.medicine_name, b.batch_number')->get();

        return view('pharmacy.dispensing-show', compact('record', 'items'));
    }

    public function dispense(Request $request)
    {
        $data = $request->validate([
            'prescription_id' => 'required|exists:prescription,prescription_id',
            'patient_id'      => 'required|exists:patient,patient_id',
        ]);

        try {
            DB::transaction(function () use ($data) {
                $items = DB::table('prescription_item')->where('prescription_id', $data['prescription_id'])->get();
                if ($items->isEmpty()) {
                    throw new \RuntimeException('This prescription has no items to dispense.');
                }

                $pharmacistId = Pharmacist::where('staff_id', Auth::user()->staff_id)->value('pharmacist_id');

                $disp = DispensingRecord::create([
                    'prescription_id' => $data['prescription_id'],
                    'patient_id' => $data['patient_id'],
                    'pharmacist_id' => $pharmacistId,
                ]);

                foreach ($items as $item) {
                    $med = Medicine::lockForUpdate()->findOrFail($item->medicine_id);
                    $qty = (int) ($item->quantity ?? 0);
                    if ($qty < 1) {
                        continue;
                    }
                    if ($med->stock_quantity < $qty) {
                        throw new \RuntimeException("Insufficient stock for {$med->medicine_name}." . $this->substitutionSuggestion($med->medicine_id, $qty));
                    }

                    // FEFO: earliest-expiry valid batch with enough quantity.
                    $batch = DB::table('medicine_batch')
                        ->where('medicine_id', $med->medicine_id)->where('status', 'valid')
                        ->where('quantity', '>=', $qty)
                        ->orderBy('expiry_date')->first();

                    DispensingItem::create([
                        'dispensing_id' => $disp->dispensing_id,
                        'medicine_id' => $med->medicine_id,
                        'batch_id' => $batch?->batch_id,
                        'quantity_dispensed' => $qty,
                    ]);
                    $med->decrement('stock_quantity', $qty);
                    if ($batch) {
                        DB::table('medicine_batch')->where('batch_id', $batch->batch_id)->decrement('quantity', $qty);
                    }
                }

                $this->audit->log('dispensing.create', 'dispensing_record', $disp->dispensing_id, ['prescription' => $data['prescription_id']]);
            });
            Cache::forget('medicine:lowstock');

            return redirect('/medicines')->with('success', 'Medicine dispensed; inventory updated.');
        } catch (\Throwable $e) {
            return redirect('/medicines')->with('error', $e->getMessage());
        }
    }

    /** Suggests a substitute with enough stock, from drug_substitution, when the original is short. */
    private function substitutionSuggestion(string $medicineId, int $neededQty): string
    {
        $alt = DB::table('drug_substitution as ds')
            ->join('medicine as m', 'm.medicine_id', '=', 'ds.alternative_medicine_id')
            ->where('ds.original_medicine_id', $medicineId)
            ->where('m.stock_quantity', '>=', $neededQty)
            ->selectRaw('m.medicine_name, ds.reason')
            ->first();

        if (! $alt) {
            return '';
        }

        return " Suggested substitute: {$alt->medicine_name}" . ($alt->reason ? " ({$alt->reason})" : '') . '.';
    }

    public function interactions()
    {
        $rows = DB::table('drug_interaction as di')
            ->join('medicine as m1', 'm1.medicine_id', '=', 'di.medicine_id_1')
            ->join('medicine as m2', 'm2.medicine_id', '=', 'di.medicine_id_2')
            ->orderByDesc('di.severity')
            ->selectRaw('di.*, m1.medicine_name as med1, m2.medicine_name as med2')->get();

        return view('misc.table', [
            'title' => 'Drug Interactions',
            'intro' => 'Known interaction rules checked during prescribing and dispensing.',
            'columns' => ['med1' => 'Medicine A', 'med2' => 'Medicine B', 'interaction_effect' => 'Effect', 'severity' => 'Severity'],
            'rows' => $rows,
        ]);
    }

    public function substitutions()
    {
        $rows = DB::table('drug_substitution as ds')
            ->join('medicine as m1', 'm1.medicine_id', '=', 'ds.original_medicine_id')
            ->join('medicine as m2', 'm2.medicine_id', '=', 'ds.alternative_medicine_id')
            ->selectRaw('ds.*, m1.medicine_name as original, m2.medicine_name as alternative')->get();

        return view('misc.table', [
            'title' => 'Drug Substitutions',
            'intro' => 'Alternative medicines suggested when an item is out of stock.',
            'columns' => ['original' => 'Original', 'alternative' => 'Alternative', 'reason' => 'Reason'],
            'rows' => $rows,
        ]);
    }
}
