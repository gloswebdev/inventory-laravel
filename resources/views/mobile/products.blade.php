@extends('layouts.mobile')

@section('content')
<div class="space-y-8 pb-10" x-data="productMasterApp()">
    <!-- Header Area -->
    <div class="flex items-center justify-between bg-white/50 backdrop-blur-2xl p-6 rounded-[2.5rem] border border-white/70 shadow-xl shadow-indigo-100/20 relative overflow-hidden mb-2">
        <div class="absolute -right-10 -top-10 w-40 h-40 bg-indigo-500/10 rounded-full blur-3xl"></div>
        <div class="relative z-10 flex items-center justify-between w-full">
        <div>
            <h2 class="text-3xl font-900 text-slate-800 font-900 tracking-tighter">Products</h2>
            <p class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400 mt-1">Master Data Management</p>
        </div>
        <div class="flex items-center gap-3">
            @if(Auth::user()->hasFeature('mobile_products', 'edit'))
            <button @click="openCreate" class="w-12 h-12 grad-indigo rounded-2xl flex items-center justify-center text-white shadow-lg shadow-indigo-100 border-2 border-white transition-all active:scale-90">
                <i class="fas fa-plus text-xs"></i>
            </button>
            @endif
            @if(Auth::user()->hasFeature('mobile_products', 'sync'))
            <button @click="triggerSync" :disabled="syncing" class="w-12 h-12 bg-white rounded-2xl flex items-center justify-center text-indigo-500 border border-white/60 shadow-md transition-all active:scale-90 overflow-hidden relative">
                <i class="fas fa-rotate text-xs" :class="syncing ? 'animate-spin' : ''"></i>
            </button>
            @endif
            <button @click="showSearch = !showSearch" :class="showSearch ? 'bg-indigo-50 text-indigo-600 border-indigo-100' : 'bg-white border-white/60'" class="w-12 h-12 rounded-2xl flex items-center justify-center border shadow-md transition-all active:scale-90">
                <i class="fas fa-search text-xs"></i>
            </button>
        </div>
    </div>
</div>

    <!-- Quick Search & Filters -->
    <div x-show="showSearch" x-cloak x-transition class="bg-white/70 backdrop-blur-xl border border-white/60 shadow-lg shadow-indigo-100/30 hover:shadow-xl transition-all p-6 rounded-[2.5rem] space-y-4">
        <!-- Search Input -->
        <div class="relative group">
            <input 
                type="text" 
                x-model="searchTerm" 
                @keyup.enter="handleSearch"
                placeholder="Find item name or code..." 
                class="w-full bg-white/60 backdrop-blur-md border-none rounded-2xl py-4 px-12 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500 shadow-inner"
            >
            <div class="absolute left-5 top-1/2 -translate-y-1/2 text-slate-300">
                <i class="fas fa-magnifying-glass text-[10px]"></i>
            </div>
            <template x-if="searchTerm">
                <button @click="searchTerm = ''; handleSearch()" class="absolute right-5 top-1/2 -translate-y-1/2 text-slate-300 hover:text-rose-500 transition-colors">
                    <i class="fas fa-times-circle text-[10px]"></i>
                </button>
            </template>
        </div>
        
        <!-- Filters Grid -->
        <div class="grid grid-cols-2 gap-3">
            <!-- Product Type -->
            <div class="relative group">
                <select x-model="filters.type_id" @change="handleSearch" class="w-full bg-white/60 backdrop-blur-md border-none rounded-xl py-3 px-4 pr-8 text-[10px] font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500 shadow-inner appearance-none cursor-pointer">
                    <option value="">All Types</option>
                    @foreach($types as $type)
                    <option value="{{ $type->id }}">{{ $type->type_name }}</option>
                    @endforeach
                </select>
                <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-slate-300">
                    <i class="fas fa-chevron-down text-[8px]"></i>
                </div>
            </div>

            <!-- Product Group -->
            <div class="relative group">
                <select x-model="filters.group_id" @change="handleSearch" class="w-full bg-white/60 backdrop-blur-md border-none rounded-xl py-3 px-4 pr-8 text-[10px] font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500 shadow-inner appearance-none cursor-pointer">
                    <option value="">All Groups</option>
                    @foreach($groups as $group)
                    <option value="{{ $group->id }}">{{ $group->group_name }}</option>
                    @endforeach
                </select>
                <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-slate-300">
                    <i class="fas fa-chevron-down text-[8px]"></i>
                </div>
            </div>

            <!-- Category -->
            <div class="relative group">
                <select x-model="filters.category" @change="handleSearch" class="w-full bg-white/60 backdrop-blur-md border-none rounded-xl py-3 px-4 pr-8 text-[10px] font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500 shadow-inner appearance-none cursor-pointer">
                    <option value="">All Categories</option>
                    @foreach($categories as $category)
                    <option value="{{ $category }}">{{ $category }}</option>
                    @endforeach
                </select>
                <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-slate-300">
                    <i class="fas fa-chevron-down text-[8px]"></i>
                </div>
            </div>

            <!-- Form -->
            <div class="relative group">
                <select x-model="filters.form" @change="handleSearch" class="w-full bg-white/60 backdrop-blur-md border-none rounded-xl py-3 px-4 pr-8 text-[10px] font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500 shadow-inner appearance-none cursor-pointer">
                    <option value="">All Forms</option>
                    @foreach($forms as $form)
                    <option value="{{ $form }}">{{ $form }}</option>
                    @endforeach
                </select>
                <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-slate-300">
                    <i class="fas fa-chevron-down text-[8px]"></i>
                </div>
            </div>

            <!-- RM Type -->
            <div class="relative group">
                <select x-model="filters.rm_type" @change="handleSearch" class="w-full bg-white/60 backdrop-blur-md border-none rounded-xl py-3 px-4 pr-8 text-[10px] font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500 shadow-inner appearance-none cursor-pointer">
                    <option value="">All RM Types</option>
                    @foreach($rmTypes as $rmType)
                    <option value="{{ $rmType }}">{{ $rmType }}</option>
                    @endforeach
                </select>
                <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-slate-300">
                    <i class="fas fa-chevron-down text-[8px]"></i>
                </div>
            </div>

            <!-- Pack Name -->
            <div class="relative group">
                <select x-model="filters.pack_name" @change="handleSearch" class="w-full bg-white/60 backdrop-blur-md border-none rounded-xl py-3 px-4 pr-8 text-[10px] font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500 shadow-inner appearance-none cursor-pointer">
                    <option value="">All Packs</option>
                    @foreach($packs as $pack)
                    <option value="{{ $pack }}">{{ $pack }}</option>
                    @endforeach
                </select>
                <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-slate-300">
                    <i class="fas fa-chevron-down text-[8px]"></i>
                </div>
            </div>
        </div>
        
        <!-- Clear Filters Button -->
        <button @click="clearFilters" class="w-full bg-indigo-50/50 text-indigo-600 font-black tracking-widest uppercase text-[10px] py-3 rounded-xl border border-indigo-100 hover:bg-indigo-100 transition-all active:scale-[0.98]">
            Clear All Filters
        </button>
    </div>

    <!-- Product Grid -->
    <div class="space-y-4">
        @foreach($products as $product)
        <div class="bg-white/70 backdrop-blur-xl border border-white/60 shadow-lg shadow-indigo-100/30 hover:shadow-xl transition-all p-6 rounded-[2.5rem] border border-white/80 flex items-center justify-between group active:scale-[0.98] transition-all">
            <div class="flex-1 min-w-0 pr-4">
                <div class="text-[12px] font-900 text-slate-800 font-900 truncate uppercase tracking-tight italic">{{ $product->name }}</div>
                <div class="flex items-center gap-2 mt-1.5">
                    <span class="text-[8px] font-black text-indigo-400 uppercase tracking-widest">{{ $product->item_code }}</span>
                    <div class="w-1 h-1 bg-slate-200 rounded-full"></div>
                    <span class="text-[8px] text-slate-400 font-bold uppercase">{{ $product->type->type_name }}</span>
                </div>
            </div>
            @if(Auth::user()->hasFeature('mobile_products', 'edit'))
            <button @click="openEdit({{ json_encode($product) }})" class="w-10 h-10 bg-white/60 backdrop-blur-md rounded-xl flex items-center justify-center text-slate-400 border border-white/60 hover:text-indigo-500 hover:border-indigo-100 transition-all">
                <i class="fas fa-pen-to-square text-[10px]"></i>
            </button>
            @endif
        </div>
        @endforeach

        <!-- Pagination -->
        <div class="px-2">
            {{ $products->links('vendor.pagination.mobile-custom') }}
        </div>

        @if(count($products) === 0)
        <div class="py-20 text-center opacity-40">
            <div class="w-20 h-20 bg-white/60 backdrop-blur-md rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fas fa-box-open text-slate-200 text-4xl"></i>
            </div>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest leading-loose">No master records matches<br>your search criteria.</p>
        </div>
        @endif
    </div>

    <!-- Product Modal (Create/Edit) -->
    <div x-show="showModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-6 bg-slate-900/40 backdrop-blur-sm">
        <div class="bg-white rounded-[3rem] w-full max-w-lg overflow-hidden shadow-2xl animate-in zoom-in duration-300 border border-white">
            <div class="grad-indigo p-8 text-white relative">
                <h3 class="text-2xl font-900 italic tracking-tighter uppercase" x-text="isEdit ? 'Edit Product' : 'New Product'"></h3>
                <p class="text-white/70 text-[10px] font-bold uppercase tracking-widest mt-1" x-text="form.item_code || 'Enter product details'"></p>
                <button @click="showModal = false" class="absolute top-8 right-8 w-10 h-10 bg-white/10 rounded-full flex items-center justify-center text-white border border-white/20 active:scale-90 transition-all">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="p-8 space-y-5 overflow-y-auto max-h-[70vh]">
                <div class="space-y-2">
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-2">Product Name</label>
                    <input type="text" x-model="form.name" class="w-full bg-white/60 backdrop-blur-md border-none rounded-2xl py-4 px-6 text-xs font-black text-slate-700 focus:ring-2 focus:ring-indigo-500">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2" x-show="!isEdit">
                        <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-2">Item Code</label>
                        <input type="text" x-model="form.item_code" class="w-full bg-white/60 backdrop-blur-md border-none rounded-2xl py-4 px-6 text-xs font-black text-slate-700 focus:ring-2 focus:ring-indigo-500" placeholder="SKU-XXX">
                    </div>
                    <div class="space-y-2">
                        <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-2">Product Type</label>
                        <select x-model="form.product_type_id" class="w-full bg-white/60 backdrop-blur-md border-none rounded-2xl py-4 px-6 text-xs font-black text-slate-700 focus:ring-2 focus:ring-indigo-500">
                            <option value="">Select Type</option>
                            @foreach($types as $type)
                            <option value="{{ $type->id }}">{{ $type->type_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="space-y-2" :class="isEdit ? 'col-span-2' : ''">
                        <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-2">Low Alert (Boxes)</label>
                        <input type="number" x-model="form.low_alert_quantity" class="w-full bg-white/60 backdrop-blur-md border-none rounded-2xl py-4 px-6 text-xs font-black text-slate-700 focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4" x-show="!isEdit">
                    <div class="space-y-2">
                        <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-2">Unit/Box</label>
                        <input type="number" x-model="form.unit_box" class="w-full bg-white/60 backdrop-blur-md border-none rounded-2xl py-4 px-6 text-xs font-black text-slate-700">
                    </div>
                    <div class="space-y-2">
                        <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-2">Weight/Unit (KG)</label>
                        <input type="number" x-model="form.weight_unit" step="0.001" class="w-full bg-white/60 backdrop-blur-md border-none rounded-2xl py-4 px-6 text-xs font-black text-slate-700">
                    </div>
                </div>

                <button @click="submitForm" :disabled="loading" class="w-full grad-indigo p-1 rounded-[2rem] shadow-xl shadow-indigo-100 transition-all active:scale-[0.98] disabled:opacity-50 mt-4">
                    <div class="bg-white/10 p-5 rounded-[1.9rem] flex items-center justify-center gap-4 text-white font-900 italic tracking-tighter uppercase text-sm border border-white/20">
                        <span x-text="isEdit ? 'Update Master Info' : 'Create Product'"></span>
                        <div x-show="loading" class="animate-spin w-4 h-4 border-2 border-white/50 border-t-white rounded-full"></div>
                    </div>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    function productMasterApp() {
        return {
            showSearch: {{ request()->anyFilled(['search', 'type_id', 'group_id', 'category', 'form', 'rm_type', 'pack_name']) ? 'true' : 'false' }},
            searchTerm: '{{ request('search') }}',
            filters: {
                type_id: '{{ request('type_id') }}',
                group_id: '{{ request('group_id') }}',
                category: '{{ request('category') }}',
                form: '{{ request('form') }}',
                rm_type: '{{ request('rm_type') }}',
                pack_name: '{{ request('pack_name') }}',
            },
            showModal: false,
            isEdit: false,
            loading: false,
            syncing: false,
            form: {
                id: '',
                name: '',
                item_code: '',
                low_alert_quantity: 0,
                product_type_id: '',
                unit_box: 1,
                weight_unit: 0
            },

            handleSearch() {
                const url = new URL(window.location.href);
                if (this.searchTerm) url.searchParams.set('search', this.searchTerm);
                else url.searchParams.delete('search');
                
                Object.keys(this.filters).forEach(key => {
                    if (this.filters[key]) url.searchParams.set(key, this.filters[key]);
                    else url.searchParams.delete(key);
                });
                
                url.searchParams.delete('page');
                window.location.href = url.toString();
            },

            clearFilters() {
                this.searchTerm = '';
                this.filters = {
                    type_id: '',
                    group_id: '',
                    category: '',
                    form: '',
                    rm_type: '',
                    pack_name: ''
                };
                this.handleSearch();
            },

            openCreate() {
                this.isEdit = false;
                this.form = { id: '', name: '', item_code: '', low_alert_quantity: 0, product_type_id: '', unit_box: 1, weight_unit: 0 };
                this.showModal = true;
            },

            openEdit(product) {
                this.isEdit = true;
                this.form = {
                    id: product.id,
                    name: product.name,
                    item_code: product.item_code,
                    low_alert_quantity: product.low_alert_quantity,
                    product_type_id: product.product_type_id,
                    unit_box: product.unit_box,
                    weight_unit: product.weight_unit
                };
                this.showModal = true;
            },

            async submitForm() {
                this.loading = true;
                const url = this.isEdit ? `/mobile/products/${this.form.id}/update` : '/mobile/products';
                try {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify(this.form)
                    });
                    const res = await response.json();
                    if (res.success) {
                        alert(res.message);
                        window.location.reload();
                    } else {
                        alert(res.message);
                    }
                } catch (e) {
                    alert('Action failed.');
                } finally {
                    this.loading = false;
                }
            },

            triggerSync() {
                if (!confirm('This will fetch all products from Algebra ERP. Proceed?')) return;
                this.syncing = true;
                window.location.href = "{{ route('mobile.products.sync') }}";
            }
        }
    }
</script>
@endsection
