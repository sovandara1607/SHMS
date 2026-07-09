<?php

namespace App\Http\Controllers;

use App\Models\MedicalRecord;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Doctor-side prescribing. The form is embedded directly in the medical
 * record show view (like "Adjust Medical Record") rather than a separate
 * page, so this only needs a store endpoint.
 */
class PrescriptionController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function store(Request $request, string $recordId)
    {
        $record = MedicalRecord::findOrFail($recordId);

        $data = $request->validate([
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.medicine_id' => 'required|exists:medicine,medicine_id',
            'items.*.dosage' => 'nullable|string|max:100',
            'items.*.frequency' => 'nullable|string|max:100',
            'items.*.duration' => 'nullable|string|max:100',
            'items.*.quantity' => 'nullable|integer|min:1',
        ]);

        $prescription = DB::transaction(function () use ($record, $data) {
            $prescription = Prescription::create([
                'medical_record_id' => $record->medical_record_id,
                'patient_id' => $record->patient_id,
                'doctor_id' => $record->doctor_id,
                'prescription_date' => now()->toDateString(),
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                PrescriptionItem::create([
                    'prescription_id' => $prescription->prescription_id,
                    'medicine_id' => $item['medicine_id'],
                    'dosage' => $item['dosage'] ?? null,
                    'frequency' => $item['frequency'] ?? null,
                    'duration' => $item['duration'] ?? null,
                    'quantity' => $item['quantity'] ?? null,
                ]);
            }

            $this->audit->log('prescription.create', 'prescription', $prescription->prescription_id, [
                'medical_record_id' => $record->medical_record_id, 'items' => count($data['items']),
            ]);

            return $prescription;
        });

        $warning = $this->interactionWarning(array_column($data['items'], 'medicine_id'));

        $redirect = redirect('/medical-records/' . $record->medical_record_id)
            ->with('success', "Prescription {$prescription->prescription_id} created.")
            ->with('reopen_record', $record->medical_record_id);

        if ($warning) {
            $redirect->with('warning', $warning);
        }

        return $redirect;
    }

    /**
     * Checks every pairwise combination of the prescribed medicines against
     * drug_interaction. Doesn't block the prescription — flags it for the
     * doctor's clinical judgement, same as a real EHR interaction alert.
     */
    private function interactionWarning(array $medicineIds): ?string
    {
        $medicineIds = array_values(array_unique($medicineIds));
        if (count($medicineIds) < 2) {
            return null;
        }

        $hits = DB::table('drug_interaction as di')
            ->join('medicine as m1', 'm1.medicine_id', '=', 'di.medicine_id_1')
            ->join('medicine as m2', 'm2.medicine_id', '=', 'di.medicine_id_2')
            ->whereIn('di.medicine_id_1', $medicineIds)
            ->whereIn('di.medicine_id_2', $medicineIds)
            ->whereColumn('di.medicine_id_1', '!=', 'di.medicine_id_2')
            ->selectRaw("m1.medicine_name as med1, m2.medicine_name as med2, di.interaction_effect, di.severity")
            ->get();

        if ($hits->isEmpty()) {
            return null;
        }

        return 'Drug interaction warning — ' . $hits->map(
            fn ($h) => "{$h->med1} + {$h->med2} ({$h->severity}): {$h->interaction_effect}"
        )->implode('; ');
    }
}
