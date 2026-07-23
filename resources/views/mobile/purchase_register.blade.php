@extends('layouts.mobile')

@section('content')
<div x-data="mobilePurchaseRegisterApp()" x-init="init()">

    {{-- Page Header --}}
    <div class="mb-6 animate-in fade-in slide-in-from-top duration-500">
        <div class="flex items-center justify-between gap-3 mb-1">
            <div class="flex items-center gap-3">
                <a href="{{ route('mobile.dashboard') }}"
                   class="w-9 h-9 rounded-xl bg-white/60 border border-white flex items-center justify-center text-slate-600 active:scale-90 transition-transform">
                    <i class="fas fa-chevron-left text-sm"></i>
                </a>
                <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-orange-500 to-amber-600 flex items-center justify-center shadow-lg shadow-orange-200">
                    <i class="fas fa-receipt text-white text-base"></i>
                </div>
                <div>
                    <h2 class="text-xl font-900 text-slate-800 tracking-tighter leading-none">Purchase Register</h2>
                    <p class="text-[9px] font-black uppercase tracking-widest text-slate-400 mt-0.5">ERP Purchase Log</p>
                </div>
            </div>

            @if(Auth::user()->hasPermission('mobile_costing_purchase', 'sync') || Auth::user()->hasPermission('costing_purchase', 'view'))
            <button @click="syncRegister()" :disabled="syncing"
                    class="px-3 py-2 rounded-2xl bg-orange-500 text-white flex items-center gap-2 shadow-md shadow-orange-200 active:scale-90 transition-transform text-xs font-black uppercase">
                <i class="fas fa-arrows-rotate" :class="syncing ? 'fa-spin' : ''"></i>
                <span x-text="syncing ? 'Syncing...' : 'Sync'"></span>
            </button>
            @endif
        </div>
    </div>

    {{-- KPI Summary Header --}}
    <div class="grid grid-cols-2 gap-3 mb-4 animate-in fade-in slide-in-from-bottom duration-500 delay-100">
        <div class="bg-white/80 backdrop-blur-xl border border-white p-4 rounded-2xl shadow-sm">
            <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Total Vouchers</div>
            <div class="text-xl font-900 text-slate-800 tracking-tight mt-0.5">{{ number_format($totalBills) }}</div>
        </div>
        <div class="bg-white/80 backdrop-blur-xl border border-white p-4 rounded-2xl shadow-sm">
            <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Total Amount</div>
            <div class="text-xl font-900 text-orange-600 tracking-tight mt-0.5">₹ {{ number_format($totalAmount, 2) }}</div>
        </div>
    </div>

    {{-- Search & Filters --}}
    <div class="mb-4 animate-in fade-in slide-in-from-bottom duration-500 delay-200">
        <form method="GET" action="{{ route('mobile.costing.purchase') }}" class="space-y-2">
            <div class="relative">
                <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Search item, supplier, or bill no..."
                       class="w-full pl-11 pr-10 py-3.5 bg-white/70 backdrop-blur-xl border border-white/80 rounded-2xl text-sm font-bold text-slate-800 placeholder:text-slate-400 outline-none focus:ring-2 focus:ring-orange-400 focus:border-orange-400 transition-all shadow-sm">
                @if(request('search'))
                <a href="{{ route('mobile.costing.purchase') }}" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                    <i class="fas fa-times text-sm"></i>
                </a>
                @endif
            </div>

            <div class="grid grid-cols-2 gap-2">
                <input type="date" name="from_date" value="{{ request('from_date') }}"
                       class="py-2.5 px-3 bg-white/70 border border-white/80 rounded-xl text-xs font-bold text-slate-700 outline-none">
                <input type="date" name="to_date" value="{{ request('to_date') }}"
                       class="py-2.5 px-3 bg-white/70 border border-white/80 rounded-xl text-xs font-bold text-slate-700 outline-none">
            </div>
            <button type="submit" class="w-full py-2.5 bg-slate-800 text-white rounded-xl font-black text-xs uppercase tracking-wider shadow-sm">
                Apply Date Filters
            </button>
        </form>
    </div>

    {{-- Purchases Card List --}}
    <div class="space-y-3 mb-6">
        @forelse($purchases as $item)
        <div class="bg-white/80 backdrop-blur-xl border border-white rounded-2xl p-4 shadow-sm space-y-2">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <div class="font-900 text-slate-800 text-xs leading-tight">
                        {{ $item->supplier_name ?: 'Direct Purchase' }}
                    </div>
                    <div class="text-[9px] font-bold text-slate-400 mt-0.5">
                        Bill #{{ $item->vouch_no ?? '—' }} | {{ $item->vouch_date ? \Carbon\Carbon::parse($item->vouch_date)->format('d M, Y') : '—' }}
                    </div>
                </div>
                <div class="text-right shrink-0">
                    <div class="font-900 text-orange-600 text-sm">
                        ₹ {{ number_format($item->qty * $item->case_rate, 2) }}
                    </div>
                    <div class="text-[8px] font-bold text-slate-400">
                        @ ₹ {{ number_format($item->case_rate, 2) }}/unit
                    </div>
                </div>
            </div>

            <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-xs">
                <div class="font-bold text-slate-700 truncate pr-2">
                    {{ $item->item_name }}
                </div>
                <div class="font-black text-slate-800 shrink-0">
                    {{ number_format($item->qty, 2) }}
                </div>
            </div>
        </div>
        @empty
        <div class="text-center py-12 text-slate-400 font-bold">
            <i class="fas fa-receipt text-3xl mb-2 opacity-40"></i>
            <div>No purchase records found.</div>
        </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if($purchases->hasPages())
    <div class="mb-8">
        {{ $purchases->links() }}
    </div>
    @endif

</div>

<script>
function mobilePurchaseRegisterApp() {
    return {
        syncing: false,

        init() {},

        async syncRegister() {
            this.syncing = true;
            try {
                const resp = await fetch('{{ route('mobile.costing.purchase.sync') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    }
                });
                const data = await resp.json();
                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert(data.message || 'Sync failed.');
                }
            } catch(e) {
                alert('Network error during sync.');
            } finally {
                this.syncing = false;
            }
        }
    };
}
</script>
@endsection
