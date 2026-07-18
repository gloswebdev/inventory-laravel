@extends('layouts.app')

@section('header', 'Pricelist Master')

@section('content')
<style>
    .pro-glass {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid rgba(249, 115, 22, 0.08);
    }
</style>

<div class="space-y-6" x-data="pricelistApp()">

    {{-- ══ Page Header ══ --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-2">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center shadow-lg shadow-amber-200/50">
                <i class="fas fa-tags text-white"></i>
            </div>
            <div>
                <h1 class="text-2xl font-black text-slate-800 tracking-tight">Pricelist Master</h1>
                <p class="text-slate-500 text-sm font-medium mt-0.5">Comprehensive pricing and item properties synced from Product Master ERP API</p>
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
                <span x-text="syncing ? 'Syncing...' : 'Sync Product Master API'"></span>
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
                <select x-model="settings.pricelist_sync_auto" class="w-full bg-white border border-slate-200 rounded-xl py-2 px-3 text-xs focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none transition-all shadow-sm font-semibold">
                    <option value="enabled">Enabled</option>
                    <option value="disabled">Disabled</option>
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5 ml-1 font-bold">Frequency</label>
                <select x-model="settings.pricelist_sync_frequency" class="w-full bg-white border border-slate-200 rounded-xl py-2 px-3 text-xs focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none transition-all shadow-sm font-semibold">
                    <option value="daily">Daily</option>
                    <option value="weekly">Weekly</option>
                </select>
            </div>
            <div x-show="settings.pricelist_sync_frequency === 'weekly'">
                <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5 ml-1 font-bold">Sync Day</label>
                <select x-model="settings.pricelist_sync_day" class="w-full bg-white border border-slate-200 rounded-xl py-2 px-3 text-xs focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none transition-all shadow-sm font-semibold">
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
                <input type="time" x-model="settings.pricelist_sync_time" class="w-full bg-white border border-slate-200 rounded-xl py-2 px-3 text-xs focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none transition-all shadow-sm font-semibold">
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
        <form action="{{ route('costing.pricelist') }}" method="GET" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Search Input --}}
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name, code, composition..." class="w-full bg-white border border-slate-200 rounded-xl py-2 px-3 text-xs focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none transition-all shadow-sm font-semibold">
                </div>

                {{-- Group 1 Filter --}}
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
                <a href="{{ route('costing.pricelist') }}" class="bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs font-bold py-2.5 px-5 rounded-xl shadow-sm transition-all flex items-center gap-2">
                    <i class="fas fa-times"></i> Clear Filters
                </a>
                @endif
            </div>
        </form>
    </div>

    {{-- ══ Table List ══ --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden flex flex-col max-h-[calc(100vh-280px)]">
        <div class="overflow-x-auto overflow-y-auto flex-grow">
            <table class="w-full text-left border-collapse table-auto">
                <thead class="bg-slate-50 border-b border-slate-200 sticky top-0 z-20 shadow-[0_1px_0_0_rgba(226,232,240,1)]">
                    <tr>
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
                        <th class="py-3.5 px-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Technical Name</th>
                        <th class="py-3.5 px-4 text-[10px] font-black text-blue-600 bg-blue-50/50 uppercase tracking-widest text-right">Factory</th>
                        <th class="py-3.5 px-4 text-[10px] font-black text-emerald-600 bg-emerald-50/50 uppercase tracking-widest text-right">Indore</th>
                        <th class="py-3.5 px-4 text-[10px] font-black text-purple-600 bg-purple-50/50 uppercase tracking-widest text-right">Pune</th>
                        <th class="py-3.5 px-4 text-[10px] font-black text-orange-600 bg-orange-50/50 uppercase tracking-widest text-right">Akola</th>
                        <th class="py-3.5 px-4 text-[10px] font-black text-pink-600 bg-pink-50/50 uppercase tracking-widest text-right">Ghaziabad</th>
                        <th class="py-3.5 px-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Tax / Pack</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($pricelists as $row)
                    <tr class="hover:bg-slate-50/50 transition-colors">
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
                        <td class="py-3.5 px-4 text-xs font-semibold text-slate-500 whitespace-normal break-words max-w-[220px]">
                            {{ $row->group3 ?: '—' }}
                        </td>
                        <td class="py-3.5 px-4 text-right bg-blue-50/30 text-xs">
                            <div class="flex items-center justify-end gap-1 font-black text-blue-700">
                                <span>₹{{ number_format($row->sp_rate1, 2) }}</span>
                                @if($row->prev_sp_rate1 !== null && $row->prev_sp_rate1 != $row->sp_rate1)
                                    @php
                                        $diff = $row->sp_rate1 - $row->prev_sp_rate1;
                                        $percent = $row->prev_sp_rate1 > 0 ? ($diff / $row->prev_sp_rate1) * 100 : 0;
                                    @endphp
                                    @if($diff > 0)
                                        <span class="inline-flex items-center text-[9px] font-bold text-emerald-600 bg-emerald-100/60 px-1 py-0.5 rounded" title="Previous: ₹{{ number_format($row->prev_sp_rate1, 2) }}">
                                            <i class="fas fa-caret-up mr-0.5"></i>{{ number_format($percent, 1) }}%
                                        </span>
                                    @else
                                        <span class="inline-flex items-center text-[9px] font-bold text-rose-600 bg-rose-100/60 px-1 py-0.5 rounded" title="Previous: ₹{{ number_format($row->prev_sp_rate1, 2) }}">
                                            <i class="fas fa-caret-down mr-0.5"></i>{{ number_format(abs($percent), 1) }}%
                                        </span>
                                    @endif
                                @endif
                            </div>
                        </td>
                        <td class="py-3.5 px-4 text-right bg-emerald-50/30 text-xs">
                            <div class="flex items-center justify-end gap-1 font-black text-emerald-700">
                                <span>₹{{ number_format($row->sp_rate2, 2) }}</span>
                                @if($row->prev_sp_rate2 !== null && $row->prev_sp_rate2 != $row->sp_rate2)
                                    @php
                                        $diff = $row->sp_rate2 - $row->prev_sp_rate2;
                                        $percent = $row->prev_sp_rate2 > 0 ? ($diff / $row->prev_sp_rate2) * 100 : 0;
                                    @endphp
                                    @if($diff > 0)
                                        <span class="inline-flex items-center text-[9px] font-bold text-emerald-600 bg-emerald-100/60 px-1 py-0.5 rounded" title="Previous: ₹{{ number_format($row->prev_sp_rate2, 2) }}">
                                            <i class="fas fa-caret-up mr-0.5"></i>{{ number_format($percent, 1) }}%
                                        </span>
                                    @else
                                        <span class="inline-flex items-center text-[9px] font-bold text-rose-600 bg-rose-100/60 px-1 py-0.5 rounded" title="Previous: ₹{{ number_format($row->prev_sp_rate2, 2) }}">
                                            <i class="fas fa-caret-down mr-0.5"></i>{{ number_format(abs($percent), 1) }}%
                                        </span>
                                    @endif
                                @endif
                            </div>
                        </td>
                        <td class="py-3.5 px-4 text-right bg-purple-50/30 text-xs">
                            <div class="flex items-center justify-end gap-1 font-black text-purple-700">
                                <span>₹{{ number_format($row->sp_rate3, 2) }}</span>
                                @if($row->prev_sp_rate3 !== null && $row->prev_sp_rate3 != $row->sp_rate3)
                                    @php
                                        $diff = $row->sp_rate3 - $row->prev_sp_rate3;
                                        $percent = $row->prev_sp_rate3 > 0 ? ($diff / $row->prev_sp_rate3) * 100 : 0;
                                    @endphp
                                    @if($diff > 0)
                                        <span class="inline-flex items-center text-[9px] font-bold text-emerald-600 bg-emerald-100/60 px-1 py-0.5 rounded" title="Previous: ₹{{ number_format($row->prev_sp_rate3, 2) }}">
                                            <i class="fas fa-caret-up mr-0.5"></i>{{ number_format($percent, 1) }}%
                                        </span>
                                    @else
                                        <span class="inline-flex items-center text-[9px] font-bold text-rose-600 bg-rose-100/60 px-1 py-0.5 rounded" title="Previous: ₹{{ number_format($row->prev_sp_rate3, 2) }}">
                                            <i class="fas fa-caret-down mr-0.5"></i>{{ number_format(abs($percent), 1) }}%
                                        </span>
                                    @endif
                                @endif
                            </div>
                        </td>
                        <td class="py-3.5 px-4 text-right bg-orange-50/30 text-xs">
                            <div class="flex items-center justify-end gap-1 font-black text-orange-700">
                                <span>₹{{ number_format($row->sp_rate4, 2) }}</span>
                                @if($row->prev_sp_rate4 !== null && $row->prev_sp_rate4 != $row->sp_rate4)
                                    @php
                                        $diff = $row->sp_rate4 - $row->prev_sp_rate4;
                                        $percent = $row->prev_sp_rate4 > 0 ? ($diff / $row->prev_sp_rate4) * 100 : 0;
                                    @endphp
                                    @if($diff > 0)
                                        <span class="inline-flex items-center text-[9px] font-bold text-emerald-600 bg-emerald-100/60 px-1 py-0.5 rounded" title="Previous: ₹{{ number_format($row->prev_sp_rate4, 2) }}">
                                            <i class="fas fa-caret-up mr-0.5"></i>{{ number_format($percent, 1) }}%
                                        </span>
                                    @else
                                        <span class="inline-flex items-center text-[9px] font-bold text-rose-600 bg-rose-100/60 px-1 py-0.5 rounded" title="Previous: ₹{{ number_format($row->prev_sp_rate4, 2) }}">
                                            <i class="fas fa-caret-down mr-0.5"></i>{{ number_format(abs($percent), 1) }}%
                                        </span>
                                    @endif
                                @endif
                            </div>
                        </td>
                        <td class="py-3.5 px-4 text-right bg-pink-50/30 text-xs">
                            <div class="flex items-center justify-end gap-1 font-black text-pink-700">
                                <span>₹{{ number_format($row->sp_rate5, 2) }}</span>
                                @if($row->prev_sp_rate5 !== null && $row->prev_sp_rate5 != $row->sp_rate5)
                                    @php
                                        $diff = $row->sp_rate5 - $row->prev_sp_rate5;
                                        $percent = $row->prev_sp_rate5 > 0 ? ($diff / $row->prev_sp_rate5) * 100 : 0;
                                    @endphp
                                    @if($diff > 0)
                                        <span class="inline-flex items-center text-[9px] font-bold text-emerald-600 bg-emerald-100/60 px-1 py-0.5 rounded" title="Previous: ₹{{ number_format($row->prev_sp_rate5, 2) }}">
                                            <i class="fas fa-caret-up mr-0.5"></i>{{ number_format($percent, 1) }}%
                                        </span>
                                    @else
                                        <span class="inline-flex items-center text-[9px] font-bold text-rose-600 bg-rose-100/60 px-1 py-0.5 rounded" title="Previous: ₹{{ number_format($row->prev_sp_rate5, 2) }}">
                                            <i class="fas fa-caret-down mr-0.5"></i>{{ number_format(abs($percent), 1) }}%
                                        </span>
                                    @endif
                                @endif
                            </div>
                        </td>
                        <td class="py-3.5 px-4 text-center">
                            <div class="space-y-1">
                                <span class="inline-block px-1.5 py-0.5 rounded text-[9px] font-black bg-blue-50 text-blue-700 border border-blue-100">
                                    {{ $row->gst_tax ?: 'N/A' }}
                                </span>
                                @if($row->cf_1)
                                <div class="text-[9px] text-slate-400 font-semibold">CF1: {{ number_format($row->cf_1, 1) }}</div>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="py-16 text-center">
                            <div class="w-16 h-16 rounded-2xl bg-amber-50 border border-amber-100 flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-tags text-2xl text-amber-300"></i>
                            </div>
                            <p class="text-slate-500 font-bold mb-2">No product master records stored yet.</p>
                            <button @click="syncData()" class="px-5 py-2.5 bg-amber-500 text-white font-black rounded-xl text-sm shadow">
                                <i class="fas fa-sync mr-2"></i> Sync Product Master Now
                            </button>
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

</div>

<script>
function pricelistApp() {
    return {
        syncing: false,
        saving: false,
        showSettings: false,
        settings: @json($settings),

        async syncData() {
            if (!confirm('Sync pricelist data from ERP Product Master API? This may take some time.')) return;
            this.syncing = true;
            try {
                const response = await fetch('{{ route('costing.sync-pricelist') }}', {
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
                const response = await fetch('{{ route('costing.save-pricelist-sync-settings') }}', {
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
