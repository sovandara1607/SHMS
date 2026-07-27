<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Services\CentralServiceClient;
use Illuminate\Http\Request;

/**
 * Doctor-side prescribing. The form is embedded directly in the medical
 * record show view (like "Adjust Medical Record") rather than a separate
 * page, so this only needs a store endpoint.
 */
class PrescriptionController extends Controller
{
    public function __construct(private AuditLogger $audit, private CentralServiceClient $centralService) {}

    public function store(Request $request, string $recordId)
    {
        $data = $request->validate([
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.medicine_id' => 'required|exists:medicine,medicine_id',
            'items.*.dosage' => 'nullable|string|max:100',
            'items.*.frequency' => 'nullable|string|max:100',
            'items.*.duration' => 'nullable|string|max:100',
            'items.*.quantity' => 'nullable|integer|min:1',
        ]);

        $response = $this->centralService->createPrescription($recordId, $data);
        abort_if($response->status() === 404, 404);
        if ($response->status() === 422) {
            return back()->withErrors($response->json('errors', []))->withInput();
        }
        $response->throw();

        $result = $response->json();
        $this->audit->log('prescription.create', 'prescription', $result['prescription_id'], [
            'medical_record_id' => $recordId, 'items' => count($data['items']),
        ]);

        return redirect('/medical-records')
            ->with('success', "Prescription {$result['prescription_id']} created.")
            ->with('reopen_record', $recordId);
    }
}
