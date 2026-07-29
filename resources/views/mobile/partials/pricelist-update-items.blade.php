@forelse($pricelists as $row)
<div class="bg-white/80 backdrop-blur-xl border rounded-3xl p-4 shadow-sm space-y-3 transition-all"
     :class="isEdited('{{ $row->user_code }}') ? 'border-amber-300 ring-2 ring-amber-200/60' : 'border-white'">

    <div class="flex items-start justify-between gap-2">
        <div>
            <div class="font-900 text-slate-800 text-sm leading-tight">{{ $row->item_hd_name ?: '—' }}</div>
            <div class="text-[9.5px] font-bold text-slate-400 mt-0.5">
                Size: <span class="text-slate-700 font-extrabold">{{ $row->size }}</span>{{ $row->size_desc ? ' ('.$row->size_desc.')' : '' }}
            </div>
        </div>
        <span class="font-mono text-xs font-bold text-indigo-600 bg-indigo-50 px-2.5 py-1 rounded-xl border border-indigo-100 shrink-0">
            {{ $row->user_code }}
        </span>
    </div>

    <div class="pt-2 border-t border-slate-100 grid grid-cols-2 gap-2">
        <div class="p-2.5 bg-blue-50/50 rounded-2xl border border-blue-100/60">
            <div class="text-[8px] font-black text-blue-600 uppercase tracking-widest">Current <span x-text="priceList"></span></div>
            <div class="text-sm font-900 text-blue-800" x-text="'₹' + formatMoney(currentRates['{{ $row->user_code }}'][priceList])"></div>
        </div>
        <div class="p-2 bg-amber-50/60 rounded-2xl border border-amber-100">
            <div class="text-[8px] font-black text-amber-700 uppercase tracking-widest mb-1">New Rate</div>
            <input type="number" step="0.01" min="0" placeholder="—" inputmode="decimal"
                   x-model="edits['{{ $row->user_code }}']"
                   class="w-full bg-white border border-amber-200 rounded-xl py-1.5 px-2.5 text-sm text-right font-black text-slate-800 outline-none focus:ring-2 focus:ring-amber-400">
        </div>
    </div>
</div>
@empty
<div class="text-center py-12 text-slate-400 font-bold">
    <i class="fas fa-tags text-3xl mb-2 opacity-40"></i>
    <div>No pricelist items found.</div>
</div>
@endforelse
