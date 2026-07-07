<x-modal-header title="Update Order Status" :subtitle="$order->test_order_id . ' · ' . $order->test_name" />
<form method="post" action="/lab-orders/{{ $order->test_order_id }}/status" class="px-6 py-5" x-data="{ status: '{{ $order->status }}' }">
    @csrf
    <input type="hidden" name="status" x-model="status">
    <div class="space-y-2">
        @foreach(['pending' => 'Pending', 'in_progress' => 'In Progress', 'completed' => 'Completed'] as $value => $label)
            <button type="button" @click="status = '{{ $value }}'"
                    :class="status === '{{ $value }}' ? 'border-blue-500 ring-2 ring-blue-500/20' : 'border-slate-200'"
                    class="flex w-full items-center justify-between rounded-lg border px-4 py-2.5 text-sm">
                <span class="text-slate-700">{{ $label }}</span>
                <x-badge :status="$value" />
            </button>
        @endforeach
    </div>
    <div class="mt-5 flex justify-end gap-2">
        <x-button variant="secondary" type="button" x-on:click="show = false">Cancel</x-button>
        <x-button variant="primary" type="submit">Update</x-button>
    </div>
</form>
