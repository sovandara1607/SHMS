<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Services\CentralServiceClient;
use Illuminate\Http\Request;

/**
 * Doctor-side report generation. Like Prescriptions, the form is embedded
 * directly in the medical record show view rather than a separate page —
 * a report always hangs off the medical record it summarizes.
 */
class MedicalReportController extends Controller
{
    public function __construct(private AuditLogger $audit, private CentralServiceClient $centralService) {}

    public function store(Request $request, string $recordId)
    {
        $data = $request->validate([
            'report_type' => 'required|string|in:Progress Report,Discharge Summary,Referral Letter,Diagnostic Summary,Consultation Report',
            'report_content' => 'required|string',
        ]);
        $data['generated_by'] = $request->user()->staff_id;

        $response = $this->centralService->createMedicalReport($recordId, $data);
        abort_if($response->status() === 404, 404);
        if ($response->status() === 422) {
            return back()->withErrors($response->json('errors', []))->withInput();
        }
        $response->throw();

        $result = $response->json();
        $this->audit->log('medical_report.create', 'medical_report', $result['report_id'], [
            'medical_record_id' => $recordId,
        ]);

        return redirect('/medical-records')
            ->with('success', "Report {$result['report_id']} generated.")
            ->with('reopen_record', $recordId);
    }
}
