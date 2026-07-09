@php
$isProtected = in_array($selected, ['super_admin', 'admin'], true);
$roleLabel = $roles[$selected] ?? $selected;
$totalGranted = $isProtected ? collect($catalog)->flatMap(fn ($c) => array_keys($c))->count() : count($granted);
@endphp

<div id="permissions-panel" class="rounded-xl border border-slate-200 bg-white p-5">
    @if($isProtected)
        <div class="mb-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <strong>Protected Role: {{ $roleLabel }}</strong> — {{ $roleLabel }} permissions cannot be fully modified and retains full system access.
        </div>
    @endif

    <form method="post" action="/roles-permissions/{{ $selected }}">
        @csrf
        <div class="mb-4 flex items-center justify-between">
            <div>
                <p class="font-semibold text-slate-900">Permissions — {{ $roleLabel }}</p>
                <p class="text-xs text-slate-400">{{ $totalGranted }} permissions granted</p>
            </div>
            @unless($isProtected)
                <div class="flex items-center gap-2">
                    <button type="button" class="text-xs font-medium text-blue-600 hover:underline" onclick="this.closest('form').querySelectorAll('input[type=checkbox]').forEach(c => c.checked = true)">Select All</button>
                    <button type="button" class="text-xs font-medium text-slate-500 hover:underline" onclick="this.closest('form').querySelectorAll('input[type=checkbox]').forEach(c => c.checked = false)">Clear</button>
                    <button type="button" class="text-xs font-medium text-slate-500 hover:underline"
                            title="Re-check the documented default permissions for this role. Review, then Save Changes to apply."
                            data-defaults="{{ json_encode(array_values($configDefaults)) }}"
                            onclick="const d = JSON.parse(this.dataset.defaults); this.closest('form').querySelectorAll('input[type=checkbox]').forEach(c => c.checked = d.includes(c.value))">Restore Defaults</button>
                    <x-button variant="primary" type="submit">Save Changes</x-button>
                </div>
            @endunless
        </div>

        <div class="grid grid-cols-2 gap-x-8 gap-y-5">
            @foreach($catalog as $group => $capabilities)
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $group }}</p>
                    <div class="space-y-1.5">
                        @foreach($capabilities as $capability => $label)
                            <label class="flex items-center gap-2 text-sm text-slate-700">
                                <input type="checkbox" name="capabilities[]" value="{{ $capability }}"
                                       @checked($isProtected || in_array($capability, $granted, true))
                                       @disabled($isProtected)
                                       class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        @unless($isProtected)
            <p class="mt-5 text-xs text-slate-400">Changes apply immediately after saving. Users with this role will be affected.</p>
            <div class="mt-2 flex justify-end">
                <x-button variant="primary" type="submit">Save Changes</x-button>
            </div>
        @endunless
    </form>
</div>
