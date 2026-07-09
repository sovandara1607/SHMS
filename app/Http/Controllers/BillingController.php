<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Payment;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BillingController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request)
    {
        $status = $request->query('status', 'all');

        $bills = DB::table('bill as b')
            ->join('patient as p', 'p.patient_id', '=', 'b.patient_id')
            ->leftJoin('payment as pm', 'pm.bill_id', '=', 'b.bill_id')
            ->when($status !== 'all', fn ($q) => $q->where('b.status', $status))
            ->groupBy('b.bill_id', 'b.patient_id', 'b.appointment_id', 'b.generated_by', 'b.bill_date', 'b.total_amount', 'b.status', 'p.first_name', 'p.last_name')
            ->orderByDesc('b.bill_date')
            ->selectRaw("b.*, (p.first_name||' '||p.last_name) as patient_name, COALESCE(SUM(pm.amount_paid), 0) as paid_amount")
            ->limit(200)->get();

        $payments = DB::table('payment as pm')
            ->join('bill as b', 'b.bill_id', '=', 'pm.bill_id')
            ->join('patient as p', 'p.patient_id', '=', 'b.patient_id')
            ->orderByDesc('pm.payment_date')
            ->selectRaw("pm.*, (p.first_name||' '||p.last_name) as patient_name")
            ->limit(100)->get();

        $stats = [
            'total_amount'    => (float) Bill::sum('total_amount'),
            'unpaid'          => Bill::where('status', 'unpaid')->count(),
            'partially_paid'  => Bill::where('status', 'partially_paid')->count(),
            'paid'            => Bill::where('status', 'paid')->count(),
        ];

        return view('billing.index', [
            'bills' => $bills, 'payments' => $payments, 'status' => $status, 'stats' => $stats,
        ]);
    }

    public function create()
    {
        return view('billing.form');
    }

    public function store(Request $request)
    {
        $data = $request->validate(['patient_id' => 'required|exists:patient,patient_id']);
        $bill = Bill::create(['patient_id' => $data['patient_id'], 'generated_by' => Auth::user()->staff_id]);
        $this->audit->log('bill.create', 'bill', $bill->bill_id);

        return redirect('/bills')->with('success', "Bill {$bill->bill_id} created.");
    }

    public function show(string $id)
    {
        $bill = Bill::with(['items', 'payments', 'patient'])->findOrFail($id);
        $paid = $bill->paidAmount();

        return view('billing.show', [
            'bill' => $bill,
            'paid' => $paid,
            'balance' => (float) $bill->total_amount - $paid,
        ]);
    }

    public function addItemForm(string $id)
    {
        $bill = Bill::with('patient')->findOrFail($id);

        return view('billing.item-form', compact('bill'));
    }

    public function addItem(Request $request, string $id)
    {
        $bill = Bill::findOrFail($id);
        $data = $request->validate([
            'item_type'   => 'required|in:service,medicine,lab_test,procedure,room',
            'description' => 'nullable|string|max:255',
            'quantity'    => 'required|integer|min:1',
            'unit_price'  => 'required|numeric|min:0',
        ]);
        BillItem::create([
            'bill_item_id' => 'BI' . strtoupper(Str::random(8)),
            'bill_id' => $bill->bill_id,
            'item_type' => $data['item_type'],
            'description' => $data['description'] ?? null,
            'quantity' => $data['quantity'],
            'unit_price' => $data['unit_price'],
        ]);
        $bill->recomputeTotal();
        $this->audit->log('bill.add_item', 'bill_item', $bill->bill_id);

        return redirect('/bills')->with('success', 'Item added.')->with('reopen_bill', $bill->bill_id);
    }

    public function payForm(string $id)
    {
        $bill = Bill::with('patient')->findOrFail($id);
        $paid = $bill->paidAmount();

        return view('billing.pay-form', ['bill' => $bill, 'paid' => $paid, 'balance' => (float) $bill->total_amount - $paid]);
    }

    public function pay(Request $request, string $id)
    {
        $bill = Bill::findOrFail($id);
        $data = $request->validate([
            'amount_paid' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,card,online',
            'transaction_reference' => 'nullable|string|max:100',
        ]);
        Payment::create([
            'payment_id' => 'PAY' . strtoupper(Str::random(8)),
            'bill_id' => $bill->bill_id,
            'received_by' => Auth::user()->staff_id,
            'payment_method' => $data['payment_method'],
            'amount_paid' => $data['amount_paid'],
            'transaction_reference' => $data['transaction_reference'] ?? null,
        ]);
        $paid = $bill->paidAmount();
        $bill->status = $paid >= (float) $bill->total_amount ? 'paid' : ($paid > 0 ? 'partially_paid' : 'unpaid');
        $bill->save();
        $this->audit->log('payment.create', 'payment', $bill->bill_id, ['amount' => $data['amount_paid'], 'method' => $data['payment_method']]);

        return redirect('/bills')->with('success', 'Payment recorded.');
    }

    public function payments()
    {
        $rows = DB::table('payment as pm')
            ->join('bill as b', 'b.bill_id', '=', 'pm.bill_id')
            ->join('patient as p', 'p.patient_id', '=', 'b.patient_id')
            ->orderByDesc('pm.payment_date')
            ->selectRaw("pm.*, (p.first_name||' '||p.last_name) as patient_name")
            ->limit(200)->get();

        return view('misc.table', [
            'title' => 'Payment History',
            'columns' => ['payment_id' => 'Payment', 'bill_id' => 'Bill', 'patient_name' => 'Patient',
                'payment_method' => 'Method', 'amount_paid' => 'Amount', 'payment_date' => 'Date'],
            'rows' => $rows,
        ]);
    }
}
