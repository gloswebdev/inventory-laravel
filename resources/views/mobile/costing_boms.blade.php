@extends('layouts.mobile')

@section('content')
<div x-data="mobileCostingBomsApp()" x-init="init()">

    {{-- Page Header --}}
    <div class="mb-6 animate-in fade-in slide-in-from-top duration-500">
        <div class="flex items-center justify-between gap-3 mb-1">
            <div class="flex items-center gap-3">
                <a href="{{ route('mobile.dashboard') }}"
                   class="w-9 h-9 rounded-xl bg-white/60 border border-white flex items-center justify-center text-slate-600 active:scale-90 transition-transform">
                    <i class="fas fa-chevron-left text-sm"></i>
                </a>
                <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center shadow-lg shadow-amber-200">
                    <i class="fas fa-layer-group text-white text-base"></i>
                </div>
                <div>
                    <h2 class="text-xl font-900 text-slate-800 tracking-tighter leading-none">Costing BOMs</h2>
                    <p class="text-[9px] font-black uppercase tracking-widest text-slate-400 mt-0.5">Bill of Materials Master</p>
                </div>
            </div>

            @if(Auth::user()->hasPermission('mobile_costing_bom', 'create') || Auth::user()->hasPermission('costing_bom', 'create'))
            <button @click="showCreateModal = true"
                    class="w-10 h-10 rounded-2xl bg-amber-500 text-white flex items-center justify-center shadow-md shadow-amber-200 active:scale-90 transition-transform">
                <i class="fas fa-plus text-sm"></i>
            </button>
            @endif
        </div>
    </div>

    {{-- Search & Filter --}}
    <div class="mb-4 animate-in fade-in slide-in-from-bottom duration-500 delay-100">
        <form method="GET" action="{{ route('mobile.costing.boms') }}" class="relative mb-3">
            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="Search BOM product or code..."
                   class="w-full pl-11 pr-10 py-3.5 bg-white/70 backdrop-blur-xl border border-white/80 rounded-2xl text-sm font-bold text-slate-800 placeholder:text-slate-400 outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400 transition-all shadow-sm">
            @if(request('search'))
            <a href="{{ route('mobile.costing.boms') }}" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                <i class="fas fa-times text-sm"></i>
            </a>
            @endif
        </form>
    </div>

    {{-- BOM List Cards --}}
    <div class="space-y-3 mb-6">
        @forelse($boms as $bom)
        <div class="bg-white/80 backdrop-blur-xl border border-white rounded-3xl p-5 shadow-sm transition-all relative overflow-hidden"
             x-data="{ open: false }">
            <div class="flex items-start justify-between gap-3 cursor-pointer" @click="open = !open">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center font-black text-sm shrink-0 border border-amber-100">
                        <i class="fas fa-boxes-stacked"></i>
                    </div>
                    <div>
                        <div class="font-900 text-slate-800 text-sm leading-tight">
                            {{ $bom->finishedProduct->name ?? '—' }}
                        </div>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="text-[9px] font-black uppercase text-slate-400 tracking-wider">
                                {{ $bom->finishedProduct->item_code ?? '—' }}
                            </span>
                            @if($bom->badge)
                            <span class="px-2 py-0.5 rounded-full text-[8px] font-black uppercase bg-amber-100 text-amber-700">
                                {{ $bom->badge }}
                            </span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="text-right shrink-0">
                    <div class="text-xs font-900 text-amber-700">
                        {{ number_format($bom->yield_quantity, 2) }} {{ $bom->yield_uom }}
                    </div>
                    <div class="text-[8px] font-bold text-slate-400 uppercase mt-0.5">
                        {{ $bom->items->count() }} RM Items
                    </div>
                </div>
            </div>

            {{-- Collapsible RM Details --}}
            <div x-show="open" x-cloak class="mt-4 pt-4 border-t border-slate-100 space-y-2">
                <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2">Raw Materials Breakdown</div>
                @foreach($bom->items as $item)
                <div class="flex items-center justify-between p-2.5 bg-slate-50/80 rounded-xl border border-slate-100">
                    <div class="flex items-center gap-2.5">
                        <div class="w-2 h-2 rounded-full {{ strtoupper(trim($item->rawMaterial->rm_type ?? '')) === 'TECHNICAL' ? 'bg-amber-500' : 'bg-slate-300' }}"></div>
                        <div>
                            <div class="text-[11px] font-bold text-slate-700 leading-tight">
                                {{ $item->rawMaterial->name ?? 'Unknown RM' }}
                            </div>
                            <div class="text-[9px] text-slate-400 font-semibold">
                                {{ $item->rawMaterial->item_code ?? '—' }}
                                @if($item->purity)
                                | Purity: <span class="text-amber-600 font-black">{{ $item->purity }}%</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="text-[11px] font-black text-slate-800">
                        {{ number_format($item->quantity, 3) }} <span class="text-[9px] text-slate-400 font-bold">{{ $item->rawMaterial->uom ?? 'KG' }}</span>
                    </div>
                </div>
                @endforeach

                {{-- Action Buttons --}}
                <div class="flex justify-end gap-2 pt-3">
                    <button @click="duplicateBom({{ $bom->id }})"
                            class="px-3 py-1.5 bg-amber-50 text-amber-700 rounded-xl text-[10px] font-black uppercase tracking-wider border border-amber-200 active:scale-95 transition-all">
                        <i class="fas fa-copy mr-1"></i> Duplicate
                    </button>
                    @if(Auth::user()->hasPermission('mobile_costing_bom', 'delete') || Auth::user()->hasPermission('costing_bom', 'delete'))
                    <button @click="deleteBom({{ $bom->id }})"
                            class="px-3 py-1.5 bg-rose-50 text-rose-600 rounded-xl text-[10px] font-black uppercase tracking-wider border border-rose-200 active:scale-95 transition-all">
                        <i class="fas fa-trash-alt mr-1"></i> Delete
                    </button>
                    @endif
                </div>
            </div>
        </div>
        @empty
        <div class="text-center py-12 text-slate-400 font-bold">
            <i class="fas fa-layer-group text-3xl mb-2 opacity-40"></i>
            <div>No Costing BOMs found.</div>
        </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if($boms->hasPages())
    <div class="mb-8">
        {{ $boms->links() }}
    </div>
    @endif

    {{-- Create BOM Modal --}}
    <div x-show="showCreateModal" x-cloak class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-black/60 backdrop-blur-sm"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-full"
         x-transition:enter-end="opacity-100 translate-y-0">
        <div class="bg-white w-full max-w-lg rounded-t-[2.5rem] sm:rounded-[2.5rem] p-6 max-h-[90vh] overflow-y-auto space-y-4">
            <div class="flex justify-between items-center pb-2 border-b border-slate-100">
                <h3 class="text-lg font-900 text-slate-800 uppercase tracking-tight">Create Costing BOM</h3>
                <button @click="showCreateModal = false" class="text-slate-400 hover:text-slate-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form @submit.prevent="submitCreateBom()">
                <div class="space-y-4">
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-1">Finished Product</label>
                        <select x-model="newBom.finished_product_id" required
                                class="w-full py-3 px-4 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-bold text-slate-800 outline-none focus:ring-2 focus:ring-amber-400">
                            <option value="">Select Product...</option>
                            @foreach($finishedGoods as $fg)
                            <option value="{{ $fg->id }}">{{ $fg->name }} ({{ $fg->item_code }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-1">Batch Yield Qty</label>
                            <input type="number" step="0.001" x-model="newBom.yield_quantity" required
                                   class="w-full py-3 px-4 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-bold text-slate-800 outline-none focus:ring-2 focus:ring-amber-400">
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-1">Badge</label>
                            <select x-model="newBom.badge"
                                    class="w-full py-3 px-4 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-bold text-slate-800 outline-none focus:ring-2 focus:ring-amber-400">
                                <option value="">Standard</option>
                                <option value="small">Small</option>
                                <option value="big">Big</option>
                                <option value="bulk">Bulk</option>
                            </select>
                        </div>
                    </div>

                    {{-- Dynamic RM Items --}}
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Raw Materials</label>
                            <button type="button" @click="addRmRow()" class="text-xs font-black text-amber-600 uppercase">
                                + Add RM
                            </button>
                        </div>

                        <div class="space-y-2">
                            <template x-for="(item, idx) in newBom.items" :key="idx">
                                <div class="p-3 bg-slate-50 rounded-2xl border border-slate-200 space-y-2 relative">
                                    <button type="button" @click="removeRmRow(idx)" class="absolute top-2 right-2 text-rose-500 text-xs">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                    <select x-model="item.raw_material_id" required
                                            class="w-full py-2 px-3 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-800">
                                        <option value="">Select RM...</option>
                                        @foreach($rawMaterials as $rm)
                                        <option value="{{ $rm->id }}">{{ $rm->name }} ({{ $rm->item_code }})</option>
                                        @endforeach
                                    </select>
                                    <div class="grid grid-cols-2 gap-2">
                                        <input type="number" step="0.001" x-model="item.quantity" placeholder="Qty (KG)" required
                                               class="py-2 px-3 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-800">
                                        <input type="number" step="0.1" x-model="item.purity" placeholder="Purity %"
                                               class="py-2 px-3 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-800">
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <button type="submit" :disabled="submitting"
                            class="w-full py-4 bg-gradient-to-r from-amber-500 to-orange-600 text-white font-900 rounded-2xl text-sm shadow-lg shadow-amber-200 active:scale-95 transition-all">
                        <span x-text="submitting ? 'Saving...' : 'Save Costing BOM'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
function mobileCostingBomsApp() {
    return {
        showCreateModal: false,
        submitting: false,
        newBom: {
            finished_product_id: '',
            yield_quantity: 100,
            yield_uom: 'KG',
            badge: '',
            items: [{ raw_material_id: '', quantity: '', purity: '' }]
        },

        init() {},

        addRmRow() {
            this.newBom.items.push({ raw_material_id: '', quantity: '', purity: '' });
        },

        removeRmRow(idx) {
            if (this.newBom.items.length > 1) {
                this.newBom.items.splice(idx, 1);
            }
        },

        async submitCreateBom() {
            this.submitting = true;
            try {
                const resp = await fetch('{{ route('mobile.costing.boms.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify(this.newBom)
                });
                const data = await resp.json();
                if (data.success) {
                    alert('Costing BOM created!');
                    window.location.reload();
                } else {
                    alert(data.message || 'Failed to create BOM.');
                }
            } catch(e) {
                alert('Network error.');
            } finally {
                this.submitting = false;
            }
        },

        async duplicateBom(id) {
            const badge = prompt('Enter badge for duplicated BOM (small, big, bulk):', 'small');
            if (!badge) return;

            try {
                const resp = await fetch(`{{ url('mobile/costing-boms') }}/${id}/duplicate`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({ badge })
                });
                const data = await resp.json();
                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert(data.message || 'Failed to duplicate BOM.');
                }
            } catch(e) {
                alert('Error duplicating BOM.');
            }
        },

        async deleteBom(id) {
            if (!confirm('Are you sure you want to delete this Costing BOM?')) return;
            try {
                const resp = await fetch(`{{ url('mobile/costing-boms') }}/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    }
                });
                const data = await resp.json();
                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert(data.message || 'Failed to delete.');
                }
            } catch(e) {
                alert('Error deleting BOM.');
            }
        }
    };
}
</script>
@endsection
