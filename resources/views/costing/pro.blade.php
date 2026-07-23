@extends('layouts.app')

@section('header', 'Costing Dashboard')

@section('content')
<style>
    .pro-glass {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid rgba(249, 115, 22, 0.12);
    }
    .text-gradient {
        background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    .glow-dot {
        box-shadow: 0 0 8px #f97316;
    }
</style>

<div x-data="costingDashboardApp()" x-init="init()" class="space-y-6">

    {{-- ══ Header Section ══ --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-orange-500 glow-dot animate-pulse"></span>
                <span class="text-xs font-black text-orange-600 uppercase tracking-widest">Master Dashboard</span>
            </div>
            <h1 class="text-3xl font-black text-slate-800 tracking-tight mt-1 flex items-center gap-2">
                Costing <span class="text-gradient">Dashboard</span>
            </h1>
            <p class="text-slate-500 text-sm font-medium mt-0.5">Alphabetical (A-Z) BOM batch costs, density rates, packaging totals & live interactive cost calculator</p>
        </div>
        <div class="flex items-center gap-3">
            @if($apiSuccess)
            <div class="px-4 py-2 bg-emerald-500 text-white font-black text-xs uppercase tracking-widest rounded-xl shadow-md shadow-emerald-200 flex items-center gap-1.5">
                <i class="fas fa-check-circle animate-pulse"></i> ERP API Connected
            </div>
            @else
            <div class="px-4 py-2 bg-amber-500 text-white font-black text-xs uppercase tracking-widest rounded-xl shadow-md shadow-amber-200 flex items-center gap-1.5">
                <i class="fas fa-exclamation-triangle"></i> Local Cache Mode
            </div>
            @endif

            @if(Auth::user()->hasPermission('costing_bom', 'create'))
            <button @click="openBomModal()" class="px-4 py-2 bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-600 hover:to-orange-700 text-white font-black text-xs rounded-xl shadow-md transition-all flex items-center gap-2">
                <i class="fas fa-plus"></i> Add Costing BOM
            </button>
            @endif
        </div>
    </div>

    {{-- ══ Search & Filter Bar ══ --}}
    <div class="pro-glass p-4 rounded-2xl shadow-sm border border-slate-100 flex flex-wrap items-center justify-between gap-4">
        {{-- Search input --}}
        <div class="flex-1 min-w-[240px] relative">
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Search BOMs</label>
            <div class="relative">
                <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input type="text" x-model="search" placeholder="Search BOM name, code, or packaging product..." class="w-full bg-white border border-slate-200 rounded-xl py-2.5 pl-10 pr-4 text-sm font-semibold text-slate-800 focus:ring-2 focus:ring-orange-500/20 focus:border-orange-500 outline-none transition-all shadow-sm">
            </div>
        </div>

        {{-- Product Filter Dropdown --}}
        <div class="w-full sm:w-72">
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Filter By BOM Product</label>
            <select x-model="selectedProductFilter" class="w-full bg-white border border-slate-200 rounded-xl py-2.5 px-3 text-sm font-bold text-slate-800 focus:ring-2 focus:ring-orange-500/20 focus:border-orange-500 outline-none transition-all shadow-sm cursor-pointer">
                <option value="">All Products (A to Z)</option>
                <template x-for="pName in uniqueProductNames" :key="pName">
                    <option :value="pName" x-text="pName"></option>
                </template>
            </select>
        </div>

        <div class="flex items-center gap-3 pt-4 sm:pt-0">
            <div class="bg-orange-50 border border-orange-100 text-orange-800 px-3.5 py-2 rounded-xl flex items-center gap-2 text-xs font-bold">
                <i class="fas fa-boxes text-orange-500"></i> Total BOMs: <span class="font-black text-slate-900" x-text="filteredBoms.length"></span>
            </div>
            <button @click="resetFilters()" x-show="search || selectedProductFilter || selectedRate.value !== null || selectedPm.cost !== null" x-cloak class="px-3.5 py-2 bg-rose-50 border border-rose-200 text-rose-600 font-bold text-xs rounded-xl hover:bg-rose-100 transition-colors flex items-center gap-1.5">
                <i class="fas fa-rotate-left"></i> Clear Filters
            </button>
        </div>
    </div>

    {{-- ══ BOM Costing Table (A to Z) ══ --}}
    <div class="pro-glass rounded-3xl overflow-hidden shadow-sm border border-slate-100">
        <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/60 flex justify-between items-center flex-wrap gap-2">
            <div>
                <h2 class="font-extrabold text-slate-800 text-base flex items-center gap-2">
                    <i class="fas fa-sort-alpha-down text-orange-500"></i> All BOMs (A to Z)
                </h2>
                <p class="text-xs text-slate-400 font-bold mt-0.5">Click radio buttons to calculate combination value, or click "View BOM" to inspect/modify BOM</p>
            </div>
            <div class="text-[11px] font-black uppercase tracking-wider text-slate-400 bg-slate-100 px-3 py-1 rounded-lg">
                Sorted Alphabetically A &rarr; Z
            </div>
        </div>

        <div class="overflow-x-auto max-h-[70vh] custom-scrollbar">
            <table class="w-full text-left border-collapse">
                <thead class="sticky top-0 z-20 bg-slate-50/95 backdrop-blur-md shadow-sm border-b border-slate-200">
                    <tr>
                        <th class="py-3.5 px-5 text-[10px] font-black text-slate-400 uppercase tracking-widest min-w-[220px] bg-slate-50/95">BOM Name (A-Z) & View BOM</th>
                        <th class="py-3.5 px-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center whitespace-nowrap bg-slate-50/95">Total Batch Size</th>
                        <th class="py-3.5 px-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right whitespace-nowrap bg-slate-50/95">Grand Total (Batch RM)</th>
                        <th class="py-3.5 px-5 text-[10px] font-black text-slate-400 uppercase tracking-widest whitespace-nowrap bg-slate-50/95">W/o Density Rate</th>
                        <th class="py-3.5 px-5 text-[10px] font-black text-slate-400 uppercase tracking-widest whitespace-nowrap bg-slate-50/95">With Density Rate</th>
                        <th class="py-3.5 px-5 text-[10px] font-black text-slate-400 uppercase tracking-widest min-w-[260px] bg-slate-50/95">Related Products, Packing & PM Total</th>
                        <th class="py-3.5 px-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center whitespace-nowrap bg-slate-50/95">Calculation</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    <template x-for="(bom, index) in paginatedBoms" :key="bom.id">
                        <tr class="hover:bg-orange-50/20 transition-colors" :class="isBomActive(bom.id) ? 'bg-orange-50/30' : ''">
                            {{-- BOM Name & View BOM button --}}
                            <td class="py-4 px-5 align-top">
                                <div class="flex items-start gap-3">
                                    <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 text-white font-black text-xs flex items-center justify-center flex-shrink-0 shadow-sm mt-0.5" x-text="(currentPage - 1) * pageSize + index + 1"></div>
                                    <div class="space-y-1.5">
                                        <div class="font-extrabold text-slate-800 text-sm leading-snug flex items-center gap-2 flex-wrap">
                                            <span x-text="bom.product_name"></span>
                                            <template x-if="bom.badge">
                                                <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-wider" :class="bom.badge === 'small' ? 'bg-orange-500 text-white' : 'bg-purple-600 text-white'" x-text="bom.badge.toUpperCase()"></span>
                                            </template>
                                        </div>
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="font-mono text-[10px] font-bold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded border border-indigo-100/50" x-text="bom.item_code"></span>
                                            <span class="text-[10px] text-slate-500 font-bold bg-slate-100 px-2 py-0.5 rounded" x-text="'Formulation: ' + bom.formulation + '%'"></span>
                                            <template x-if="bom.density && bom.density > 0">
                                                <span class="text-[10px] text-amber-700 font-bold bg-amber-50 px-2 py-0.5 rounded border border-amber-100" x-text="'Density: ' + bom.density"></span>
                                            </template>
                                        </div>
                                        <div>
                                            <button type="button" @click="editBomFromData(bom.raw_bom_data)" class="px-2.5 py-1 bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200 text-xs font-black rounded-lg transition-all inline-flex items-center gap-1.5 shadow-sm hover:shadow cursor-pointer">
                                                <i class="fas fa-eye text-amber-500"></i> View / Edit BOM
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            {{-- Total Batch Size --}}
                            <td class="py-4 px-4 align-top text-center">
                                <span class="inline-flex items-center px-3 py-1.5 rounded-xl text-xs font-black bg-blue-50 text-blue-700 border border-blue-100/60 shadow-sm">
                                    <i class="fas fa-weight-hanging text-[10px] mr-1.5 text-blue-500"></i>
                                    <span x-text="bom.yield_qty + ' ' + bom.yield_uom"></span>
                                </span>
                            </td>

                            {{-- Grand Total --}}
                            <td class="py-4 px-4 align-top text-right whitespace-nowrap">
                                <div class="font-black text-slate-900 text-base" x-text="'₹' + Number(bom.grand_total).toLocaleString('en-IN', {minimumFractionDigits:2})"></div>
                                <div class="text-[10px] text-slate-400 font-bold mt-0.5">Total Batch RM Cost</div>
                            </td>

                            {{-- W/o Density Rate --}}
                            <td class="py-4 px-5 align-top whitespace-nowrap">
                                <div @click="toggleRate(bom, 'wo')" class="flex items-center gap-2 cursor-pointer group p-2 rounded-xl transition-all select-none" :class="isRateSelected(bom.id, 'wo') ? 'bg-orange-100/80 border border-orange-300 ring-2 ring-orange-400/20' : 'hover:bg-slate-100/70'">
                                    <input type="checkbox" :checked="isRateSelected(bom.id, 'wo')" @click.stop="toggleRate(bom, 'wo')" class="w-4 h-4 text-orange-600 focus:ring-orange-500 rounded cursor-pointer">
                                    <div>
                                        <div class="font-black text-slate-800 text-sm group-hover:text-orange-600 transition-colors" x-text="'₹' + bom.wo_density_rate + '/' + bom.yield_uom"></div>
                                        <div class="text-[10px] font-bold text-slate-400">W/o Density Rate</div>
                                    </div>
                                </div>
                            </td>

                            {{-- With Density Rate --}}
                            <td class="py-4 px-5 align-top whitespace-nowrap">
                                <div @click="toggleRate(bom, 'with')" class="flex items-center gap-2 cursor-pointer group p-2 rounded-xl transition-all select-none" :class="isRateSelected(bom.id, 'with') ? 'bg-emerald-100/80 border border-emerald-300 ring-2 ring-emerald-400/20' : 'hover:bg-slate-100/70'">
                                    <input type="checkbox" :checked="isRateSelected(bom.id, 'with')" @click.stop="toggleRate(bom, 'with')" class="w-4 h-4 text-emerald-600 focus:ring-emerald-500 rounded cursor-pointer">
                                    <div>
                                        <div class="font-black text-emerald-700 text-sm group-hover:text-emerald-800 transition-colors" x-text="'₹' + bom.with_density_rate + '/Ltr'"></div>
                                        <div class="text-[10px] font-bold text-slate-400">With Density Rate</div>
                                    </div>
                                </div>
                            </td>

                            {{-- Related Products & Packing PM Total & Per Pack Rate --}}
                            <td class="py-4 px-5 align-top">
                                <template x-if="bom.packing_costs && bom.packing_costs.length > 0">
                                    <div class="space-y-2">
                                        <template x-for="p in bom.packing_costs" :key="p.pricelist_id">
                                            <div @click="togglePm(bom, p)" class="flex items-start gap-2.5 p-2.5 rounded-xl border border-slate-200/80 cursor-pointer transition-all select-none" :class="isPmSelected(bom.id, p.pricelist_id) ? 'bg-orange-100/80 border-orange-400 ring-2 ring-orange-400/20' : 'hover:bg-slate-50 bg-white'">
                                                <input type="checkbox" :checked="isPmSelected(bom.id, p.pricelist_id)" @click.stop="togglePm(bom, p)" class="w-4 h-4 text-orange-600 focus:ring-orange-500 mt-1 flex-shrink-0 rounded cursor-pointer">
                                                <div class="flex-1 min-w-0">
                                                    <div class="flex items-center justify-between gap-1">
                                                        <div class="font-extrabold text-slate-800 text-xs truncate" x-text="p.fg_name"></div>
                                                        <span class="px-2 py-0.5 bg-slate-100 text-slate-700 text-[10px] font-black rounded" x-text="p.size"></span>
                                                    </div>
                                                    <div class="flex items-center justify-between gap-2 mt-1 pt-1 border-t border-slate-100/80">
                                                        <span class="text-[10px] font-bold text-slate-500" x-text="'PM: ₹' + p.pm_cost"></span>
                                                        <span class="text-xs font-black text-indigo-700" x-text="'Per Pack: ₹' + (selectedRate.type === 'with' ? p.unit_total_with : p.unit_total_wo)"></span>
                                                    </div>
                                                    <div class="text-[9.5px] font-bold text-slate-400 mt-0.5" x-text="'(Bulk: ₹' + (selectedRate.type === 'with' ? p.unit_bulk_with : p.unit_bulk_wo) + ' + PM: ₹' + p.pm_cost + ')'"></div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                                <template x-if="!bom.packing_costs || bom.packing_costs.length === 0">
                                    <div class="text-xs text-slate-400 italic font-medium p-2">No packing items linked</div>
                                </template>
                            </td>

                            {{-- Calculation Action --}}
                            <td class="py-4 px-5 align-middle text-center whitespace-nowrap">
                                <template x-if="isBomActive(bom.id)">
                                    <div class="p-3 bg-orange-50 border border-orange-200 rounded-2xl text-center space-y-1.5 shadow-sm">
                                        <div class="text-[9px] font-black uppercase text-orange-600 tracking-wider">Calculation</div>
                                        
                                        <!-- When both Rate & PM item selected for this BOM -->
                                        <template x-if="selectedRate.bom_id === bom.id && selectedPm.bom_id === bom.id && selectedRate.value && selectedPm.cost !== null">
                                            <div>
                                                <div class="text-[11px] font-mono font-extrabold text-indigo-700" x-text="getPackBulkFormula(bom)"></div>
                                                <div class="text-lg font-black text-emerald-700 mt-0.5" x-text="'₹' + getPackTotalCost(bom).toFixed(2) + ' / Pack'"></div>
                                                <div class="text-[9.5px] font-bold text-slate-500" x-text="'(Bulk ₹' + getPackBulkCost(bom).toFixed(2) + ' + PM ₹' + selectedPm.cost + ')'"></div>
                                            </div>
                                        </template>

                                        <!-- When only Rate selected -->
                                        <template x-if="selectedRate.bom_id === bom.id && (selectedPm.bom_id !== bom.id || selectedPm.cost === null)">
                                            <div>
                                                <div class="text-base font-black text-orange-700" x-text="'₹' + selectedRate.value.toFixed(2) + '/' + selectedRate.unit"></div>
                                                <div class="text-[9.5px] font-bold text-slate-500" x-text="selectedRate.type === 'wo' ? 'W/o Density Rate' : 'With Density Rate'"></div>
                                            </div>
                                        </template>

                                        <!-- When only PM selected -->
                                        <template x-if="selectedPm.bom_id === bom.id && (selectedRate.bom_id !== bom.id || !selectedRate.value)">
                                            <div>
                                                <div class="text-base font-black text-indigo-700" x-text="'₹' + selectedPm.cost.toFixed(2)"></div>
                                                <div class="text-[9.5px] font-bold text-slate-500" x-text="'PM Total (' + selectedPm.size + ')'"></div>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                                <template x-if="!isBomActive(bom.id)">
                                    <div class="text-xs text-slate-400 font-bold flex flex-col items-center gap-1 opacity-60">
                                        <i class="fas fa-calculator text-base text-slate-300"></i>
                                        <span>Select Rate or PM</span>
                                    </div>
                                </template>
                            </td>
                        </tr>
                    </template>

                    <template x-if="filteredBoms.length === 0">
                        <tr>
                            <td colspan="7" class="py-16 text-center">
                                <div class="w-16 h-16 rounded-2xl bg-orange-50 border border-orange-100 flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-search text-2xl text-orange-300"></i>
                                </div>
                                <p class="text-slate-500 font-bold mb-2">No costing BOMs match your search/filter.</p>
                                <button @click="resetFilters()" class="px-4 py-2 bg-orange-500 text-white font-bold text-xs rounded-xl shadow">
                                    Clear Filters
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        {{-- Pagination Footer Bar --}}
        <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/60 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <span class="text-xs font-bold text-slate-500">Items per page:</span>
                <select x-model.number="pageSize" @change="currentPage = 1" class="bg-white border border-slate-200 rounded-xl px-3 py-1.5 text-xs font-bold text-slate-700 shadow-sm cursor-pointer focus:ring-2 focus:ring-orange-400 outline-none">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                    <option value="999999">All</option>
                </select>
                <span class="text-xs font-bold text-slate-500 ml-2" x-text="'Showing ' + (filteredBoms.length > 0 ? (currentPage - 1) * pageSize + 1 : 0) + ' to ' + Math.min(currentPage * pageSize, filteredBoms.length) + ' of ' + filteredBoms.length + ' entries'"></span>
            </div>

            <div class="flex items-center gap-2">
                <button @click="if (currentPage > 1) currentPage--" :disabled="currentPage === 1" class="px-3.5 py-1.5 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed shadow-sm transition">
                    &larr; Prev
                </button>
                <div class="flex items-center gap-1">
                    <template x-for="p in totalPages" :key="p">
                        <button @click="currentPage = p" class="w-8 h-8 rounded-xl text-xs font-black transition-all shadow-sm" :class="currentPage === p ? 'bg-orange-500 text-white shadow-orange-200' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'" x-text="p"></button>
                    </template>
                </div>
                <button @click="if (currentPage < totalPages) currentPage++" :disabled="currentPage === totalPages" class="px-3.5 py-1.5 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed shadow-sm transition">
                    Next &rarr;
                </button>
            </div>
        </div>
    </div>

    {{-- ══ Sticky Bottom Interactive Live Calculator Drawer ══ --}}
    <div x-show="selectedRate.value !== null || selectedPm.cost !== null" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full opacity-0" x-transition:enter-end="translate-y-0 opacity-100" class="fixed bottom-4 left-1/2 -translate-x-1/2 z-[90] w-full max-w-4xl px-4">
        <div class="pro-glass bg-slate-900/95 text-white p-5 rounded-3xl shadow-2xl border border-orange-500/30 flex flex-wrap items-center justify-between gap-6 backdrop-blur-xl">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-orange-500 to-amber-600 flex items-center justify-center text-white text-xl font-black shadow-lg shadow-orange-500/30">
                    <i class="fas fa-calculator animate-pulse"></i>
                </div>
                <div>
                    <div class="text-[10px] font-black text-orange-400 uppercase tracking-widest">Live Combined Cost Calculator</div>
                    <div class="font-extrabold text-sm text-slate-100" x-text="getActiveBomName()"></div>
                </div>
            </div>

            <div class="flex items-center gap-6 flex-wrap">
                <!-- Selected Rate -->
                <div class="text-center px-3 border-r border-slate-700">
                    <div class="text-[10px] font-bold text-slate-400">Selected Rate</div>
                    <div class="font-black text-base text-orange-400" x-text="selectedRate.value !== null ? '₹' + selectedRate.value + '/' + selectedRate.unit : 'Not Selected'"></div>
                    <div class="text-[9px] text-slate-500" x-text="selectedRate.type === 'wo' ? 'W/o Density' : (selectedRate.type === 'with' ? 'With Density' : '')"></div>
                </div>

                <!-- Selected PM -->
                <div class="text-center px-3 border-r border-slate-700">
                    <div class="text-[10px] font-bold text-slate-400">Selected PM Total</div>
                    <div class="font-black text-base text-indigo-400" x-text="selectedPm.cost !== null ? '₹' + selectedPm.cost : 'Not Selected'"></div>
                    <div class="text-[9px] text-slate-500" x-text="selectedPm.size ? selectedPm.size : ''"></div>
                </div>

                <!-- Final Calculation Total -->
                <div class="bg-gradient-to-br from-orange-500 to-amber-600 px-5 py-2.5 rounded-2xl text-center shadow-lg shadow-orange-500/20">
                    <div class="text-[9px] font-black text-orange-100 uppercase tracking-widest">Combined Total Value</div>
                    <div class="text-2xl font-black text-white" x-text="'₹' + calculatedCombinedTotal.toFixed(2)"></div>
                    <template x-if="selectedRate.value !== null && selectedPm.cost !== null">
                        <div class="text-[9.5px] font-extrabold text-orange-100 mt-0.5" x-text="selectedPm.size_in_ml ? '(₹' + selectedRate.value + '/1000 × ' + selectedPm.size_in_ml + 'ML) = ₹' + getPackBulkCost({}).toFixed(2) + ' Bulk + ₹' + selectedPm.cost + ' PM = ₹' + getPackTotalCost({}).toFixed(2) + ' / Pack' : '(₹' + selectedRate.value + ' × ' + selectedPm.cf1 + ' Ltr) + ₹' + selectedPm.cost + ' PM = ₹' + calculatedPackAdjustedTotal.toFixed(2) + ' / Pack'"></div>
                    </template>
                </div>

                <button @click="resetSelections()" class="w-9 h-9 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 flex items-center justify-center transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         EXACT COSTING BOM MODAL (Identical to Costing BOMs Master)
         ══════════════════════════════════════════════════════════════ --}}
    <div x-show="bomModal.show" x-cloak
         class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
        <div class="bg-white w-full max-w-2xl rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]"
             @click.outside="bomModal.show = false">

            {{-- Modal Header --}}
            <div class="bg-gradient-to-r from-amber-500 to-orange-500 p-6 text-white flex items-center justify-between flex-shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center">
                        <i class="fas fa-flask text-white"></i>
                    </div>
                    <div>
                        <div class="font-black text-lg tracking-tight" x-text="bomModal.editId ? 'Edit Costing BOM' : 'New Costing BOM'"></div>
                        <div class="text-[10px] font-bold uppercase tracking-widest text-white/70" x-show="!bomModal.form.finished_product_id">Independent Costing Formula</div>
                        <div class="text-[11px] font-extrabold text-amber-50 truncate max-w-[350px] uppercase tracking-wider" x-show="bomModal.form.finished_product_id" x-text="getFinishedProductName(bomModal.form.finished_product_id)"></div>
                    </div>
                </div>
                
                <!-- Per Unit Cost Badging -->
                <template x-if="bomModal.form.finished_product_id && getIngredientsGrandTotal() > 0">
                    <div class="hidden md:flex flex-col gap-1 items-end bg-white border border-slate-150 text-slate-800 px-3.5 py-2 rounded-xl font-sans text-xs font-bold shadow-md">
                        <div>
                            <span>W/o Density per Ltr/Kg Rate: <span class="text-indigo-600 font-extrabold" x-text="'₹' + (getIngredientsGrandTotal() / (parseFloat(bomModal.form.yield_quantity) || 1)).toFixed(2) + '/' + bomModal.form.yield_uom"></span></span>
                        </div>
                        <template x-if="bomModal.form.density && parseFloat(bomModal.form.density) > 0">
                            <div class="text-[11px] text-slate-500 border-t border-slate-100 pt-1 w-full text-right font-medium">
                                <span>With Density per Ltr/Kg Rate: <span class="text-emerald-600 font-extrabold" x-text="'₹' + (getIngredientsGrandTotal() / (parseFloat(bomModal.form.yield_quantity) / parseFloat(bomModal.form.density))).toFixed(2) + '/Ltr'"></span></span>
                            </div>
                        </template>
                    </div>
                </template>

                <button @click="bomModal.show = false" class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center hover:bg-white/30 transition">
                    <i class="fas fa-times text-white"></i>
                </button>
            </div>

            {{-- Visual Step Indicators --}}
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center justify-center flex-shrink-0">
                <div class="flex items-center w-full justify-center max-w-2xl mx-auto gap-x-1 sm:gap-x-2">
                    <!-- Step 1 Indicator -->
                    <div class="flex items-center gap-1 cursor-pointer" @click="bomModal.step = 1">
                        <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-full flex-shrink-0 flex items-center justify-center text-xs font-black transition-all"
                             :class="bomModal.step >= 1 ? 'bg-amber-500 text-white shadow-md shadow-amber-200' : 'bg-slate-200 text-slate-500'">1</div>
                        <span class="text-[11px] sm:text-xs font-bold transition-all hidden sm:inline whitespace-nowrap" :class="bomModal.step === 1 ? 'text-amber-600 font-extrabold' : 'text-slate-400'">Product Info</span>
                    </div>
                    <!-- Connector -->
                    <div class="w-4 sm:w-10 h-0.5 transition-all" :class="bomModal.step >= 2 ? 'bg-amber-400' : 'bg-slate-200'"></div>
                    <!-- Step 2 Indicator -->
                    <div class="flex items-center gap-1 cursor-pointer" @click="bomModal.step = 2">
                        <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-full flex-shrink-0 flex items-center justify-center text-xs font-black transition-all"
                             :class="bomModal.step >= 2 ? 'bg-amber-500 text-white shadow-md shadow-amber-200' : 'bg-slate-200 text-slate-500'">2</div>
                        <span class="text-[11px] sm:text-xs font-bold transition-all hidden sm:inline whitespace-nowrap" :class="bomModal.step === 2 ? 'text-amber-600 font-extrabold' : 'text-slate-400'">Batch Specs</span>
                    </div>
                    <!-- Connector -->
                    <div class="w-4 sm:w-10 h-0.5 transition-all" :class="bomModal.step >= 3 ? 'bg-amber-400' : 'bg-slate-200'"></div>
                    <!-- Step 3 Indicator -->
                    <div class="flex items-center gap-1 cursor-pointer" @click="bomModal.step = 3">
                        <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-full flex-shrink-0 flex items-center justify-center text-xs font-black transition-all"
                             :class="bomModal.step >= 3 ? 'bg-amber-500 text-white shadow-md shadow-amber-200' : 'bg-slate-200 text-slate-500'">3</div>
                        <span class="text-[11px] sm:text-xs font-bold transition-all hidden sm:inline whitespace-nowrap" :class="bomModal.step === 3 ? 'text-amber-600 font-extrabold' : 'text-slate-400'">Ingredients</span>
                    </div>
                    <!-- Connector -->
                    <div class="w-4 sm:w-10 h-0.5 transition-all" :class="bomModal.step >= 4 ? 'bg-amber-400' : 'bg-slate-200'"></div>
                    <!-- Step 4 Indicator -->
                    <div class="flex items-center gap-1 cursor-pointer" @click="bomModal.step = 4">
                        <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-full flex-shrink-0 flex items-center justify-center text-xs font-black transition-all"
                             :class="bomModal.step >= 4 ? 'bg-amber-500 text-white shadow-md shadow-amber-200' : 'bg-slate-200 text-slate-500'">4</div>
                        <span class="text-[11px] sm:text-xs font-bold transition-all hidden sm:inline whitespace-nowrap" :class="bomModal.step === 4 ? 'text-amber-600 font-extrabold' : 'text-slate-400'">Packing</span>
                    </div>
                    <!-- Connector -->
                    <div class="w-4 sm:w-10 h-0.5 transition-all" :class="bomModal.step >= 5 ? 'bg-amber-400' : 'bg-slate-200'"></div>
                    <!-- Step 5 Indicator -->
                    <div class="flex items-center gap-1 cursor-pointer" @click="bomModal.step = 5">
                        <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-full flex-shrink-0 flex items-center justify-center text-xs font-black transition-all"
                             :class="bomModal.step >= 5 ? 'bg-amber-500 text-white shadow-md shadow-amber-200' : 'bg-slate-200 text-slate-500'">5</div>
                        <span class="text-[11px] sm:text-xs font-bold transition-all hidden sm:inline whitespace-nowrap" :class="bomModal.step === 5 ? 'text-amber-600 font-extrabold' : 'text-slate-400'">Calculations</span>
                    </div>
                </div>
            </div>

            {{-- Modal Body --}}
            <div class="flex-1 overflow-y-auto p-6">

                {{-- Step 1: Product Info & Settings --}}
                <div x-show="bomModal.step === 1" class="space-y-5" x-transition>
                    <div class="grid grid-cols-12 gap-4">
                        <div class="col-span-12 md:col-span-4">
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Product Type</label>
                            <select x-model="bomModal.typeFilter" @change="bomModal.form.finished_product_id = ''"
                                    class="w-full px-3 py-2.5 text-sm border border-slate-200 rounded-xl focus:ring-2 focus:ring-amber-400 outline-none font-medium">
                                <option value="">All Types</option>
                                @foreach($types as $type)
                                <option value="{{ $type->id }}">{{ $type->type_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-span-12 md:col-span-8">
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Bom Container *</label>
                            <select x-model="bomModal.form.finished_product_id"
                                    class="w-full px-4 py-3 text-sm border border-slate-200 rounded-xl focus:ring-4 focus:ring-amber-500/10 focus:border-amber-500 outline-none font-semibold text-slate-700 bg-white shadow-sm hover:border-slate-300 transition-all cursor-pointer" required>
                                <option value="">Select Finished Good</option>
                                <template x-for="(p, index) in filteredFGs" :key="p.id">
                                    <option :value="p.id" x-text="(index + 1) + '. ' + p.name + (p.pack_name ? ' ['+p.pack_name+']' : '') + ' ('+p.item_code+')'"></option>
                                </template>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-12 gap-4">
                        <div class="col-span-12 md:col-span-4">
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">BOM Badge</label>
                            <select x-model="bomModal.form.badge"
                                    class="w-full px-3 py-2.5 text-sm border border-slate-200 rounded-xl focus:ring-2 focus:ring-amber-400 outline-none font-medium">
                                <option value="">Default (Standard)</option>
                                <option value="small">Small</option>
                                <option value="big">Big</option>
                                <option value="bulk">Bulk</option>
                            </select>
                        </div>
                        <div class="col-span-12 md:col-span-4">
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Formulation Override %</label>
                            <input type="number" step="0.001" min="0" max="100" x-model="bomModal.form.formulation"
                                   placeholder="e.g. 5.0"
                                   class="w-full px-3 py-2.5 text-sm border border-slate-200 rounded-xl focus:ring-2 focus:ring-amber-400 outline-none font-bold text-slate-800">
                        </div>
                        <div class="col-span-12 md:col-span-4">
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Density (g/ml)</label>
                            <input type="number" step="0.0001" min="0.001" max="10" x-model="bomModal.form.density"
                                   placeholder="e.g. 1.05"
                                   class="w-full px-3 py-2.5 text-sm border border-slate-200 rounded-xl focus:ring-2 focus:ring-amber-400 outline-none font-bold text-slate-800">
                        </div>
                    </div>

                    <template x-if="bomModal.form.finished_product_id">
                        <div class="text-[10px] text-blue-700 font-black mt-1.5 ml-1 bg-blue-50 border border-blue-100 px-3 py-1.5 rounded-xl inline-block">
                            <i class="fas fa-flask"></i> Formulation: <span x-text="bomModal.form.formulation ? bomModal.form.formulation + '%' : getFormulation(bomModal.form.finished_product_id)"></span>
                        </div>
                    </template>
                </div>

                {{-- Step 2: Batch Specifications --}}
                <div x-show="bomModal.step === 2" class="space-y-5" x-transition>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Batch Quantity *</label>
                            <input type="number" step="0.001" x-model="bomModal.form.yield_quantity"
                                   class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:ring-2 focus:ring-amber-400 outline-none font-bold" placeholder="e.g. 1">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Batch UOM *</label>
                            <input type="text" x-model="bomModal.form.yield_uom"
                                   class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:ring-2 focus:ring-amber-400 outline-none font-bold" placeholder="e.g. KG">
                        </div>
                    </div>
                </div>

                {{-- Step 3: Raw Materials / Ingredients --}}
                <div x-show="bomModal.step === 3" class="space-y-5" x-transition>
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest">Raw Materials / Ingredients *</label>
                                <p class="text-[10px] text-slate-400 mt-0.5">Quantities are per 1 batch unit</p>
                            </div>
                            <button type="button" 
                                    @click="if (!hasActiveSolvent() && getRemainingIngredientsQty() > 0) addRMRow(false)"
                                    :disabled="hasActiveSolvent() || getRemainingIngredientsQty() <= 0"
                                    :class="(hasActiveSolvent() || getRemainingIngredientsQty() <= 0) ? 'opacity-50 cursor-not-allowed bg-slate-100 border-slate-200 text-slate-400' : 'bg-amber-50 border-amber-200 text-amber-700 hover:bg-amber-100 hover:shadow-sm'"
                                    class="flex items-center gap-1.5 px-3 py-1.5 border font-black text-xs rounded-lg transition-all">
                                <i class="fas fa-plus-circle text-xs"></i> Add Row
                            </button>
                        </div>

                        {{-- Global RM Type Filter --}}
                        <div class="mb-3">
                            <select x-model="bomModal.rmTypeFilter"
                                    class="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:ring-2 focus:ring-amber-400 outline-none font-bold text-slate-600">
                                <option value="">All Raw Material Types (filter all rows)</option>
                                <template x-for="t in rmTypes" :key="t">
                                    <option :value="t" x-text="t"></option>
                                </template>
                            </select>
                        </div>
                        <template x-for="(item, idx) in bomModal.form.items.filter(i => !i.is_packing)" :key="idx">
                                <div x-effect="if (item.raw_material_id) { const isTech = isTechnical(item.raw_material_id); if (isTech && item.is_technical === '') { item.is_technical = true; } } if (item.is_technical) { item.quantity = calculateSuggestedQty(item); } else if (item.is_solvent) { item.quantity = calculateRemainingQty(item); }"
                                     :class="item.is_solvent ? 'bg-indigo-50/40 border-indigo-300 ring-2 ring-indigo-100/50' : 'bg-amber-50/50 border-amber-100'"
                                     class="grid grid-cols-12 gap-2 p-3 border rounded-xl items-center transition-all duration-200 mb-2">
                                    <div class="col-span-7">
                                        <select x-model="item.raw_material_id"
                                                @change="if (isTechnical(item.raw_material_id)) { item.is_technical = true; } else { item.is_technical = false; }"
                                                class="w-full px-3 py-2.5 text-xs border border-slate-200 rounded-xl focus:ring-4 focus:ring-amber-500/10 focus:border-amber-500 outline-none font-bold text-slate-700 bg-white shadow-sm hover:border-slate-300 transition-all cursor-pointer">
                                            <option value="">Select Raw Material</option>
                                            <template x-for="(rm, index) in getFilteredRMs(item.rm_type_filter, false)" :key="rm.id">
                                                <option :value="rm.id" :selected="rm.id == item.raw_material_id"
                                                         x-text="(index + 1) + '. ' + rm.name + (rm.pack_name ? ' ['+rm.pack_name+']' : '') + ' ('+rm.item_code+')'"></option>
                                            </template>
                                        </select>
                                        <div class="mt-1.5 flex flex-wrap gap-2 items-center">
                                            <label :class="item.is_solvent ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm' : 'bg-slate-100 text-slate-500 border-slate-200'"
                                                    class="inline-flex items-center gap-1.5 cursor-pointer select-none text-[9px] font-black border px-2.5 py-1 rounded-lg hover:bg-opacity-90 transition-all">
                                                <input type="radio" name="solvent_radio" :checked="item.is_solvent"
                                                       @click.prevent="toggleSolvent(item); if (item.is_solvent) { item.is_technical = false; }"
                                                       :class="item.is_solvent ? 'text-indigo-600 focus:ring-indigo-500' : 'text-amber-600 focus:ring-amber-500'"
                                                       class="w-3 h-3 border-slate-300">
                                                Solvent
                                            </label>

                                            <label :class="item.is_technical ? 'bg-amber-600 text-white border-amber-600 shadow-sm' : 'bg-slate-100 text-slate-500 border-slate-200'"
                                                    class="inline-flex items-center gap-1.5 cursor-pointer select-none text-[9px] font-black border px-2.5 py-1 rounded-lg hover:bg-opacity-90 transition-all">
                                                <input type="checkbox" :checked="item.is_technical"
                                                       @click="item.is_technical = !item.is_technical; if (item.is_technical) { item.is_solvent = false; item.quantity = calculateSuggestedQty(item); }"
                                                       class="w-3 h-3 border-slate-300 rounded text-amber-600 focus:ring-amber-500">
                                                Technical
                                            </label>

                                            <template x-if="item.raw_material_id && getMaterialRate(item.raw_material_id) !== null">
                                                <div class="inline-flex items-center gap-1 bg-emerald-50 text-emerald-700 border border-emerald-200 px-2 py-1 rounded-lg text-[9px] font-black shadow-sm">
                                                    <i class="fas fa-tag"></i> Rate: <span x-text="'₹' + getMaterialRate(item.raw_material_id)"></span>
                                                </div>
                                            </template>
                                            <template x-if="item.raw_material_id && getMaterialRate(item.raw_material_id) === null">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <div class="inline-flex items-center gap-1 bg-slate-100 text-slate-500 border border-slate-200 px-2 py-1 rounded-lg text-[9px] font-black">
                                                        <i class="fas fa-tag"></i> Rate: Not Available
                                                    </div>
                                                    <div class="flex items-center gap-1">
                                                        <label class="text-[9px] font-black text-rose-700 uppercase tracking-wider block bg-rose-50 border border-rose-100 px-1.5 py-0.5 rounded">Enter Rate (₹):</label>
                                                        <input type="number" step="0.01" min="0.01" x-model="item.rate"
                                                               class="w-16 px-1.5 py-0.5 text-[10px] font-black border border-rose-200 rounded-lg outline-none focus:ring-1 focus:ring-rose-400 text-center text-rose-800 bg-rose-50/20"
                                                               placeholder="Rate">
                                                    </div>
                                                </div>
                                            </template>

                                            <!-- Transportation Cost Input -->
                                            <template x-if="item.raw_material_id">
                                                <div class="flex items-center gap-1">
                                                    <label class="text-[9px] font-black text-amber-700 uppercase tracking-wider block bg-amber-50 border border-amber-100 px-1.5 py-0.5 rounded">Trans Cost (₹):</label>
                                                    <input type="number" step="0.01" min="0" x-model="item.transportation_cost"
                                                           class="w-14 px-1.5 py-0.5 text-[10px] font-black border border-amber-200 rounded-lg outline-none focus:ring-1 focus:ring-amber-400 text-center text-amber-800 bg-amber-50/20"
                                                           placeholder="5.00">
                                                </div>
                                            </template>

                                            <!-- Calculation Breakdown Display -->
                                            <template x-if="item.raw_material_id && item.quantity && (getMaterialRate(item.raw_material_id) !== null || item.rate)">
                                                <div class="w-full mt-1 bg-slate-50 border border-slate-100 rounded-lg p-1.5 text-[9.5px] font-mono text-slate-600 font-bold">
                                                    <span class="text-blue-700 font-extrabold" x-text="item.quantity"></span> * 
                                                    <span class="text-emerald-700 font-extrabold" x-text="getMaterialRate(item.raw_material_id) !== null ? getMaterialRate(item.raw_material_id) : (item.rate || 0)"></span> = 
                                                    <span class="text-slate-800 font-extrabold" x-text="'₹' + (parseFloat(item.quantity) * (getMaterialRate(item.raw_material_id) !== null ? parseFloat(getMaterialRate(item.raw_material_id)) : (parseFloat(item.rate) || 0))).toFixed(2)"></span>
                                                    <span class="text-slate-400 font-semibold"> + </span>
                                                    <span class="text-blue-700 font-extrabold" x-text="item.quantity"></span> * 
                                                    <span class="text-amber-700 font-extrabold" x-text="item.transportation_cost !== undefined && item.transportation_cost !== '' ? item.transportation_cost : 5"></span> = 
                                                    <span class="text-slate-800 font-extrabold" x-text="'₹' + (parseFloat(item.quantity) * (item.transportation_cost !== undefined && item.transportation_cost !== '' ? parseFloat(item.transportation_cost) : 5)).toFixed(2)"></span>
                                                    <span class="text-indigo-600 font-black" x-text="' (Total: ₹' + ( (parseFloat(item.quantity) * (getMaterialRate(item.raw_material_id) !== null ? parseFloat(getMaterialRate(item.raw_material_id)) : (parseFloat(item.rate) || 0))) + (parseFloat(item.quantity) * (item.transportation_cost !== undefined && item.transportation_cost !== '' ? parseFloat(item.transportation_cost) : 5)) ).toFixed(2) + ')'"></span>
                                                </div>
                                            </template>

                                            <template x-if="item.raw_material_id && item.is_technical">
                                                <div class="flex flex-col gap-2 mt-1.5 w-full">
                                                    <div class="flex flex-wrap gap-2 items-center">
                                                        <!-- Show fetched purity if not null -->
                                                        <template x-if="getPurityVal(item.raw_material_id) !== null">
                                                            <div class="text-[9px] text-amber-700 font-black bg-amber-50 border border-amber-100 px-2 py-1 rounded-lg inline-block">
                                                                <i class="fas fa-percent"></i> Purity (fetched): <span x-text="getPurity(item.raw_material_id)"></span>
                                                            </div>
                                                        </template>
                                                        
                                                        <!-- Show manual purity input if fetched is null -->
                                                        <template x-if="getPurityVal(item.raw_material_id) === null">
                                                            <div class="flex items-center gap-1">
                                                                <label class="text-[9px] font-black text-rose-700 uppercase tracking-wider block bg-rose-50 border border-rose-100 px-1.5 py-0.5 rounded">Enter Purity (%):</label>
                                                                <input type="number" step="0.1" min="0.1" max="100" x-model="item.purity"
                                                                       @input="item.quantity = calculateSuggestedQty(item)"
                                                                       class="w-14 px-1.5 py-0.5 text-[10px] font-black border border-rose-200 rounded-lg outline-none focus:ring-1 focus:ring-rose-400 text-center text-rose-800 bg-rose-50/20"
                                                                       placeholder="100">
                                                            </div>
                                                        </template>
                                                    </div>
                                                    
                                                    <!-- Formulation selection / input -->
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <div class="flex items-center gap-1">
                                                            <label class="text-[9px] font-black text-indigo-700 uppercase tracking-wider block bg-indigo-50 border border-indigo-100 px-1.5 py-0.5 rounded">Formulation (%):</label>
                                                            <input type="number" step="0.001" min="0.001" max="100" x-model="item.formulation"
                                                                   @input="item.quantity = calculateSuggestedQty(item)"
                                                                   class="w-14 px-1.5 py-0.5 text-[10px] font-black border border-indigo-200 rounded-lg outline-none focus:ring-1 focus:ring-indigo-400 text-center text-indigo-800 bg-indigo-50/20"
                                                                   placeholder="0.0">
                                                        </div>
                                                        <div class="flex flex-wrap gap-1">
                                                            <template x-for="pct in getFormulationList(bomModal.form.finished_product_id)" :key="pct">
                                                                <button type="button" @click="item.formulation = pct; item.quantity = calculateSuggestedQty(item)"
                                                                        class="text-[9px] font-extrabold bg-slate-100 hover:bg-amber-100 text-slate-700 hover:text-amber-800 px-1.5 py-0.5 rounded border border-slate-200 hover:border-amber-300 transition-all shadow-sm animate-none"
                                                                        x-text="pct + '%'"></button>
                                                            </template>
                                                        </div>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                    <div class="col-span-3">
                                        <input type="number" step="0.001" x-model="item.quantity"
                                               placeholder="Qty"
                                               :readonly="item.is_technical || item.is_solvent"
                                               :class="(item.is_technical || item.is_solvent) ? 'bg-slate-100 text-slate-500 cursor-not-allowed' : ''"
                                               class="w-full px-2 py-2 text-xs border border-slate-200 rounded-lg focus:ring-1 focus:ring-amber-400 outline-none font-bold text-center">
                                        <template x-if="item.is_technical">
                                            <div class="text-[9px] text-emerald-600 mt-1 text-center font-bold">Auto-calculated</div>
                                        </template>
                                        <template x-if="item.is_solvent">
                                            <div class="text-[9px] text-indigo-600 mt-1 text-center font-bold">Solvent (Balance)</div>
                                        </template>
                                        <template x-if="!item.is_technical && !item.is_solvent">
                                            <div class="text-[9px] text-indigo-600 hover:text-indigo-800 mt-1 text-center cursor-pointer font-black select-none"
                                                 @click="item.quantity = calculateSuggestedQty(item)"
                                                 title="Click to apply: Batch Quantity * Formulation / Purity">
                                                Calc: <span x-text="calculateSuggestedQty(item)"></span>
                                            </div>
                                        </template>
                                    </div>
                                    <div class="col-span-1 text-center text-[10px] font-bold text-slate-400" x-text="getItemUom(item.raw_material_id)"></div>
                                    <div class="col-span-1 flex justify-center">
                                        <button @click="bomModal.form.items.splice(bomModal.form.items.indexOf(item), 1)" class="w-6 h-6 rounded-lg flex items-center justify-center text-red-400 hover:text-red-600 hover:bg-red-50 transition">
                                            <i class="fas fa-trash-alt text-[10px]"></i>
                                        </button>
                                    </div>
                                </div>
                            </template>
                            <div x-show="bomModal.form.items.filter(i => !i.is_packing).length === 0" class="text-center py-6 text-slate-400 text-sm font-bold border-2 border-dashed border-slate-200 rounded-xl">
                                No ingredients added yet — click "Add Row"
                            </div>

                            <!-- Ingredients Quantity Summary -->
                            <template x-if="bomModal.form.items.filter(i => !i.is_packing).length > 0">
                                <div class="mt-4 p-3 bg-slate-50 rounded-xl border border-slate-200 flex justify-between items-center text-xs font-bold text-slate-600 shadow-sm">
                                    <div class="flex items-center gap-3">
                                        <div>
                                            Total Batch Size: <span class="text-slate-800" x-text="bomModal.form.yield_quantity + ' ' + bomModal.form.yield_uom"></span>
                                        </div>
                                        <span class="text-slate-300">|</span>
                                        <div class="bg-indigo-50 border border-indigo-100 text-indigo-700 px-2.5 py-0.5 rounded-lg font-black">
                                            Grand Total: <span x-text="'₹' + getIngredientsGrandTotal().toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                                        </div>
                                    </div>
                                    <div class="flex gap-4">
                                        <div>
                                            Used: <span :class="getUsedIngredientsQty() > parseFloat(bomModal.form.yield_quantity) ? 'text-red-600' : 'text-slate-800'" x-text="getUsedIngredientsQty() + ' ' + bomModal.form.yield_uom"></span>
                                        </div>
                                        <div>
                                            Remaining: <span :class="getRemainingIngredientsQty() < 0 ? 'text-red-600' : 'text-emerald-600'" x-text="getRemainingIngredientsQty() + ' ' + bomModal.form.yield_uom"></span>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                {{-- Step 4: Packing Materials --}}
                <div x-show="bomModal.step === 4" class="space-y-4 max-h-[50vh] overflow-y-auto pr-1" x-transition>
                    <div class="bg-amber-50/40 border border-amber-100/70 p-3.5 rounded-2xl">
                        <h4 class="text-[10px] font-black text-amber-700 uppercase tracking-widest mb-1">Size-Wise Packing Config</h4>
                        <p class="text-[10px] text-slate-400 font-semibold leading-relaxed">Har packing size (1Ltr, 500ml, etc.) ke side me raw packing material custom add karein.</p>
                    </div>

                    <!-- Manual Linking Card -->
                    <div class="bg-slate-50 border border-slate-200/60 p-3.5 rounded-2xl space-y-2.5">
                        <h4 class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Manually Link Finished Good (Pricelist Item)</h4>
                        <div class="grid grid-cols-12 gap-2">
                            <div class="col-span-5">
                                <input type="text" x-model="bomModal.manualSearchQuery" placeholder="Search product name/code..." 
                                       class="w-full px-3 py-1.5 text-xs border border-slate-200 rounded-xl outline-none font-bold text-slate-700 bg-white placeholder-slate-400 shadow-sm focus:ring-2 focus:ring-amber-400">
                            </div>
                            <div class="col-span-5">
                                <select x-model="bomModal.selectedManualPricelistId" 
                                        class="w-full px-3 py-1.5 text-xs border border-slate-200 rounded-xl outline-none font-bold text-slate-700 bg-white shadow-sm focus:ring-2 focus:ring-amber-400">
                                    <option value="">Select Finished Good</option>
                                    <template x-for="p in __pricelists.filter(x => !bomModal.manualSearchQuery || x.item_hd_name.toLowerCase().includes(bomModal.manualSearchQuery.toLowerCase()) || x.user_code.toLowerCase().includes(bomModal.manualSearchQuery.toLowerCase()))" :key="p.id">
                                        <option :value="p.id" x-text="p.item_hd_name + ' (' + p.user_code + ') [Size: ' + p.size + ']'"></option>
                                    </template>
                                </select>
                            </div>
                            <div class="col-span-2">
                                <button type="button" @click="if (bomModal.selectedManualPricelistId) { if (!bomModal.form.manual_pricelist_ids) { bomModal.form.manual_pricelist_ids = []; } if (!bomModal.form.manual_pricelist_ids.includes(parseInt(bomModal.selectedManualPricelistId))) { bomModal.form.manual_pricelist_ids.push(parseInt(bomModal.selectedManualPricelistId)); } bomModal.selectedManualPricelistId = ''; bomModal.manualSearchQuery = ''; }" 
                                        class="w-full bg-amber-500 hover:bg-amber-600 text-white font-black text-[10px] py-2 rounded-xl transition-all shadow-sm">
                                    Link
                                </button>
                            </div>
                        </div>
                    </div>

                    <template x-for="fg in getLinkedPricelistItems(bomModal.form.finished_product_id)" :key="fg.id">
                        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm hover:border-amber-200 hover:shadow-md transition-all duration-300 mb-3">
                            <!-- FG Header Row -->
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3 mb-3">
                                <div>
                                    <div class="font-extrabold text-slate-800 text-xs uppercase tracking-tight" x-text="fg.item_hd_name"></div>
                                    <div class="flex items-center gap-3 mt-0.5">
                                        <span class="text-[10px] text-slate-400 font-bold">Code: <span class="text-slate-600 font-mono" x-text="fg.user_code"></span></span>
                                        <span class="text-[10px] font-extrabold text-rose-600 bg-rose-50 border border-rose-200 px-2 py-0.5 rounded-lg" x-text="'PM Total = ' + getPmTotalRate(fg.id, fg.cf_1)"></span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <template x-if="bomModal.form.manual_pricelist_ids && bomModal.form.manual_pricelist_ids.includes(fg.id)">
                                        <button type="button" @click="bomModal.form.manual_pricelist_ids = bomModal.form.manual_pricelist_ids.filter(id => id != fg.id); bomModal.form.packing_materials = bomModal.form.packing_materials.filter(p => p.pricelist_id != fg.id);" 
                                                class="text-[9px] font-black text-rose-600 bg-rose-50 border border-rose-100 px-2 py-0.5 rounded-lg hover:bg-rose-100 transition-all shadow-sm">
                                            Unlink
                                        </button>
                                    </template>
                                    <span class="text-[9px] font-black text-indigo-700 bg-indigo-50 border border-indigo-100/60 px-2 py-0.5 rounded-lg" x-text="'Size: ' + fg.size"></span>
                                    <span class="text-[9px] font-black text-emerald-700 bg-emerald-50 border border-emerald-100/60 px-2 py-0.5 rounded-lg" x-text="'CF1: ' + (parseFloat(fg.cf_1) || 0)"></span>
                                    <button type="button" @click="addPackingRow(fg.id)"
                                            class="flex items-center gap-1 px-2.5 py-1 bg-amber-500 hover:bg-amber-600 text-white font-black text-[9px] rounded-lg transition-all shadow-sm animate-none">
                                        <i class="fas fa-plus-circle text-[8px]"></i> Add Material
                                    </button>
                                </div>
                            </div>

                            <!-- Grouped Packing Materials items -->
                            <div class="space-y-2">
                                <template x-for="(pm, idx) in bomModal.form.packing_materials.filter(p => p.pricelist_id == fg.id)" :key="idx">
                                    <div class="grid grid-cols-12 gap-2 p-2 bg-slate-50/50 border border-slate-100 rounded-xl items-center">
                                        <div class="col-span-5 space-y-1">
                                            <input type="text" x-model="pm.search" placeholder="Search material..."
                                                   class="w-full px-2 py-1 text-[10px] border border-slate-200 rounded-lg outline-none font-semibold text-slate-600 bg-white placeholder-slate-400">
                                            <select x-model="pm.raw_material_id"
                                                    class="w-full px-2.5 py-1.5 text-xs border border-slate-200 rounded-lg focus:ring-2 focus:ring-amber-500/10 focus:border-amber-500 outline-none font-bold text-slate-700 bg-white">
                                                <option value="">Select Material</option>
                                                <template x-for="(rm, index) in getSearchedPackingMaterials(pm.search, pm.raw_material_id)" :key="rm.id">
                                                    <option :value="rm.id" :selected="rm.id == pm.raw_material_id" x-text="rm.name + (rm.pack_name ? ' ['+rm.pack_name+']' : '') + ' ('+rm.item_code+')'"></option>
                                                </template>
                                            </select>
                                        </div>
                                        <div class="col-span-2 flex flex-col items-center gap-1">
                                            <div class="flex items-center gap-1">
                                                <input type="checkbox" x-model="pm.is_container" class="rounded text-amber-500 focus:ring-amber-500/20 w-3 h-3 cursor-pointer">
                                                <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest cursor-pointer select-none" @click="pm.is_container = !pm.is_container">Container</span>
                                            </div>
                                            <div class="text-[9px] font-bold text-slate-400 bg-slate-100 px-1.5 py-0.5 rounded" x-show="pm.raw_material_id && getPmRate(pm.raw_material_id, false, 1) > 0">
                                                Rate: <span class="text-indigo-600 font-extrabold" x-text="'₹' + getPmRate(pm.raw_material_id, pm.is_container, fg.cf_1)"></span>
                                            </div>
                                            <template x-if="pm.raw_material_id && getPmRate(pm.raw_material_id, false, 1) === 0">
                                                <div class="flex flex-col items-center gap-1 mt-1">
                                                    <div class="text-[9px] font-bold text-rose-700 bg-rose-50 border border-rose-100 px-1 py-0.5 rounded whitespace-nowrap">
                                                        Rate: <span class="text-rose-800 font-extrabold" x-text="'₹' + getPmRowRate(pm, fg.cf_1)"></span>
                                                    </div>
                                                    <input type="number" step="0.01" min="0.01" x-model="pm.rate"
                                                           class="w-16 px-1.5 py-0.5 text-[9px] font-black border border-rose-200 rounded-lg outline-none focus:ring-1 focus:ring-rose-400 text-center text-rose-800 bg-rose-50/20"
                                                           placeholder="Enter Rate">
                                                </div>
                                            </template>
                                        </div>
                                        <div class="col-span-3">
                                            <input type="number" step="0.001" x-model="pm.quantity"
                                                   placeholder="Qty"
                                                   class="w-full px-2 py-1.5 text-xs border border-slate-200 rounded-lg focus:ring-1 focus:ring-amber-400 outline-none font-bold text-center">
                                        </div>
                                        <div class="col-span-2 flex justify-center">
                                            <button type="button" @click="bomModal.form.packing_materials.splice(bomModal.form.packing_materials.indexOf(pm), 1)"
                                                    class="w-8 h-8 rounded-lg flex items-center justify-center text-red-400 hover:text-red-600 hover:bg-red-50 transition border border-slate-100">
                                                <i class="fas fa-trash-alt text-[10px]"></i>
                                            </button>
                                        </div>
                                    </div>
                                </template>
                                <template x-if="bomModal.form.packing_materials.filter(p => p.pricelist_id == fg.id).length === 0">
                                    <div class="text-[10px] text-slate-400 font-bold text-center py-3 bg-slate-50/30 rounded-xl border border-dashed border-slate-200">
                                        No packing materials added for this size. Click "Add Material" above.
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    <template x-if="getLinkedPricelistItems(bomModal.form.finished_product_id).length === 0">
                        <div class="text-center py-8 text-slate-400 text-sm font-bold border-2 border-dashed border-slate-200 rounded-xl bg-white">
                            Pricelist Master me is composition se linked koi Finished Good (FG) nahi mila.
                        </div>
                    </template>
                </div>

                {{-- Step 5: Calculations Summary --}}
                <div x-show="bomModal.step === 5" class="space-y-5 max-h-[50vh] overflow-y-auto pr-1" x-transition>
                    <div class="bg-indigo-50/50 border border-indigo-100 p-4 rounded-2xl space-y-3">
                        <h4 class="text-xs font-black text-indigo-700 uppercase tracking-widest">Calculations Summary</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs font-bold text-slate-700">
                            <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-sm">
                                <span class="text-slate-400 font-semibold block mb-1">W/o Density per Ltr/Kg Rate:</span>
                                <span class="text-indigo-600 font-extrabold text-sm" x-text="bomModal.form.finished_product_id ? '₹' + (getIngredientsGrandTotal() / (parseFloat(bomModal.form.yield_quantity) || 1)).toFixed(2) + '/' + bomModal.form.yield_uom : '₹0.00'"></span>
                            </div>
                            <template x-if="bomModal.form.density && parseFloat(bomModal.form.density) > 0">
                                <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-sm">
                                    <span class="text-slate-400 font-semibold block mb-1">With Density per Ltr/Kg Rate:</span>
                                    <span class="text-emerald-600 font-extrabold text-sm" x-text="bomModal.form.finished_product_id ? '₹' + (getIngredientsGrandTotal() / (parseFloat(bomModal.form.yield_quantity) / parseFloat(bomModal.form.density))).toFixed(2) + '/Ltr' : '₹0.00'"></span>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Size-Wise Packing & Ingredient Costs</h4>
                        <div class="space-y-3">
                            <template x-for="fg in getLinkedPricelistItems(bomModal.form.finished_product_id)" :key="fg.id">
                                <div x-show="getPmTotalRate(fg.id, fg.cf_1) > 0" class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm space-y-3">
                                    <!-- FG Summary Info -->
                                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
                                        <div>
                                            <span class="font-extrabold text-slate-800 text-xs uppercase tracking-tight" x-text="fg.item_hd_name"></span>
                                            <span class="text-[9px] font-black text-indigo-700 bg-indigo-50 border border-indigo-100/60 px-2 py-0.5 rounded-lg ml-2" x-text="'Size: ' + fg.size"></span>
                                            <span class="text-[9px] font-black text-emerald-700 bg-emerald-50 border border-emerald-100/60 px-2 py-0.5 rounded-lg ml-1" x-text="'CF1: ' + (parseFloat(fg.cf_1) || 0)"></span>
                                            <div class="text-[10px] text-slate-400 font-bold mt-1">Code: <span class="text-slate-600 font-mono" x-text="fg.user_code"></span></div>
                                        </div>
                                        <div class="text-right">
                                            <span class="text-xs font-black text-rose-600 bg-rose-50 border border-rose-200 px-2.5 py-1 rounded-xl shadow-sm inline-block" x-text="'PM Total = ₹' + getPmTotalRate(fg.id, fg.cf_1)"></span>
                                        </div>
                                    </div>

                                    <!-- Costs Calculation Display -->
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-[11px] font-semibold text-slate-600 bg-slate-50/50 p-3 rounded-xl border border-slate-100">
                                        <div class="space-y-1">
                                            <span class="text-slate-400 font-bold block text-[10px] uppercase tracking-wider">Ingredient Cost Calculation</span>
                                            <div class="font-mono text-slate-700">
                                                <span>₹</span><span x-text="(getIngredientsGrandTotal() / (parseFloat(bomModal.form.yield_quantity) || 1)).toFixed(2)"></span> / 1000 x <span x-text="getPackingSizeInMlOrGm(fg.size)"></span> = 
                                                <span class="text-indigo-600 font-bold" x-text="'₹' + getIngredientCostForSize(fg.size).toFixed(2)"></span>
                                            </div>
                                        </div>
                                        <div class="space-y-1 text-right md:border-l border-slate-200 md:pl-4">
                                            <span class="text-slate-400 font-bold block text-[10px] uppercase tracking-wider">Final Cost (Bulk + PM)</span>
                                            <div class="font-mono text-slate-700">
                                                <span>₹</span><span x-text="getIngredientCostForSize(fg.size).toFixed(2)"></span> + <span>₹</span><span x-text="getPmTotalRate(fg.id, fg.cf_1)"></span> = 
                                                <span class="text-emerald-600 font-black text-xs" x-text="'₹' + (getIngredientCostForSize(fg.size) + getPmTotalRate(fg.id, fg.cf_1)).toFixed(2)"></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Modal Footer --}}
            <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex items-center justify-between flex-shrink-0">
                <button type="button" @click="if (bomModal.step > 1) bomModal.step--" x-show="bomModal.step > 1" class="px-4 py-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 font-bold text-xs rounded-xl shadow-sm">
                    Back
                </button>
                <div class="flex items-center gap-2 ml-auto">
                    <button type="button" @click="nextStep()" x-show="bomModal.step < 5" class="px-5 py-2 bg-amber-500 hover:bg-amber-600 text-white font-black text-xs rounded-xl shadow">
                        Next &rarr;
                    </button>
                    <button type="button" @click="submitBom()" x-show="bomModal.step === 5" :disabled="bomModal.submitting" class="px-6 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs rounded-xl shadow">
                        <span x-text="bomModal.submitting ? 'Saving...' : 'Save & Update BOM'"></span>
                    </button>
                </div>
            </div>

        </div>
    </div>

</div>

<script>
const __finishedGoods = @json($finishedGoods ?? []);
const __rawMaterials  = @json($rawMaterials ?? []);
const __purities      = @json($purities ?? []);
const __types         = @json(\App\Models\ProductType::all());
const __pricelists    = @json($pricelists ?? []);
const __pmRates       = @json($pmRates ?? []);

function costingDashboardApp() {
    return {
        search: '',
        selectedProductFilter: '',
        currentPage: 1,
        pageSize: 10,
        boms: @json($processedBoms),
        selectedBomId: null,
        selectedRate: { bom_id: null, type: null, value: null, unit: '' },
        selectedPm: { bom_id: null, pricelist_id: null, cost: null, size: '', cf1: 1 },

        get totalPages() {
            return Math.max(1, Math.ceil(this.filteredBoms.length / this.pageSize));
        },

        get paginatedBoms() {
            const start = (this.currentPage - 1) * this.pageSize;
            return this.filteredBoms.slice(start, start + this.pageSize);
        },

        bomModal: {
            show: false,
            editId: null,
            submitting: false,
            typeFilter: '',
            rmTypeFilter: '',
            step: 1,
            manualSearchQuery: '',
            selectedManualPricelistId: '',
            form: {
                finished_product_id: '',
                badge: '',
                formulation: '',
                density: '',
                yield_quantity: 1,
                yield_uom: 'KG',
                items: [],
                packing_materials: [],
                manual_pricelist_ids: []
            }
        },

        init() {
            this.boms.sort((a, b) => (a.product_name || '').localeCompare(b.product_name || ''));
        },

        get uniqueProductNames() {
            const names = [...new Set(this.boms.map(b => b.product_name).filter(Boolean))];
            return names.sort((a, b) => a.localeCompare(b));
        },

        get filteredBoms() {
            return this.boms.filter(bom => {
                const pName = (bom.product_name || '').toLowerCase();
                const code  = (bom.item_code || '').toLowerCase();

                if (this.selectedProductFilter && bom.product_name !== this.selectedProductFilter) {
                    return false;
                }

                if (this.search.trim()) {
                    const q = this.search.toLowerCase();
                    const pmMatch = (bom.packing_costs || []).some(p => (p.fg_name || '').toLowerCase().includes(q) || (p.size || '').toLowerCase().includes(q));
                    if (!pName.includes(q) && !code.includes(q) && !pmMatch) {
                        return false;
                    }
                }

                return true;
            });
        },

        resetFilters() {
            this.search = '';
            this.selectedProductFilter = '';
            this.resetSelections();
        },

        toggleRate(bom, type) {
            if (this.isRateSelected(bom.id, type)) {
                this.selectedRate = { bom_id: null, type: null, value: null, unit: '' };
                if (this.selectedPm.bom_id === bom.id && (this.selectedPm.cost === null || this.selectedPm.cost === undefined)) {
                    this.selectedBomId = null;
                }
            } else {
                this.selectedBomId = bom.id;
                this.selectedRate = {
                    bom_id: bom.id,
                    type: type,
                    value: type === 'wo' ? bom.wo_density_rate : bom.with_density_rate,
                    unit: type === 'wo' ? bom.yield_uom : 'Ltr'
                };
            }
        },

        togglePm(bom, pm) {
            if (this.isPmSelected(bom.id, pm.pricelist_id)) {
                this.selectedPm = { bom_id: null, pricelist_id: null, cost: null, size: '', cf1: 1, size_in_ml: 0, unit_bulk_wo: 0, unit_bulk_with: 0, unit_total_wo: 0, unit_total_with: 0 };
                if (this.selectedRate.bom_id === bom.id && (this.selectedRate.value === null || this.selectedRate.value === undefined)) {
                    this.selectedBomId = null;
                }
            } else {
                this.selectedBomId = bom.id;
                this.selectedPm = {
                    bom_id: bom.id,
                    pricelist_id: pm.pricelist_id,
                    cost: pm.pm_cost,
                    size: pm.size,
                    cf1: pm.cf1 || 1,
                    size_in_ml: pm.size_in_ml || 0,
                    unit_bulk_wo: pm.unit_bulk_wo || 0,
                    unit_bulk_with: pm.unit_bulk_with || 0,
                    unit_total_wo: pm.unit_total_wo || 0,
                    unit_total_with: pm.unit_total_with || 0,
                };
            }
        },

        isBomActive(bomId) {
            return (this.selectedRate.bom_id === bomId) || (this.selectedPm.bom_id === bomId);
        },

        isRateSelected(bomId, type) {
            return this.selectedRate.bom_id === bomId && this.selectedRate.type === type;
        },

        isPmSelected(bomId, pricelistId) {
            return this.selectedPm.bom_id === bomId && this.selectedPm.pricelist_id === pricelistId;
        },

        resetSelections() {
            this.selectedBomId = null;
            this.selectedRate = { bom_id: null, type: null, value: null, unit: '' };
            this.selectedPm = { bom_id: null, pricelist_id: null, cost: null, size: '', cf1: 1, size_in_ml: 0, unit_bulk_wo: 0, unit_bulk_with: 0, unit_total_wo: 0, unit_total_with: 0 };
        },

        getActiveBomName() {
            if (!this.selectedBomId) return '';
            const b = this.boms.find(item => item.id === this.selectedBomId);
            return b ? b.product_name : '';
        },

        getPackBulkCost(bom) {
            if (!this.selectedRate.value || !this.selectedPm.size_in_ml) return 0;
            const rate = this.selectedRate.value;
            const sizeInMl = this.selectedPm.size_in_ml;
            return (rate / 1000.0) * sizeInMl;
        },

        getPackBulkFormula(bom) {
            if (!this.selectedRate.value || !this.selectedPm.size_in_ml) return '';
            const rateVal = this.selectedRate.value;
            const sizeInMl = this.selectedPm.size_in_ml;
            const bulkCost = this.getPackBulkCost(bom);
            return `₹${rateVal}/1000 × ${sizeInMl}ML = ₹${bulkCost.toFixed(2)}`;
        },

        getPackTotalCost(bom) {
            const bulkCost = this.getPackBulkCost(bom);
            const pmCost = this.selectedPm.cost || 0;
            return bulkCost + pmCost;
        },

        get calculatedCombinedTotal() {
            const rateVal = this.selectedRate.value || 0;
            const pmCost = this.selectedPm.cost || 0;
            return rateVal + pmCost;
        },

        get calculatedPackAdjustedTotal() {
            if (this.selectedRate.value && this.selectedPm.size_in_ml) {
                return this.getPackTotalCost({});
            }
            const rateVal = this.selectedRate.value || 0;
            const cf1 = this.selectedPm.cf1 || 1;
            const pmCost = this.selectedPm.cost || 0;
            return (rateVal * cf1) + pmCost;
        },

        getCalculatedTotal(bomId) {
            let total = 0;
            if (this.selectedRate.bom_id === bomId && this.selectedRate.value) {
                total += this.selectedRate.value;
            }
            if (this.selectedPm.bom_id === bomId && this.selectedPm.cost) {
                total += this.selectedPm.cost;
            }
            return total;
        },

        getCalculationSummary(bomId) {
            const parts = [];
            if (this.selectedRate.bom_id === bomId && this.selectedRate.value) {
                parts.push('Rate: ₹' + this.selectedRate.value);
            }
            if (this.selectedPm.bom_id === bomId && this.selectedPm.cost) {
                parts.push('PM: ₹' + this.selectedPm.cost);
            }
            return parts.join(' + ');
        },

        // ════════════════════════════════════════════════════════
        // Exact BOM Modal Functions (Identical to Costing BOMs)
        // ════════════════════════════════════════════════════════
        get filteredFGs() {
            if (!this.bomModal.typeFilter) return __finishedGoods;
            return __finishedGoods.filter(p => p.product_type_id == this.bomModal.typeFilter);
        },
        get rmTypes() {
            return [...new Set(__rawMaterials.map(r => r.rm_type).filter(Boolean))].sort();
        },
        isPackingMaterial(rmId) {
            const rm = __rawMaterials.find(r => r.id == rmId);
            if (!rm) return false;
            const type = __types.find(t => t.id == rm.product_type_id);
            return type && type.type_name.toUpperCase().includes('PACKING');
        },
        getFilteredRMs(filter, wantPacking = false) {
            let list = __rawMaterials;
            if (filter || this.bomModal.rmTypeFilter) {
                const f = filter || this.bomModal.rmTypeFilter;
                list = list.filter(r => r.rm_type === f);
            }
            return list.filter(r => {
                const isPM = this.isPackingMaterial(r.id);
                return wantPacking ? isPM : !isPM;
            });
        },
        getItemUom(id) {
            const rm = __rawMaterials.find(r => r.id == id);
            return rm ? rm.uom : '';
        },

        getFormulation(fgId) {
            const fg = __finishedGoods.find(f => f.id == fgId);
            if (!fg) return '';
            const matches = [...fg.name.matchAll(/(\d+(?:\.\d+)?)\s*%/g)];
            if (matches.length > 0) {
                return matches.map(m => m[1] + '%').join(' + ');
            }
            return '100%';
        },

        getFinishedProductName(id) {
            const fg = __finishedGoods.find(f => f.id == id);
            return fg ? fg.name + (fg.pack_name ? ' ['+fg.pack_name+']' : '') : '';
        },

        getLinkedPricelistItems(fgId) {
            const fg = __finishedGoods.find(f => f.id == fgId);
            if (!fg) return [];
            const normalize = (str) => {
                return (str || '').replace(/\[[^\]]+\]/g, '').replace(/\./g, '').replace(/\s+/g, ' ').replace(/\s*%\s*/g, '%').trim().toLowerCase();
            };
            const baseComposition = normalize(fg.name);
            let items = (__pricelists || []).filter(p => {
                const hdName = normalize(p.item_hd_name);
                const grp3 = normalize(p.group3);
                return hdName.includes(baseComposition) || grp3.includes(baseComposition);
            });
            const manualIds = this.bomModal.form.manual_pricelist_ids || [];
            manualIds.forEach(id => {
                if (!items.some(it => it.id == id)) {
                    const match = __pricelists.find(p => p.id == id);
                    if (match) items.push(match);
                }
            });
            return items;
        },

        getSearchedPackingMaterials(query = '', selectedId = null) {
            const list = __rawMaterials.filter(r => this.isPackingMaterial(r.id));
            if (!query) return list;
            const q = query.toLowerCase();
            return list.filter(r => {
                if (selectedId && r.id == selectedId) return true;
                const name = (r.name || '').toLowerCase();
                const code = (r.item_code || '').toLowerCase();
                return name.includes(q) || code.includes(q);
            });
        },

        getPmRate(rmId, isContainer = false, cf1 = 1) {
            const rm = __rawMaterials.find(r => r.id == rmId);
            if (!rm || !rm.item_code) return 0;
            let rate = parseFloat(__pmRates[rm.item_code]) || 0;
            if (isContainer) {
                const divisor = parseFloat(cf1) || 1;
                rate = rate / divisor;
            }
            return parseFloat(rate.toFixed(2));
        },

        getPmRowRate(pm, fgCf1) {
            if (!pm.raw_material_id) return 0;
            let baseRate = this.getPmRate(pm.raw_material_id, false, 1);
            if (baseRate === 0 && pm.rate) {
                baseRate = parseFloat(pm.rate) || 0;
            }
            if (pm.is_container) {
                const divisor = parseFloat(fgCf1) || 1;
                baseRate = baseRate / divisor;
            }
            return parseFloat(baseRate.toFixed(2));
        },

        getPmTotalRate(pricelistId, cf1) {
            let sum = 0;
            const pms = this.bomModal.form.packing_materials.filter(p => p.pricelist_id == pricelistId);
            pms.forEach(pm => {
                sum += this.getPmRowRate(pm, cf1);
            });
            return parseFloat(sum.toFixed(2));
        },

        getPackingSizeInMlOrGm(sizeStr) {
            if (!sizeStr) return 0;
            const str = sizeStr.toLowerCase().trim();
            const num = parseFloat(str) || 0;
            if (str.includes('ml') || str.includes('gm') || str.includes('g') || str.includes('gram')) {
                return num;
            }
            if (str.includes('ltr') || str.includes('liter') || str.includes('litre') || str.includes('kg') || str.endsWith('l')) {
                return num * 1000;
            }
            return num;
        },

        getIngredientCostForSize(sizeStr) {
            const yieldQty = parseFloat(this.bomModal.form.yield_quantity) || 1;
            const sizeInMl = this.getPackingSizeInMlOrGm(sizeStr);
            const ingredientGrandTotal = this.getIngredientsGrandTotal();
            const ratePerUnit = ingredientGrandTotal / yieldQty;
            const cost = (ratePerUnit / 1000) * sizeInMl;
            return parseFloat(cost.toFixed(4));
        },

        getMaterialRate(rmId) {
            const rm = __rawMaterials.find(r => r.id == rmId);
            if (!rm || !rm.item_code) return null;
            let rate = parseFloat(__pmRates[rm.item_code]) || 0;
            return rate > 0 ? parseFloat(rate.toFixed(2)) : null;
        },

        getFormulationList(fgId) {
            const fg = __finishedGoods.find(f => f.id == fgId);
            if (!fg) return [];
            const matches = [...fg.name.matchAll(/(\d+(?:\.\d+)?)\s*%/g)];
            return matches.map(m => parseFloat(m[1]));
        },

        getPurity(rmId) {
            const rm = __rawMaterials.find(r => r.id == rmId);
            if (!rm) return '—';
            const purity = __purities[rm.item_code];
            return purity !== undefined && purity !== null ? purity + '%' : '—';
        },

        isTechnical(rmId) {
            const rm = __rawMaterials.find(r => r.id == rmId);
            return rm && rm.rm_type === 'TECHNICAL';
        },

        getPurityVal(rmId) {
            const rm = __rawMaterials.find(r => r.id == rmId);
            if (!rm) return null;
            return __purities[rm.item_code] !== undefined && __purities[rm.item_code] !== null ? parseFloat(__purities[rm.item_code]) : null;
        },

        calculateSuggestedQty(item) {
            const batchQty = parseFloat(this.bomModal.form.yield_quantity) || 0;
            let formulation = parseFloat(item.formulation);
            if (isNaN(formulation) || formulation <= 0) {
                formulation = parseFloat(this.bomModal.form.formulation);
            }
            if (isNaN(formulation) || formulation <= 0) {
                const fgFormList = this.getFormulationList(this.bomModal.form.finished_product_id);
                formulation = fgFormList.length > 0 ? fgFormList[0] : 100;
            }
            let purity = 100;
            if (item && item.raw_material_id) {
                const fetchedPurity = this.getPurityVal(item.raw_material_id);
                if (fetchedPurity !== null) {
                    purity = fetchedPurity;
                } else if (item.purity) {
                    purity = parseFloat(item.purity) || 100;
                }
            }
            if (purity <= 0) purity = 100;
            const qty = (batchQty * formulation) / purity;
            return parseFloat(qty.toFixed(2));
        },

        calculateRemainingQty(currentItem) {
            const batchQty = parseFloat(this.bomModal.form.yield_quantity) || 0;
            let sumOthers = 0;
            this.bomModal.form.items.forEach(i => {
                if (!i.is_packing && i !== currentItem) {
                    sumOthers += parseFloat(i.quantity) || 0;
                }
            });
            const remaining = batchQty - sumOthers;
            return parseFloat(Math.max(0, remaining).toFixed(2));
        },

        toggleSolvent(item) {
            const targetVal = !item.is_solvent;
            this.bomModal.form.items.forEach(i => {
                if (!i.is_packing) {
                    i.is_solvent = false;
                }
            });
            item.is_solvent = targetVal;
        },

        getUsedIngredientsQty() {
            let sum = 0;
            this.bomModal.form.items.forEach(i => {
                if (!i.is_packing) {
                    sum += parseFloat(i.quantity) || 0;
                }
            });
            return parseFloat(sum.toFixed(2));
        },

        getRemainingIngredientsQty() {
            const batchQty = parseFloat(this.bomModal.form.yield_quantity) || 0;
            const used = this.getUsedIngredientsQty();
            return parseFloat((batchQty - used).toFixed(2));
        },

        getIngredientsGrandTotal() {
            let sum = 0;
            this.bomModal.form.items.forEach(i => {
                if (!i.is_packing && i.raw_material_id && i.quantity) {
                    const rate = parseFloat(this.getMaterialRate(i.raw_material_id)) || parseFloat(i.rate) || 0;
                    const tc = i.transportation_cost !== undefined && i.transportation_cost !== '' ? parseFloat(i.transportation_cost) : 5;
                    sum += parseFloat(i.quantity) * (rate + tc);
                }
            });
            return parseFloat(sum.toFixed(2));
        },

        hasActiveSolvent() {
            return this.bomModal.form.items.some(i => !i.is_packing && i.is_solvent);
        },

        openBomModal() {
            this.bomModal.editId = null;
            this.bomModal.typeFilter = '';
            this.bomModal.rmTypeFilter = '';
            this.bomModal.step = 1;
            this.bomModal.manualSearchQuery = '';
            this.bomModal.selectedManualPricelistId = '';
            this.bomModal.form = { finished_product_id: '', badge: '', formulation: '', density: '', yield_quantity: 1, yield_uom: 'KG', items: [{ raw_material_id: '', quantity: '', purity: '', rate: '', transportation_cost: 5, formulation: '', rm_type_filter: '', is_packing: false, is_solvent: false, is_technical: '' }], packing_materials: [], manual_pricelist_ids: [] };
            this.bomModal.show = true;
        },

        editBomFromData(recipeData) {
            if (!recipeData) return;
            const recipe = typeof recipeData === 'string' ? JSON.parse(recipeData) : recipeData;
            this.bomModal.editId = recipe.id;
            this.bomModal.typeFilter = '';
            this.bomModal.rmTypeFilter = '';
            this.bomModal.step = 1;
            this.bomModal.manualSearchQuery = '';
            this.bomModal.selectedManualPricelistId = '';

            const normalize = (str) => {
                return (str || '').replace(/\[[^\]]+\]/g, '').replace(/\./g, '').replace(/\s+/g, ' ').replace(/\s*%\s*/g, '%').trim().toLowerCase();
            };
            const baseComposition = normalize(this.getFinishedProductName(recipe.finished_product_id));
            const autoIds = (__pricelists || []).filter(p => {
                const hdName = normalize(p.item_hd_name);
                const grp3 = normalize(p.group3);
                return hdName.includes(baseComposition) || grp3.includes(baseComposition);
            }).map(p => p.id);

            const manualIds = [];
            (recipe.packing_materials || []).forEach(p => {
                if (!autoIds.includes(p.pricelist_id) && !manualIds.includes(p.pricelist_id)) {
                    manualIds.push(p.pricelist_id);
                }
            });

            this.bomModal.form = {
                finished_product_id: recipe.finished_product_id,
                badge: recipe.badge || '',
                formulation: recipe.formulation || '',
                density: recipe.density || '',
                yield_quantity: recipe.yield_quantity,
                yield_uom: recipe.yield_uom,
                manual_pricelist_ids: manualIds,
                items: (recipe.items || []).filter(i => !this.isPackingMaterial(i.raw_material_id)).map(i => {
                    const isPacking = false;
                    const purityVal = i.purity || this.getPurityVal(i.raw_material_id) || 100;
                    const rawFormulation = ((parseFloat(i.quantity) * parseFloat(purityVal)) / parseFloat(recipe.yield_quantity));
                    return {
                        raw_material_id: i.raw_material_id,
                        quantity: i.quantity,
                        purity: i.purity || '',
                        rate: '',
                        transportation_cost: i.transportation_cost !== null && i.transportation_cost !== undefined ? parseFloat(i.transportation_cost) : 5,
                        formulation: rawFormulation ? parseFloat(rawFormulation.toFixed(3)) : '',
                        rm_type_filter: '',
                        is_packing: isPacking,
                        is_solvent: false,
                        is_technical: this.isTechnical(i.raw_material_id)
                    };
                }),
                packing_materials: (recipe.packing_materials || []).map(p => ({
                    pricelist_id: p.pricelist_id,
                    raw_material_id: p.raw_material_id,
                    quantity: p.quantity,
                    is_container: p.is_container || false,
                    rate: '',
                    search: ''
                }))
            };
            this.bomModal.show = true;
        },

        addPackingRow(pricelistId) {
            if (!this.bomModal.form.packing_materials) {
                this.bomModal.form.packing_materials = [];
            }
            this.bomModal.form.packing_materials.push({
                pricelist_id: pricelistId,
                raw_material_id: '',
                quantity: '',
                is_container: false,
                rate: '',
                search: ''
            });
        },

        nextStep() {
            if (this.bomModal.step === 1) {
                if (!this.bomModal.form.finished_product_id) {
                    alert('Please select a finished product.');
                    return;
                }
                this.bomModal.step = 2;
            } else if (this.bomModal.step === 2) {
                if (!this.bomModal.form.yield_quantity || this.bomModal.form.yield_quantity <= 0) {
                    alert('Please enter a valid batch quantity.');
                    return;
                }
                if (!this.bomModal.form.yield_uom) {
                    alert('Please enter a batch UOM.');
                    return;
                }
                this.bomModal.step = 3;
            } else if (this.bomModal.step === 3) {
                const rmMats = this.bomModal.form.items.filter(i => !i.is_packing);
                if (rmMats.length === 0) {
                    alert('Please add at least one raw material ingredient.');
                    return;
                }
                if (rmMats.some(i => !i.raw_material_id || !i.quantity || i.quantity <= 0)) {
                    alert('Please ensure all raw material rows have a selected material and valid quantity.');
                    return;
                }
                
                const batchQty = parseFloat(this.bomModal.form.yield_quantity) || 0;
                const sumIngredients = this.getUsedIngredientsQty();
                if (sumIngredients > batchQty + 0.01) {
                    alert(`Total ingredients quantity (${sumIngredients.toFixed(2)}) cannot exceed the Batch Quantity (${batchQty}).`);
                    return;
                }
                
                this.bomModal.step = 4;
            } else if (this.bomModal.step === 4) {
                this.bomModal.step = 5;
            }
        },

        addRMRow(isPacking = false) {
            this.bomModal.form.items.push({ raw_material_id: '', quantity: '', purity: '', rate: '', transportation_cost: 5, formulation: '', rm_type_filter: '', is_packing: isPacking, is_solvent: false, is_technical: '' });
        },

        async submitBom() {
            const cleanItems = this.bomModal.form.items.filter(i => i.raw_material_id && !i.is_packing);
            const cleanPMs = (this.bomModal.form.packing_materials || []).filter(p => p.raw_material_id && p.quantity).map(p => ({
                pricelist_id: p.pricelist_id,
                raw_material_id: p.raw_material_id,
                quantity: p.quantity,
                is_container: p.is_container,
                rate: p.rate || ''
            }));
            if (!this.bomModal.form.finished_product_id || !this.bomModal.form.yield_quantity || cleanItems.length === 0) {
                alert('Please fill all required fields and add at least one item.');
                return;
            }
            
            const batchQty = parseFloat(this.bomModal.form.yield_quantity) || 0;
            const sumIngredients = this.getUsedIngredientsQty();
            if (sumIngredients > batchQty + 0.01) {
                alert(`Total ingredients quantity (${sumIngredients.toFixed(2)}) cannot exceed the Batch Quantity (${batchQty}).`);
                return;
            }
            
            this.bomModal.submitting = true;
            const isEdit = !!this.bomModal.editId;
            const url    = isEdit ? `{{ url('costing-boms') }}/${this.bomModal.editId}` : '{{ route('costing.boms.store') }}';
            const method = isEdit ? 'PUT' : 'POST';
 
            const submissionForm = { ...this.bomModal.form, items: cleanItems, packing_materials: cleanPMs };

            try {
                const resp = await fetch(url, {
                    method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(submissionForm)
                });
                const data = await resp.json();
                if (data.success) {
                    this.bomModal.show = false;
                    window.location.reload();
                } else {
                    const msg = data.message || (data.errors ? Object.values(data.errors).flat().join('\n') : 'Failed to save.');
                    alert(msg);
                }
            } catch(e) { alert('Network error.'); }
            finally { this.bomModal.submitting = false; }
        }
    };
}
</script>
@endsection
