@extends('layouts.mobile')

@section('content')
<div x-data="mobileCostingDashboardApp()" x-init="init()">

    {{-- Page Title & Tab Switcher --}}
    <div class="mb-5 animate-in fade-in slide-in-from-top duration-500">
        <div class="flex items-center justify-between gap-3 mb-3">
            <div class="flex items-center gap-3">
                <a href="{{ route('mobile.dashboard') }}"
                   class="w-9 h-9 rounded-xl bg-white/60 border border-white flex items-center justify-center text-slate-600 active:scale-90 transition-transform">
                    <i class="fas fa-chevron-left text-sm"></i>
                </a>
                <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-yellow-400 to-orange-500 flex items-center justify-center shadow-lg shadow-yellow-200">
                    <i class="fas fa-coins text-white text-base"></i>
                </div>
                <div>
                    <h2 class="text-xl font-900 text-slate-800 tracking-tighter leading-none">Costing</h2>
                    <p class="text-[9px] font-black uppercase tracking-widest text-slate-400 mt-0.5">Manufacturing Cost Dashboard</p>
                </div>
            </div>
        </div>

        {{-- Mode Switcher Tabs --}}
        <div class="grid grid-cols-2 p-1.5 bg-white/60 backdrop-blur-xl border border-white rounded-2xl shadow-sm">
            <button @click="activeTab = 'dashboard'"
                    :class="activeTab === 'dashboard' ? 'bg-gradient-to-r from-yellow-500 to-orange-500 text-white shadow-md shadow-yellow-200' : 'text-slate-600 hover:text-slate-800'"
                    class="py-2.5 rounded-xl text-xs font-900 uppercase tracking-tight transition-all flex items-center justify-center gap-2">
                <i class="fas fa-chart-pie"></i> Master BOMs (A-Z)
            </button>
            <button @click="activeTab = 'calculator'"
                    :class="activeTab === 'calculator' ? 'bg-gradient-to-r from-yellow-500 to-orange-500 text-white shadow-md shadow-yellow-200' : 'text-slate-600 hover:text-slate-800'"
                    class="py-2.5 rounded-xl text-xs font-900 uppercase tracking-tight transition-all flex items-center justify-center gap-2">
                <i class="fas fa-calculator"></i> Batch Calculator
            </button>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         TAB 1: MASTER BOMs DASHBOARD (A-Z)
         ══════════════════════════════════════════════════════════════ --}}
    <div x-show="activeTab === 'dashboard'" x-cloak class="space-y-4">

        {{-- Search input --}}
        <div class="relative mb-3">
            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
            <input type="text" x-model="searchDashboard"
                   placeholder="Search BOM name, code or packaging item..."
                   class="w-full pl-11 pr-4 py-3.5 bg-white/70 backdrop-blur-xl border border-white/80 rounded-2xl text-sm font-bold text-slate-800 placeholder:text-slate-400 outline-none focus:ring-2 focus:ring-yellow-400 focus:border-yellow-400 transition-all shadow-sm">
        </div>

        {{-- BOM Cards List --}}
        <div class="space-y-3 pb-24">
            <template x-for="bom in filteredBoms" :key="bom.id">
                <div class="bg-white/80 backdrop-blur-xl border border-white rounded-3xl p-4 shadow-sm space-y-3 transition-all relative overflow-hidden"
                     :class="isBomActive(bom.id) ? 'ring-2 ring-yellow-400/50 bg-yellow-50/20' : ''">
                    
                    {{-- BOM Header --}}
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="font-900 text-slate-800 text-sm leading-tight flex items-center gap-2 flex-wrap">
                                <span x-text="bom.product_name"></span>
                                <template x-if="bom.badge">
                                    <span class="px-2 py-0.5 rounded text-[8px] font-black uppercase bg-amber-100 text-amber-700" x-text="bom.badge"></span>
                                </template>
                            </div>
                            <div class="text-[9px] font-bold text-slate-400 mt-1 flex items-center gap-2 flex-wrap">
                                <span class="font-mono bg-slate-100 px-1.5 py-0.5 rounded" x-text="bom.item_code"></span>
                                <span x-text="'Formulation: ' + bom.formulation + '%'"></span>
                                <template x-if="bom.density && bom.density > 0">
                                    <span class="text-amber-700 font-bold bg-amber-50 px-1.5 py-0.5 rounded border border-amber-100" x-text="'Density: ' + bom.density"></span>
                                </template>
                            </div>
                        </div>

                        <div class="text-right shrink-0">
                            <div class="text-xs font-900 text-slate-800" x-text="'₹' + Number(bom.grand_total).toLocaleString('en-IN', {minimumFractionDigits:2})"></div>
                            <div class="text-[8px] font-bold text-slate-400 uppercase" x-text="'Batch: ' + bom.yield_qty + ' ' + bom.yield_uom"></div>
                        </div>
                    </div>

                    {{-- W/o & With Density Rate Selector Buttons --}}
                    <div class="grid grid-cols-2 gap-2 pt-1 border-t border-slate-100">
                        {{-- W/o Density Rate Button --}}
                        <div @click="toggleRate(bom, 'wo')"
                             class="p-2.5 rounded-2xl border transition-all cursor-pointer select-none text-center"
                             :class="isRateSelected(bom.id, 'wo') ? 'bg-amber-500 text-white border-amber-500 shadow-md shadow-amber-200' : 'bg-slate-50 border-slate-200/80 text-slate-700'">
                            <div class="text-[9px] font-black uppercase tracking-wider opacity-80">W/o Density</div>
                            <div class="text-xs font-900 mt-0.5" x-text="'₹' + bom.wo_density_rate + '/' + bom.yield_uom"></div>
                        </div>

                        {{-- With Density Rate Button --}}
                        <div @click="toggleRate(bom, 'with')"
                             class="p-2.5 rounded-2xl border transition-all cursor-pointer select-none text-center"
                             :class="isRateSelected(bom.id, 'with') ? 'bg-emerald-600 text-white border-emerald-600 shadow-md shadow-emerald-200' : 'bg-slate-50 border-slate-200/80 text-slate-700'">
                            <div class="text-[9px] font-black uppercase tracking-wider opacity-80">With Density</div>
                            <div class="text-xs font-900 mt-0.5" x-text="'₹' + bom.with_density_rate + '/Ltr'"></div>
                        </div>
                    </div>

                    {{-- Linked Packaging Items & PM Costs --}}
                    <template x-if="bom.packing_costs && bom.packing_costs.length > 0">
                        <div class="space-y-1.5 pt-2 border-t border-slate-100">
                            <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Linked Packaging Items</div>
                            <template x-for="p in bom.packing_costs" :key="p.pricelist_id">
                                <div @click="togglePm(bom, p)"
                                     class="p-2.5 rounded-2xl border flex items-center justify-between gap-2 cursor-pointer transition-all select-none"
                                     :class="isPmSelected(bom.id, p.pricelist_id) ? 'bg-orange-500 text-white border-orange-500 shadow-md shadow-orange-200' : 'bg-white border-slate-200 text-slate-700'">
                                    <div class="min-w-0 flex-1">
                                        <div class="text-xs font-900 truncate" x-text="p.fg_name"></div>
                                        <div class="text-[9px] font-bold opacity-80" x-text="'Size: ' + p.size + ' | PM Cost: ₹' + p.pm_cost"></div>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <div class="text-xs font-900" x-text="'₹' + (selectedRate.type === 'with' ? p.unit_total_with : p.unit_total_wo) + '/pack'"></div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>

                    {{-- View BOM Raw Details Modal Button --}}
                    <div class="pt-2 flex justify-end">
                        <button @click="openViewBomModal(bom)"
                                class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-[10px] font-black uppercase tracking-wider transition-all">
                            <i class="fas fa-eye text-yellow-600 mr-1"></i> View Formula Details
                        </button>
                    </div>
                </div>
            </template>
        </div>

        {{-- Sticky Bottom Calculator Bar --}}
        <div x-show="selectedRate.value !== null || selectedPm.cost !== null" x-cloak
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="translate-y-full opacity-0"
             x-transition:enter-end="translate-y-0 opacity-100"
             class="fixed bottom-20 left-4 right-4 z-40">
            <div class="bg-slate-900/95 text-white p-4 rounded-3xl shadow-2xl border border-yellow-500/30 flex items-center justify-between gap-3 backdrop-blur-xl">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-yellow-400 to-orange-500 text-white flex items-center justify-center text-lg font-black shrink-0 shadow-lg shadow-yellow-500/30">
                        <i class="fas fa-calculator animate-pulse"></i>
                    </div>
                    <div>
                        <div class="text-[9px] font-black text-yellow-400 uppercase tracking-widest">Combined Cost</div>
                        <div class="text-lg font-900 text-white" x-text="'₹ ' + calculatedCombinedTotal.toFixed(2)"></div>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button @click="resetSelections()" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 flex items-center justify-center">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         TAB 2: BATCH COST CALCULATOR
         ══════════════════════════════════════════════════════════════ --}}
    <div x-show="activeTab === 'calculator'" x-cloak class="space-y-4">

        {{-- Grand Total Banner --}}
        <div x-show="grandTotal > 0" x-cloak
             class="bg-gradient-to-r from-yellow-500 to-orange-500 p-5 rounded-[1.5rem] text-white relative overflow-hidden shadow-xl shadow-yellow-200">
            <div class="text-[9px] font-black uppercase tracking-widest opacity-80 mb-1">Total Manufacturing Cost</div>
            <div class="text-3xl font-900 tracking-tighter" x-text="'₹ ' + grandTotal.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></div>
            <div class="text-[10px] font-bold opacity-70 mt-1" x-text="results.length + ' product(s) calculated'"></div>
        </div>

        {{-- Search & Type Filter --}}
        <div class="relative mb-3">
            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
            <input type="text" x-model="search" @input="filterProducts()"
                   placeholder="Search product..."
                   class="w-full pl-11 pr-4 py-3.5 bg-white/70 backdrop-blur-xl border border-white/80 rounded-2xl text-sm font-bold text-slate-800 placeholder:text-slate-400 outline-none focus:ring-2 focus:ring-yellow-400 focus:border-yellow-400 transition-all shadow-sm">
        </div>

        {{-- Product Cards List --}}
        <div class="space-y-2 mb-5" id="mobile-product-list">
            @forelse($products as $product)
            @php
                $priceData = isset($priceMap[$product->item_code]) ? $priceMap[$product->item_code] : 0;
            @endphp
            <div class="product-item"
                 data-id="{{ $product->id }}"
                 data-name="{{ strtolower($product->name . ' ' . $product->item_code) }}">
                <div class="bg-white/70 backdrop-blur-xl border border-white/80 p-4 rounded-2xl flex items-center gap-3 shadow-sm transition-all active:scale-[0.98] cursor-pointer"
                     @click="toggleProduct({{ $product->id }}, '{{ addslashes($product->name) }}', '{{ addslashes($product->pack_name) }}')">
                    <div class="w-11 h-11 rounded-2xl flex-shrink-0 flex items-center justify-center text-white font-black text-sm"
                         :class="isSelected({{ $product->id }}) ? 'bg-gradient-to-br from-yellow-400 to-orange-500 shadow-md shadow-yellow-200' : 'bg-gradient-to-br from-slate-200 to-slate-300'">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="font-900 text-slate-800 text-[13px] leading-tight truncate">{{ $product->name }}</div>
                        <div class="text-[9px] font-black uppercase tracking-widest text-slate-400 mt-0.5">{{ $product->pack_name ?? '—' }}</div>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <div class="w-6 h-6 rounded-full border-2 flex items-center justify-center transition-all"
                             :class="isSelected({{ $product->id }}) ? 'bg-yellow-500 border-yellow-500' : 'border-slate-300'">
                            <i class="fas fa-check text-white text-[8px]" x-show="isSelected({{ $product->id }})"></i>
                        </div>
                    </div>
                </div>

                {{-- Qty & Density Inputs when selected --}}
                <div x-show="isSelected({{ $product->id }})" x-cloak class="mt-1.5 px-2">
                    <div class="bg-yellow-50 border border-yellow-100 rounded-2xl p-3.5 space-y-3 shadow-sm">
                        <div class="flex items-center justify-between gap-2">
                            <div class="text-[10px] font-black text-yellow-800 uppercase tracking-wider">Quantity (boxes):</div>
                            <div class="flex items-center gap-1 bg-white border border-yellow-200 rounded-xl overflow-hidden">
                                <button @click.stop="decrementQty({{ $product->id }})" class="w-8 h-8 flex items-center justify-center text-yellow-600 font-black text-sm">−</button>
                                <input type="number" :value="getQty({{ $product->id }})" @input.stop="setQty({{ $product->id }}, $event.target.value)" min="0.001" step="0.001" class="w-16 text-center font-black text-xs text-slate-800 outline-none py-1 border-x border-yellow-100">
                                <button @click.stop="incrementQty({{ $product->id }})" class="w-8 h-8 flex items-center justify-center text-yellow-600 font-black text-sm">+</button>
                            </div>
                        </div>
                        <div class="pt-2 border-t border-yellow-100/50 flex items-center justify-between gap-2">
                            <label class="text-[10px] font-black text-yellow-800 uppercase tracking-wider">Density (g/ml):</label>
                            <input type="number" :value="getDensity({{ $product->id }})" @input.stop="setDensity({{ $product->id }}, $event.target.value)" min="0.1" max="3" step="0.01" class="w-20 text-center font-black text-xs text-slate-800 bg-white border border-yellow-200 rounded-xl py-1 outline-none">
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <div class="text-center py-12 text-slate-400 font-bold">No products with recipes found.</div>
            @endforelse
        </div>

        {{-- Sticky Calculate Button --}}
        <div x-show="selected.length > 0" x-cloak class="sticky bottom-20 z-30 mb-3">
            <div class="glass-premium rounded-2xl p-3 flex items-center justify-between gap-3 shadow-xl border-white/50">
                <div class="text-[10px] font-black text-slate-600 uppercase tracking-wider" x-text="selected.length + ' product(s) selected'"></div>
                <button @click="calculate()" :disabled="calculating"
                        class="flex items-center gap-2 px-5 py-3 bg-gradient-to-r from-yellow-500 to-orange-500 text-white font-black text-sm rounded-xl shadow-lg shadow-yellow-200 active:scale-95 transition-all">
                    <i class="fas fa-calculator" :class="calculating ? 'fa-spin' : ''"></i>
                    <span x-text="calculating ? 'Calculating...' : 'Calculate Cost'"></span>
                </button>
            </div>
        </div>

        {{-- Results Breakdown --}}
        <div x-show="results.length > 0" x-cloak class="space-y-3 pb-24">
            <template x-for="r in results" :key="r.product_id">
                <div class="bg-white/80 backdrop-blur-xl border border-white rounded-2xl overflow-hidden shadow-sm" x-data="{ open: false }">
                    <div class="p-4 flex items-center justify-between gap-3 cursor-pointer" @click="open = !open">
                        <div>
                            <div class="font-900 text-slate-800 text-xs" x-text="r.product_name"></div>
                            <div class="text-[9px] text-slate-400 font-bold mt-0.5" x-text="'Qty: ' + r.quantity + ' | Density: ' + r.density"></div>
                        </div>
                        <div class="text-right">
                            <div class="font-900 text-yellow-700 text-sm" x-text="'₹' + (r.total_cost || 0).toLocaleString('en-IN', {minimumFractionDigits:2})"></div>
                            <div class="text-[8px] text-slate-400 font-bold" x-text="r.breakdown.length + ' RM items'"></div>
                        </div>
                    </div>

                    <div x-show="open" class="border-t border-slate-100 p-3 space-y-2">
                        <template x-for="(rm, i) in r.breakdown" :key="i">
                            <div class="flex items-center justify-between text-xs p-2 bg-slate-50 rounded-xl">
                                <div>
                                    <div class="font-bold text-slate-700" x-text="rm.rm_name"></div>
                                    <div class="text-[9px] text-slate-400" x-text="rm.required_qty + ' ' + rm.uom"></div>
                                </div>
                                <div class="text-right font-black text-slate-800" x-text="'₹' + rm.sub_cost.toLocaleString('en-IN', {minimumFractionDigits:2})"></div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- View Formula Modal --}}
    <div x-show="viewBomModal.show" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
        <div class="bg-white w-full max-w-lg rounded-3xl p-6 max-h-[85vh] overflow-y-auto space-y-4">
            <div class="flex justify-between items-center pb-2 border-b border-slate-100">
                <h3 class="text-base font-900 text-slate-800 uppercase tracking-tight" x-text="viewBomModal.bom?.product_name"></h3>
                <button @click="viewBomModal.show = false" class="text-slate-400"><i class="fas fa-times text-lg"></i></button>
            </div>

            <div class="space-y-3 text-xs" x-if="viewBomModal.bom">
                <div class="p-3 bg-amber-50 border border-amber-100 rounded-2xl flex justify-between">
                    <div>Yield: <span class="font-black" x-text="viewBomModal.bom?.yield_qty + ' ' + viewBomModal.bom?.yield_uom"></span></div>
                    <div>Density: <span class="font-black" x-text="viewBomModal.bom?.density"></span></div>
                    <div>Formulation: <span class="font-black" x-text="viewBomModal.bom?.formulation + '%'"></span></div>
                </div>

                <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Ingredients List</div>
                <template x-for="item in (viewBomModal.bom?.raw_bom_data?.items || [])" :key="item.id">
                    <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-100 flex justify-between items-center">
                        <div>
                            <div class="font-bold text-slate-800" x-text="item.raw_material?.name"></div>
                            <div class="text-[9px] text-slate-400" x-text="item.raw_material?.item_code"></div>
                        </div>
                        <div class="text-right font-black" x-text="item.quantity + ' ' + (item.raw_material?.uom || 'KG')"></div>
                    </div>
                </template>
            </div>
        </div>
    </div>

</div>

<script>
function mobileCostingDashboardApp() {
    return {
        activeTab: 'dashboard',
        searchDashboard: '',
        boms: @json($processedBoms ?? []),
        selectedRate: { bom_id: null, type: null, value: null, unit: 'KG' },
        selectedPm: { bom_id: null, pricelist_id: null, cost: null, size: null, size_in_ml: null, cf1: 1 },

        selected: [],
        results: [],
        grandTotal: 0,
        calculating: false,
        search: '',

        viewBomModal: { show: false, bom: null },

        init() {},

        get filteredBoms() {
            if (!this.searchDashboard) return this.boms;
            const q = this.searchDashboard.toLowerCase();
            return this.boms.filter(b => 
                (b.product_name && b.product_name.toLowerCase().includes(q)) ||
                (b.item_code && b.item_code.toLowerCase().includes(q))
            );
        },

        isBomActive(bomId) {
            return this.selectedRate.bom_id === bomId || this.selectedPm.bom_id === bomId;
        },

        isRateSelected(bomId, type) {
            return this.selectedRate.bom_id === bomId && this.selectedRate.type === type;
        },

        isPmSelected(bomId, pricelistId) {
            return this.selectedPm.bom_id === bomId && this.selectedPm.pricelist_id === pricelistId;
        },

        toggleRate(bom, type) {
            if (this.selectedRate.bom_id !== bom.id) {
                this.selectedPm = { bom_id: null, pricelist_id: null, cost: null, size: null, size_in_ml: null, cf1: 1 };
            }
            if (this.isRateSelected(bom.id, type)) {
                this.selectedRate = { bom_id: null, type: null, value: null, unit: 'KG' };
            } else {
                const val = type === 'wo' ? bom.wo_density_rate : bom.with_density_rate;
                const u = type === 'wo' ? bom.yield_uom : 'Ltr';
                this.selectedRate = { bom_id: bom.id, type: type, value: val, unit: u };
            }
        },

        togglePm(bom, p) {
            if (this.selectedPm.bom_id !== bom.id) {
                this.selectedRate = { bom_id: null, type: null, value: null, unit: 'KG' };
            }
            if (this.isPmSelected(bom.id, p.pricelist_id)) {
                this.selectedPm = { bom_id: null, pricelist_id: null, cost: null, size: null, size_in_ml: null, cf1: 1 };
            } else {
                this.selectedPm = {
                    bom_id: bom.id,
                    pricelist_id: p.pricelist_id,
                    cost: p.pm_cost,
                    size: p.size,
                    size_in_ml: p.size_in_ml,
                    cf1: p.cf1
                };
                if (!this.selectedRate.value) {
                    this.selectedRate = { bom_id: bom.id, type: 'with', value: bom.with_density_rate, unit: 'Ltr' };
                }
            }
        },

        resetSelections() {
            this.selectedRate = { bom_id: null, type: null, value: null, unit: 'KG' };
            this.selectedPm   = { bom_id: null, pricelist_id: null, cost: null, size: null, size_in_ml: null, cf1: 1 };
        },

        get calculatedCombinedTotal() {
            let r = this.selectedRate.value || 0;
            let pm = this.selectedPm.cost || 0;
            if (this.selectedRate.value !== null && this.selectedPm.cost !== null && this.selectedPm.size_in_ml) {
                let bulk = (r / 1000) * this.selectedPm.size_in_ml;
                return bulk + pm;
            }
            return r + pm;
        },

        openViewBomModal(bom) {
            this.viewBomModal.bom = bom;
            this.viewBomModal.show = true;
        },

        // Calculator methods
        isSelected(id) { return this.selected.some(s => s.id === id); },
        getQty(id) { const item = this.selected.find(s => s.id === id); return item ? item.quantity : 1; },
        setQty(id, val) { const item = this.selected.find(s => s.id === id); if (item) item.quantity = parseFloat(val) || 1; },
        incrementQty(id) { const item = this.selected.find(s => s.id === id); if (item) item.quantity++; },
        decrementQty(id) { const item = this.selected.find(s => s.id === id); if (item && item.quantity > 1) item.quantity--; },
        getDensity(id) { const item = this.selected.find(s => s.id === id); return item ? item.density : 1.0; },
        setDensity(id, val) { const item = this.selected.find(s => s.id === id); if (item) item.density = parseFloat(val) || 1.0; },

        toggleProduct(id, name, pack_name) {
            if (this.isSelected(id)) {
                this.selected = this.selected.filter(s => s.id !== id);
            } else {
                this.selected.push({ id, name, pack_name, quantity: 1, density: 1.0 });
            }
        },

        filterProducts() {
            const q = this.search.toLowerCase();
            document.querySelectorAll('.product-item').forEach(el => {
                el.style.display = (!q || el.dataset.name.includes(q)) ? '' : 'none';
            });
        },

        async calculate() {
            if (!this.selected.length) return;
            this.calculating = true;
            try {
                const resp = await fetch('{{ route('mobile.costing.calculate') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({
                        products: this.selected.map(s => ({ id: s.id, quantity: s.quantity, density: s.density }))
                    }),
                });
                const data = await resp.json();
                if (data.success) {
                    this.results    = data.results;
                    this.grandTotal = data.grand_total;
                }
            } catch(e) {
                alert('Network error.');
            } finally {
                this.calculating = false;
            }
        }
    };
}
</script>
@endsection
