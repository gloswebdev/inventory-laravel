@extends('layouts.mobile')

@section('content')
<div class="space-y-8 pb-10" x-data="stockApp()">
    <!-- Header Area -->
    <div class="flex items-center justify-between bg-white/50 backdrop-blur-2xl p-6 rounded-[2.5rem] border border-white/70 shadow-xl shadow-indigo-100/20 relative overflow-hidden mb-2">
        <div class="absolute -right-10 -top-10 w-40 h-40 bg-indigo-500/10 rounded-full blur-3xl"></div>
        <div class="relative z-10 flex items-center justify-between w-full">
        <div>
            <h2 class="text-3xl font-900 text-slate-800 font-900 tracking-tighter">Live Stock</h2>
            <p class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400 mt-1">Real-time inventory insights</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('mobile.stock', array_merge(request()->query(), ['refresh' => 1])) }}" 
               @click="syncing = true"
               :class="syncing ? 'pointer-events-none opacity-60' : ''"
               class="w-10 h-10 bg-white rounded-xl flex items-center justify-center text-blue-500 border border-white/60 shadow-md active:scale-90 transition-all">
                <i class="fas text-xs" :class="syncing ? 'fa-spinner fa-spin' : 'fa-sync-alt'"></i>
            </a>
            @if(Auth::user()->hasPermission('mobile_stock', 'excel'))
            <button @click="exportStock('excel')" class="w-10 h-10 bg-white rounded-xl flex items-center justify-center text-emerald-500 border border-white/60 shadow-md active:scale-90 transition-all">
                <i class="fas fa-file-excel text-xs"></i>
            </button>
            @endif
            @if(Auth::user()->hasPermission('mobile_stock', 'pdf'))
            <button @click="exportStock('pdf')" class="w-10 h-10 bg-white rounded-xl flex items-center justify-center text-rose-500 border border-white/60 shadow-md active:scale-90 transition-all">
                <i class="fas fa-file-pdf text-xs"></i>
            </button>
            @endif
            <div class="w-10 h-10 grad-cyan rounded-xl flex items-center justify-center text-white shadow-lg shadow-cyan-100 border-2 border-white italic font-black text-[9px]" x-text="displayUnit === 'kg' ? 'KG' : 'BX'"></div>
        </div>
    </div>
</div>

    <!-- Filters Section -->
    <div class="bg-white/70 backdrop-blur-xl border border-white/60 shadow-lg shadow-indigo-100/30 hover:shadow-xl transition-all p-6 rounded-[2.5rem] space-y-6 border border-white/50">
        @if(Auth::user()->hasFeature('mobile_stock', 'branch_select'))
        <div>
            <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-3 mb-2 block">Stock Location</label>
            <div class="relative group">
                <select 
                    x-model="selectedBranch" 
                    @change="refreshPage()"
                    class="w-full bg-white/40 backdrop-blur-sm border-2 border-white/60/50 rounded-2xl py-4 px-5 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500 appearance-none shadow-md transition-all"
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
                    <select id="mTypeIdFilter" x-model="typeId" @change="refreshPage()" class="w-full bg-white/40 backdrop-blur-sm border-2 border-white/60/50 rounded-2xl py-4 px-5 text-[10px] font-bold text-slate-700 appearance-none shadow-md">
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
                    <select x-model="rmType" @change="refreshPage()" class="w-full bg-white/40 backdrop-blur-sm border-2 border-white/60/50 rounded-2xl py-4 px-5 text-[10px] font-bold text-slate-700 appearance-none shadow-md">
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
                <div class="flex bg-white/50 backdrop-blur-md p-1.5 rounded-2xl border border-white/60/50">
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
                <div class="flex bg-white/50 backdrop-blur-md p-1.5 rounded-2xl border border-white/60/50">
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

    {{-- Selective Products Bottom-Sheet Picker --}}
    <div class="bg-white/70 backdrop-blur-xl border border-white/60 shadow-lg shadow-indigo-100/30 hover:shadow-xl transition-all p-5 rounded-[2.5rem] border border-white/50">
        <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-1 mb-2 flex items-center justify-between">
            <span>Selective Products <span class="font-normal normal-case text-slate-300">(empty = all)</span></span>
            <span id="mpms-badge" style="background:#6366f1;color:#fff;border-radius:9999px;font-size:0.55rem;font-weight:900;padding:2px 8px;display:none;">0 selected</span>
        </label>
        <div id="mpms-wrapper"
            onclick="mpmsOpen()"
            style="min-height:48px;border-radius:1rem;background:rgba(255,255,255,0.5);border:2px solid rgba(255,255,255,0.6);padding:8px 14px;display:flex;flex-wrap:wrap;gap:4px;align-items:center;cursor:pointer;box-shadow:0 2px 8px rgba(99,102,241,0.06);">
            <div id="mpms-tags" style="display:contents;"></div>
            <span id="mpms-placeholder" style="font-size:0.72rem;font-weight:700;color:#94a3b8;"><i class="fas fa-filter mr-1 text-[0.6rem]"></i>Tap to pick products...</span>
        </div>
        <div id="mpms-inputs"></div>
    </div>

    {{-- Bottom Sheet Modal --}}
    <div id="mpms-modal" style="display:none;position:fixed;inset:0;z-index:99999;background:rgba(15,22,35,0.6);backdrop-filter:blur(4px);" onclick="if(event.target===this) mpmsClose()">
        <div style="position:absolute;bottom:0;left:0;right:0;background:#fff;border-radius:2rem 2rem 0 0;max-height:82vh;display:flex;flex-direction:column;">
            <div style="width:36px;height:4px;background:#e2e8f0;border-radius:2px;margin:10px auto 0;flex-shrink:0;"></div>
            <div style="padding:16px 20px 12px;border-bottom:1px solid #f1f5f9;flex-shrink:0;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                    <div style="font-size:0.8rem;font-weight:900;color:#1e293b;text-transform:uppercase;letter-spacing:0.08em;">Pick Products</div>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <span id="mpms-modal-count" style="background:#6366f1;color:#fff;border-radius:9999px;font-size:0.6rem;font-weight:900;padding:2px 8px;display:none;">0 selected</span>
                        <button onclick="mpmsClose()" style="width:32px;height:32px;border-radius:50%;background:#f1f5f9;border:none;font-size:1rem;cursor:pointer;color:#64748b;">×</button>
                    </div>
                </div>
                <div style="position:relative;">
                    <input id="mpms-search" type="search" placeholder="Search name, code or pack size..." autocomplete="off"
                        oninput="mpmsSearch(this.value)"
                        style="width:100%;border:1.5px solid #e2e8f0;border-radius:0.75rem;padding:10px 40px 10px 14px;font-size:0.78rem;font-weight:600;color:#1e293b;background:#f8fafc;outline:none;box-sizing:border-box;">
                    <i class="fas fa-search" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:0.7rem;pointer-events:none;"></i>
                </div>
            </div>
            <div id="mpms-list" style="overflow-y:auto;flex:1;padding:8px 0;"></div>
            <div style="padding:16px 20px;border-top:1px solid #f1f5f9;flex-shrink:0;safe-area-inset-bottom:env(safe-area-inset-bottom);">
                <button onclick="mpmsDone()" style="width:100%;padding:14px;background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;border:none;border-radius:1rem;font-size:0.78rem;font-weight:900;cursor:pointer;letter-spacing:0.05em;text-transform:uppercase;">Done &nbsp;✓</button>
            </div>
        </div>
    </div>

    <!-- Search Section -->
    @if(Auth::user()->hasFeature('mobile_stock', 'search'))
    <div class="bg-white/70 backdrop-blur-xl border border-white/60 shadow-lg shadow-indigo-100/30 hover:shadow-xl transition-all flex items-center px-6 py-5 rounded-[2rem] gap-4 shadow-md border border-white/50 group focus-within:border-indigo-300 transition-all">
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
    <div class="bg-white/70 backdrop-blur-xl border border-white/60 shadow-lg shadow-indigo-100/30 hover:shadow-xl transition-all overflow-hidden rounded-[2.5rem] border border-white/50">
        <div class="divide-y divide-slate-100">
            <template x-for="product in products" :key="product.id">
                <div class="group flex flex-col hover:bg-white/40 backdrop-blur-sm transition-all">
                    <div class="p-6 flex items-center justify-between active:scale-[0.98]">
                        <div class="flex-1 min-w-0 pr-4">
                            <div class="text-[13px] font-900 text-slate-800 font-900 truncate uppercase tracking-tight" x-text="product.name"></div>
                            <div class="flex items-center gap-2 mt-1.5">
                                <div class="px-2 py-0.5 rounded text-[8px] font-black uppercase tracking-widest border shadow-sm" :class="getPackColor(product.pack_name)" x-text="product.pack_name"></div>
                                <div class="w-1 h-1 bg-slate-200 rounded-full"></div>
                                <div class="text-[9px] font-black text-indigo-400 uppercase tracking-widest" x-text="product.item_code"></div>
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <template x-if="displayUnit === 'kg'">
                                <div class="text-xl font-900 tracking-tighter" :class="stocks[product.id] <= (product.low_alert_quantity * (product.weight_multiplier || 1)) ? 'text-red-500 font-black' : (stocks[product.id] <= 0 ? 'text-slate-200' : 'text-slate-800 font-900')" x-text="Number.isInteger(stocks[product.id]) ? stocks[product.id] : parseFloat(stocks[product.id]).toFixed(2)"></div>
                            </template>
                            <template x-if="displayUnit !== 'kg'">
                                <div class="text-xl font-900 tracking-tighter" :class="stocks[product.id] <= (product.low_alert_quantity / (product.unit_box || 1)) ? 'text-red-500 font-black' : (stocks[product.id] <= 0 ? 'text-slate-200' : 'text-slate-800 font-900')" x-text="Number.isInteger(stocks[product.id]) ? stocks[product.id] : parseFloat(stocks[product.id]).toFixed(2)"></div>
                            </template>
                            <div class="text-[8px] font-black uppercase tracking-[0.1em] mt-0.5" :class="stocks[product.id] <= 0 ? 'text-slate-300' : 'text-indigo-500'" x-text="displayUnit === 'kg' ? (product.uom === 'Ltr' ? 'Units (LTR)' : 'Units (KG)') : 'Boxes'"></div>
                        </div>
                    </div>
                    
                    <!-- Branch Breakdown (Only for Global View) -->
                    <template x-if="!selectedBranch && product.branch_stocks">
                        <div class="px-6 pb-6 pt-0">
                            <div class="bg-white/40 rounded-[1.5rem] p-4 border border-white/60 shadow-inner">
                                <div class="flex items-center gap-2 mb-3 px-1">
                                    <i class="fas fa-layer-group text-[9px] text-indigo-400"></i>
                                    <span class="text-[8px] font-black uppercase tracking-[0.2em] text-indigo-500">Branch Distribution</span>
                                </div>
                                <div class="flex overflow-x-auto gap-3 pb-1 snap-x snap-mandatory [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
                                    <template x-for="branch in branches" :key="branch.code">
                                        <div class="snap-start shrink-0 flex flex-col items-center justify-center gap-1 px-3 py-3 rounded-2xl border min-w-[75px] transition-all"
                                             :class="product.branch_stocks[branch.code] > 0 ? 'bg-white border-indigo-100 shadow-md shadow-indigo-100/50' : 'bg-white/30 border-white/40 opacity-60'">
                                            
                                            <div class="text-[7px] font-black uppercase tracking-widest truncate max-w-[65px] text-center" 
                                                 :class="product.branch_stocks[branch.code] > 0 ? 'text-indigo-400' : 'text-slate-400'" 
                                                 x-text="branch.name"></div>
                                            
                                            <div class="flex items-baseline gap-0.5 mt-0.5">
                                                <span class="text-sm font-900 tracking-tighter leading-none" 
                                                      :class="product.branch_stocks[branch.code] > 0 ? 'text-indigo-600' : 'text-slate-300'" 
                                                      x-text="product.branch_stocks[branch.code] > 0 ? (Number.isInteger(product.branch_stocks[branch.code]) ? product.branch_stocks[branch.code] : parseFloat(product.branch_stocks[branch.code]).toFixed(1)) : '-'"></span>
                                                <span x-show="product.branch_stocks[branch.code] > 0" class="text-[6px] font-black text-indigo-300 uppercase tracking-widest" x-text="displayUnit === 'kg' ? (product.uom === 'Ltr' ? 'L' : 'KG') : 'BX'"></span>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
            
            <div x-show="products.length === 0" class="p-16 text-center">
                <div class="w-16 h-16 bg-white/60 backdrop-blur-md rounded-full flex items-center justify-center mx-auto mb-4 border border-white/60">
                    <i class="fas fa-magnifying-glass text-slate-300"></i>
                </div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest leading-loose">No inventory records found<br>for this search criteria.</p>
            </div>
        </div>

        <!-- Load More Section -->
        <div x-show="hasMore" class="p-6 border-t border-white/50 bg-white/60 backdrop-blur-md/30">
            <button 
                @click="loadMore()" 
                :disabled="loading"
                class="w-full py-5 bg-white border-2 border-white/60 rounded-3xl text-[10px] font-black uppercase tracking-widest text-indigo-600 hover:bg-indigo-50 active:scale-95 transition-all flex items-center justify-center gap-3 disabled:opacity-50 shadow-md"
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
            branches: @json($branches),
            hasMore: @json($hasMore),
            nextPage: 2,
            loading: false,
            syncing: false,

            getPackColor(packName) {
                if (!packName) return 'bg-slate-50 text-slate-500 border-slate-200';
                const name = packName.toString().toUpperCase();
                if (name.includes('1 KG') || name.includes('1 LTR') || name.includes('1KG') || name.includes('1LTR')) return 'bg-indigo-50 text-indigo-600 border-indigo-200';
                if (name.includes('500 GM') || name.includes('500 ML') || name.includes('500GM') || name.includes('500ML')) return 'bg-emerald-50 text-emerald-600 border-emerald-200';
                if (name.includes('250 GM') || name.includes('250 ML') || name.includes('250GM') || name.includes('250ML')) return 'bg-rose-50 text-rose-600 border-rose-200';
                if (name.includes('100 GM') || name.includes('100 ML') || name.includes('100GM') || name.includes('100ML')) return 'bg-amber-50 text-amber-600 border-amber-200';
                if (name.includes('5 LTR') || name.includes('5 KG') || name.includes('5LTR') || name.includes('5KG')) return 'bg-cyan-50 text-cyan-600 border-cyan-200';
                return 'bg-violet-50 text-violet-600 border-violet-200';
            },

            refreshPage() {
                let url = new URL('{{ route('mobile.stock') }}');
                if (this.selectedBranch) url.searchParams.set('branch', this.selectedBranch);
                if (this.displayUnit !== 'unit') url.searchParams.set('display_unit', this.displayUnit);
                if (this.stockFilter !== 'all') url.searchParams.set('stock_filter', this.stockFilter);
                if (this.search) url.searchParams.set('search', this.search);
                if (this.typeId) url.searchParams.set('type_id', this.typeId);
                if (this.rmType) url.searchParams.set('rm_type', this.rmType);
                // Append selected product IDs
                document.querySelectorAll('#mpms-inputs input').forEach(inp => {
                    url.searchParams.append('product_ids[]', inp.value);
                });
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
                document.querySelectorAll('#mpms-inputs input').forEach(inp => {
                    url.searchParams.append('product_ids[]', inp.value);
                });
                window.location.href = url.toString();
            }
        }
    }
</script>

<style>
    .mpms-item { display:flex;align-items:center;gap:10px;padding:12px 20px;border-bottom:1px solid #f8fafc;cursor:pointer;transition:background 0.1s; }
    .mpms-item:active { background:#f0f9ff; }
    .mpms-item.sel { background:#f0fdf4; }
    .mpms-check { width:20px;height:20px;border-radius:5px;border:2px solid #e2e8f0;flex-shrink:0;transition:all 0.1s; }
    .mpms-item.sel .mpms-check { background:#22c55e;border-color:#22c55e;position:relative; }
    .mpms-item.sel .mpms-check::after { content:'✓';position:absolute;top:-1px;left:2px;color:#fff;font-size:11px;font-weight:900; }
    .mpms-name { font-size:0.78rem;font-weight:700;color:#1e293b;flex:1;line-height:1.3; }
    .mpms-code { font-size:0.62rem;font-weight:900;color:#6366f1;background:#eef2ff;border-radius:4px;padding:2px 5px;flex-shrink:0; }
    .mpms-pack { font-size:0.6rem;font-weight:800;background:#fef3c7;color:#92400e;border:1px solid #fcd34d;border-radius:4px;padding:1px 5px;flex-shrink:0; }
    .mpms-tag { display:inline-flex;align-items:center;gap:3px;background:#eef2ff;color:#4338ca;border:1px solid #c7d2fe;border-radius:5px;font-size:0.62rem;font-weight:800;padding:2px 5px 2px 7px;line-height:1.4; }
    .mpms-tag button { border:none;background:none;cursor:pointer;color:#818cf8;font-size:0.9rem;line-height:1;padding:0 2px; }
    .mpms-empty { padding:24px;text-align:center;color:#94a3b8;font-size:0.75rem;font-style:italic; }
</style>

<script>
    @php
        $mpmsAllProducts = $allProducts->map(function($p) {
            return [
                'id'              => (string) $p->id,
                'name'            => $p->name,
                'item_code'       => $p->item_code,
                'pack_name'       => $p->pack_name ?? '',
                'product_type_id' => (string) ($p->product_type_id ?? ''),
            ];
        })->values()->all();
        $mpmsInitIds = array_map('strval', request()->input('product_ids', []));
    @endphp

    const MPMS_ALL  = @json($mpmsAllProducts);
    const MPMS_INIT = @json($mpmsInitIds);

    let mpmsSelected = new Map();
    let mpmsFiltered = MPMS_ALL;
    let mpmsJustDone = false;

    function mpmsGetFiltered(typeId) {
        if (!typeId) return MPMS_ALL;
        return MPMS_ALL.filter(p => p.product_type_id === String(typeId));
    }

    function mpmsEsc(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    function mpmsRenderTags() {
        const tags = document.getElementById('mpms-tags');
        const ph   = document.getElementById('mpms-placeholder');
        const badge = document.getElementById('mpms-badge');
        const mBadge = document.getElementById('mpms-modal-count');
        const inp   = document.getElementById('mpms-inputs');

        tags.innerHTML = '';
        inp.innerHTML  = '';

        const count = mpmsSelected.size;
        badge.textContent  = count;
        badge.style.display = count > 0 ? 'inline-block' : 'none';
        ph.style.display = count > 0 ? 'none' : 'inline';

        if (mBadge) {
            mBadge.textContent  = count + ' selected';
            mBadge.style.display = count > 0 ? 'inline-block' : 'none';
        }

        mpmsSelected.forEach((p, id) => {
            // Tag
            const t = document.createElement('span');
            t.className = 'mpms-tag';
            t.innerHTML = `${mpmsEsc(p.item_code)}<button type="button" onclick="mpmsRemove('${id}');event.stopPropagation();">×</button>`;
            tags.appendChild(t);
            // Hidden input
            const h = document.createElement('input');
            h.type = 'hidden'; h.name = 'product_ids[]'; h.value = id;
            inp.appendChild(h);
        });
    }

    function mpmsRenderList(query) {
        const list = document.getElementById('mpms-list');
        const q = (query || '').toLowerCase().trim();
        const shown = q
            ? mpmsFiltered.filter(p =>
                p.name.toLowerCase().includes(q) ||
                p.item_code.toLowerCase().includes(q) ||
                (p.pack_name && p.pack_name.toLowerCase().includes(q))
              )
            : mpmsFiltered;

        if (shown.length === 0) {
            list.innerHTML = '<div class="mpms-empty">No products found</div>';
            return;
        }
        list.innerHTML = '';
        shown.forEach(p => {
            const isSel = mpmsSelected.has(p.id);
            const div = document.createElement('div');
            div.className = 'mpms-item' + (isSel ? ' sel' : '');
            div.innerHTML = `<span class="mpms-check"></span><span class="mpms-name">${mpmsEsc(p.name)}</span><span class="mpms-code">${mpmsEsc(p.item_code)}</span>${p.pack_name ? '<span class="mpms-pack">' + mpmsEsc(p.pack_name) + '</span>' : ''}`;
            div.ontouchstart = () => {}; // enable :active on iOS
            div.onclick = () => mpmsToggle(p);
            list.appendChild(div);
        });
    }

    function mpmsToggle(p) {
        if (mpmsSelected.has(p.id)) mpmsSelected.delete(p.id);
        else mpmsSelected.set(p.id, p);
        mpmsRenderTags();
        mpmsRenderList(document.getElementById('mpms-search')?.value);
    }

    function mpmsRemove(id) {
        mpmsSelected.delete(id);
        mpmsRenderTags();
    }

    function mpmsOpen() {
        const typeEl = document.getElementById('mTypeIdFilter');
        mpmsFiltered = mpmsGetFiltered(typeEl ? typeEl.value : '');
        mpmsRenderList('');
        document.getElementById('mpms-search').value = '';
        document.getElementById('mpms-modal').style.display = 'block';
        document.getElementById('mpms-search').focus();
    }

    function mpmsClose() {
        document.getElementById('mpms-modal').style.display = 'none';
    }

    function mpmsDone() {
        mpmsClose();
        // Use Alpine v3 API to access stockApp data
        let url = new URL('{{ route('mobile.stock') }}');
        const alpineEl = document.querySelector('[x-data]');
        if (alpineEl) {
            try {
                const app = Alpine.$data(alpineEl);
                if (app.selectedBranch) url.searchParams.set('branch', app.selectedBranch);
                if (app.displayUnit && app.displayUnit !== 'unit') url.searchParams.set('display_unit', app.displayUnit);
                if (app.stockFilter && app.stockFilter !== 'all') url.searchParams.set('stock_filter', app.stockFilter);
                if (app.search) url.searchParams.set('search', app.search);
                if (app.typeId) url.searchParams.set('type_id', app.typeId);
                if (app.rmType) url.searchParams.set('rm_type', app.rmType);
            } catch(e) {
                console.warn('Alpine data access failed, using URL params only:', e);
            }
        }
        // Append selected product IDs from hidden inputs
        document.querySelectorAll('#mpms-inputs input').forEach(inp => {
            url.searchParams.append('product_ids[]', inp.value);
        });
        window.location.href = url.toString();
    }

    function mpmsSearch(val) {
        mpmsRenderList(val);
    }

    // Sync type filter with product picker
    document.addEventListener('DOMContentLoaded', function () {
        const typeEl = document.getElementById('mTypeIdFilter');
        if (typeEl) {
            typeEl.addEventListener('change', function () {
                mpmsFiltered = mpmsGetFiltered(this.value);
                mpmsSelected.clear();
                mpmsRenderTags();
            });
            mpmsFiltered = mpmsGetFiltered(typeEl.value);
        }

        // Restore pre-selected from URL
        MPMS_INIT.forEach(id => {
            const found = MPMS_ALL.find(p => p.id === id);
            if (found) mpmsSelected.set(id, found);
        });
        mpmsRenderTags();
    });
</script>
@endsection
