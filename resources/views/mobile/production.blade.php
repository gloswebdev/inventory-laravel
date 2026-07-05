@extends('layouts.mobile')

@section('content')
<div class="space-y-8 pb-16" x-data="productionApp()">
    
    <!-- Dynamic Step Container -->
    <div x-show="step === 1" class="space-y-6">
        <!-- Header Block -->
        <div class="flex items-center justify-between bg-white/50 backdrop-blur-2xl p-6 rounded-[2rem] border border-white/70 shadow-xl shadow-indigo-100/10 relative overflow-hidden">
            <div class="absolute -right-10 -top-10 w-40 h-40 bg-indigo-500/10 rounded-full blur-3xl"></div>
            <div class="relative z-10 flex items-center justify-between w-full">
                <div>
                    <h2 class="text-2xl font-black text-slate-800 tracking-tighter">Production Manager</h2>
                    <p class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400 mt-1">Record batch yields & raw materials</p>
                </div>
                <div class="flex items-center gap-2">
                    <div class="px-2.5 py-0.5 bg-indigo-50 border border-indigo-100 rounded-full text-[8px] font-black uppercase tracking-widest text-indigo-500 shadow-sm">Form</div>
                    <div class="w-2 h-2 bg-indigo-500 rounded-full animate-pulse"></div>
                </div>
            </div>
        </div>

        @if(Auth::user()->hasFeature('mobile_production', 'management'))
        <!-- Batch Metadata Panel -->
        <div class="bg-white/70 backdrop-blur-xl border border-white/60 shadow-lg shadow-indigo-100/30 p-6 rounded-[2.5rem] space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-2">
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-2 block">Production Site</label>
                    <div class="relative">
                        <select x-model="branchCode" class="w-full bg-white/50 border-2 border-white rounded-2xl py-3 px-4 text-xs font-black text-slate-800 focus:ring-2 focus:ring-indigo-500 appearance-none transition-all cursor-not-allowed" disabled>
                            @foreach($branches as $branch)
                            <option value="{{ $branch->code }}">{{ $branch->name }} ({{ $branch->code }})</option>
                            @endforeach
                        </select>
                        <div class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400">
                            <i class="fas fa-lock text-[10px]"></i>
                        </div>
                    </div>
                </div>
                <div class="space-y-2">
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-2 block">Production Date</label>
                    <input type="date" x-model="productionDate" class="w-full bg-white/50 border-2 border-white rounded-2xl py-3 px-4 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-500 transition-all">
                </div>
            </div>
            <p class="text-[8px] font-bold text-amber-600 mt-1 uppercase tracking-tight flex items-center gap-1">
                <i class="fas fa-circle-info text-[9px]"></i> * All production is logged in Factory branch.
            </p>
        </div>

        <!-- Products List Section -->
        <div class="space-y-4">
            <div class="flex items-center justify-between px-3">
                <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Yielding Products</h3>
                <span class="text-[8px] font-black text-slate-400 bg-slate-200/50 px-2 py-0.5 rounded-full" x-text="items.length + ' Items'"></span>
            </div>

            <!-- Card items loop -->
            <template x-for="(item, index) in items" :key="index">
                <div class="bg-white/70 backdrop-blur-xl border border-white/60 shadow-lg shadow-indigo-100/30 p-6 rounded-[2.5rem] space-y-4 relative overflow-hidden transition-all duration-300">
                    
                    <!-- Card Header -->
                    <div class="flex items-center justify-between border-b border-slate-100/60 pb-3">
                        <span class="text-[10px] font-black text-indigo-500 uppercase tracking-widest italic" x-text="'Product #' + (index + 1)"></span>
                        <button @click="removeItem(index)" class="w-8 h-8 rounded-full flex items-center justify-center bg-rose-50 hover:bg-rose-100 text-rose-500 transition-colors" title="Remove product">
                            <i class="fas fa-trash-can text-xs"></i>
                        </button>
                    </div>

                    <!-- Product Choice Selector -->
                    <div class="space-y-2">
                        <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-2 block">Select Product</label>
                        <button @click="openProductSearch(index)" class="w-full bg-white/50 border-2 border-dashed border-slate-200 rounded-2xl py-3.5 px-5 text-left text-xs font-black transition-all hover:bg-white flex items-center justify-between" :class="item.product_id ? 'text-indigo-600 border-indigo-200' : 'text-slate-400'">
                            <span class="truncate" x-text="item.product_name ? item.product_name + ' (' + item.pack_size + ')' : 'Tap to search product...'"></span>
                            <i class="fas fa-search text-[10px]"></i>
                        </button>
                    </div>

                    <!-- Quantity & Batch details Grid -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-2 block">Quantity (Boxes)</label>
                            <input type="number" step="0.001" x-model="item.quantity" @input="fetchRequirements(index)" placeholder="0.00" class="w-full bg-white/50 border-2 border-white rounded-2xl py-3 px-4 text-sm font-black text-slate-800 focus:ring-2 focus:ring-indigo-500 placeholder:text-slate-300 transition-all">
                        </div>
                        <div class="space-y-2">
                            <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-2 block">Batch Number</label>
                            <input type="text" x-model="item.batch_number" @input="item.batch_number = $event.target.value.toUpperCase()" placeholder="BATCH#" class="w-full bg-white/50 border-2 border-white rounded-2xl py-3 px-4 text-xs font-black text-slate-800 uppercase focus:ring-2 focus:ring-indigo-500 placeholder:text-slate-300 transition-all">
                        </div>
                    </div>

                    <!-- MFG / EXP Date inputs -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-2 block">MFG Date</label>
                            <input type="date" x-model="item.mfg_date" class="w-full bg-white/50 border-2 border-white rounded-2xl py-3 px-4 text-[10px] font-bold text-slate-800 focus:ring-2 focus:ring-indigo-500 transition-all">
                        </div>
                        <div class="space-y-2">
                            <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-2 block">EXP Date</label>
                            <input type="date" x-model="item.exp_date" class="w-full bg-white/50 border-2 border-white rounded-2xl py-3 px-4 text-[10px] font-bold text-slate-800 focus:ring-2 focus:ring-indigo-500 transition-all">
                        </div>
                    </div>

                    <!-- Recipe checks summary -->
                    <div x-show="item.product_id && item.quantity > 0" class="mt-2 pt-2 border-t border-slate-100/50">
                        <!-- Loading Indicator -->
                        <div x-show="item.loadingRequirements" class="flex items-center gap-2 py-2 px-3 bg-slate-50 rounded-xl text-[9px] font-black uppercase text-slate-400">
                            <i class="fas fa-circle-notch fa-spin text-indigo-500"></i>
                            <span>Fetching Recipe Requirements...</span>
                        </div>

                        <!-- Error Message -->
                        <div x-show="item.requirementsError" class="py-2 px-3 bg-rose-50 rounded-xl text-[9px] font-black uppercase text-rose-500 flex items-center gap-2">
                            <i class="fas fa-circle-exclamation"></i>
                            <span x-text="item.requirementsError"></span>
                        </div>

                        <!-- Requirements Available -->
                        <div x-show="item.requirements.length > 0 && !item.loadingRequirements" class="space-y-2">
                            <!-- Toggle Button / Header -->
                            <button @click="item.showRecipeCollapse = !item.showRecipeCollapse" class="w-full py-2 px-3 rounded-xl flex items-center justify-between text-[9px] font-black uppercase tracking-wider transition-all" :class="item.isPossible ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'">
                                <span class="flex items-center gap-2">
                                    <i class="fas" :class="item.isPossible ? 'fa-circle-check text-emerald-500' : 'fa-circle-xmark text-rose-500'"></i>
                                    <span x-text="item.isPossible ? 'Deduction Stock Available' : 'Insufficient Recipe Stock'"></span>
                                </span>
                                <i class="fas text-[8px] transition-transform duration-200" :class="item.showRecipeCollapse ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                            </button>

                            <!-- Collapsible Content -->
                            <div x-show="item.showRecipeCollapse" x-cloak class="p-3 bg-slate-50/50 border border-slate-100 rounded-2xl space-y-2 mt-1">
                                <template x-for="req in item.requirements" :key="req.item_code">
                                    <div class="flex items-center justify-between text-[10px] py-1.5 border-b border-slate-100 last:border-0">
                                        <div class="min-w-0 flex-1 pr-2">
                                            <div class="font-bold text-slate-700 truncate" x-text="req.name"></div>
                                            <div class="text-[8px] font-black text-slate-400 uppercase tracking-wider" x-text="req.item_code"></div>
                                        </div>
                                        <div class="text-right flex gap-3">
                                            <div>
                                                <div class="font-black text-slate-900" x-text="parseFloat(req.required_qty).toFixed(2)"></div>
                                                <div class="text-[7px] text-slate-400 font-bold uppercase">Required</div>
                                            </div>
                                            <div>
                                                <div class="font-black" :class="req.live_stock < req.required_qty ? 'text-rose-500' : 'text-emerald-600'" x-text="parseFloat(req.live_stock).toFixed(2)"></div>
                                                <div class="text-[7px] text-slate-400 font-bold uppercase">Stock</div>
                                            </div>
                                            <div x-show="req.shortfall > 0">
                                                <div class="font-black text-rose-600" x-text="'-' + parseFloat(req.shortfall).toFixed(2)"></div>
                                                <div class="text-[7px] text-rose-400 font-bold uppercase">Lack</div>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Add Product Button -->
            <button @click="addItem()" class="w-full py-4 border-2 border-dashed border-indigo-200 rounded-[2rem] text-indigo-500 bg-white/40 hover:bg-white hover:border-indigo-400 transition flex items-center justify-center gap-2 text-xs font-black uppercase tracking-widest active:scale-[0.98]">
                <i class="fas fa-plus-circle text-sm"></i>
                <span>Add Product to Batch</span>
            </button>
        </div>

        <!-- Floating Submit Bar / Footer -->
        <div class="fixed bottom-24 left-6 right-6 z-30 bg-white/80 backdrop-blur-md p-4 rounded-[2rem] border border-white/80 shadow-2xl flex items-center justify-between gap-4">
            <div class="pl-2">
                <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest block">Total Yield</span>
                <span class="text-lg font-black text-indigo-600 tracking-tighter" x-text="totalQuantity.toFixed(2) + ' Boxes'"></span>
            </div>
            <button @click="goToReview()" class="grad-indigo text-white px-6 py-3.5 rounded-2xl font-black text-xs uppercase tracking-widest shadow-lg shadow-indigo-200 active:scale-95 transition-all flex items-center gap-2">
                <span>Preview Slip</span>
                <i class="fas fa-chevron-right text-[10px]"></i>
            </button>
        </div>
        @else
        <div class="bg-white/70 backdrop-blur-xl border border-white/60 shadow-lg shadow-indigo-100/30 p-10 rounded-[3rem] text-center">
            <div class="w-16 h-16 bg-slate-50 border border-slate-100 rounded-full flex items-center justify-center mx-auto mb-4 text-slate-300">
                <i class="fas fa-lock text-2xl"></i>
            </div>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Entry Submission Restricted</p>
        </div>
        @endif
    </div>

    <!-- Step 2: Invoice / Yield Slip Review -->
    <div x-show="step === 2" x-cloak class="space-y-6">
        <!-- Review header -->
        <div class="flex items-center gap-4 bg-white/50 backdrop-blur-2xl p-6 rounded-[2rem] border border-white/70 shadow-xl shadow-indigo-100/10">
            <button @click="step = 1" class="w-10 h-10 rounded-xl bg-white flex items-center justify-center text-slate-500 shadow-sm border border-slate-100 active:scale-90 transition-transform">
                <i class="fas fa-chevron-left"></i>
            </button>
            <div>
                <h2 class="text-xl font-black text-slate-800 tracking-tighter">Review Production Slip</h2>
                <p class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400 mt-0.5">Check batch parameters before save</p>
            </div>
        </div>

        <!-- Slip Details Receipt -->
        <div class="bg-white p-6 rounded-[2.5rem] border border-slate-100 shadow-xl space-y-6 relative overflow-hidden">
            <!-- Decorative dots for receipt cut -->
            <div class="absolute -top-1 left-0 w-full flex justify-around opacity-20">
                <template x-for="i in 20">
                    <span class="w-3 h-3 bg-indigo-500 rounded-full"></span>
                </template>
            </div>
            
            <div class="flex justify-between items-start pt-3">
                <div>
                    <h3 class="text-lg font-black text-indigo-600 tracking-tighter">PRODUCTION SLIP</h3>
                    <p class="text-[8px] font-black text-slate-400 uppercase tracking-widest mt-0.5" x-text="'USER_ID: ' + {{ auth()->id() }}"></p>
                </div>
                <div class="text-right">
                    <div class="text-xs font-black text-slate-800 uppercase">Factory (2)</div>
                    <div class="text-[8px] font-black text-slate-400 uppercase tracking-widest mt-0.5" x-text="productionDate"></div>
                </div>
            </div>

            <!-- Items Table -->
            <div class="border-t border-b border-dashed border-slate-200 py-4 space-y-4">
                <template x-for="(item, index) in items" :key="index">
                    <div class="flex justify-between items-start text-xs">
                        <div class="min-w-0 pr-4">
                            <div class="font-black text-slate-800 truncate" x-text="item.product_name"></div>
                            <div class="text-[8px] font-bold text-indigo-500 uppercase mt-0.5" x-text="'Lot: ' + item.batch_number"></div>
                            <div class="text-[8px] text-slate-400 font-bold" x-text="'MFG: ' + item.mfg_date + ' | EXP: ' + item.exp_date"></div>
                        </div>
                        <div class="text-right shrink-0">
                            <span class="text-sm font-black text-slate-900" x-text="parseFloat(item.quantity).toFixed(2)"></span>
                            <span class="text-[8px] font-black text-slate-400 uppercase block">Boxes</span>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Totals -->
            <div class="flex justify-between items-center">
                <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Total Produced Volume</span>
                <div class="text-right">
                    <span class="text-2xl font-black text-indigo-600 tracking-tighter" x-text="totalQuantity.toFixed(2)"></span>
                    <span class="text-[8px] font-black text-indigo-300 uppercase block">Total Boxes</span>
                </div>
            </div>

            <!-- Notice card -->
            <div class="p-4 bg-amber-50 rounded-2xl border border-amber-100 flex gap-3">
                <div class="w-8 h-8 rounded-lg bg-amber-500 text-white flex items-center justify-center shrink-0">
                    <i class="fas fa-circle-exclamation text-xs"></i>
                </div>
                <div>
                    <h4 class="text-[10px] font-black text-amber-800 uppercase tracking-tight">Post Notice</h4>
                    <p class="text-[9px] text-amber-600 leading-tight mt-0.5 font-bold">This yield entry will deduct recipe raw materials automatically from the live Factory Inventory.</p>
                </div>
            </div>

            <!-- Submission Action -->
            <button @click="submit" :disabled="loading" class="w-full grad-rose p-1 rounded-[1.8rem] shadow-xl shadow-rose-100 transition-all active:scale-[0.98] group disabled:opacity-50 disabled:scale-100">
                <div class="bg-white/10 p-4 rounded-[1.7rem] flex items-center justify-center gap-3 text-white font-black uppercase text-xs border border-white/20">
                    <template x-if="!loading">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-cloud-arrow-up text-sm"></i>
                            <span>Save Yield Entry</span>
                        </div>
                    </template>
                    <template x-if="loading">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-circle-notch fa-spin text-sm"></i>
                            <span>Processing...</span>
                        </div>
                    </template>
                </div>
            </button>
        </div>
    </div>

    @if(Auth::user()->hasFeature('mobile_production', 'history'))
    <!-- Production logs History List -->
    <div x-show="step === 1" class="space-y-4">
        <div class="flex items-center justify-between px-3">
            <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Yield History Log</h3>
            <span class="text-[8px] font-black text-slate-300 uppercase tracking-widest">Last 20 batches</span>
        </div>

        <div class="space-y-3">
            @forelse($history as $item)
            <div @click="viewDetail({{ $item->id }})" class="bg-white/70 backdrop-blur-xl border border-white/60 shadow-lg shadow-indigo-100/20 p-5 rounded-[2rem] hover:shadow-xl transition-all flex items-center justify-between active:scale-[0.99] cursor-pointer">
                <div class="flex items-center gap-3.5">
                    <div class="w-10 h-10 bg-indigo-50 border border-indigo-100/50 rounded-2xl flex items-center justify-center text-indigo-500">
                        <i class="fas fa-boxes-stacked text-xs"></i>
                    </div>
                    <div>
                        <div class="text-[11px] font-black text-slate-800 uppercase tracking-tight">Batch #{{ str_pad($item->id, 5, '0', STR_PAD_LEFT) }}</div>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="px-1.5 py-0.2 bg-slate-100 text-slate-500 text-[7px] font-black uppercase rounded">{{ $item->branch_name }}</span>
                            <div class="w-1 h-1 bg-slate-300 rounded-full"></div>
                            <span class="text-[8px] text-slate-400 font-bold">{{ date('d M, Y', strtotime($item->production_date)) }}</span>
                        </div>
                    </div>
                </div>
                <div class="text-right flex items-center gap-3">
                    <div class="pr-1">
                        <div class="text-xs font-black text-slate-800 tracking-tighter">{{ number_format($item->items->sum('quantity_box'), 1) }} Box</div>
                        <div class="text-[7px] font-black text-slate-400 uppercase tracking-wider" x-text="'{{ $item->items->count() }} Products'"></div>
                    </div>
                    <div>
                        @if(($item->erp_push_status ?? 'pending') === 'success')
                            <span class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-[9px]"><i class="fas fa-check"></i></span>
                        @elseif(($item->erp_push_status ?? 'pending') === 'failed')
                            <span class="w-5 h-5 rounded-full bg-rose-100 text-rose-500 flex items-center justify-center text-[9px]"><i class="fas fa-xmark"></i></span>
                        @elseif(($item->erp_push_status ?? 'pending') === 'skipped')
                            <span class="w-5 h-5 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center text-[9px]"><i class="fas fa-minus"></i></span>
                        @else
                            <span class="w-5 h-5 rounded-full bg-amber-100 text-amber-500 flex items-center justify-center text-[9px]"><i class="fas fa-clock"></i></span>
                        @endif
                    </div>
                </div>
            </div>
            @empty
            <div class="p-8 text-center bg-white/40 border border-white rounded-[2rem] text-slate-400 italic text-[10px] font-bold uppercase tracking-widest">
                No recent production logs found.
            </div>
            @endforelse
        </div>
    </div>
    @endif

    <!-- Product Search Bottom Drawer -->
    <div x-show="showSearchDrawer" x-cloak class="fixed inset-0 z-50 flex flex-col justify-end bg-black/60 backdrop-blur-sm transition-all"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        
        <!-- Drawer content -->
        <div class="bg-white rounded-t-[3rem] shadow-2xl max-h-[85vh] flex flex-col overflow-hidden w-full"
             @click.outside="showSearchDrawer = false"
             x-transition:enter="transition ease-out duration-300 transform"
             x-transition:enter-start="translate-y-full"
             x-transition:enter-end="translate-y-0"
             x-transition:leave="transition ease-in duration-200 transform"
             x-transition:leave-start="translate-y-0"
             x-transition:leave-end="translate-y-full">
            
            <!-- Drawer Header -->
            <div class="bg-indigo-600 p-6 text-white relative flex-shrink-0">
                <h3 class="text-lg font-black tracking-tighter uppercase italic">Search Finished Product</h3>
                <p class="text-indigo-100 text-[8px] font-bold uppercase tracking-widest mt-0.5">Filter by query or type</p>
                <button @click="showSearchDrawer = false" class="absolute top-6 right-6 w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 flex items-center justify-center transition-colors">
                    <i class="fas fa-times text-sm"></i>
                </button>
            </div>

            <!-- Drawer Search Bar & Filters -->
            <div class="p-5 border-b border-slate-100 bg-slate-50/50 space-y-3 flex-shrink-0">
                <div class="relative">
                    <input type="text" x-model="searchQuery" placeholder="Search product name, code or alias..." class="w-full bg-white border border-slate-200 rounded-2xl py-3 pl-11 pr-5 text-xs font-medium text-slate-800 placeholder:text-slate-400 focus:ring-2 focus:ring-indigo-500 transition-all">
                    <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                </div>

                <!-- Type Quick Filter pills (Horizontal Scroll) -->
                <div class="flex gap-2 overflow-x-auto no-scrollbar py-1">
                    <button @click="typeFilter = ''" class="px-4 py-1.5 rounded-full text-[9px] font-black uppercase tracking-wider shrink-0 transition-all" :class="typeFilter === '' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-100' : 'bg-white border border-slate-200 text-slate-500 hover:bg-slate-50'">
                        All Types
                    </button>
                    <template x-for="t in productTypes" :key="t.id">
                        <button @click="typeFilter = t.id" class="px-4 py-1.5 rounded-full text-[9px] font-black uppercase tracking-wider shrink-0 transition-all" :class="typeFilter == t.id ? 'bg-indigo-600 text-white shadow-md shadow-indigo-100' : 'bg-white border border-slate-200 text-slate-500 hover:bg-slate-50'" x-text="t.type_name">
                        </button>
                    </template>
                </div>
            </div>

            <!-- Drawer Products List -->
            <div class="flex-1 overflow-y-auto p-5 space-y-2">
                <template x-for="p in filteredProductsForSearch" :key="p.id">
                    <div @click="selectProduct(p)" class="p-4 bg-white border border-slate-100 rounded-2xl hover:border-indigo-100 active:bg-indigo-50/20 transition-all flex items-center justify-between cursor-pointer">
                        <div class="min-w-0 pr-4">
                            <div class="text-[11px] font-black text-slate-800 uppercase tracking-tight truncate" x-text="p.name"></div>
                            <div class="flex items-center gap-1.5 mt-0.5">
                                <span class="text-[8px] font-black text-indigo-500 uppercase tracking-wider" x-text="p.item_code"></span>
                                <div class="w-1 h-1 bg-slate-200 rounded-full"></div>
                                <span class="text-[8px] font-bold text-slate-400 uppercase tracking-widest" x-text="p.pack_name || 'N/A'"></span>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right text-[9px] text-slate-300"></i>
                    </div>
                </template>
                <div x-show="filteredProductsForSearch.length === 0" class="text-center py-10 text-slate-400 italic text-[10px] font-bold uppercase tracking-widest">
                    No products matching search query.
                </div>
            </div>
        </div>
    </div>

    <!-- Production Details Sheet (Slide-Up) -->
    <div x-show="showDetailsDrawer" x-cloak class="fixed inset-0 z-50 flex flex-col justify-end bg-black/60 backdrop-blur-sm transition-all"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        
        <!-- Drawer content -->
        <div class="bg-white rounded-t-[3rem] shadow-2xl max-h-[85vh] flex flex-col overflow-hidden w-full"
             @click.outside="showDetailsDrawer = false"
             x-transition:enter="transition ease-out duration-300 transform"
             x-transition:enter-start="translate-y-full"
             x-transition:enter-end="translate-y-0"
             x-transition:leave="transition ease-in duration-200 transform"
             x-transition:leave-start="translate-y-0"
             x-transition:leave-end="translate-y-full">
            
            <!-- Drawer Header -->
            <div class="bg-indigo-600 p-6 text-white relative flex-shrink-0">
                <div class="flex items-center gap-3">
                    <div class="bg-white/20 p-2 rounded-xl text-white">
                        <i class="fas fa-industry text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-black tracking-tighter uppercase italic leading-none" x-text="selectedProduction ? 'Batch #' + String(selectedProduction.id).padStart(5, '0') : ''"></h3>
                        <p class="text-indigo-100 text-[8px] font-bold uppercase tracking-widest mt-1">Batch Yield details</p>
                    </div>
                </div>
                <button @click="showDetailsDrawer = false" class="absolute top-6 right-6 w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 flex items-center justify-center transition-colors">
                    <i class="fas fa-times text-sm"></i>
                </button>
            </div>

            <!-- Drawer Body -->
            <div class="flex-1 overflow-y-auto p-6 space-y-6" x-show="selectedProduction">
                
                <!-- Metadata items -->
                <div class="grid grid-cols-2 gap-4 bg-slate-50 p-4 rounded-3xl border border-slate-100">
                    <div>
                        <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest block">Entry Date</span>
                        <span class="text-xs font-black text-slate-700 uppercase" x-text="selectedProduction ? selectedProduction.production_date : ''"></span>
                    </div>
                    <div>
                        <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest block">Branch</span>
                        <span class="text-xs font-black text-slate-700 uppercase" x-text="selectedProduction ? selectedProduction.branch_name : ''"></span>
                    </div>
                    <div class="mt-2">
                        <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest block">ERP Push Status</span>
                        <div class="mt-0.5">
                            <span class="px-2 py-0.5 rounded text-[8px] font-black uppercase tracking-wider" :class="{
                                'bg-emerald-100 text-emerald-700': selectedProduction && selectedProduction.erp_push_status === 'success',
                                'bg-rose-100 text-rose-600': selectedProduction && selectedProduction.erp_push_status === 'failed',
                                'bg-slate-100 text-slate-400': selectedProduction && selectedProduction.erp_push_status === 'skipped',
                                'bg-amber-100 text-amber-600': selectedProduction && (selectedProduction.erp_push_status === 'pending' || !selectedProduction.erp_push_status)
                            }" x-text="selectedProduction ? selectedProduction.erp_push_status || 'pending' : 'pending'"></span>
                        </div>
                    </div>
                    <div class="mt-2">
                        <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest block">Recorded By</span>
                        <span class="text-xs font-black text-slate-700 uppercase" x-text="selectedProduction && selectedProduction.user ? selectedProduction.user.name : 'System'"></span>
                    </div>
                </div>

                <!-- Products Yield List -->
                <div class="space-y-3">
                    <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest px-1">Produced Items</div>
                    <div class="space-y-2.5">
                        <template x-for="item in (selectedProduction ? selectedProduction.items : [])" :key="item.id">
                            <div class="p-4 bg-white border border-slate-100 rounded-2xl flex justify-between items-start">
                                <div class="min-w-0 pr-4">
                                    <div class="text-[11px] font-black text-slate-800 uppercase tracking-tight truncate" x-text="item.product_name"></div>
                                    <div class="flex items-center gap-1.5 mt-1">
                                        <span class="px-1.5 py-0.2 bg-slate-100 text-slate-500 text-[7px] font-black uppercase rounded" x-text="'Lot: ' + item.batch_number"></span>
                                        <div class="w-1 h-1 bg-slate-300 rounded-full"></div>
                                        <span class="text-[8px] text-slate-400 font-bold" x-text="'Pack: ' + item.pack_size"></span>
                                    </div>
                                </div>
                                <div class="text-right shrink-0">
                                    <span class="text-xs font-black text-slate-800" x-text="parseFloat(item.quantity_box).toFixed(1) + ' Box'"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Drawer Footer Actions -->
            <div class="p-6 border-t bg-slate-50 flex gap-4 flex-shrink-0" x-show="selectedProduction">
                <button @click="showDetailsDrawer = false" class="flex-1 py-4 bg-white border-2 border-slate-200 text-slate-500 rounded-2xl font-black italic tracking-tighter uppercase text-xs active:scale-[0.97] transition-all">
                    Close details
                </button>
                @if(Auth::user()->role === 'admin' || Auth::user()->hasPermission('mobile_production', 'delete'))
                <button @click="deleteProductionEntry(selectedProduction.id)" class="flex-1 py-4 bg-rose-50 border-2 border-rose-100 text-rose-500 rounded-2xl font-black italic tracking-tighter uppercase text-xs hover:bg-rose-100 active:scale-[0.97] transition-all flex items-center justify-center gap-2">
                    <i class="fas fa-trash-can"></i>
                    <span>Revert Entry</span>
                </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Toast Notification Block -->
    <div x-show="toast.show" x-cloak 
         x-transition:enter="transition ease-out duration-300 transform"
         x-transition:enter-start="opacity-0 translate-y-10 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-200 transform"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 translate-y-10 scale-95"
         class="fixed bottom-24 left-6 right-6 z-[200] p-1 rounded-[2rem] shadow-2xl"
         :class="toast.success ? 'grad-emerald' : 'grad-rose'">
        <div class="bg-white/95 backdrop-blur-md rounded-[1.9rem] p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-2xl flex items-center justify-center text-white shrink-0 shadow-lg" :class="toast.success ? 'grad-emerald' : 'grad-rose'">
                <i class="fas" :class="toast.success ? 'fa-check' : 'fa-circle-exclamation'"></i>
            </div>
            <div class="min-w-0 flex-1">
                <div class="text-[8px] font-black uppercase tracking-widest" :class="toast.success ? 'text-emerald-500' : 'text-rose-500'" x-text="toast.success ? 'Confirmed' : 'Notification'"></div>
                <p class="text-[11px] font-bold text-slate-800 leading-tight mt-0.5 truncate" x-text="toast.message"></p>
            </div>
        </div>
    </div>
</div>

<script>
function productionApp() {
    return {
        loading: false,
        branchCode: '2', // default Factory
        productionDate: '{{ date("Y-m-d") }}',
        items: [],
        typeFilter: '',
        finishedGoods: @json($products),
        productTypes: @json($productTypes),
        branches: @json($branches),
        
        // Search drawer state
        showSearchDrawer: false,
        searchQuery: '',
        activeSearchIndex: null,

        // Details drawer state
        showDetailsDrawer: false,
        selectedProduction: null,

        // Step/Wizard
        step: 1, 

        // Toast feedback
        toast: {
            show: false,
            success: true,
            message: ''
        },

        init() {
            this.addItem();
        },

        showToast(message, success = true) {
            this.toast.message = message;
            this.toast.success = success;
            this.toast.show = true;
            setTimeout(() => this.toast.show = false, 4000);
        },

        addItem() {
            this.items.push({
                product_id: '',
                product_name: '',
                pack_size: '',
                quantity: '',
                batch_number: '',
                mfg_date: '{{ date("Y-m-d") }}', 
                exp_date: '',
                requirements: [],
                loadingRequirements: false,
                isPossible: true,
                requirementsError: '',
                showRecipeCollapse: false
            });
        },

        removeItem(index) {
            if (this.items.length > 1) {
                this.items.splice(index, 1);
            } else {
                this.showToast('At least one item is required in a batch.', false);
            }
        },

        openProductSearch(index) {
            this.activeSearchIndex = index;
            this.searchQuery = '';
            this.showSearchDrawer = true;
        },

        selectProduct(product) {
            const index = this.activeSearchIndex;
            if (index !== null && this.items[index]) {
                const item = this.items[index];
                item.product_id = product.id;
                item.product_name = product.name;
                item.pack_size = product.pack_name || 'N/A';
                
                // Auto calculate exp_date to 1 year from mfg_date
                if (item.mfg_date) {
                    const mfg = new Date(item.mfg_date);
                    mfg.setFullYear(mfg.getFullYear() + 1);
                    item.exp_date = mfg.toISOString().split('T')[0];
                }

                this.fetchRequirements(index);
            }
            this.showSearchDrawer = false;
        },

        get filteredProductsForSearch() {
            let list = this.finishedGoods;
            if (this.typeFilter) {
                list = list.filter(p => p.product_type_id == this.typeFilter);
            }
            if (this.searchQuery.trim()) {
                const q = this.searchQuery.toLowerCase();
                list = list.filter(p => 
                    p.name.toLowerCase().includes(q) || 
                    (p.alias_name && p.alias_name.toLowerCase().includes(q)) ||
                    (p.item_code && p.item_code.toLowerCase().includes(q))
                );
            }
            return list;
        },

        fetchRequirements(index) {
            const item = this.items[index];
            if (!item.product_id || !item.quantity || item.quantity <= 0) {
                item.requirements = [];
                return;
            }

            item.loadingRequirements = true;
            item.requirementsError = '';

            fetch("{{ route('mobile.production.check-stock') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    product_id: item.product_id,
                    quantity: item.quantity,
                    branch_code: this.branchCode
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    item.requirements = data.requirements;
                    item.isPossible = data.possible;
                } else {
                    item.requirements = [];
                    item.isPossible = true; 
                    item.requirementsError = data.message;
                }
            })
            .catch(err => {
                console.error(err);
                item.requirementsError = 'Failed to load requirements';
            })
            .finally(() => {
                item.loadingRequirements = false;
            });
        },

        get totalQuantity() {
            return this.items.reduce((sum, item) => sum + (parseFloat(item.quantity) || 0), 0);
        },

        goToReview() {
            if (!this.branchCode || !this.productionDate) {
                this.showToast('Please fill Branch and Date', false);
                return;
            }
            if (this.items.some(i => !i.product_id || !i.quantity || !i.batch_number || !i.mfg_date || !i.exp_date)) {
                this.showToast('Kindly fill all fields, including Batch No, MFG and EXP dates.', false);
                return;
            }
            if (this.items.some(i => !i.isPossible)) {
                this.showToast('Recipe stock shortage! Review components.', false);
                return;
            }
            this.step = 2;
        },

        async submit() {
            this.loading = true;
            try {
                const response = await fetch("{{ route('mobile.production.store') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        production_date: this.productionDate,
                        branch_code: this.branchCode,
                        items: this.items
                    })
                });

                const data = await response.json();
                if (data.success) {
                    this.showToast(data.message, true);
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    this.showToast(data.message, false);
                }
            } catch (e) {
                this.showToast('System connectivity error. Try again.', false);
            } finally {
                this.loading = false;
            }
        },

        viewDetail(id) {
            this.loading = true;
            fetch(`{{ url('mobile/production') }}/${id}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        this.selectedProduction = data.production;
                        this.showDetailsDrawer = true;
                    } else {
                        this.showToast('Failed to load batch details', false);
                    }
                })
                .catch(err => {
                    console.error(err);
                    this.showToast('Failed to load batch details', false);
                })
                .finally(() => {
                    this.loading = false;
                });
        },

        async deleteProductionEntry(id) {
            if (!confirm('Are you sure you want to delete this production entry? Stock will be reverted back.')) {
                return;
            }

            try {
                const response = await fetch(`{{ url('mobile/production') }}/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });

                const data = await response.json();
                if (data.success) {
                    this.showToast(data.message, true);
                    this.showDetailsDrawer = false;
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    this.showToast(data.message, false);
                }
            } catch (e) {
                this.showToast('Failed to delete production entry.', false);
            }
        }
    }
}
</script>
@endsection
