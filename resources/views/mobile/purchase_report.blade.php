@extends('layouts.mobile')

@section('content')
<div class="space-y-5 pb-6">

    {{-- Header --}}
    <div class="bg-gradient-to-br from-amber-500 via-orange-500 to-orange-600 rounded-[2rem] p-6 text-white shadow-lg shadow-orange-200 relative overflow-hidden">
        <div class="absolute -right-8 -top-8 w-32 h-32 bg-white/10 rounded-full blur-2xl"></div>
        <div class="absolute -left-4 bottom-0 w-24 h-24 bg-amber-300/20 rounded-full blur-xl"></div>
        <div class="relative z-10 flex items-center gap-4">
            <div class="w-12 h-12 bg-white/20 rounded-2xl flex items-center justify-center border border-white/30 shadow-inner">
                <i class="fas fa-shopping-cart text-xl"></i>
            </div>
            <div>
                <h1 class="text-xl font-900 tracking-tighter leading-tight">Purchase Report</h1>
                <p class="text-amber-100 text-[9px] font-black uppercase tracking-widest mt-0.5">Algebra ERP · Live Data</p>
            </div>
        </div>
    </div>

    {{-- Filter Form --}}
    <div class="bg-white/80 backdrop-blur-xl rounded-[1.5rem] border border-white shadow-sm p-5 space-y-4">
        <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
            <i class="fas fa-filter text-amber-500 text-[10px]"></i> Filter Parameters
        </div>
        <form method="GET" action="{{ route('mobile.purchase-report') }}" class="space-y-3" id="purchaseForm">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">From Date</label>
                    <input type="date" name="from_date"
                        value="{{ request('from_date', $defaults['from_date']) }}"
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2.5 px-3 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-amber-400 outline-none">
                </div>
                <div>
                    <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">To Date</label>
                    <input type="date" name="to_date"
                        value="{{ request('to_date', $defaults['to_date']) }}"
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2.5 px-3 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-amber-400 outline-none">
                </div>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Account</label>
                    <input type="text" name="account" value="{{ request('account', $defaults['account']) }}" placeholder="all"
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2.5 px-3 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-amber-400 outline-none">
                </div>
                <div>
                    <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Item</label>
                    <input type="text" name="item" value="{{ request('item', $defaults['item']) }}" placeholder="all"
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2.5 px-3 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-amber-400 outline-none">
                </div>
                <div>
                    <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Branch</label>
                    <input type="text" name="branch" value="{{ request('branch', $defaults['branch']) }}" placeholder="all"
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2.5 px-3 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-amber-400 outline-none">
                </div>
            </div>
            <div class="flex gap-3 pt-1">
                <button type="submit"
                    class="flex-1 bg-gradient-to-r from-amber-500 to-orange-500 text-white font-black py-3 rounded-2xl text-sm flex items-center justify-center gap-2 shadow-lg shadow-amber-200 active:scale-95 transition-all">
                    <i class="fas fa-search"></i> Fetch
                </button>
                <a href="{{ route('mobile.purchase-report') }}"
                    class="flex items-center justify-center px-4 bg-slate-100 text-slate-500 rounded-2xl font-black text-sm active:scale-95 transition-all">
                    <i class="fas fa-rotate-left"></i>
                </a>
            </div>
        </form>
    </div>

    {{-- Error --}}
    @if(isset($error))
    <div class="bg-red-50 border border-red-100 rounded-2xl p-4 flex items-center gap-3">
        <div class="w-8 h-8 bg-red-100 rounded-xl flex items-center justify-center text-red-500 flex-shrink-0">
            <i class="fas fa-circle-exclamation text-sm"></i>
        </div>
        <div>
            <div class="text-[10px] font-black text-red-700 uppercase tracking-widest">API Error</div>
            <div class="text-[11px] font-medium text-red-500 mt-0.5">{{ $error }}</div>
        </div>
    </div>
    @endif

    {{-- Summary Stats --}}
    @if(!empty($reportData))
    @php
        $totalAmt   = collect($reportData)->sum(fn($r) => (float)($r['Amount'] ?? 0));
        $uniqueItems = collect($reportData)->pluck('User_Code')->unique()->count();
    @endphp
    <div class="grid grid-cols-3 gap-3">
        <div class="bg-white/80 backdrop-blur-xl rounded-2xl p-4 border border-white shadow-sm text-center">
            <div class="text-lg font-900 text-slate-800">{{ count($reportData) }}</div>
            <div class="text-[8px] font-black text-slate-400 uppercase tracking-widest mt-1">Rows</div>
        </div>
        <div class="bg-white/80 backdrop-blur-xl rounded-2xl p-4 border border-white shadow-sm text-center">
            <div class="text-lg font-900 text-indigo-600">{{ $uniqueItems }}</div>
            <div class="text-[8px] font-black text-slate-400 uppercase tracking-widest mt-1">Items</div>
        </div>
        <div class="bg-amber-50 rounded-2xl p-4 border border-amber-100 shadow-sm text-center">
            <div class="text-lg font-900 text-amber-700">₹{{ number_format($totalAmt / 1000, 0) }}K</div>
            <div class="text-[8px] font-black text-amber-500 uppercase tracking-widest mt-1">Amount</div>
        </div>
    </div>

    {{-- Search --}}
    <div class="relative">
        <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-300 text-sm"></i>
        <input type="text" id="mobileSearch" placeholder="Search records..." onkeyup="mobilePurchaseFilter()"
            class="w-full bg-white/80 backdrop-blur-xl border border-white rounded-2xl py-3 pl-10 pr-4 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-amber-400 outline-none shadow-sm">
    </div>

    {{-- Records List --}}
    <div class="space-y-3" id="mobileRecordList">
        @foreach($reportData as $index => $row)
        <div class="bg-white/80 backdrop-blur-xl rounded-[1.5rem] border border-white shadow-sm overflow-hidden mobile-purchase-row"
             data-search="{{ strtolower(($row['Account_Name'] ?? '') . ' ' . ($row['User_Code'] ?? '') . ' ' . ($row['Item_Name'] ?? '') . ' ' . ($row['Bill_No'] ?? '')) }}">
            {{-- Top row --}}
            <div class="px-4 py-3 flex items-start justify-between gap-2 border-b border-slate-50">
                <div class="flex-1 min-w-0">
                    <div class="text-[11px] font-900 text-slate-800 truncate">{{ $row['Account_Name'] ?? '—' }}</div>
                    <div class="text-[9px] font-bold text-slate-400 mt-0.5">
                        {{ isset($row['Date']) ? \Carbon\Carbon::parse($row['Date'])->format('d M Y') : '—' }}
                        @if(isset($row['Bill_No']) && $row['Bill_No'])
                        · <span class="text-indigo-500 font-black">{{ $row['Bill_No'] }}</span>
                        @endif
                    </div>
                </div>
                <div class="text-right flex-shrink-0">
                    <div class="text-sm font-900 text-amber-700">₹{{ number_format((float)($row['Amount'] ?? 0), 2) }}</div>
                    @if(isset($row['Branch_Code']) && $row['Branch_Code'])
                    <span class="text-[8px] font-black uppercase bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded">{{ $row['Branch_Code'] }}</span>
                    @endif
                </div>
            </div>
            {{-- Bottom row: item details --}}
            <div class="px-4 py-2.5 flex items-center justify-between gap-3">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="bg-indigo-50 text-indigo-600 border border-indigo-100 px-1.5 py-0.5 rounded text-[8px] font-black uppercase flex-shrink-0">
                        {{ $row['User_Code'] ?? '—' }}
                    </span>
                    <span class="text-[10px] text-slate-600 font-bold truncate">{{ $row['Item_Name'] ?? '—' }}</span>
                </div>
                <div class="flex items-center gap-1 text-right flex-shrink-0">
                    <span class="text-[10px] font-900 text-slate-700">{{ number_format((float)($row['Qty'] ?? 0), 2) }}</span>
                    <span class="text-[8px] font-bold text-slate-400 uppercase">{{ $row['Unit'] ?? '' }}</span>
                    <span class="text-[8px] text-slate-300 mx-1">·</span>
                    <span class="text-[10px] font-bold text-slate-500">₹{{ number_format((float)($row['CaseRate'] ?? $row['Rate'] ?? 0), 2) }}</span>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Total Footer --}}
    <div class="bg-amber-600 rounded-2xl p-4 text-white flex items-center justify-between">
        <div class="text-[10px] font-black uppercase tracking-widest">Grand Total ({{ count($reportData) }} records)</div>
        <div class="text-lg font-900">₹{{ number_format($totalAmt, 2) }}</div>
    </div>

    @else
    {{-- Empty state --}}
    @if(request()->anyFilled(['from_date', 'to_date', 'account', 'item', 'branch']))
    <div class="bg-white/60 backdrop-blur-xl rounded-[2rem] border border-white p-12 text-center">
        <div class="w-14 h-14 bg-amber-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-inbox text-2xl text-amber-300"></i>
        </div>
        <div class="text-[11px] font-black text-slate-400 uppercase tracking-widest">No records found</div>
        <div class="text-[10px] text-slate-300 mt-1">Try adjusting filters</div>
    </div>
    @else
    <div class="bg-white/60 backdrop-blur-xl rounded-[2rem] border border-white p-10 text-center">
        <div class="w-14 h-14 bg-amber-50 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-inner">
            <i class="fas fa-shopping-cart text-2xl text-amber-400"></i>
        </div>
        <div class="text-[12px] font-900 text-slate-600 uppercase tracking-tighter">Set Filters & Fetch</div>
        <div class="text-[10px] text-slate-400 mt-1 font-bold">Select date range and tap Fetch</div>
    </div>
    @endif
    @endif

</div>

<script>
function mobilePurchaseFilter() {
    const q = document.getElementById('mobileSearch').value.toLowerCase();
    document.querySelectorAll('.mobile-purchase-row').forEach(row => {
        const match = (row.dataset.search || '').includes(q);
        row.style.display = match ? '' : 'none';
    });
}
</script>
@endsection
