@extends('layouts.app')

@section('header', 'Costing Manager')

@section('content')
<style>
.bom-tab-active  { background: linear-gradient(135deg,#f59e0b,#ea580c); color:#fff; box-shadow: 0 4px 12px rgba(245,158,11,.3); }
.bom-tab-inactive{ background:#f8fafc; color:#64748b; border: 1px solid #e2e8f0; }
.bom-tab-inactive:hover { background:#fffbeb; color:#92400e; border-color:#fde68a; }
</style>

<div x-data="costingApp()" x-init="init()">

    {{-- ══ Page Header ══ --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <div>
            <h1 class="text-2xl font-black text-slate-800 tracking-tight">Costing Manager</h1>
            <p class="text-slate-500 text-sm font-medium mt-0.5">Manufacturing cost calculator with technical purity & density parameters</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            @if(Auth::user()->hasFeature('costing', 'fetch_prices'))
            <button @click="syncPrices()" :disabled="syncing" id="btn-sync-prices"
                    title="Fetch latest purchase rates from ERP"
                    class="flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 hover:border-blue-300 hover:bg-blue-50 text-slate-700 hover:text-blue-700 rounded-xl font-bold text-sm transition-all">
                <i class="fas fa-rotate" :class="syncing ? 'fa-spin text-blue-500' : 'text-blue-500'"></i>
                <span x-text="syncing ? 'Syncing from ERP...' : 'Sync Prices from ERP'"></span>
            </button>
            @endif

            <a href="{{ route('costing.boms.index') }}" id="btn-manage-boms"
               class="flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white rounded-xl font-bold text-sm transition-all shadow-md shadow-amber-200">
                <i class="fas fa-flask"></i> Manage Costing BOMs
            </a>

            @if(Auth::user()->hasFeature('costing', 'export'))
            <button @click="exportPdf()" id="btn-export-pdf"
               class="flex items-center gap-2 px-4 py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl font-bold text-sm transition-all shadow-sm shadow-rose-200">
                <i class="fas fa-file-pdf"></i> Export PDF
            </button>
            @endif
        </div>
    </div>

    {{-- ══════════════════════════════════════════
         TAB 1: COST CALCULATOR
         ══════════════════════════════════════════ --}}
    <div class="grid grid-cols-12 gap-6">

        {{-- ── Left: Calculator Panel ── --}}
        <div class="col-span-12 lg:col-span-8 space-y-5">

            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-8 h-8 rounded-xl bg-amber-50 border border-amber-100 flex items-center justify-center">
                        <i class="fas fa-coins text-amber-500 text-sm"></i>
                    </div>
                    <div>
                        <div class="font-black text-slate-800 text-sm">Batch Cost Calculator</div>
                        <div class="text-xs text-slate-500">Select finished goods → enter quantities, purity & density → click Calculate Cost</div>
                    </div>
                </div>

                {{-- Info Banner --}}
                <div class="flex items-start gap-3 bg-blue-50 border border-blue-100 rounded-xl p-3 mb-4">
                    <i class="fas fa-circle-info text-blue-400 text-sm mt-0.5"></i>
                    <div class="text-[11px] text-slate-600 leading-relaxed">
                        <strong>Technical Purity (%)</strong> increases the required quantity of raw materials labeled as <strong class="text-amber-800">TECHNICAL</strong> to compensate for lower purity.
                        <strong class="block mt-1">Density (g/ml)</strong> adjusts the batch weight multiplier for liquid finished goods (e.g. EC, SL formulations).
                        @if($lastSync)
                        <span class="block mt-1 text-emerald-600 font-bold">✅ Prices synced: {{ \Carbon\Carbon::parse($lastSync)->format('d M Y, h:i A') }}</span>
                        @else
                        <span class="block mt-1 text-rose-600 font-bold">⚠️ No prices synced yet — click "Sync Prices from ERP" above.</span>
                        @endif
                    </div>
                </div>

                {{-- Filters --}}
                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input type="text" x-model="search" @input="filterProducts()"
                               placeholder="Search product..."
                               class="w-full pl-8 pr-3 py-2.5 text-sm border border-slate-200 rounded-xl focus:ring-2 focus:ring-amber-400 focus:border-amber-400 outline-none transition font-medium">
                    </div>
                    <select x-model="typeFilter" @change="filterProducts()"
                            class="w-full px-3 py-2.5 text-sm border border-slate-200 rounded-xl focus:ring-2 focus:ring-amber-400 outline-none font-medium text-slate-700">
                        <option value="">All Types</option>
                        @foreach($productTypes as $type)
                        <option value="{{ $type->id }}">{{ $type->type_name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Product List --}}
                <div class="space-y-2 max-h-[400px] overflow-y-auto pr-1" id="product-list">
                    @foreach($products as $product)
                    @php
                        $hasCost = ($costData[$product->id]['has_recipe'] ?? false) && ($costData[$product->id]['cost_per_unit'] ?? 0) > 0;
                        $costPerUnit = $costData[$product->id]['cost_per_unit'] ?? 0;
                    @endphp
                    <div class="product-card flex items-center gap-3 p-3 rounded-xl border border-slate-100 hover:border-amber-200 hover:bg-amber-50/30 transition-all cursor-pointer group"
                         data-id="{{ $product->id }}"
                         data-name="{{ $product->name }}"
                         data-pack="{{ $product->pack_name }}"
                         data-type="{{ $product->product_type_id }}"
                         data-search="{{ strtolower($product->name . ' ' . $product->item_code) }}"
                         @click="toggleProduct({{ $product->id }}, '{{ addslashes($product->name) }}', '{{ addslashes($product->pack_name) }}')">
                        <div class="w-9 h-9 rounded-xl flex-shrink-0 flex items-center justify-center text-white text-xs font-black
                                    {{ $hasCost ? 'bg-gradient-to-br from-amber-400 to-orange-500' : 'bg-gradient-to-br from-slate-300 to-slate-400' }}">
                            <i class="fas fa-box"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-bold text-slate-800 truncate group-hover:text-amber-800">{{ $product->name }}</div>
                            <div class="text-xs text-slate-400 font-medium">{{ $product->pack_name }} &bull; {{ $product->type->type_name ?? '—' }}</div>
                        </div>
                        <div class="text-right flex-shrink-0">
                            @if($hasCost)
                            <div class="text-xs font-black text-amber-700">₹ {{ number_format($costPerUnit, 2) }}</div>
                            <div class="text-[9px] text-amber-500 font-bold uppercase">per unit</div>
                            @else
                            <div class="text-[10px] text-slate-400 font-bold">{{ $costData[$product->id]['has_recipe'] ? 'No price' : 'No costing BOM' }}</div>
                            @endif
                        </div>
                        <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center flex-shrink-0 transition-all"
                             :class="isSelected({{ $product->id }}) ? 'bg-amber-500 border-amber-500' : 'border-slate-200 group-hover:border-amber-400'">
                            <i class="fas fa-check text-white text-[8px]" x-show="isSelected({{ $product->id }})"></i>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Selected Products & Qty --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5" x-show="selected.length > 0" x-cloak>
                <div class="flex items-center justify-between mb-4">
                    <div class="font-black text-slate-800 text-sm flex items-center gap-2">
                        <i class="fas fa-list-check text-amber-500"></i>
                        Selected Products
                        <span class="px-2 py-0.5 bg-amber-100 text-amber-700 rounded-full text-xs font-black" x-text="selected.length"></span>
                    </div>
                    <button @click="calculate()" :disabled="calculating"
                            class="flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white font-black text-sm rounded-xl transition-all shadow-md shadow-amber-200 disabled:opacity-60">
                        <i class="fas fa-calculator" :class="calculating ? 'fa-spin' : ''"></i>
                        <span x-text="calculating ? 'Calculating...' : 'Calculate Cost'"></span>
                    </button>
                </div>
                <div class="space-y-2">
                    <template x-for="item in selected" :key="item.id">
                        <div class="flex flex-wrap items-center gap-3 p-3 bg-amber-50/40 rounded-xl border border-amber-100">
                            <div class="flex-1 min-w-[180px]">
                                <div class="text-sm font-bold text-slate-800 truncate" x-text="item.name"></div>
                                <div class="text-xs text-slate-500" x-text="item.pack_name || '—'"></div>
                            </div>
                            <div class="flex items-center gap-1.5 flex-shrink-0">
                                <label class="text-[10px] text-slate-500 font-bold">Qty:</label>
                                <input type="number" x-model="item.quantity" min="0.001" step="0.001"
                                       class="w-20 text-center text-xs font-black border border-amber-200 rounded-lg py-1.5 focus:ring-2 focus:ring-amber-400 outline-none">
                            </div>
                            <div class="flex items-center gap-1.5 flex-shrink-0">
                                <label class="text-[10px] text-slate-500 font-bold">Density:</label>
                                <input type="number" x-model="item.density" min="0.1" max="3" step="0.01"
                                       class="w-14 text-center text-xs font-black border border-amber-200 rounded-lg py-1.5 focus:ring-2 focus:ring-amber-400 outline-none">
                            </div>
                            <button @click="removeProduct(item.id)"
                                    class="w-7 h-7 rounded-lg bg-red-50 hover:bg-red-100 border border-red-100 flex items-center justify-center text-red-400 hover:text-red-600 transition-all flex-shrink-0">
                                <i class="fas fa-times text-xs"></i>
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- ── Right: Results Panel ── --}}
        <div class="col-span-12 lg:col-span-4 space-y-5">

            {{-- Grand Total Card --}}
            <div class="bg-gradient-to-br from-amber-500 to-orange-600 rounded-2xl p-6 text-white relative overflow-hidden shadow-xl shadow-amber-200">
                <div class="absolute -top-8 -right-8 w-32 h-32 bg-white/10 rounded-full"></div>
                <div class="relative z-10">
                    <div class="text-[10px] font-black uppercase tracking-widest text-white/70 mb-1">Total Manufacturing Cost</div>
                    <div class="text-4xl font-black tracking-tight" x-text="grandTotal > 0 ? '₹ ' + grandTotal.toLocaleString('en-IN', {minimumFractionDigits: 2}) : '—'">—</div>
                    <div class="mt-3 pt-3 border-t border-white/20 flex items-center justify-between">
                        <div class="text-[10px] font-bold text-white/60 uppercase tracking-wider">
                            <span x-text="results.length"></span> product(s) calculated
                        </div>
                        <div x-show="results.length > 0" class="px-2 py-1 bg-white/20 rounded-lg text-[10px] font-black uppercase tracking-wider">Live</div>
                    </div>
                </div>
            </div>

            {{-- Last Sync --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-blue-50 flex items-center justify-center">
                    <i class="fas fa-clock-rotate-left text-blue-500 text-sm"></i>
                </div>
                <div class="flex-1">
                    <div class="text-xs font-black text-slate-600">Price Data Last Synced</div>
                    <div class="text-xs text-slate-400 font-medium mt-0.5">
                        {{ $lastSync ? \Carbon\Carbon::parse($lastSync)->format('d M Y, h:i A') : 'Never synced — Click Sync Prices' }}
                    </div>
                </div>
                <div class="text-[10px] font-black px-2 py-1 rounded-lg {{ $lastSync ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-500' }}">
                    {{ $lastSync ? 'Synced' : 'Not Synced' }}
                </div>
            </div>

            {{-- Results --}}
            <div x-show="results.length > 0" x-cloak class="space-y-3">
                <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest px-1 flex items-center gap-2">
                    <i class="fas fa-chart-bar text-amber-400"></i> Cost Breakdown
                </div>
                <template x-for="r in results" :key="r.product_id">
                    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden" x-data="{ open: false }">
                        <div class="flex items-center gap-3 p-4 cursor-pointer hover:bg-slate-50 transition-colors" @click="open = !open">
                            <div class="w-9 h-9 rounded-xl flex-shrink-0 flex items-center justify-center text-white text-xs font-black"
                                 :class="r.has_recipe ? 'bg-gradient-to-br from-amber-400 to-orange-500' : 'bg-slate-300'">
                                <i class="fas fa-box"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-black text-slate-800 truncate" x-text="r.product_name"></div>
                                <div class="text-[10px] text-slate-400 font-bold" x-text="'Qty: ' + r.quantity + ' | Purity: ' + r.purity + '% | Formulation: ' + r.formulation + '% | Density: ' + r.density + ' | ₹' + (r.cost_per_unit||0).toLocaleString('en-IN',{minimumFractionDigits:2}) + '/unit'"></div>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <div class="text-sm font-black text-amber-600" x-text="'₹ ' + (r.total_cost||0).toLocaleString('en-IN',{minimumFractionDigits:2})"></div>
                                <div class="text-[9px] text-slate-400 font-bold" x-text="r.breakdown.length + ' RM items'"></div>
                            </div>
                            <i class="fas fa-chevron-down text-slate-400 text-xs transition-transform flex-shrink-0" :class="open ? 'rotate-180' : ''"></i>
                        </div>
                        <div x-show="open" x-cloak class="border-t border-slate-100">
                            <div class="overflow-x-auto">
                                <table class="w-full text-xs">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="text-left px-4 py-2 font-black text-slate-500 uppercase tracking-wider text-[9px]">Raw Material</th>
                                            <th class="text-right px-3 py-2 font-black text-slate-500 uppercase tracking-wider text-[9px]">Qty</th>
                                            <th class="text-right px-3 py-2 font-black text-slate-500 uppercase tracking-wider text-[9px]">Rate</th>
                                            <th class="text-right px-4 py-2 font-black text-slate-500 uppercase tracking-wider text-[9px]">Cost</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-50">
                                        <template x-for="(rm, i) in r.breakdown" :key="i">
                                            <tr class="hover:bg-amber-50/30">
                                                <td class="px-4 py-2">
                                                    <div class="font-bold text-slate-700 truncate max-w-[140px]" x-text="rm.rm_name"></div>
                                                    <div class="text-[9px] text-slate-400" x-text="rm.item_code + ' • ' + rm.uom"></div>
                                                </td>
                                                <td class="px-3 py-2 text-right font-bold text-slate-600" x-text="rm.required_qty"></td>
                                                <td class="px-3 py-2 text-right">
                                                    <span x-show="rm.has_price" class="font-bold text-slate-600" x-text="'₹' + rm.price.toLocaleString('en-IN',{minimumFractionDigits:2})"></span>
                                                    <span x-show="!rm.has_price" @click.stop="promptPrice(rm)" class="font-black text-rose-500 cursor-pointer hover:underline">Set price ✎</span>
                                                </td>
                                                <td class="px-4 py-2 text-right font-black" :class="rm.has_price ? 'text-amber-700' : 'text-slate-400'"
                                                    x-text="rm.has_price ? '₹' + rm.sub_cost.toLocaleString('en-IN',{minimumFractionDigits:2}) : '—'"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                    <tfoot class="bg-amber-50 border-t border-amber-100">
                                        <tr>
                                            <td colspan="3" class="px-4 py-2.5 font-black text-amber-800 text-xs uppercase tracking-wider">Total</td>
                                            <td class="px-4 py-2.5 text-right font-black text-amber-700" x-text="'₹ ' + (r.total_cost||0).toLocaleString('en-IN',{minimumFractionDigits:2})"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <!-- Size-wise Finished Goods Costing Card Breakdown -->
                            <template x-if="r.packing_costs && r.packing_costs.length > 0">
                                <div class="p-4 border-t border-slate-100 bg-slate-50/50 space-y-3">
                                    <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-1.5">
                                        <i class="fas fa-box-open text-orange-500"></i> Size-Wise Finished Goods Costing (Packed Cost)
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <template x-for="pc in r.packing_costs" :key="pc.pricelist_id">
                                            <div class="bg-white border border-slate-200 rounded-xl p-3 shadow-xs hover:border-orange-200 transition-all">
                                                <div class="flex items-center justify-between border-b pb-2 mb-2">
                                                    <div>
                                                        <span class="font-extrabold text-slate-700 text-xs uppercase" x-text="pc.size"></span>
                                                        <span class="text-[9px] font-semibold text-slate-400 block" x-text="pc.fg_name"></span>
                                                    </div>
                                                    <span class="font-mono text-[9px] font-black text-emerald-700 bg-emerald-50 border border-emerald-100 px-2 py-0.5 rounded-lg" x-text="'Packed: ₹' + (pc.total_cost || 0).toFixed(2)"></span>
                                                </div>
                                                <div class="space-y-1 text-[11px] font-semibold text-slate-600">
                                                    <div class="flex justify-between">
                                                        <span>Bulk Liquid (CF1: <span x-text="pc.cf1"></span>):</span>
                                                        <span class="font-bold text-slate-800" x-text="'₹' + (pc.bulk_cost || 0).toFixed(2)"></span>
                                                    </div>
                                                    <div class="flex justify-between">
                                                        <span>Packing Materials Cost:</span>
                                                        <span class="font-bold text-slate-800" x-text="'₹' + (pc.pm_cost || 0).toFixed(2)"></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Empty State --}}
            <div x-show="results.length === 0" class="bg-white rounded-2xl border-2 border-dashed border-slate-200 p-12 text-center">
                <div class="w-14 h-14 rounded-2xl bg-amber-50 border border-amber-100 flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-coins text-2xl text-amber-300"></i>
                </div>
                <p class="text-slate-500 font-bold text-sm">Select products &amp; click</p>
                <p class="text-amber-600 font-black text-sm">Calculate Cost</p>
            </div>
        </div>
    </div>

    {{-- Price Edit Modal --}}
    <div x-show="priceModal.show" x-cloak
         class="fixed inset-0 z-[110] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
        <div class="bg-white w-full max-w-sm rounded-2xl shadow-2xl p-6" @click.outside="priceModal.show = false">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center"><i class="fas fa-pen text-amber-500"></i></div>
                <div>
                    <div class="font-black text-slate-800">Set Price</div>
                    <div class="text-xs text-slate-500" x-text="priceModal.rm_name"></div>
                </div>
            </div>
            <label class="text-xs font-black text-slate-600 uppercase tracking-wider mb-1.5 block">Price per Unit (₹)</label>
            <input type="number" x-model="priceModal.price" min="0" step="0.01"
                   class="w-full px-4 py-3 text-lg font-black border border-slate-200 rounded-xl focus:ring-2 focus:ring-amber-400 outline-none mb-4" placeholder="0.00">
            <div class="flex gap-3">
                <button @click="priceModal.show = false" class="flex-1 py-2.5 border border-slate-200 rounded-xl text-sm font-bold text-slate-600">Cancel</button>
                <button @click="savePrice()" class="flex-1 py-2.5 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-sm font-black transition-all">Save</button>
            </div>
        </div>
    </div>

    {{-- Export Form --}}
    <form id="export-form" method="GET" action="{{ route('costing.export') }}" class="hidden">
        <div id="export-fields"></div>
    </form>

</div>

<script>
function costingApp() {
    return {
        selected: [], results: [], grandTotal: 0,
        calculating: false, syncing: false,
        search: '', typeFilter: '',

        // Price Modal
        priceModal: { show: false, item_code: '', rm_name: '', price: '' },

        init() {},

        // ─── Calculator ───────────────────────────────
        isSelected(id) { return this.selected.some(s => s.id === id); },
        toggleProduct(id, name, pack_name) {
            if (this.isSelected(id)) { this.selected = this.selected.filter(s => s.id !== id); }
            else {
                const matches = [...name.matchAll(/(\d+(?:\.\d+)?)\s*%/g)];
                const defaultFormulation = matches.length > 0 ? matches.reduce((sum, m) => sum + parseFloat(m[1]), 0) : 100;
                this.selected.push({ id, name, pack_name, quantity: 1, purity: 100, formulation: defaultFormulation, density: 1.0 });
            }
        },
        removeProduct(id) { this.selected = this.selected.filter(s => s.id !== id); },

        filterProducts() {
            const q = this.search.toLowerCase(), tid = this.typeFilter;
            document.querySelectorAll('.product-card').forEach(el => {
                const ms = !q || el.dataset.search.includes(q);
                const mt = !tid || el.dataset.type == tid;
                el.style.display = (ms && mt) ? '' : 'none';
            });
        },

        async calculate() {
            if (!this.selected.length) return;
            this.calculating = true;
            try {
                const resp = await fetch('{{ route('costing.calculate') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ products: this.selected.map(s => ({ id: s.id, quantity: parseFloat(s.quantity) || 1, density: parseFloat(s.density) || 1.0 })) })
                });
                const data = await resp.json();
                if (data.success) { this.results = data.results; this.grandTotal = data.grand_total; }
                else alert(data.message || 'Calculation failed.');
            } catch(e) { alert('Network error.'); }
            finally { this.calculating = false; }
        },

        async syncPrices() {
            this.syncing = true;
            try {
                const resp = await fetch('{{ route('costing.fetch-prices') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
                });
                const data = await resp.json();
                alert(data.message || (data.success ? 'Prices synced!' : 'Sync failed.'));
                if (data.success) location.reload();
            } catch(e) { alert('Sync failed.'); }
            finally { this.syncing = false; }
        },

        promptPrice(rm) { this.priceModal = { show: true, item_code: rm.item_code, rm_name: rm.rm_name, price: rm.price || '' }; },
        async savePrice() {
            const resp = await fetch('{{ route('costing.update-price') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                body: JSON.stringify({ item_code: this.priceModal.item_code, price_per_unit: this.priceModal.price })
            });
            const data = await resp.json();
            if (data.success) { this.priceModal.show = false; this.calculate(); }
            else alert(data.message || 'Failed.');
        },

        exportPdf() {
            if (!this.selected.length) {
                alert('Please select at least one product to export.');
                return;
            }
            const form = document.getElementById('export-form');
            const fields = document.getElementById('export-fields');
            fields.innerHTML = '';

            this.selected.forEach(s => {
                const pidInput = document.createElement('input');
                pidInput.type  = 'hidden';
                pidInput.name  = 'product_ids[]';
                pidInput.value = s.id;
                fields.appendChild(pidInput);

                const qInput  = document.createElement('input');
                qInput.type   = 'hidden';
                qInput.name   = `quantities[${s.id}]`;
                qInput.value  = s.quantity;
                fields.appendChild(qInput);

                const pInput  = document.createElement('input');
                pInput.type   = 'hidden';
                pInput.name   = `purities[${s.id}]`;
                pInput.value  = s.purity || 100;
                fields.appendChild(pInput);

                const fInput  = document.createElement('input');
                fInput.type   = 'hidden';
                fInput.name   = `formulations[${s.id}]`;
                fInput.value  = s.formulation || 100;
                fields.appendChild(fInput);

                const dInput  = document.createElement('input');
                dInput.type   = 'hidden';
                dInput.name   = `densities[${s.id}]`;
                dInput.value  = s.density || 1.0;
                fields.appendChild(dInput);
            });

            form.submit();
        }
    };
}
</script>
@endsection
