@extends('layouts.mobile')

@section('content')
<div class="space-y-8 pb-10" x-data="processApp()">
    <!-- Header Area -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-3xl font-900 text-slate-800 tracking-tighter uppercase italic">Dispatch</h2>
            <p class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400 mt-1">
                Ref: #IND-{{ $indent->id }} • {{ $indent->branch_name }}
            </p>
            <p class="text-[8px] font-black text-indigo-500 uppercase tracking-widest mt-1">
                <i class="fas fa-user-circle mr-1"></i> BY: {{ $indent->user->name ?? 'SYSTEM' }}
            </p>
        </div>
        <div class="flex flex-col items-end gap-3">
            <div class="flex items-center gap-2">
                <a href="javascript:window.print()" class="w-10 h-10 bg-blue-50 rounded-2xl flex items-center justify-center text-blue-600 border border-blue-100 shadow-sm transition-all active:scale-90 hover:grad-blue hover:text-white">
                    <i class="fas fa-print text-xs"></i>
                </a>
                <a href="{{ route('mobile.indents.process.excel', $indent->id) }}" class="w-10 h-10 bg-emerald-50 rounded-2xl flex items-center justify-center text-emerald-600 border border-emerald-100 shadow-sm transition-all active:scale-90 hover:grad-emerald hover:text-white">
                    <i class="fas fa-file-excel text-xs"></i>
                </a>
                <a href="{{ route('mobile.indents.process.pdf', $indent->id) }}" class="w-10 h-10 bg-rose-50 rounded-2xl flex items-center justify-center text-rose-600 border border-rose-100 shadow-sm transition-all active:scale-90 hover:grad-rose hover:text-white">
                    <i class="fas fa-file-pdf text-xs"></i>
                </a>
            </div>
            <div class="flex flex-col items-end">
                @php
                    $statusColor = [
                        'completed' => 'text-emerald-500 bg-emerald-50 border-emerald-100',
                        'partly completed' => 'text-indigo-500 bg-indigo-50 border-indigo-100',
                        'pending' => 'text-amber-500 bg-amber-50 border-amber-100',
                    ][strtolower($indent->status)] ?? 'text-amber-500 bg-amber-50 border-amber-100';
                @endphp
                <span class="px-2 py-0.5 {{ $statusColor }} border rounded text-[8px] font-black uppercase tracking-widest shadow-sm">{{ $indent->status ?: 'PENDING' }}</span>
                <span class="text-[10px] font-black text-slate-800 tracking-tighter uppercase mt-1 italic">{{ date('d M, Y', strtotime($indent->indent_date)) }}</span>
            </div>
        </div>
    </div>

    <!-- Cross-Branch Stock Analytics -->
    <div class="glass-premium overflow-hidden rounded-[2.5rem] border border-white/80">
        <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/30">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl grad-indigo flex items-center justify-center text-white shadow-lg shadow-indigo-100 border border-white/20">
                    <i class="fas fa-chart-column text-[10px]"></i>
                </div>
                <h3 class="text-[10px] font-black text-slate-700 uppercase tracking-[0.1em]">Cross-Branch Availability</h3>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-1.5 h-1.5 bg-indigo-400 rounded-full animate-pulse"></div>
                <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Live Sync</span>
            </div>
        </div>
        
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-indigo-50/20">
                        <th class="px-6 py-4 text-[9px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-50 sticky left-0 bg-white/95 backdrop-blur-md z-10 w-44">Product Profile</th>
                        <th class="px-6 py-4 text-[9px] font-black text-green-500 uppercase tracking-widest border-b border-slate-50 text-center whitespace-nowrap">Stock at<br>Entry</th>
                        <th class="px-6 py-4 text-[9px] font-black text-indigo-400 uppercase tracking-widest border-b border-slate-50 whitespace-nowrap text-center">Indent<br>Box</th>
                        @foreach($branches as $branch)
                        <th class="px-6 py-4 text-[9px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-50 text-center whitespace-nowrap">{{ $branch->code }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($indent->items as $item)
                    <tr class="hover:bg-indigo-50/10 transition-colors">
                        <td class="px-6 py-5 sticky left-0 bg-white/95 backdrop-blur-md z-10 shadow-[8px_0_15px_rgba(0,0,0,0.02)]">
                            <div class="text-[12px] font-900 text-slate-800 leading-tight uppercase tracking-tighter">{{ $item->product ? $item->product->name : 'Unknown' }}</div>
                            <div class="text-[8px] font-black text-slate-400 uppercase tracking-widest mt-1">{{ $item->product ? $item->product->pack_name : '-' }}</div>
                        </td>
                        <td class="px-6 py-5 text-center">
                            <div class="text-[11px] font-black text-green-600 tracking-tighter whitespace-nowrap">
                                {{ number_format($item->stock_box, 0) }}
                                <span class="text-[8px] opacity-60 ml-0.5">BOX</span>
                            </div>
                        </td>
                        <td class="px-6 py-5 text-center">
                            <div class="text-[11px] font-900 text-indigo-600 tracking-tighter whitespace-nowrap">
                                {{ number_format($item->final_qty_box, 0) }}
                                <span class="text-[8px] opacity-60 ml-0.5">BOX</span>
                            </div>
                        </td>
                        @foreach($branches as $branch)
                        @php $stock = $branchStocks[$item->product_id][$branch->code] ?? 0; @endphp
                        <td class="px-6 py-5 text-center">
                            <span class="text-[11px] font-black {{ $stock <= 0 ? 'text-slate-200' : 'text-slate-600' }}">
                                {{ $stock > 0 ? number_format($stock, 0) : '---' }}
                            </span>
                        </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-6 py-3 bg-slate-50/20 flex justify-center items-center gap-2">
            <i class="fas fa-arrows-left-right text-[10px] text-slate-300"></i>
            <span class="text-[8px] font-black text-slate-300 uppercase tracking-[0.2em] italic">Swipe horizontally to view more branches</span>
        </div>
    </div>

    <!-- Completion Update Section -->
    <div class="glass-premium p-8 rounded-[3rem] space-y-8 border border-white/80">
        <div class="flex items-center gap-3 ml-2">
            <i class="fas fa-file-circle-check text-indigo-500 text-xs shadow-glow"></i>
            <h3 class="text-[10px] font-black text-slate-700 uppercase tracking-[0.1em]">Fulfilment Execution</h3>
        </div>
        
        <div class="space-y-5">
            @foreach($indent->items as $item)
            <div class="flex items-center justify-between gap-6 p-6 bg-slate-50/50 rounded-[2rem] border-2 border-slate-100/50 group hover:border-indigo-200 transition-all active:scale-[0.98]">
                <div class="flex-1 min-w-0">
                    <div class="text-[13px] font-900 text-slate-800 truncate uppercase tracking-tighter italic">{{ $item->product ? $item->product->name : 'Product' }}</div>
                    <div class="flex items-center gap-3 mt-2">
                        <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest bg-white px-2 py-0.5 rounded shadow-sm border border-slate-100">Asked: {{ number_format($item->final_qty_box, 0) }}</span>
                    </div>
                </div>
                <div class="w-28 relative">
                    <input 
                        type="number" 
                        x-model="form.completed_qty[{{ $item->id }}]" 
                        class="w-full bg-white border-none rounded-2xl py-3.5 px-5 text-sm font-900 text-slate-800 text-right focus:ring-2 focus:ring-indigo-500 shadow-inner group-hover:shadow-md transition-all"
                        placeholder="0"
                    >
                    <div class="absolute -top-2 -right-1 bg-white border border-slate-100 px-1.5 rounded text-[7px] font-black text-indigo-400 shadow-sm">LOAD</div>
                </div>
            </div>
            @endforeach
        </div>

        <button 
            @click="submitUpdate" 
            :disabled="loading"
            class="w-full grad-indigo p-1 rounded-[2.5rem] shadow-xl shadow-indigo-100 transition-all active:scale-[0.98] group disabled:opacity-50"
        >
            <div class="bg-white/10 p-5 rounded-[2.4rem] flex items-center justify-center gap-4 text-white font-900 italic tracking-tight uppercase text-sm border border-white/20">
                <template x-if="!loading">
                    <div class="flex items-center gap-4">
                        <i class="fas fa-truck-loading group-hover:translate-x-1 transition-transform"></i>
                        <span>Commit Fulfillment</span>
                    </div>
                </template>
                <template x-if="loading">
                    <div class="flex items-center gap-4">
                        <i class="fas fa-compass fa-spin"></i>
                        <span>Syncing Logs...</span>
                    </div>
                </template>
            </div>
        </button>
    </div>

    <!-- Alert Box -->
    <div class="grad-emerald p-0.5 rounded-[2.5rem] opacity-90 shadow-lg shadow-emerald-50">
        <div class="bg-white/95 backdrop-blur-md p-6 rounded-[2.4rem] flex items-start gap-5">
            <div class="w-12 h-12 bg-emerald-50 rounded-2xl flex items-center justify-center text-emerald-600 shrink-0 border border-emerald-50 shadow-inner">
                <i class="fas fa-route text-lg"></i>
            </div>
            <div>
                <h4 class="text-[11px] font-900 text-slate-700 uppercase tracking-tighter italic leading-none">Status Intelligence</h4>
                <p class="text-[10px] text-slate-500 leading-relaxed mt-2 font-bold">Registering fulfillments will dynamically update the indent status to 'Partial' or 'Completed' based on quantity parity.</p>
            </div>
        </div>
    </div>

    <!-- Feedback Toast -->
    <div 
        x-show="toast.show" 
        x-cloak 
        x-transition:enter="transition ease-out duration-500"
        x-transition:enter-start="opacity-0 translate-y-20 scale-90"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-300"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-10 scale-95"
        class="fixed bottom-28 left-6 right-6 z-50 p-1 rounded-[2rem] shadow-2xl grad-emerald"
    >
        <div class="bg-white/95 backdrop-blur-md rounded-[1.9rem] p-6 flex items-center gap-5">
            <div class="w-12 h-12 grad-emerald rounded-2xl flex items-center justify-center text-white shrink-0 shadow-lg">
                <i class="fas fa-check-double"></i>
            </div>
            <div>
                <div class="text-[9px] font-black text-emerald-500 uppercase tracking-widest">Logs Secured</div>
                <p class="text-[13px] font-bold text-slate-800 leading-tight mt-1" x-text="toast.message"></p>
            </div>
        </div>
    </div>
</div>

<script>
    function processApp() {
        return {
            loading: false,
            form: {
                completed_qty: {
                    @foreach($indent->items as $item)
                    "{{ $item->id }}": "{{ $item->completed_qty ?: '' }}",
                    @endforeach
                }
            },
            toast: {
                show: false,
                message: ''
            },
            async submitUpdate() {
                this.loading = true;
                try {
                    const response = await fetch("{{ route('mobile.indents.completion', $indent->id) }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify(this.form)
                    });

                    const data = await response.json();
                    if (data.success) {
                        this.toast.message = data.message;
                        this.toast.show = true;
                        setTimeout(() => this.toast.show = false, 3000);
                        setTimeout(() => window.location.href = "{{ route('mobile.indents') }}", 1500);
                    }
                } catch (e) {
                    alert('System timeout. Please verify connectivity.');
                } finally {
                    this.loading = false;
                }
            }
        }
    }
</script>

<style>
    .custom-scrollbar::-webkit-scrollbar { height: 4px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.05); border-radius: 10px; }
    
    [x-cloak] { display: none !important; }
    
    .shadow-glow { filter: drop-shadow(0 0 5px rgba(99,102,241,0.5)); }
</style>
@endsection
