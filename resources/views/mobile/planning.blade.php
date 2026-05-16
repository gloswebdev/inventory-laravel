@extends('layouts.mobile')

@section('content')
<div class="space-y-8 pb-10" x-data="planningApp()">
    <!-- Header Block -->
    <div class="flex items-center justify-between bg-white/50 backdrop-blur-2xl p-6 rounded-[2.5rem] border border-white/70 shadow-xl shadow-indigo-100/20 relative overflow-hidden mb-2">
        <div class="absolute -right-10 -top-10 w-40 h-40 bg-indigo-500/10 rounded-full blur-3xl"></div>
        <div class="relative z-10 flex items-center justify-between w-full">
        <div>
            <h2 class="text-3xl font-900 text-slate-800 font-900 tracking-tighter">Planning Hub</h2>
            <p class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400 mt-1">Material resource planning</p>
        </div>
        <div class="flex items-center gap-3">
            <button @click="showIndentModal = true" class="bg-indigo-50 hover:bg-indigo-100 px-4 py-2.5 rounded-xl border border-indigo-100 flex items-center gap-2 transition-all active:scale-95 text-indigo-700 shadow-md">
                <i class="fas fa-file-invoice text-xs"></i>
                <span class="text-[9px] font-black uppercase tracking-widest">Plan by Indent</span>
            </button>
        <div class="flex items-center gap-2">
            <template x-if="results.length > 0">
                <form action="{{ route('mobile.planning.pdf') }}" method="POST" target="_blank">
                    @csrf
                    <input type="hidden" name="data" :value="JSON.stringify(form.products)">
                    <button type="submit" class="w-11 h-11 bg-indigo-600 rounded-xl flex items-center justify-center text-white shadow-lg active:scale-95">
                        <i class="fas fa-file-pdf text-xs"></i>
                    </button>
                </form>
            </template>
            <button @click="resetForm" class="w-11 h-11 bg-white rounded-xl flex items-center justify-center text-slate-400 border border-white/60 transition-all active:scale-90 shadow-md">
                <i class="fas fa-rotate-right text-xs"></i>
            </button>
        </div>
        </div>
    </div>
</div>

    <!-- Planning Form -->
    <div class="space-y-6">
        @if(Auth::user()->hasFeature('mobile_planning', 'type_filter'))
        <!-- Category Filter -->
        <div class="bg-white/70 backdrop-blur-xl border border-white/60 shadow-lg shadow-indigo-100/30 hover:shadow-xl transition-all p-6 rounded-[2.5rem] border border-white/80">
            <div class="flex items-center gap-3 ml-1 mb-3">
                <i class="fas fa-filter text-indigo-500 text-[10px]"></i>
                <label class="text-[9px] font-black text-slate-800 font-900 uppercase tracking-[0.2em]">Filter Category</label>
            </div>
            <div class="relative">
                <select 
                    x-model="selectedTypeId" 
                    class="w-full bg-white/40 backdrop-blur-sm border-none rounded-2xl py-3.5 px-6 text-[11px] font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500 appearance-none shadow-md"
                >
                    <option value="">All Categories</option>
                    @foreach($types as $type)
                    <option value="{{ $type->id }}">{{ $type->type_name }}</option>
                    @endforeach
                </select>
                <div class="absolute right-6 top-1/2 -translate-y-1/2 pointer-events-none text-slate-300">
                    <i class="fas fa-chevron-down text-[10px]"></i>
                </div>
            </div>
        </div>
        @endif

        <div class="bg-white/70 backdrop-blur-xl border border-white/60 shadow-lg shadow-indigo-100/30 hover:shadow-xl transition-all p-8 rounded-[3rem] space-y-8 border border-white/80">
        <div class="flex items-center gap-3 ml-1">
            <i class="fas fa-bullseye text-emerald-500 text-xs"></i>
            <label class="text-[9px] font-black text-slate-800 font-900 uppercase tracking-[0.2em]">Target Production Load</label>
        </div>
        
        <div class="space-y-5">
            <template x-for="(row, index) in form.products" :key="index">
                <div class="p-6 bg-white/40 backdrop-blur-sm rounded-[2rem] border-2 border-white/60/50 space-y-4 relative group">
                    <div class="space-y-3">
                        <div class="relative">
                            <select 
                                x-model="row.id" 
                                class="w-full bg-white border-none rounded-xl py-3.5 px-5 text-[11px] font-bold text-slate-700 focus:ring-2 focus:ring-emerald-500 appearance-none shadow-md"
                            >
                                <option value="">Select Finished Item...</option>
                                <template x-for="product in filteredProducts" :key="product.id">
                                    <option :value="product.id" x-text="product.name + ' (' + product.pack_name + ')'"></option>
                                </template>
                            </select>
                            <div class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-slate-300">
                                <i class="fas fa-box text-[10px]"></i>
                            </div>
                        </div>
                        
                        <div class="relative">
                            <input 
                                type="number" 
                                x-model="row.demand_qty" 
                                placeholder="Target Box Qty" 
                                class="w-full bg-white border-none rounded-xl py-3.5 px-5 text-[11px] font-black text-slate-800 font-900 focus:ring-2 focus:ring-emerald-500 shadow-md placeholder:text-slate-200"
                            >
                            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[8px] font-black text-emerald-400 uppercase tracking-widest">BOXES</span>
                        </div>
                    </div>
                    
                    <button 
                        x-show="form.products.length > 1" 
                        @click="removeRow(index)" 
                        class="absolute -top-3 -right-3 w-8 h-8 grad-rose rounded-full flex items-center justify-center text-white shadow-lg border-2 border-white transition-transform active:scale-90"
                    >
                        <i class="fas fa-times text-[10px]"></i>
                    </button>
                </div>
            </template>
        </div>

        <button 
            @click="addRow" 
            class="w-full py-4 border-2 border-dashed border-white/70 rounded-2xl text-[9px] font-black text-slate-400 uppercase tracking-[0.15em] hover:border-emerald-200 hover:text-emerald-500 transition-all active:scale-[0.98] flex items-center justify-center gap-3"
        >
            <i class="fas fa-plus-circle text-xs"></i>
            <span>Add Target Product</span>
        </button>

        @if(Auth::user()->hasFeature('mobile_planning', 'branch_select'))
        <!-- Branch Selection -->
        <div class="space-y-3 pt-4 border-t border-white/60">
            <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-3">Inventory Source</label>
            <div class="relative">
                <select 
                    x-model="form.branch_code" 
                    class="w-full bg-white/40 backdrop-blur-sm border-none rounded-2xl py-4 px-6 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-emerald-500 appearance-none"
                >
                    <option value="">Consolidated View (All Branches)</option>
                    @foreach($branches as $branch)
                    <option value="{{ $branch->code }}">{{ $branch->name }} ({{ $branch->code }})</option>
                    @endforeach
                </select>
                <div class="absolute right-6 top-1/2 -translate-y-1/2 pointer-events-none text-slate-300">
                    <i class="fas fa-warehouse text-[10px]"></i>
                </div>
            </div>
        </div>
        @endif

        <button 
            @click="calculate" 
            :disabled="loading"
            class="w-full grad-emerald p-1 rounded-[2.5rem] shadow-xl shadow-emerald-100 transition-all active:scale-[0.98] disabled:opacity-50"
        >
            <div class="bg-white/10 p-5 rounded-[2.4rem] flex items-center justify-center gap-4 text-white font-900 italic tracking-tight uppercase text-sm border border-white/20">
                <template x-if="!loading">
                    <div class="flex items-center gap-4">
                        <i class="fas fa-bolt-lightning text-emerald-200"></i>
                        <span>Generate Requirements</span>
                    </div>
                </template>
                <template x-if="loading">
                    <div class="flex items-center gap-4">
                        <i class="fas fa-dna fa-spin"></i>
                        <span>Exploding Recipes...</span>
                    </div>
                </template>
            </div>
        </button>
    </div>

    <!-- Results Section -->
    <div x-show="results.length > 0" x-cloak class="space-y-8 animate-in fade-in slide-in-from-bottom duration-700" x-transition>
        <div class="flex items-center justify-between px-3">
            <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Material Analytics</h3>
            <button 
                @click="exportToExcel" 
                class="bg-emerald-50/80 text-emerald-600 text-[9px] font-black py-2.5 px-5 rounded-xl flex items-center gap-3 border border-emerald-100/50 shadow-md active:scale-95 transition-all"
            >
                <i class="fas fa-file-export text-xs"></i>
                <span>GET EXCEL</span>
            </button>
        </div>

        <div class="grid grid-cols-1 gap-5">
            <template x-for="rm in results" :key="rm.item_code">
                <div class="bg-white/70 backdrop-blur-xl border border-white/60 shadow-lg shadow-indigo-100/30 hover:shadow-xl transition-all p-6 rounded-[2.5rem] flex items-center justify-between relative overflow-hidden border border-white/80 group active:scale-[0.98] transition-transform">
                    <div class="absolute top-0 left-0 w-2 h-full" :class="rm.shortfall > 0 ? 'bg-rose-500' : 'grad-emerald'"></div>
                    
                    <div class="flex-1 min-w-0 pr-5">
                        <div class="text-[12px] font-900 text-slate-800 font-900 truncate uppercase" x-text="rm.name"></div>
                        <div class="flex items-center gap-3 mt-2">
                            <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest bg-slate-100 px-2 py-0.5 rounded" x-text="rm.item_code"></span>
                            <div class="text-[9px] font-black uppercase tracking-tight" :class="rm.current_stock <= 0 ? 'text-rose-400' : 'text-indigo-500'" x-text="'Stock: ' + parseFloat(rm.current_stock).toFixed(2) + ' ' + rm.uom"></div>
                        </div>
                    </div>
                    
                    <div class="text-right shrink-0">
                        <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest" x-text="'Need: ' + parseFloat(rm.required_qty).toFixed(2)"></div>
                        <div 
                            class="text-sm font-900 tracking-tighter mt-1" 
                            :class="rm.shortfall > 0 ? 'text-rose-600' : 'text-emerald-600'" 
                        >
                            <span x-show="rm.shortfall > 0" x-text="'Short ' + parseFloat(rm.shortfall).toFixed(2)"></span>
                            <span x-show="rm.shortfall <= 0" class="flex items-center gap-1 justify-end"><i class="fas fa-check-circle"></i> VALID</span>
                        </div>
                    </div>
                </div>
            </template>
        </div>
        
        <!-- Calculation Summary Box -->
        <div class="grad-indigo p-1 rounded-[3rem] shadow-2xl shadow-indigo-100">
            <div class="bg-slate-900/90 backdrop-blur-md p-8 rounded-[2.9rem] border border-white/10">
                <div class="flex items-center gap-5 mb-8">
                    <div class="w-14 h-14 bg-indigo-500/20 rounded-3xl flex items-center justify-center text-white border border-white/10 ring-4 ring-white/5 shadow-inner">
                        <i class="fas fa-wand-magic-sparkles text-xl text-indigo-300"></i>
                    </div>
                    <div>
                        <h4 class="text-white text-lg font-900 tracking-tighter italic leading-none">Scenario Ready</h4>
                        <p class="text-[9px] text-indigo-300 font-bold uppercase tracking-[0.2em] mt-2">Yield Projection Summary</p>
                    </div>
                </div>
                
                <div class="space-y-3 bg-white/5 p-6 rounded-[2rem] border border-white/5">
                    <template x-for="item in summary" :key="item.id">
                        <div class="flex items-center justify-between py-1 group">
                            <span class="text-[11px] text-slate-400 font-bold uppercase tracking-tight group-hover:text-white transition-colors" x-text="item.name"></span>
                            <div class="flex-1 border-b border-dashed border-white/10 mx-3 mb-1"></div>
                            <span class="text-[11px] text-indigo-300 font-900" x-text="item.quantity + ' BOX'"></span>
                        </div>
                    </template>
                </div>
                
                <button @click="resetForm" class="w-full mt-8 py-4 bg-white/10 hover:bg-white/20 rounded-2xl text-[9px] font-black text-white uppercase tracking-[0.2em] transition-all border border-white/10 active:scale-95">
                    Clear Workspace & Redesign Plan
                </button>
            </div>
        </div>
    </div>

    <!-- Indent Selection Modal -->
    <div x-show="showIndentModal" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        
        <div class="bg-white w-full max-w-sm rounded-[2.5rem] shadow-2xl overflow-hidden flex flex-col max-h-[85vh] animate-in slide-in-from-bottom duration-300"
             @click.outside="showIndentModal = false">
            
            <div class="bg-indigo-600 p-8 text-white relative">
                <div class="flex items-center gap-4">
                    <div class="bg-white/20 p-3 rounded-2xl">
                        <i class="fas fa-file-invoice-dollar text-2xl"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-900 italic tracking-tighter uppercase leading-tight">Plan by Indent</h2>
                        <p class="text-indigo-100 font-bold text-[9px] uppercase tracking-widest mt-1">Select source indent</p>
                    </div>
                </div>
                <button @click="showIndentModal = false" class="absolute top-8 right-8 text-white/50 hover:text-white transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-4 space-y-3 bg-white/60 backdrop-blur-md">
                @foreach($indents as $indent)
                <div class="bg-white p-5 rounded-[2rem] border border-white/60 shadow-md space-y-4">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="text-[10px] font-black text-indigo-600 uppercase tracking-widest">#{{ $indent->id }} | {{ $indent->branch_code }}</div>
                            <div class="text-xs font-900 text-slate-800 font-900 mt-1">{{ $indent->branch_name }}</div>
                            <div class="text-[9px] text-slate-400 font-bold mt-0.5">{{ \Carbon\Carbon::parse($indent->indent_date)->format('d M, Y') }}</div>
                        </div>
                        <span class="px-2 py-0.5 bg-slate-100 text-slate-500 rounded text-[8px] font-black uppercase tracking-tighter">{{ $indent->status }}</span>
                    </div>
                    
                    <div class="flex gap-2 pt-2">
                        <button 
                            @click="planFromIndent({{ $indent->id }}, 'full')" 
                            class="flex-1 py-2.5 bg-indigo-50 text-indigo-600 rounded-xl text-[9px] font-black uppercase tracking-widest hover:bg-indigo-600 hover:text-white transition-all shadow-md border border-indigo-100"
                        >
                            PLAN FULL
                        </button>
                        <button 
                            @click="planFromIndent({{ $indent->id }}, 'shortfall')" 
                            class="flex-1 py-2.5 bg-emerald-50 text-emerald-600 rounded-xl text-[9px] font-black uppercase tracking-widest hover:bg-emerald-600 hover:text-white transition-all shadow-md border border-emerald-100"
                        >
                            SHORTFALL
                        </button>
                    </div>
                </div>
                @endforeach
                
                @if(count($indents) === 0)
                <div class="p-10 text-center space-y-4">
                    <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto">
                        <i class="fas fa-folder-open text-slate-300 text-2xl"></i>
                    </div>
                    <p class="text-[10px] text-slate-400 font-black uppercase tracking-widest">No recent indents found</p>
                </div>
                @endif
            </div>

            <div class="p-4 border-t bg-white">
                <button @click="showIndentModal = false" class="w-full py-4 bg-white/60 backdrop-blur-md text-slate-500 rounded-2xl font-black italic tracking-tighter uppercase text-xs">
                    Cancel Selection
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    function planningApp() {
        return {
            loading: false,
            showIndentModal: false,
            form: {
                branch_code: '',
                products: [
                    { id: '', demand_qty: '' }
                ]
            },
            results: [],
            summary: [],
            selectedTypeId: '',
            allProducts: @json($products),
            get filteredProducts() {
                if (!this.selectedTypeId) return this.allProducts;
                return this.allProducts.filter(p => p.product_type_id == this.selectedTypeId);
            },
            addRow(id = '', demand_qty = '') {
                this.form.products.push({ id: id, demand_qty: demand_qty });
            },
            removeRow(index) {
                this.form.products.splice(index, 1);
                if(this.form.products.length === 0) this.addRow();
            },
            resetForm() {
                this.form.products = [{ id: '', demand_qty: '' }];
                this.results = [];
                this.summary = [];
                this.showIndentModal = false;
            },
            async planFromIndent(indentId, mode) {
                this.loading = true;
                this.showIndentModal = false;
                
                try {
                    const response = await fetch(`{{ route('mobile.indent.show', ['indent' => '__ID__']) }}`.replace('__ID__', indentId));
                    if (!response.ok) throw new Error('Network response was not ok');
                    const data = await response.json();
                    
                    if (data.success) {
                        const indent = data.indent;
                        this.form.branch_code = indent.branch_code;
                        this.form.products = [];
                        
                        let itemsAdded = 0;
                        indent.items.forEach(item => {
                            const asked = parseFloat(item.final_qty_box || 0);
                            const completed = parseFloat(item.completed_qty || 0);
                            let qtyToPlan = 0;

                            if (mode === 'full') {
                                qtyToPlan = asked;
                            } else {
                                qtyToPlan = asked - completed;
                            }

                            if (qtyToPlan > 0) {
                                this.form.products.push({ 
                                    id: item.product_id, 
                                    demand_qty: qtyToPlan.toFixed(2) 
                                });
                                itemsAdded++;
                            }
                        });

                        if (itemsAdded === 0) {
                            alert('No items to plan in this indent.');
                            this.addRow();
                        }
                    } else {
                        alert(data.message);
                    }
                } catch (e) {
                    console.error(e);
                    alert('Synchronisation failure.');
                } finally {
                    this.loading = false;
                }
            },
            async calculate() {
                if (this.form.products.some(p => !p.id || !p.demand_qty)) {
                    alert('Kindly populate all target fields.');
                    return;
                }
                
                this.loading = true;
                try {
                    const response = await fetch("{{ route('mobile.planning.calculate') }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify(this.form)
                    });

                    const res = await response.json();
                    if (res.success) {
                        this.results = res.data;
                        this.summary = res.summary;
                    } else {
                        alert(res.message);
                    }
                } catch (e) {
                    alert('Synchronisation failure. Please retry.');
                } finally {
                    this.loading = false;
                }
            },
            exportToExcel() {
                if (this.form.products.length === 0) return;

                const form = document.createElement('form');
                form.method = 'POST';
                form.action = "{{ route('planning.export') }}";
                form.style.display = 'none';

                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = '_token';
                csrfInput.value = "{{ csrf_token() }}";
                form.appendChild(csrfInput);

                const dataInput = document.createElement('input');
                dataInput.type = 'hidden';
                dataInput.name = 'products_json';
                dataInput.value = JSON.stringify(this.form.products);
                form.appendChild(dataInput);

                const branchInput = document.createElement('input');
                branchInput.type = 'hidden';
                branchInput.name = 'branch_code';
                branchInput.value = this.form.branch_code;
                form.appendChild(branchInput);

                document.body.appendChild(form);
                form.submit();
                document.body.removeChild(form);
            }
        }
    }
</script>
@endsection
