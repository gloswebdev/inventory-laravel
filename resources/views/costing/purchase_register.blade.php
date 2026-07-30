@extends('layouts.app')

@section('header', 'Purchase Register')

@section('content')
<style>
    .pro-glass {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid rgba(249, 115, 22, 0.08);
    }
</style>

<div class="space-y-6" x-data="purchaseRegisterApp()">

    {{-- ══ Page Header ══ --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-2">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center shadow-lg shadow-amber-200/50">
                <i class="fas fa-shopping-cart text-white"></i>
            </div>
            <div>
                <h1 class="text-2xl font-black text-slate-800 tracking-tight">Purchase Register Database</h1>
                <p class="text-slate-500 text-sm font-medium mt-0.5">Synced historical purchase rates and purity parameters from ERP</p>
            </div>
        </div>
        <div class="flex gap-2 flex-wrap items-center">
            <button @click="showSettings = !showSettings"
                    class="bg-white border border-slate-200 text-slate-700 text-sm font-bold py-2.5 px-4 rounded-xl hover:bg-slate-50 transition-all flex items-center gap-2 shadow-sm">
                <i class="fas fa-cog text-slate-400"></i> Sync Settings
            </button>
            <button @click="syncData()" :disabled="syncing"
                    class="bg-gradient-to-r from-amber-500 to-orange-600 text-white text-sm font-bold py-2.5 px-5 rounded-xl hover:from-amber-600 hover:to-orange-700 transition-all flex items-center gap-2 shadow-md shadow-amber-200 disabled:opacity-60">
                <i class="fas fa-sync" :class="syncing ? 'fa-spin' : ''"></i>
                <span x-text="syncing ? 'Syncing...' : 'Sync Current FY Purchases'"></span>
            </button>
        </div>
    </div>

    {{-- ══ Auto Sync Settings Card ══ --}}
    <div class="bg-slate-50 border border-slate-200/70 rounded-2xl p-5 shadow-sm space-y-4" x-show="showSettings" x-cloak x-transition>
        <div class="flex items-center gap-2 mb-1">
            <div class="w-6 h-6 rounded bg-amber-100 flex items-center justify-center">
                <i class="fas fa-clock text-amber-600 text-xs"></i>
            </div>
            <h3 class="text-xs font-black text-slate-800 uppercase tracking-widest">Automatic Sync Scheduler Settings</h3>
        </div>
        <form @submit.prevent="saveSettings()" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
            <div>
                <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5 ml-1 font-bold">Auto Sync</label>
                <select x-model="settings.purchase_sync_auto" class="w-full bg-white border border-slate-200 rounded-xl py-2 px-3 text-xs focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none transition-all shadow-sm font-semibold">
                    <option value="enabled">Enabled</option>
                    <option value="disabled">Disabled</option>
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5 ml-1 font-bold">Frequency</label>
                <select x-model="settings.purchase_sync_frequency" class="w-full bg-white border border-slate-200 rounded-xl py-2 px-3 text-xs focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none transition-all shadow-sm font-semibold">
                    <option value="daily">Daily</option>
                    <option value="weekly">Weekly</option>
                </select>
            </div>
            <div x-show="settings.purchase_sync_frequency === 'weekly'">
                <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5 ml-1 font-bold">Sync Day</label>
                <select x-model="settings.purchase_sync_day" class="w-full bg-white border border-slate-200 rounded-xl py-2 px-3 text-xs focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none transition-all shadow-sm font-semibold">
                    <option value="Sunday">Sunday</option>
                    <option value="Monday">Monday</option>
                    <option value="Tuesday">Tuesday</option>
                    <option value="Wednesday">Wednesday</option>
                    <option value="Thursday">Thursday</option>
                    <option value="Friday">Friday</option>
                    <option value="Saturday">Saturday</option>
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5 ml-1 font-bold">Sync Time</label>
                <input type="time" x-model="settings.purchase_sync_time" class="w-full bg-white border border-slate-200 rounded-xl py-2 px-3 text-xs focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none transition-all shadow-sm font-semibold">
            </div>
            <div>
                <button type="submit" :disabled="saving" class="w-full bg-slate-800 hover:bg-slate-900 text-white text-xs font-black py-2.5 px-6 rounded-xl shadow-sm transition-all flex items-center justify-center gap-2">
                    <i class="fas fa-save" x-show="!saving"></i>
                    <i class="fas fa-circle-notch fa-spin" x-show="saving"></i>
                    <span x-text="saving ? 'Saving...' : 'Save Settings'"></span>
                </button>
            </div>
        </form>
    </div>

    {{-- ══ Search & Filter Form ══ --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
        <form action="{{ route('costing.purchase-register') }}" method="GET" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                {{-- Supplier Filter --}}
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Supplier Name</label>
                    <select name="supplier_name" class="w-full bg-white border border-slate-200 rounded-xl py-2 px-3 text-xs focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none transition-all shadow-sm font-semibold">
                        <option value="">All Suppliers</option>
                        @foreach($supplierList as $sup)
                            <option value="{{ $sup }}" {{ request('supplier_name') === $sup ? 'selected' : '' }}>{{ $sup }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Product Name Filter --}}
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Product Name</label>
                    <select name="item_name" class="w-full bg-white border border-slate-200 rounded-xl py-2 px-3 text-xs focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none transition-all shadow-sm font-semibold">
                        <option value="">All Products</option>
                        @foreach($productList as $prod)
                            <option value="{{ $prod }}" {{ request('item_name') === $prod ? 'selected' : '' }}>{{ $prod }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- RM Type Filter --}}
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">RM Type</label>
                    <select name="rm_type" class="w-full bg-white border border-slate-200 rounded-xl py-2 px-3 text-xs focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none transition-all shadow-sm font-semibold">
                        <option value="">All RM Types</option>
                        @foreach($rmTypeList as $type)
                            <option value="{{ $type }}" {{ request('rm_type') === $type ? 'selected' : '' }}>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Product Group Filter --}}
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Product Group</label>
                    <select name="group_name" class="w-full bg-white border border-slate-200 rounded-xl py-2 px-3 text-xs focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none transition-all shadow-sm font-semibold">
                        <option value="">All Groups</option>
                        @foreach($groupList as $grp)
                            <option value="{{ $grp }}" {{ request('group_name') === $grp ? 'selected' : '' }}>{{ $grp }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Bill Number (Vouch No) --}}
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Bill Number (Vouch No)</label>
                    <input type="text" name="vouch_no" value="{{ request('vouch_no') }}" placeholder="Enter bill number..." class="w-full bg-white border border-slate-200 rounded-xl py-2 px-3 text-xs focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none transition-all shadow-sm font-semibold">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2 items-end">
                {{-- Date From --}}
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Purchase Date From</label>
                    <input type="date" name="from_date" value="{{ request('from_date') }}" class="w-full bg-white border border-slate-200 rounded-xl py-2 px-3 text-xs focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none transition-all shadow-sm font-semibold">
                </div>

                {{-- Date To --}}
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Purchase Date To</label>
                    <input type="date" name="to_date" value="{{ request('to_date') }}" class="w-full bg-white border border-slate-200 rounded-xl py-2 px-3 text-xs focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none transition-all shadow-sm font-semibold">
                </div>

                {{-- Action Buttons --}}
                <div class="flex gap-2 justify-end">
                    <button type="submit" class="bg-amber-500 hover:bg-amber-600 text-white text-xs font-black py-2.5 px-6 rounded-xl shadow-sm transition-all flex items-center gap-2">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    @if(request()->anyFilled(['supplier_name', 'item_name', 'rm_type', 'group_name', 'vouch_no', 'from_date', 'to_date']))
                    <a href="{{ route('costing.purchase-register') }}" class="bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs font-bold py-2.5 px-5 rounded-xl shadow-sm transition-all flex items-center gap-2">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                    @endif
                </div>
            </div>
        </form>
    </div>

    {{-- ══ Table List ══ --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="py-3.5 px-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Supplier Name</th>
                        <th class="py-3.5 px-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Item Detail</th>
                        <th class="py-3.5 px-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Item Code</th>
                        <th class="py-3.5 px-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Purity</th>
                        <th class="py-3.5 px-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Qty</th>
                        <th class="py-3.5 px-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Case Rate</th>
                        <th class="py-3.5 px-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Voucher info</th>
                                   <tbody class="divide-y divide-slate-100">
                    @php
                        $groupedPurchases = collect($purchases->items())->groupBy(function($item) {
                            return $item->vouch_no . '_' . $item->supplier_name . '_' . $item->vouch_date;
                        });
                    @endphp

                    @forelse($groupedPurchases as $groupKey => $items)
                        @foreach($items as $index => $row)
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            @if($index === 0)
                            <td class="py-3.5 px-5 align-top border-r border-slate-100" rowspan="{{ count($items) }}">
                                <div class="font-bold text-slate-800 text-sm whitespace-normal break-words max-w-[280px]">
                                    {{ $row->supplier_name ?: '—' }}
                                </div>
                            </td>
                            @endif
                            
                            <td class="py-3.5 px-5">
                                <div class="font-semibold text-slate-700 text-xs">{{ $row->item_name }}</div>
                                @if($row->group_name4 || $row->group_name5)
                                <div class="flex gap-1.5 mt-1">
                                    @if($row->group_name4)
                                    <span class="text-[9px] font-bold text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-100">{{ $row->group_name4 }}</span>
                                    @endif
                                    @if($row->group_name5)
                                    <span class="text-[9px] font-bold text-indigo-700 bg-indigo-50 px-1.5 py-0.5 rounded border border-indigo-100">{{ $row->group_name5 }}</span>
                                    @endif
                                </div>
                                @endif
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="font-mono text-xs font-bold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded border border-indigo-100">
                                    {{ $row->item_code }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                @if($row->purity !== null)
                                <span class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded-lg text-xs font-black bg-emerald-50 text-emerald-700 border border-emerald-100">
                                    {{ $row->purity }}%
                                </span>
                                @else
                                <span class="text-slate-300">—</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-right font-semibold text-slate-600 text-xs">
                                {{ number_format($row->qty, 2) }}
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <div class="font-black text-slate-800 text-xs">₹{{ number_format($row->case_rate, 2) }}</div>
                                {{-- Price Difference Badging --}}
                                @php
                                    $prevPurchase = \App\Models\PurchaseRegister::where('item_code', $row->item_code)
                                        ->where(function($q) use ($row) {
                                            $q->where('vouch_date', '<', $row->vouch_date)
                                              ->orWhere(function($sq) use ($row) {
                                                  $sq->where('vouch_date', '=', $row->vouch_date)
                                                     ->where('id', '<', $row->id);
                                              });
                                        })
                                        ->orderByDesc('vouch_date')
                                        ->orderByDesc('id')
                                        ->first();
                                @endphp
                                @if($prevPurchase && $prevPurchase->case_rate > 0)
                                    @php
                                        $diff = (float)$row->case_rate - (float)$prevPurchase->case_rate;
                                    @endphp
                                    @if($diff > 0)
                                        <div class="inline-flex items-center gap-0.5 mt-1 px-1.5 py-0.5 rounded text-[9px] font-black bg-rose-50 text-rose-600 border border-rose-100" title="Previous Rate: ₹{{ number_format($prevPurchase->case_rate, 2) }}">
                                            <i class="fas fa-arrow-trend-up text-[8px]"></i> +₹{{ number_format($diff, 2) }}
                                        </div>
                                    @elseif($diff < 0)
                                        <div class="inline-flex items-center gap-0.5 mt-1 px-1.5 py-0.5 rounded text-[9px] font-black bg-emerald-50 text-emerald-600 border border-emerald-100" title="Previous Rate: ₹{{ number_format($prevPurchase->case_rate, 2) }}">
                                            <i class="fas fa-arrow-trend-down text-[8px]"></i> -₹{{ number_format(abs($diff), 2) }}
                                        </div>
                                    @endif
                                @endif
                            </td>
                            
                            @if($index === 0)
                            @php
                                $billTotalAmount = $items->sum(fn($r) => (float)$r->qty * (float)$r->case_rate);
                            @endphp
                            <td class="py-3.5 px-5 text-right align-top border-l border-slate-100 bg-slate-50/20" rowspan="{{ count($items) }}">
                                <div class="text-xs font-bold text-slate-600">Vouch No: {{ $row->vouch_no }}</div>
                                <div class="text-[10px] text-slate-400 font-semibold mt-0.5">
                                    {{ $row->vouch_date ? \Carbon\Carbon::parse($row->vouch_date)->format('d M Y') : '—' }}
                                </div>
                                <div class="mt-3 pt-2 border-t border-slate-100">
                                    <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Bill Total</div>
                                    <div class="text-sm font-black text-amber-600 mt-0.5">₹{{ number_format($billTotalAmount, 2) }}</div>
                                </div>
                            </td>
                            @endif
                        </tr>
                        @endforeach
                    @empty
                    <tr>
                        <td colspan="7" class="py-16 text-center">
                            <div class="w-16 h-16 rounded-2xl bg-amber-50 border border-amber-100 flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-shopping-cart text-2xl text-amber-300"></i>
                            </div>
                            <p class="text-slate-500 font-bold mb-2">No purchase records stored yet.</p>
                            <button @click="syncData()" class="px-5 py-2.5 bg-amber-500 text-white font-black rounded-xl text-sm shadow">
                                <i class="fas fa-sync mr-2"></i> Sync Purchases Now
                            </button>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($purchases->hasPages())
        <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50">
            {{ $purchases->links() }}
        </div>
        @endif
    </div>

</div>

<script>
function purchaseRegisterApp() {
    return {
        syncing: false,
        saving: false,
        showSettings: false,
        settings: @json($settings),

        async syncData() {
            if (!confirm('Sync purchase register for the current financial year from ERP? This may take some time.')) return;
            this.syncing = true;
            try {
                const response = await fetch('{{ route('costing.sync-purchase-register') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json'
                    }
                });
                const data = await response.json();
                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert(data.message || 'Failed to sync.');
                }
            } catch (e) {
                alert('Network error.');
            } finally {
                this.syncing = false;
            }
        },

        async saveSettings() {
            this.saving = true;
            try {
                const response = await fetch('{{ route('costing.save-sync-settings') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(this.settings)
                });
                const data = await response.json();
                if (data.success) {
                    alert(data.message);
                    this.showSettings = false;
                } else {
                    alert(data.message || 'Failed to save settings.');
                }
            } catch (e) {
                alert('Network error while saving settings.');
            } finally {
                this.saving = false;
            }
        }
    };
}
</script>
@endsection
