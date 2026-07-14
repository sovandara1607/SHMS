<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;

/**
 * Synchronous REST calls to the separate central-service repo, for the
 * cases where the caller is actively waiting on a result (e.g. a staff
 * member clicking "Regenerate PDF"). Routine async work goes over
 * CentralServiceBus instead.
 */
class CentralServiceClient
{
    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::baseUrl(config('services.central_service.base_url'))
            ->withHeader('X-Central-Service-Key', config('services.central_service.api_key'))
            ->acceptJson();
    }

    public function labReportStatus(string $labReportId): Response
    {
        return $this->request()->get("/api/lab-reports/{$labReportId}/status");
    }

    public function regenerateLabReport(string $labReportId): Response
    {
        return $this->request()->post("/api/lab-reports/{$labReportId}/regenerate");
    }
}
