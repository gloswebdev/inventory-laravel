@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex items-center justify-between py-4">
        {{-- Previous Page Link --}}
        @if ($paginator->onFirstPage())
            <span class="w-12 h-12 rounded-2xl bg-slate-50 flex items-center justify-center text-slate-200 border border-slate-100 opacity-50 cursor-not-allowed">
                <i class="fas fa-chevron-left text-xs"></i>
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="w-12 h-12 rounded-2xl bg-white flex items-center justify-center text-slate-400 border border-slate-100 shadow-sm active:scale-90 transition-all">
                <i class="fas fa-chevron-left text-xs"></i>
            </a>
        @endif

        <div class="flex items-center gap-2">
            <span class="text-[10px] font-black text-slate-800 uppercase tracking-widest bg-white px-4 py-2 rounded-xl border border-slate-100 shadow-sm">
                Page {{ $paginator->currentPage() }}
            </span>
        </div>

        {{-- Next Page Link --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="w-12 h-12 rounded-2xl bg-white flex items-center justify-center text-slate-400 border border-slate-100 shadow-sm active:scale-90 transition-all">
                <i class="fas fa-chevron-right text-xs"></i>
            </a>
        @else
            <span class="w-12 h-12 rounded-2xl bg-slate-50 flex items-center justify-center text-slate-200 border border-slate-100 opacity-50 cursor-not-allowed">
                <i class="fas fa-chevron-right text-xs"></i>
            </span>
        @endif
    </nav>
@endif
