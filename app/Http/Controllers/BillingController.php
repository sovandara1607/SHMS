<?php

namespace App\Http\Controllers;

use App\DTOs\BillDTO;
use App\Services\AuditLogger;
use App\Services\CentralServiceClient;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class BillingController extends Controller
{
    public function __construct(private AuditLogger $audit, private CentralServiceClient $centralService) {}

    public function index(Request $request)
    {
        $status = $request->query('status', 'all');

        $body = $this->centralService->listBills([
            'status' => $status,
            'bills_page' => (int) $request->query('bills_page', 1),
            'payments_page' => (int) $request->query('payments_page', 1),
        ])->throw()->json();

        $bills = $this->paginatorFrom($body['bills'], $request, 'bills_page');
        $payments = $this->paginatorFrom($body['payments'], $request, 'payments_page');
        $stats = $body['stats'];

        return view('billing.index', compact('bills', 'payments', 'status', 'stats'));
    }

    public function create()
    {
        return view('billing.form');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'patient_id'     => 'required|exists:patient,patient_id',
            'appointment_id' => 'nullable|exists:appointment,appointment_id',
            'items'                    => 'nullable|array',
            'items.*.item_type'        => 'required_with:items|in:service,medicine,lab_test,procedure,room',
            'items.*.description'      => 'nullable|string|max:255',
            'items.*.quantity'         => 'required_with:items|integer|min:1',
            'items.*.unit_price'       => 'required_with:items|numeric|min:0',
        ]);
        $data['generated_by'] = Auth::user()->staff_id;

        $response = $this->centralService->createBill($data)->throw();
        $bill = $response->json();
        $this->audit->log('bill.create', 'bill', $bill['bill_id']);

        return redirect('/bills')->with('success', "Bill {$bill['bill_id']} created.");
    }

    public function show(string $id)
    {
        $response = $this->centralService->getBill($id);
        abort_if($response->status() === 404, 404);
        $bill = BillDTO::fromArray($response->throw()->json());

        return view('billing.show', [
            'bill' => $bill,
            'paid' => $bill->paid_amount ?? 0.0,
            'balance' => $bill->balance ?? 0.0,
        ]);
    }

    public function addItemForm(string $id)
    {
        $response = $this->centralService->getBill($id);
        abort_if($response->status() === 404, 404);
        $bill = BillDTO::fromArray($response->throw()->json());

        return view('billing.item-form', compact('bill'));
    }

    public function addItem(Request $request, string $id)
    {
        $data = $request->validate([
            'item_type'   => 'required|in:service,medicine,lab_test,procedure,room',
            'description' => 'nullable|string|max:255',
            'quantity'    => 'required|integer|min:1',
            'unit_price'  => 'required|numeric|min:0',
        ]);

        $response = $this->centralService->addBillItem($id, $data);
        abort_if($response->status() === 404, 404);
        $response->throw();

        $this->audit->log('bill.add_item', 'bill_item', $id);

        return redirect('/bills')->with('success', 'Item added.')->with('reopen_bill', $id);
    }

    public function payForm(string $id)
    {
        $response = $this->centralService->getBill($id);
        abort_if($response->status() === 404, 404);
        $bill = BillDTO::fromArray($response->throw()->json());

        return view('billing.pay-form', [
            'bill' => $bill,
            'paid' => $bill->paid_amount ?? 0.0,
            'balance' => $bill->balance ?? 0.0,
        ]);
    }

    public function pay(Request $request, string $id)
    {
        $data = $request->only(['amount_paid', 'payment_method', 'transaction_reference']);
        $data['received_by'] = Auth::user()->staff_id;

        $response = $this->centralService->payBill($id, $data);
        if ($response->status() === 404) {
            abort(404);
        }
        if ($response->status() === 409) {
            return back()->with('error', $response->json('message'));
        }
        if ($response->status() === 422) {
            return back()->withErrors($response->json('errors', []))->withInput();
        }
        $response->throw();

        $this->audit->log('payment.create', 'payment', $id, [
            'amount' => $data['amount_paid'] ?? null, 'method' => $data['payment_method'] ?? null,
        ]);

        return redirect('/bills')->with('success', 'Payment recorded.');
    }

    public function payments(Request $request)
    {
        $body = $this->centralService->listBills(['payments_page' => (int) $request->query('page', 1)])->throw()->json();
        $rows = $this->paginatorFrom($body['payments'], $request, 'page');

        return view('misc.table', [
            'title' => 'Payment History',
            'columns' => ['payment_id' => 'Payment', 'bill_id' => 'Bill', 'patient_name' => 'Patient',
                'payment_method' => 'Method', 'amount_paid' => 'Amount', 'payment_date' => 'Date'],
            'rows' => $rows,
        ]);
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
