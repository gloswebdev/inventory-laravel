@extends('layouts.app')

@section('header', 'Pricelist Update')

@section('content')
<style>
    .pro-glass {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid rgba(249, 115, 22, 0.08);
    }
</style>

<div class="space-y-6" x-data="pricelistUpdateApp()">

    {{-- ══ Page Header ══ --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-2">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center shadow-lg shadow-amber-200/50">
                <i class="fas fa-cloud-arrow-up text-white"></i>
            </div>
            <div>
                <h1 class="text-2xl font-black text-slate-800 tracking-tight">Pricelist Update</h1>
                <p class="text-slate-500 text-sm font-medium mt-0.5">Edit selling rates and push them directly to the Algebra ERP</p>
            </div>
        </div>
        <div class="flex gap-2 flex-wrap items-center">
            <button @click="showHistory = true"
                    class="bg-white border border-slate-200 text-slate-700 text-sm font-bold py-2.5 px-4 rounded-xl hover:bg-slate-50 transition-all flex items-center gap-2 shadow-sm">
                <i class="fas fa-clock-rotate-left text-slate-400"></i> Push History
            </button>
            <button @click="pushToErp()" :disabled="pushing || selectedCount === 0"
                    class="bg-gradient-to-r from-amber-500 to-orange-600 text-white text-sm font-bold py-2.5 px-5 rounded-xl hover:from-amber-600 hover:to-orange-700 transition-all flex items-center gap-2 shadow-md shadow-amber-200 disabled:opacity-60 disabled:cursor-not-allowed">
                <i class="fas fa-cloud-arrow-up" :class="pushing ? 'fa-spin' : ''"></i>
                <span x-text="pushing ? 'Pushing...' : 'Push to ERP'"></span>
            </button>
        </div>
    </div>

    {{-- ══ Rate Type Selector ══ --}}
    <div class="bg-gradient-to-r from-amber-50 to-orange-50 border border-amber-200/70 rounded-2xl p-5 shadow-sm">
        <div class="flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[240px]">
                <label class="block text-[10px] font-black text-amber-700 uppercase tracking-widest mb-1.5 ml-1">Price List / Branch to update</label>
                <select x-model="priceList" @change="clearEdits()"
                        class="w-full bg-white border border-amber-200 rounded-xl py-2.5 px-3 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none transition-all shadow-sm font-bold text-slate-800">
                    @foreach($priceLists as $key => $meta)
                        <option value="{{ $key }}">{{ $key }} — {{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="text-xs font-semibold text-amber-800/80 max-w-md">
                <i class="fas fa-circle-info mr-1"></i>
                Changing the price list clears any rates you have typed. Only rows with a new rate are pushed.
            </div>
        </div>
    </div>

    {{-- ══ Search & Filter Form ══ --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
        <form action="{{ route('costing.pricelist-update') }}" method="GET" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name, code, composition..." class="w-full bg-white border border-slate-200 rounded-xl py-2 px-3 text-xs focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none transition-all shadow-sm font-semibold">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Group 1 (Category)</label>
                    <select name="group1" class="w-full bg-white border border-slate-200 rounded-xl py-2 px-3 text-xs focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none transition-all shadow-sm font-semibold">
                        <option value="">All Categories</option>
                        @foreach($group1List as $grp)
                            <option value="{{ $grp }}" {{ request('group1') === $grp ? 'selected' : '' }}>{{ $grp }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex gap-2 justify-end">
                <button type="submit" class="bg-amber-500 hover:bg-amber-600 text-white text-xs font-black py-2.5 px-6 rounded-xl shadow-sm transition-all flex items-center gap-2">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
                @if(request()->anyFilled(['search', 'group1']))
                <a href="{{ route('costing.pricelist-update') }}" class="bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs font-bold py-2.5 px-5 rounded-xl shadow-sm transition-all flex items-center gap-2">
                    <i class="fas fa-times"></i> Clear Filters
                </a>
                @endif
            </div>
        </form>
    </div>

    {{-- ══ Editable Grid ══ --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden flex flex-col max-h-[calc(100vh-320px)]">
        <div class="overflow-x-auto overflow-y-auto flex-grow">
            <table class="w-full text-left border-collapse table-auto">
                <thead class="bg-slate-50 border-b border-slate-200 sticky top-0 z-20 shadow-[0_1px_0_0_rgba(226,232,240,1)]">
                    <tr>
                        <th class="py-3.5 px-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center w-12">
                            <input type="checkbox" @change="toggleAll($event.target.checked)"
                                   class="rounded border-slate-300 text-amber-600 focus:ring-amber-500/30 cursor-pointer">
                        </th>
                        <th class="py-3.5 px-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => request('sort') === 'asc' ? 'desc' : 'asc']) }}" class="inline-flex items-center gap-1 hover:text-slate-700 transition-colors">
                                Item details
                                @if(request('sort', 'asc') === 'asc')
                                    <i class="fas fa-sort-alpha-down text-slate-500 text-[11px]"></i>
                                @else
                                    <i class="fas fa-sort-alpha-up-alt text-slate-500 text-[11px]"></i>
                                @endif
                            </a>
                        </th>
                        <th class="py-3.5 px-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Item Code</th>
                        <th class="py-3.5 px-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Product Category</th>
                        <th class="py-3.5 px-4 text-[10px] font-black text-blue-600 bg-blue-50/50 uppercase tracking-widest text-right">
                            Current <span x-text="priceList"></span>
                        </th>
                        <th class="py-3.5 px-4 text-[10px] font-black text-amber-700 bg-amber-50/60 uppercase tracking-widest text-right w-40">New Rate</th>
                        <th class="py-3.5 px-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Change</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($pricelists as $row)
                    <tr class="hover:bg-slate-50/50 transition-colors"
                        :class="selected['{{ $row->user_code }}'] ? 'bg-amber-50/40' : ''">
                        <td class="py-3.5 px-4 text-center">
                            <input type="checkbox" x-model="selected['{{ $row->user_code }}']"
                                   class="rounded border-slate-300 text-amber-600 focus:ring-amber-500/30 cursor-pointer">
                        </td>
                        <td class="py-3.5 px-5">
                            <div class="font-bold text-slate-800 text-sm whitespace-normal break-words max-w-[280px]">
                                {{ $row->item_hd_name ?: '—' }}
                            </div>
                            <div class="text-[10px] text-slate-400 font-semibold mt-0.5">
                                Size: {{ $row->size }} {{ $row->size_desc ? ' ('.$row->size_desc.')' : '' }}
                            </div>
                        </td>
                        <td class="py-3.5 px-4">
                            <span class="font-mono text-xs font-bold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded border border-indigo-100">
                                {{ $row->user_code }}
                            </span>
                        </td>
                        <td class="py-3.5 px-4 text-xs font-bold text-slate-700">
                            {{ $row->group1 ?: '—' }}
                        </td>
                        <td class="py-3.5 px-4 text-right bg-blue-50/30">
                            <span class="text-xs font-black text-blue-700"
                                  x-text="'₹' + formatMoney(currentRates['{{ $row->user_code }}'][priceList])"></span>
                        </td>
                        <td class="py-3.5 px-4 bg-amber-50/40">
                            <input type="number" step="0.01" min="0" placeholder="—"
                                   x-model="edits['{{ $row->user_code }}']"
                                   @input="onRateInput('{{ $row->user_code }}')"
                                   class="w-full bg-white border border-amber-200 rounded-lg py-1.5 px-2.5 text-xs text-right font-bold focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none transition-all shadow-sm">
                        </td>
                        <td class="py-3.5 px-4 text-right">
                            <template x-if="deltaPercent('{{ $row->user_code }}') !== null">
                                <span class="inline-flex items-center text-[10px] font-bold px-1.5 py-0.5 rounded"
                                      :class="deltaPercent('{{ $row->user_code }}') >= 0 ? 'text-emerald-600 bg-emerald-100/60' : 'text-rose-600 bg-rose-100/60'">
                                    <i class="fas mr-0.5" :class="deltaPercent('{{ $row->user_code }}') >= 0 ? 'fa-caret-up' : 'fa-caret-down'"></i>
                                    <span x-text="Math.abs(deltaPercent('{{ $row->user_code }}')).toFixed(1) + '%'"></span>
                                </span>
                            </template>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-16 text-center">
                            <div class="w-16 h-16 rounded-2xl bg-amber-50 border border-amber-100 flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-tags text-2xl text-amber-300"></i>
                            </div>
                            <p class="text-slate-500 font-bold mb-2">No product master records stored yet.</p>
                            <a href="{{ route('costing.pricelist') }}" class="inline-block px-5 py-2.5 bg-amber-500 text-white font-black rounded-xl text-sm shadow">
                                <i class="fas fa-sync mr-2"></i> Go to Pricelist &amp; Sync First
                            </a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($pricelists->hasPages())
        <div class="px-6 py-4 border-t border-slate-100 bg-white sticky bottom-0 z-20 shadow-[0_-1px_0_0_rgba(226,232,240,1)]">
            {{ $pricelists->links() }}
        </div>
        @endif
    </div>

    {{-- ══ Sticky Selection Bar ══ --}}
    <div x-show="selectedCount > 0" x-cloak x-transition
         class="fixed bottom-6 left-1/2 -translate-x-1/2 z-40 pro-glass rounded-2xl shadow-2xl shadow-amber-900/10 px-6 py-4 flex items-center gap-5">
        <div class="text-sm font-bold text-slate-700">
            <span class="text-amber-600 font-black" x-text="selectedCount"></span> item(s) ready
            <span class="text-slate-400 font-semibold">· <span x-text="priceList"></span></span>
        </div>
        <button @click="pushToErp()" :disabled="pushing"
                class="bg-gradient-to-r from-amber-500 to-orange-600 text-white text-sm font-bold py-2.5 px-5 rounded-xl hover:from-amber-600 hover:to-orange-700 transition-all flex items-center gap-2 shadow-md shadow-amber-200 disabled:opacity-60">
            <i class="fas fa-cloud-arrow-up" :class="pushing ? 'fa-spin' : ''"></i>
            <span x-text="pushing ? 'Pushing...' : 'Push to ERP'"></span>
        </button>
        <button @click="clearEdits()" class="text-xs font-bold text-slate-500 hover:text-slate-700 transition-colors">
            Clear
        </button>
    </div>

    {{-- ══ Push History Modal ══ --}}
    <div x-show="showHistory" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="showHistory = false; detail = null"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[85vh] flex flex-col overflow-hidden" x-transition>
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-amber-100 flex items-center justify-center">
                        <i class="fas fa-clock-rotate-left text-amber-600 text-sm"></i>
                    </div>
                    <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest">Push History</h3>
                </div>
                <button @click="showHistory = false; detail = null" class="text-slate-400 hover:text-slate-700 transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="overflow-y-auto flex-grow p-6 space-y-4">
                @if($recentPushes->isEmpty())
                    <p class="text-center text-slate-400 font-semibold py-10 text-sm">No pushes recorded yet.</p>
                @else
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="py-2.5 px-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">Date</th>
                                <th class="py-2.5 px-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">Price List</th>
                                <th class="py-2.5 px-3 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Items</th>
                                <th class="py-2.5 px-3 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Status</th>
                                <th class="py-2.5 px-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">By</th>
                                <th class="py-2.5 px-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($recentPushes as $push)
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="py-2.5 px-3 text-xs font-semibold text-slate-600">{{ $push->created_at->format('d M Y, h:i A') }}</td>
                                <td class="py-2.5 px-3 text-xs font-bold text-slate-800">{{ $push->price_list }}</td>
                                <td class="py-2.5 px-3 text-xs font-bold text-right">
                                    <span class="text-emerald-600">{{ $push->total_success }}</span>
                                    @if($push->total_failed > 0)
                                        <span class="text-slate-300 mx-0.5">/</span><span class="text-rose-600">{{ $push->total_failed }}</span>
                                    @endif
                                </td>
                                <td class="py-2.5 px-3 text-center">
                                    <span class="inline-block px-2 py-0.5 rounded text-[9px] font-black border
                                        @if($push->status === 'success') bg-emerald-50 text-emerald-700 border-emerald-100
                                        @elseif($push->status === 'partial') bg-amber-50 text-amber-700 border-amber-100
                                        @else bg-rose-50 text-rose-700 border-rose-100 @endif">
                                        {{ strtoupper($push->status) }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3 text-xs font-semibold text-slate-500">{{ $push->pushed_by }}</td>
                                <td class="py-2.5 px-3 text-right">
                                    <button @click="loadDetail({{ $push->id }})" class="text-[10px] font-black text-amber-600 hover:text-amber-700 uppercase tracking-widest">
                                        Detail
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                {{-- Line-level detail --}}
                <template x-if="detail">
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <h4 class="text-[10px] font-black text-slate-500 uppercase tracking-widest">
                                Run #<span x-text="detail.id"></span> — <span x-text="detail.price_list"></span>
                            </h4>
                            <button @click="detail = null" class="text-[10px] font-bold text-slate-400 hover:text-slate-600">Close</button>
                        </div>
                        <template x-if="detail.error_message">
                            <p class="text-xs font-semibold text-rose-700 bg-rose-50 border border-rose-100 rounded-lg px-3 py-2" x-text="detail.error_message"></p>
                        </template>
                        <table class="w-full text-left border-collapse">
                            <thead class="border-b border-slate-200">
                                <tr>
                                    <th class="py-2 px-2 text-[9px] font-black text-slate-400 uppercase tracking-widest">Item Code</th>
                                    <th class="py-2 px-2 text-[9px] font-black text-slate-400 uppercase tracking-widest">Name</th>
                                    <th class="py-2 px-2 text-[9px] font-black text-slate-400 uppercase tracking-widest text-right">Old</th>
                                    <th class="py-2 px-2 text-[9px] font-black text-slate-400 uppercase tracking-widest text-right">New</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <template x-for="item in detail.items" :key="item.id">
                                    <tr>
                                        <td class="py-1.5 px-2 font-mono text-[11px] font-bold text-indigo-600" x-text="item.user_code"></td>
                                        <td class="py-1.5 px-2 text-[11px] font-semibold text-slate-600" x-text="item.item_name || '—'"></td>
                                        <td class="py-1.5 px-2 text-[11px] font-bold text-slate-400 text-right" x-text="item.old_value !== null ? '₹' + formatMoney(item.old_value) : '—'"></td>
                                        <td class="py-1.5 px-2 text-[11px] font-black text-slate-800 text-right" x-text="'₹' + formatMoney(item.new_value)"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </template>
            </div>
        </div>
    </div>

</div>

<script>
function pricelistUpdateApp() {
    return {
        priceList: 'Sp_Rate1',
        pushing: false,
        showHistory: false,
        detail: null,
        selected: {},
        edits: {},
        currentRates: @json($rateMatrix),

        get selectedCount() {
            return this.payloadItems().length;
        },

        formatMoney(v) {
            return Number(v || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        // Typing a rate auto-selects the row; clearing it deselects.
        onRateInput(code) {
            const v = this.edits[code];
            this.selected[code] = !(v === '' || v === null || v === undefined);
        },

        toggleAll(checked) {
            Object.keys(this.currentRates).forEach(code => {
                const v = this.edits[code];
                if (checked && v !== '' && v !== null && v !== undefined) {
                    this.selected[code] = true;
                } else if (!checked) {
                    this.selected[code] = false;
                }
            });
        },

        clearEdits() {
            this.edits = {};
            this.selected = {};
        },

        deltaPercent(code) {
            const nv = parseFloat(this.edits[code]);
            if (isNaN(nv)) return null;
            const ov = parseFloat(this.currentRates[code]?.[this.priceList] ?? 0);
            if (!ov) return null;
            return ((nv - ov) / ov) * 100;
        },

        payloadItems() {
            return Object.keys(this.selected)
                .filter(code => this.selected[code])
                .map(code => ({ user_code: code, new_value: parseFloat(this.edits[code]) }))
                .filter(i => !isNaN(i.new_value));
        },

        async pushToErp() {
            const items = this.payloadItems();
            if (items.length === 0) {
                alert('Enter a new rate for at least one item.');
                return;
            }
            if (!confirm(`Push ${items.length} item(s) to ERP for ${this.priceList}? This updates live prices in Algebra ERP.`)) return;

            this.pushing = true;
            try {
                const response = await fetch('{{ route('costing.push-pricelist') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ price_list: this.priceList, items: items })
                });
                const data = await response.json();
                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert(data.message || 'Failed to push prices.');
                }
            } catch (e) {
                alert('Network error while pushing prices.');
            } finally {
                this.pushing = false;
            }
        },

        async loadDetail(id) {
            this.detail = null;
            try {
                const response = await fetch('{{ url('costing/pricelist-push-history') }}/' + id, {
                    headers: { 'Accept': 'application/json' }
                });
                this.detail = await response.json();
            } catch (e) {
                alert('Could not load push detail.');
            }
        }
    };
}
</script>
@endsection
