@props(['name' => 'medical_record_id', 'selectedId' => null, 'selectedLabel' => null, 'placeholder' => 'Search by patient name or record ID...', 'required' => false])

<div
    x-data="{
        query: @js($selectedLabel ?? ''),
        selectedId: @js($selectedId),
        results: [],
        open: false,
        search() {
            this.selectedId = null;
            if (this.query.length < 2) { this.results = []; this.open = false; return; }
            fetch('/medical-records/search?q=' + encodeURIComponent(this.query))
                .then(r => r.json())
                .then(data => { this.results = data; this.open = data.length > 0; });
        },
        select(r) {
            this.selectedId = r.id;
            this.query = r.label;
            this.open = false;
        },
        clear() {
            this.selectedId = null;
            this.query = '';
            this.results = [];
        },
    }"
    class="relative"
>
    <div class="relative">
        <input
            type="text" x-model="query" @input.debounce.300ms="search()" @focus="if (results.length) open = true"
            placeholder="{{ $placeholder }}" autocomplete="off"
            class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 pr-8 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
        >
        <button type="button" x-show="query" x-on:click="clear()" style="display: none;"
                class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">&times;</button>
    </div>
    <input type="hidden" name="{{ $name }}" x-model="selectedId" @if($required) required @endif>
    <div
        x-show="open" x-on:click.outside="open = false" x-transition style="display: none;"
        class="absolute z-10 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border border-manila/60 bg-paper-card shadow-lg"
    >
        <template x-for="r in results" :key="r.id">
            <button type="button" x-on:click="select(r)" class="block w-full px-3.5 py-2 text-left text-sm hover:bg-slate-50" x-text="r.label"></button>
        </template>
    </div>
</div>
