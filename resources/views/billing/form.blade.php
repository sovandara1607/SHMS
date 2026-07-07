<x-modal-header title="Create Bill" />
<form id="bill-form" method="post" action="/bills" class="space-y-4 px-6 py-5">
    @csrf
    <input type="hidden" name="_modal_target" value="/bills/create">
    <div>
        <label class="mb-1.5 block text-sm font-medium text-slate-700">Patient *</label>
        <select name="patient_id" required class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
            <option value="">— select —</option>
            @foreach($patients as $p)
                <option value="{{ $p->patient_id }}" @selected(old('patient_id') === $p->patient_id)>{{ $p->fullName() }} ({{ $p->patient_id }})</option>
            @endforeach
        </select>
    </div>
</form>
<div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4">
    <x-button variant="secondary" x-on:click="show = false">Cancel</x-button>
    <x-button variant="primary" type="submit" form="bill-form">Create Bill</x-button>
</div>
