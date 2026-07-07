<x-modal-header :title="'Batch ' . $batch->batch_id" :subtitle="$medicine?->medicine_name" />
<div class="space-y-3 px-6 py-5 text-sm">
    <div class="flex justify-between"><span class="text-slate-500">Batch ID</span><span class="font-medium text-slate-900">{{ $batch->batch_id }}</span></div>
    <div class="flex justify-between"><span class="text-slate-500">Medicine Name</span><span class="text-slate-900">{{ $medicine?->medicine_name }}</span></div>
    <div class="flex justify-between"><span class="text-slate-500">Batch Number</span><span class="text-slate-900">{{ $batch->batch_number ?: '—' }}</span></div>
    <div class="flex justify-between"><span class="text-slate-500">Manufacture Date</span><span class="text-slate-900">{{ $batch->manufacture_date ?: '—' }}</span></div>
    <div class="flex justify-between"><span class="text-slate-500">Expiry Date</span><span class="text-slate-900">{{ $batch->expiry_date ?: '—' }}</span></div>
    <div class="flex justify-between"><span class="text-slate-500">Quantity</span><span class="text-slate-900">{{ $batch->quantity }}</span></div>
    <div class="flex justify-between"><span class="text-slate-500">Batch Status</span><x-badge :status="$batch->status" /></div>
</div>
<div class="flex justify-end border-t border-slate-100 px-6 py-4">
    <x-button variant="secondary" x-on:click="show = false">Close</x-button>
</div>
