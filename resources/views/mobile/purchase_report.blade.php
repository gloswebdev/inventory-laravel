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
                    Algebra ERP · LogicPurchaseRegisterDetail
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

            {{-- Date Row --}}
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

            {{-- Branch --}}
            <div>
                <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Branch</label>
                <input type="text" name="branch" value="{{ $branch ?? $defaults['branch'] }}" placeholder="all"
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2.5 px-3 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-amber-400 outline-none">
            </div>

            {{-- Account Dropdown --}}
            <div>
                <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Account (Supplier)</label>
                <div class="relative">
                    <select name="account"
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2.5 px-3 pr-8 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-amber-400 outline-none appearance-none">
                        <option value="all" {{ ($account ?? 'all') === 'all' ? 'selected' : '' }}>— All Accounts —</option>
                        @if(!empty($accountOptions ?? []))
                            @foreach($accountOptions as $opt)
                                <option value="{{ $opt }}" {{ ($account ?? '') === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                            @endforeach
                        @elseif(!empty($account) && $account !== 'all')
                            <option value="{{ $account }}" selected>{{ $account }}</option>
                        @endif
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-amber-500">
                        <i class="fas fa-chevron-down text-xs"></i>
                    </div>
                </div>
            </div>

            {{-- Item Dropdown --}}
            <div>
                <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Item</label>
                <div class="relative">
                    <select name="item"
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2.5 px-3 pr-8 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-indigo-400 outline-none appearance-none">
                        <option value="all" {{ ($item ?? 'all') === 'all' ? 'selected' : '' }}>— All Items —</option>
                        @if(!empty($itemOptions ?? []))
                            @foreach($itemOptions as $opt)
                                <option value="{{ $opt['name'] }}" {{ ($item ?? '') === $opt['name'] ? 'selected' : '' }}>{{ $opt['name'] }}</option>
                            @endforeach
                        @elseif(!empty($item) && $item !== 'all')
                            <option value="{{ $item }}" selected>{{ $item }}</option>
                        @endif
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-indigo-500">
                        <i class="fas fa-chevron-down text-xs"></i>
                    </div>
                </div>
            </div>

            {{-- Rm Type + Types Row --}}
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Rm Type</label>
                    <div class="relative">
                        <select name="rm_type"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2.5 px-3 pr-8 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-teal-400 outline-none appearance-none">
                            <option value="">— All —</option>
                            @if(!empty($rmTypeOptions ?? []))
                                @foreach($rmTypeOptions as $opt)
                                    <option value="{{ $opt }}" {{ ($rmType ?? '') === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                @endforeach
                            @elseif(!empty($rmType))
                                <option value="{{ $rmType }}" selected>{{ $rmType }}</option>
                            @endif
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-teal-500">
                            <i class="fas fa-chevron-down text-xs"></i>
                        </div>
                    </div>
                </div>
                <div>
                    <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Types</label>
                    <div class="relative">
                        <select name="types"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2.5 px-3 pr-8 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-violet-400 outline-none appearance-none">
                            <option value="">— All —</option>
                            @if(!empty($typesOptions ?? []))
                                @foreach($typesOptions as $opt)
                                    <option value="{{ $opt }}" {{ ($types ?? '') === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                @endforeach
                            @elseif(!empty($types))
                                <option value="{{ $types }}" selected>{{ $types }}</option>
                            @endif
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-violet-500">
                            <i class="fas fa-chevron-down text-xs"></i>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Buttons --}}
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

    {{-- Stats Grid --}}
    <div class="grid grid-cols-2 gap-3">
        <div class="bg-white/80 backdrop-blur-xl rounded-2xl p-4 border border-white shadow-sm">
            <div class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Records / Items</div>
            <div class="text-lg font-900 text-slate-800 mt-1">{{ count($reportData) }} <span class="text-sm text-slate-400 font-bold">/ {{ $uniqueItems }}</span></div>
        </div>
        <div class="bg-white/80 backdrop-blur-xl rounded-2xl p-4 border border-white shadow-sm">
            <div class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Suppliers</div>
            <div class="text-lg font-900 text-violet-600 mt-1">{{ $uniqueSuppliers }}</div>
        </div>
        <div class="bg-white/80 backdrop-blur-xl rounded-2xl p-4 border border-white shadow-sm">
            <div class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Total GST</div>
            <div class="text-lg font-900 text-rose-600 mt-1">₹{{ number_format($totalGST, 0) }}</div>
        </div>
        <div class="bg-amber-50 rounded-2xl p-4 border border-amber-100 shadow-sm">
            <div class="text-[8px] font-black text-amber-600 uppercase tracking-widest">Net Amount</div>
            <div class="text-lg font-900 text-amber-700 mt-1">₹{{ number_format($totalNetAmt / 100000, 2) }}L</div>
        </div>
    </div>

    {{-- Active Filter Tags --}}
    @php
        $activeTags = [];
        if(!empty($rmType) && $rmType !== 'all') $activeTags[] = ['label' => 'Rm Type', 'val' => $rmType, 'color' => 'teal'];
        if(!empty($types) && $types !== 'all')   $activeTags[] = ['label' => 'Types',   'val' => $types,  'color' => 'violet'];
        if(!empty($account) && $account !== 'all') $activeTags[] = ['label' => 'Account', 'val' => Str::limit($account, 20), 'color' => 'amber'];
        if(!empty($item) && $item !== 'all')       $activeTags[] = ['label' => 'Item',    'val' => Str::limit($item, 20),    'color' => 'indigo'];
    @endphp
    @if(!empty($activeTags))
    <div class="flex flex-wrap gap-2">
        @foreach($activeTags as $tag)
        <span class="bg-{{ $tag['color'] }}-50 text-{{ $tag['color'] }}-700 border border-{{ $tag['color'] }}-100 px-2.5 py-1 rounded-full text-[9px] font-black flex items-center gap-1">
            <i class="fas fa-tag text-[8px]"></i>
            {{ $tag['label'] }}: {{ $tag['val'] }}
        </span>
        @endforeach
    </div>
    @endif

    {{-- Search --}}
    <div class="relative">
        <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-300 text-sm"></i>
        <input type="text" id="mobileSearch" placeholder="Search supplier, item, bill..." onkeyup="mobilePurchaseFilter()"
            class="w-full bg-white/80 backdrop-blur-xl border border-white rounded-2xl py-3 pl-10 pr-4 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-amber-400 outline-none shadow-sm">
    </div>

    {{-- Records List --}}
    <div class="space-y-3" id="mobileRecordList">
        @php
            $groupedReport = collect($reportData)->groupBy(function($item) {
                return ($item['Bill_No'] ?? '') ?: (($item['Vouch_No'] ?? '') ?: 'unq_' . uniqid());
            });
        @endphp

        @foreach($groupedReport as $billNo => $items)
            @php
                $firstRow = $items->first();
                $billNetAmt = $items->sum(fn($r) => (float)str_replace(',', '', $r['Calc_Net_Amt'] ?? 0));
                $billGrossAmt = $items->sum(fn($r) => (float)str_replace(',', '', $r['Calc_Gross_Amt'] ?? 0));
                $billGST = $items->sum(fn($r) => (float)str_replace(',', '', $r['GST'] ?? 0));
                
                // Construct search string combining all items in this bill
                $searchStr = strtolower(
                    ($firstRow['SupplierName'] ?? '') . ' ' . 
                    ($firstRow['Bill_No'] ?? '') . ' ' . 
                    ($firstRow['Branch_Name'] ?? '') . ' ' .
                    $items->map(fn($r) => ($r['User_Code'] ?? '') . ' ' . ($r['Item_Hd_Name'] ?? ''))->implode(' ')
                );
            @endphp
            <div class="bg-white/80 backdrop-blur-xl rounded-[1.5rem] border border-white shadow-sm overflow-hidden mobile-purchase-row"
                 data-search="{{ $searchStr }}">

                {{-- Header: Supplier + Total Bill Net Amount --}}
                <div class="px-4 pt-3 pb-2 flex items-start justify-between gap-2 border-b border-slate-100 bg-slate-50/50">
                    <div class="flex-1 min-w-0">
                        <div class="text-[11px] font-900 text-slate-800 leading-tight truncate" title="{{ $firstRow['SupplierName'] ?? '' }}">
                            {{ $firstRow['SupplierName'] ?? '—' }}
                        </div>
                        <div class="flex items-center gap-2 mt-1 flex-wrap">
                            <span class="text-[8px] font-black uppercase bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded">{{ $firstRow['Branch_Name'] ?? '—' }}</span>
                            <span class="text-[8px] text-slate-400 font-bold">{{ $firstRow['Vouch_Date'] ?? '' }}</span>
                            @if($billNo && !str_starts_with($billNo, 'unq_'))
                            <span class="text-[8px] font-black text-indigo-600">Bill: {{ $billNo }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <div class="text-sm font-900 text-amber-700">₹{{ number_format($billNetAmt, 0) }}</div>
                        <div class="text-[8px] text-slate-400 font-bold">Total Net</div>
                    </div>
                </div>

                {{-- Items --}}
                <div class="divide-y divide-slate-100">
                    @foreach($items as $row)
                    @php
                        $rowGrossAmt = (float)str_replace(',', '', $row['Calc_Gross_Amt'] ?? 0);
                        $rowGst      = (float)str_replace(',', '', $row['GST']            ?? 0);
                        $rowQty      = (float)str_replace(',', '', $row['Qty']            ?? 0);
                        $rowRate     = (float)str_replace(',', '', $row['CaseRate']       ?? 0);
                        $purity      = $row['Purity'] ?? '';
                    @endphp
                    <div class="p-3.5 space-y-2">
                        {{-- Code, Name, Purity --}}
                        <div class="flex items-center gap-2">
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

                        {{-- Groups / Tags --}}
                        <div class="flex items-center gap-2 flex-wrap">
                            @if(isset($row['GroupName4']) && $row['GroupName4'])
                            <span class="bg-teal-50 text-teal-700 border border-teal-100 px-1.5 py-0.5 rounded text-[8px] font-black">{{ $row['GroupName4'] }}</span>
                            @endif
                            @if(isset($row['GroupName5']) && $row['GroupName5'])
                            <span class="bg-violet-50 text-violet-700 border border-violet-100 px-1.5 py-0.5 rounded text-[8px] font-black">{{ $row['GroupName5'] }}</span>
                            @endif
                            @if(isset($row['Pack_Name']) && $row['Pack_Name'])
                            <span class="bg-amber-50 text-amber-700 border border-amber-100 px-1.5 py-0.5 rounded text-[8px] font-black">{{ $row['Pack_Name'] }}</span>
                            @endif
                        </div>

                        {{-- Financials --}}
                        <div class="grid grid-cols-4 gap-2 text-center pt-1.5">
                            <div>
                                <div class="text-[8px] font-black text-slate-400 uppercase">Qty</div>
                                <div class="text-[10px] font-900 text-slate-700 mt-0.5">{{ number_format($rowQty, 0) }}</div>
                            </div>
                            <div>
                                <div class="text-[8px] font-black text-slate-400 uppercase">Rate</div>
                                <div class="text-[10px] font-900 text-slate-700 mt-0.5">₹{{ number_format($rowRate, 0) }}</div>
                            </div>
                            <div>
                                <div class="text-[8px] font-black text-rose-500 uppercase">GST</div>
                                <div class="text-[10px] font-900 text-rose-600 mt-0.5">₹{{ number_format($rowGst, 0) }}</div>
                            </div>
                            <div class="bg-amber-50 rounded-xl">
                                <div class="text-[8px] font-black text-amber-600 uppercase mt-0.5">Gross</div>
                                <div class="text-[10px] font-900 text-amber-700 mb-0.5">₹{{ number_format($rowGrossAmt, 0) }}</div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    {{-- Pagination Controls --}}
    <div class="flex items-center justify-between py-2" id="paginationControls">
        {{-- Previous button --}}
        <button id="prevBtn" onclick="goToPage(currentPage-1)" class="w-12 h-12 rounded-2xl bg-white flex items-center justify-center text-slate-400 border border-slate-100 shadow-sm active:scale-90 transition-all focus:outline-none">
            <i class="fas fa-chevron-left text-xs"></i>
        </button>

        {{-- Page Info / Size --}}
        <div class="flex items-center gap-2">
            <span class="text-[10px] font-black text-slate-800 uppercase tracking-widest bg-white px-3 py-2 rounded-xl border border-slate-100 shadow-sm" id="pageLabel">
                Page 1 of 1
            </span>
            <select id="pageSizeSelect" onchange="changePageSize()" class="text-[10px] font-black text-slate-700 uppercase tracking-widest bg-white px-3 py-2 rounded-xl border border-slate-100 shadow-sm focus:outline-none focus:ring-1 focus:ring-amber-400">
                <option value="15">15 rows</option>
                <option value="30" selected>30 rows</option>
                <option value="50">50 rows</option>
                <option value="100">100 rows</option>
                <option value="999999">All</option>
            </select>
        </div>

        {{-- Next button --}}
        <button id="nextBtn" onclick="goToPage(currentPage+1)" class="w-12 h-12 rounded-2xl bg-white flex items-center justify-center text-slate-400 border border-slate-100 shadow-sm active:scale-90 transition-all focus:outline-none">
            <i class="fas fa-chevron-right text-xs"></i>
        </button>
    </div>

    {{-- Grand Total Footer --}}
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
    @if(!isset($error))
    <div class="bg-white/60 backdrop-blur-xl rounded-[2rem] border border-white p-10 text-center">
        <div class="w-14 h-14 bg-amber-50 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-inner">
            <i class="fas fa-shopping-cart text-2xl text-amber-400"></i>
        </div>
        <div class="text-[12px] font-900 text-slate-600 uppercase tracking-tighter">Set Filters & Fetch</div>
        <div class="text-[10px] text-slate-400 mt-1 font-bold">Select date range and click Fetch</div>
    </div>
    @endif
    @endif

</div>

<script>
let allCards      = [];
let filteredCards = [];
let currentPage  = 1;
let pageSize     = 30;

function initPagination() {
    allCards      = Array.from(document.querySelectorAll('.mobile-purchase-row'));
    filteredCards = [...allCards];
    currentPage   = 1;
    renderPage();
}

function renderPage() {
    const total = filteredCards.length;
    const totalPages = Math.max(1, Math.ceil(total / pageSize));
    if (currentPage > totalPages) currentPage = totalPages;
    if (currentPage < 1) currentPage = 1;

    const start = (currentPage - 1) * pageSize;
    const end   = Math.min(start + pageSize, total);

    // Show/hide cards
    allCards.forEach(c => c.style.display = 'none');
    filteredCards.forEach((c, i) => {
        if (i >= start && i < end) {
            c.style.display = '';
        }
    });

    // Update Page Label
    const pageLabel = document.getElementById('pageLabel');
    if (pageLabel) {
        pageLabel.textContent = `Page ${currentPage} of ${totalPages}`;
    }

    // Prev Button State
    const prevBtn = document.getElementById('prevBtn');
    if (prevBtn) {
        if (currentPage <= 1) {
            prevBtn.disabled = true;
            prevBtn.classList.add('bg-slate-50', 'text-slate-200', 'opacity-50', 'cursor-not-allowed');
            prevBtn.classList.remove('bg-white', 'text-slate-400', 'shadow-sm', 'active:scale-90');
        } else {
            prevBtn.disabled = false;
            prevBtn.classList.remove('bg-slate-50', 'text-slate-200', 'opacity-50', 'cursor-not-allowed');
            prevBtn.classList.add('bg-white', 'text-slate-400', 'shadow-sm', 'active:scale-90');
        }
    }

    // Next Button State
    const nextBtn = document.getElementById('nextBtn');
    if (nextBtn) {
        if (currentPage >= totalPages) {
            nextBtn.disabled = true;
            nextBtn.classList.add('bg-slate-50', 'text-slate-200', 'opacity-50', 'cursor-not-allowed');
            nextBtn.classList.remove('bg-white', 'text-slate-400', 'shadow-sm', 'active:scale-90');
        } else {
            nextBtn.disabled = false;
            nextBtn.classList.remove('bg-slate-50', 'text-slate-200', 'opacity-50', 'cursor-not-allowed');
            nextBtn.classList.add('bg-white', 'text-slate-400', 'shadow-sm', 'active:scale-90');
        }
    }
}

function goToPage(page) {
    currentPage = page;
    renderPage();
    document.getElementById('mobileSearch')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function changePageSize() {
    pageSize = parseInt(document.getElementById('pageSizeSelect').value);
    currentPage = 1;
    renderPage();
}

function mobilePurchaseFilter() {
    const q = document.getElementById('mobileSearch').value.toLowerCase().trim();
    filteredCards = q
        ? allCards.filter(c => (c.dataset.search || '').includes(q))
        : [...allCards];
    currentPage = 1;
    renderPage();
}

// Init on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('mobileRecordList')) {
        initPagination();
    }
});
</script>
@endsection
