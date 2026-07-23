@extends('layouts.mobile')

@section('content')
<div x-data="mobilePricelistApp()" x-init="init()">

    {{-- Page Header --}}
    <div class="mb-5 animate-in fade-in slide-in-from-top duration-500">
        <div class="flex items-center justify-between gap-3 mb-1">
            <div class="flex items-center gap-3">
                <a href="{{ route('mobile.dashboard') }}"
                   class="w-9 h-9 rounded-xl bg-white/60 border border-white flex items-center justify-center text-slate-600 active:scale-90 transition-transform">
                    <i class="fas fa-chevron-left text-sm"></i>
                </a>
                <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center shadow-lg shadow-amber-200">
                    <i class="fas fa-tags text-white text-base"></i>
                </div>
                <div>
                    <h2 class="text-xl font-900 text-slate-800 tracking-tighter leading-none">Pricelist Master</h2>
                    <p class="text-[9px] font-black uppercase tracking-widest text-slate-400 mt-0.5">Synced from Product Master ERP API</p>
                </div>
            </div>

            @if(Auth::user()->hasPermission('mobile_costing_pricelist', 'sync') || Auth::user()->hasPermission('costing_pricelist', 'view'))
            <button @click="syncPrices()" :disabled="syncing"
                    class="px-3 py-2 rounded-2xl bg-amber-500 text-white flex items-center gap-2 shadow-md shadow-amber-200 active:scale-90 transition-transform text-xs font-black uppercase">
                <i class="fas fa-sync" :class="syncing ? 'fa-spin' : ''"></i>
                <span x-text="syncing ? 'Syncing...' : 'ERP Sync'"></span>
            </button>
            @endif
        </div>
    </div>

    {{-- Search & Category Filter --}}
    <div class="mb-4 animate-in fade-in slide-in-from-bottom duration-500 delay-100">
        <form method="GET" action="{{ route('mobile.costing.pricelist') }}" class="space-y-2">
            <div class="relative">
                <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Search name, code, composition..."
                       class="w-full pl-11 pr-10 py-3.5 bg-white/70 backdrop-blur-xl border border-white/80 rounded-2xl text-sm font-bold text-slate-800 placeholder:text-slate-400 outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400 transition-all shadow-sm">
                @if(request('search'))
                <a href="{{ route('mobile.costing.pricelist') }}" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                    <i class="fas fa-times text-sm"></i>
                </a>
                @endif
            </div>

            <div class="flex gap-2">
                <select name="group1" onchange="this.form.submit()"
                        class="flex-1 py-3 px-4 bg-white/70 border border-white/80 rounded-2xl text-xs font-bold text-slate-800 outline-none focus:ring-2 focus:ring-amber-400 cursor-pointer">
                    <option value="">All Categories (Group 1)</option>
                    @foreach($group1List as $grp)
                    <option value="{{ $grp }}" {{ request('group1') === $grp ? 'selected' : '' }}>{{ $grp }}</option>
                    @endforeach
                </select>

                @if(request()->anyFilled(['search', 'group1']))
                <a href="{{ route('mobile.costing.pricelist') }}" class="px-4 py-3 bg-rose-50 border border-rose-200 text-rose-600 rounded-2xl text-xs font-black uppercase flex items-center justify-center">
                    Clear
                </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Pricelist Items Cards --}}
    <div class="space-y-3 mb-6">
        @forelse($pricelists as $row)
        <div class="bg-white/80 backdrop-blur-xl border border-white rounded-3xl p-4 shadow-sm space-y-3 transition-all">
            
            {{-- Item Header & Code --}}
            <div class="flex items-start justify-between gap-2">
                <div>
                    <div class="font-900 text-slate-800 text-sm leading-tight">
                        {{ $row->item_hd_name ?: '—' }}
                    </div>
                    <div class="text-[9.5px] font-bold text-slate-400 mt-0.5">
                        Size: <span class="text-slate-700 font-extrabold">{{ $row->size }}</span> {{ $row->size_desc ? ' ('.$row->size_desc.')' : '' }}
                    </div>
                </div>

                <div class="text-right shrink-0">
                    <span class="font-mono text-xs font-bold text-indigo-600 bg-indigo-50 px-2.5 py-1 rounded-xl border border-indigo-100">
                        {{ $row->user_code }}
                    </span>
                </div>
            </div>

            {{-- Category & Technical Name --}}
            <div class="flex items-center gap-2 flex-wrap text-[10px] font-bold">
                @if($row->group1)
                <span class="px-2 py-0.5 rounded-lg bg-amber-50 text-amber-700 border border-amber-100 uppercase tracking-wider">
                    {{ $row->group1 }}
                </span>
                @endif
                @if($row->group3)
                <span class="px-2 py-0.5 rounded-lg bg-slate-100 text-slate-600">
                    {{ $row->group3 }}
                </span>
                @endif
            </div>

            {{-- Branch Rates Grid --}}
            <div class="pt-2 border-t border-slate-100 space-y-1.5">
                <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Branch Rates & Taxes</div>
                <div class="grid grid-cols-3 gap-1.5 text-center">
                    <div class="p-2 bg-blue-50/50 rounded-xl border border-blue-100/60">
                        <div class="text-[8px] font-black text-blue-600 uppercase">Factory</div>
                        <div class="text-xs font-900 text-blue-800">₹{{ number_format($row->sp_rate1, 2) }}</div>
                    </div>
                    <div class="p-2 bg-emerald-50/50 rounded-xl border border-emerald-100/60">
                        <div class="text-[8px] font-black text-emerald-600 uppercase">Indore</div>
                        <div class="text-xs font-900 text-emerald-800">₹{{ number_format($row->sp_rate2, 2) }}</div>
                    </div>
                    <div class="p-2 bg-purple-50/50 rounded-xl border border-purple-100/60">
                        <div class="text-[8px] font-black text-purple-600 uppercase">Pune</div>
                        <div class="text-xs font-900 text-purple-800">₹{{ number_format($row->sp_rate3, 2) }}</div>
                    </div>
                    <div class="p-2 bg-orange-50/50 rounded-xl border border-orange-100/60">
                        <div class="text-[8px] font-black text-orange-600 uppercase">Akola</div>
                        <div class="text-xs font-900 text-orange-800">₹{{ number_format($row->sp_rate4, 2) }}</div>
                    </div>
                    <div class="p-2 bg-pink-50/50 rounded-xl border border-pink-100/60">
                        <div class="text-[8px] font-black text-pink-600 uppercase">Ghaziabad</div>
                        <div class="text-xs font-900 text-pink-800">₹{{ number_format($row->sp_rate5, 2) }}</div>
                    </div>
                    <div class="p-2 bg-slate-50 rounded-xl border border-slate-100">
                        <div class="text-[8px] font-black text-slate-500 uppercase">Tax / Pack</div>
                        <div class="text-[10px] font-900 text-slate-700">
                            {{ $row->gst_tax ?: 'N/A' }}
                            @if($row->cf_1)
                            | CF1: {{ number_format($row->cf_1, 1) }}
                            @endif
                        </div>
                    </div>
                </div>
            </div>

        </div>
        @empty
        <div class="text-center py-12 text-slate-400 font-bold">
            <i class="fas fa-tags text-3xl mb-2 opacity-40"></i>
            <div>No pricelist items found.</div>
        </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if($pricelists->hasPages())
    <div class="mb-8">
        {{ $pricelists->links() }}
    </div>
    @endif

</div>

<script>
function mobilePricelistApp() {
    return {
        syncing: false,

        init() {},

        async syncPrices() {
            if (!confirm('Sync pricelist data from Product Master ERP API? This may take some time.')) return;
            this.syncing = true;
            try {
                const resp = await fetch('{{ route('mobile.costing.pricelist.sync') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json'
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
                alert('Error syncing pricelist.');
            } finally {
                this.syncing = false;
            }
        }
    };
}
</script>
@endsection
