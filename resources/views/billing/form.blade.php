<x-modal-header title="Create Bill" />
<form id="bill-form" method="post" action="/bills" class="space-y-4 px-6 py-5">
    @csrf
    <input type="hidden" name="_modal_target" value="/bills/create">
    <div>
        <label class="mb-1.5 block text-sm font-medium text-slate-700">Patient *</label>
        <x-patient-picker name="patient_id" required />
    </div>
</form>
<div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4">
    <x-button variant="secondary" x-on:click="show = false">Cancel</x-button>
    <x-button variant="primary" type="submit" form="bill-form">Create Bill</x-button>
</div>
