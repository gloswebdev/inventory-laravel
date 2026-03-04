@extends('layouts.mobile')

@section('content')
<div class="space-y-10 pb-10">
    <!-- Welcome Section -->
    <div class="animate-in fade-in slide-in-from-top duration-1000">
        <div class="flex items-center gap-4">
            <div class="w-16 h-16 rounded-3xl grad-indigo flex items-center justify-center text-white text-2xl shadow-xl shadow-indigo-200 border-2 border-white">
                {{ substr(Auth::user()->name, 0, 1) }}
            </div>
            <div>
                <p class="text-slate-400 font-900 uppercase tracking-[0.2em] text-[10px] mb-1">Authenticated User</p>
                <h2 class="text-3xl font-900 text-slate-800 tracking-tighter">{{ Auth::user()->name }}</h2>
                <div class="flex items-center gap-2 mt-1">
                    <span class="px-2 py-0.5 bg-indigo-100/50 text-indigo-600 rounded-lg text-[9px] font-black uppercase tracking-widest border border-indigo-100">{{ Auth::user()->role }}</span>
                    <div class="w-1 h-1 bg-slate-300 rounded-full"></div>
                    <span class="text-[10px] text-slate-400 font-bold italic">{{ Carbon\Carbon::now()->format('l, jS F') }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Section -->
    <div class="grid grid-cols-2 gap-5 animate-in fade-in slide-in-from-bottom duration-700 delay-200">
        <div class="glass-premium p-6 rounded-[2.5rem] relative overflow-hidden group">
            <div class="absolute -top-6 -right-6 w-20 h-20 bg-indigo-100/30 rounded-full blur-2xl group-hover:scale-150 transition-transform"></div>
            <div class="w-12 h-12 bg-indigo-50 rounded-2xl flex items-center justify-center text-indigo-600 mb-4 shadow-inner">
                <i class="fas fa-boxes-stacked text-lg"></i>
            </div>
            <div class="text-2xl font-900 text-slate-800 tracking-tighter leading-none">{{ number_format($stats['products']) }}</div>
            <div class="text-[9px] font-black text-slate-400 uppercase tracking-[0.15em] mt-2">Active Products</div>
        </div>
        <div class="glass-premium p-6 rounded-[2.5rem] relative overflow-hidden group">
            <div class="absolute -top-6 -right-6 w-20 h-20 bg-amber-100/30 rounded-full blur-2xl group-hover:scale-150 transition-transform"></div>
            <div class="w-12 h-12 bg-amber-50 rounded-2xl flex items-center justify-center text-amber-600 mb-4 shadow-inner">
                <i class="fas fa-clock-rotate-left text-lg"></i>
            </div>
            <div class="text-2xl font-900 text-slate-800 tracking-tighter leading-none">{{ $stats['pending_indents'] }}</div>
            <div class="text-[9px] font-black text-slate-400 uppercase tracking-[0.15em] mt-2">Pending Indents</div>
        </div>
    </div>

    <!-- Modules Grid -->
    <div class="animate-in fade-in slide-in-from-bottom duration-700 delay-400">
        <h3 class="text-slate-500 font-900 uppercase tracking-[0.2em] text-[10px] mb-6 ml-3 flex items-center gap-2">
            <i class="fas fa-shapes text-indigo-400"></i> Operational Hub
        </h3>
        <div class="grid grid-cols-2 gap-5">
            @php
                $colorMap = [
                    'mobile_stock' => 'grad-cyan shadow-cyan-200/50',
                    'mobile_production' => 'grad-rose shadow-rose-200/50',
                    'mobile_planning' => 'grad-emerald shadow-emerald-200/50',
                    'mobile_indents' => 'grad-violet shadow-violet-200/50',
                ];
            @endphp

            @foreach($modules as $module)
            <a href="{{ route($module['route']) }}" class="group relative glass-premium p-6 rounded-[3rem] flex flex-col items-center justify-center text-center transition-all active:scale-95 hover:shadow-xl hover:shadow-indigo-100/50 border border-white/80">
                <div class="w-16 h-16 {{ $colorMap[$module['permission']] ?? 'grad-indigo shadow-indigo-200/50' }} rounded-[1.8rem] flex items-center justify-center text-white text-2xl shadow-lg border-2 border-white mb-4 transform transition group-hover:scale-110 group-hover:rotate-6">
                    <i class="{{ $module['icon'] }}"></i>
                </div>
                <div class="text-[13px] font-900 text-slate-800 tracking-tight italic uppercase">{{ $module['name'] }}</div>
                <div class="mt-2 flex items-center gap-1.5">
                    <div class="w-1 h-1 bg-green-500 rounded-full animate-pulse"></div>
                    <div class="text-[8px] text-slate-400 font-black uppercase tracking-widest">Active Access</div>
                </div>
            </a>
            @endforeach
            
            @if(empty($modules))
            <div class="col-span-2 glass-premium p-12 rounded-[3rem] text-center border-dashed border-2 border-slate-200">
                <div class="w-20 h-20 bg-slate-50/50 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-lock text-slate-300 text-3xl"></i>
                </div>
                <h4 class="text-slate-700 font-900 italic uppercase italic tracking-tighter">Security Lockout</h4>
                <p class="text-[9px] text-slate-400 mt-3 uppercase font-black px-6 tracking-widest leading-relaxed">Your administrator has not assigned any mobile modules to this profile.</p>
            </div>
            @endif
        </div>
    </div>

    <!-- Floating Insight Card -->
    <div class="animate-in fade-in slide-in-from-bottom duration-700 delay-600">
        <div class="grad-indigo p-1 rounded-[2.5rem] shadow-xl shadow-indigo-200">
            <div class="bg-white/95 backdrop-blur-md rounded-[2.4rem] p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-slate-800 font-900 tracking-tighter uppercase italic text-sm flex items-center gap-2">
                        <i class="fas fa-bolt text-amber-500"></i> Pulse Check
                    </h3>
                    <div class="px-3 py-1 bg-indigo-50 text-indigo-600 rounded-full text-[8px] font-black uppercase tracking-widest">Real-time</div>
                </div>
                
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-4 bg-slate-50/50 rounded-3xl border border-slate-100 group transition-all hover:bg-white hover:shadow-sm">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 bg-white rounded-2xl flex items-center justify-center text-indigo-600 shadow-sm border border-slate-100 group-hover:grad-indigo group-hover:text-white transition-all">
                                <i class="fas fa-award"></i>
                            </div>
                            <div>
                                <div class="text-[11px] font-900 text-slate-800 uppercase tracking-tight">Active Operation</div>
                                <div class="text-[8px] text-slate-400 font-black uppercase tracking-[0.15em] mt-0.5">Inventory Tracking</div>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right text-[10px] text-slate-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
