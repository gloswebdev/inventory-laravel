@extends('layouts.mobile')

@section('content')
<div class="space-y-8 pb-10">
    <!-- Welcome Header -->
    <div class="animate-in fade-in slide-in-from-top duration-700">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-2xl grad-indigo flex items-center justify-center text-white text-xl shadow-lg shadow-indigo-100 border-2 border-white relative">
                    {{ substr(Auth::user()->name, 0, 1) }}
                    <div class="absolute -bottom-1 -right-1 w-4 h-4 bg-green-500 border-2 border-white rounded-full animate-pulse"></div>
                </div>
                <div>
                    <h2 class="text-2xl font-900 text-slate-800 tracking-tighter leading-none">{{ Auth::user()->name }}</h2>
                    <p class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400 mt-1.5 flex items-center gap-2">
                        <span class="text-indigo-500 italic">{{ Auth::user()->role }}</span> 
                        <span class="bg-slate-200 w-1 h-1 rounded-full"></span> 
                        <span>{{ Carbon\Carbon::now()->format('l, jS') }}</span>
                    </p>
                </div>
            </div>
            <div class="flex -space-x-2">
                @foreach(range(1,3) as $i)
                <div class="w-8 h-8 rounded-full border-2 border-white bg-slate-100 flex items-center justify-center text-[10px] font-black text-slate-400">
                    <i class="fas fa-shield-halved text-[8px]"></i>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- PWA Install Prompt (Smart Card) -->
    <div id="pwaInstallCard" class="hidden animate-in fade-in zoom-in duration-700 delay-100 mb-6 cursor-pointer active:scale-95 transition-transform">
        <div class="grad-violet p-6 rounded-[2.5rem] text-white relative overflow-hidden shadow-xl shadow-violet-100 border-2 border-white">
            <div class="absolute -right-10 -top-10 w-40 h-40 bg-white/10 rounded-full blur-3xl animate-pulse-slow"></div>
            <div class="relative flex items-center justify-between gap-4">
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-2">
                        <i class="fas fa-rocket text-[10px] text-white/80"></i>
                        <span id="pwaStatusTag" class="text-[9px] font-black uppercase tracking-widest text-white/90">Experience Better</span>
                    </div>
                    <h3 class="text-xl font-900 tracking-tighter italic leading-none">Install InvoFlow</h3>
                    <p id="pwaStatusText" class="text-[9px] text-white/70 mt-3 font-bold uppercase tracking-widest leading-relaxed">Add to Home Screen for faster access and offline capabilities.</p>
                </div>
                <div id="pwaActionContainer">
                    <button id="pwaInstallActionBtn" class="shrink-0 w-14 h-14 bg-white/20 hover:bg-white/30 rounded-2xl flex items-center justify-center backdrop-blur-md border border-white/30 shadow-lg active:scale-95 transition-all hidden">
                        <i class="fas fa-download text-xl"></i>
                    </button>
                    <div id="iosInstallHint" class="shrink-0 w-14 h-14 bg-white/10 rounded-2xl flex flex-col items-center justify-center border border-white/20 text-[8px] font-black hidden">
                        <i class="fas fa-share-square text-lg mb-1"></i>
                        <span>SHARE</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Featured Sync Status -->
    <div class="animate-in fade-in zoom-in duration-700 delay-200">
        <div class="grad-cyan p-6 rounded-[2.5rem] text-white relative overflow-hidden shadow-xl shadow-cyan-100">
            <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-white/10 rounded-full blur-3xl"></div>
            <div class="relative flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-2 h-2 bg-white rounded-full animate-ping"></div>
                        <span class="text-[9px] font-black uppercase tracking-widest text-white/80">Sync Insight</span>
                    </div>
                    <h3 class="text-xl font-900 tracking-tighter italic">Factory Pulse is Live</h3>
                    <p class="text-[10px] text-white/70 mt-1 font-bold">Total Physical Stock: {{ number_format($stats['total_stock'], 2) }} Units</p>
                </div>
                <div class="w-12 h-12 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-md border border-white/30">
                    <i class="fas fa-bolt-lightning text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Metrics Row -->
    <div class="grid grid-cols-2 gap-4 animate-in fade-in slide-in-from-bottom duration-700 delay-300">
        <div class="bg-rose-50/50 border border-rose-100 p-5 rounded-[2rem] flex items-center gap-4">
            <div class="w-10 h-10 rounded-2xl grad-rose flex items-center justify-center text-white shadow-lg shadow-rose-100">
                <i class="fas fa-triangle-exclamation text-xs"></i>
            </div>
            <div>
                <div class="text-sm font-900 text-rose-600 tracking-tight">{{ $stats['low_stock_count'] }}</div>
                <div class="text-[7px] font-black text-rose-400 uppercase tracking-widest">Low Stock</div>
            </div>
        </div>
        <div class="bg-indigo-50/50 border border-indigo-100 p-5 rounded-[2rem] flex items-center gap-4">
            <div class="w-10 h-10 rounded-2xl grad-indigo flex items-center justify-center text-white shadow-lg shadow-indigo-100">
                <i class="fas fa-truck-ramp-box text-xs"></i>
            </div>
            <div>
                <div class="text-sm font-900 text-indigo-600 tracking-tight">{{ (int)$stats['today_production_boxes'] }}</div>
                <div class="text-[7px] font-black text-indigo-400 uppercase tracking-widest">Today Box</div>
            </div>
        </div>
    </div>

    <!-- Operational Hub -->
    <div class="animate-in fade-in slide-in-from-bottom duration-700 delay-400">
        <div class="flex items-center justify-between mb-5 px-2">
            <h3 class="text-slate-800 font-900 uppercase tracking-[0.15em] text-[10px] flex items-center gap-2 italic">
                <i class="fas fa-shapes text-indigo-500"></i> Operational Hub
            </h3>
            <span class="text-[8px] font-black text-slate-300 uppercase tracking-widest">{{ count($modules) }} Modules Active</span>
        </div>
        
        <div class="grid {{ count($modules) === 1 ? 'grid-cols-1' : 'grid-cols-2' }} gap-4">
            @php
                $colorMap = [
                    'mobile_stock' => 'grad-cyan shadow-cyan-100',
                    'mobile_production' => 'grad-rose shadow-rose-100',
                    'mobile_planning' => 'grad-emerald shadow-emerald-100',
                    'mobile_indents' => 'grad-violet shadow-violet-100',
                    'mobile_products' => 'grad-slate shadow-slate-200',
                    'mobile_recipes' => 'grad-indigo shadow-indigo-100',
                    'mobile_adjustments' => 'grad-emerald shadow-emerald-100',
                    'mobile_ledger' => 'grad-rose shadow-rose-100',
                    'mobile_users' => 'grad-indigo shadow-indigo-100',
                    'mobile_settings' => 'grad-cyan shadow-cyan-100',
                ];
            @endphp

            @foreach($modules as $module)
            <a href="{{ route($module['route']) }}" class="group relative glass-premium p-6 rounded-[2.5rem] flex flex-col items-center justify-center text-center transition-all active:scale-95 border border-white/80 shadow-sm hover:shadow-md">
                <div class="w-14 h-14 {{ $colorMap[$module['permission']] ?? 'grad-indigo' }} rounded-2xl flex items-center justify-center text-white text-xl shadow-lg border-2 border-white mb-4 transform transition group-hover:scale-110 group-hover:rotate-3">
                    <i class="{{ $module['icon'] }}"></i>
                </div>
                <div class="text-[11px] font-900 text-slate-800 tracking-tight uppercase italic">{{ $module['name'] }}</div>
                <div class="mt-2 flex items-center gap-1.5 opacity-60">
                    <div class="w-1 h-1 bg-green-500 rounded-full"></div>
                    <div class="text-[7px] text-slate-500 font-black uppercase tracking-widest">Authorized</div>
                </div>
            </a>
            @endforeach
        </div>
    </div>

    <!-- Activity Stream -->
    <div class="animate-in fade-in slide-in-from-bottom duration-700 delay-500">
        <h3 class="text-slate-800 font-900 uppercase tracking-[0.15em] text-[10px] mb-5 px-2 italic">
            <i class="fas fa-bolt text-indigo-500"></i> Recent Activity
        </h3>
        
        <div class="space-y-3">
            @forelse($activities as $act)
            <div class="glass-premium p-4 rounded-3xl border border-white/80 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 {{ $act['color'] }} rounded-2xl flex items-center justify-center text-white text-xs shadow-lg shadow-gray-100">
                        <i class="{{ $act['icon'] }}"></i>
                    </div>
                    <div>
                        <div class="text-[11px] font-900 text-slate-800 tracking-tight">{{ $act['title'] }}</div>
                        <div class="text-[8px] font-black text-slate-400 mt-0.5 uppercase tracking-widest">{{ $act['subtitle'] }}</div>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-[7px] font-black text-indigo-400 uppercase tracking-widest">{{ $act['time'] }}</div>
                </div>
            </div>
            @empty
            <div class="glass-premium p-10 rounded-[2.5rem] text-center border-dashed border-2 border-slate-100">
                <p class="text-[9px] text-slate-300 uppercase font-black tracking-widest italic">Waiting for new actions...</p>
            </div>
            @endforelse
        </div>
    </div>

    <!-- System Intelligence Section -->
    <div class="animate-in fade-in slide-in-from-bottom duration-700 delay-600">
        <h3 class="text-slate-800 font-900 uppercase tracking-[0.15em] text-[10px] mb-5 px-2 italic">
            <i class="fas fa-chart-pie text-indigo-500"></i> Business Intelligence
        </h3>
        
        <div class="space-y-4">
            <!-- Summary Card -->
            <div class="glass-premium p-6 rounded-[2.5rem] border border-white/80">
                <div class="grid grid-cols-3 gap-4 border-b border-slate-100 pb-5 mb-5">
                    <div class="text-center">
                        <div class="text-lg font-900 text-slate-800 tracking-tighter">{{ $stats['finished_goods'] }}</div>
                        <div class="text-[7px] font-black text-slate-400 uppercase tracking-widest">Finished</div>
                    </div>
                    <div class="text-center border-x border-slate-100">
                        <div class="text-lg font-900 text-indigo-600 tracking-tighter">{{ $stats['raw_materials'] }}</div>
                        <div class="text-[7px] font-black text-slate-400 uppercase tracking-widest">Materials</div>
                    </div>
                    <div class="text-center">
                        <div class="text-lg font-900 text-slate-800 tracking-tighter">{{ $stats['products'] }}</div>
                        <div class="text-[7px] font-black text-slate-400 uppercase tracking-widest">Items</div>
                    </div>
                </div>
                
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-xl bg-amber-50 flex items-center justify-center text-amber-500 border border-amber-100">
                            <i class="fas fa-clock-rotate-left text-[10px]"></i>
                        </div>
                        <div>
                            <div class="text-[9px] font-black text-slate-800 uppercase tracking-tight">Last Batch Entry</div>
                            <div class="text-[8px] text-slate-400 font-bold tracking-wide mt-0.5">{{ $stats['last_production'] }}</div>
                        </div>
                    </div>
                    <div class="text-[8px] font-black text-indigo-400 border border-indigo-100 px-2 py-1 rounded-lg uppercase tracking-widest bg-indigo-50/30">Auto Sync</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Support Access Area -->
    <div class="animate-in fade-in slide-in-from-bottom duration-700 delay-700">
        <div class="bg-slate-800 p-8 rounded-[3rem] text-white relative overflow-hidden group">
            <div class="absolute top-0 right-0 w-32 h-32 bg-indigo-500/20 rounded-full blur-3xl transform group-hover:scale-150 transition-transform"></div>
            <div class="relative">
                <h4 class="text-sm font-900 uppercase italic tracking-tighter mb-2">Need Assistance?</h4>
                <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest leading-relaxed mb-6">Contact system administrator for permission updates or technical reports.</p>
                <button @click="showAccessModal = true" class="px-6 py-3 bg-white/10 hover:bg-white/20 border border-white/20 rounded-2xl text-[9px] font-black uppercase tracking-widest transition-all">View My Access Profile</button>
            </div>
        </div>
    </div>
</div>
@endsection
