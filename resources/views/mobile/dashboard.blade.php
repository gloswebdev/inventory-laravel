@extends('layouts.mobile')

@section('content')
<div class="space-y-6 pb-12">
    <!-- Welcome Header -->
    <div class="animate-in fade-in slide-in-from-top duration-700">
        <div class="glass-premium p-5 rounded-[2rem] flex items-center justify-between border-white/60 relative overflow-hidden">
            <div class="absolute -right-8 -top-8 w-32 h-32 bg-indigo-400/10 rounded-full blur-2xl"></div>
            <div class="flex items-center gap-4 relative z-10">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-indigo-500 to-indigo-700 flex items-center justify-center text-white text-2xl font-black shadow-lg shadow-indigo-200 border border-white/20 relative">
                    {{ substr(Auth::user()->name, 0, 1) }}
                    <div class="absolute -bottom-1 -right-1 w-4 h-4 bg-emerald-400 border-2 border-white rounded-full shadow-sm animate-pulse"></div>
                </div>
                <div>
                    <h2 class="text-xl font-900 text-slate-800 tracking-tighter leading-tight">{{ Auth::user()->name }}</h2>
                    <p class="text-[9px] font-black uppercase tracking-[0.15em] text-slate-400 mt-1 flex items-center gap-2">
                        <span class="text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-md">{{ Auth::user()->role }}</span> 
                        <span class="w-1 h-1 bg-slate-300 rounded-full"></span> 
                        <span>{{ Carbon\Carbon::now()->format('D, M j') }}</span>
                    </p>
                </div>
            </div>
            <div class="w-10 h-10 rounded-full bg-white/60 border border-white shadow-sm flex items-center justify-center text-indigo-400 relative z-10">
                <i class="fas fa-bell text-sm"></i>
                <div class="absolute top-0 right-0 w-2.5 h-2.5 bg-rose-500 rounded-full border-2 border-white"></div>
            </div>
        </div>
    </div>

    <!-- PWA Install Prompt (Smart Card) -->
    <div id="pwaInstallCard" class="hidden animate-in fade-in zoom-in duration-700 delay-100 cursor-pointer group">
        <div class="bg-gradient-to-br from-indigo-600 to-violet-700 p-6 rounded-[2rem] text-white relative overflow-hidden shadow-xl shadow-indigo-200 border border-white/20 transform transition-all duration-300 active:scale-95 group-hover:shadow-indigo-300 group-hover:-translate-y-1">
            <div class="absolute -right-4 -top-4 w-40 h-40 bg-white/20 rounded-full blur-3xl animate-pulse-slow"></div>
            <div class="absolute -left-10 -bottom-10 w-32 h-32 bg-violet-400/30 rounded-full blur-2xl"></div>
            <div class="relative flex items-center justify-between gap-4">
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-6 h-6 rounded-lg bg-white/20 flex items-center justify-center backdrop-blur-sm">
                            <i class="fas fa-rocket text-[10px] text-white"></i>
                        </div>
                        <span id="pwaStatusTag" class="text-[9px] font-black uppercase tracking-widest text-white/90">Experience Better</span>
                    </div>
                    <h3 class="text-xl font-900 tracking-tighter leading-none mt-1">Install InvoFlow</h3>
                    <p id="pwaStatusText" class="text-[10px] text-indigo-100 mt-2 font-bold leading-relaxed">Add to Home Screen for faster access and offline capabilities.</p>
                </div>
                <div id="pwaActionContainer">
                    <button id="pwaInstallActionBtn" class="shrink-0 w-12 h-12 bg-white text-indigo-600 rounded-2xl flex items-center justify-center shadow-lg active:scale-90 transition-all hidden">
                        <i class="fas fa-download text-lg"></i>
                    </button>
                    <div id="iosInstallHint" class="shrink-0 w-12 h-12 bg-white/10 rounded-2xl flex flex-col items-center justify-center border border-white/20 text-[8px] font-black hidden">
                        <i class="fas fa-share-square text-sm mb-1"></i>
                        <span>SHARE</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Metrics Row -->
    <div class="grid grid-cols-2 gap-4 animate-in fade-in slide-in-from-bottom duration-700 delay-200">
        <div class="bg-white/70 backdrop-blur-xl border border-white p-4 rounded-[1.5rem] flex items-center gap-4 shadow-sm hover:shadow-md transition-shadow">
            <div class="w-12 h-12 rounded-2xl bg-rose-50 border border-rose-100 flex items-center justify-center text-rose-500 shadow-inner">
                <i class="fas fa-triangle-exclamation text-lg"></i>
            </div>
            <div>
                <div class="text-xl font-900 text-slate-800 tracking-tight leading-none">{{ $stats['low_stock_count'] }}</div>
                <div class="text-[8px] font-black text-rose-500 uppercase tracking-widest mt-1">Low Stock</div>
            </div>
        </div>
        <div class="bg-white/70 backdrop-blur-xl border border-white p-4 rounded-[1.5rem] flex items-center gap-4 shadow-sm hover:shadow-md transition-shadow">
            <div class="w-12 h-12 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600 shadow-inner">
                <i class="fas fa-truck-ramp-box text-lg"></i>
            </div>
            <div>
                <div class="text-xl font-900 text-slate-800 tracking-tight leading-none">{{ (int)$stats['today_production_boxes'] }}</div>
                <div class="text-[8px] font-black text-indigo-500 uppercase tracking-widest mt-1">Today Box</div>
            </div>
        </div>
    </div>

    <!-- Operational Hub -->
    <div class="animate-in fade-in slide-in-from-bottom duration-700 delay-300">
        <div class="flex items-center justify-between mb-4 px-2">
            <h3 class="text-slate-800 font-900 uppercase tracking-[0.15em] text-[11px] flex items-center gap-2">
                <div class="w-2 h-2 rounded-full bg-indigo-500"></div> Operational Hub
            </h3>
            <span class="px-2 py-1 bg-indigo-50 text-indigo-600 rounded-lg text-[9px] font-black uppercase tracking-widest">{{ count($modules) }} Active</span>
        </div>
        
        <div class="grid {{ count($modules) === 1 ? 'grid-cols-1' : 'grid-cols-2' }} gap-4">
            @php
                $iconStyleMap = [
                    'mobile_stock' => ['bg' => 'bg-cyan-500', 'text' => 'text-white', 'shadow' => 'shadow-cyan-200'],
                    'mobile_production' => ['bg' => 'bg-rose-500', 'text' => 'text-white', 'shadow' => 'shadow-rose-200'],
                    'mobile_planning' => ['bg' => 'bg-emerald-500', 'text' => 'text-white', 'shadow' => 'shadow-emerald-200'],
                    'mobile_indents' => ['bg' => 'bg-violet-500', 'text' => 'text-white', 'shadow' => 'shadow-violet-200'],
                    'mobile_products' => ['bg' => 'bg-slate-700', 'text' => 'text-white', 'shadow' => 'shadow-slate-300'],
                    'mobile_recipes' => ['bg' => 'bg-amber-500', 'text' => 'text-white', 'shadow' => 'shadow-amber-200'],
                    'mobile_adjustments' => ['bg' => 'bg-orange-500', 'text' => 'text-white', 'shadow' => 'shadow-orange-200'],
                    'mobile_ledger' => ['bg' => 'bg-rose-600', 'text' => 'text-white', 'shadow' => 'shadow-rose-200'],
                    'mobile_users'    => ['bg' => 'bg-indigo-600', 'text' => 'text-white', 'shadow' => 'shadow-indigo-200'],
                    'mobile_settings' => ['bg' => 'bg-slate-800',  'text' => 'text-white', 'shadow' => 'shadow-slate-300'],
                    'mobile_costing'  => ['bg' => 'bg-yellow-500', 'text' => 'text-white', 'shadow' => 'shadow-yellow-200'],
                    'mobile_purchase_report' => ['bg' => 'bg-orange-600', 'text' => 'text-white', 'shadow' => 'shadow-orange-200'],
                ];
            @endphp

            @foreach($modules as $module)
            @php
                $style = $iconStyleMap[$module['permission']] ?? ['bg' => 'bg-indigo-500', 'text' => 'text-white', 'shadow' => 'shadow-indigo-200'];
            @endphp
            <a href="{{ route($module['route']) }}" class="group relative bg-white/60 backdrop-blur-xl p-5 rounded-[1.5rem] flex flex-col items-center justify-center text-center transition-all active:scale-95 border border-white/80 shadow-sm hover:shadow-lg hover:bg-white/80 overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-br from-white/40 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                <div class="w-12 h-12 {{ $style['bg'] }} {{ $style['text'] }} rounded-2xl flex items-center justify-center text-xl shadow-md {{ $style['shadow'] }} mb-3 transform transition-transform duration-300 group-hover:scale-110 group-hover:-translate-y-1 group-hover:rotate-3">
                    <i class="{{ $module['icon'] }}"></i>
                </div>
                <div class="text-[11px] font-900 text-slate-800 tracking-tight uppercase relative z-10">{{ $module['name'] }}</div>
                <div class="mt-1 flex items-center gap-1.5 opacity-80 relative z-10">
                    <div class="w-1.5 h-1.5 bg-emerald-400 rounded-full"></div>
                    <div class="text-[8px] text-slate-500 font-black uppercase tracking-widest">Ready</div>
                </div>
            </a>
            @endforeach
        </div>
    </div>

    <!-- System Intelligence Section -->
    <div class="animate-in fade-in slide-in-from-bottom duration-700 delay-400">
        <h3 class="text-slate-800 font-900 uppercase tracking-[0.15em] text-[11px] mb-4 px-2 flex items-center gap-2">
            <div class="w-2 h-2 rounded-full bg-rose-500"></div> Business Intelligence
        </h3>
        
        <div class="bg-white/70 backdrop-blur-xl p-6 rounded-[2rem] border border-white shadow-sm relative overflow-hidden">
            <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-indigo-50 rounded-full blur-3xl"></div>
            <div class="relative z-10">
                <div class="grid grid-cols-3 gap-2 border-b border-slate-100/80 pb-5 mb-5">
                    <div class="text-center bg-white/50 p-3 rounded-2xl border border-white/60">
                        <div class="text-xl font-900 text-slate-800 tracking-tighter">{{ $stats['finished_goods'] }}</div>
                        <div class="text-[8px] font-black text-slate-400 uppercase tracking-widest mt-1">Finished</div>
                    </div>
                    <div class="text-center bg-white/50 p-3 rounded-2xl border border-white/60">
                        <div class="text-xl font-900 text-indigo-600 tracking-tighter">{{ $stats['raw_materials'] }}</div>
                        <div class="text-[8px] font-black text-slate-400 uppercase tracking-widest mt-1">Materials</div>
                    </div>
                    <div class="text-center bg-white/50 p-3 rounded-2xl border border-white/60">
                        <div class="text-xl font-900 text-slate-800 tracking-tighter">{{ $stats['products'] }}</div>
                        <div class="text-[8px] font-black text-slate-400 uppercase tracking-widest mt-1">Total Items</div>
                    </div>
                </div>
                
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-[1rem] bg-amber-50 flex items-center justify-center text-amber-500 border border-amber-100">
                            <i class="fas fa-clock-rotate-left text-sm"></i>
                        </div>
                        <div>
                            <div class="text-[10px] font-black text-slate-800 uppercase tracking-tight">Last Batch Entry</div>
                            <div class="text-[9px] text-slate-500 font-bold tracking-wide mt-0.5">{{ $stats['last_production'] }}</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-1 text-[9px] font-black text-emerald-600 bg-emerald-50 px-2.5 py-1.5 rounded-xl border border-emerald-100 uppercase tracking-widest">
                        <div class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></div> Sync
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Activity Stream -->
    <div class="animate-in fade-in slide-in-from-bottom duration-700 delay-500">
        <div class="flex items-center justify-between mb-4 px-2">
            <h3 class="text-slate-800 font-900 uppercase tracking-[0.15em] text-[11px] flex items-center gap-2">
                <div class="w-2 h-2 rounded-full bg-amber-500"></div> Recent Activity
            </h3>
        </div>
        
        <div class="space-y-3">
            @forelse($activities as $act)
            <div class="bg-white/70 backdrop-blur-md p-4 rounded-[1.5rem] border border-white shadow-sm flex items-center justify-between hover:shadow-md transition-shadow">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 {{ $act['color'] }} rounded-[1rem] flex items-center justify-center text-white text-sm shadow-md">
                        <i class="{{ $act['icon'] }}"></i>
                    </div>
                    <div>
                        <div class="text-[12px] font-900 text-slate-800 tracking-tight">{{ $act['title'] }}</div>
                        <div class="text-[9px] font-black text-slate-400 mt-1 uppercase tracking-widest">{{ $act['subtitle'] }}</div>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-[9px] font-black text-indigo-500 uppercase tracking-widest bg-indigo-50 px-2 py-1 rounded-lg">{{ $act['time'] }}</div>
                </div>
            </div>
            @empty
            <div class="bg-white/50 backdrop-blur-sm p-10 rounded-[2rem] text-center border-dashed border-2 border-slate-200">
                <div class="w-12 h-12 rounded-full bg-slate-100 mx-auto flex items-center justify-center text-slate-300 mb-3">
                    <i class="fas fa-inbox text-xl"></i>
                </div>
                <p class="text-[10px] text-slate-400 uppercase font-black tracking-widest">No recent actions</p>
            </div>
            @endforelse
        </div>
    </div>

    <!-- Support Access Area -->
    <div class="animate-in fade-in slide-in-from-bottom duration-700 delay-600 mt-4">
        <div class="bg-slate-900 p-8 rounded-[2rem] text-white relative overflow-hidden group shadow-xl">
            <div class="absolute -top-10 -right-10 w-40 h-40 bg-indigo-500/30 rounded-full blur-3xl transform group-hover:scale-150 transition-transform duration-500"></div>
            <div class="absolute -bottom-10 -left-10 w-32 h-32 bg-violet-500/20 rounded-full blur-2xl"></div>
            <div class="relative z-10 flex flex-col items-center text-center">
                <div class="w-12 h-12 rounded-full bg-white/10 flex items-center justify-center mb-4 border border-white/20">
                    <i class="fas fa-headset text-indigo-300"></i>
                </div>
                <h4 class="text-lg font-900 tracking-tighter mb-2 leading-none">Need Assistance?</h4>
                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest leading-relaxed mb-6">Contact system administrator for permission updates or technical reports.</p>
                <button @click="showAccessModal = true" class="w-full py-3.5 bg-white text-slate-900 hover:bg-indigo-50 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all shadow-lg active:scale-95 flex items-center justify-center gap-2">
                    <i class="fas fa-user-shield text-indigo-600"></i> View My Access Profile
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

