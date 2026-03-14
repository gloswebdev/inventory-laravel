@extends('layouts.app')

@section('content')
<div x-data="{ showCompletionModal: false, showReorderModal: false }" class="min-h-screen bg-[#f8fafc] py-8">
    <div class="max-w-[95%] mx-auto">
        <!-- Header Card -->
        <div class="bg-white rounded-[2.5rem] shadow-xl shadow-indigo-100/50 p-8 mb-8 border border-indigo-50/50 overflow-hidden relative">
            <div class="absolute top-0 right-0 w-64 h-64 bg-indigo-50/30 rounded-full -mr-32 -mt-32 blur-3xl"></div>
            
            <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div>
                    <div class="flex items-center gap-3 mb-2">
                        <div class="bg-indigo-600 text-white p-3 rounded-2xl shadow-lg shadow-indigo-200">
                            <i class="fas fa-cog fa-spin text-xl"></i>
                        </div>
                    </div>
                    <h1 class="text-2xl font-black text-gray-900 italic tracking-tighter uppercase">Indent Process Manager</h1>
                    <p class="text-gray-400 font-bold text-[10px] uppercase tracking-widest flex items-center gap-2">
                        Cross-Branch Stock Distribution Grid
                        @if($indent->status == 'completed')
                        <span class="bg-green-500 text-white px-2 py-0.5 rounded-full text-[8px] font-black italic">FULLY COMPLETED</span>
                        @elseif($indent->status == 'partly completed')
                        <span class="bg-blue-500 text-white px-2 py-0.5 rounded-full text-[8px] font-black italic">PARTLY COMPLETED</span>
                        @else
                        <span class="bg-amber-500 text-white px-2 py-0.5 rounded-full text-[8px] font-black italic uppercase tracking-tighter shadow-sm shadow-amber-100">PENDING</span>
                        @endif

                        @if(Auth::user()->hasFeature('indent', 'branch_reorder'))
                        <button type="button" @click="showReorderModal = true" class="ml-4 bg-indigo-50 hover:bg-indigo-100 text-indigo-600 px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest border border-indigo-100 transition-all flex items-center gap-2 shadow-sm">
                            <i class="fas fa-sort"></i> Reorder Columns
                        </button>
                        @endif
                    </p>
                </div>

                <div class="bg-indigo-50/50 p-6 rounded-3xl border border-indigo-100 flex items-center gap-8">
                    <div class="text-center">
                        <div class="text-[10px] font-black text-indigo-400 uppercase tracking-widest mb-1">Indent ID</div>
                        <div class="text-lg font-black text-indigo-600 italic tracking-tighter">#IND-{{ $indent->id }}</div>
                    </div>
                    <div class="w-px h-10 bg-indigo-200"></div>
                    <div class="text-center">
                        <div class="text-[10px] font-black text-indigo-400 uppercase tracking-widest mb-1">Target Branch</div>
                        <div class="text-lg font-black text-gray-800 italic">{{ $indent->branch_name }}</div>
                    </div>
                    <div class="w-px h-10 bg-indigo-200"></div>
                    <div class="text-center">
                        <div class="text-[10px] font-black text-indigo-400 uppercase tracking-widest mb-1">Created By</div>
                        <div class="text-lg font-black text-gray-800 italic">{{ $indent->user->name ?? 'System' }}</div>
                    </div>
                    <div class="w-px h-10 bg-indigo-200"></div>
                    <div class="text-center">
                        <div class="text-[10px] font-black text-indigo-400 uppercase tracking-widest mb-1">Indent Date</div>
                        <div class="text-lg font-black text-gray-800 italic">{{ date('d M, Y', strtotime($indent->indent_date)) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Processing Grid Card -->
        <div class="bg-white rounded-[2.5rem] shadow-xl shadow-indigo-100/50 overflow-hidden border border-indigo-50/50">
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50/50">
                            <th class="sticky left-0 bg-gray-50/50 px-8 py-6 text-[11px] font-black text-gray-400 uppercase tracking-widest z-20 border-r border-gray-100 shadow-[2px_0_5px_rgba(0,0,0,0.02)]">Product Details</th>
                            <th class="px-6 py-6 text-[11px] font-black text-indigo-600 uppercase tracking-widest text-center bg-indigo-50/30">Indent<br>Qty (Boxes)</th>
                            <th class="px-6 py-6 text-[11px] font-black text-green-600 uppercase tracking-widest text-center bg-green-50/30">Stock at<br>Entry (Box)</th>
                            
                            @foreach($branches as $branch)
                            <th class="px-6 py-6 text-[11px] font-black text-gray-500 uppercase tracking-widest text-center border-l border-gray-100 min-w-[120px]">
                                {{ $branch->name }}
                                <div class="text-[9px] text-gray-400 font-bold mt-1">CODE: {{ $branch->code }}</div>
                            </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($indent->items as $item)
                        <tr class="hover:bg-indigo-50/20 transition-all group">
                            <td class="sticky left-0 bg-white group-hover:bg-indigo-50/20 px-8 py-5 z-10 border-r border-gray-100 shadow-[2px_0_5px_rgba(0,0,0,0.02)]">
                                <div class="font-bold text-gray-800 text-sm italic">{{ $item->product_name }}</div>
                                <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest">{{ $item->product->pack_name ?? 'N/A' }}</div>
                            </td>
                            <td class="px-6 py-5 text-center">
                                <span class="text-xl font-black italic tracking-tighter text-indigo-600">{{ number_format($item->final_qty_box, 0) }}</span>
                            </td>
                            <td class="px-6 py-5 text-center">
                                <span class="text-sm font-black text-green-600">{{ number_format($item->stock_box, 2) }}</span>
                            </td>

                            @foreach($branches as $branch)
                            @php 
                                $stock = $branchStocks[$item->product_id][$branch->code] ?? 0;
                                $isTarget = $branch->code == $indent->branch_code;
                            @endphp
                            <td class="px-6 py-5 text-center border-l border-gray-50 {{ $isTarget ? 'bg-indigo-50/10' : '' }}">
                                <div class="flex flex-col items-center">
                                    <span class="text-sm font-black {{ $stock > 0 ? 'text-gray-800' : 'text-gray-300' }}">
                                        {{ number_format($stock, 1) }}
                                    </span>
                                    <span class="text-[8px] font-black uppercase {{ $stock > 0 ? 'text-gray-400' : 'text-gray-300' }}">Box</span>
                                    
                                    @if($isTarget)
                                    <div class="mt-1 px-2 py-0.5 rounded-full bg-indigo-600 text-[7px] font-black text-white uppercase tracking-tighter">TARGET</div>
                                    @endif
                                </div>
                            </td>
                            @endforeach
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Float Action Bar -->
        <div class="fixed bottom-8 left-1/2 -translate-x-1/2 bg-white/80 backdrop-blur-xl border border-white/20 px-8 py-4 rounded-3xl shadow-2xl flex items-center gap-6 z-30">
            <div class="flex flex-col">
                <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Total Indented</span>
                <span class="text-2xl font-black italic tracking-tighter text-indigo-600">{{ number_format($indent->total_boxes, 0) }} <span class="text-xs uppercase">Boxes</span></span>
            </div>
            <div class="w-px h-10 bg-gray-200"></div>
            <a href="{{ route('indent.process.excel', $indent->id) }}" class="bg-green-600 text-white px-6 py-3 rounded-2xl font-black italic tracking-tighter hover:bg-green-700 transition shadow-xl shadow-green-100 flex items-center gap-3">
                <i class="fas fa-file-excel"></i> EXCEL MATRIX
            </a>
            <a href="{{ route('indent.process.pdf', $indent->id) }}" class="bg-red-600 text-white px-6 py-3 rounded-2xl font-black italic tracking-tighter hover:bg-red-700 transition shadow-xl shadow-red-100 flex items-center gap-3">
                <i class="fas fa-file-pdf"></i> PDF MATRIX
            </a>
            <button onclick="window.print()" class="bg-indigo-600 text-white px-6 py-3 rounded-2xl font-black italic tracking-tighter hover:bg-indigo-700 transition shadow-xl shadow-indigo-100 flex items-center gap-3">
                <i class="fas fa-print"></i> PRINT GRID
            </button>
            <div class="w-px h-10 bg-gray-200"></div>
            
            @if($indent->status != 'completed')
            <button @click="showCompletionModal = true" class="bg-amber-500 text-white px-8 py-3 rounded-2xl font-black italic tracking-tighter hover:bg-amber-600 transition shadow-xl shadow-amber-100 flex items-center gap-3">
                <i class="fas fa-check-double"></i> UPDATE COMPLETION
            </button>
            @else
            <button @click="showCompletionModal = true" class="bg-green-600 text-white px-8 py-3 rounded-2xl font-black italic tracking-tighter hover:bg-green-700 transition shadow-xl shadow-green-100 flex items-center gap-3">
                <i class="fas fa-edit"></i> EDIT COMPLETION
            </button>
            @endif

            <a href="{{ route('indent.index') }}" class="text-gray-500 font-bold hover:text-indigo-600 transition p-2">
                <i class="fas fa-arrow-left mr-1"></i> BACK
            </a>
        </div>
    </div>

    {{-- Completion Modal --}}
    <div x-show="showCompletionModal" 
         class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         style="display: none;">
        
        <div class="bg-white w-full max-w-2xl rounded-[2.5rem] shadow-2xl overflow-hidden"
             @click.away="showCompletionModal = false">
            <div class="bg-indigo-600 p-8 text-white relative">
                <h2 class="text-2xl font-black italic tracking-tighter uppercase">Update Indent Completion</h2>
                <p class="text-indigo-100 font-bold text-[10px] uppercase tracking-widest mt-1">Enter actual volumes completed for each product</p>
                <button @click="showCompletionModal = false" class="absolute top-8 right-8 text-white/50 hover:text-white transition">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>

            <form action="{{ route('indent.complete', $indent->id) }}" method="POST" class="p-8">
                @csrf
                <div class="max-h-[50vh] overflow-y-auto pr-2 custom-scrollbar">
                    <table class="w-full">
                        <thead class="sticky top-0 bg-white z-10">
                            <tr>
                                <th class="text-left text-[10px] font-black text-gray-400 uppercase pb-4">Product</th>
                                <th class="text-center text-[10px] font-black text-gray-400 uppercase pb-4">Asked (Box)</th>
                                <th class="text-right text-[10px] font-black text-gray-400 uppercase pb-4">Completed (Box)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($indent->items as $item)
                            <tr>
                                <td class="py-4">
                                    <div class="font-bold text-gray-900 text-sm italic">{{ $item->product_name }}</div>
                                    <div class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">{{ $item->product->pack_name ?? '' }}</div>
                                </td>
                                <td class="py-4 text-center font-black text-indigo-600 text-lg italic">
                                    {{ number_format($item->final_qty_box, 0) }}
                                </td>
                                <td class="py-4 text-right">
                                    <input type="number" step="1" name="completed_qty[{{ $item->id }}]" 
                                           value="{{ (int)$item->completed_qty }}"
                                           class="completion-input w-32 bg-gray-50 border-2 border-gray-100 rounded-xl px-4 py-2 text-right font-black text-gray-900 focus:border-indigo-500 focus:ring-0 transition">
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-8 flex gap-4">
                    <button type="submit" class="flex-1 bg-indigo-600 text-white py-4 rounded-2xl font-black italic tracking-tighter hover:bg-indigo-700 transition shadow-xl shadow-indigo-100 uppercase">
                        Save Completion Status
                    </button>
                    <button type="button" onclick="resetCompletion()" class="px-6 py-4 bg-red-50 text-red-500 rounded-2xl font-black italic tracking-tighter hover:bg-red-100 transition uppercase">
                        Reset
                    </button>
                    <button type="button" @click="showCompletionModal = false" class="px-8 py-4 bg-gray-100 text-gray-500 rounded-2xl font-black italic tracking-tighter hover:bg-gray-200 transition uppercase">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Reorder Branches Modal --}}
    <div x-show="showReorderModal" 
         class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         style="display: none;">
        
        <div class="bg-white w-full max-w-md rounded-[2.5rem] shadow-2xl overflow-hidden"
             @click.away="showReorderModal = false">
            <div class="bg-indigo-600 p-8 text-white relative">
                <h2 class="text-xl font-black italic tracking-tighter uppercase">Set Column Order</h2>
                <p class="text-indigo-100 font-bold text-[10px] uppercase tracking-widest mt-1">Drag branches to rearrange grid columns</p>
                <button @click="showReorderModal = false" class="absolute top-8 right-8 text-white/50 hover:text-white transition">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>

            <div class="p-8">
                <ul id="sortableBranches" class="space-y-3">
                    @foreach($branches as $branch)
                    <li data-id="{{ $branch->id }}" class="flex items-center gap-4 p-4 bg-gray-50 rounded-2xl border border-gray-100 cursor-move hover:bg-indigo-50 hover:border-indigo-100 transition-all group">
                        <i class="fas fa-grip-lines text-gray-300 group-hover:text-indigo-300"></i>
                        <span class="text-sm font-bold text-gray-700 italic">{{ $branch->name }}</span>
                        <span class="ml-auto text-[9px] font-black text-gray-400 uppercase tracking-widest">{{ $branch->code }}</span>
                    </li>
                    @endforeach
                </ul>

                <div class="mt-8 flex gap-4">
                    <button type="button" onclick="saveNewOrder()" class="flex-1 bg-indigo-600 text-white py-4 rounded-2xl font-black italic tracking-tighter hover:bg-indigo-700 transition shadow-xl shadow-indigo-100 uppercase">
                        Confirm Arrangement
                    </button>
                    <button type="button" @click="showReorderModal = false" class="px-8 py-4 bg-gray-100 text-gray-500 rounded-2xl font-black italic tracking-tighter hover:bg-gray-200 transition uppercase">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<style>
    .custom-scrollbar::-webkit-scrollbar { height: 6px; width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: #f8fafc; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }
    
    @media print {
        .fixed, .blur-3xl { display: none !important; }
        body { background: white; margin: 0; padding: 0; }
        .max-w-\[95\%\] { max-width: 100%; margin: 0; }
        .shadow-xl, .shadow-2xl { shadow: none; }
        .rounded-\[2\.5rem\] { border-radius: 0; }
        .custom-scrollbar { overflow: visible !important; }
        table { font-size: 10px; }
        .sticky { position: static !important; }
    }
</style>

<script>
    function resetCompletion() {
        if(confirm('Are you sure you want to reset all completed quantities to 0?')) {
            const inputs = document.querySelectorAll('.completion-input');
            inputs.forEach(input => {
                input.value = 0;
            });
        }
    }

    // Initialize Sortable
    document.addEventListener('DOMContentLoaded', function() {
        const el = document.getElementById('sortableBranches');
        if (el) {
            new Sortable(el, {
                animation: 150,
                ghostClass: 'bg-indigo-50'
            });
        }
    });

    async function saveNewOrder() {
        const list = document.getElementById('sortableBranches');
        const items = list.querySelectorAll('li');
        const order = Array.from(items).map(item => item.dataset.id);

        try {
            const response = await fetch("{{ route('settings.branches.reorder') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ order })
            });

            const data = await response.json();
            if (data.success) {
                window.location.reload();
            } else {
                alert('Error updating order');
            }
        } catch (e) {
            alert('System error. Please try again.');
        }
    }
</script>
@endsection
