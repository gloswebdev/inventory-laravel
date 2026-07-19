@extends('layouts.app')

@section('header', 'Costing BOM Master')

@section('content')
<style>
.bom-btn-gradient { background: linear-gradient(135deg, #f59e0b, #ea580c); color: #fff; box-shadow: 0 4px 12px rgba(245,158,11,.3); }
.bom-btn-gradient:hover { background: linear-gradient(135deg, #d97706, #dd6b20); }
</style>

<div x-data="bomApp()" x-init="init()">

    {{-- ══ Page Header ══ --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center shadow-lg shadow-amber-200/50">
                <i class="fas fa-flask text-white"></i>
            </div>
            <div>
                <h1 class="text-2xl font-black text-slate-800 tracking-tight">Costing BOM Master</h1>
                <p class="text-slate-500 text-sm font-medium mt-0.5">Decoupled Bill of Materials for manufacturing cost calculation</p>
            </div>
        </div>
        <div class="flex gap-2 flex-wrap items-center">
            @if(Auth::user()->hasPermission('costing', 'create'))
            <button @click="openBomModal()" class="bom-btn-gradient text-white text-sm font-bold py-2.5 px-5 rounded-xl transition-all flex items-center gap-2">
                <i class="fas fa-plus"></i> Add Costing BOM
            </button>
            @endif
            <a href="{{ route('costing.boms.export', request()->query()) }}" class="bg-amber-50 hover:bg-amber-100 border border-amber-200 text-amber-700 text-xs font-bold py-2.5 px-4 rounded-xl transition-colors flex items-center gap-2">
                <i class="fas fa-file-excel"></i> Export Excel
            </a>
            <a href="{{ route('costing.index') }}" class="bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs font-bold py-2.5 px-4 rounded-xl transition-all flex items-center gap-2">
                <i class="fas fa-arrow-left"></i> Cost Calculator
            </a>
        </div>
    </div>

    {{-- ══ Search & Filter Form ══ --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 mb-5">
        <form action="{{ route('costing.boms.index') }}" method="GET" class="flex flex-wrap gap-4 items-end">
            <div class="flex-grow min-w-[200px]">
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Search Finished Product</label>
                <div class="relative">
                    <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search finished product name or code..." class="w-full bg-white border border-slate-200 rounded-xl py-2.5 pl-10 pr-4 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none transition-all shadow-sm">
                </div>
            </div>
            
            <div class="w-full sm:w-44">
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Product Type</label>
                <select name="type_id" class="w-full bg-white border border-slate-200 rounded-xl py-2.5 px-3 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none transition-all shadow-sm font-semibold">
                    <option value="">All Types</option>
                    @foreach($types as $type)
                        <option value="{{ $type->id }}" {{ request('type_id') == $type->id ? 'selected' : '' }}>
                            {{ $type->type_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="w-full sm:w-44">
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">BOM Badge / Grade</label>
                <select name="badge" class="w-full bg-white border border-slate-200 rounded-xl py-2.5 px-3 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none transition-all shadow-sm font-semibold">
                    <option value="">All Badges</option>
                    <option value="standard" {{ request('badge') === 'standard' ? 'selected' : '' }}>Standard (No Badge)</option>
                    <option value="small" {{ request('badge') === 'small' ? 'selected' : '' }}>Small</option>
                    <option value="big" {{ request('badge') === 'big' ? 'selected' : '' }}>Big</option>
                    <option value="bulk" {{ request('badge') === 'bulk' ? 'selected' : '' }}>Bulk</option>
                </select>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold py-2.5 px-5 rounded-xl shadow-sm hover:shadow-md transition-all flex items-center gap-2">
                    <i class="fas fa-filter"></i> Filter
                </button>
                @if(request()->anyFilled(['search', 'type_id', 'badge']))
                <a href="{{ route('costing.boms.index') }}" class="bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs font-bold py-2.5 px-4 rounded-xl shadow-sm transition-all flex items-center gap-2">
                    <i class="fas fa-times"></i> Clear
                </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Bulk Actions --}}
    @if(Auth::user()->hasPermission('costing', 'delete'))
    <div id="bulkActions" class="bg-white px-6 py-3 border border-slate-100 rounded-2xl mb-4 flex items-center gap-3 shadow-sm" x-show="selectedIds.length > 0" x-cloak>
        <span class="text-xs font-bold text-slate-500">Selected <span class="text-amber-600 font-black" x-text="selectedIds.length"></span> items</span>
        <button @click="bulkDelete()" class="bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 text-xs font-bold py-1.5 px-4 rounded-lg transition-colors flex items-center gap-2">
            <i class="fas fa-trash-alt"></i> Delete Selected
        </button>
    </div>
    @endif

    {{-- Recipe Table --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        @if(session('success'))
        <div class="bg-emerald-50 border-b border-emerald-100 px-6 py-3 flex items-center gap-2 text-emerald-700 text-sm font-bold animate-pulse">
            <i class="fas fa-check-circle text-emerald-500"></i> {{ session('success') }}
        </div>
        @endif

        <table class="w-full text-left border-collapse">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="py-3 px-5 border-b border-slate-200 w-10 text-center">
                        <input type="checkbox" @change="toggleAll($event.target.checked)" class="rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                    </th>
                    <th class="py-3 px-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Finished Product</th>
                    <th class="py-3 px-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Batch</th>
                    <th class="py-3 px-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Raw Materials (BOM)</th>
                    <th class="py-3 px-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($boms as $bom)
                <tr class="hover:bg-amber-50/30 transition-colors group">
                    <td class="py-3 px-5 text-center">
                        <input type="checkbox" :checked="selectedIds.includes({{ $bom->id }})" @change="toggleSelect({{ $bom->id }}, $event.target.checked)" class="rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                    </td>
                    <td class="py-3 px-5">
                        <div class="font-bold text-slate-800 text-sm flex items-center gap-2">
                            {{ $bom->finishedProduct->name ?? '—' }}
                            @if($bom->badge)
                            <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider {{ $bom->badge === 'small' ? 'bg-orange-500 text-white' : ($bom->badge === 'big' ? 'bg-purple-600 text-white' : 'bg-blue-600 text-white') }} border border-transparent shadow-sm">
                                {{ strtoupper($bom->badge) }}
                            </span>
                            @endif
                        </div>
                        <div class="flex items-center gap-2 mt-1">
                            @php 
                                $typeName = $bom->finishedProduct->type->type_name ?? 'N/A'; 
                                preg_match_all('/(\d+(?:\.\d+)?)\s*%/', $bom->finishedProduct->name ?? '', $matches);
                                $formulation = !empty($matches[1]) ? array_sum(array_map('floatval', $matches[1])) . '%' : '100%';
                            @endphp
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-amber-100 text-amber-700 border border-amber-200">
                                {{ $typeName }}
                            </span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-blue-50 text-blue-700 border border-blue-100">
                                Formulation: {{ $formulation }}
                            </span>
                            @if($bom->finishedProduct->item_code)
                            <span class="font-mono text-[10px] font-bold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded border border-indigo-100">{{ $bom->finishedProduct->item_code }}</span>
                            @endif
                            <span class="text-[10px] text-slate-400 font-medium">{{ $bom->finishedProduct->pack_name ?? '' }}</span>
                        </div>
                    </td>
                    <td class="py-3 px-4">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-black bg-blue-50 text-blue-700 border border-blue-100">
                            {{ $bom->yield_quantity }} {{ $bom->yield_uom }}
                        </span>
                    </td>
                    <td class="py-3 px-4">
                        <div class="space-y-1">
                            @foreach($bom->items->take(4) as $item)
                            <div class="flex items-center gap-1.5 text-xs">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-400 flex-shrink-0"></span>
                                <span class="font-semibold text-slate-700 truncate max-w-[160px]">{{ $item->rawMaterial->name ?? '?' }}</span>
                                <span class="text-slate-400 font-bold bg-slate-100 px-1.5 py-0.5 rounded text-[10px]">{{ $item->quantity }}</span>
                                <span class="text-slate-400 text-[10px]">{{ $item->rawMaterial->uom ?? '' }}</span>
                                @if(strtoupper(trim($item->rawMaterial->rm_type ?? '')) === 'TECHNICAL')
                                    @php
                                        $purity = $purities[$item->rawMaterial->item_code] ?? $item->purity;
                                    @endphp
                                    <span class="text-[9px] font-bold text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-100 whitespace-nowrap">
                                        Purity: {{ $purity ? $purity . '%' : '—' }}
                                    </span>
                                @endif
                            </div>
                            @endforeach
                            @if($bom->items->count() > 4)
                            <div class="text-[10px] text-slate-400 font-bold ml-3">+{{ $bom->items->count() - 4 }} more ingredients</div>
                            @endif
                        </div>
                    </td>
                    <td class="py-3 px-5 text-right whitespace-nowrap">
                        <div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                            @if(Auth::user()->hasPermission('costing', 'edit'))
                            <button type="button"
                                    data-bom='@json($bom->load("items"))'
                                    @click="editBomFromData($event.currentTarget.getAttribute('data-bom'))"
                                    class="w-8 h-8 rounded-lg bg-white border border-slate-200 text-slate-400 hover:text-amber-600 hover:border-amber-300 hover:bg-amber-50 flex items-center justify-center transition-all shadow-sm">
                                <i class="fas fa-edit text-xs"></i>
                            </button>
                            @endif
                            @if(Auth::user()->hasPermission('costing', 'create'))
                            <button type="button"
                                    @click="openDuplicateModal({{ $bom->id }}, '{{ addslashes($bom->finishedProduct->name ?? '') }}')"
                                    class="w-8 h-8 rounded-lg bg-white border border-slate-200 text-slate-400 hover:text-indigo-600 hover:border-indigo-300 hover:bg-indigo-50 flex items-center justify-center transition-all shadow-sm"
                                    title="Duplicate BOM">
                                <i class="fas fa-copy text-xs"></i>
                            </button>
                            @endif
                            @if(Auth::user()->hasPermission('costing', 'delete'))
                            <button type="button"
                                    @click="deleteBom({{ $bom->id }})"
                                    class="w-8 h-8 rounded-lg bg-white border border-slate-200 text-slate-400 hover:text-red-600 hover:border-red-300 hover:bg-red-50 flex items-center justify-center transition-all shadow-sm">
                                <i class="fas fa-trash-alt text-xs"></i>
                            </button>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="py-16 text-center">
                        <div class="w-16 h-16 rounded-2xl bg-amber-50 border border-amber-100 flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-flask text-2xl text-amber-300"></i>
                        </div>
                        <p class="text-slate-500 font-bold mb-2">No costing BOMs found.</p>
                        @if(Auth::user()->hasPermission('costing', 'create'))
                        <button @click="openBomModal()" class="px-5 py-2 bg-amber-500 text-white font-black rounded-xl text-sm shadow">
                            <i class="fas fa-plus mr-2"></i> Create First Costing BOM
                        </button>
                        @endif
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>

        @if($boms instanceof \Illuminate\Pagination\LengthAwarePaginator && $boms->hasPages())
        <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50">
            {{ $boms->links() }}
        </div>
        @endif
    </div>

    {{-- ══════════════════════
         BOM MODAL (Add / Edit)
         ══════════════════════ --}}
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
                        <div class="text-[10px] font-bold uppercase tracking-widest text-white/70">Independent Costing Formula</div>
                    </div>
                </div>
                <button @click="bomModal.show = false" class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center hover:bg-white/30 transition">
                    <i class="fas fa-times text-white"></i>
                </button>
            </div>

            {{-- Visual Step Indicators --}}
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between flex-shrink-0">
                <div class="flex items-center w-full justify-between max-w-lg mx-auto">
                    <!-- Step 1 Indicator -->
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-full flex-shrink-0 flex items-center justify-center text-xs font-black transition-all"
                             :class="bomModal.step >= 1 ? 'bg-amber-500 text-white shadow-md shadow-amber-200' : 'bg-slate-200 text-slate-500'">1</div>
                        <span class="text-xs font-bold transition-all hidden sm:inline whitespace-nowrap" :class="bomModal.step === 1 ? 'text-amber-600 font-extrabold' : 'text-slate-400'">Product Info</span>
                    </div>
                    <!-- Connector -->
                    <div class="flex-1 h-0.5 mx-2 transition-all" :class="bomModal.step >= 2 ? 'bg-amber-400' : 'bg-slate-200'"></div>
                    <!-- Step 2 Indicator -->
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-full flex-shrink-0 flex items-center justify-center text-xs font-black transition-all"
                             :class="bomModal.step >= 2 ? 'bg-amber-500 text-white shadow-md shadow-amber-200' : 'bg-slate-200 text-slate-500'">2</div>
                        <span class="text-xs font-bold transition-all hidden sm:inline whitespace-nowrap" :class="bomModal.step === 2 ? 'text-amber-600 font-extrabold' : 'text-slate-400'">Batch Specs</span>
                    </div>
                    <!-- Connector -->
                    <div class="flex-1 h-0.5 mx-2 transition-all" :class="bomModal.step >= 3 ? 'bg-amber-400' : 'bg-slate-200'"></div>
                    <!-- Step 3 Indicator -->
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-full flex-shrink-0 flex items-center justify-center text-xs font-black transition-all"
                             :class="bomModal.step >= 3 ? 'bg-amber-500 text-white shadow-md shadow-amber-200' : 'bg-slate-200 text-slate-500'">3</div>
                        <span class="text-xs font-bold transition-all hidden sm:inline whitespace-nowrap" :class="bomModal.step === 3 ? 'text-amber-600 font-extrabold' : 'text-slate-400'">Ingredients</span>
                    </div>
                    <!-- Connector -->
                    <div class="flex-1 h-0.5 mx-2 transition-all" :class="bomModal.step >= 4 ? 'bg-amber-400' : 'bg-slate-200'"></div>
                    <!-- Step 4 Indicator -->
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-full flex-shrink-0 flex items-center justify-center text-xs font-black transition-all"
                             :class="bomModal.step >= 4 ? 'bg-amber-500 text-white shadow-md shadow-amber-200' : 'bg-slate-200 text-slate-500'">4</div>
                        <span class="text-xs font-bold transition-all hidden sm:inline whitespace-nowrap" :class="bomModal.step === 4 ? 'text-amber-600 font-extrabold' : 'text-slate-400'">Packing</span>
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
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Finished Product *</label>
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
                                      <template x-for="(item, idx) in bomModal.form.items.filter(i => !i.is_packing)" :key="idx">
                                <div x-effect="if (isTechnical(item.raw_material_id)) { item.quantity = calculateSuggestedQty(item); } else if (item.is_solvent) { item.quantity = calculateRemainingQty(item); }"
                                     :class="item.is_solvent ? 'bg-indigo-50/40 border-indigo-300 ring-2 ring-indigo-100/50' : 'bg-amber-50/50 border-amber-100'"
                                     class="grid grid-cols-12 gap-2 p-3 border rounded-xl items-center transition-all duration-200">
                                    <div class="col-span-7">
                                        <select x-model="item.raw_material_id"
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
                                                       @click.prevent="toggleSolvent(item)"
                                                       :class="item.is_solvent ? 'text-indigo-600 focus:ring-indigo-500' : 'text-amber-600 focus:ring-amber-500'"
                                                       class="w-3 h-3 border-slate-300">
                                                Solvent
                                            </label>

                                            <template x-if="item.raw_material_id && isTechnical(item.raw_material_id)">
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
                                                                   class="w-14 px-1.5 py-0.5 text-[10px] font-black border border-rose-200 rounded-lg outline-none focus:ring-1 focus:ring-rose-400 text-center text-rose-800 bg-rose-50/20"
                                                                   placeholder="100">
                                                        </div>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                    <div class="col-span-3">
                                        <input type="number" step="0.001" x-model="item.quantity"
                                               placeholder="Qty"
                                               :readonly="isTechnical(item.raw_material_id) || item.is_solvent"
                                               :class="(isTechnical(item.raw_material_id) || item.is_solvent) ? 'bg-slate-100 text-slate-500 cursor-not-allowed' : ''"
                                               class="w-full px-2 py-2 text-xs border border-slate-200 rounded-lg focus:ring-1 focus:ring-amber-400 outline-none font-bold text-center">
                                        <template x-if="isTechnical(item.raw_material_id)">
                                            <div class="text-[9px] text-emerald-600 mt-1 text-center font-bold">Auto-calculated</div>
                                        </template>
                                        <template x-if="item.is_solvent">
                                            <div class="text-[9px] text-indigo-600 mt-1 text-center font-bold">Solvent (Balance)</div>
                                        </template>
                                        <template x-if="!isTechnical(item.raw_material_id) && !item.is_solvent">
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
                                    <div>
                                        Total Batch Size: <span class="text-slate-800" x-text="bomModal.form.yield_quantity + ' ' + bomModal.form.yield_uom"></span>
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
                </div>

                {{-- Step 4: Packing Materials --}}
                <div x-show="bomModal.step === 4" class="space-y-5" x-transition>
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest">Packing Materials *</label>
                                <p class="text-[10px] text-slate-400 mt-0.5">Quantities are per 1 batch unit</p>
                            </div>
                            <button type="button" @click="addRMRow(true)"
                                    class="flex items-center gap-1.5 px-3 py-1.5 bg-amber-50 border border-amber-200 text-amber-700 font-black text-xs rounded-lg hover:bg-amber-100 transition-all">
                                <i class="fas fa-plus-circle text-xs"></i> Add Row

                        <div class="space-y-2 max-h-[280px] overflow-y-auto pr-1">
                            <template x-for="(item, idx) in bomModal.form.items.filter(i => i.is_packing)" :key="idx">
                                <div class="grid grid-cols-12 gap-2 p-3 bg-amber-50/50 border border-amber-100 rounded-xl items-center">
                                    <div class="col-span-7">
                                        <select x-model="item.raw_material_id"
                                                class="w-full px-3 py-2.5 text-xs border border-slate-200 rounded-xl focus:ring-4 focus:ring-amber-500/10 focus:border-amber-500 outline-none font-bold text-slate-700 bg-white shadow-sm hover:border-slate-300 transition-all cursor-pointer">
                                            <option value="">Select Packing Material</option>
                                            <template x-for="(rm, index) in getFilteredRMs('', true)" :key="rm.id">
                                                <option :value="rm.id" :selected="rm.id == item.raw_material_id"
                                                        x-text="(index + 1) + '. ' + rm.name + (rm.pack_name ? ' ['+rm.pack_name+']' : '') + ' ('+rm.item_code+')'"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <div class="col-span-3">
                                        <input type="number" step="0.001" x-model="item.quantity"
                                               placeholder="Qty"
                                               class="w-full px-2 py-2 text-xs border border-slate-200 rounded-lg focus:ring-1 focus:ring-amber-400 outline-none font-bold text-center">
                                    </div>
                                    <div class="col-span-1 text-center text-[10px] font-bold text-slate-400" x-text="getItemUom(item.raw_material_id)"></div>
                                    <div class="col-span-1 flex justify-center">
                                        <button @click="bomModal.form.items.splice(bomModal.form.items.indexOf(item), 1)" class="w-6 h-6 rounded-lg flex items-center justify-center text-red-400 hover:text-red-600 hover:bg-red-50 transition">
                                            <i class="fas fa-trash-alt text-[10px]"></i>
                                        </button>
                                    </div>
                                </div>
                            </template>
                            <div x-show="bomModal.form.items.filter(i => i.is_packing).length === 0" class="text-center py-6 text-slate-400 text-sm font-bold border-2 border-dashed border-slate-200 rounded-xl">
                                No packing materials added yet — click "Add Row"
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Modal Footer --}}
            <div class="p-5 border-t bg-slate-50 flex gap-3 flex-shrink-0">
                <template x-if="bomModal.step === 1">
                    <button @click="bomModal.show = false" class="flex-1 py-3 border border-slate-200 rounded-xl text-sm font-bold text-slate-600 hover:bg-slate-100 transition">Cancel</button>
                </template>
                <template x-if="bomModal.step > 1">
                    <button @click="bomModal.step--" class="flex-1 py-3 border border-slate-200 rounded-xl text-sm font-bold text-slate-600 hover:bg-slate-100 transition flex items-center justify-center gap-1">
                        <i class="fas fa-chevron-left text-xs"></i> Back
                    </button>
                </template>

                <template x-if="bomModal.step < 4">
                    <button @click="nextStep()" class="flex-1 py-3 bg-amber-500 hover:bg-amber-600 text-white font-black text-sm rounded-xl transition-all shadow-md shadow-amber-200 flex items-center justify-center gap-1">
                        Next <i class="fas fa-chevron-right text-xs"></i>
                    </button>
                </template>
                <template x-if="bomModal.step === 4">
                    <button @click="submitBom()" :disabled="bomModal.submitting"
                            class="flex-1 py-3 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white font-black text-sm rounded-xl transition-all shadow-md shadow-amber-200 disabled:opacity-60 flex items-center justify-center gap-2">
                        <i class="fas fa-save" :class="bomModal.submitting ? 'fa-spin' : ''"></i>
                        <span x-text="bomModal.submitting ? 'Saving...' : (bomModal.editId ? 'Update BOM' : 'Save BOM')"></span>
                    </button>
                </template>
            </div>
        </div>
    </div>

    {{-- ══ Duplicate Modal ══ --}}
    <div class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4" x-show="duplicateModal.show" x-cloak x-transition>
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden flex flex-col border border-slate-100" @click.away="duplicateModal.show = false">
            <div class="p-5 border-b flex items-center justify-between bg-slate-50">
                <div class="flex items-center gap-2">
                    <i class="fas fa-copy text-indigo-500"></i>
                    <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest">Duplicate Costing BOM</h3>
                </div>
                <button @click="duplicateModal.show = false" class="text-slate-400 hover:text-slate-600 transition">
                    <i class="fas fa-times text-sm"></i>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <div class="bg-indigo-50/50 border border-indigo-100 rounded-xl p-3">
                    <div class="text-[10px] font-black text-indigo-500 uppercase tracking-widest mb-0.5">Product Name</div>
                    <div class="text-sm font-bold text-slate-800" x-text="duplicateModal.productName"></div>
                </div>

                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">New BOM Badge</label>
                    <select x-model="duplicateModal.badge" class="w-full bg-white border border-slate-200 rounded-xl py-2.5 px-3 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none transition-all shadow-sm font-semibold">
                        <option value="small">SMALL Badge</option>
                        <option value="big">BIG Badge</option>
                        <option value="bulk">BULK Badge</option>
                    </select>
                </div>
            </div>
            <div class="p-5 border-t bg-slate-50 flex gap-3">
                <button @click="duplicateModal.show = false" class="flex-1 py-2.5 border border-slate-200 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition">Cancel</button>
                <button @click="submitDuplicate()" :disabled="duplicateModal.submitting"
                        class="flex-1 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-black text-xs rounded-xl transition-all shadow-md shadow-indigo-200 disabled:opacity-60 flex items-center justify-center gap-2">
                    <i class="fas fa-save" x-show="!duplicateModal.submitting"></i>
                    <i class="fas fa-circle-notch fa-spin" x-show="duplicateModal.submitting"></i>
                    <span x-text="duplicateModal.submitting ? 'Duplicating...' : 'Duplicate BOM'"></span>
                </button>
            </div>
        </div>
    </div>

</div>

<script>
const __finishedGoods = @json($finishedGoods);
const __rawMaterials  = @json($rawMaterials);
const __bomIds        = @json($boms->pluck('id'));
const __purities      = @json($purities);
const __types         = @json(\App\Models\ProductType::all());

function bomApp() {
    return {
        selectedIds: [],
        bomModal: {
            show: false, editId: null, submitting: false,
            typeFilter: '', rmTypeFilter: '', step: 1,
            form: { finished_product_id: '', badge: '', formulation: '', density: '', yield_quantity: 1, yield_uom: 'KG', items: [] }
        },
        duplicateModal: {
            show: false, bomId: null, productName: '', badge: 'small', submitting: false
        },

        init() {},

        toggleSelect(id, checked) {
            if (checked) {
                if (!this.selectedIds.includes(id)) this.selectedIds.push(id);
            } else {
                this.selectedIds = this.selectedIds.filter(x => x !== id);
            }
        },

        toggleAll(checked) {
            this.selectedIds = checked ? [...__bomIds] : [];
        },

        // ─── BOM / Recipe ─────────────────────────────
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
                const total = matches.reduce((sum, m) => sum + parseFloat(m[1]), 0);
                return total + '%';
            }
            return '100%';
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
            let formulation = parseFloat(this.bomModal.form.formulation);
            if (isNaN(formulation)) {
                const fgForm = this.getFormulation(this.bomModal.form.finished_product_id);
                formulation = parseFloat(fgForm) || 0;
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
            return parseFloat(qty.toFixed(4));
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
            return parseFloat(Math.max(0, remaining).toFixed(4));
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
            return parseFloat(sum.toFixed(4));
        },

        getRemainingIngredientsQty() {
            const batchQty = parseFloat(this.bomModal.form.yield_quantity) || 0;
            const used = this.getUsedIngredientsQty();
            return parseFloat((batchQty - used).toFixed(4));
        },

        hasActiveSolvent() {
            return this.bomModal.form.items.some(i => !i.is_packing && i.is_solvent);
        },

        openBomModal() {
            this.bomModal.editId = null;
            this.bomModal.typeFilter = '';
            this.bomModal.rmTypeFilter = '';
            this.bomModal.step = 1;
            this.bomModal.form = { finished_product_id: '', badge: '', formulation: '', density: '', yield_quantity: 1, yield_uom: 'KG', items: [{ raw_material_id: '', quantity: '', purity: '', rm_type_filter: '', is_packing: false, is_solvent: false }] };
            this.bomModal.show = true;
        },

        editBomFromData(jsonStr) {
            const recipe = JSON.parse(jsonStr);
            this.bomModal.editId = recipe.id;
            this.bomModal.typeFilter = '';
            this.bomModal.rmTypeFilter = '';
            this.bomModal.step = 1;
            this.bomModal.form = {
                finished_product_id: recipe.finished_product_id,
                badge: recipe.badge || '',
                formulation: recipe.formulation || '',
                density: recipe.density || '',
                yield_quantity: recipe.yield_quantity,
                yield_uom: recipe.yield_uom,
                items: (recipe.items || []).map(i => ({
                    raw_material_id: i.raw_material_id,
                    quantity: i.quantity,
                    purity: i.purity || '',
                    rm_type_filter: '',
                    is_packing: this.isPackingMaterial(i.raw_material_id),
                    is_solvent: false
                }))
            };
            this.bomModal.show = true;
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
                
                // Validate total ingredients quantity against batch size
                const batchQty = parseFloat(this.bomModal.form.yield_quantity) || 0;
                const sumIngredients = this.getUsedIngredientsQty();
                if (sumIngredients > batchQty + 0.001) {
                    alert(`Total ingredients quantity (${sumIngredients.toFixed(4)}) cannot exceed the Batch Quantity (${batchQty}).`);
                    return;
                }
                
                this.bomModal.step = 4;
            }
        },

        addRMRow(isPacking = false) {
            this.bomModal.form.items.push({ raw_material_id: '', quantity: '', purity: '', rm_type_filter: '', is_packing: isPacking, is_solvent: false });
        },

        async submitBom() {
            const cleanItems = this.bomModal.form.items.filter(i => i.raw_material_id);
            if (!this.bomModal.form.finished_product_id || !this.bomModal.form.yield_quantity || cleanItems.length === 0) {
                alert('Please fill all required fields and add at least one item.');
                return;
            }
            
            // Validate total ingredients quantity against batch size
            const batchQty = parseFloat(this.bomModal.form.yield_quantity) || 0;
            const sumIngredients = this.getUsedIngredientsQty();
            if (sumIngredients > batchQty + 0.001) {
                alert(`Total ingredients quantity (${sumIngredients.toFixed(4)}) cannot exceed the Batch Quantity (${batchQty}).`);
                return;
            }
            
            this.bomModal.submitting = true;
            const isEdit = !!this.bomModal.editId;
            const url    = isEdit ? `{{ url('costing-boms') }}/${this.bomModal.editId}` : '{{ route('costing.boms.store') }}';
            const method = isEdit ? 'PUT' : 'POST';

            const submissionForm = { ...this.bomModal.form, items: cleanItems };

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
        },

        async deleteBom(id) {
            if (!confirm('Delete this costing BOM?')) return;
            try {
                const resp = await fetch(`{{ url('costing-boms') }}/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    }
                });
                const data = await resp.json();
                if (data.success) {
                    window.location.reload();
                } else alert(data.message || 'Delete failed.');
            } catch(e) { alert('Network error.'); }
        },

        async bulkDelete() {
            if (this.selectedIds.length === 0) return;
            if (!confirm(`Delete ${this.selectedIds.length} selected costing BOMs?`)) return;

            try {
                const resp = await fetch('{{ route('costing.boms.bulk-delete') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ ids: this.selectedIds })
                });
                const data = await resp.json();
                if (data.success) {
                    window.location.reload();
                } else alert(data.message || 'Bulk delete failed.');
            } catch(e) { alert('Network error.'); }
        },

        openDuplicateModal(id, name) {
            this.duplicateModal.bomId = id;
            this.duplicateModal.productName = name;
            this.duplicateModal.badge = 'small';
            this.duplicateModal.show = true;
        },

        async submitDuplicate() {
            if (!this.duplicateModal.badge) {
                alert('Please select a badge.');
                return;
            }
            this.duplicateModal.submitting = true;
            try {
                const resp = await fetch(`{{ url('costing-boms') }}/${this.duplicateModal.bomId}/duplicate`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ badge: this.duplicateModal.badge })
                });
                const data = await resp.json();
                if (data.success) {
                    this.duplicateModal.show = false;
                    window.location.reload();
                } else {
                    alert(data.message || 'Duplicate failed.');
                }
            } catch(e) {
                alert('Network error.');
            } finally {
                this.duplicateModal.submitting = false;
            }
        }
    };
}
</script>
@endsection
