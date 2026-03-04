@extends('layouts.app')

@section('header', 'Branch Settings')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- API Configuration Card -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="bg-slate-800 px-6 py-4 flex justify-between items-center">
            <h3 class="text-white font-bold flex items-center text-sm tracking-wide">
                <i class="fas fa-api mr-2 text-blue-400"></i> API CONFIGURATION
            </h3>
            <span class="flex items-center">
                <span class="w-2 h-2 {{ $apiStatus === 'Active' ? 'bg-green-500' : 'bg-red-500' }} rounded-full mr-2 animate-pulse"></span>
                <span class="text-[10px] font-bold {{ $apiStatus === 'Active' ? 'text-green-400' : 'text-red-400' }} uppercase">{{ $apiStatus }}</span>
            </span>
        </div>
        <div class="p-6 space-y-6">
            <!-- Internal API -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-6 border-b border-slate-50">
                <div class="flex-grow">
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1">Internal API Endpoint URL</label>
                    <div class="flex items-center bg-slate-50 border border-slate-100 rounded-xl px-4 py-2.5">
                        <code class="text-blue-600 font-mono text-sm break-all flex-grow">{{ $apiUrl }}</code>
                        <button onclick="navigator.clipboard.writeText('{{ $apiUrl }}'); alert('Copied to clipboard!')" class="ml-3 text-slate-400 hover:text-blue-500 transition">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
                <div class="md:w-32 flex flex-col justify-center">
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1">Status</label>
                    <div class="px-4 py-2.5 bg-blue-50 text-blue-700 rounded-xl text-center font-bold text-xs uppercase tracking-tight">
                        {{ $apiStatus }}
                    </div>
                </div>
            </div>

            <!-- Live Stock API (ERP) -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex-grow">
                    <div class="flex items-center mb-1.5 ml-1">
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest">Live Stock API (Algebra ERP)</label>
                        <span class="ml-2 px-1.5 py-0.5 bg-amber-100 text-amber-700 text-[8px] font-bold rounded">EXTERNAL</span>
                    </div>
                    <div class="flex items-center bg-slate-50 border border-slate-100 rounded-xl px-4 py-2.5">
                        <code class="text-slate-600 font-mono text-sm break-all flex-grow">{{ $erpApiUrl }}</code>
                        <button onclick="navigator.clipboard.writeText('{{ $erpApiUrl }}'); alert('Copied to clipboard!')" class="ml-3 text-slate-400 hover:text-blue-500 transition">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
                <div class="md:w-32 flex flex-col justify-center">
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1">ERP Status</label>
                    <div class="px-4 py-2.5 bg-emerald-50 text-emerald-700 rounded-xl text-center font-bold text-xs uppercase tracking-tight">
                        {{ $erpApiStatus }}
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-6">
        <div class="bg-indigo-600 px-6 py-4 flex justify-between items-center">
            <h3 class="text-white font-bold flex items-center">
                <i class="fas fa-building mr-2"></i> Add New Branch Mapping
            </h3>
        </div>
        <form action="{{ route('settings.branches.store') }}" method="POST" class="p-6">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1">Branch Code</label>
                    <input type="text" name="code" placeholder="e.g. 2" class="w-full border border-gray-200 rounded-xl py-2.5 px-4 focus:ring-2 focus:ring-indigo-500 outline-none transition" required>
                </div>
                <div class="md:col-span-2 flex gap-3 items-end">
                    <div class="flex-grow">
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1">Branch Name</label>
                        <input type="text" name="name" placeholder="e.g. Main Warehouse" class="w-full border border-gray-200 rounded-xl py-2.5 px-4 focus:ring-2 focus:ring-indigo-500 outline-none transition" required>
                    </div>
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-6 rounded-xl shadow-lg hover:shadow-indigo-100 transition transform active:scale-95 flex items-center">
                        <i class="fas fa-plus mr-2"></i> Add
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="bg-gray-50 px-6 py-4 border-b border-gray-100">
            <h3 class="text-gray-700 font-bold flex items-center">
                <i class="fas fa-list-ul mr-2 text-indigo-500"></i> Existing Branch Mappings
            </h3>
        </div>
        <div class="p-0">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100">
                        <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Branch Code</th>
                        <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Display Name</th>
                        <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($branches as $branch)
                    <tr class="hover:bg-indigo-50/30 transition">
                        <td class="px-6 py-4">
                            <span class="bg-indigo-100 text-indigo-700 font-black px-2 py-1 rounded-lg text-xs">{{ $branch->code }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <form action="{{ route('settings.branches.update') }}" method="POST" class="flex items-center gap-2 group">
                                @csrf
                                <input type="hidden" name="branches[0][code]" value="{{ $branch->code }}">
                                <input type="text" name="branches[0][name]" value="{{ $branch->name }}" class="bg-transparent border-none focus:ring-0 font-bold text-gray-700 w-full p-0">
                                <button type="submit" class="opacity-0 group-hover:opacity-100 text-indigo-500 hover:text-indigo-700 transition">
                                    <i class="fas fa-save"></i>
                                </button>
                            </form>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <form action="{{ route('settings.branches.destroy', $branch) }}" method="POST" onsubmit="return confirm('Delete this mapping?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-300 hover:text-red-500 transition">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="px-6 py-10 text-center text-gray-400 italic">No branch mappings found. Add one above.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="mt-6 bg-amber-50 border border-amber-100 rounded-xl p-4 flex gap-4">
        <div class="text-amber-500 text-xl"><i class="fas fa-info-circle"></i></div>
        <div class="text-amber-800 text-sm">
            <p class="font-bold mb-1">How it works:</p>
            Mapping a Branch Code to a Name will update all dropdowns across the application (like Indent Manager) to show the name instead of just the number.
        </div>
    </div>
</div>
@endsection
