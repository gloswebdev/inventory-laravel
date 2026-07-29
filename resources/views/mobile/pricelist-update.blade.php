@extends('layouts.mobile')

@section('content')
<div x-data="mobilePricelistUpdateApp()">

    {{-- Page Header --}}
    <div class="mb-5 animate-in fade-in slide-in-from-top duration-500">
        <div class="flex items-center justify-between gap-3 mb-1">
            <div class="flex items-center gap-3">
                <a href="{{ route('mobile.dashboard') }}"
                   class="w-9 h-9 rounded-xl bg-white/60 border border-white flex items-center justify-center text-slate-600 active:scale-90 transition-transform">
                    <i class="fas fa-chevron-left text-sm"></i>
                </a>
                <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center shadow-lg shadow-amber-200">
                    <i class="fas fa-cloud-arrow-up text-white text-base"></i>
                </div>
                <div>
                    <h2 class="text-xl font-900 text-slate-800 tracking-tighter leading-none">Pricelist Update</h2>
                    <p class="text-[9px] font-black uppercase tracking-widest text-slate-400 mt-0.5">Push new rates to Algebra ERP</p>
                </div>
            </div>

            <button @click="showHistory = true"
                    class="w-9 h-9 rounded-xl bg-white/60 border border-white flex items-center justify-center text-slate-500 active:scale-90 transition-transform">
                <i class="fas fa-clock-rotate-left text-sm"></i>
            </button>
        </div>
    </div>

    {{-- Rate Type Selector --}}
    <div class="mb-4 bg-gradient-to-r from-amber-50 to-orange-50 border border-amber-200/70 rounded-3xl p-4">
        <label class="block text-[9px] font-black text-amber-700 uppercase tracking-widest mb-1.5">Price List / Branch</label>
        <select x-model="priceList" @change="clearEdits()"
                class="w-full py-3 px-4 bg-white border border-amber-200 rounded-2xl text-sm font-black text-slate-800 outline-none focus:ring-2 focus:ring-amber-400 cursor-pointer">
            @foreach($priceLists as $key => $meta)
                <option value="{{ $key }}">{{ $key }} — {{ $meta['label'] }}</option>
            @endforeach
        </select>
    </div>

    {{-- Active Filter Chip (tap the floating search button to change) --}}
    @if(request()->anyFilled(['search', 'group1']))
    <div class="mb-4 flex items-center gap-2 flex-wrap animate-in fade-in slide-in-from-bottom duration-500 delay-100">
        @if(request('search'))
        <span class="inline-flex items-center gap-1.5 px-3 py-2 bg-white/70 border border-white/80 rounded-2xl text-xs font-bold text-slate-700 shadow-sm">
            <i class="fas fa-search text-slate-400 text-[10px]"></i> {{ request('search') }}
        </span>
        @endif
        @if(request('group1'))
        <span class="inline-flex items-center gap-1.5 px-3 py-2 bg-white/70 border border-white/80 rounded-2xl text-xs font-bold text-slate-700 shadow-sm">
            {{ request('group1') }}
        </span>
        @endif
        <a href="{{ route('mobile.costing.pricelist-update') }}" class="px-3 py-2 bg-rose-50 border border-rose-200 text-rose-600 rounded-2xl text-[10px] font-black uppercase flex items-center justify-center">
            Clear
        </a>
    </div>
    @endif

    {{-- Item Cards --}}
    <div class="space-y-3 mb-6" id="pricelist-items-container">
        @include('mobile.partials.pricelist-update-items', ['pricelists' => $pricelists])
    </div>

    {{-- Infinite Scroll Sentinel --}}
    <div x-ref="sentinel" class="mb-28 py-4 flex items-center justify-center" x-show="hasMore">
        <i class="fas fa-spinner fa-spin text-amber-500 text-lg" x-show="loadingMore"></i>
    </div>
    <div x-show="!hasMore && !loadingMore" class="mb-28 py-4 text-center text-[10px] font-black text-slate-300 uppercase tracking-widest">
        End of list
    </div>

    {{-- Floating Action Toolbar --}}
    <div class="fixed bottom-28 right-5 z-40 flex flex-col items-end gap-2.5">
        {{-- Clear pill, only when there are pending edits --}}
        <button x-show="selectedCount > 0" x-cloak x-transition @click="clearEdits()"
                class="px-4 py-2 rounded-full bg-slate-800 shadow-xl text-[10px] font-black text-white uppercase tracking-widest active:scale-90 transition-transform">
            Clear <span x-text="selectedCount"></span>
        </button>

        {{-- Search + Push pill bar --}}
        <div class="flex items-center gap-1 bg-white rounded-full border border-slate-200 shadow-2xl shadow-slate-900/20 p-1.5">
            {{-- Search FAB --}}
            <button @click="showSearchSheet = true"
                    class="relative w-11 h-11 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-600 active:scale-90 transition-transform">
                <i class="fas fa-search text-sm"></i>
                @if(request()->anyFilled(['search', 'group1']))
                <span class="absolute -top-0.5 -right-0.5 w-3 h-3 rounded-full bg-amber-500 border-2 border-white"></span>
                @endif
            </button>

            {{-- Push / Update FAB --}}
            <button @click="pushToErp()" :disabled="pushing"
                    class="relative w-14 h-14 rounded-full bg-gradient-to-br from-amber-500 to-orange-600 text-white shadow-lg shadow-amber-900/30 flex items-center justify-center active:scale-90 transition-transform disabled:opacity-60">
                <i class="fas fa-cloud-arrow-up text-lg" :class="pushing ? 'fa-spin' : ''"></i>
                <span x-show="selectedCount > 0" x-cloak x-text="selectedCount"
                      class="absolute -top-1.5 -right-1.5 min-w-[1.25rem] h-5 px-1 rounded-full bg-rose-500 border-2 border-white text-white text-[10px] font-black flex items-center justify-center"></span>
            </button>
        </div>
    </div>

    {{-- Search & Category Filter Sheet --}}
    <div x-show="showSearchSheet" x-cloak class="fixed inset-0 z-50 flex items-end">
        <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="showSearchSheet = false"></div>
        <div class="relative bg-white rounded-t-3xl w-full p-5 pb-8" x-transition>
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xs font-black text-slate-800 uppercase tracking-widest">Search &amp; Filter</h3>
                <button @click="showSearchSheet = false" class="text-slate-400"><i class="fas fa-times"></i></button>
            </div>
            <form method="GET" action="{{ route('mobile.costing.pricelist-update') }}" class="space-y-3">
                <div class="relative">
                    <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="Search name, code, composition..."
                           class="w-full pl-11 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-bold text-slate-800 placeholder:text-slate-400 outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400 transition-all">
                </div>

                <select name="group1"
                        class="w-full py-3 px-4 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-bold text-slate-800 outline-none focus:ring-2 focus:ring-amber-400 cursor-pointer">
                    <option value="">All Categories (Group 1)</option>
                    @foreach($group1List as $grp)
                    <option value="{{ $grp }}" {{ request('group1') === $grp ? 'selected' : '' }}>{{ $grp }}</option>
                    @endforeach
                </select>

                <div class="flex gap-2 pt-1">
                    <button type="submit" class="flex-1 py-3 rounded-2xl bg-amber-500 text-white text-xs font-black uppercase shadow-md shadow-amber-200">
                        Apply
                    </button>
                    @if(request()->anyFilled(['search', 'group1']))
                    <a href="{{ route('mobile.costing.pricelist-update') }}" class="px-5 py-3 bg-rose-50 border border-rose-200 text-rose-600 rounded-2xl text-xs font-black uppercase flex items-center justify-center">
                        Clear
                    </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- History Sheet --}}
    <div x-show="showHistory" x-cloak class="fixed inset-0 z-50 flex items-end">
        <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="showHistory = false; detail = null"></div>
        <div class="relative bg-white rounded-t-3xl w-full max-h-[85vh] flex flex-col overflow-hidden" x-transition>
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="text-xs font-black text-slate-800 uppercase tracking-widest">Push History</h3>
                <button @click="showHistory = false; detail = null" class="text-slate-400"><i class="fas fa-times"></i></button>
            </div>
            <div class="overflow-y-auto flex-grow p-4 space-y-2">
                @if($recentPushes->isEmpty())
                    <p class="text-center text-slate-400 font-bold py-10 text-sm">No pushes recorded yet.</p>
                @else
                    @foreach($recentPushes as $push)
                    <div class="bg-slate-50 border border-slate-100 rounded-2xl p-3" @click="loadDetail({{ $push->id }})">
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <div class="text-xs font-black text-slate-800">{{ $push->price_list }}</div>
                                <div class="text-[9px] font-bold text-slate-400 mt-0.5">{{ $push->created_at->format('d M Y, h:i A') }} · {{ $push->pushed_by }}</div>
                            </div>
                            <div class="text-right">
                                <span class="inline-block px-2 py-0.5 rounded-lg text-[8px] font-black border
                                    @if($push->status === 'success') bg-emerald-50 text-emerald-700 border-emerald-100
                                    @elseif($push->status === 'partial') bg-amber-50 text-amber-700 border-amber-100
                                    @else bg-rose-50 text-rose-700 border-rose-100 @endif">
                                    {{ strtoupper($push->status) }}
                                </span>
                                <div class="text-[9px] font-black text-slate-500 mt-0.5">
                                    {{ $push->total_success }}/{{ $push->total_items }}
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                @endif

                <template x-if="detail">
                    <div class="bg-white border border-amber-200 rounded-2xl p-3 space-y-2">
                        <div class="flex items-center justify-between">
                            <div class="text-[9px] font-black text-slate-500 uppercase tracking-widest">
                                Run #<span x-text="detail.id"></span> — <span x-text="detail.price_list"></span>
                            </div>
                            <button @click="detail = null" class="text-[9px] font-black text-slate-400">Close</button>
                        </div>
                        <template x-if="detail.error_message">
                            <p class="text-[10px] font-bold text-rose-700 bg-rose-50 border border-rose-100 rounded-xl px-2.5 py-2" x-text="detail.error_message"></p>
                        </template>
                        <template x-for="item in detail.items" :key="item.id">
                            <div class="flex items-center justify-between gap-2 py-1.5 border-b border-slate-100 last:border-0">
                                <div class="min-w-0">
                                    <div class="font-mono text-[10px] font-bold text-indigo-600" x-text="item.user_code"></div>
                                    <div class="text-[9px] font-semibold text-slate-400 truncate" x-text="item.item_name || '—'"></div>
                                </div>
                                <div class="text-[10px] font-black shrink-0">
                                    <span class="text-slate-400" x-text="item.old_value !== null ? '₹' + formatMoney(item.old_value) : '—'"></span>
                                    <i class="fas fa-arrow-right text-slate-300 mx-1 text-[8px]"></i>
                                    <span class="text-slate-800" x-text="'₹' + formatMoney(item.new_value)"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </div>

</div>

<script>
function mobilePricelistUpdateApp() {
    return {
        priceList: 'Sp_Rate1',
        pushing: false,
        showHistory: false,
        showSearchSheet: false,
        detail: null,
        edits: {},
        currentRates: @json($rateMatrix),
        page: {{ $pricelists->currentPage() }},
        hasMore: {{ $pricelists->hasMorePages() ? 'true' : 'false' }},
        loadingMore: false,

        init() {
            const observer = new IntersectionObserver((entries) => {
                if (entries[0].isIntersecting) this.loadMore();
            }, { rootMargin: '200px' });
            observer.observe(this.$refs.sentinel);
        },

        async loadMore() {
            if (!this.hasMore || this.loadingMore) return;
            this.loadingMore = true;
            try {
                const params = new URLSearchParams(window.location.search);
                params.set('page', this.page + 1);
                const resp = await fetch('{{ route('mobile.costing.pricelist-update.items') }}?' + params.toString(), {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await resp.json();

                Object.assign(this.currentRates, data.rates);
                this.hasMore = data.has_more;
                this.page = this.page + 1;

                const container = document.getElementById('pricelist-items-container');
                const before = new Set(container.children);
                container.insertAdjacentHTML('beforeend', data.html);
                container.querySelectorAll(':scope > *').forEach(el => {
                    if (!before.has(el)) window.Alpine.initTree(el);
                });
            } catch (e) {
                this.hasMore = false;
            } finally {
                this.loadingMore = false;
            }
        },

        get selectedCount() {
            return this.payloadItems().length;
        },

        formatMoney(v) {
            return Number(v || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        isEdited(code) {
            return !isNaN(parseFloat(this.edits[code]));
        },

        clearEdits() {
            this.edits = {};
        },

        payloadItems() {
            return Object.keys(this.edits)
                .map(code => ({ user_code: code, new_value: parseFloat(this.edits[code]) }))
                .filter(i => !isNaN(i.new_value));
        },

        async pushToErp() {
            const items = this.payloadItems();
            if (items.length === 0) {
                alert('Enter a new rate for at least one item.');
                return;
            }
            if (!confirm(`Push ${items.length} item(s) to ERP for ${this.priceList}? This updates live prices.`)) return;

            this.pushing = true;
            try {
                const resp = await fetch('{{ route('mobile.costing.pricelist-update.push') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ price_list: this.priceList, items: items })
                });
                const data = await resp.json();
                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert(data.message || 'Failed to push prices.');
                }
            } catch (e) {
                alert('Network error while pushing prices.');
            } finally {
                this.pushing = false;
            }
        },

        async loadDetail(id) {
            this.detail = null;
            try {
                const resp = await fetch('{{ url('mobile/costing-pricelist-update/history') }}/' + id, {
                    headers: { 'Accept': 'application/json' }
                });
                this.detail = await resp.json();
            } catch (e) {
                alert('Could not load push detail.');
            }
        }
    };
}
</script>
@endsection
