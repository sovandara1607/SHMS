@props(['name' => 'staff_id', 'placeholder' => 'Search by staff name or ID...', 'required' => false])

<div
    x-data="{
        query: '',
        selectedId: null,
        selectedRole: null,
        selectedDepartment: null,
        results: [],
        open: false,
        search() {
            this.selectedId = null;
            if (this.query.length < 2) { this.results = []; this.open = false; return; }
            fetch('/staff/search?q=' + encodeURIComponent(this.query))
                .then(r => r.json())
                .then(data => { this.results = data; this.open = data.length > 0; });
        },
        select(s) {
            this.selectedId = s.id;
            this.selectedRole = s.role;
            this.selectedDepartment = s.department;
            this.query = s.label;
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
        x-show="open" x-on:click.outside="open = false" x-transition style="display: none;"
        class="absolute z-10 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border border-manila/60 bg-paper-card shadow-lg"
    >
        <template x-for="s in results" :key="s.id">
            <button type="button" x-on:click="select(s)" class="block w-full px-3.5 py-2 text-left text-sm hover:bg-slate-50" x-text="s.label"></button>
        </template>
    </div>
    <div x-show="selectedId" x-transition style="display: none;" class="mt-2 grid grid-cols-2 gap-2 text-sm">
        <div><span class="text-xs text-slate-500">Role</span><p class="text-slate-900" x-text="selectedRole ?? '—'"></p></div>
        <div><span class="text-xs text-slate-500">Department</span><p class="text-slate-900" x-text="selectedDepartment ?? '—'"></p></div>
    </div>
</div>
