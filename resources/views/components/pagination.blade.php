@props(['paginator'])

@if($paginator->hasPages())
    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-4 py-3 text-sm">
        <p class="text-slate-500">
            Showing <span class="font-medium text-slate-700">{{ $paginator->firstItem() }}</span>
            to <span class="font-medium text-slate-700">{{ $paginator->lastItem() }}</span>
            of <span class="font-medium text-slate-700">{{ $paginator->total() }}</span> results
        </p>

        <div class="flex items-center gap-1">
            @if($paginator->onFirstPage())
                <span class="rounded-lg px-3 py-1.5 text-slate-300">Prev</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="rounded-lg px-3 py-1.5 text-slate-600 hover:bg-slate-50">Prev</a>
            @endif

            @foreach($paginator->getUrlRange(max(1, $paginator->currentPage() - 2), min($paginator->lastPage(), $paginator->currentPage() + 2)) as $page => $url)
                @if($page === $paginator->currentPage())
                    <span class="rounded-lg bg-blue-600 px-3 py-1.5 font-medium text-white">{{ $page }}</span>
                @else
                    <a href="{{ $url }}" class="rounded-lg px-3 py-1.5 text-slate-600 hover:bg-slate-50">{{ $page }}</a>
                @endif
            @endforeach

            @if($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="rounded-lg px-3 py-1.5 text-slate-600 hover:bg-slate-50">Next</a>
            @else
                <span class="rounded-lg px-3 py-1.5 text-slate-300">Next</span>
            @endif
        </div>
    </div>
@endif
