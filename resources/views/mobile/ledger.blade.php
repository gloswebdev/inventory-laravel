@extends('layouts.mobile')

@section('content')
<div class="space-y-8 pb-10" x-data="ledgerApp()">
    <!-- Header Block -->
    <div class="flex items-center justify-between bg-white/50 backdrop-blur-2xl p-6 rounded-[2.5rem] border border-white/70 shadow-xl shadow-indigo-100/20 relative overflow-hidden mb-2">
        <div class="absolute -right-10 -top-10 w-40 h-40 bg-indigo-500/10 rounded-full blur-3xl"></div>
        <div class="relative z-10 flex items-center justify-between w-full">
        <div>
            <h2 class="text-3xl font-900 text-slate-800 font-900 tracking-tighter">Ledger</h2>
            <p class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400 mt-1">Movement Analytics</p>
        </div>
        <button @click="showFilters = !showFilters" :class="showFilters ? 'bg-indigo-50 text-indigo-500' : 'bg-white text-slate-400'" class="w-12 h-12 rounded-2xl flex items-center justify-center border border-white/60 shadow-md transition-all active:scale-90">
            <i class="fas fa-search-plus text-xs"></i>
        </button>
    </div>
</div>

    <!-- Quick Search / Filters -->
    <div x-show="showFilters" x-cloak x-transition class="bg-white/70 backdrop-blur-xl border border-white/60 shadow-lg shadow-indigo-100/30 hover:shadow-xl transition-all p-6 rounded-[2.5rem] border border-white/80 space-y-6">
        <div class="space-y-2">
            <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-3">Product</label>
            <select x-model="filters.product_id" @change="applyFilters" class="w-full bg-white/60 backdrop-blur-md border-none rounded-2xl py-4 px-6 text-xs font-bold text-slate-700">
                <option value="">Full History</option>
                @foreach($products as $product)
                <option value="{{ $product->id }}">{{ $product->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div class="space-y-2">
                <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-3">From</label>
                <input type="date" x-model="filters.from_date" @change="applyFilters" class="w-full bg-white/60 backdrop-blur-md border-none rounded-2xl py-4 px-5 text-[10px] font-black text-slate-700">
            </div>
            <div class="space-y-2">
                <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-3">To</label>
                <input type="date" x-model="filters.to_date" @change="applyFilters" class="w-full bg-white/60 backdrop-blur-md border-none rounded-2xl py-4 px-5 text-[10px] font-black text-slate-700">
            </div>
        </div>
    </div>

    <!-- Movement Logs -->
    <div class="space-y-4">
        @foreach($ledger as $item)
        @php
            $typeConfig = [
                'production_add' => ['icon' => 'fa-industry', 'color' => 'text-emerald-500', 'bg' => 'bg-emerald-50', 'label' => 'Production Yield'],
                'production_deduct' => ['icon' => 'fa-vial', 'color' => 'text-rose-500', 'bg' => 'bg-rose-50', 'label' => 'Recipe Consumption'],
                'adjustment_add' => ['icon' => 'fa-circle-plus', 'color' => 'text-indigo-500', 'bg' => 'bg-indigo-50', 'label' => 'Internal Addition'],
                'adjustment_deduct' => ['icon' => 'fa-circle-minus', 'color' => 'text-amber-500', 'bg' => 'bg-amber-50', 'label' => 'Inventory Correction'],
            ][$item->transaction_type] ?? ['icon' => 'fa-arrow-right-arrow-left', 'color' => 'text-slate-500', 'bg' => 'bg-white/60 backdrop-blur-md', 'label' => 'Transaction'];
        @endphp
        
        <div class="bg-white/70 backdrop-blur-xl border border-white/60 shadow-lg shadow-indigo-100/30 hover:shadow-xl transition-all p-5 rounded-[2.2rem] border border-white/80 flex items-center justify-between group active:scale-[0.98] transition-all relative overflow-hidden">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 {{ $typeConfig['bg'] }} {{ $typeConfig['color'] }} rounded-2xl flex items-center justify-center border border-white/50 shadow-md">
                    <i class="fas {{ $typeConfig['icon'] }} text-xs"></i>
                </div>
                <div class="overflow-hidden">
                    <div class="text-[11px] font-900 text-slate-800 font-900 tracking-tighter truncate max-w-[140px] uppercase italic">
                        {{ $item->product ? $item->product->name : 'Unknown' }}
                    </div>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="text-[7px] font-black uppercase tracking-widest {{ $typeConfig['color'] }}">{{ $typeConfig['label'] }}</span>
                        <div class="w-1 h-1 bg-slate-200 rounded-full"></div>
                        <span class="text-[8px] text-slate-400 font-bold uppercase">{{ $item->created_at->diffForHumans() }}</span>
                    </div>
                </div>
            </div>
            
            <div class="text-right">
                <div class="text-xs font-900 {{ $item->change_quantity > 0 ? 'text-emerald-500' : 'text-rose-500' }} tracking-tighter">
                    {{ $item->change_quantity > 0 ? '+' : '' }}{{ number_format($item->change_quantity, 2) }}
                </div>
                <div class="text-[7px] font-black text-slate-400 uppercase tracking-widest mt-0.5">Final: {{ number_format($item->new_stock, 2) }}</div>
            </div>
        </div>
        @endforeach

        @if(count($ledger) === 0)
        <div class="py-20 text-center opacity-40">
            <div class="w-20 h-20 bg-white/60 backdrop-blur-md rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fas fa-ghost text-slate-200 text-4xl"></i>
            </div>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Zero Historical Movement</p>
        </div>
        @endif
    </div>
</div>

<script>
    function ledgerApp() {
        return {
            showFilters: false,
            filters: {
                product_id: '{{ request('product_id') }}',
                from_date: '{{ request('from_date') }}',
                to_date: '{{ request('to_date') }}'
            },
            applyFilters() {
                const url = new URL(window.location.href);
                Object.keys(this.filters).forEach(key => {
                    if (this.filters[key]) url.searchParams.set(key, this.filters[key]);
                    else url.searchParams.delete(key);
                });
                window.location.href = url.toString();
            }
        }
    }
</script>
@endsection
