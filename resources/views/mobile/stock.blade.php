@extends('layouts.mobile')

@section('content')
<div class="space-y-8 pb-10" x-data="stockApp()">
    <!-- Header Area -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-3xl font-900 text-slate-800 tracking-tighter">Live Stock</h2>
            <p class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400 mt-1">Real-time inventory insights</p>
        </div>
        <div class="flex items-center gap-2">
            @if(Auth::user()->hasPermission('mobile_stock', 'excel'))
            <button @click="exportStock('excel')" class="w-10 h-10 bg-white rounded-xl flex items-center justify-center text-emerald-500 border border-slate-100 shadow-sm active:scale-90 transition-all">
                <i class="fas fa-file-excel text-xs"></i>
            </button>
            @endif
            @if(Auth::user()->hasPermission('mobile_stock', 'pdf'))
            <button @click="exportStock('pdf')" class="w-10 h-10 bg-white rounded-xl flex items-center justify-center text-rose-500 border border-slate-100 shadow-sm active:scale-90 transition-all">
                <i class="fas fa-file-pdf text-xs"></i>
            </button>
            @endif
            <div class="w-10 h-10 grad-cyan rounded-xl flex items-center justify-center text-white shadow-lg shadow-cyan-100 border-2 border-white italic font-black text-[9px]" x-text="displayUnit === 'kg' ? 'KG' : 'BX'"></div>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="glass-premium p-6 rounded-[2.5rem] space-y-6 border border-white/50">
        @if(Auth::user()->hasFeature('mobile_stock', 'branch_select'))
        <div>
            <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-3 mb-2 block">Stock Location</label>
            <div class="relative group">
                <select 
                    x-model="selectedBranch" 
                    @change="refreshPage()"
                    class="w-full bg-slate-50/50 border-2 border-slate-100/50 rounded-2xl py-4 px-5 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500 appearance-none shadow-sm transition-all"
                >
                    @if(Auth::user()->role === 'admin' || count(Auth::user()->branches) > 1 || count(Auth::user()->branches) === 0)
                    <option value="">Global Stock (All Branches)</option>
                    @endif
                    @foreach($branches as $branch)
                    <option value="{{ $branch->code }}" {{ $selectedBranch == $branch->code ? 'selected' : '' }}>
                        {{ $branch->name }} ({{ $branch->code }})
                    </option>
                    @endforeach
                </select>
                <div class="absolute right-5 top-1/2 -translate-y-1/2 pointer-events-none text-slate-300">
                    <i class="fas fa-location-dot text-[10px]"></i>
                </div>
            </div>
        </div>
        @endif

        @if(Auth::user()->hasFeature('mobile_stock', 'category_filter'))
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-3 mb-2 block">Type</label>
                <div class="relative">
                    <select x-model="typeId" @change="refreshPage()" class="w-full bg-slate-50/50 border-2 border-slate-100/50 rounded-2xl py-4 px-5 text-[10px] font-bold text-slate-700 appearance-none shadow-sm">
                        <option value="">All Types</option>
                        @foreach($productTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->type_name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div>
                <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-3 mb-2 block">Category</label>
                <div class="relative">
                    <select x-model="rmType" @change="refreshPage()" class="w-full bg-slate-50/50 border-2 border-slate-100/50 rounded-2xl py-4 px-5 text-[10px] font-bold text-slate-700 appearance-none shadow-sm">
                        <option value="">All RM Types</option>
                        @foreach($rmTypes as $rm)
                            <option value="{{ $rm->value }}">{{ $rm->value }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
        @endif

        <div class="grid grid-cols-2 gap-4">
            @if(Auth::user()->hasFeature('mobile_stock', 'display_unit'))
            <div>
                <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-3 mb-2 block">Scale</label>
                <div class="flex bg-slate-100/50 p-1.5 rounded-2xl border border-slate-100/50">
                    <button 
                        @click="displayUnit = 'unit'; refreshPage()"
                        :class="displayUnit === 'unit' ? 'bg-white text-indigo-600 shadow-md' : 'text-slate-400'"
                        class="flex-1 py-2 text-[10px] font-black rounded-xl transition-all uppercase tracking-wider"
                    >Unit</button>
                    <button 
                        @click="displayUnit = 'kg'; refreshPage()"
                        :class="displayUnit === 'kg' ? 'bg-white text-indigo-600 shadow-md' : 'text-slate-400'"
                        class="flex-1 py-2 text-[10px] font-black rounded-xl transition-all uppercase tracking-wider"
                    >KG/LT</button>
                </div>
            </div>
            @endif
            @if(Auth::user()->hasFeature('mobile_stock', 'stock_filter'))
            <div>
                <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-3 mb-2 block">Filter</label>
                <div class="flex bg-slate-100/50 p-1.5 rounded-2xl border border-slate-100/50">
                    <button 
                        @click="stockFilter = 'all'; refreshPage()"
                        :class="stockFilter === 'all' ? 'bg-white text-indigo-600 shadow-md' : 'text-slate-400'"
                        class="flex-1 py-2 text-[10px] font-black rounded-xl transition-all uppercase tracking-wider"
                    >All</button>
                    <button 
                        @@click="stockFilter = 'ignore_zero'; refreshPage()"
                        :class="stockFilter === 'ignore_zero' ? 'bg-white text-indigo-600 shadow-md' : 'text-slate-400'"
                        class="flex-1 py-2 text-[10px] font-black rounded-xl transition-all uppercase tracking-wider"
                    >> 0</button>
                </div>
            </div>
            @endif
        </div>
    </div>

    <!-- Search Section -->
    @if(Auth::user()->hasFeature('mobile_stock', 'search'))
    <div class="glass-premium flex items-center px-6 py-5 rounded-[2rem] gap-4 shadow-sm border border-white/50 group focus-within:border-indigo-300 transition-all">
        <i class="fas fa-search text-slate-300 group-focus-within:text-indigo-400 transition-colors"></i>
        <input 
            type="text" 
            x-model="search" 
            @input.debounce.500ms="refreshPage()"
            placeholder="Find product name or code..." 
            class="bg-transparent border-none focus:ring-0 text-sm font-bold text-slate-700 w-full placeholder:text-slate-300"
        >
        <button x-show="search" @click="search = ''; refreshPage()" class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 hover:text-slate-600 transition-colors">
            <i class="fas fa-times text-[10px]"></i>
        </button>
    </div>
    @endif
    
    <!-- Results Section -->
    <div class="glass-premium overflow-hidden rounded-[2.5rem] border border-white/50">
        <div class="divide-y divide-slate-100">
            <template x-for="product in products" :key="product.id">
                <div class="p-6 flex items-center justify-between hover:bg-slate-50/50 transition-all active:scale-[0.98]">
                    <div class="flex-1 min-w-0 pr-4">
                        <div class="text-[13px] font-900 text-slate-800 truncate uppercase tracking-tight" x-text="product.name"></div>
                        <div class="flex items-center gap-2 mt-1.5">
                            <div class="px-2 py-0.5 bg-slate-100 text-slate-500 rounded text-[8px] font-black uppercase tracking-widest" x-text="product.pack_name"></div>
                            <div class="w-1 h-1 bg-slate-200 rounded-full"></div>
                            <div class="text-[9px] font-black text-indigo-400 uppercase tracking-widest" x-text="product.item_code"></div>
                        </div>
                    </div>
                    <div class="text-right shrink-0">
                        @php
                            // Note: In mobile, displayUnit affects what is stored in 'stocks'
                            // We'll calculate the limit check in Alpine.js
                        @endphp
                        <template x-if="displayUnit === 'kg'">
                            <div class="text-xl font-900 tracking-tighter" :class="stocks[product.id] <= (product.low_alert_quantity * (product.weight_multiplier || 1)) ? 'text-red-500 font-black' : (stocks[product.id] <= 0 ? 'text-slate-200' : 'text-slate-800')" x-text="Number.isInteger(stocks[product.id]) ? stocks[product.id] : parseFloat(stocks[product.id]).toFixed(2)"></div>
                        </template>
                        <template x-if="displayUnit !== 'kg'">
                            <div class="text-xl font-900 tracking-tighter" :class="stocks[product.id] <= (product.low_alert_quantity / (product.unit_box || 1)) ? 'text-red-500 font-black' : (stocks[product.id] <= 0 ? 'text-slate-200' : 'text-slate-800')" x-text="Number.isInteger(stocks[product.id]) ? stocks[product.id] : parseFloat(stocks[product.id]).toFixed(2)"></div>
                        </template>
                        <div class="text-[8px] font-black uppercase tracking-[0.1em] mt-0.5" :class="stocks[product.id] <= 0 ? 'text-slate-300' : 'text-indigo-500'" x-text="displayUnit === 'kg' ? (product.uom === 'Ltr' ? 'Units (LTR)' : 'Units (KG)') : 'Boxes'"></div>
                    </div>
                </div>
            </template>
            
            <div x-show="products.length === 0" class="p-16 text-center">
                <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4 border border-slate-100">
                    <i class="fas fa-magnifying-glass text-slate-300"></i>
                </div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest leading-loose">No inventory records found<br>for this search criteria.</p>
            </div>
        </div>

        <!-- Load More Section -->
        <div x-show="hasMore" class="p-6 border-t border-white/50 bg-slate-50/30">
            <button 
                @click="loadMore()" 
                :disabled="loading"
                class="w-full py-5 bg-white border-2 border-slate-100 rounded-3xl text-[10px] font-black uppercase tracking-widest text-indigo-600 hover:bg-indigo-50 active:scale-95 transition-all flex items-center justify-center gap-3 disabled:opacity-50 shadow-sm"
            >
                <template x-if="!loading">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-arrow-down-long animate-bounce text-indigo-400"></i>
                        <span>Reveal More Records</span>
                    </div>
                </template>
                <template x-if="loading">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-spinner fa-spin"></i>
                        <span>Syncing Data...</span>
                    </div>
                </template>
            </button>
        </div>
    </div>
</div>

<script>
    function stockApp() {
        return {
            search: '{{ request('search') }}',
            selectedBranch: '{{ $selectedBranch }}',
            displayUnit: '{{ $displayUnit ?? 'unit' }}',
            stockFilter: '{{ $stockFilter ?? 'all' }}',
            typeId: '{{ request('type_id') }}',
            rmType: '{{ request('rm_type') }}',
            products: @json($products),
            stocks: @json($stocks),
            hasMore: @json($hasMore),
            nextPage: 2,
            loading: false,

            refreshPage() {
                let url = new URL('{{ route('mobile.stock') }}');
                if (this.selectedBranch) url.searchParams.set('branch', this.selectedBranch);
                if (this.displayUnit !== 'unit') url.searchParams.set('display_unit', this.displayUnit);
                if (this.stockFilter !== 'all') url.searchParams.set('stock_filter', this.stockFilter);
                if (this.search) url.searchParams.set('search', this.search);
                if (this.typeId) url.searchParams.set('type_id', this.typeId);
                if (this.rmType) url.searchParams.set('rm_type', this.rmType);
                window.location.href = url.toString();
            },

            async loadMore() {
                if (this.loading || !this.hasMore) return;
                this.loading = true;

                try {
                    let url = new URL('{{ route('mobile.stock') }}');
                    url.searchParams.set('page', this.nextPage);
                    if (this.selectedBranch) url.searchParams.set('branch', this.selectedBranch);
                    if (this.displayUnit !== 'unit') url.searchParams.set('display_unit', this.displayUnit);
                    if (this.stockFilter !== 'all') url.searchParams.set('stock_filter', this.stockFilter);
                    if (this.search) url.searchParams.set('search', this.search);
                    if (this.typeId) url.searchParams.set('type_id', this.typeId);
                    if (this.rmType) url.searchParams.set('rm_type', this.rmType);

                    const response = await fetch(url, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const result = await response.json();

                    if (result.success) {
                        this.products = [...this.products, ...result.products];
                        this.stocks = { ...this.stocks, ...result.stocks };
                        this.hasMore = result.hasMore;
                        this.nextPage = result.nextPage;
                    }
                } catch (e) {
                    console.error('Failed to load more products:', e);
                } finally {
                    this.loading = false;
                }
            },

            exportStock(format) {
                let url = new URL(format === 'excel' ? '{{ route('mobile.stock.excel') }}' : '{{ route('mobile.stock.pdf') }}');
                if (this.selectedBranch) url.searchParams.set('branch', this.selectedBranch);
                if (this.displayUnit !== 'unit') url.searchParams.set('display_unit', this.displayUnit);
                if (this.stockFilter !== 'all') url.searchParams.set('stock_filter', this.stockFilter);
                if (this.search) url.searchParams.set('search', this.search);
                if (this.typeId) url.searchParams.set('type_id', this.typeId);
                if (this.rmType) url.searchParams.set('rm_type', this.rmType);
                window.location.href = url.toString();
            }
        }
    }
</script>
@endsection
