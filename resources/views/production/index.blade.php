@extends('layouts.app')

@section('content')
<div x-data="productionManager()" class="min-h-screen bg-[#f8fafc] py-8">
    <div class="max-w-[95%] mx-auto">
        <!-- Dashboard Header -->
        <div class="bg-white rounded-[2.5rem] shadow-xl shadow-indigo-100/50 p-8 mb-8 border border-indigo-50/50 overflow-hidden relative">
            <div class="absolute top-0 right-0 w-64 h-64 bg-amber-50/30 rounded-full -mr-32 -mt-32 blur-3xl"></div>
            
            <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div>
                    <div class="flex items-center gap-3 mb-2">
                        <div class="bg-amber-500 text-white p-3 rounded-2xl shadow-lg shadow-amber-200">
                            <i class="fas fa-boxes-stacked text-xl"></i>
                        </div>
                    </div>
                    <h1 class="text-3xl font-black text-gray-900 italic tracking-tighter uppercase">Production Manager</h1>
                    <p class="text-gray-400 font-bold text-[10px] uppercase tracking-widest flex items-center gap-2">
                        Record and Track Manufacturing Batches
                    </p>
                </div>

                <div class="flex gap-4">
                    @if(Auth::user()->hasPermission('production', 'create') && Auth::user()->hasFeature('production', 'management'))
                    <button @click.stop="openModal()" class="bg-indigo-600 text-white px-8 py-4 rounded-2xl font-black italic tracking-tighter hover:bg-indigo-700 transition shadow-xl shadow-indigo-100 flex items-center gap-3 uppercase">
                        <i class="fas fa-plus"></i> New Production Entry
                    </button>
                    @endif
                </div>
            </div>
        </div>

        @if(Auth::user()->hasFeature('production', 'history'))
        <!-- Production History -->
        <div class="bg-white rounded-[2.5rem] shadow-xl shadow-indigo-100/50 overflow-hidden border border-indigo-50/50">
            <div class="p-8 border-b border-gray-50 bg-gray-50/30">
                <h3 class="text-gray-700 font-black flex items-center italic uppercase tracking-tighter">
                    <i class="fas fa-history mr-3 text-amber-500"></i> Recent Production History
                </h3>
            </div>
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50/50">
                            <th class="px-8 py-6 text-[10px] font-black text-gray-400 uppercase tracking-widest">Entry Date</th>
                            <th class="px-8 py-6 text-[10px] font-black text-gray-400 uppercase tracking-widest">Target Branch</th>
                            <th class="px-8 py-6 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">Items</th>
                            <th class="px-8 py-6 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">Total Volume</th>
                            <th class="px-8 py-6 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">Recorded By</th>
                            <th class="px-8 py-6 text-[10px] font-black text-gray-400 uppercase tracking-widest text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse($history as $production)
                        <tr class="hover:bg-amber-50/20 transition-all group">
                            <td class="px-8 py-5">
                                <div class="font-bold text-gray-800 text-sm italic">{{ date('d M, Y', strtotime($production->production_date)) }}</div>
                                <div class="text-[9px] text-gray-400 font-bold">#{{ str_pad($production->id, 5, '0', STR_PAD_LEFT) }}</div>
                            </td>
                            <td class="px-8 py-5">
                                <span class="bg-indigo-50 text-indigo-600 font-bold px-3 py-1 rounded-lg text-xs">
                                    {{ $production->branch_name }} ({{ $production->branch_code }})
                                </span>
                            </td>
                            <td class="px-8 py-5 text-center">
                                <div class="text-sm font-bold text-gray-600">{{ $production->items->count() }} Products</div>
                            </td>
                            <td class="px-8 py-5 text-center">
                                <span class="text-lg font-black italic tracking-tighter text-gray-900">{{ number_format($production->items->sum('quantity_box'), 0) }}</span>
                                <span class="text-[9px] font-black text-gray-400 uppercase block -mt-1">Boxes</span>
                            </td>
                            <td class="px-8 py-5 text-center">
                                <div class="text-xs font-black text-gray-600 uppercase">{{ $production->user->name ?? 'System' }}</div>
                            </td>
                            <td class="px-8 py-5 text-right">
                                <div class="flex justify-end gap-2">
                                    @if(Auth::user()->hasPermission('production', 'edit'))
                                    <button @click="editProduction({{ $production->id }})" title="Edit" class="bg-blue-100 text-blue-600 p-2.5 rounded-xl hover:bg-blue-600 hover:text-white transition shadow-sm"><i class="fas fa-edit text-xs"></i></button>
                                    @endif
                                    @if(Auth::user()->hasPermission('production', 'delete'))
                                    <button @click="deleteProduction({{ $production->id }})" title="Delete" class="bg-red-100 text-red-600 p-2.5 rounded-xl hover:bg-red-600 hover:text-white transition shadow-sm"><i class="fas fa-trash text-xs"></i></button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="px-8 py-20 text-center text-gray-400 italic font-bold uppercase tracking-widest">No production records found</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>

    <!-- Production Modal -->
    <template x-if="showModal">
        <div class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100">
            
            <div class="bg-white w-full max-w-6xl h-[90vh] rounded-[2.5rem] shadow-2xl overflow-hidden flex flex-col"
                 @click.outside="closeModal()">
                
                <!-- Modal Header -->
                <div class="bg-indigo-600 p-8 text-white relative">
                    <div class="flex items-center gap-4">
                        <div class="bg-white/20 p-3 rounded-2xl">
                            <i class="fas fa-industry text-2xl"></i>
                        </div>
                        <div>
                            <h2 class="text-2xl font-black italic tracking-tighter uppercase">
                                <span x-text="isEditing ? 'Edit Production Entry' : 'Record New Production'"></span>
                                <span x-show="step === 2"> / Preview</span>
                            </h2>
                            <p class="text-indigo-100 font-bold text-[10px] uppercase tracking-widest mt-1">
                                <span x-show="step === 1" x-text="isEditing ? 'Modify existing production details' : 'Fill production details for multiple products'"></span>
                                <span x-show="step === 2">Review batch details before final submission</span>
                            </p>
                        </div>
                    </div>
                    <button @click="closeModal()" class="absolute top-8 right-8 text-white/50 hover:text-white transition">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                    
                    <!-- Progress Bar -->
                    <div class="absolute bottom-0 left-0 w-full h-1 bg-white/10">
                        <div class="h-full bg-amber-400 transition-all duration-500" :style="'width: ' + (step * 50) + '%'"></div>
                    </div>
                </div>

                <!-- Modal Body -->
                <div class="flex-1 overflow-hidden flex flex-col p-8">
                    
                    <!-- Step 1: Entry Form -->
                    <div x-show="step === 1" class="flex flex-col h-full overflow-hidden">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8 pb-8 border-b border-gray-100">
                            <div class="space-y-2">
                                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Target Branch</label>
                                <select x-model="branchCode" class="w-full bg-gray-100 border-2 border-gray-200 rounded-2xl px-6 py-4 font-black text-indigo-600 focus:border-indigo-500 focus:ring-0 transition cursor-not-allowed" disabled>
                                    @foreach($branches as $branch)
                                    <option value="{{ $branch->code }}">{{ $branch->name }} ({{ $branch->code }})</option>
                                    @endforeach
                                </select>
                                <p class="text-[8px] font-bold text-amber-500 mt-1 uppercase tracking-tighter">* All production is logged in Factory Branch</p>
                            </div>
                            <div class="space-y-2">
                                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Production Date</label>
                                <input type="date" x-model="productionDate" class="w-full bg-gray-50 border-2 border-gray-100 rounded-2xl px-6 py-4 font-bold text-gray-900 focus:border-indigo-500 focus:ring-0 transition">
                            </div>
                            @if(Auth::user()->hasFeature('production', 'type_filter'))
                            <div class="space-y-2">
                                <label class="text-[10px] font-black text-indigo-400 uppercase tracking-widest ml-1 italic flex items-center gap-2">
                                    <i class="fas fa-filter text-[10px]"></i> Quick Filter by Type
                                </label>
                                <select x-model="typeFilter" class="w-full bg-indigo-50/50 border-2 border-indigo-100 rounded-2xl px-6 py-4 font-black text-indigo-600 focus:border-indigo-500 focus:ring-0 transition italic uppercase tracking-tighter text-xs">
                                    <option value="">Show All Products</option>
                                    <template x-for="type in productTypes" :key="type.id">
                                        <option :value="type.id" x-text="type.type_name"></option>
                                    </template>
                                </select>
                            </div>
                            @endif
                        </div>

                        <div class="flex-1 overflow-y-auto pr-4 custom-scrollbar">
                            <table class="w-full">
                                <thead class="sticky top-0 bg-white z-10">
                                    <tr>
                                        <th class="text-left text-[10px] font-black text-gray-400 uppercase pb-4">Product Details <span class="text-rose-500">*</span></th>
                                        <th class="text-center text-[10px] font-black text-gray-400 uppercase pb-4">Qty (Box) <span class="text-rose-500">*</span></th>
                                        <th class="text-left text-[10px] font-black text-gray-400 uppercase pb-4">Batch Info (No / MFG / EXP) <span class="text-rose-500">*</span></th>
                                        <th class="text-right text-[10px] font-black text-gray-400 uppercase pb-4"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <template x-for="(item, index) in items" :key="index">
                                        <tr class="group">
                                            <td class="py-4 align-top w-1/3 pr-4">
                                                <select x-model="item.product_id" @change="updateProductInfo(index)" class="w-full bg-gray-50 border-2 border-gray-100 rounded-xl px-4 py-3 font-black text-gray-800 focus:border-indigo-500 focus:ring-0 transition text-sm italic">
                                                    <option value="">Select Product</option>
                                                    <template x-for="p in filteredProducts" :key="p.id">
                                                        <option :value="p.id" x-text="p.name + ' (' + (p.pack_name || 'N/A') + ')'"></option>
                                                    </template>
                                                </select>
                                                <div x-show="item.pack_size" class="mt-1 px-3 py-1 bg-amber-50 text-amber-600 rounded-lg text-[9px] font-black uppercase tracking-tighter inline-block" x-text="'Pack: ' + item.pack_size"></div>
                                            </td>
                                            <td class="py-4 align-top w-20">
                                                <input type="number" x-model="item.quantity" @input="fetchRequirements(index)" class="w-24 bg-gray-50 border-2 border-gray-100 rounded-xl px-4 py-3 text-center font-black text-gray-900 focus:border-indigo-500 focus:ring-0 transition" placeholder="0">
                                            </td>
                                            <td class="py-4 align-top pl-4">
                                                <div class="flex gap-2">
                                                    <div class="flex-1 relative">
                                                        <input type="text" x-model="item.batch_number" @input="item.batch_number = $event.target.value.toUpperCase()" class="w-full bg-gray-50 border-2 border-gray-100 rounded-xl px-4 py-3 text-[11px] font-bold text-gray-800 placeholder:text-gray-300 uppercase" placeholder="BATCH #">
                                                        <div x-show="!item.batch_number" class="absolute top-0 right-2 text-rose-300 transform translate-y-1 p-1"><i class="fas fa-circle text-[6px]"></i></div>
                                                    </div>
                                                    <div class="w-32 relative">
                                                        <input type="date" x-model="item.mfg_date" title="MFG Date" class="w-full bg-gray-50 border-2 border-gray-100 rounded-xl px-3 py-3 text-[10px] font-bold border-gray-100">
                                                        <div x-show="!item.mfg_date" class="absolute top-0 right-2 text-rose-300 transform translate-y-1 p-1"><i class="fas fa-circle text-[6px]"></i></div>
                                                    </div>
                                                    <div class="w-32 relative">
                                                        <input type="date" x-model="item.exp_date" title="EXP Date" class="w-full bg-gray-50 border-2 border-gray-100 rounded-xl px-3 py-3 text-[10px] font-bold border-gray-100">
                                                        <div x-show="!item.exp_date" class="absolute top-0 right-2 text-rose-300 transform translate-y-1 p-1"><i class="fas fa-circle text-[6px]"></i></div>
                                                    </div>
                                                </div>

                                                <!-- Loading Skeleton -->
                                                <div x-show="item.loadingRequirements" class="mt-4 bg-gray-50/50 rounded-2xl border border-gray-100 p-6 space-y-5">
                                                    <div class="flex items-center gap-3">
                                                        <div class="w-4 h-4 bg-indigo-100 rounded-full animate-bounce"></div>
                                                        <div class="h-3 w-40 bg-indigo-100/50 rounded-lg animate-pulse"></div>
                                                    </div>
                                                    <div class="space-y-4">
                                                        <template x-for="i in [1,2]">
                                                            <div class="flex justify-between items-center opacity-50">
                                                                <div class="space-y-2">
                                                                    <div class="h-3 w-48 bg-gray-200 rounded animate-pulse"></div>
                                                                    <div class="h-2 w-20 bg-gray-100 rounded animate-pulse"></div>
                                                                </div>
                                                                <div class="flex gap-4">
                                                                    <div class="h-6 w-16 bg-gray-100 rounded-lg animate-pulse"></div>
                                                                    <div class="h-6 w-16 bg-gray-100 rounded-lg animate-pulse"></div>
                                                                </div>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </div>

                                                <!-- Recipe Requirements Display -->
                                                <div x-show="item.requirements && item.requirements.length > 0 && !item.loadingRequirements" class="mt-4 bg-indigo-50/30 rounded-2xl border border-indigo-100/50 p-4 shadow-inner">
                                                    <div class="flex items-center justify-between mb-3 px-1">
                                                        <h4 class="text-[9px] font-black text-indigo-500 uppercase tracking-widest italic flex items-center gap-2">
                                                            <i class="fas fa-flask"></i> Recipe Requirements (Factory Stock)
                                                        </h4>
                                                        <span x-show="!item.isPossible" class="bg-rose-500 text-white px-2 py-0.5 rounded text-[7px] font-black uppercase tracking-tighter shadow-lg shadow-rose-200 animate-bounce">Insufficient Stock</span>
                                                    </div>
                                                    <div class="space-y-2">
                                                        <template x-for="req in item.requirements" :key="req.item_code">
                                                            <div class="flex items-center justify-between py-1 border-b border-indigo-100/30 last:border-0 grow">
                                                                <div class="flex-1">
                                                                    <div class="text-[10px] font-bold text-gray-700" x-text="req.name"></div>
                                                                    <div class="text-[8px] font-black text-gray-400 uppercase" x-text="req.item_code"></div>
                                                                </div>
                                                                <div class="text-right flex gap-6 items-center">
                                                                    <div>
                                                                        <div class="text-[10px] font-black text-gray-900" x-text="parseFloat(req.required_qty).toFixed(3)"></div>
                                                                        <div class="text-[8px] font-bold text-gray-400 uppercase tracking-tighter">Required</div>
                                                                    </div>
                                                                    <div>
                                                                        <div class="text-[10px] font-black" :class="req.live_stock < req.required_qty ? 'text-rose-500' : 'text-emerald-600'" x-text="parseFloat(req.live_stock).toFixed(3)"></div>
                                                                        <div class="text-[8px] font-bold text-gray-400 uppercase tracking-tighter">Live Stock</div>
                                                                    </div>
                                                                    <div x-show="req.shortfall > 0">
                                                                        <div class="text-[10px] font-black text-rose-600" x-text="'-' + parseFloat(req.shortfall).toFixed(3)"></div>
                                                                        <div class="text-[8px] font-black text-rose-300 uppercase tracking-tighter">Shortfall</div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </div>
                                                <div x-show="item.requirementsError" class="mt-2 text-[9px] font-bold text-rose-500 italic" x-text="item.requirementsError"></div>
                                            </td>
                                            <td class="py-4 text-right">
                                                <button @click="removeItem(index)" class="text-red-300 hover:text-red-500 transition-colors p-2 pt-4 block">
                                                    <i class="fas fa-trash-can"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                            <button @click="addItem()" class="mt-4 w-full py-4 border-2 border-dashed border-indigo-100 rounded-2xl text-indigo-400 font-bold hover:bg-indigo-50 hover:border-indigo-200 transition flex items-center justify-center gap-2">
                                <i class="fas fa-plus-circle"></i> ADD ANOTHER PRODUCT
                            </button>
                        </div>
                    </div>

                    <!-- Step 2: Invoice Preview -->
                    <div x-show="step === 2" class="h-full overflow-y-auto px-12 py-8 bg-gray-50/50 rounded-[2rem] border border-indigo-50/50">
                        <div class="flex justify-between items-start mb-12">
                            <div>
                                <h1 class="text-4xl font-black italic tracking-tighter text-indigo-600 mb-2">PRODUCTION SLIP</h1>
                                <p class="text-xs font-bold text-gray-400 uppercase tracking-widest" x-text="isEditing ? 'UPDATE PREVIEW' : 'NEW ENTRY PREVIEW'"></p>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-black text-gray-800 uppercase" x-text="branchName"></div>
                                <div class="text-[10px] font-bold text-indigo-400" x-text="branchCode"></div>
                                <div class="mt-2 text-sm font-black italic text-gray-500" x-text="formattedDate"></div>
                            </div>
                        </div>

                        <table class="w-full mb-12">
                            <thead>
                                <tr class="border-b-2 border-gray-200 pb-4">
                                    <th class="text-left py-4 text-[10px] font-black text-gray-400 uppercase">Description</th>
                                    <th class="text-center py-4 text-[10px] font-black text-gray-400 uppercase">Batch Info</th>
                                    <th class="text-right py-4 text-[10px] font-black text-gray-400 uppercase">Quantity</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="item in items" :key="item.id">
                                    <tr class="border-b border-gray-100">
                                        <td class="py-6">
                                            <div class="font-bold text-gray-800 italic" x-text="item.product_name"></div>
                                            <div class="text-[9px] font-black text-indigo-400 uppercase" x-text="item.pack_size"></div>
                                        </td>
                                        <td class="py-6 text-center">
                                            <div class="inline-flex flex-col items-center">
                                                <span class="px-2 py-0.5 bg-amber-100 text-amber-700 rounded text-[9px] font-black mb-1" x-text="item.batch_number || 'N/A'"></span>
                                                <div class="text-[8px] font-bold text-gray-400 uppercase">
                                                    MFG: <span x-text="item.mfg_date || '--'"></span> | EXP: <span x-text="item.exp_date || '--'"></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-6 text-right">
                                            <div class="text-xl font-black italic tracking-tighter text-gray-900" x-text="item.quantity"></div>
                                            <div class="text-[9px] font-black text-gray-400 uppercase">BOXES</div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="2" class="py-8 text-right font-black text-gray-400 uppercase tracking-widest">Total Produced Volume</td>
                                    <td class="py-8 text-right">
                                        <div class="text-3xl font-black italic tracking-tighter text-indigo-600" x-text="totalQuantity"></div>
                                        <div class="text-[10px] font-black text-indigo-300 uppercase">Total Boxes</div>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>

                        <div class="p-6 bg-amber-50 rounded-2xl border border-amber-100">
                            <div class="flex gap-4">
                                <div class="bg-amber-400 text-white p-3 rounded-xl">
                                    <i class="fas fa-circle-exclamation"></i>
                                </div>
                                <div>
                                    <h4 class="font-black italic text-amber-700 text-sm">Action Notice</h4>
                                    <p class="text-xs font-bold text-amber-600 leading-relaxed mt-1" x-text="isEditing ? 'This update will reverse previous stock changes and apply new values for products and raw materials.' : 'This entry will automatically adjust the inventory levels for products and their corresponding raw materials.'"></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="p-8 border-t bg-gray-50 flex items-center justify-between gap-4">
                    <button @click="closeModal()" class="px-8 py-4 bg-white border-2 border-gray-100 text-gray-500 rounded-2xl font-black italic tracking-tighter hover:bg-gray-200 transition uppercase">
                        Cancel
                    </button>
                    <div class="flex gap-4">
                        <template x-if="step === 2">
                            <button @click="step = 1" class="px-8 py-4 bg-gray-200 text-gray-700 rounded-2xl font-black italic tracking-tighter hover:bg-gray-300 transition uppercase">
                                BACK TO EDIT
                            </button>
                        </template>
                        <button @click="step === 1 ? goToPreview() : submitProduction()" 
                                class="px-12 py-4 bg-indigo-600 text-white rounded-2xl font-black italic tracking-tighter hover:bg-indigo-700 transition shadow-xl shadow-indigo-100 uppercase flex items-center gap-3">
                            <i :class="step === 1 ? 'fas fa-eye' : (isEditing ? 'fas fa-save' : 'fas fa-cloud-upload-alt')"></i>
                            <span x-text="step === 1 ? 'Preview Slip' : (isEditing ? 'Update & Save Changes' : 'Confirm & Save Production')"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>

<!-- Forms for Submission -->
<form id="productionSubmitForm" action="" method="POST" style="display: none;">
    @csrf
    <div id="method_field"></div>
    <input type="hidden" name="production_date" id="form_date">
    <input type="hidden" name="branch_code" id="form_branch">
    <div id="form_items"></div>
</form>

<form id="deleteForm" action="" method="POST" style="display: none;">
    @csrf
    @method('DELETE')
</form>

<script>
function productionManager() {
    return {
        showModal: false,
        isEditing: false,
        editId: null,
        step: 1,
        branchCode: '2',
        branchName: 'Factory',
        productionDate: '{{ date("Y-m-d") }}',
        items: [],
        typeFilter: '',
        branches: @json($branches),
        finishedGoods: @json($finishedGoods),
        productTypes: @json($productTypes),

        get filteredProducts() {
            if (!this.typeFilter) return this.finishedGoods;
            return this.finishedGoods.filter(p => p.product_type_id == this.typeFilter);
        },

        openModal() {
            this.isEditing = false;
            this.editId = null;
            this.showModal = true;
            this.step = 1;
            this.typeFilter = '';
            this.branchCode = '2';
            this.items = [];
            this.addItem();
        },

        closeModal() {
            // Only confirm if they have actually selected a product or entered quantity
            const hasData = this.items.some(i => i.product_id || i.quantity || i.batch_number);
            if (hasData) {
                if (confirm('Are you sure you want to discard this and close?')) {
                    this.showModal = false;
                    this.isEditing = false;
                    this.items = [];
                }
            } else {
                this.showModal = false;
                this.isEditing = false;
                this.items = [];
            }
        },

        addItem() {
            this.items.push({
                product_id: '',
                product_name: '',
                pack_size: '',
                quantity: '',
                batch_number: '',
                mfg_date: '',
                exp_date: '',
                requirements: [],
                loadingRequirements: false,
                isPossible: true,
                requirementsError: ''
            });
        },

        removeItem(index) {
            if (this.items.length > 1) {
                this.items.splice(index, 1);
            }
        },

        updateProductInfo(index) {
            const item = this.items[index];
            const p = this.finishedGoods.find(p => p.id == item.product_id);
            if (p) {
                item.product_name = p.name;
                item.pack_size = p.pack_name || 'N/A';
                this.fetchRequirements(index);
            }
        },

        fetchRequirements(index) {
            const item = this.items[index];
            if (!item.product_id || !item.quantity || item.quantity <= 0) {
                item.requirements = [];
                return;
            }

            item.loadingRequirements = true;
            item.requirementsError = '';

            fetch("{{ route('production.check-stock') }}", {
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
                    item.isPossible = true; // No recipe means we don't block
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

        goToPreview() {
            if (!this.branchCode || !this.productionDate) {
                alert('Please fill Branch and Date');
                return;
            }
            if (this.items.some(i => !i.product_id || !i.quantity || !i.batch_number || !i.mfg_date || !i.exp_date)) {
                alert('Please ensure all required fields (*), including Batch No, MFG and EXP dates are filled for all products.');
                return;
            }
            if (this.items.some(i => !i.isPossible)) {
                alert('CRITICAL: Some products cannot be produced due to Raw Material shortfall in Factory. Entry blocked.');
                return;
            }
            const b = this.branches.find(b => b.code == this.branchCode);
            this.branchName = b ? b.name : this.branchCode;
            this.step = 2;
        },

        editProduction(id) {
            fetch(`{{ url('production') }}/${id}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const p = data.production;
                        this.editId = p.id;
                        this.isEditing = true;
                        this.branchCode = p.branch_code;
                        this.productionDate = p.production_date;
                        this.typeFilter = '';
                        this.items = p.items.map(i => ({
                            product_id: i.product_id,
                            product_name: i.product_name,
                            pack_size: i.pack_size,
                            quantity: i.quantity_box,
                            batch_number: i.batch_number,
                            mfg_date: i.mfg_date,
                            exp_date: i.exp_date
                        }));
                        this.showModal = true;
                        this.step = 1;
                    }
                });
        },

        deleteProduction(id) {
            if (confirm('Are you sure you want to delete this production entry? Stock will be reverted back.')) {
                const form = document.getElementById('deleteForm');
                form.action = `{{ url('production') }}/${id}`;
                form.submit();
            }
        },

        get totalQuantity() {
            return this.items.reduce((sum, item) => sum + (parseFloat(item.quantity) || 0), 0);
        },

        get formattedDate() {
            if (!this.productionDate) return '';
            const options = { day: 'numeric', month: 'short', year: 'numeric' };
            return new Date(this.productionDate).toLocaleDateString('en-GB', options);
        },

        submitProduction() {
            const form = document.getElementById('productionSubmitForm');
            const methodField = document.getElementById('method_field');
            
            if (this.isEditing) {
                form.action = `{{ url('production') }}/${this.editId}`;
                methodField.innerHTML = '<input type="hidden" name="_method" value="PUT">';
            } else {
                form.action = `{{ route('production.store') }}`;
                methodField.innerHTML = '';
            }

            document.getElementById('form_date').value = this.productionDate;
            document.getElementById('form_branch').value = this.branchCode;
            
            const itemsContainer = document.getElementById('form_items');
            itemsContainer.innerHTML = '';
            
            this.items.forEach((item, index) => {
                const prefix = `items[${index}]`;
                const fields = ['product_id', 'quantity', 'batch_number', 'mfg_date', 'exp_date'];
                fields.forEach(field => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = `${prefix}[${field}]`;
                    input.value = item[field];
                    itemsContainer.appendChild(input);
                });
            });

            form.submit();
        }
    }
}
</script>

<style>
    .custom-scrollbar::-webkit-scrollbar { width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }
</style>
@endsection
