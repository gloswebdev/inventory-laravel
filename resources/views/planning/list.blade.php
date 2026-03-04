@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-[#f8fafc] py-8">
    <div class="max-w-7xl mx-auto">
        <div class="bg-white rounded-[2.5rem] shadow-xl shadow-indigo-100/50 p-8 mb-8 border border-indigo-50/50 overflow-hidden relative">
            <div class="absolute top-0 right-0 w-64 h-64 bg-indigo-50/30 rounded-full -mr-32 -mt-32 blur-3xl"></div>
            
            <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div>
                    <div class="flex items-center gap-3 mb-2">
                        <div class="bg-amber-500 text-white p-3 rounded-2xl shadow-lg shadow-amber-200">
                            <i class="fas fa-microchip text-xl"></i>
                        </div>
                        <h1 class="text-3xl font-black text-gray-900 italic tracking-tighter uppercase">Process Indents</h1>
                    </div>
                    <p class="text-gray-500 font-bold text-sm ml-14 uppercase tracking-widest">Select an indent to view cross-branch stock distribution</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-[2.5rem] shadow-xl shadow-indigo-100/50 p-8 mb-8 border border-indigo-50/50">
            <form action="{{ route('indent.process.list') }}" method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
                <div class="space-y-2">
                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">From Date</label>
                    <input type="date" name="from_date" value="{{ request('from_date') }}" class="w-full bg-gray-50 border-2 border-gray-100 rounded-xl px-4 py-2.5 font-bold text-gray-900 focus:border-indigo-500 focus:ring-0 transition">
                </div>
                <div class="space-y-2">
                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">To Date</label>
                    <input type="date" name="to_date" value="{{ request('to_date') }}" class="w-full bg-gray-50 border-2 border-gray-100 rounded-xl px-4 py-2.5 font-bold text-gray-900 focus:border-indigo-500 focus:ring-0 transition">
                </div>
                <div class="space-y-2">
                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Target Branch</label>
                    <select name="branch_code" class="w-full bg-gray-50 border-2 border-gray-100 rounded-xl px-4 py-2.5 font-bold text-gray-900 focus:border-indigo-500 focus:ring-0 transition">
                        <option value="">All Branches</option>
                        @foreach($branches as $branch)
                        <option value="{{ $branch->code }}" {{ request('branch_code') == $branch->code ? 'selected' : '' }}>
                            {{ $branch->name }} ({{ $branch->code }})
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-2">
                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Creator</label>
                    <select name="user_id" class="w-full bg-gray-50 border-2 border-gray-100 rounded-xl px-4 py-2.5 font-bold text-gray-900 focus:border-indigo-500 focus:ring-0 transition">
                        <option value="">All Users</option>
                        @foreach($users as $user)
                        <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>
                            {{ $user->name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-2">
                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Status</label>
                    <select name="status" class="w-full bg-gray-50 border-2 border-gray-100 rounded-xl px-4 py-2.5 font-bold text-gray-900 focus:border-indigo-500 focus:ring-0 transition">
                        <option value="">All Statuses</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="partly completed" {{ request('status') == 'partly completed' ? 'selected' : '' }}>Partly Completed</option>
                        <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Fully Completed</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="flex-1 bg-indigo-600 text-white px-6 py-2.5 rounded-xl font-black italic tracking-tighter hover:bg-indigo-700 transition shadow-lg shadow-indigo-200">
                        <i class="fas fa-filter mr-2"></i>FILTER
                    </button>
                    <a href="{{ route('indent.process.list') }}" class="bg-gray-100 text-gray-600 px-4 py-2.5 rounded-xl flex items-center justify-center hover:bg-gray-200 transition">
                        <i class="fas fa-redo-alt"></i>
                    </a>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-[2.5rem] shadow-xl shadow-indigo-100/50 overflow-hidden border border-indigo-50/50">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-gray-50/50">
                            <th class="px-8 py-6 text-[11px] font-black text-gray-400 uppercase tracking-widest">Date & Branch</th>
                            <th class="px-8 py-6 text-[11px] font-black text-gray-400 uppercase tracking-widest text-center">Creator</th>
                            <th class="px-8 py-6 text-[11px] font-black text-gray-400 uppercase tracking-widest text-center">Status</th>
                            <th class="px-8 py-6 text-[11px] font-black text-gray-400 uppercase tracking-widest text-center">Items</th>
                            <th class="px-8 py-6 text-[11px] font-black text-gray-400 uppercase tracking-widest text-right">Volume</th>
                            <th class="px-8 py-6 text-[11px] font-black text-gray-400 uppercase tracking-widest text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse($history as $indent)
                        <tr class="hover:bg-amber-50/20 transition-all group">
                            <td class="px-8 py-5">
                                <div class="font-bold text-gray-800 text-sm italic">{{ date('d M, Y', strtotime($indent->indent_date)) }}</div>
                                <span class="bg-indigo-50 text-indigo-600 font-bold px-2 py-0.5 rounded-lg text-[10px] uppercase">
                                    {{ $indent->branch_name }}
                                </span>
                            </td>
                            <td class="px-8 py-5 text-center">
                                <div class="text-xs font-black text-gray-600 uppercase">{{ $indent->user->name ?? 'System' }}</div>
                            </td>
                            <td class="px-8 py-5 text-center text-[10px] font-black">
                                @if($indent->status == 'completed')
                                <span class="text-green-600 uppercase tracking-tighter">● Fully Completed</span>
                                @elseif($indent->status == 'partly completed')
                                <span class="text-blue-500 uppercase tracking-tighter italic">◌ Partly Completed</span>
                                @else
                                <span class="text-amber-500 uppercase tracking-tighter italic">◌ Pending</span>
                                @endif
                            </td>
                            <td class="px-8 py-5 text-center">
                                <div class="text-sm font-bold text-gray-500">{{ $indent->items_count }} Products</div>
                            </td>
                            <td class="px-8 py-5 text-right">
                                <span class="text-xl font-black italic tracking-tighter text-gray-900">{{ number_format($indent->total_boxes, 0) }}</span>
                                <span class="text-[9px] font-black text-gray-400 uppercase block -mt-1">Boxes</span>
                            </td>
                            <td class="px-8 py-5 text-right">
                                <div class="flex justify-end gap-2 items-center">
                                    <button onclick="viewIndent({{ $indent->id }})" title="View" class="bg-indigo-100 text-indigo-600 p-2.5 rounded-xl hover:bg-indigo-600 hover:text-white transition shadow-sm"><i class="fas fa-eye text-xs"></i></button>
                                    <button onclick="viewProgress({{ $indent->id }})" title="View Progress (Asked vs Completed)" class="bg-blue-100 text-blue-600 p-2.5 rounded-xl hover:bg-blue-600 hover:text-white transition shadow-sm"><i class="fas fa-list-check text-xs"></i></button>
                                    @if(Auth::user()->hasPermission('planning_process', 'print'))
                                    <button onclick="printIndent({{ $indent->id }})" title="Print" class="bg-indigo-100 text-indigo-600 p-2.5 rounded-xl hover:bg-indigo-600 hover:text-white transition shadow-sm"><i class="fas fa-print text-xs"></i></button>
                                    @endif

                                    @if(Auth::user()->hasPermission('planning_process', 'excel'))
                                    <button onclick="exportExcel({{ $indent->id }})" title="Excel" class="bg-green-100 text-green-600 p-2.5 rounded-xl hover:bg-green-600 hover:text-white transition shadow-sm"><i class="fas fa-file-excel text-xs"></i></button>
                                    @endif

                                    @if(Auth::user()->hasPermission('planning_process', 'pdf'))
                                    <button onclick="exportPdf({{ $indent->id }})" title="PDF" class="bg-red-100 text-red-600 p-2.5 rounded-xl hover:bg-red-600 hover:text-white transition shadow-sm"><i class="fas fa-file-pdf text-xs"></i></button>
                                    @endif
                                    
                                    <div class="h-8 w-px bg-gray-200 mx-1"></div>
                                    
                                    @if(Auth::user()->hasPermission('planning_process', 'edit'))
                                    <a href="{{ route('indent.process', $indent->id) }}" class="inline-flex items-center gap-2 bg-amber-500 text-white px-6 py-2.5 rounded-xl font-black italic tracking-tighter hover:bg-amber-600 transition shadow-lg shadow-amber-200">
                                        <i class="fas fa-microchip text-xs"></i> PROCESS NOW
                                    </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-8 py-20 text-center text-gray-400 italic font-bold">No indents available for processing.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Comparison / Progress Modal -->
<div id="progressModal" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
        <div class="p-8 border-b flex justify-between items-center bg-indigo-600 text-white">
            <div>
                <h3 class="text-2xl font-black italic tracking-tighter uppercase">Indent Completion Progress</h3>
                <p id="progressModalBranch" class="text-xs font-bold text-indigo-200 uppercase tracking-widest mt-1"></p>
            </div>
            <button onclick="closeProgressModal()" class="text-white/50 hover:text-white transition p-2 hover:bg-white/10 rounded-full">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        <div class="p-8 overflow-y-auto custom-scrollbar flex-1">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b-2 border-gray-100">
                        <th class="py-4 text-[11px] font-black text-gray-400 uppercase tracking-widest">Product</th>
                        <th class="py-4 text-[11px] font-black text-gray-400 uppercase tracking-widest text-center">Asked (Box)</th>
                        <th class="py-4 text-[11px] font-black text-gray-400 uppercase tracking-widest text-center">Completed (Box)</th>
                        <th class="py-4 text-[11px] font-black text-gray-400 uppercase tracking-widest text-right">Status</th>
                    </tr>
                </thead>
                <tbody id="progressTableBody">
                    <!-- Dynamic Rows -->
                </tbody>
            </table>
        </div>
        <div class="p-8 border-t bg-gray-50 flex justify-end">
            <button onclick="closeProgressModal()" class="bg-indigo-600 text-white px-12 py-4 rounded-2xl font-black italic tracking-tighter hover:bg-indigo-700 transition shadow-xl shadow-indigo-100 uppercase">
                Got it
            </button>
        </div>
    </div>
</div>

<!-- View Indent Modal -->
<div id="viewModal" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
        <div class="p-6 border-b flex justify-between items-center bg-gray-50">
            <div>
                <h3 class="text-xl font-black text-gray-800 italic">Indent Details</h3>
                <p id="modalBranch" class="text-xs font-bold text-indigo-600"></p>
            </div>
            <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 transition p-2 hover:bg-gray-100 rounded-full">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6 overflow-y-auto custom-scrollbar flex-1">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b-2 border-gray-100">
                        <th class="py-3 text-[10px] font-black text-gray-400 uppercase">Product</th>
                        <th class="py-3 text-[10px] font-black text-gray-400 uppercase text-center">Live Stock</th>
                        <th class="py-3 text-[10px] font-black text-gray-400 uppercase text-center">Required</th>
                        <th class="py-3 text-[10px] font-black text-gray-400 uppercase text-right">Final Boxes</th>
                    </tr>
                </thead>
                <tbody id="modalTableBody">
                    <!-- Dynamic Rows -->
                </tbody>
            </table>
        </div>
        <div class="p-6 border-t bg-gray-50 flex justify-between items-center">
            <div class="text-xs text-gray-400 font-bold uppercase italic" id="modalMeta"></div>
            <div class="flex gap-3">
                <button id="modalPrintBtn" class="bg-indigo-600 text-white px-6 py-2.5 rounded-xl font-black italic tracking-tighter hover:bg-indigo-700 transition shadow-lg shadow-indigo-200">
                    <i class="fas fa-print mr-2"></i>PRINT INDENT
                </button>
                <button onclick="closeModal()" class="bg-white border-2 border-gray-200 text-gray-600 px-6 py-2.5 rounded-xl font-black italic tracking-tighter hover:bg-gray-50 transition">
                    CLOSE
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

        fetch(`{{ url('planning/indent') }}/${id}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const indent = data.indent;
                    document.getElementById('modalBranch').innerText = `${indent.branch_name} (${indent.branch_code}) - ${new Date(indent.indent_date).toLocaleDateString()}`;
                    document.getElementById('modalMeta').innerText = `Created by: ${indent.user?.name || 'System'} | ID: #${String(indent.id).padStart(5, '0')}`;
                    
                    let html = '';
                    indent.items.forEach(item => {
                        html += `
                            <tr class="border-b border-gray-50">
                                <td class="py-4 font-bold text-gray-800 text-sm italic">
                                    ${item.product_name}
                                    <div class="text-[9px] text-gray-400 uppercase">${item.product?.pack_name || ''}</div>
                                </td>
                                <td class="py-4 text-center">
                                    <div class="text-xs font-black text-green-600">${parseFloat(item.stock_box).toFixed(2)} BOX</div>
                                </td>
                                <td class="py-4 text-center font-bold text-gray-700">
                                    ${item.demand_qty} ${item.demand_unit.toUpperCase()}
                                </td>
                                <td class="py-4 text-right font-black text-lg italic text-indigo-600">
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

        fetch(`{{ url('planning/indent') }}/${id}`)
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
                            statusHtml = '<span class="text-green-600 font-black italic text-[10px] uppercase tracking-tighter">● FULLY DONE</span>';
                        } else if (completed > 0) {
                            statusHtml = '<span class="text-blue-500 font-black italic text-[10px] uppercase tracking-tighter">◌ PARTIAL (' + Math.round((completed/asked)*100) + '%)</span>';
                        } else {
                            statusHtml = '<span class="text-amber-500 font-black italic text-[10px] uppercase tracking-tighter italic">◌ PENDING</span>';
                        }

                        html += `
                            <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                                <td class="py-5 font-bold text-gray-800 text-sm italic">
                                    ${item.product_name}
                                    <div class="text-[9px] text-gray-400 uppercase font-black tracking-widest">${item.product?.pack_name || ''}</div>
                                </td>
                                <td class="py-5 text-center font-black text-gray-500 text-lg">${asked.toFixed(0)}</td>
                                <td class="py-5 text-center font-black text-indigo-600 text-xl italic">${completed.toFixed(0)}</td>
                                <td class="py-5 text-right">${statusHtml}</td>
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
