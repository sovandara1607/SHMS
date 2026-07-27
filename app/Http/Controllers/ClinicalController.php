<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Services\CentralServiceClient;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class ClinicalController extends Controller
{
    public function __construct(private AuditLogger $audit, private CentralServiceClient $centralService) {}

    public function vitalSigns(Request $request)
    {
        $page = (int) $request->query('page', 1);

        $response = $this->centralService->listVitalSigns(['page' => $page])->throw();
        $body = $response->json();

        $vitals = new LengthAwarePaginator(
            collect($body['data'])->map(fn (array $v) => (object) $v),
            $body['meta']['total'],
            $body['meta']['per_page'],
            $body['meta']['current_page'],
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('medical.vitals', ['vitals' => $vitals]);
    }

    public function storeVitals(Request $request)
    {
        $data = $request->validate([
            'patient_id'     => 'required|exists:patient,patient_id',
            'temperature'    => 'nullable|numeric',
            'blood_pressure' => 'nullable|string|max:20',
            'heart_rate'     => 'nullable|integer',
            'height'         => 'nullable|numeric',
            'weight'         => 'nullable|numeric',
        ]);
        $data['recorded_by'] = Auth::user()->staff_id;

        $response = $this->centralService->createVitalSign($data);
        if ($response->status() === 422) {
            return back()->withErrors($response->json('errors', []))->withInput();
        }
        $response->throw();

        $this->audit->log('vital_signs.create', 'vital_signs', $data['patient_id']);

        return redirect('/vital-signs')->with('success', 'Vital signs recorded.');
    }
}
