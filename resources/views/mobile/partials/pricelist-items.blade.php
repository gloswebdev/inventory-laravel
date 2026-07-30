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
