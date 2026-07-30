<x-modal-header title="Medical Record History" :subtitle="$record->medical_record_id . ' — ' . ($record->patient?->fullName() ?? $record->patient_id)" />

<div class="max-h-[75vh] overflow-y-auto px-6 py-5">
    @if($versions->isEmpty())
        <p class="text-sm text-slate-400">No version history recorded for this medical record yet.</p>
    @else
        <ol class="space-y-4 border-l border-slate-200 pl-5">
            @foreach($versions as $v)
                @php
                    $staffId = data_get($v, 'created_by');
                    $actor = $doctors->first(fn ($d) => $d->staff_id === $staffId);
                    $actorName = $actor?->name ?? $staffId ?? '—';
                    $snapshot = (array) data_get($v, 'snapshot', []);
                    $type = data_get($v, 'type', 'update');
                @endphp
                <li class="relative">
                    <span class="absolute -left-[27px] top-1 h-3 w-3 rounded-full border-2 border-white {{ $type === 'original' ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                    <div class="rounded-lg border border-manila/60 bg-paper-card p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">v{{ data_get($v, 'version') }}</span>
                                <span class="text-xs font-semibold uppercase tracking-wider {{ $type === 'original' ? 'text-emerald-600' : 'text-amber-600' }}">
                                    {{ $type === 'original' ? 'Original' : 'Adjustment' }}
                                </span>
                            </div>
                            <span class="text-xs text-slate-400">{{ data_get($v, 'created_at') }}</span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">By {{ $actorName }}</p>
                        @if(data_get($v, 'reason'))
                            <p class="mt-2 text-sm text-slate-700"><span class="font-medium">Reason:</span> {{ data_get($v, 'reason') }}</p>
                        @endif
                        @if(!empty($snapshot))
                            <div class="mt-3 grid grid-cols-1 gap-2 rounded-lg bg-slate-50/70 p-3 text-sm sm:grid-cols-3">
                                <div><p class="text-xs text-slate-400">Symptoms</p><p class="text-slate-700">{{ $snapshot['symptoms'] ?? '—' }}</p></div>
                                <div><p class="text-xs text-slate-400">Diagnosis</p><p class="text-slate-700">{{ $snapshot['diagnosis'] ?? '—' }}</p></div>
                                <div><p class="text-xs text-slate-400">Treatment Notes</p><p class="text-slate-700">{{ $snapshot['treatment_notes'] ?? '—' }}</p></div>
                            </div>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</div>

<div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4">
    <x-button variant="secondary" x-on:click="show = false">Close</x-button>
</div>
