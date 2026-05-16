@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-[#f8fafc] py-8">
    <div class="max-w-7xl mx-auto">
        <div class="bg-white rounded-3xl shadow-sm border border-slate-100/80 p-8 mb-8 overflow-hidden relative flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="flex items-center gap-4 relative z-10">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-lg shadow-indigo-200/50 flex-shrink-0">
                    <i class="fas fa-microchip text-white text-xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-black text-slate-800 tracking-tight leading-tight">Process Indents</h1>
                    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mt-1">Select an indent to view cross-branch stock distribution</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow-sm border border-slate-100/80 p-6 mb-8 relative z-20">
            <form action="{{ route('indent.process.list') }}" method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-5 items-end">
                <div class="space-y-1.5">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">From Date</label>
                    <input type="date" name="from_date" value="{{ request('from_date') }}" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 font-bold text-slate-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition shadow-sm outline-none">
                </div>
                <div class="space-y-1.5">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">To Date</label>
                    <input type="date" name="to_date" value="{{ request('to_date') }}" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 font-bold text-slate-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition shadow-sm outline-none">
                </div>
                <div class="space-y-1.5">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Target Branch</label>
                    <select name="branch_code" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 font-bold text-slate-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition shadow-sm outline-none">
                        <option value="">All Branches</option>
                        @foreach($branches as $branch)
                        <option value="{{ $branch->code }}" {{ request('branch_code') == $branch->code ? 'selected' : '' }}>
                            {{ $branch->name }} ({{ $branch->code }})
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-1.5">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Creator</label>
                    <select name="user_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 font-bold text-slate-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition shadow-sm outline-none">
                        <option value="">All Users</option>
                        @foreach($users as $user)
                        <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>
                            {{ $user->name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-1.5">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Status</label>
                    <select name="status" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 font-bold text-slate-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition shadow-sm outline-none">
                        <option value="">All Statuses</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="partly completed" {{ request('status') == 'partly completed' ? 'selected' : '' }}>Partly Completed</option>
                        <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Fully Completed</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="flex-1 bg-gradient-to-r from-indigo-500 to-purple-600 text-white px-6 py-2.5 rounded-xl font-bold hover:from-indigo-600 hover:to-purple-700 transition shadow-lg shadow-indigo-200/50 text-xs tracking-wider uppercase">
                        <i class="fas fa-filter mr-2"></i>Filter
                    </button>
                    <a href="{{ route('indent.process.list') }}" class="bg-white border border-slate-200 text-slate-500 px-4 py-2.5 rounded-xl flex items-center justify-center hover:bg-slate-50 transition shadow-sm">
                        <i class="fas fa-redo-alt"></i>
                    </a>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-[2.5rem] shadow-xl shadow-indigo-100/50 overflow-hidden border border-indigo-50/50">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
        <div class="bg-white rounded-3xl shadow-sm overflow-hidden border border-slate-100/80">
            <div class="overflow-x-auto relative">
                <table class="w-full text-left border-collapse">
                    <thead class="sticky top-0 z-10 bg-slate-50 shadow-sm before:content-[''] before:absolute before:bottom-0 before:left-0 before:w-full before:h-px before:bg-slate-200">
                        <tr>
                            <th class="px-8 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-200">Date & Branch</th>
                            <th class="px-8 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center border-b border-slate-200">Creator</th>
                            <th class="px-8 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center border-b border-slate-200">Status</th>
                            <th class="px-8 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center border-b border-slate-200">Items</th>
                            <th class="px-8 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right border-b border-slate-200">Volume</th>
                            <th class="px-8 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right border-b border-slate-200">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($history as $indent)
                        <tr class="hover:bg-slate-50/70 transition-colors group">
                            <td class="px-8 py-5">
                                <div class="font-bold text-slate-800 text-sm mb-1">{{ date('d M, Y', strtotime($indent->indent_date)) }}</div>
                                <span class="bg-indigo-50 text-indigo-600 font-bold px-2 py-0.5 rounded text-[10px] uppercase border border-indigo-100">
                                    {{ $indent->branch_name }}
                                </span>
                            </td>
                            <td class="px-8 py-5 text-center">
                                <div class="text-sm font-bold text-slate-700">{{ $indent->user->name ?? 'System' }}</div>
                            </td>
                            <td class="px-8 py-5 text-center text-[10px] font-black">
                                @if($indent->status == 'completed')
                                <span class="bg-emerald-50 text-emerald-600 px-2.5 py-1 rounded-lg text-[10px] font-black tracking-widest uppercase border border-emerald-200">Completed</span>
                                @elseif($indent->status == 'partly completed')
                                <span class="bg-blue-50 text-blue-600 px-2.5 py-1 rounded-lg text-[10px] font-black tracking-widest uppercase border border-blue-200">Partly</span>
                                @else
                                <span class="bg-amber-50 text-amber-600 px-2.5 py-1 rounded-lg text-[10px] font-black tracking-widest uppercase border border-amber-200">Pending</span>
                                @endif
                            </td>
                            <td class="px-8 py-5 text-center">
                                <div class="text-sm font-bold text-slate-500">{{ $indent->items_count }} Products</div>
                            </td>
                            <td class="px-8 py-5 text-right">
                                <span class="text-xl font-black tracking-tight text-slate-800">{{ number_format($indent->total_boxes, 0) }}</span>
                                <span class="text-[9px] font-black text-slate-400 uppercase block -mt-0.5 tracking-widest">Boxes</span>
                            </td>
                            <td class="px-8 py-5 text-right">
                                <div class="flex justify-end gap-2 items-center">
                                    <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button onclick="viewIndent({{ $indent->id }})" title="View" class="w-8 h-8 flex items-center justify-center bg-indigo-50 text-indigo-600 rounded-lg hover:bg-indigo-600 hover:text-white transition shadow-sm border border-indigo-100/50"><i class="fas fa-eye text-xs"></i></button>
                                        <button onclick="viewProgress({{ $indent->id }})" title="View Progress (Asked vs Completed)" class="w-8 h-8 flex items-center justify-center bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-600 hover:text-white transition shadow-sm border border-blue-100/50"><i class="fas fa-list-check text-xs"></i></button>
                                        @if(Auth::user()->hasPermission('planning_process', 'print'))
                                        <button onclick="printIndent({{ $indent->id }})" title="Print" class="w-8 h-8 flex items-center justify-center bg-indigo-50 text-indigo-600 rounded-lg hover:bg-indigo-600 hover:text-white transition shadow-sm border border-indigo-100/50"><i class="fas fa-print text-xs"></i></button>
                                        @endif
                                        
                                        @if(Auth::user()->hasPermission('planning_process', 'excel'))
                                        <button onclick="exportExcel({{ $indent->id }})" title="Excel" class="w-8 h-8 flex items-center justify-center bg-emerald-50 text-emerald-600 rounded-lg hover:bg-emerald-600 hover:text-white transition shadow-sm border border-emerald-100/50"><i class="fas fa-file-excel text-xs"></i></button>
                                        @endif

                                        @if(Auth::user()->hasPermission('planning_process', 'pdf'))
                                        <button onclick="exportPdf({{ $indent->id }})" title="PDF" class="w-8 h-8 flex items-center justify-center bg-red-50 text-red-600 rounded-lg hover:bg-red-600 hover:text-white transition shadow-sm border border-red-100/50"><i class="fas fa-file-pdf text-xs"></i></button>
                                        @endif
                                    </div>
                                    
                                    <div class="h-8 w-px bg-slate-200 mx-1"></div>
                                    
                                    @if(Auth::user()->hasPermission('planning_process', 'edit'))
                                    <a href="{{ route('indent.process', $indent->id) }}" class="inline-flex items-center gap-2 bg-gradient-to-r from-amber-500 to-orange-500 text-white px-5 py-2.5 rounded-xl font-bold transition shadow-lg shadow-amber-200/50 hover:from-amber-600 hover:to-orange-600 uppercase text-[11px] tracking-wider">
                                        <i class="fas fa-microchip"></i> Process Now
                                    </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="px-8 py-20 text-center text-slate-400 font-medium">No indents available for processing.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Comparison / Progress Modal -->
<!-- Comparison / Progress Modal -->
<div id="progressModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
        <div class="p-8 border-b border-slate-100 flex justify-between items-center bg-slate-50 relative">
            <div>
                <h3 class="text-xl font-black tracking-tight text-slate-800">Indent Completion Progress</h3>
                <p id="progressModalBranch" class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1"></p>
            </div>
            <button onclick="closeProgressModal()" class="text-slate-400 hover:text-slate-600 transition p-2 hover:bg-white rounded-full bg-white shadow-sm border border-slate-200 absolute top-8 right-8">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
        <div class="p-8 overflow-y-auto custom-scrollbar flex-1">
            <table class="w-full text-left">
                <thead class="sticky top-0 bg-white z-10 before:content-[''] before:absolute before:bottom-0 before:left-0 before:w-full before:h-px before:bg-slate-200">
                    <tr>
                        <th class="py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest bg-white border-b border-slate-200">Product</th>
                        <th class="py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center bg-white border-b border-slate-200">Asked (Box)</th>
                        <th class="py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center bg-white border-b border-slate-200">Completed (Box)</th>
                        <th class="py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right bg-white border-b border-slate-200">Status</th>
                    </tr>
                </thead>
                <tbody id="progressTableBody" class="divide-y divide-slate-100">
                    <!-- Dynamic Rows -->
                </tbody>
            </table>
        </div>
        <div class="p-6 border-t border-slate-100 bg-slate-50 flex justify-end">
            <button onclick="closeProgressModal()" class="bg-gradient-to-r from-indigo-500 to-purple-600 text-white px-8 py-3.5 rounded-xl font-bold hover:from-indigo-600 hover:to-purple-700 transition shadow-lg shadow-indigo-200/50 uppercase text-sm tracking-wider">
                Got it
            </button>
        </div>
    </div>
</div>

<!-- View Indent Modal -->
<div id="viewModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
        <div class="p-8 border-b border-slate-100 flex justify-between items-center bg-slate-50 relative">
            <div>
                <h3 class="text-xl font-black tracking-tight text-slate-800">Indent Details</h3>
                <p id="modalBranch" class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1"></p>
            </div>
            <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600 transition p-2 hover:bg-white rounded-full bg-white shadow-sm border border-slate-200 absolute top-8 right-8">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
        <div class="p-8 overflow-y-auto custom-scrollbar flex-1">
            <table class="w-full text-left">
                <thead class="sticky top-0 bg-white z-10 before:content-[''] before:absolute before:bottom-0 before:left-0 before:w-full before:h-px before:bg-slate-200">
                    <tr>
                        <th class="py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest bg-white border-b border-slate-200">Product</th>
                        <th class="py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center bg-white border-b border-slate-200">Live Stock</th>
                        <th class="py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center bg-white border-b border-slate-200">Required</th>
                        <th class="py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right bg-white border-b border-slate-200">Final Boxes</th>
                    </tr>
                </thead>
                <tbody id="modalTableBody" class="divide-y divide-slate-100">
                    <!-- Dynamic Rows -->
                </tbody>
            </table>
        </div>
        <div class="p-6 border-t border-slate-100 bg-slate-50 flex justify-between items-center">
            <div class="text-[10px] text-slate-400 font-bold uppercase tracking-widest" id="modalMeta"></div>
            <div class="flex gap-3">
                <button id="modalPrintBtn" class="bg-indigo-50 text-indigo-600 border border-indigo-200 px-6 py-3 rounded-xl font-bold hover:bg-indigo-100 transition shadow-sm uppercase tracking-wider text-xs">
                    <i class="fas fa-print mr-2"></i>Print
                </button>
                <button onclick="closeModal()" class="bg-white border border-slate-200 text-slate-600 px-6 py-3 rounded-xl font-bold hover:bg-slate-50 transition shadow-sm uppercase tracking-wider text-xs">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    function viewIndent(id) {
        const modal = document.getElementById('viewModal');
        const loader = `<tr class="modal-loading"><td colspan="4" class="py-10 text-center text-gray-400 italic">Loading details...</td></tr>`;
        document.getElementById('modalTableBody').innerHTML = loader;
        modal.classList.remove('hidden');

        fetch(`{{ url('indent-api/show') }}/${id}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const indent = data.indent;
                    document.getElementById('modalBranch').innerText = `${indent.branch_name} (${indent.branch_code}) - ${new Date(indent.indent_date).toLocaleDateString()}`;
                    document.getElementById('modalMeta').innerText = `Created by: ${indent.user?.name || 'System'} | ID: #${String(indent.id).padStart(5, '0')}`;
                    
                    let html = '';
                    indent.items.forEach(item => {
                        html += `
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="py-4">
                                    <div class="font-bold text-slate-800 text-sm">${item.product_name}</div>
                                    <div class="text-[9px] text-slate-400 font-semibold uppercase tracking-widest mt-0.5">${item.product?.pack_name || ''}</div>
                                </td>
                                <td class="py-4 text-center">
                                    <div class="text-xs font-black text-emerald-600">${parseFloat(item.stock_box).toFixed(2)} BOX</div>
                                </td>
                                <td class="py-4 text-center font-bold text-slate-700">
                                    ${item.demand_qty} <span class="text-[9px] uppercase tracking-widest text-slate-400">${item.demand_unit}</span>
                                </td>
                                <td class="py-4 text-right font-black text-lg text-indigo-600">
                                    ${parseFloat(item.final_qty_box).toFixed(0)}
                                </td>
                            </tr>
                        `;
                    });
                    document.getElementById('modalTableBody').innerHTML = html;
                    document.getElementById('modalPrintBtn').onclick = () => printIndent(id);
                }
            })
            .catch(e => {
                console.error(e);
                alert('Error loading indent details');
                closeModal();
            });
    }

    function closeModal() {
        document.getElementById('viewModal').classList.add('hidden');
    }

    function viewProgress(id) {
        const modal = document.getElementById('progressModal');
        const loader = `<tr><td colspan="4" class="py-10 text-center text-gray-400 italic">Analyzing progress...</td></tr>`;
        document.getElementById('progressTableBody').innerHTML = loader;
        modal.classList.remove('hidden');

        fetch(`{{ url('indent-api/show') }}/${id}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const indent = data.indent;
                    document.getElementById('progressModalBranch').innerText = `${indent.branch_name} (${indent.branch_code}) | ${new Date(indent.indent_date).toLocaleDateString()}`;
                    
                    let html = '';
                    indent.items.forEach(item => {
                        const asked = parseFloat(item.final_qty_box);
                        const completed = parseFloat(item.completed_qty || 0);
                        let statusHtml = '';
                        
                        if (completed >= asked && asked > 0) {
                            statusHtml = '<span class="bg-emerald-50 text-emerald-600 px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-widest border border-emerald-200">Fully Done</span>';
                        } else if (completed > 0) {
                            statusHtml = '<span class="bg-blue-50 text-blue-600 px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-widest border border-blue-200">Partial (' + Math.round((completed/asked)*100) + '%)</span>';
                        } else {
                            statusHtml = '<span class="bg-amber-50 text-amber-600 px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-widest border border-amber-200">Pending</span>';
                        }

                        html += `
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="py-4">
                                    <div class="font-bold text-slate-800 text-sm">${item.product_name}</div>
                                    <div class="text-[9px] text-slate-400 font-semibold uppercase tracking-widest mt-0.5">${item.product?.pack_name || ''}</div>
                                </td>
                                <td class="py-4 text-center font-bold text-slate-600 text-sm">${asked.toFixed(0)}</td>
                                <td class="py-4 text-center font-black text-indigo-600 text-lg">${completed.toFixed(0)}</td>
                                <td class="py-4 text-right">${statusHtml}</td>
                            </tr>
                        `;
                    });
                    document.getElementById('progressTableBody').innerHTML = html;
                }
            })
            .catch(e => {
                console.error(e);
                alert('Error loading progress details');
                closeProgressModal();
            });
    }

    function closeProgressModal() {
        document.getElementById('progressModal').classList.add('hidden');
    }

    function printIndent(id) {
        window.open(`{{ url('indent/show') }}/${id}/print`, '_blank');
    }

    function exportExcel(id) {
        window.location.href = `{{ url('indent/show') }}/${id}/excel`;
    }

    function exportPdf(id) {
        window.location.href = `{{ url('indent/show') }}/${id}/pdf`;
    }
</script>

<style>
    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: #f8fafc; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }
</style>
@endsection
