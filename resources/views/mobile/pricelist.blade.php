@extends('layouts.mobile')

@section('content')
<div x-data="mobilePricelistApp()" x-init="init()">

    {{-- Page Header --}}
    <div class="mb-5 animate-in fade-in slide-in-from-top duration-500">
        <div class="flex items-center justify-between gap-3 mb-1">
            <div class="flex items-center gap-3">
                <a href="{{ route('mobile.dashboard') }}"
                   class="w-9 h-9 rounded-xl bg-white/60 border border-white flex items-center justify-center text-slate-600 active:scale-90 transition-transform">
                    <i class="fas fa-chevron-left text-sm"></i>
                </a>
                <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center shadow-lg shadow-amber-200">
                    <i class="fas fa-tags text-white text-base"></i>
                </div>
                <div>
                    <h2 class="text-xl font-900 text-slate-800 tracking-tighter leading-none">Pricelist Master</h2>
                    <p class="text-[9px] font-black uppercase tracking-widest text-slate-400 mt-0.5">Synced from Product Master ERP API</p>
                </div>
            </div>

            @if(Auth::user()->hasPermission('mobile_costing_pricelist', 'sync') || Auth::user()->hasPermission('costing_pricelist', 'view'))
            <button @click="syncPrices()" :disabled="syncing"
                    class="px-3 py-2 rounded-2xl bg-amber-500 text-white flex items-center gap-2 shadow-md shadow-amber-200 active:scale-90 transition-transform text-xs font-black uppercase">
                <i class="fas fa-sync" :class="syncing ? 'fa-spin' : ''"></i>
                <span x-text="syncing ? 'Syncing...' : 'ERP Sync'"></span>
            </button>
            @endif
        </div>
    </div>

    {{-- Search & Category Filter --}}
    <div class="mb-4 animate-in fade-in slide-in-from-bottom duration-500 delay-100">
        <div class="space-y-2">
            <div class="flex gap-2">
                <div class="relative flex-1">
                    <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                    <input type="text" x-model.debounce.300ms="search" @keydown.enter="fetchResults()"
                           placeholder="Search name, code, composition..."
                           class="w-full pl-11 pr-10 py-3.5 bg-white/70 backdrop-blur-xl border border-white/80 rounded-2xl text-sm font-bold text-slate-800 placeholder:text-slate-400 outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400 transition-all shadow-sm">
                    <button x-show="search" @click="search = ''; fetchResults()" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>
                <button @click="fetchResults()" class="px-5 py-3.5 bg-amber-500 text-white rounded-2xl text-xs font-black uppercase flex items-center justify-center shadow-md shadow-amber-200 active:scale-95 transition-transform shrink-0">
                    Search
                </button>
            </div>

            <div class="flex gap-2">
                <select x-model="group1" @change="fetchResults()"
                        class="flex-1 py-3 px-4 bg-white/70 border border-white/80 rounded-2xl text-xs font-bold text-slate-800 outline-none focus:ring-2 focus:ring-amber-400 cursor-pointer">
                    <option value="">All Categories (Group 1)</option>
                    @foreach($group1List as $grp)
                    <option value="{{ $grp }}">{{ $grp }}</option>
                    @endforeach
                </select>

                <button x-show="search || group1" @click="clearFilters()" class="px-4 py-3 bg-rose-50 border border-rose-200 text-rose-600 rounded-2xl text-xs font-black uppercase flex items-center justify-center">
                    Clear
                </button>
            </div>
        </div>
    </div>

    {{-- Pricelist Items Cards --}}
    <div class="space-y-3 mb-6" id="pricelist-items-container">
        @include('mobile.partials.pricelist-items', ['pricelists' => $pricelists])
    </div>

    {{-- Infinite Scroll Sentinel / Loading Spinner --}}
    <div x-ref="sentinel" class="mb-28 py-4 flex items-center justify-center" x-show="hasMore">
        <i class="fas fa-spinner fa-spin text-amber-500 text-lg" x-show="loadingMore"></i>
    </div>
    <div x-show="!hasMore && !loadingMore" class="mb-28 py-4 text-center text-[10px] font-black text-slate-300 uppercase tracking-widest">
        End of list
    </div>

</div>

<script>
function mobilePricelistApp() {
    return {
        syncing: false,
        search: '{{ request('search', '') }}',
        group1: '{{ request('group1', '') }}',
        page: {{ $pricelists->currentPage() }},
        hasMore: {{ $pricelists->hasMorePages() ? 'true' : 'false' }},
        loadingMore: false,

        init() {
            const observer = new IntersectionObserver((entries) => {
                if (entries[0].isIntersecting) this.loadMore();
            }, { rootMargin: '200px' });
            observer.observe(this.$refs.sentinel);
        },

        async fetchResults() {
            this.page = 1;
            this.hasMore = true;
            this.loadingMore = true;
            
            // Update URL parameters without reloading for consistent back/refresh behavior
            const url = new URL(window.location.href);
            if (this.search) url.searchParams.set('search', this.search);
            else url.searchParams.delete('search');
            if (this.group1) url.searchParams.set('group1', this.group1);
            else url.searchParams.delete('group1');
            url.searchParams.set('page', 1);
            window.history.replaceState({}, '', url.toString());

            try {
                const resp = await fetch(url.toString(), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await resp.json();
                
                const container = document.getElementById('pricelist-items-container');
                container.innerHTML = data.html;
                
                // Initialize Alpine directives on newly loaded items if any
                container.querySelectorAll(':scope > *').forEach(el => {
                    if (window.Alpine) window.Alpine.initTree(el);
                });

                this.hasMore = data.has_more;
                this.page = 1;
            } catch (e) {
                console.error('Error searching pricelist:', e);
            } finally {
                this.loadingMore = false;
            }
        },

        clearFilters() {
            this.search = '';
            this.group1 = '';
            this.fetchResults();
        },

        async loadMore() {
            if (!this.hasMore || this.loadingMore) return;
            this.loadingMore = true;
            try {
                const url = new URL(window.location.href);
                if (this.search) url.searchParams.set('search', this.search);
                else url.searchParams.delete('search');
                if (this.group1) url.searchParams.set('group1', this.group1);
                else url.searchParams.delete('group1');
                url.searchParams.set('page', this.page + 1);

                const resp = await fetch(url.toString(), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await resp.json();

                this.hasMore = data.has_more;
                this.page = this.page + 1;

                const container = document.getElementById('pricelist-items-container');
                const before = new Set(container.children);
                container.insertAdjacentHTML('beforeend', data.html);
                container.querySelectorAll(':scope > *').forEach(el => {
                    if (!before.has(el) && window.Alpine) window.Alpine.initTree(el);
                });
            } catch (e) {
                console.error('Error loading more items:', e);
                this.hasMore = false;
            } finally {
                this.loadingMore = false;
            }
        },

        async syncPrices() {
            if (!confirm('Sync pricelist data from Product Master ERP API? This may take some time.')) return;
            this.syncing = true;
            try {
                const resp = await fetch('{{ route('mobile.costing.pricelist.sync') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json'
                    }
                });
                const data = await resp.json();
                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert(data.message || 'Sync failed.');
                }
            } catch(e) {
                alert('Error syncing pricelist.');
            } finally {
                this.syncing = false;
            }
        }
    };
}
</script>
@endsection
