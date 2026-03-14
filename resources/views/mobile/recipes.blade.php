@extends('layouts.mobile')

@section('content')
<div class="space-y-8 pb-10" x-data="recipeApp()">
    <!-- Header Block -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-3xl font-900 text-slate-800 tracking-tighter">Recipes</h2>
            <p class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400 mt-1">Master Production Profiles</p>
        </div>
        <div class="flex gap-2">
            <button @click="showSearch = !showSearch" :class="showSearch ? 'bg-indigo-50 text-indigo-600 border-indigo-100' : 'bg-white border-slate-100'" class="w-12 h-12 rounded-2xl flex items-center justify-center border shadow-sm transition-all active:scale-90">
                <i class="fas fa-magnifying-glass text-xs"></i>
            </button>
            @if(Auth::user()->hasFeature('mobile_recipes', 'edit'))
            <button @click="openModal()" class="w-12 h-12 rounded-2xl bg-indigo-600 text-white flex items-center justify-center shadow-lg shadow-indigo-200 transition-all active:scale-90">
                <i class="fas fa-plus text-xs"></i>
            </button>
            @endif
        </div>
    </div>

    <!-- Search Section -->
    <div x-show="showSearch" x-cloak x-transition class="glass-premium p-6 rounded-[2.5rem] border border-white/80">
        <div class="relative group">
            <input 
                type="text" 
                x-model="searchTerm" 
                @keyup.enter="handleSearch"
                placeholder="Search Item Name or Code..." 
                class="w-full bg-slate-50 border-none rounded-2xl py-4 px-12 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500 shadow-inner"
            >
            <div class="absolute left-5 top-1/2 -translate-y-1/2 text-slate-300">
                <i class="fas fa-search text-[10px]"></i>
            </div>
            <template x-if="searchTerm">
                <button @click="clearSearch" class="absolute right-5 top-1/2 -translate-y-1/2 text-slate-300 hover:text-rose-500">
                    <i class="fas fa-times-circle text-[10px]"></i>
                </button>
            </template>
        </div>
    </div>

    <!-- Recipe Cards -->
    <div class="space-y-6">
        @foreach($recipes as $recipe)
        <div class="glass-premium p-8 rounded-[3rem] border border-white/80 space-y-6 shadow-sm hover:shadow-xl transition-all duration-500 relative overflow-hidden">
            <div class="flex items-start justify-between">
                <div>
                    <h3 class="text-xs font-900 text-slate-800 uppercase italic tracking-tighter">{{ $recipe->finishedProduct ? $recipe->finishedProduct->name : 'N/A' }}</h3>
                    <div class="flex items-center gap-2 mt-1.5">
                        <span class="text-[8px] font-black text-indigo-500 uppercase tracking-widest">{{ $recipe->finishedProduct ? $recipe->finishedProduct->item_code : 'N/A' }}</span>
                        <div class="w-1 h-1 bg-slate-200 rounded-full"></div>
                        <span class="text-[8px] text-slate-400 font-bold italic">{{ $recipe->finishedProduct ? ($recipe->finishedProduct->type ? $recipe->finishedProduct->type->type_name : 'Unknown') : 'Unknown' }}</span>
                    </div>
                </div>
                <div class="flex flex-col items-end gap-2">
                    <div class="grad-indigo px-3 py-1.5 rounded-xl border border-white/20 shadow-md">
                        <div class="text-[10px] font-900 italic text-white tracking-widest">{{ number_format($recipe->yield_quantity, 1) }} {{ $recipe->yield_uom }}</div>
                    </div>
                    @if(Auth::user()->hasFeature('mobile_recipes', 'edit') || Auth::user()->hasFeature('mobile_recipes', 'delete'))
                    <div class="flex gap-1.5 mt-1">
                        @if(Auth::user()->hasFeature('mobile_recipes', 'edit'))
                        <button @click="openModal({{ $recipe->id }})" class="w-7 h-7 bg-white/50 rounded-lg flex items-center justify-center text-indigo-500 border border-indigo-50 transition-all active:scale-95">
                            <i class="fas fa-edit text-[10px]"></i>
                        </button>
                        @endif
                        @if(Auth::user()->hasFeature('mobile_recipes', 'delete'))
                        <button @click="deleteRecipe({{ $recipe->id }})" class="w-7 h-7 bg-white/50 rounded-lg flex items-center justify-center text-rose-500 border border-rose-50 transition-all active:scale-95">
                            <i class="fas fa-trash-alt text-[10px]"></i>
                        </button>
                        @endif
                    </div>
                    @endif
                </div>
            </div>

            <div class="space-y-4">
                <div class="flex items-center gap-3 ml-1">
                    <i class="fas fa-vial-circle-check text-indigo-500 text-[10px]"></i>
                    <h4 class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em]">Ingredients Components</h4>
                </div>
                
                <div class="space-y-3">
                    @foreach($recipe->items as $item)
                    <div class="p-4 bg-slate-50/50 rounded-2xl flex items-center justify-between border border-slate-100/30">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 bg-white rounded-xl flex items-center justify-center text-slate-400 border border-slate-100 shadow-sm text-[8px]">
                                <i class="fas fa-box-open"></i>
                            </div>
                            <div>
                                <div class="text-[9px] font-900 text-slate-700 tracking-tight uppercase max-w-[120px] truncate">{{ $item->rawMaterial ? $item->rawMaterial->name : 'Unknown' }}</div>
                                <div class="text-[7px] text-slate-400 font-black uppercase tracking-widest">{{ $item->rawMaterial ? $item->rawMaterial->item_code : '?' }}</div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-[11px] font-900 text-slate-800 tracking-tighter">{{ number_format($item->quantity, 3) }}</div>
                            <div class="text-[7px] font-black text-indigo-400 uppercase tracking-widest">{{ $item->rawMaterial ? $item->rawMaterial->uom : 'Units' }}</div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endforeach

        <!-- Pagination -->
        <div class="px-2">
            {{ $recipes->links('vendor.pagination.mobile-custom') }}
        </div>

        @if(count($recipes) === 0)
        <div class="py-20 text-center opacity-40 italic">
            <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fas fa-flask text-slate-200 text-4xl"></i>
            </div>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Zero Master Recipes Defined</p>
        </div>
        @endif
    </div>

    <!-- Recipe Modal -->
    <div x-show="showModal" x-cloak x-transition.opacity class="fixed inset-0 z-[100] flex items-end sm:items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
        <div x-show="showModal" x-transition.scale.origin.bottom class="w-full max-w-lg bg-white rounded-[3rem] overflow-hidden shadow-2xl space-y-6" @click.away="closeModal">
            <div class="p-8 pb-0">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-2xl font-900 text-slate-800 tracking-tighter" x-text="editingId ? 'Edit Recipe' : 'New Recipe'"></h2>
                    <button @click="closeModal" class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center text-slate-400">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="space-y-6 max-h-[60vh] overflow-y-auto px-1 -mx-1 scrollbar-hide pb-8">
                    <!-- Finished Product Selection -->
                    <div class="space-y-3">
                        <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-1">Finished Product</label>
                        <select x-model="formData.finished_product_id" class="w-full bg-slate-50 border-none rounded-2xl py-4 px-6 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500 shadow-inner">
                            <option value="">Select Finished Good</option>
                            @foreach($finishedGoods as $product)
                            <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->item_code }})</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Yield Config -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-3">
                            <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-1">Yield Qty</label>
                            <input type="number" step="0.001" x-model="formData.yield_quantity" class="w-full bg-slate-50 border-none rounded-2xl py-4 px-6 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500 shadow-inner">
                        </div>
                        <div class="space-y-3">
                            <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-1">Yield UOM</label>
                            <input type="text" x-model="formData.yield_uom" placeholder="e.g. BOX" class="w-full bg-slate-50 border-none rounded-2xl py-4 px-6 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500 shadow-inner">
                        </div>
                    </div>

                    <!-- Ingredients List -->
                    <div class="space-y-4">
                        <div class="flex items-center justify-between ml-1">
                            <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em]">Ingredients Components</label>
                            <button @click="addIngredient" class="text-[9px] font-black text-indigo-600 uppercase tracking-widest">+ Add Row</button>
                        </div>
                        
                        <div class="space-y-3">
                            <template x-for="(item, index) in formData.items" :key="index">
                                <div class="p-4 bg-indigo-50/30 rounded-3xl border border-indigo-100/50 space-y-4">
                                    <div class="flex items-center justify-between">
                                        <div class="text-[8px] font-black text-indigo-400 uppercase tracking-widest" x-text="'Ingredient #' + (index + 1)"></div>
                                        <button @click="removeIngredient(index)" class="text-rose-400">
                                            <i class="fas fa-trash-alt text-[10px]"></i>
                                        </button>
                                    </div>
                                    <div class="space-y-3">
                                        <select x-model="item.raw_material_id" class="w-full bg-white border-none rounded-xl py-3 px-4 text-[10px] font-bold text-slate-700 focus:ring-1 focus:ring-indigo-500 shadow-sm">
                                            <option value="">Select Raw Material</option>
                                            @foreach($rawMaterials as $rm)
                                            <option value="{{ $rm->id }}">{{ $rm->name }} ({{ $rm->item_code }})</option>
                                            @endforeach
                                        </select>
                                        <div class="relative">
                                            <input type="number" step="0.001" x-model="item.quantity" placeholder="Quantity" class="w-full bg-white border-none rounded-xl py-3 px-4 text-[10px] font-bold text-slate-700 focus:ring-1 focus:ring-indigo-500 shadow-sm">
                                            <div class="absolute right-4 top-1/2 -translate-y-1/2 text-[8px] font-black text-slate-400 uppercase" x-text="getItemUom(item.raw_material_id)"></div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="bg-slate-50 p-8 pt-6 flex items-center gap-4">
                <button @click="closeModal" class="flex-1 bg-white border border-slate-200 text-slate-600 font-bold py-4 rounded-2xl active:scale-95 transition-all text-sm">Cancel</button>
                <button @click="submitRecipe" :disabled="submitting" class="flex-1 grad-indigo text-white font-bold py-4 rounded-2xl shadow-lg shadow-indigo-100 active:scale-95 transition-all text-sm flex items-center justify-center gap-2">
                    <span x-show="!submitting" x-text="editingId ? 'Update Recipe' : 'Save Recipe'"></span>
                    <i x-show="submitting" class="fas fa-spinner fa-spin"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    function recipeApp() {
        return {
            showSearch: false,
            searchTerm: '{{ request('search') }}',
            showModal: false,
            submitting: false,
            editingId: null,
            rawMaterials: @json($rawMaterials),
            formData: {
                finished_product_id: '',
                yield_quantity: 1,
                yield_uom: 'BOX',
                items: []
            },

            getItemUom(id) {
                const item = this.rawMaterials.find(rm => rm.id == id);
                return item ? item.uom : 'UNITS';
            },

            addIngredient() {
                this.formData.items.push({ raw_material_id: '', quantity: '' });
            },

            removeIngredient(index) {
                this.formData.items.splice(index, 1);
            },

            openModal(id = null) {
                this.editingId = id;
                if(id) {
                    fetch(`{{ url('/mobile/recipes') }}/${id}`)
                        .then(res => res.json())
                        .then(data => {
                            if(data.success) {
                                this.formData = {
                                    finished_product_id: data.recipe.finished_product_id,
                                    yield_quantity: data.recipe.yield_quantity,
                                    yield_uom: data.recipe.yield_uom,
                                    items: data.recipe.items.map(i => ({
                                        raw_material_id: i.raw_material_id,
                                        quantity: i.quantity
                                    }))
                                };
                                this.showModal = true;
                            }
                        });
                } else {
                    this.formData = {
                        finished_product_id: '',
                        yield_quantity: 1,
                        yield_uom: 'BOX',
                        items: [{ raw_material_id: '', quantity: '' }]
                    };
                    this.showModal = true;
                }
            },

            closeModal() {
                this.showModal = false;
                this.editingId = null;
            },

            async submitRecipe() {
                if(!this.formData.finished_product_id || this.formData.items.length === 0) {
                    alert('Please fill all required fields');
                    return;
                }

                this.submitting = true;
                const url = this.editingId ? `{{ url('/mobile/recipes') }}/${this.editingId}/update` : `{{ route('mobile.recipes.store') }}`;
                
                try {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify(this.formData)
                    });

                    const res = await response.json();
                    if (res.success) {
                        window.location.reload();
                    } else {
                        alert(res.message);
                    }
                } catch (e) {
                    alert('Error saving recipe');
                } finally {
                    this.submitting = false;
                }
            },

            async deleteRecipe(id) {
                if(!confirm('Are you sure you want to delete this recipe?')) return;

                try {
                    const response = await fetch(`{{ url('/mobile/recipes') }}/${id}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });

                    const res = await response.json();
                    if (res.success) {
                        window.location.reload();
                    } else {
                        alert(res.message);
                    }
                } catch (e) {
                    alert('Error deleting recipe');
                }
            },

            handleSearch() {
                const url = new URL(window.location.href);
                if (this.searchTerm) {
                    url.searchParams.set('search', this.searchTerm);
                } else {
                    url.searchParams.delete('search');
                }
                url.searchParams.delete('page');
                window.location.href = url.toString();
            },

            clearSearch() {
                this.searchTerm = '';
                this.handleSearch();
            }
        }
    }
</script>
@endsection
