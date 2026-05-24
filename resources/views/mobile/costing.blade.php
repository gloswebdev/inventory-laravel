@extends('layouts.mobile')

@section('content')
<div x-data="mobileCostingApp()" x-init="init()">

    {{-- Page Title --}}
    <div class="mb-6 animate-in fade-in slide-in-from-top duration-500">
        <div class="flex items-center gap-3 mb-1">
            <a href="{{ route('mobile.dashboard') }}"
               class="w-9 h-9 rounded-xl bg-white/60 border border-white flex items-center justify-center text-slate-600 active:scale-90 transition-transform">
                <i class="fas fa-chevron-left text-sm"></i>
            </a>
            <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-yellow-400 to-orange-500 flex items-center justify-center shadow-lg shadow-yellow-200">
                <i class="fas fa-coins text-white text-base"></i>
            </div>
            <div>
                <h2 class="text-xl font-900 text-slate-800 tracking-tighter leading-none">Costing</h2>
                <p class="text-[9px] font-black uppercase tracking-widest text-slate-400 mt-0.5">Manufacturing Cost Calculator</p>
            </div>
        </div>
    </div>

    {{-- Grand Total Banner (shows after calculation) --}}
    <div x-show="grandTotal > 0" x-cloak
         x-transition:enter="transition ease-out duration-500"
         x-transition:enter-start="opacity-0 -translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="mb-5 bg-gradient-to-r from-yellow-500 to-orange-500 p-5 rounded-[1.5rem] text-white relative overflow-hidden shadow-xl shadow-yellow-200">
        <div class="absolute -right-4 -top-4 w-24 h-24 bg-white/10 rounded-full blur-2xl"></div>
        <div class="text-[9px] font-black uppercase tracking-widest opacity-80 mb-1">Total Manufacturing Cost</div>
        <div class="text-3xl font-900 tracking-tighter" x-text="'₹ ' + grandTotal.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></div>
        <div class="text-[10px] font-bold opacity-70 mt-1" x-text="results.length + ' product(s) calculated'"></div>
    </div>

    {{-- Search & Filter --}}
    <div class="mb-4 animate-in fade-in slide-in-from-bottom duration-500 delay-100">
        <div class="relative mb-3">
            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
            <input type="text" x-model="search" @input="filterProducts()"
                   placeholder="Search product..."
                   class="w-full pl-11 pr-4 py-3.5 bg-white/70 backdrop-blur-xl border border-white/80 rounded-2xl text-sm font-bold text-slate-800 placeholder:text-slate-400 outline-none focus:ring-2 focus:ring-yellow-400 focus:border-yellow-400 transition-all shadow-sm">
        </div>
        <div class="overflow-x-auto no-scrollbar">
            <div class="flex gap-2 pb-1">
                <button @click="typeFilter = ''; filterProducts()"
                        :class="typeFilter === '' ? 'bg-yellow-500 text-white shadow-md shadow-yellow-200' : 'bg-white/70 text-slate-600 border border-white'"
                        class="shrink-0 px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all active:scale-95">
                    All
                </button>
                @foreach($types as $type)
                <button @click="typeFilter = '{{ $type->id }}'; filterProducts()"
                        :class="typeFilter === '{{ $type->id }}' ? 'bg-yellow-500 text-white shadow-md shadow-yellow-200' : 'bg-white/70 text-slate-600 border border-white'"
                        class="shrink-0 px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all active:scale-95">
                    {{ $type->type_name }}
                </button>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Product Cards --}}
    <div class="space-y-2 mb-5 animate-in fade-in slide-in-from-bottom duration-500 delay-200" id="mobile-product-list">
        @forelse($products as $product)
        @php
            $priceData = isset($priceMap[$product->item_code]) ? $priceMap[$product->item_code] : 0;
        @endphp
        <div class="product-item"
             data-id="{{ $product->id }}"
             data-name="{{ strtolower($product->name . ' ' . $product->item_code) }}"
             data-type="{{ $product->product_type_id }}">
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
                    @if($priceData > 0)
                    <div class="text-right">
                        <div class="text-xs font-black text-yellow-700">₹{{ number_format($priceData, 2) }}</div>
                        <div class="text-[8px] text-yellow-500 font-bold">per unit</div>
                    </div>
                    @else
                    <div class="text-[9px] text-slate-400 font-bold">No price</div>
                    @endif
                    <div class="w-6 h-6 rounded-full border-2 flex items-center justify-center transition-all"
                         :class="isSelected({{ $product->id }}) ? 'bg-yellow-500 border-yellow-500' : 'border-slate-300'">
                        <i class="fas fa-check text-white text-[8px]" x-show="isSelected({{ $product->id }})"></i>
                    </div>
                </div>
            </div>

            {{-- Quantity, Purity & Density row (shows when selected) --}}
            <div x-show="isSelected({{ $product->id }})" x-cloak
                 class="mt-1.5 px-2 animate-in slide-in-from-top duration-200">
                <div class="bg-yellow-50 border border-yellow-100 rounded-2xl p-3.5 space-y-3 shadow-sm">
                    <!-- Qty Row -->
                    <div class="flex items-center justify-between gap-2">
                        <div class="text-[10px] font-black text-yellow-800 uppercase tracking-wider">Quantity (boxes):</div>
                        <div class="flex items-center gap-1 bg-white border border-yellow-200 rounded-xl overflow-hidden">
                            <button @click.stop="decrementQty({{ $product->id }})"
                                    class="w-8 h-8 flex items-center justify-center text-yellow-600 hover:bg-yellow-50 font-black text-sm active:bg-yellow-100 transition-colors">−</button>
                            <input type="number"
                                   :value="getQty({{ $product->id }})"
                                   @input.stop="setQty({{ $product->id }}, $event.target.value)"
                                   min="0.001" step="0.001"
                                   class="w-16 text-center font-black text-xs text-slate-800 outline-none py-1 border-x border-yellow-100">
                            <button @click.stop="incrementQty({{ $product->id }})"
                                    class="w-8 h-8 flex items-center justify-center text-yellow-600 hover:bg-yellow-50 font-black text-sm active:bg-yellow-100 transition-colors">+</button>
                        </div>
                    </div>
                    
                    <!-- Density Row -->
                    <div class="pt-2.5 border-t border-yellow-100/50 flex items-center justify-between gap-2">
                        <label class="text-[10px] font-black text-yellow-800 uppercase tracking-wider block mb-1">Density (g/ml):</label>
                        <input type="number"
                               :value="getDensity({{ $product->id }})"
                               @input.stop="setDensity({{ $product->id }}, $event.target.value)"
                               min="0.1" max="3" step="0.01"
                               class="w-20 text-center font-black text-xs text-slate-800 bg-white border border-yellow-200 rounded-xl py-1.5 outline-none focus:ring-2 focus:ring-yellow-400">
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="text-center py-12 text-slate-400 font-bold">No products with recipes found.</div>
        @endforelse
    </div>

    {{-- Calculate Button (sticky) --}}
    <div x-show="selected.length > 0" x-cloak class="sticky bottom-28 mb-3 z-30">
        <div class="glass-premium rounded-2xl p-3 flex items-center gap-3 shadow-xl border-white/50">
            <div class="flex-1">
                <div class="text-[10px] font-black text-slate-600 uppercase tracking-wider" x-text="selected.length + ' product(s) selected'"></div>
            </div>
            <button @click="calculate()" :disabled="calculating"
                    class="flex items-center gap-2 px-5 py-3 bg-gradient-to-r from-yellow-500 to-orange-500 text-white font-black text-sm rounded-xl shadow-lg shadow-yellow-200 active:scale-95 transition-all disabled:opacity-60">
                <i class="fas fa-calculator" :class="calculating ? 'fa-spin' : ''"></i>
                <span x-text="calculating ? 'Calculating...' : 'Calculate'"></span>
            </button>
        </div>
    </div>

    {{-- Results --}}
    <div x-show="results.length > 0" x-cloak class="space-y-4 mt-2 pb-20">
        <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest px-1 flex items-center gap-2">
            <i class="fas fa-chart-pie text-yellow-400"></i> Cost Breakdown
        </div>

        <template x-for="r in results" :key="r.product_id">
            <div class="bg-white/80 backdrop-blur-xl border border-white rounded-2xl overflow-hidden shadow-sm"
                 x-data="{ open: false }">
                {{-- Product Header --}}
                <div class="p-4 flex items-center gap-3 cursor-pointer active:bg-yellow-50 transition-colors"
                     @click="open = !open">
                    <div class="w-10 h-10 rounded-xl flex-shrink-0 flex items-center justify-center text-white font-black"
                         :class="r.has_recipe ? 'bg-gradient-to-br from-yellow-400 to-orange-500' : 'bg-slate-300'">
                        <i class="fas fa-box text-sm"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="font-900 text-slate-800 text-[13px] truncate" x-text="r.product_name"></div>
                        <div class="text-[9px] font-bold text-slate-400 mt-0.5"
                             x-text="'Qty: ' + r.quantity + ' | Purity: ' + r.purity + '% | Formulation: ' + r.formulation + '% | Density: ' + r.density + ' | ₹' + (r.cost_per_unit || 0).toLocaleString('en-IN', {minimumFractionDigits:2}) + '/unit'"></div>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <div class="font-900 text-yellow-700 text-base" x-text="'₹' + (r.total_cost || 0).toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2})"></div>
                        <div class="text-[8px] text-slate-400 font-bold" x-text="r.breakdown.length + ' RM items'"></div>
                    </div>
                    <i class="fas fa-chevron-down text-slate-400 text-xs transition-transform flex-shrink-0"
                       :class="open ? 'rotate-180' : ''"></i>
                </div>

                {{-- Breakdown --}}
                <div x-show="open" x-cloak class="border-t border-slate-100">
                    <template x-for="(rm, i) in r.breakdown" :key="i">
                        <div class="flex items-start gap-3 px-4 py-3 border-b border-slate-50 last:border-0">
                            <div class="w-2 h-2 rounded-full mt-1.5 flex-shrink-0"
                                 :class="rm.has_price ? 'bg-yellow-400' : 'bg-slate-300'"></div>
                            <div class="flex-1 min-w-0">
                                <div class="text-[12px] font-bold text-slate-700 leading-tight" x-text="rm.rm_name"></div>
                                <div class="text-[9px] text-slate-400 font-bold mt-0.5" x-text="rm.required_qty + ' ' + rm.uom + ' needed'"></div>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <div class="text-[11px] font-black" :class="rm.has_price ? 'text-yellow-700' : 'text-slate-400'"
                                     x-text="rm.has_price ? '₹' + rm.sub_cost.toLocaleString('en-IN', {minimumFractionDigits:2}) : '—'"></div>
                                <div class="text-[9px] text-slate-400 font-bold"
                                     x-text="rm.has_price ? '@₹' + rm.price.toLocaleString('en-IN', {minimumFractionDigits:2}) : 'No price set'"></div>
                            </div>
                        </div>
                    </template>
                    <div class="px-4 py-3 bg-yellow-50 border-t border-yellow-100 flex justify-between items-center">
                        <div class="text-[10px] font-black text-yellow-800 uppercase tracking-wider">Total Cost</div>
                        <div class="font-900 text-yellow-700" x-text="'₹ ' + (r.total_cost || 0).toLocaleString('en-IN', {minimumFractionDigits:2})"></div>
                    </div>
                </div>
            </div>
        </template>

        {{-- Export PDF --}}
        <button @click="exportPdf()"
                class="w-full flex items-center justify-center gap-2 py-4 bg-slate-800 text-white font-black rounded-2xl text-sm active:scale-95 transition-all shadow-lg mt-2">
            <i class="fas fa-file-pdf text-red-400"></i>
            Export Cost Report PDF
        </button>
    </div>

    {{-- Hidden export form --}}
    <form id="export-form" method="POST" action="{{ route('mobile.costing.export') }}" class="hidden">
        @csrf
        <div id="export-fields"></div>
    </form>

</div>

<script>
function mobileCostingApp() {
    return {
        selected: [],
        results: [],
        grandTotal: 0,
        calculating: false,
        search: '',
        typeFilter: '',

        init() {},

        isSelected(id) {
            return this.selected.some(s => s.id === id);
        },

        getQty(id) {
            const item = this.selected.find(s => s.id === id);
            return item ? item.quantity : 1;
        },

        setQty(id, val) {
            const item = this.selected.find(s => s.id === id);
            if (item) item.quantity = Math.max(0.001, parseFloat(val) || 0.001);
        },

        incrementQty(id) {
            const item = this.selected.find(s => s.id === id);
            if (item) item.quantity = Math.round((item.quantity + 1) * 1000) / 1000;
        },

        decrementQty(id) {
            const item = this.selected.find(s => s.id === id);
            if (item) item.quantity = Math.max(1, item.quantity - 1);
        },

        getPurity(id) {
            const item = this.selected.find(s => s.id === id);
            return item ? item.purity : 100;
        },

        setPurity(id, val) {
            const item = this.selected.find(s => s.id === id);
            if (item) item.purity = Math.max(1, Math.min(100, parseFloat(val) || 100));
        },

        getFormulation(id) {
            const item = this.selected.find(s => s.id === id);
            return item ? item.formulation : 100;
        },

        setFormulation(id, val) {
            const item = this.selected.find(s => s.id === id);
            if (item) item.formulation = Math.max(0.1, Math.min(100, parseFloat(val) || 100));
        },

        getDensity(id) {
            const item = this.selected.find(s => s.id === id);
            return item ? item.density : 1.0;
        },

        setDensity(id, val) {
            const item = this.selected.find(s => s.id === id);
            if (item) item.density = Math.max(0.1, Math.min(3, parseFloat(val) || 1.0));
        },

        toggleProduct(id, name, pack_name) {
            if (this.isSelected(id)) {
                this.selected = this.selected.filter(s => s.id !== id);
            } else {
                const match = name.match(/(\d+(?:\.\d+)?)\s*%/);
                const defaultFormulation = match ? parseFloat(match[1]) : 100;
                this.selected.push({ id, name, pack_name, quantity: 1, purity: 100, formulation: defaultFormulation, density: 1.0 });
            }
        },

        filterProducts() {
            const q   = this.search.toLowerCase();
            const tid = this.typeFilter;
            document.querySelectorAll('.product-item').forEach(el => {
                const matchSearch = !q || el.dataset.name.includes(q);
                const matchType   = !tid || el.dataset.type == tid;
                el.style.display  = (matchSearch && matchType) ? '' : 'none';
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
                        products: this.selected.map(s => ({
                            id: s.id,
                            quantity: parseFloat(s.quantity) || 1,
                            density: parseFloat(s.density) || 1.0
                        }))
                    }),
                });
                const data = await resp.json();
                if (data.success) {
                    this.results    = data.results;
                    this.grandTotal = data.grand_total;
                    setTimeout(() => window.scrollTo({ top: 0, behavior: 'smooth' }), 200);
                } else {
                    alert(data.message || 'Calculation failed.');
                }
            } catch (e) {
                alert('Network error. Please try again.');
            } finally {
                this.calculating = false;
            }
        },

        exportPdf() {
            const form   = document.getElementById('export-form');
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

                const dInput  = document.createElement('input');
                dInput.type   = 'hidden';
                dInput.name   = `densities[${s.id}]`;
                dInput.value  = s.density || 1.0;
                fields.appendChild(dInput);
            });

            form.submit();
        },
    };
}
</script>
@endsection
