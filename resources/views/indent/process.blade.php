@extends('layouts.app')

@section('content')
<div x-data="{ showCompletionModal: false, showReorderModal: false }" class="min-h-screen bg-[#f8fafc] py-8">
    <div class="max-w-[95%] mx-auto">
        <!-- Header Card -->
        <!-- Header Card -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-100/80 p-8 mb-8 overflow-hidden relative flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="flex items-center gap-4 relative z-10">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-lg shadow-indigo-200/50 flex-shrink-0">
                    <i class="fas fa-cog fa-spin text-white text-xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-black text-slate-800 tracking-tight leading-tight">Indent Process Manager</h1>
                    <div class="flex items-center gap-3 mt-1.5 flex-wrap">
                        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Cross-Branch Stock Distribution Grid</p>
                        @if($indent->status == 'completed')
                        <span class="bg-emerald-50 text-emerald-600 border border-emerald-200 px-2.5 py-0.5 rounded-lg text-[10px] font-black uppercase tracking-widest">Fully Completed</span>
                        @elseif($indent->status == 'partly completed')
                        <span class="bg-blue-50 text-blue-600 border border-blue-200 px-2.5 py-0.5 rounded-lg text-[10px] font-black uppercase tracking-widest">Partly Completed</span>
                        @else
                        <span class="bg-amber-50 text-amber-600 border border-amber-200 px-2.5 py-0.5 rounded-lg text-[10px] font-black uppercase tracking-widest">Pending</span>
                        @endif

                        @if(Auth::user()->hasFeature('indent', 'branch_reorder'))
                        <button type="button" @click="showReorderModal = true" class="bg-slate-50 hover:bg-slate-100 text-slate-600 px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-widest border border-slate-200 transition-all flex items-center gap-2 shadow-sm ml-2">
                            <i class="fas fa-sort"></i> Reorder Columns
                        </button>
                        @endif
                    </div>
                </div>
            </div>

            <div class="bg-slate-50/50 p-5 rounded-2xl border border-slate-100 flex items-center gap-6 relative z-10 shadow-inner flex-wrap justify-center">
                <div class="text-center px-2">
                    <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Indent ID</div>
                    <div class="text-lg font-black text-indigo-600 tracking-tight">#IND-{{ $indent->id }}</div>
                </div>
                <div class="w-px h-10 bg-slate-200 hidden sm:block"></div>
                <div class="text-center px-2">
                    <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Target Branch</div>
                    <div class="text-lg font-bold text-slate-700">{{ $indent->branch_name }}</div>
                </div>
                <div class="w-px h-10 bg-slate-200 hidden sm:block"></div>
                <div class="text-center px-2">
                    <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Created By</div>
                    <div class="text-lg font-bold text-slate-700">{{ $indent->user->name ?? 'System' }}</div>
                </div>
                <div class="w-px h-10 bg-slate-200 hidden sm:block"></div>
                <div class="text-center px-2">
                    <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Indent Date</div>
                    <div class="text-lg font-bold text-slate-700">{{ date('d M, Y', strtotime($indent->indent_date)) }}</div>
                </div>
            </div>
        </div>

        <!-- Processing Grid Card -->
        <!-- Processing Grid Card -->
        <div class="bg-white rounded-3xl shadow-sm overflow-hidden border border-slate-100/80 mb-32">
            <div class="overflow-x-auto custom-scrollbar pb-4">
                <table class="w-full text-left border-collapse relative">
                    <thead class="sticky top-0 z-20 bg-slate-50 shadow-sm before:content-[''] before:absolute before:bottom-0 before:left-0 before:w-full before:h-px before:bg-slate-200">
                        <tr>
                            <th class="sticky left-0 bg-slate-50 px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest z-30 border-r border-slate-200 border-b min-w-[250px]">Product Details</th>
                            <th class="px-6 py-5 text-[10px] font-black text-indigo-600 uppercase tracking-widest text-center bg-indigo-50/50 border-b border-indigo-100 min-w-[120px]">Indent<br>Qty (Boxes)</th>
                            <th class="px-6 py-5 text-[10px] font-black text-emerald-600 uppercase tracking-widest text-center bg-emerald-50/50 border-b border-emerald-100 min-w-[120px]">Stock at<br>Entry (Box)</th>
                            
                            @foreach($branches as $branch)
                            <th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center border-l border-b border-slate-200 min-w-[140px]">
                                {{ $branch->name }}
                                <div class="text-[9px] text-slate-300 font-bold mt-1">CODE: {{ $branch->code }}</div>
                            </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($indent->items as $item)
                        <tr class="hover:bg-slate-50/70 transition-colors group">
                            <td class="sticky left-0 bg-white group-hover:bg-slate-50 px-8 py-5 z-10 border-r border-slate-100 shadow-[2px_0_5px_rgba(0,0,0,0.02)] transition-colors">
                                <div class="font-bold text-slate-800 text-sm group-hover:text-indigo-700 transition-colors">{{ $item->product_name }}</div>
                                <div class="text-[10px] font-semibold text-slate-400 uppercase tracking-widest mt-0.5">{{ $item->product->pack_name ?? 'N/A' }}</div>
                            </td>
                            <td class="px-6 py-5 text-center bg-indigo-50/10 group-hover:bg-indigo-50/30 transition-colors border-r border-slate-50">
                                <span class="text-xl font-black text-indigo-600 tracking-tight">{{ number_format($item->final_qty_box, 0) }}</span>
                            </td>
                            <td class="px-6 py-5 text-center bg-emerald-50/10 group-hover:bg-emerald-50/30 transition-colors">
                                <span class="text-sm font-black text-emerald-600">{{ number_format($item->stock_box, 2) }}</span>
                            </td>

                            @foreach($branches as $branch)
                            @php 
                                $stock = $branchStocks[$item->product_id][$branch->code] ?? 0;
                                $isTarget = $branch->code == $indent->branch_code;
                            @endphp
                            <td class="px-6 py-5 text-center border-l border-slate-100 {{ $isTarget ? 'bg-indigo-50/20 group-hover:bg-indigo-50/40' : '' }} transition-colors relative">
                                <div class="flex flex-col items-center">
                                    <span class="text-sm font-black {{ $stock > 0 ? 'text-slate-800' : 'text-slate-300' }}">
                                        {{ number_format($stock, 1) }}
                                    </span>
                                    <span class="text-[8px] font-black uppercase {{ $stock > 0 ? 'text-slate-400' : 'text-slate-300' }} mt-0.5">Box</span>
                                    
                                    @if($isTarget)
                                    <div class="mt-2 px-2 py-0.5 rounded-md bg-indigo-100 border border-indigo-200 text-[8px] font-black text-indigo-700 uppercase tracking-widest">TARGET</div>
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
        <!-- Float Action Bar -->
        <div class="fixed bottom-6 left-1/2 -translate-x-1/2 bg-white/95 backdrop-blur-xl border border-slate-200 px-6 py-4 rounded-full shadow-[0_8px_30px_rgb(0,0,0,0.12)] flex items-center gap-5 z-40 w-max max-w-[95vw] overflow-x-auto custom-scrollbar">
            <div class="flex flex-col pr-2">
                <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Total Indented</span>
                <span class="text-2xl font-black tracking-tight text-indigo-600 leading-none">{{ number_format($indent->total_boxes, 0) }} <span class="text-[9px] uppercase font-bold text-indigo-400">Boxes</span></span>
            </div>
            <div class="w-px h-8 bg-slate-200 flex-shrink-0"></div>
            
            <a href="{{ route('indent.process.excel', $indent->id) }}" class="bg-emerald-50 text-emerald-600 border border-emerald-200 hover:bg-emerald-100 px-5 py-2.5 rounded-full font-bold transition shadow-sm flex items-center gap-2 text-[11px] uppercase tracking-wider flex-shrink-0">
                <i class="fas fa-file-excel"></i> Excel
            </a>
            <a href="{{ route('indent.process.pdf', $indent->id) }}" class="bg-red-50 text-red-600 border border-red-200 hover:bg-red-100 px-5 py-2.5 rounded-full font-bold transition shadow-sm flex items-center gap-2 text-[11px] uppercase tracking-wider flex-shrink-0">
                <i class="fas fa-file-pdf"></i> PDF
            </a>
            <button onclick="window.print()" class="bg-indigo-50 text-indigo-600 border border-indigo-200 hover:bg-indigo-100 px-5 py-2.5 rounded-full font-bold transition shadow-sm flex items-center gap-2 text-[11px] uppercase tracking-wider flex-shrink-0">
                <i class="fas fa-print"></i> Print
            </button>
            
            <div class="w-px h-8 bg-slate-200 flex-shrink-0"></div>
            
            @if($indent->status != 'completed')
            <button @click="showCompletionModal = true" class="bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white px-6 py-2.5 rounded-full font-bold transition shadow-md shadow-amber-200/50 flex items-center gap-2 text-[11px] uppercase tracking-wider flex-shrink-0">
                <i class="fas fa-check-double text-sm"></i> Update Completion
            </button>
            @else
            <button @click="showCompletionModal = true" class="bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-600 hover:to-teal-600 text-white px-6 py-2.5 rounded-full font-bold transition shadow-md shadow-emerald-200/50 flex items-center gap-2 text-[11px] uppercase tracking-wider flex-shrink-0">
                <i class="fas fa-edit text-sm"></i> Edit Completion
            </button>
            @endif

            <a href="{{ route('indent.index') }}" class="text-slate-400 font-bold hover:text-indigo-600 transition p-2 ml-1 flex items-center gap-1 text-[11px] uppercase tracking-wider flex-shrink-0 hover:bg-slate-50 rounded-full">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    {{-- Completion Modal --}}
    <div x-show="showCompletionModal" 
         class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-4"
         style="display: none;">
        
        <div class="bg-white w-full max-w-3xl rounded-3xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]"
             @click.away="showCompletionModal = false">
            <div class="p-8 border-b border-slate-100 relative bg-slate-50">
                <h2 class="text-xl font-black tracking-tight text-slate-800">Update Indent Completion</h2>
                <p class="text-slate-400 font-bold text-[10px] uppercase tracking-widest mt-1">Enter actual volumes completed for each product</p>
                <button @click="showCompletionModal = false" class="absolute top-8 right-8 text-slate-400 hover:text-slate-600 transition bg-white p-2 rounded-full shadow-sm border border-slate-200">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>

            <form action="{{ route('indent.complete', $indent->id) }}" method="POST" class="flex flex-col overflow-hidden">
                @csrf
                <div class="overflow-y-auto p-8 custom-scrollbar">
                    <table class="w-full">
                        <thead class="sticky top-0 bg-white z-10 before:content-[''] before:absolute before:bottom-0 before:left-0 before:w-full before:h-px before:bg-slate-200">
                            <tr>
                                <th class="text-left text-[10px] font-black text-slate-400 uppercase tracking-widest pb-3 border-b border-slate-200 bg-white">Product</th>
                                <th class="text-center text-[10px] font-black text-slate-400 uppercase tracking-widest pb-3 border-b border-slate-200 bg-white">Asked (Box)</th>
                                <th class="text-right text-[10px] font-black text-slate-400 uppercase tracking-widest pb-3 border-b border-slate-200 bg-white">Completed (Box)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($indent->items as $item)
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="py-4">
                                    <div class="font-bold text-slate-800 text-sm">{{ $item->product_name }}</div>
                                    <div class="text-[10px] text-slate-400 font-semibold uppercase tracking-widest mt-0.5">{{ $item->product->pack_name ?? '' }}</div>
                                </td>
                                <td class="py-4 text-center font-black text-indigo-600 text-lg">
                                    {{ number_format($item->final_qty_box, 0) }}
                                </td>
                                <td class="py-4 text-right">
                                    <input type="number" step="1" name="completed_qty[{{ $item->id }}]" 
                                           value="{{ (int)$item->completed_qty }}"
                                           class="completion-input w-32 bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-right font-black text-slate-800 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all outline-none">
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="p-6 border-t border-slate-100 bg-slate-50 flex gap-4">
                    <button type="submit" class="flex-1 bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700 text-white py-3.5 rounded-xl font-bold transition shadow-lg shadow-indigo-200/50 uppercase tracking-wider text-sm">
                        Save Status
                    </button>
                    <button type="button" onclick="resetCompletion()" class="px-6 py-3.5 bg-red-50 text-red-500 border border-red-200 rounded-xl font-bold hover:bg-red-100 transition uppercase tracking-wider text-sm shadow-sm">
                        Reset
                    </button>
                    <button type="button" @click="showCompletionModal = false" class="px-6 py-3.5 bg-white text-slate-600 border border-slate-200 rounded-xl font-bold hover:bg-slate-50 transition uppercase tracking-wider text-sm shadow-sm">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Reorder Branches Modal --}}
    <div x-show="showReorderModal" 
         class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-4"
         style="display: none;">
        
        <div class="bg-white w-full max-w-md rounded-3xl shadow-2xl overflow-hidden"
             @click.away="showReorderModal = false">
            <div class="p-8 border-b border-slate-100 relative bg-slate-50">
                <h2 class="text-xl font-black tracking-tight text-slate-800">Set Column Order</h2>
                <p class="text-slate-400 font-bold text-[10px] uppercase tracking-widest mt-1">Drag branches to rearrange grid columns</p>
                <button @click="showReorderModal = false" class="absolute top-8 right-8 text-slate-400 hover:text-slate-600 transition bg-white p-2 rounded-full shadow-sm border border-slate-200">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>

            <div class="p-8">
                <ul id="sortableBranches" class="space-y-3">
                    @foreach($branches as $branch)
                    <li data-id="{{ $branch->id }}" class="flex items-center gap-4 p-4 bg-white rounded-xl border border-slate-200 shadow-sm cursor-move hover:border-indigo-300 hover:shadow-md transition-all group">
                        <i class="fas fa-grip-lines text-slate-300 group-hover:text-indigo-400 transition-colors"></i>
                        <span class="text-sm font-bold text-slate-700">{{ $branch->name }}</span>
                        <span class="ml-auto text-[10px] font-black text-slate-400 uppercase tracking-widest bg-slate-50 px-2 py-1 rounded-md">{{ $branch->code }}</span>
                    </li>
                    @endforeach
                </ul>

                <div class="mt-8 flex gap-4">
                    <button type="button" onclick="saveNewOrder()" class="flex-1 bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700 text-white py-3.5 rounded-xl font-bold transition shadow-lg shadow-indigo-200/50 uppercase tracking-wider text-sm">
                        Confirm Arrangement
                    </button>
                    <button type="button" @click="showReorderModal = false" class="px-6 py-3.5 bg-white border border-slate-200 text-slate-600 rounded-xl font-bold hover:bg-slate-50 transition uppercase tracking-wider text-sm shadow-sm">
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
