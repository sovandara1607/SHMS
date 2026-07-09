@props(['name' => 'patient_id', 'selected' => null, 'placeholder' => 'Search by name or patient ID...', 'required' => false])

<div
    x-data="{
        query: @js($selected ? $selected->fullName() . ' (' . $selected->patient_id . ')' : ''),
        selectedId: @js($selected?->patient_id),
        results: [],
        open: false,
        search() {
            this.selectedId = null;
            if (this.query.length < 2) { this.results = []; this.open = false; return; }
            fetch('/patients/search?q=' + encodeURIComponent(this.query))
                .then(r => r.json())
                .then(data => { this.results = data; this.open = data.length > 0; });
        },
        select(p) {
            this.selectedId = p.id;
            this.query = p.label;
            this.open = false;
        },
    }"
    class="relative"
>
    <input
        type="text" x-model="query" @input.debounce.300ms="search()" @focus="if (results.length) open = true"
        placeholder="{{ $placeholder }}" autocomplete="off"
        class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
    >
    <input type="hidden" name="{{ $name }}" x-model="selectedId" @if($required) required @endif>
    <div
        x-show="open" x-on:click.outside="open = false" style="display: none;"
        class="absolute z-10 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg"
    >
        <template x-for="p in results" :key="p.id">
            <button type="button" x-on:click="select(p)" class="block w-full px-3.5 py-2 text-left text-sm hover:bg-slate-50" x-text="p.label"></button>
        </template>
    </div>
</div>
