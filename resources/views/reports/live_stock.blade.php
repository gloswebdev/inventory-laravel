@extends('layouts.app')

@section('header', 'Live Stock Report')

@section('content')
<div class="bg-white rounded shadow-md p-6">
    <div class="flex flex-wrap justify-between items-center mb-6 gap-4">
        <h3 class="text-xl font-bold text-gray-700">Live Inventory Across Branches</h3>
        <div class="flex items-center gap-2">
            <span class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></span>
            <span class="text-sm font-bold text-green-600 uppercase tracking-wider">Live from Algebra ERP</span>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="mb-6 bg-gray-50 p-4 rounded-lg border border-gray-200">
        <form action="{{ route('reports.live-stock') }}" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            @if(Auth::user()->hasFeature('reports', 'search'))
            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Search Product</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Name or Code..." class="w-full rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 py-2 px-3 border text-sm">
            </div>
            @endif
            @if(Auth::user()->hasFeature('reports', 'category_filter'))
            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Product Type</label>
                <select name="type_id" class="w-full rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 py-2 px-3 border text-sm">
                    <option value="">All Types</option>
                    @foreach($types as $type)
                        <option value="{{ $type->id }}" {{ request('type_id') == $type->id ? 'selected' : '' }}>{{ $type->type_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase mb-1">RM Type</label>
                <select name="rm_type" class="w-full rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 py-2 px-3 border text-sm">
                    <option value="">All RM Types</option>
                    @foreach($rmTypes as $rmType)
                        <option value="{{ $rmType }}" {{ request('rm_type') == $rmType ? 'selected' : '' }}>{{ $rmType }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            @if(Auth::user()->hasFeature('reports', 'display_unit'))
            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Display Unit</label>
                <select name="display_unit" class="w-full rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 py-2 px-3 border text-sm">
                    <option value="unit" {{ request('display_unit') == 'unit' ? 'selected' : '' }}>Units / Pcs</option>
                    <option value="kg" {{ request('display_unit') == 'kg' ? 'selected' : '' }}>kg / Ltr</option>
                </select>
            </div>
            @endif
            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Per Page</label>
                <select name="per_page" class="w-full rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 py-2 px-3 border text-sm">
                    <option value="20" {{ request('per_page') == 20 ? 'selected' : '' }}>20</option>
                    <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                    <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                    <option value="all" {{ request('per_page') == 'all' ? 'selected' : '' }}>All</option>
                </select>
            </div>
            @if(Auth::user()->hasFeature('reports', 'stock_filter'))
            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Stock Filter</label>
                <select name="stock_filter" class="w-full rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 py-2 px-3 border text-sm">
                    <option value="all" {{ request('stock_filter') == 'all' ? 'selected' : '' }}>Show All</option>
                    <option value="ignore_zero" {{ request('stock_filter') == 'ignore_zero' ? 'selected' : '' }}>Ignore 0 Stock</option>
                </select>
            </div>
            @endif
            <div class="flex gap-2">
                <button type="submit" class="flex-grow bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded shadow text-sm">
                    <i class="fas fa-filter mr-1"></i> Filter
                </button>
                @if(request()->anyFilled(['search', 'type_id', 'rm_type', 'display_unit', 'per_page', 'stock_filter']))
                    <a href="{{ route('reports.live-stock') }}" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded shadow text-sm">Clear</a>
                @endif
            </div>
        </form>
    </div>

    <div class="flex justify-between items-center mb-4">
        <div class="flex items-center gap-3">
            @if(Auth::user()->hasFeature('reports', 'branch_reorder'))
            <button type="button" onclick="openReorderModal()" class="bg-indigo-100 hover:bg-indigo-200 text-indigo-700 font-bold py-2 px-4 rounded shadow-sm text-sm flex items-center">
                <i class="fas fa-sort mr-2"></i> Reorder Branches
            </button>
            @endif
            <a href="{{ route('reports.live-stock', array_merge(request()->query(), ['refresh' => 1])) }}" 
               onclick="this.classList.add('pointer-events-none', 'opacity-60'); this.querySelector('i').classList.add('fa-spin');"
               class="bg-blue-100 hover:bg-blue-200 text-blue-700 font-bold py-2 px-4 rounded shadow-sm text-sm flex items-center transition-colors">
                <i class="fas fa-sync-alt mr-2"></i> Sync Stock Now
            </a>
        </div>
        <div class="flex gap-2">
            @if(Auth::user()->hasPermission('reports', 'excel'))
            <a href="{{ route('reports.live-stock.excel', request()->query()) }}" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded shadow text-sm flex items-center">
                <i class="fas fa-file-excel mr-2"></i> EXCEL
            </a>
            @endif
            @if(Auth::user()->hasPermission('reports', 'pdf'))
            <a href="{{ route('reports.live-stock.pdf', request()->query()) }}" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded shadow text-sm flex items-center">
                <i class="fas fa-file-pdf mr-2"></i> PDF
            </a>
            @endif
        </div>
    </div>

    {{-- Reorder Modal --}}
    <div id="reorderModal" class="fixed inset-0 bg-black bg-opacity-50 z-[100] hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
            <div class="p-6 border-b border-gray-100 flex justify-between items-center">
                <h3 class="text-lg font-black text-gray-800 uppercase tracking-tighter">Set Branch Order</h3>
                <button onclick="closeReorderModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            <div class="p-6">
                <p class="text-xs text-gray-500 mb-4 italic">Drag and drop branches to change their display order in reports.</p>
                <ul id="sortableBranches" class="space-y-2">
                    @foreach($branches as $branch)
                    <li data-id="{{ $branch->id }}" class="flex items-center gap-4 p-4 bg-gray-50 rounded-xl border border-gray-100 cursor-move hover:bg-indigo-50 hover:border-indigo-100 transition-all group">
                        <i class="fas fa-grip-lines text-gray-300 group-hover:text-indigo-300"></i>
                        <span class="text-sm font-bold text-gray-700">{{ $branch->name }}</span>
                        <span class="ml-auto text-[10px] font-black text-gray-300 uppercase">{{ $branch->code }}</span>
                    </li>
                    @endforeach
                </ul>
                <div class="mt-8 flex gap-3">
                    <button onclick="saveNewOrder()" class="flex-grow bg-blue-600 hover:bg-blue-700 text-white font-black py-4 rounded-xl shadow-lg shadow-blue-100 text-sm uppercase tracking-widest italic">
                        Confirm & Update
                    </button>
                    <button onclick="closeReorderModal()" class="px-6 py-4 bg-gray-100 text-gray-500 font-bold rounded-xl text-sm">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script>
        function openReorderModal() {
            document.getElementById('reorderModal').classList.remove('hidden');
            document.getElementById('reorderModal').classList.add('flex');
            
            new Sortable(document.getElementById('sortableBranches'), {
                animation: 150,
                ghostClass: 'bg-indigo-100'
            });
        }

        function closeReorderModal() {
            document.getElementById('reorderModal').classList.add('hidden');
            document.getElementById('reorderModal').classList.remove('flex');
        }

        async function saveNewOrder() {
            const list = document.getElementById('sortableBranches');
            const items = list.querySelectorAll('li');
            const order = Array.from(items).map(item => item.dataset.id);

            try {
                const response = await fetch("{{ route('settings.branches.reorder') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ order })
                });

                const data = await response.json();
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Error updating order');
                }
            } catch (e) {
                alert('System error. Please try again.');
            }
        }
    </script>

    <div class="overflow-x-auto">
        <table class="min-w-full bg-white border border-gray-300">
            <thead>
                <tr class="bg-gray-800 text-white uppercase text-[10px] leading-normal font-black">
                    <th class="py-3 px-4 text-left border-r border-gray-700 sticky left-0 bg-gray-800 z-10 w-64">Product Information</th>
                    @foreach($branches as $branch)
                        <th class="py-3 px-4 text-center border-r border-gray-700" colspan="2">{{ $branch->name }}</th>
                    @endforeach
                    <th class="py-3 px-4 text-center bg-indigo-900" colspan="2">Consolidated Total</th>
                </tr>
                <tr class="bg-gray-100 text-gray-600 uppercase text-[9px] leading-normal font-black border-b border-gray-300">
                    <th class="py-2 px-4 text-left border-r border-gray-300 sticky left-0 bg-gray-100 z-10">Name / Code</th>
                    @foreach($branches as $branch)
                        <th class="py-2 px-2 text-center border-r border-gray-200">Qty ({{ $displayUnit === 'kg' ? 'kg/Ltr' : 'Unit' }})</th>
                        <th class="py-2 px-2 text-center border-r border-gray-300">Boxes</th>
                    @endforeach
                    <th class="py-2 px-2 text-center border-r border-gray-200 bg-indigo-50">Total ({{ $displayUnit === 'kg' ? 'kg/Ltr' : 'Unit' }})</th>
                    <th class="py-2 px-2 text-center bg-indigo-50">Total Boxes</th>
                </tr>
            </thead>
            <tbody class="text-gray-700 text-xs font-medium">
                @foreach($reportData as $row)
                <tr class="border-b border-gray-200 hover:bg-blue-50 transition-colors">
                    <td class="py-3 px-4 text-left border-r border-gray-200 sticky left-0 bg-white group-hover:bg-blue-50 z-10">
                        <div class="font-bold text-gray-900">{{ $row['product']->name }}</div>
                        <div class="flex gap-2 mt-0.5">
                            <span class="text-[10px] font-black text-indigo-500 uppercase">{{ $row['product']->item_code }}</span>
                            @if($row['product']->rm_type)
                                <span class="bg-gray-200 px-1 rounded text-[9px] text-gray-600">{{ $row['product']->rm_type }}</span>
                            @endif
                        </div>
                        <div class="text-[9px] text-gray-400 italic">Pack: {{ $row['product']->pack_name }} ({{ $row['product']->uom }})</div>
                    </td>
                    @foreach($branches as $branch)
                        @php 
                            $stock = $row['branch_stocks'][$branch->code]; 
                            $isLowStock = false;
                            $alertLimit = (float)($row['product']->low_alert_quantity ?: 0);
                            
                            // Determine if low stock based on display unit
                            if ($displayUnit === 'kg') {
                                $isLowStock = $stock['qty'] <= ($alertLimit * $row['product']->weight_multiplier);
                            } else {
                                $isLowStock = $stock['qty'] <= $alertLimit;
                            }
                        @endphp
                        <td class="py-3 px-2 text-center border-r border-gray-100 {{ $isLowStock ? 'text-red-600 font-black' : ($stock['qty'] > 0 ? 'text-green-600 font-bold' : 'text-gray-300') }}">
                            {{ number_format($stock['qty'], 2) }}
                        </td>
                        <td class="py-3 px-2 text-center border-r border-gray-200 {{ $stock['boxes'] > 0 ? 'text-blue-600 font-black' : 'text-gray-300' }}">
                            {{ number_format($stock['boxes'], 2) }}
                        </td>
                    @endforeach
                    @php
                        $totalLimit = (float)($row['product']->low_alert_quantity ?: 0);
                        if ($displayUnit === 'kg') {
                            $isTotalLow = $row['total_qty'] <= ($totalLimit * $row['product']->weight_multiplier);
                        } else {
                            $isTotalLow = $row['total_qty'] <= $totalLimit;
                        }
                    @endphp
                    <td class="py-3 px-2 text-center border-r border-gray-200 bg-indigo-50 font-black {{ $isTotalLow ? 'text-red-600' : 'text-indigo-900' }}">
                        {{ number_format($row['total_qty'], 2) }}
                    </td>
                    <td class="py-3 px-2 text-center bg-indigo-50 font-900 text-indigo-900">
                        {{ number_format($row['total_boxes'], 1) }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if(method_exists($products, 'links'))
    <div class="mt-6">
        {{ $products->links() }}
    </div>
    @endif
</div>

<style>
    /* Sticky Column Shadow */
    .sticky {
        position: sticky;
        left: 0;
        z-index: 5;
    }
    th.sticky { z-index: 15; }
    
    /* Chrome-specific shadow for sticky column */
    td.sticky::after {
        content: '';
        position: absolute;
        top: 0;
        right: -5px;
        bottom: 0;
        width: 5px;
        background: linear-gradient(to right, rgba(0,0,0,0.05), transparent);
        pointer-events: none;
    }
</style>
@endsection
