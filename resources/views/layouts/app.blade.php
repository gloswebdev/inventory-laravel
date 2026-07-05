<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'InvoFlow') }}</title>


    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    {{-- <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script> --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        * { font-family: 'Inter', sans-serif; }
        [x-cloak] { display: none !important; }

        /* ── Sidebar ── */
        .sidebar {
            background: #0f1623;
            border-right: 1px solid rgba(255,255,255,0.05);
        }
        .sidebar-logo-glow {
            background: linear-gradient(135deg, #3b82f6 0%, #6366f1 100%);
            box-shadow: 0 0 20px rgba(99,102,241,0.4);
        }
        .nav-link {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 10px 14px;
            margin: 2px 10px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            color: #8892a4;
            transition: all 0.2s ease;
            cursor: pointer;
            position: relative;
            letter-spacing: 0.01em;
        }
        .nav-link:hover {
            background: rgba(255,255,255,0.06);
            color: #e2e8f0;
        }
        .nav-link:hover .nav-icon {
            color: #60a5fa;
            transform: scale(1.15);
        }
        .nav-link.active {
            background: linear-gradient(135deg, rgba(59,130,246,0.2) 0%, rgba(99,102,241,0.15) 100%);
            color: #93c5fd;
            border: 1px solid rgba(99,102,241,0.25);
        }
        .nav-link.active .nav-icon { color: #60a5fa; }
        .nav-link.active::before {
            content: '';
            position: absolute;
            left: -10px;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 60%;
            background: linear-gradient(180deg, #3b82f6, #6366f1);
            border-radius: 0 3px 3px 0;
        }
        .nav-icon {
            width: 16px;
            text-align: center;
            font-size: 13px;
            transition: all 0.2s;
            flex-shrink: 0;
        }
        .nav-section-label {
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 0.15em;
            color: #3d4a5c;
            text-transform: uppercase;
            padding: 8px 24px 4px;
            margin-top: 4px;
        }
        .sub-nav-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 14px 8px 42px;
            margin: 1px 10px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 600;
            color: #6b7a8d;
            transition: all 0.15s;
        }
        .sub-nav-link:hover { background: rgba(255,255,255,0.04); color: #cbd5e1; }
        .sub-nav-link.active { color: #93c5fd; }
        .sub-dot {
            width: 5px; height: 5px;
            border-radius: 50%;
            background: currentColor;
            flex-shrink: 0;
            opacity: 0.6;
        }
        .sub-nav-link.active .sub-dot { opacity: 1; background: #60a5fa; }

        /* ── Divider ── */
        .sidebar-divider {
            border: none;
            border-top: 1px solid rgba(255,255,255,0.05);
            margin: 8px 20px;
        }

        /* ── Topbar ── */
        .topbar {
            background: rgba(255,255,255,0.96);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(226,232,240,0.8);
        }

        /* ── Scrollbar ── */
        .sidebar-scroll::-webkit-scrollbar { width: 3px; }
        .sidebar-scroll::-webkit-scrollbar-track { background: transparent; }
        .sidebar-scroll::-webkit-scrollbar-thumb { background: #1e2d42; border-radius: 10px; }
        .content-scroll::-webkit-scrollbar { width: 5px; }
        .content-scroll::-webkit-scrollbar-track { background: transparent; }
        .content-scroll::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }

        /* ── Alert Badges ── */
        .flash-success { background:linear-gradient(135deg,#ecfdf5,#d1fae5); border:1px solid #6ee7b7; color:#065f46; }
        .flash-error   { background:linear-gradient(135deg,#fef2f2,#fee2e2); border:1px solid #fca5a5; color:#991b1b; }
    </style>
</head>
<body class="bg-[#f0f4f9] text-slate-900 antialiased">

<div class="flex h-screen overflow-hidden" x-data="{ showAccessModal: false }">

    {{-- ══════════════════════════════
         SIDEBAR
    ══════════════════════════════ --}}
    <aside class="sidebar w-[220px] flex flex-col flex-shrink-0 z-20">

        {{-- Logo --}}
        <div class="px-5 pt-6 pb-5 flex items-center gap-3 flex-shrink-0">
            <div class="sidebar-logo-glow w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0">
                <i class="fas fa-cubes text-white text-base"></i>
            </div>
            <div>
                <div class="text-white font-black text-[15px] leading-tight tracking-tight">{{ config('app.name','InvoFlow') }}</div>
                <div class="text-[9px] font-semibold text-slate-500 uppercase tracking-widest">Inventory Suite</div>
            </div>
        </div>

        {{-- User chip --}}
        <div class="mx-3 mb-4 px-3 py-2.5 rounded-xl bg-white/5 border border-white/[0.06] flex items-center gap-2.5 flex-shrink-0">
            <div class="w-7 h-7 rounded-lg bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white text-[11px] font-black flex-shrink-0">
                {{ strtoupper(substr(Auth::user()->name ?? Auth::user()->username, 0, 1)) }}
            </div>
            <div class="min-w-0">
                <div class="text-[12px] font-bold text-slate-300 truncate">{{ Auth::user()->name ?? Auth::user()->username }}</div>
                <div class="text-[9px] font-bold uppercase tracking-widest text-slate-500">{{ Auth::user()->role }}</div>
            </div>
            <div class="ml-auto flex-shrink-0">
                <span class="relative flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                </span>
            </div>
        </div>

        <hr class="sidebar-divider">

        {{-- Navigation --}}
        <nav class="flex-1 sidebar-scroll overflow-y-auto py-2 space-y-0.5">

            <div class="nav-section-label">Main</div>

            <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="fas fa-gauge-high nav-icon"></i>
                <span>Dashboard</span>
            </a>

            @if(Auth::user()->hasPermission('products', 'view'))
            <a href="{{ route('products.index') }}" class="nav-link {{ request()->routeIs('products.*') ? 'active' : '' }}">
                <i class="fas fa-box nav-icon"></i>
                <span>Product Master</span>
            </a>
            @endif

            @if(Auth::user()->hasPermission('recipes', 'view'))
            <a href="{{ route('recipes.index') }}" class="nav-link {{ request()->routeIs('recipes.*') ? 'active' : '' }}">
                <i class="fas fa-flask nav-icon"></i>
                <span>Recipe Master</span>
            </a>
            @endif

            <div class="nav-section-label mt-2">Operations</div>

            @if(Auth::user()->hasPermission('indent', 'view'))
            <a href="{{ route('planning.index') }}" class="nav-link {{ request()->routeIs('planning.index') ? 'active' : '' }}">
                <i class="fas fa-calculator nav-icon"></i>
                <span>Prod. Planning</span>
            </a>
            @endif

            @if(Auth::user()->hasPermission('planning_bulk', 'view') || Auth::user()->hasPermission('planning_process', 'view'))
            <div x-data="{ open: {{ request()->routeIs('indent.*') ? 'true' : 'false' }} }">
                <button @click="open = !open"
                    class="nav-link w-[calc(100%-20px)] justify-between {{ request()->routeIs('indent.*') ? 'active' : '' }}">
                    <div class="flex items-center gap-[11px]">
                        <i class="fas fa-list-check nav-icon"></i>
                        <span>Indent Manager</span>
                    </div>
                    <i class="fas fa-chevron-down text-[9px] text-slate-500 transition-transform duration-200" :class="open ? 'rotate-180' : ''"></i>
                </button>
                <div x-show="open" x-cloak
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 -translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0">
                    @if(Auth::user()->hasPermission('planning_bulk', 'view'))
                    <a href="{{ route('indent.index') }}" class="sub-nav-link {{ request()->routeIs('indent.index') ? 'active' : '' }}">
                        <span class="sub-dot"></span> Bulk Entry
                    </a>
                    @endif
                    @if(Auth::user()->hasPermission('planning_process', 'view'))
                    <a href="{{ route('indent.process.list') }}" class="sub-nav-link {{ request()->routeIs('indent.process.*') || request()->routeIs('indent.process') ? 'active' : '' }}">
                        <span class="sub-dot"></span> Process Indents
                    </a>
                    @endif
                </div>
            </div>
            @endif

            @if(Auth::user()->hasPermission('production', 'view'))
            <a href="{{ route('production.index') }}" class="nav-link {{ request()->routeIs('production.*') ? 'active' : '' }}">
                <i class="fas fa-industry nav-icon"></i>
                <span>Production</span>
            </a>
            @endif

            @if(Auth::user()->hasPermission('adjustments', 'view'))
            <a href="{{ route('adjustments.index') }}" class="nav-link {{ request()->routeIs('adjustments.*') ? 'active' : '' }}">
                <i class="fas fa-sliders nav-icon"></i>
                <span>Adjustment</span>
            </a>
            @endif

            <div class="nav-section-label mt-2">Analytics</div>

            @if(Auth::user()->hasPermission('reports', 'view'))
            <div x-data="{ open: {{ request()->is('reports/*') ? 'true' : 'false' }} }">
                <button @click="open = !open"
                    class="nav-link w-[calc(100%-20px)] justify-between {{ request()->is('reports/*') ? 'active' : '' }}">
                    <div class="flex items-center gap-[11px]">
                        <i class="fas fa-chart-pie nav-icon"></i>
                        <span>Reports</span>
                    </div>
                    <i class="fas fa-chevron-down text-[9px] text-slate-500 transition-transform duration-200" :class="open ? 'rotate-180' : ''"></i>
                </button>
                <div x-show="open" x-cloak
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 -translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0">
                    <a href="{{ route('reports.stock-ledger') }}" class="sub-nav-link {{ request()->routeIs('reports.stock-ledger') ? 'active' : '' }}">
                        <span class="sub-dot"></span> Stock Ledger
                    </a>
                    <a href="{{ route('reports.live-stock') }}" class="sub-nav-link {{ request()->routeIs('reports.live-stock') ? 'active' : '' }}">
                        <span class="sub-dot"></span> Live Stock
                    </a>
                    @if(Auth::user()->hasPermission('purchase_report', 'view') || Auth::user()->role === 'admin')
                    <a href="{{ route('reports.purchase') }}" class="sub-nav-link {{ request()->routeIs('reports.purchase') ? 'active' : '' }}">
                        <span class="sub-dot"></span> Purchase Report
                    </a>
                    @endif
                </div>
            </div>
            @endif

            @if(Auth::user()->hasPermission('costing', 'view'))
            <div x-data="{ open: {{ (request()->routeIs('costing.index') || request()->routeIs('costing.show') || request()->routeIs('costing.boms.*')) ? 'true' : 'false' }} }">
                <button @click="open = !open"
                    class="nav-link w-[calc(100%-20px)] justify-between {{ (request()->routeIs('costing.index') || request()->routeIs('costing.show') || request()->routeIs('costing.boms.*')) ? 'active' : '' }}">
                    <div class="flex items-center gap-[11px]">
                        <i class="fas fa-coins nav-icon"></i>
                        <span>Costing</span>
                    </div>
                    <i class="fas fa-chevron-down text-[9px] text-slate-500 transition-transform duration-200" :class="open ? 'rotate-180' : ''"></i>
                </button>
                <div x-show="open" x-cloak
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 -translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0">
                    <a href="{{ route('costing.index') }}" class="sub-nav-link {{ (request()->routeIs('costing.index') || request()->routeIs('costing.show')) ? 'active' : '' }}">
                        <span class="sub-dot"></span> Cost Calculator
                    </a>
                    <a href="{{ route('costing.boms.index') }}" class="sub-nav-link {{ request()->routeIs('costing.boms.*') ? 'active' : '' }}">
                        <span class="sub-dot"></span> Costing BOMs
                    </a>
                </div>
            </div>
            @endif

            <div class="nav-section-label mt-2">System</div>

            @if(Auth::user()->hasPermission('users', 'view'))
            <div x-data="{ open: {{ request()->routeIs('users.*') ? 'true' : 'false' }} }">
                <button @click="open = !open"
                    class="nav-link w-[calc(100%-20px)] justify-between {{ request()->routeIs('users.*') ? 'active' : '' }}">
                    <div class="flex items-center gap-[11px]">
                        <i class="fas fa-users-gear nav-icon"></i>
                        <span>User Manager</span>
                    </div>
                    <i class="fas fa-chevron-down text-[9px] text-slate-500 transition-transform duration-200" :class="open ? 'rotate-180' : ''"></i>
                </button>
                <div x-show="open" x-cloak
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 -translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0">
                    <a href="{{ route('users.index', ['type'=>'desktop']) }}" class="sub-nav-link {{ request()->get('type')==='desktop' ? 'active' : '' }}">
                        <span class="sub-dot"></span> Desktop Users
                    </a>
                    <a href="{{ route('users.index', ['type'=>'mobile']) }}" class="sub-nav-link {{ request()->get('type')==='mobile' ? 'active' : '' }}">
                        <span class="sub-dot"></span> Mobile Users
                    </a>
                </div>
            </div>
            @endif

            @if(Auth::user()->hasPermission('settings', 'view'))
            <a href="{{ route('settings.branches.index') }}" class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                <i class="fas fa-gear nav-icon"></i>
                <span>Settings</span>
            </a>
            @endif

            @if(Auth::user()->role === 'admin')
            <a href="{{ route('system.index') }}" class="nav-link {{ request()->routeIs('system.*') ? 'active' : '' }}">
                <i class="fas fa-server nav-icon"></i>
                <span>System</span>
                <span class="ml-auto text-[9px] font-black px-1.5 py-0.5 rounded-md"
                      style="background:rgba(99,102,241,0.2);color:#a5b4fc">ADMIN</span>
            </a>
            @endif


        </nav>

        {{-- Logout --}}
        <div class="p-3 flex-shrink-0">
            <hr class="sidebar-divider mb-3">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                    class="w-full flex items-center justify-center gap-2.5 bg-white/5 hover:bg-red-500/20 border border-white/[0.06] hover:border-red-500/30 text-slate-400 hover:text-red-400 font-bold py-2.5 px-4 rounded-xl transition-all duration-200 text-[12px] group">
                    <i class="fas fa-arrow-right-from-bracket text-sm group-hover:translate-x-0.5 transition-transform"></i>
                    Sign Out
                </button>
            </form>
        </div>
    </aside>

    {{-- ══════════════════════════════
         MAIN CONTENT AREA
    ══════════════════════════════ --}}
    <div class="flex-1 flex flex-col overflow-hidden">

        {{-- Topbar --}}
        <header class="topbar px-7 py-3.5 flex justify-between items-center flex-shrink-0 z-10">
            <div class="flex items-center gap-3">
                <div class="w-1 h-6 rounded-full bg-gradient-to-b from-blue-500 to-indigo-600"></div>
                <h2 class="text-[17px] font-black text-slate-800 tracking-tight">
                    @yield('header', 'Dashboard')
                </h2>
            </div>

            <div class="flex items-center gap-3">
                {{-- My Access button --}}
                <button @click="showAccessModal = true"
                    class="group flex items-center gap-2 px-4 py-2 bg-slate-50 hover:bg-indigo-50 border border-slate-200 hover:border-indigo-200 rounded-xl transition-all duration-200">
                    <div class="w-6 h-6 rounded-lg bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center">
                        <i class="fas fa-shield-halved text-white text-[10px]"></i>
                    </div>
                    <span class="text-[11px] font-black text-slate-600 group-hover:text-indigo-700 uppercase tracking-wider transition-colors">My Access</span>
                </button>

                {{-- Online badge --}}
                <div class="flex items-center gap-2 px-3.5 py-2 bg-emerald-50 border border-emerald-100 rounded-xl">
                    <span class="relative flex h-2 w-2 flex-shrink-0">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                    </span>
                    <span class="text-[12px] font-bold text-emerald-700">{{ Auth::user()->name ?? Auth::user()->username }}</span>
                </div>
            </div>
        </header>

        {{-- Page Content --}}
        <main class="flex-1 overflow-x-hidden overflow-y-auto content-scroll px-7 py-6">

            {{-- Flash Messages --}}
            @if(session('success'))
            <div class="flash-success rounded-xl px-4 py-3 mb-5 flex items-center gap-3 text-sm font-semibold">
                <i class="fas fa-circle-check text-emerald-500"></i>
                {{ session('success') }}
            </div>
            @endif

            @if(session('error'))
            <div class="flash-error rounded-xl px-4 py-3 mb-5 flex items-center gap-3 text-sm font-semibold">
                <i class="fas fa-circle-xmark text-red-500"></i>
                {{ session('error') }}
            </div>
            @endif

            @if($errors->any())
            <div class="flash-error rounded-xl px-4 py-3 mb-5 text-sm font-semibold">
                <div class="flex items-center gap-2 mb-1"><i class="fas fa-circle-xmark text-red-500"></i> Please fix the following:</div>
                <ul class="list-disc list-inside space-y-0.5 ml-5 font-medium opacity-80">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
            @endif

            @yield('content')
        </main>

        {{-- Footer --}}
        <footer class="text-center py-3 text-[11px] text-slate-400 border-t border-slate-100 bg-white/60 flex-shrink-0">
            {{ config('app.name', 'InvoFlow') }} &mdash; Inventory Management Suite
        </footer>
    </div>

    {{-- ══════════════════════════════
         MY ACCESS MODAL
    ══════════════════════════════ --}}
    <div x-show="showAccessModal" x-cloak
        class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
        x-transition:enter="transition ease-out duration-250"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0">

        <div class="bg-white w-full max-w-md rounded-3xl shadow-2xl overflow-hidden flex flex-col max-h-[85vh]"
             @click.outside="showAccessModal = false"
             x-transition:enter="transition ease-out duration-250"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100">

            {{-- Modal Header --}}
            <div class="relative bg-gradient-to-br from-indigo-600 to-violet-700 p-7 text-white overflow-hidden">
                <div class="absolute -top-8 -right-8 w-32 h-32 rounded-full bg-white/5"></div>
                <div class="absolute -bottom-4 -left-4 w-20 h-20 rounded-full bg-white/5"></div>
                <div class="relative flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-white/20 backdrop-blur-sm border border-white/30 flex items-center justify-center text-xl font-black">
                        {{ strtoupper(substr(Auth::user()->name ?? Auth::user()->username, 0, 1)) }}
                    </div>
                    <div>
                        <div class="font-black text-lg tracking-tight">{{ Auth::user()->name ?? Auth::user()->username }}</div>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="px-2 py-0.5 bg-white/20 rounded-md text-[10px] font-black uppercase tracking-wide">{{ Auth::user()->role }}</span>
                            <span class="px-2 py-0.5 bg-white/20 rounded-md text-[10px] font-black uppercase tracking-wide">
                                {{ Auth::user()->interface_type === 'mobile' ? 'Mobile' : 'Desktop' }}
                            </span>
                        </div>
                    </div>
                </div>
                <button @click="showAccessModal = false" class="absolute top-5 right-5 w-8 h-8 rounded-xl bg-white/10 hover:bg-white/20 flex items-center justify-center transition-colors">
                    <i class="fas fa-times text-sm"></i>
                </button>
            </div>

            {{-- Modal Body --}}
            <div class="flex-1 overflow-y-auto p-6 space-y-3 custom-scrollbar">
                <div class="text-[10px] font-black text-slate-400 uppercase tracking-[0.15em] flex justify-between items-center mb-1">
                    <span>Module Permissions</span>
                    <span class="text-emerald-500">● Active</span>
                </div>

                @foreach(Auth::user()->permissions as $perm)
                <div class="flex items-center justify-between px-4 py-3 bg-slate-50 border border-slate-100 rounded-2xl hover:border-indigo-200 hover:bg-indigo-50/30 transition-all group">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-xl bg-white border border-slate-200 group-hover:border-indigo-200 flex items-center justify-center transition-colors">
                            <i class="fas {{ str_starts_with($perm->page_key, 'mobile_') ? 'fa-mobile-screen-button' : 'fa-laptop' }} text-[11px] text-slate-400 group-hover:text-indigo-500 transition-colors"></i>
                        </div>
                        <span class="text-[12px] font-bold text-slate-700">
                            {{ ucwords(str_replace(['mobile_', '_'], ['', ' '], $perm->page_key)) }}
                        </span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                        <span class="text-[10px] font-black text-emerald-500 uppercase tracking-wider">Active</span>
                    </div>
                </div>
                @endforeach

                @if(Auth::user()->role === 'admin')
                <div class="flex items-center gap-3 px-4 py-3 bg-amber-50 border border-amber-200 rounded-2xl">
                    <div class="w-8 h-8 rounded-xl bg-amber-100 flex items-center justify-center">
                        <i class="fas fa-crown text-amber-500 text-[11px]"></i>
                    </div>
                    <div>
                        <div class="text-[12px] font-black text-amber-800">Super Admin</div>
                        <div class="text-[10px] font-bold text-amber-600/80">Full system access enabled</div>
                    </div>
                </div>
                @endif
            </div>

            {{-- Modal Footer --}}
            <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50">
                <button @click="showAccessModal = false"
                    class="w-full py-2.5 bg-white border border-slate-200 hover:border-slate-300 hover:bg-slate-50 text-slate-600 font-bold rounded-xl text-sm transition-all">
                    Close
                </button>
            </div>
        </div>
    </div>

</div>

<style>
    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
</style>

</body>
</html>
