<x-modal-header :title="$result->test_name" :subtitle="$result->test_result_id . ' · ' . $result->test_order_id" />
<div class="space-y-3 px-6 py-5 text-sm">
    <div class="flex justify-between"><span class="text-slate-500">Test Order ID</span><span class="font-medium text-slate-900">{{ $result->test_order_id }}</span></div>
    <div class="flex justify-between"><span class="text-slate-500">Patient</span><span class="text-slate-900">{{ $result->patient_name }} ({{ $result->patient_id }})</span></div>
    <div class="flex justify-between"><span class="text-slate-500">Test Type</span><span class="text-slate-900">{{ $result->test_name }}</span></div>
    <div class="flex justify-between"><span class="text-slate-500">Result Value</span><span class="text-slate-900">{{ $result->result_value }}</span></div>
    <div class="flex justify-between"><span class="text-slate-500">Result Status</span><x-badge :status="$result->result_status" /></div>
    <div class="flex justify-between"><span class="text-slate-500">Remarks</span><span class="text-slate-900">{{ $result->remarks ?: '—' }}</span></div>
    <div class="flex justify-between"><span class="text-slate-500">Entered At</span><span class="text-slate-900">{{ $result->entered_at }}</span></div>
</div>
<div class="flex justify-end border-t border-slate-100 px-6 py-4">
    <x-button variant="secondary" x-on:click="show = false">Close</x-button>
</div>
