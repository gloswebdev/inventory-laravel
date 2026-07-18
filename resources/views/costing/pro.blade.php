@extends('layouts.app')

@section('header', 'Costing Pro')

@section('content')
<style>
    /* Premium visual effects */
    .pro-glass {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid rgba(249, 115, 22, 0.08);
    }
    .text-gradient {
        background: linear-gradient(135deg, #f97316 0%, #d97706 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    .glow-dot {
        box-shadow: 0 0 8px #f97316;
    }
</style>

<div class="space-y-6">

    {{-- ══ Header Section ══ --}}
    <div class="flex flex-wrap items-center justify-between gap-4 mb-2">
        <div>
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-orange-500 glow-dot animate-pulse"></span>
                <span class="text-xs font-black text-orange-600 uppercase tracking-widest">Premium Module</span>
            </div>
            <h1 class="text-3xl font-black text-slate-800 tracking-tight mt-1 flex items-center gap-2">
                Costing <span class="text-gradient">Pro</span>
            </h1>
            <p class="text-slate-500 text-sm font-medium mt-0.5">Advanced forecasting, optimization models, and API-driven yield tracking</p>
        </div>
        <div>
            @if($apiSuccess)
            <div class="px-4 py-2 bg-emerald-500 text-white font-black text-xs uppercase tracking-widest rounded-xl shadow-md shadow-emerald-200 flex items-center gap-1.5">
                <i class="fas fa-check-circle animate-pulse"></i> ERP API Connected
            </div>
            @else
            <div class="px-4 py-2 bg-amber-500 text-white font-black text-xs uppercase tracking-widest rounded-xl shadow-md shadow-amber-200 flex items-center gap-1.5">
                <i class="fas fa-exclamation-triangle"></i> Local Cache Mode
            </div>
            @endif
        </div>
    </div>

    {{-- ══ BOM Purity Dashboard ══ --}}
    <div class="pro-glass rounded-3xl overflow-hidden shadow-sm border border-slate-100">
        <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
            <div>
                <h2 class="font-extrabold text-slate-800 text-base">BOM Purity Dashboard</h2>
                <p class="text-xs text-slate-400 font-bold mt-0.5">Showing all active Bill of Materials with their latest purity rates fetched from ERP</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-50/70 border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-6 text-[10px] font-black text-slate-400 uppercase tracking-widest">Product Name</th>
                        <th class="py-3 px-6 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Purity</th>
                        <th class="py-3 px-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Item Code</th>
                        <th class="py-3 px-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Batch Qty</th>
                        <th class="py-3 px-6 text-[10px] font-black text-slate-400 uppercase tracking-widest">Latest Purchase Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($processedBoms as $item)
                    <tr class="hover:bg-orange-50/10 transition-colors">
                        <td class="py-4 px-6">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-lg bg-orange-50 border border-orange-100 flex items-center justify-center text-orange-600 flex-shrink-0">
                                    <i class="fas fa-box text-sm"></i>
                                </div>
                                <div>
                                    <div class="font-bold text-slate-800 text-sm flex items-center gap-2">
                                        {{ $item['product_name'] }}
                                        @if($item['badge'])
                                        <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-wider {{ $item['badge'] === 'small' ? 'bg-orange-500 text-white' : 'bg-purple-600 text-white' }}">
                                            {{ strtoupper($item['badge']) }}
                                        </span>
                                        @endif
                                    </div>
                                    <div class="text-[10px] text-slate-400 font-medium mt-0.5">{{ $item['pack_name'] }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="py-4 px-6 text-center">
                            @if($item['purity'] !== '—')
                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-black bg-emerald-50 text-emerald-700 border border-emerald-100 shadow-sm">
                                <i class="fas fa-percent text-[10px] text-emerald-500"></i> {{ $item['purity'] }}
                            </span>
                            @else
                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-black bg-slate-50 text-slate-400 border border-slate-100">
                                —
                            </span>
                            @endif
                        </td>
                        <td class="py-4 px-4">
                            <span class="font-mono text-xs font-bold text-indigo-600 bg-indigo-50/50 px-2.5 py-1 rounded-lg border border-indigo-100/30">
                                {{ $item['item_code'] }}
                            </span>
                        </td>
                        <td class="py-4 px-4 text-xs font-semibold text-slate-600">
                            {{ $item['yield_qty'] }} {{ $item['yield_uom'] }}
                        </td>
                        <td class="py-4 px-6">
                            @if($item['purchase_date'] !== '—')
                            <div class="flex items-center gap-1.5 text-xs font-bold text-slate-600">
                                <i class="far fa-calendar-alt text-slate-400"></i>
                                {{ $item['purchase_date'] }}
                            </div>
                            @else
                            <span class="text-xs text-slate-400 font-medium">—</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="py-16 text-center">
                            <div class="w-16 h-16 rounded-2xl bg-orange-50 border border-orange-100 flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-cubes-stacked text-2xl text-orange-300"></i>
                            </div>
                            <p class="text-slate-500 font-bold mb-2">No costing BOMs found.</p>
                            <a href="{{ route('costing.boms.index') }}" class="px-5 py-2.5 bg-orange-500 hover:bg-orange-600 text-white font-black rounded-xl text-sm shadow inline-flex items-center gap-2">
                                <i class="fas fa-plus"></i> Create Costing BOM
                            </a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
