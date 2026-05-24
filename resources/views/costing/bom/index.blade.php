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
                <select name="type_id" class="w-full bg-white border border-slate-200 rounded-xl py-2.5 px-3 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none transition-all shadow-sm">
                    <option value="">All Types</option>
                    @foreach($types as $type)
                        <option value="{{ $type->id }}" {{ request('type_id') == $type->id ? 'selected' : '' }}>
                            {{ $type->type_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold py-2.5 px-5 rounded-xl shadow-sm hover:shadow-md transition-all flex items-center gap-2">
                    <i class="fas fa-filter"></i> Filter
                </button>
                @if(request()->anyFilled(['search', 'type_id']))
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
                    <th class="py-3 px-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Yield</th>
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
                        <div class="font-bold text-slate-800 text-sm">{{ $bom->finishedProduct->name ?? '—' }}</div>
                        <div class="flex items-center gap-2 mt-1">
                            @php $typeName = $bom->finishedProduct->type->type_name ?? 'N/A'; @endphp
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-amber-100 text-amber-700 border border-amber-200">
                                {{ $typeName }}
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

            {{-- Modal Body --}}
            <div class="flex-1 overflow-y-auto p-6 space-y-5">

                {{-- Finished Product --}}
                <div>
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Finished Product *</label>
                    <div class="grid grid-cols-2 gap-3 mb-2">
                        <select x-model="bomModal.typeFilter" @change="bomModal.form.finished_product_id = ''"
                                class="px-3 py-2.5 text-sm border border-slate-200 rounded-xl focus:ring-2 focus:ring-amber-400 outline-none font-medium">
                            <option value="">All Types</option>
                            @foreach($types as $type)
                            <option value="{{ $type->id }}">{{ $type->type_name }}</option>
                            @endforeach
                        </select>
                        <select x-model="bomModal.form.finished_product_id"
                                class="px-3 py-2.5 text-sm border border-slate-200 rounded-xl focus:ring-2 focus:ring-amber-400 outline-none font-medium" required>
                            <option value="">Select Finished Good</option>
                            <template x-for="p in filteredFGs" :key="p.id">
                                <option :value="p.id" x-text="p.name + (p.pack_name ? ' ['+p.pack_name+']' : '') + ' ('+p.item_code+')'"></option>
                            </template>
                        </select>
                    </div>
                </div>

                {{-- Yield --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Yield Quantity *</label>
                        <input type="number" step="0.001" x-model="bomModal.form.yield_quantity"
                               class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:ring-2 focus:ring-amber-400 outline-none font-bold" placeholder="e.g. 1">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Yield UOM *</label>
                        <input type="text" x-model="bomModal.form.yield_uom"
                               class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:ring-2 focus:ring-amber-400 outline-none font-bold" placeholder="e.g. BOX">
                    </div>
                </div>

                {{-- Raw Materials / Ingredients --}}
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest">Raw Materials / Ingredients *</label>
                            <p class="text-[10px] text-slate-400 mt-0.5">Quantities are per 1 yield unit</p>
                        </div>
                        <button type="button" @click="addRMRow()"
                                class="flex items-center gap-1.5 px-3 py-1.5 bg-amber-50 border border-amber-200 text-amber-700 font-black text-xs rounded-lg hover:bg-amber-100 transition-all">
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

                    <div class="space-y-2 max-h-[280px] overflow-y-auto pr-1">
                        <template x-for="(item, idx) in bomModal.form.items" :key="idx">
                            <div class="grid grid-cols-12 gap-2 p-3 bg-amber-50/50 border border-amber-100 rounded-xl items-center">
                                <div class="col-span-7">
                                    <select x-model="item.raw_material_id"
                                            class="w-full px-2 py-2 text-xs border border-slate-200 rounded-lg focus:ring-1 focus:ring-amber-400 outline-none font-bold">
                                        <option value="">Select Raw Material</option>
                                        <template x-for="rm in getFilteredRMs(item.rm_type_filter)" :key="rm.id">
                                            <option :value="rm.id" :selected="rm.id == item.raw_material_id"
                                                    x-text="rm.name + (rm.pack_name ? ' ['+rm.pack_name+']' : '') + ' ('+rm.item_code+')'"></option>
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
                                    <button @click="bomModal.form.items.splice(idx, 1)" class="w-6 h-6 rounded-lg flex items-center justify-center text-red-400 hover:text-red-600 hover:bg-red-50 transition">
                                        <i class="fas fa-trash-alt text-[10px]"></i>
                                    </button>
                                </div>
                            </div>
                        </template>
                        <div x-show="bomModal.form.items.length === 0" class="text-center py-6 text-slate-400 text-sm font-bold border-2 border-dashed border-slate-200 rounded-xl">
                            No ingredients added yet — click "Add Row"
                        </div>
                    </div>
                </div>
            </div>

            {{-- Modal Footer --}}
            <div class="p-5 border-t bg-slate-50 flex gap-3 flex-shrink-0">
                <button @click="bomModal.show = false" class="flex-1 py-3 border border-slate-200 rounded-xl text-sm font-bold text-slate-600 hover:bg-slate-100 transition">Cancel</button>
                <button @click="submitBom()" :disabled="bomModal.submitting"
                        class="flex-1 py-3 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white font-black text-sm rounded-xl transition-all shadow-md shadow-amber-200 disabled:opacity-60 flex items-center justify-center gap-2">
                    <i class="fas fa-save" :class="bomModal.submitting ? 'fa-spin' : ''"></i>
                    <span x-text="bomModal.submitting ? 'Saving...' : (bomModal.editId ? 'Update BOM' : 'Save BOM')"></span>
                </button>
            </div>
        </div>
    </div>

</div>

<script>
const __finishedGoods = @json($finishedGoods);
const __rawMaterials  = @json($rawMaterials);
const __bomIds        = @json($boms->pluck('id'));

function bomApp() {
    return {
        selectedIds: [],
        bomModal: {
            show: false, editId: null, submitting: false,
            typeFilter: '', rmTypeFilter: '',
            form: { finished_product_id: '', yield_quantity: 1, yield_uom: 'BOX', items: [] }
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
        getFilteredRMs(filter) {
            if (!filter && !this.bomModal.rmTypeFilter) return __rawMaterials;
            const f = filter || this.bomModal.rmTypeFilter;
            return __rawMaterials.filter(r => r.rm_type === f);
        },
        getItemUom(id) {
            const rm = __rawMaterials.find(r => r.id == id);
            return rm ? rm.uom : '';
        },

        openBomModal() {
            this.bomModal.editId = null;
            this.bomModal.typeFilter = '';
            this.bomModal.rmTypeFilter = '';
            this.bomModal.form = { finished_product_id: '', yield_quantity: 1, yield_uom: 'BOX', items: [{ raw_material_id: '', quantity: '', rm_type_filter: '' }] };
            this.bomModal.show = true;
        },

        editBomFromData(jsonStr) {
            const recipe = JSON.parse(jsonStr);
            this.bomModal.editId = recipe.id;
            this.bomModal.typeFilter = '';
            this.bomModal.rmTypeFilter = '';
            this.bomModal.form = {
                finished_product_id: recipe.finished_product_id,
                yield_quantity: recipe.yield_quantity,
                yield_uom: recipe.yield_uom,
                items: (recipe.items || []).map(i => ({
                    raw_material_id: i.raw_material_id,
                    quantity: i.quantity,
                    rm_type_filter: ''
                }))
            };
            this.bomModal.show = true;
        },

        addRMRow() {
            this.bomModal.form.items.push({ raw_material_id: '', quantity: '', rm_type_filter: '' });
        },

        async submitBom() {
            if (!this.bomModal.form.finished_product_id || !this.bomModal.form.yield_quantity || this.bomModal.form.items.length === 0) {
                alert('Please fill all required fields and add at least one raw material.');
                return;
            }
            this.bomModal.submitting = true;
            const isEdit = !!this.bomModal.editId;
            const url    = isEdit ? `/costing-boms/${this.bomModal.editId}` : '{{ route('costing.boms.store') }}';
            const method = isEdit ? 'PUT' : 'POST';

            try {
                const resp = await fetch(url, {
                    method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(this.bomModal.form)
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
                const resp = await fetch(`/costing-boms/${id}`, {
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
        }
    };
}
</script>
@endsection
