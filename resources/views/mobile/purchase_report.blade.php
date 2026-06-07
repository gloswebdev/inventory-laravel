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
                <p class="text-amber-100 text-[9px] font-black uppercase tracking-widest mt-0.5">
                    Algebra ERP · {{ isset($branch) ? $branch : 'FACTORY (HO)' }}
                </p>
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
                        value="{{ $fromDate ?? $defaults['from_date'] }}"
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2.5 px-3 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-amber-400 outline-none">
                </div>
                <div>
                    <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">To Date</label>
                    <input type="date" name="to_date"
                        value="{{ $toDate ?? $defaults['to_date'] }}"
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2.5 px-3 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-amber-400 outline-none">
                </div>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Account</label>
                    <input type="text" name="account" value="{{ $account ?? $defaults['account'] }}" placeholder="all"
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2.5 px-3 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-amber-400 outline-none">
                </div>
                <div>
                    <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Item</label>
                    <input type="text" name="item" value="{{ $item ?? $defaults['item'] }}" placeholder="all"
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2.5 px-3 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-amber-400 outline-none">
                </div>
                <div>
                    <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Branch</label>
                    <input type="text" name="branch" value="{{ $branch ?? $defaults['branch'] }}" placeholder="FACTORY (HO)"
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2.5 px-3 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-amber-400 outline-none">
                </div>
            </div>
            <div class="flex gap-3 pt-1">
                <button type="submit"
                    class="flex-1 bg-gradient-to-r from-amber-500 to-orange-500 text-white font-black py-3 rounded-2xl text-sm flex items-center justify-center gap-2 shadow-lg shadow-amber-200 active:scale-95 transition-all">
                    <i class="fas fa-search"></i> Fetch
                </button>
                <a href="{{ route('mobile.purchase-report') }}"
                    class="flex items-center justify-center px-5 bg-slate-100 text-slate-500 rounded-2xl font-black text-sm active:scale-95 transition-all">
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
        $totalGrossAmt   = collect($reportData)->sum(fn($r) => (float)str_replace(',', '', $r['Calc_Gross_Amt'] ?? 0));
        $totalNetAmt     = collect($reportData)->sum(fn($r) => (float)str_replace(',', '', $r['Calc_Net_Amt']   ?? 0));
        $totalGST        = collect($reportData)->sum(fn($r) => (float)str_replace(',', '', $r['GST']            ?? 0));
        $totalQty        = collect($reportData)->sum(fn($r) => (float)str_replace(',', '', $r['Qty']            ?? 0));
        $uniqueItems     = collect($reportData)->pluck('User_Code')->unique()->count();
        $uniqueSuppliers = collect($reportData)->pluck('SupplierName')->unique()->count();
    @endphp
    <div class="grid grid-cols-2 gap-3">
        <div class="bg-white/80 backdrop-blur-xl rounded-2xl p-4 border border-white shadow-sm">
            <div class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Records / Items</div>
            <div class="text-lg font-900 text-slate-800 mt-1">{{ count($reportData) }} <span class="text-sm text-slate-400 font-bold">/ {{ $uniqueItems }}</span></div>
        </div>
        <div class="bg-white/80 backdrop-blur-xl rounded-2xl p-4 border border-white shadow-sm">
            <div class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Total GST</div>
            <div class="text-lg font-900 text-rose-600 mt-1">₹{{ number_format($totalGST, 0) }}</div>
        </div>
        <div class="bg-white/80 backdrop-blur-xl rounded-2xl p-4 border border-white shadow-sm">
            <div class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Gross Amt</div>
            <div class="text-lg font-900 text-slate-700 mt-1">₹{{ number_format($totalGrossAmt / 100000, 2) }}L</div>
        </div>
        <div class="bg-amber-50 rounded-2xl p-4 border border-amber-100 shadow-sm">
            <div class="text-[8px] font-black text-amber-600 uppercase tracking-widest">Net Amount</div>
            <div class="text-lg font-900 text-amber-700 mt-1">₹{{ number_format($totalNetAmt / 100000, 2) }}L</div>
        </div>
    </div>

    {{-- Search --}}
    <div class="relative">
        <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-300 text-sm"></i>
        <input type="text" id="mobileSearch" placeholder="Search by supplier, item, bill..." onkeyup="mobilePurchaseFilter()"
            class="w-full bg-white/80 backdrop-blur-xl border border-white rounded-2xl py-3 pl-10 pr-4 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-amber-400 outline-none shadow-sm">
    </div>

    {{-- Records List --}}
    <div class="space-y-3" id="mobileRecordList">
        @foreach($reportData as $index => $row)
        @php
            $netAmt   = (float)str_replace(',', '', $row['Calc_Net_Amt']   ?? 0);
            $grossAmt = (float)str_replace(',', '', $row['Calc_Gross_Amt'] ?? 0);
            $gst      = (float)str_replace(',', '', $row['GST']            ?? 0);
            $qty      = (float)str_replace(',', '', $row['Qty']            ?? 0);
            $rate     = (float)str_replace(',', '', $row['CaseRate']       ?? 0);
            $purity   = $row['Purity'] ?? '';
        @endphp
        <div class="bg-white/80 backdrop-blur-xl rounded-[1.5rem] border border-white shadow-sm overflow-hidden mobile-purchase-row"
             data-search="{{ strtolower(($row['SupplierName'] ?? '') . ' ' . ($row['User_Code'] ?? '') . ' ' . ($row['Item_Hd_Name'] ?? '') . ' ' . ($row['Bill_No'] ?? '') . ' ' . ($row['Branch_Name'] ?? '')) }}">

            {{-- Header row: Supplier + Net Amount --}}
            <div class="px-4 pt-3 pb-2 flex items-start justify-between gap-2 border-b border-slate-50">
                <div class="flex-1 min-w-0">
                    <div class="text-[11px] font-900 text-slate-800 leading-tight truncate" title="{{ $row['SupplierName'] ?? '' }}">
                        {{ $row['SupplierName'] ?? '—' }}
                    </div>
                    <div class="flex items-center gap-2 mt-1 flex-wrap">
                        <span class="text-[8px] font-black uppercase bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded">{{ $row['Branch_Name'] ?? '—' }}</span>
                        <span class="text-[8px] text-slate-400 font-bold">{{ $row['Vouch_Date'] ?? '' }}</span>
                        @if(isset($row['Bill_No']))
                        <span class="text-[8px] font-black text-indigo-600">{{ $row['Bill_No'] }}</span>
                        @endif
                    </div>
                </div>
                <div class="text-right flex-shrink-0">
                    <div class="text-sm font-900 text-amber-700">₹{{ number_format($netAmt, 0) }}</div>
                    <div class="text-[8px] text-slate-400 font-bold">Net</div>
                </div>
            </div>

            {{-- Item row --}}
            <div class="px-4 py-2 border-b border-slate-50 flex items-center gap-2">
                <span class="bg-indigo-50 text-indigo-600 border border-indigo-100 px-1.5 py-0.5 rounded text-[8px] font-black uppercase flex-shrink-0">
                    {{ $row['User_Code'] ?? '—' }}
                </span>
                <span class="text-[10px] text-slate-700 font-bold truncate flex-1" title="{{ $row['Item_Hd_Name'] ?? '' }}">
                    {{ $row['Item_Hd_Name'] ?? '—' }}
                </span>
                @if($purity !== '' && $purity !== null)
                <span class="bg-emerald-50 text-emerald-700 border border-emerald-100 px-1.5 py-0.5 rounded text-[8px] font-black flex-shrink-0">{{ $purity }}%</span>
                @endif
            </div>

            {{-- Groups row --}}
            <div class="px-4 py-2 border-b border-slate-50 flex items-center gap-2 flex-wrap">
                @if(isset($row['GroupName4']) && $row['GroupName4'])
                <span class="bg-teal-50 text-teal-700 border border-teal-100 px-1.5 py-0.5 rounded text-[8px] font-black">{{ $row['GroupName4'] }}</span>
                @endif
                @if(isset($row['GroupName5']) && $row['GroupName5'])
                <span class="bg-violet-50 text-violet-700 border border-violet-100 px-1.5 py-0.5 rounded text-[8px] font-black">{{ $row['GroupName5'] }}</span>
                @endif
                @if(isset($row['Pack_Name']) && $row['Pack_Name'])
                <span class="bg-amber-50 text-amber-700 border border-amber-100 px-1.5 py-0.5 rounded text-[8px] font-black">{{ $row['Pack_Name'] }}</span>
                @endif
                @if(isset($row['GroupName1']) && $row['GroupName1'] && $row['GroupName1'] !== '(NIL)')
                <span class="text-[8px] text-slate-400 font-bold">{{ $row['GroupName1'] }}</span>
                @endif
            </div>

            {{-- Financials row --}}
            <div class="px-4 py-2.5 grid grid-cols-4 gap-2 text-center">
                <div>
                    <div class="text-[8px] font-black text-slate-400 uppercase">Qty</div>
                    <div class="text-[11px] font-900 text-slate-700 mt-0.5">{{ number_format($qty, 0) }}</div>
                </div>
                <div>
                    <div class="text-[8px] font-black text-slate-400 uppercase">Rate</div>
                    <div class="text-[11px] font-900 text-slate-700 mt-0.5">₹{{ number_format($rate, 0) }}</div>
                </div>
                <div>
                    <div class="text-[8px] font-black text-rose-500 uppercase">GST</div>
                    <div class="text-[11px] font-900 text-rose-600 mt-0.5">₹{{ number_format($gst, 0) }}</div>
                </div>
                <div class="bg-amber-50 rounded-xl">
                    <div class="text-[8px] font-black text-amber-600 uppercase mt-1">Gross</div>
                    <div class="text-[11px] font-900 text-amber-700 mb-1">₹{{ number_format($grossAmt, 0) }}</div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Total Footer --}}
    <div class="bg-slate-800 rounded-2xl p-5 text-white space-y-2">
        <div class="text-[9px] font-black uppercase tracking-widest text-slate-400">Grand Total ({{ count($reportData) }} records)</div>
        <div class="grid grid-cols-3 gap-4 mt-2">
            <div>
                <div class="text-[8px] text-slate-400 font-black uppercase">GST</div>
                <div class="text-base font-900 text-rose-400">₹{{ number_format($totalGST, 0) }}</div>
            </div>
            <div>
                <div class="text-[8px] text-slate-400 font-black uppercase">Gross</div>
                <div class="text-base font-900 text-slate-200">₹{{ number_format($totalGrossAmt, 0) }}</div>
            </div>
            <div>
                <div class="text-[8px] text-amber-400 font-black uppercase">Net</div>
                <div class="text-base font-900 text-amber-400">₹{{ number_format($totalNetAmt, 0) }}</div>
            </div>
        </div>
    </div>

    @else
    {{-- Empty state --}}
    @if(isset($error))
    {{-- Error already shown above --}}
    @else
    <div class="bg-white/60 backdrop-blur-xl rounded-[2rem] border border-white p-10 text-center">
        <div class="w-14 h-14 bg-amber-50 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-inner">
            <i class="fas fa-shopping-cart text-2xl text-amber-400"></i>
        </div>
        <div class="text-[12px] font-900 text-slate-600 uppercase tracking-tighter">Set Filters & Fetch</div>
        <div class="text-[10px] text-slate-400 mt-1 font-bold">Default: FACTORY (HO) · Current FY</div>
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
