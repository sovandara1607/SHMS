@php
$mode = $mode ?? 'create';
$result = $result ?? null;
$action = $mode === 'create' ? '/lab-results' : '/lab-results/' . $result->test_result_id;
$target = $mode === 'create' ? '/lab-results/create/' . $order->test_order_id : '/lab-results/' . $result->test_result_id . '/edit';
@endphp

<x-modal-header :title="$mode === 'create' ? 'Add Test Result' : 'Edit Test Result'" />
<form id="lab-result-form" method="post" action="{{ $action }}" class="px-6 py-5">
    @csrf
    @if($mode === 'edit')@method('PUT')@endif
    <input type="hidden" name="_modal_target" value="{{ $target }}">
    @if($mode === 'create')<input type="hidden" name="test_order_id" value="{{ $order->test_order_id }}">@endif

    <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Test Order Information</p>
    <dl class="mb-4 grid grid-cols-2 gap-y-1 text-sm">
        <dt class="text-slate-500">Test Order ID</dt><dd class="text-right text-slate-900">{{ $order->test_order_id }}</dd>
        <dt class="text-slate-500">Patient Name</dt><dd class="text-right text-slate-900">{{ $order->patient_name }}</dd>
        <dt class="text-slate-500">Test Name</dt><dd class="text-right text-slate-900">{{ $order->test_name }}</dd>
        <dt class="text-slate-500">Doctor</dt><dd class="text-right text-slate-900">{{ $order->doctor_name }}</dd>
        <dt class="text-slate-500">Assigned Lab Technician</dt><dd class="text-right text-slate-900">{{ $order->technician_name ?: '—' }}</dd>
    </dl>

    <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Result Details</p>
    <div class="mb-4">
        <label class="mb-1.5 block text-sm font-medium text-slate-700">Result Value / Details *</label>
        <textarea name="result_value" rows="3" required placeholder="Enter result values and findings..."
                  class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">{{ old('result_value', $result->result_value ?? '') }}</textarea>
    </div>
    <div class="mb-4">
        <label class="mb-1.5 block text-sm font-medium text-slate-700">Result Status *</label>
        <select name="result_status" required class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
            @foreach(['normal' => 'Normal', 'abnormal' => 'Abnormal'] as $v => $l)
                <option value="{{ $v }}" @selected(old('result_status', $result->result_status ?? '') === $v)>{{ $l }}</option>
            @endforeach
        </select>
    </div>
    <div class="mb-4">
        <label class="mb-1.5 block text-sm font-medium text-slate-700">Remarks</label>
        <textarea name="remarks" rows="2" placeholder="Additional remarks or clinical notes..."
                  class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">{{ old('remarks', $result->remarks ?? '') }}</textarea>
    </div>

    <div class="rounded-lg bg-blue-50 px-4 py-3 text-xs text-blue-700">
        <p class="font-semibold uppercase tracking-wider">System-Generated Information</p>
        <p class="mt-1">Entered By: {{ auth()->user()->displayName() }}</p>
        <p>{{ $mode === 'create' ? 'Entered At' : 'Updated At' }}: {{ now()->format('Y-m-d H:i') }}</p>
    </div>
</form>
<div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4">
    <x-button variant="secondary" x-on:click="show = false">Cancel</x-button>
    <x-button variant="primary" type="submit" form="lab-result-form">{{ $mode === 'create' ? 'Save Result' : 'Save Changes' }}</x-button>
</div>
