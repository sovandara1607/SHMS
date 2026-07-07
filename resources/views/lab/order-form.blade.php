<x-modal-header title="Order Lab Test" />
<form id="lab-order-form" method="post" action="/lab-orders" class="space-y-4 px-6 py-5">
    @csrf
    <input type="hidden" name="_modal_target" value="/lab-orders/create">
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Patient ID *</label>
            <input name="patient_id" required value="{{ old('patient_id') }}" placeholder="P-00842" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>
    </div>
    <div>
        <label class="mb-1.5 block text-sm font-medium text-slate-700">Test Type *</label>
        <input name="test_name" required value="{{ old('test_name') }}" placeholder="e.g. Blood Test, CT Scan, X-ray" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
    </div>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Ordered By (Doctor) *</label>
            <select name="doctor_id" required class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                <option value="">— select —</option>
                @foreach($doctors as $d)
                    <option value="{{ $d->doctor_id }}" @selected(old('doctor_id') === $d->doctor_id)>{{ $d->name() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Assigned Lab Technician</label>
            <select name="technician_id" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                <option value="">— unassigned —</option>
                @foreach($technicians as $t)
                    <option value="{{ $t->technician_id }}" @selected(old('technician_id') === $t->technician_id)>{{ $t->name() }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div>
        <label class="mb-1.5 block text-sm font-medium text-slate-700">Priority</label>
        <select name="priority" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
            <option value="routine">Routine</option>
            <option value="urgent">Urgent</option>
            <option value="stat">STAT</option>
        </select>
    </div>
    <div>
        <label class="mb-1.5 block text-sm font-medium text-slate-700">Clinical Notes</label>
        <textarea name="notes" rows="2" placeholder="Reason for test, clinical context..." class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"></textarea>
    </div>
</form>
<div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4">
    <x-button variant="secondary" x-on:click="show = false">Cancel</x-button>
    <x-button variant="primary" type="submit" form="lab-order-form">Order Test</x-button>
</div>
