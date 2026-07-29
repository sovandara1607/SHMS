<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Services\CentralServiceClient;
use Illuminate\Http\Request;

/**
 * Doctor-documented procedures. The form is embedded directly in the
 * medical record show view (like Prescriptions/Reports) rather than a
 * separate page, so this only needs a store endpoint.
 */
class ProcedureController extends Controller
{
    public function __construct(private AuditLogger $audit, private CentralServiceClient $centralService) {}

    public function store(Request $request, string $recordId)
    {
        $data = $request->validate([
            'procedure_name' => 'required|string|max:100',
            'procedure_details' => 'nullable|string',
            'outcome' => 'nullable|string',
            'procedure_date' => 'nullable|date',
        ]);

        $response = $this->centralService->createProcedure($recordId, $data);
        abort_if($response->status() === 404, 404);
        if ($response->status() === 422) {
            return back()->withErrors($response->json('errors', []))->withInput();
        }
        $response->throw();

        $procedure = $response->json();
        $this->audit->log('procedure.create', 'medical_procedure', $procedure['procedure_id'], [
            'medical_record_id' => $recordId,
        ]);

        return redirect('/medical-records')
            ->with('success', "Procedure {$procedure['procedure_id']} recorded.")
            ->with('reopen_record', $recordId);
    }
}
