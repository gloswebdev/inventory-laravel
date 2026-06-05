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
        <form action="{{ route('reports.live-stock') }}" method="GET" id="liveStockFilterForm">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                @if(Auth::user()->hasFeature('reports', 'search'))
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Search Product</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Name or Code..." class="w-full rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 py-2 px-3 border text-sm">
                </div>
                @endif
                @if(Auth::user()->hasFeature('reports', 'category_filter'))
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Product Type</label>
                    <select id="typeIdFilter" name="type_id" class="w-full rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 py-2 px-3 border text-sm">
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
            </div>

            {{-- Selective Products Multi-Pick Row --}}
            <div class="mt-4 border-t border-gray-200 pt-4">
                <div class="flex items-end gap-4">
                    <div class="flex-grow" style="position:relative">
                        <label class="block text-xs font-bold text-gray-600 uppercase mb-1" style="display:flex;justify-content:space-between;align-items:center">
                            <span>Selective Products <span class="text-gray-400 font-normal normal-case">(leave empty = show all)</span></span>
                            <span id="pms-badge" style="background:#6366f1;color:#fff;border-radius:9999px;font-size:0.6rem;font-weight:900;padding:1px 7px;display:none;">0 selected</span>
                        </label>
                        {{-- Custom Multi-Select Widget --}}
                        <div id="pms-wrapper" style="border:1px solid #d1d5db;border-radius:6px;background:#fff;min-height:38px;cursor:text;padding:4px 8px 4px 6px;display:flex;flex-wrap:wrap;align-items:center;gap:3px;box-shadow:0 1px 2px rgba(0,0,0,0.05);" onclick="document.getElementById('pms-search').focus()">
                            <div id="pms-tags" style="display:contents"></div>
                            <input id="pms-search" type="text" placeholder="Type to search and pick products..." autocomplete="off"
                                style="border:none;outline:none;font-size:0.8rem;min-width:200px;flex:1;padding:2px 4px;background:transparent;"
                                oninput="pmsSearch(this.value)"
                                onfocus="pmsOpen()"
                                onkeydown="pmsKeydown(event)">
                        </div>
                        {{-- Dropdown (rendered via JS into body) --}}
                        <div id="pms-dropdown" style="display:none;position:fixed;z-index:99999;background:#fff;border:1px solid #e2e8f0;border-radius:8px;box-shadow:0 10px 30px rgba(0,0,0,0.15);max-height:260px;overflow-y:auto;min-width:340px;">
                            <div id="pms-list"></div>
                        </div>
                        {{-- Hidden inputs for form submission --}}
                        <div id="pms-inputs"></div>
                    </div>
                    <div class="flex gap-2 flex-shrink-0">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-5 rounded shadow text-sm flex items-center gap-1">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                        @if(request()->anyFilled(['search', 'type_id', 'rm_type', 'display_unit', 'per_page', 'stock_filter']) || request()->filled('product_ids'))
                            <a href="{{ route('reports.live-stock') }}" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded shadow text-sm flex items-center gap-1">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        @endif
                    </div>
                </div>
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

    <div class="overflow-x-auto rounded-lg border border-gray-300 shadow-sm" id="stockTableWrapper">
        <table class="min-w-full bg-white" style="border-collapse: separate; border-spacing: 0;" id="stockTable">
            <thead id="stockThead">
                <tr class="bg-gray-800 text-white uppercase text-[10px] leading-normal font-black">
                    <th class="py-3 px-4 text-left border-r border-gray-700 sticky left-0 bg-gray-800 z-40 w-64" style="box-shadow: 2px 0 4px rgba(0,0,0,0.15);">Product Information</th>
                    @foreach($branches as $branch)
                        <th class="py-3 px-4 text-center border-r border-b border-gray-700" colspan="2">{{ $branch->name }}</th>
                    @endforeach
                    <th class="py-3 px-4 text-center bg-indigo-900 border-b border-indigo-800" colspan="2">Consolidated Total</th>
                </tr>
                <tr class="bg-gray-100 text-gray-600 uppercase text-[9px] leading-normal font-black">
                    <th class="py-2 px-4 text-left border-r border-b border-gray-300 sticky left-0 bg-gray-100 z-40" style="box-shadow: 2px 0 4px rgba(0,0,0,0.08);">Name / Code</th>
                    @foreach($branches as $branch)
                        <th class="py-2 px-2 text-center border-r border-b border-gray-200">Qty ({{ $displayUnit === 'kg' ? 'kg/Ltr' : 'Unit' }})</th>
                        <th class="py-2 px-2 text-center border-r border-b border-gray-300">Boxes</th>
                    @endforeach
                    <th class="py-2 px-2 text-center border-r border-b border-gray-200 bg-indigo-50">Total ({{ $displayUnit === 'kg' ? 'kg/Ltr' : 'Unit' }})</th>
                    <th class="py-2 px-2 text-center border-b border-indigo-100 bg-indigo-50">Total Boxes</th>
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
    /* Sticky column left shadow */
    td.sticky, th.sticky {
        position: sticky !important;
        left: 0;
    }
    td.sticky::after, th.sticky::after {
        content: ''; position: absolute; top: 0; right: -6px; bottom: 0; width: 6px;
        background: linear-gradient(to right, rgba(0,0,0,0.07), transparent);
        pointer-events: none;
    }
    /* Custom multi-select */
    #pms-wrapper:focus-within { border-color: #3b82f6 !important; box-shadow: 0 0 0 2px rgba(59,130,246,0.25) !important; }
    .pms-tag {
        display:inline-flex;align-items:center;gap:3px;
        background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;
        border-radius:4px;font-size:0.68rem;font-weight:700;padding:1px 4px 1px 6px;
    }
    .pms-tag button { border:none;background:none;cursor:pointer;color:#6b7280;font-size:0.75rem;line-height:1;padding:0 1px; }
    .pms-tag button:hover { color:#dc2626; }
    .pms-item {
        padding:7px 12px;cursor:pointer;font-size:0.8rem;display:flex;align-items:center;gap:6px;
        border-bottom:1px solid #f1f5f9;
    }
    .pms-item:last-child { border-bottom:none; }
    .pms-item:hover, .pms-item.highlighted { background:#eff6ff; }
    .pms-item.selected { background:#f0fdf4; }
    .pms-item .pms-name { font-weight:600;color:#1e293b;flex:1; }
    .pms-item .pms-code { color:#6366f1;font-weight:800;font-size:0.65rem; }
    .pms-item .pms-pack {
        font-size:0.6rem;font-weight:700;font-style:normal;
        background:#fef3c7;color:#92400e;border:1px solid #fcd34d;
        border-radius:3px;padding:1px 5px;margin-left:2px;white-space:nowrap;
    }
    .pms-item .pms-check { width:14px;height:14px;border-radius:3px;border:2px solid #d1d5db;flex-shrink:0; }
    .pms-item.selected .pms-check { background:#22c55e;border-color:#22c55e; position:relative; }
    .pms-item.selected .pms-check::after { content:'✓';position:absolute;top:-2px;left:1px;color:#fff;font-size:10px;font-weight:900; }
    .pms-empty { padding:12px;text-align:center;color:#94a3b8;font-size:0.78rem;font-style:italic; }
</style>

<script>
    @php
        $allProductsForJs = $allProducts->map(function($p) {
            return [
                'id'              => (string) $p->id,
                'name'            => $p->name,
                'item_code'       => $p->item_code,
                'pack_name'       => $p->pack_name ?? '',
                'product_type_id' => (string) ($p->product_type_id ?? ''),
            ];
        })->values()->all();
        $preSelectedIdsForJs = array_map('strval', request()->input('product_ids', []));
    @endphp

    const PMS_ALL   = @json($allProductsForJs);
    const PMS_INIT  = @json($preSelectedIdsForJs);

    let pmsSelected    = new Map(); // id -> product object
    let pmsFiltered    = [];
    let pmsHighlight   = -1;
    let pmsOpen_flag   = false;
    let pmsJustToggled = false; // prevents clickOutside closing after DOM re-render

    function pmsGetFiltered(typeId) {
        if (!typeId) return PMS_ALL;
        return PMS_ALL.filter(p => p.product_type_id === String(typeId));
    }

    function pmsRenderTags() {
        const tags = document.getElementById('pms-tags');
        tags.innerHTML = '';
        pmsSelected.forEach((p, id) => {
            const t = document.createElement('span');
            t.className = 'pms-tag';
            t.innerHTML = `${p.item_code}${p.pack_name ? ' <em style="font-style:normal;font-weight:400;">· ' + p.pack_name + '</em>' : ''}<button type="button" onclick="pmsRemove('${id}')" title="Remove">×</button>`;
            tags.appendChild(t);
        });
        // Update hidden inputs
        const inp = document.getElementById('pms-inputs');
        inp.innerHTML = '';
        pmsSelected.forEach((p, id) => {
            const h = document.createElement('input');
            h.type = 'hidden'; h.name = 'product_ids[]'; h.value = id;
            inp.appendChild(h);
        });
        // Badge
        const badge = document.getElementById('pms-badge');
        const count = pmsSelected.size;
        badge.textContent = count + ' selected';
        badge.style.display = count > 0 ? 'inline-block' : 'none';
    }

    function pmsRenderList(query) {
        const list = document.getElementById('pms-list');
        const q = (query || '').toLowerCase().trim();
        const shown = q
            ? pmsFiltered.filter(p =>
                p.name.toLowerCase().includes(q) ||
                p.item_code.toLowerCase().includes(q) ||
                (p.pack_name && p.pack_name.toLowerCase().includes(q))
              )
            : pmsFiltered;

        if (shown.length === 0) {
            list.innerHTML = '<div class="pms-empty">No products found</div>';
            pmsHighlight = -1;
            return;
        }
        list.innerHTML = '';
        shown.forEach((p, idx) => {
            const isSel = pmsSelected.has(p.id);
            const div = document.createElement('div');
            div.className = 'pms-item' + (isSel ? ' selected' : '') + (idx === pmsHighlight ? ' highlighted' : '');
            div.dataset.id = p.id;
            div.dataset.idx = idx;
            div.innerHTML = `<span class="pms-check"></span><span class="pms-name">${esc(p.name)}</span><span class="pms-code">[${esc(p.item_code)}]</span>${p.pack_name ? '<span class="pms-pack">' + esc(p.pack_name) + '</span>' : ''}`;
            div.onmousedown = (e) => { e.preventDefault(); pmsToggle(p); };
            list.appendChild(div);
        });
    }

    function esc(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    function pmsToggle(p) {
        // Set flag so clickOutside doesn't fire after DOM re-render detaches target
        pmsJustToggled = true;
        setTimeout(() => { pmsJustToggled = false; }, 100);

        if (pmsSelected.has(p.id)) {
            pmsSelected.delete(p.id);
        } else {
            pmsSelected.set(p.id, p);
        }
        pmsRenderTags();
        pmsRenderList(document.getElementById('pms-search').value);
        // Keep input focused so dropdown stays open
        document.getElementById('pms-search').focus();
    }

    function pmsRemove(id) {
        pmsSelected.delete(id);
        pmsRenderTags();
        pmsRenderList(document.getElementById('pms-search').value);
    }

    function pmsOpen() {
        if (pmsOpen_flag) return;
        pmsOpen_flag = true;
        const dd = document.getElementById('pms-dropdown');
        const wrap = document.getElementById('pms-wrapper');
        const rect = wrap.getBoundingClientRect();
        dd.style.top  = (rect.bottom + 4) + 'px';
        dd.style.left = rect.left + 'px';
        dd.style.width = Math.max(rect.width, 340) + 'px';
        dd.style.display = 'block';
        pmsRenderList(document.getElementById('pms-search').value);
        document.addEventListener('mousedown', pmsClickOutside);
        window.addEventListener('scroll', pmsReposition, true);
    }

    function pmsClose() {
        pmsOpen_flag = false;
        document.getElementById('pms-dropdown').style.display = 'none';
        document.removeEventListener('mousedown', pmsClickOutside);
        window.removeEventListener('scroll', pmsReposition, true);
    }

    function pmsReposition() {
        const wrap = document.getElementById('pms-wrapper');
        const dd   = document.getElementById('pms-dropdown');
        const rect = wrap.getBoundingClientRect();
        dd.style.top  = (rect.bottom + 4) + 'px';
        dd.style.left = rect.left + 'px';
    }

    function pmsClickOutside(e) {
        if (pmsJustToggled) return; // ignore right after a toggle
        const dd   = document.getElementById('pms-dropdown');
        const wrap = document.getElementById('pms-wrapper');
        if (!dd.contains(e.target) && !wrap.contains(e.target)) pmsClose();
    }

    function pmsSearch(val) {
        if (!pmsOpen_flag) pmsOpen();
        pmsHighlight = -1;
        pmsRenderList(val);
    }

    function pmsKeydown(e) {
        const items = document.querySelectorAll('#pms-list .pms-item');
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            pmsHighlight = Math.min(pmsHighlight + 1, items.length - 1);
            items.forEach((el,i) => el.classList.toggle('highlighted', i === pmsHighlight));
            if (items[pmsHighlight]) items[pmsHighlight].scrollIntoView({block:'nearest'});
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            pmsHighlight = Math.max(pmsHighlight - 1, 0);
            items.forEach((el,i) => el.classList.toggle('highlighted', i === pmsHighlight));
            if (items[pmsHighlight]) items[pmsHighlight].scrollIntoView({block:'nearest'});
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (pmsHighlight >= 0 && items[pmsHighlight]) items[pmsHighlight].dispatchEvent(new MouseEvent('mousedown'));
        } else if (e.key === 'Escape') {
            pmsClose();
        }
    }

    function pmsLoadType(typeId, restoreIds) {
        pmsFiltered = pmsGetFiltered(typeId);
        // On type change, keep only selected items that still match new type
        const filteredSet = new Set(pmsFiltered.map(p => p.id));
        if (restoreIds) {
            pmsSelected.clear();
            restoreIds.forEach(id => {
                const found = pmsFiltered.find(p => p.id === id);
                if (found) pmsSelected.set(id, found);
            });
        } else {
            // type changed manually — clear selection
            pmsSelected.clear();
        }
        pmsRenderTags();
        if (pmsOpen_flag) pmsRenderList(document.getElementById('pms-search').value);
    }

    document.addEventListener('DOMContentLoaded', function () {
        const typeSelect = document.getElementById('typeIdFilter');
        const initType   = typeSelect ? typeSelect.value : '';

        // Initial load with pre-selected ids from URL
        pmsLoadType(initType, PMS_INIT);

        if (typeSelect) {
            typeSelect.addEventListener('change', function () {
                pmsLoadType(this.value, null);
            });
        }
    });
</script>

{{-- Sticky Table Header (JS-based, works with overflow-x wrapper) --}}
<div id="stockTheadClone" style="position:fixed;z-index:50;overflow:hidden;display:none;box-shadow:0 4px 14px rgba(0,0,0,0.15);pointer-events:none;"></div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const mainEl  = document.querySelector('main.content-scroll');
        const thead   = document.getElementById('stockThead');
        const table   = document.getElementById('stockTable');
        const wrapper = document.getElementById('stockTableWrapper');
        const clone   = document.getElementById('stockTheadClone');

        if (!mainEl || !thead || !table || !wrapper || !clone) return;

        const TOPBAR_H  = 53;   // px — height of the fixed topbar
        const SIDEBAR_W = 220;  // px — width of sidebar

        function buildClone() {
            // Get wrapper position to align clone correctly
            const wRect = wrapper.getBoundingClientRect();

            // Clone the thead HTML
            const clonedHtml = thead.cloneNode(true);

            // Copy exact cell widths from original header cells
            const origRows = thead.querySelectorAll('tr');
            const clonRows = clonedHtml.querySelectorAll('tr');

            origRows.forEach((origRow, rIdx) => {
                const origCells = origRow.querySelectorAll('th');
                const clonCells = clonRows[rIdx] ? clonRows[rIdx].querySelectorAll('th') : [];
                origCells.forEach((origCell, cIdx) => {
                    if (clonCells[cIdx]) {
                        const w = origCell.getBoundingClientRect().width;
                        clonCells[cIdx].style.width  = w + 'px';
                        clonCells[cIdx].style.minWidth = w + 'px';
                        clonCells[cIdx].style.maxWidth = w + 'px';
                        // Remove sticky left from cloned header — not needed in clone
                        clonCells[cIdx].style.position = '';
                    }
                });
            });

            // Build table around the cloned thead
            const cloneTable = document.createElement('table');
            cloneTable.style.cssText = `
                width: ${table.offsetWidth}px;
                border-collapse: separate;
                border-spacing: 0;
                table-layout: fixed;
            `;
            cloneTable.appendChild(clonedHtml);

            clone.innerHTML = '';
            clone.appendChild(cloneTable);

            // Position the clone
            clone.style.top   = TOPBAR_H + 'px';
            clone.style.left  = wRect.left + 'px';
            clone.style.width = wRect.width + 'px';

            // Apply current scroll offset
            syncScroll();
        }

        function syncScroll() {
            const t = clone.querySelector('table');
            if (t) t.style.transform = `translateX(-${wrapper.scrollLeft}px)`;
        }

        // Sync horizontal scroll
        wrapper.addEventListener('scroll', syncScroll);

        // Show/hide on vertical scroll
        mainEl.addEventListener('scroll', function () {
            const theadBottom = thead.getBoundingClientRect().bottom;

            if (theadBottom < TOPBAR_H) {
                buildClone();
                clone.style.display = 'block';
            } else {
                clone.style.display = 'none';
            }
        });

        // Rebuild on resize
        window.addEventListener('resize', function () {
            if (clone.style.display !== 'none') buildClone();
        });
    });
</script>
@endsection
