<?php

namespace App\Http\Controllers;

use App\Models\MedicalReport;
use App\Services\AuditLogger;
use App\Services\CentralServiceClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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

    public function downloadReport(string $id)
    {
        // Local read-only lookup: the file itself lives on Database-final's
        // disk and must stream from here, same justified exception as the
        // existing LabController::downloadReport()/regenerateReport().
        $report = MedicalReport::findOrFail($id);
        abort_if(! $report->report_file_path, 404, 'Report PDF is still being generated.');

        $disk = Storage::disk(config('filesystems.documents'));
        abort_if(! $disk->exists($report->report_file_path), 404, 'Report PDF not found.');

        return $disk->download($report->report_file_path, "{$report->report_id}-medical-report.pdf");
    }

    /**
     * Synchronous fallback for when the PDF job failed or was never picked
     * up (e.g. central-service's queue worker was down): calls
     * central-service's REST API directly so the staff member gets an
     * immediate result instead of waiting on the queue.
     */
    public function regenerateReport(string $id)
    {
        MedicalReport::findOrFail($id);

        $response = $this->centralService->regenerateMedicalReport($id);

        if (in_array($response->status(), [404, 422], true)) {
            return back()->with('error', 'Could not regenerate report: '.$response->json('message'));
        }
        if ($response->failed()) {
            report(new \RuntimeException("Medical report regenerate failed for {$id}: HTTP {$response->status()} - {$response->body()}"));

            return back()->with('error', 'Could not regenerate the report right now. Please try again.');
        }

        $this->audit->log('medical_report.regenerate', 'medical_report', $id);

        return back()->with('success', 'Report PDF regenerated.');
    }
}
