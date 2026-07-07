<x-modal-header :title="$order->test_order_id" :subtitle="$order->test_name" />
<div class="space-y-3 px-6 py-5 text-sm">
    <div class="flex justify-between"><span class="text-slate-500">Patient</span><span class="font-medium text-slate-900">{{ $order->patient_name }} ({{ $order->patient_id }})</span></div>
    <div class="flex justify-between"><span class="text-slate-500">Test Type</span><span class="text-slate-900">{{ $order->test_name }}</span></div>
    <div class="flex justify-between"><span class="text-slate-500">Ordered By</span><span class="text-slate-900">{{ $order->doctor_name }}</span></div>
    <div class="flex justify-between"><span class="text-slate-500">Technician</span><span class="text-slate-900">{{ $order->technician_name ?: '—' }}</span></div>
    <div class="flex justify-between"><span class="text-slate-500">Ordered Date</span><span class="text-slate-900">{{ $order->order_date }}</span></div>
    <div class="flex justify-between"><span class="text-slate-500">Status</span><x-badge :status="$order->status" /></div>
</div>
<div class="flex justify-end border-t border-slate-100 px-6 py-4">
    <x-button variant="secondary" x-on:click="show = false">Close</x-button>
</div>
