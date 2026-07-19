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
            background: #090d16;
            border-right: 1px solid rgba(255,255,255,0.03);
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

        /* ── Colorful Sidebar Nav Links ── */
        .nav-link {
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .nav-link .nav-icon {
            transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1), color 0.25s ease;
        }
        .nav-link:hover .nav-icon {
            transform: scale(1.2) rotate(3deg);
        }

        /* 1. Dashboard (Blue) */
        .link-dashboard .nav-icon { color: #3b82f6; }
        .link-dashboard:hover { background: rgba(59, 130, 246, 0.08) !important; color: #60a5fa !important; }
        .link-dashboard.active {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.15) 0%, rgba(59, 130, 246, 0.05) 100%) !important;
            color: #93c5fd !important;
            border: 1px solid rgba(59, 130, 246, 0.25) !important;
        }
        .link-dashboard.active .nav-icon { color: #60a5fa !important; }
        .link-dashboard.active::before { background: #3b82f6 !important; }

        /* 2. Product Master (Indigo) */
        .link-products .nav-icon { color: #6366f1; }
        .link-products:hover { background: rgba(99, 102, 241, 0.08) !important; color: #818cf8 !important; }
        .link-products.active {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.15) 0%, rgba(99, 102, 241, 0.05) 100%) !important;
            color: #c7d2fe !important;
            border: 1px solid rgba(99, 102, 241, 0.25) !important;
        }
        .link-products.active .nav-icon { color: #818cf8 !important; }
        .link-products.active::before { background: #6366f1 !important; }

        /* 3. Recipe Master (Purple) */
        .link-recipes .nav-icon { color: #a855f7; }
        .link-recipes:hover { background: rgba(168, 85, 247, 0.08) !important; color: #c084fc !important; }
        .link-recipes.active {
            background: linear-gradient(135deg, rgba(168, 85, 247, 0.15) 0%, rgba(168, 85, 247, 0.05) 100%) !important;
            color: #e9d5ff !important;
            border: 1px solid rgba(168, 85, 247, 0.25) !important;
        }
        .link-recipes.active .nav-icon { color: #c084fc !important; }
        .link-recipes.active::before { background: #a855f7 !important; }

        /* 4. Production Planning (Amber) */
        .link-planning .nav-icon { color: #f59e0b; }
        .link-planning:hover { background: rgba(245, 158, 11, 0.08) !important; color: #fbbf24 !important; }
        .link-planning.active {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.15) 0%, rgba(245, 158, 11, 0.05) 100%) !important;
            color: #fef3c7 !important;
            border: 1px solid rgba(245, 158, 11, 0.25) !important;
        }
        .link-planning.active .nav-icon { color: #fbbf24 !important; }
        .link-planning.active::before { background: #f59e0b !important; }

        /* 5. Indent Manager (Cyan) */
        .link-indent .nav-icon { color: #06b6d4; }
        .link-indent:hover { background: rgba(6, 182, 212, 0.08) !important; color: #22d3ee !important; }
        .link-indent.active {
            background: linear-gradient(135deg, rgba(6, 182, 212, 0.15) 0%, rgba(6, 182, 212, 0.05) 100%) !important;
            color: #cffafe !important;
            border: 1px solid rgba(6, 182, 212, 0.25) !important;
        }
        .link-indent.active .nav-icon { color: #22d3ee !important; }
        .link-indent.active::before { background: #06b6d4 !important; }

        /* 6. Production (Emerald) */
        .link-production .nav-icon { color: #10b981; }
        .link-production:hover { background: rgba(16, 185, 129, 0.08) !important; color: #34d399 !important; }
        .link-production.active {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(16, 185, 129, 0.05) 100%) !important;
            color: #d1fae5 !important;
            border: 1px solid rgba(16, 185, 129, 0.25) !important;
        }
        .link-production.active .nav-icon { color: #34d399 !important; }
        .link-production.active::before { background: #10b981 !important; }

        /* 7. Adjustment (Rose) */
        .link-adjustments .nav-icon { color: #f43f5e; }
        .link-adjustments:hover { background: rgba(244, 63, 94, 0.08) !important; color: #fb7185 !important; }
        .link-adjustments.active {
            background: linear-gradient(135deg, rgba(244, 63, 94, 0.15) 0%, rgba(244, 63, 94, 0.05) 100%) !important;
            color: #ffe4e6 !important;
            border: 1px solid rgba(244, 63, 94, 0.25) !important;
        }
        .link-adjustments.active .nav-icon { color: #fb7185 !important; }
        .link-adjustments.active::before { background: #f43f5e !important; }

        /* 8. Reports (Sky/Blue) */
        .link-reports .nav-icon { color: #0ea5e9; }
        .link-reports:hover { background: rgba(14, 165, 233, 0.08) !important; color: #38bdf8 !important; }
        .link-reports.active {
            background: linear-gradient(135deg, rgba(14, 165, 233, 0.15) 0%, rgba(14, 165, 233, 0.05) 100%) !important;
            color: #e0f2fe !important;
            border: 1px solid rgba(14, 165, 233, 0.25) !important;
        }
        .link-reports.active .nav-icon { color: #38bdf8 !important; }
        .link-reports.active::before { background: #0ea5e9 !important; }

        /* 9. Costing (Orange) */
        .link-costing .nav-icon { color: #f97316; }
        .link-costing:hover { background: rgba(249, 115, 22, 0.08) !important; color: #fdba74 !important; }
        .link-costing.active {
            background: linear-gradient(135deg, rgba(249, 115, 22, 0.15) 0%, rgba(249, 115, 22, 0.05) 100%) !important;
            color: #ffedd5 !important;
            border: 1px solid rgba(249, 115, 22, 0.25) !important;
        }
        .link-costing.active .nav-icon { color: #fdba74 !important; }
        .link-costing.active::before { background: #f97316; }

        /* 10. User Manager (Fuchsia) */
        .link-users .nav-icon { color: #d946ef; }
        .link-users:hover { background: rgba(217, 70, 239, 0.08) !important; color: #f472b6; }
        .link-users.active {
            background: linear-gradient(135deg, rgba(217, 70, 239, 0.15) 0%, rgba(217, 70, 239, 0.05) 100%) !important;
            color: #fae8ff !important;
            border: 1px solid rgba(217, 70, 239, 0.25) !important;
        }
        .link-users.active .nav-icon { color: #f472b6 !important; }
        .link-users.active::before { background: #d946ef; }

        /* 11. Settings (Slate) */
        .link-settings .nav-icon { color: #94a3b8; }
        .link-settings:hover { background: rgba(148, 163, 184, 0.08) !important; color: #cbd5e1 !important; }
        .link-settings.active {
            background: linear-gradient(135deg, rgba(148, 163, 184, 0.15) 0%, rgba(148, 163, 184, 0.05) 100%) !important;
            color: #f1f5f9 !important;
            border: 1px solid rgba(148, 163, 184, 0.25) !important;
        }
        .link-settings.active .nav-icon { color: #cbd5e1 !important; }
        .link-settings.active::before { background: #94a3b8; }

        /* 12. System (Pink) */
        .link-system .nav-icon { color: #ec4899; }
        .link-system:hover { background: rgba(236, 72, 153, 0.08) !important; color: #f472b6; }
        .link-system.active {
            background: linear-gradient(135deg, rgba(236, 72, 153, 0.15) 0%, rgba(236, 72, 153, 0.05) 100%) !important;
            color: #fce7f3 !important;
            border: 1px solid rgba(236, 72, 153, 0.25) !important;
        }
        .link-system.active .nav-icon { color: #f472b6 !important; }
        .link-system.active::before { background: #ec4899; }
    </style>
</head>
<body class="bg-[#f4f7fc] text-slate-900 antialiased">

<div class="flex h-screen overflow-hidden" x-data="{ showAccessModal: false, isFullscreen: false, showFullscreenToast: false }" 
     x-init="showFullscreenToast = (localStorage.getItem('wasFullscreen') === 'true' && !document.fullscreenElement)"
     @fullscreenchange.window="isFullscreen = !!document.fullscreenElement; localStorage.setItem('wasFullscreen', isFullscreen ? 'true' : 'false')">

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

            <a href="{{ route('dashboard') }}" class="nav-link link-dashboard {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="fas fa-gauge-high nav-icon"></i>
                <span>Dashboard</span>
            </a>

            @if(Auth::user()->hasPermission('products', 'view'))
            <a href="{{ route('products.index') }}" class="nav-link link-products {{ request()->routeIs('products.*') ? 'active' : '' }}">
                <i class="fas fa-box nav-icon"></i>
                <span>Product Master</span>
            </a>
            @endif

            @if(Auth::user()->hasPermission('recipes', 'view'))
            <a href="{{ route('recipes.index') }}" class="nav-link link-recipes {{ request()->routeIs('recipes.*') ? 'active' : '' }}">
                <i class="fas fa-flask nav-icon"></i>
                <span>Recipe Master</span>
            </a>
            @endif

            <div class="nav-section-label mt-2">Operations</div>

            @if(Auth::user()->hasPermission('indent', 'view'))
            <a href="{{ route('planning.index') }}" class="nav-link link-planning {{ request()->routeIs('planning.index') ? 'active' : '' }}">
                <i class="fas fa-calculator nav-icon"></i>
                <span>Prod. Planning</span>
            </a>
            @endif

            @if(Auth::user()->hasPermission('planning_bulk', 'view') || Auth::user()->hasPermission('planning_process', 'view'))
            <div x-data="{ open: {{ request()->routeIs('indent.*') ? 'true' : 'false' }} }">
                <button @click="open = !open"
                    class="nav-link link-indent w-[calc(100%-20px)] justify-between {{ request()->routeIs('indent.*') ? 'active' : '' }}">
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
            <a href="{{ route('production.index') }}" class="nav-link link-production {{ request()->routeIs('production.*') ? 'active' : '' }}">
                <i class="fas fa-industry nav-icon"></i>
                <span>Production</span>
            </a>
            @endif

            @if(Auth::user()->hasPermission('adjustments', 'view'))
            <a href="{{ route('adjustments.index') }}" class="nav-link link-adjustments {{ request()->routeIs('adjustments.*') ? 'active' : '' }}">
                <i class="fas fa-sliders nav-icon"></i>
                <span>Adjustment</span>
            </a>
            @endif

            <div class="nav-section-label mt-2">Analytics</div>

            @if(Auth::user()->hasPermission('reports', 'view'))
            <div x-data="{ open: {{ request()->is('reports/*') ? 'true' : 'false' }} }">
                <button @click="open = !open"
                    class="nav-link link-reports w-[calc(100%-20px)] justify-between {{ request()->is('reports/*') ? 'active' : '' }}">
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

            @if(Auth::user()->hasPermission('costing', 'view') || Auth::user()->hasPermission('costing_bom', 'view') || Auth::user()->hasPermission('costing_pro', 'view') || Auth::user()->hasPermission('costing_purchase', 'view') || Auth::user()->hasPermission('costing_pricelist', 'view'))
            <div x-data="{ open: {{ (request()->routeIs('costing.boms.*') || request()->routeIs('costing.pro') || request()->routeIs('costing.purchase-register') || request()->routeIs('costing.pricelist')) ? 'true' : 'false' }} }">
                <button @click="open = !open"
                    class="nav-link link-costing w-[calc(100%-20px)] justify-between {{ (request()->routeIs('costing.boms.*') || request()->routeIs('costing.pro') || request()->routeIs('costing.purchase-register') || request()->routeIs('costing.pricelist')) ? 'active' : '' }}">
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
                    @if(Auth::user()->hasPermission('costing_bom', 'view'))
                    <a href="{{ route('costing.boms.index') }}" class="sub-nav-link {{ request()->routeIs('costing.boms.*') ? 'active' : '' }}">
                        <span class="sub-dot"></span> Costing BOMs
                    </a>
                    @endif
                    @if(Auth::user()->hasPermission('costing_pro', 'view'))
                    <a href="{{ route('costing.pro') }}" class="sub-nav-link {{ request()->routeIs('costing.pro') ? 'active' : '' }}">
                        <span class="sub-dot"></span> Costing Pro
                    </a>
                    @endif
                    @if(Auth::user()->hasPermission('costing_purchase', 'view'))
                    <a href="{{ route('costing.purchase-register') }}" class="sub-nav-link {{ request()->routeIs('costing.purchase-register') ? 'active' : '' }}">
                        <span class="sub-dot"></span> Purchase Register
                    </a>
                    @endif
                    @if(Auth::user()->hasPermission('costing_pricelist', 'view'))
                    <a href="{{ route('costing.pricelist') }}" class="sub-nav-link {{ request()->routeIs('costing.pricelist') ? 'active' : '' }}">
                        <span class="sub-dot"></span> Pricelist
                    </a>
                    @endif
                </div>
            </div>
            @endif

            <div class="nav-section-label mt-2">System</div>

            @if(Auth::user()->hasPermission('users', 'view'))
            <div x-data="{ open: {{ request()->routeIs('users.*') ? 'true' : 'false' }} }">
                <button @click="open = !open"
                    class="nav-link link-users w-[calc(100%-20px)] justify-between {{ request()->routeIs('users.*') ? 'active' : '' }}">
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
            <a href="{{ route('settings.branches.index') }}" class="nav-link link-settings {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                <i class="fas fa-gear nav-icon"></i>
                <span>Settings</span>
            </a>
            @endif

            @if(Auth::user()->role === 'admin')
            <a href="{{ route('system.index') }}" class="nav-link link-system {{ request()->routeIs('system.*') ? 'active' : '' }}">
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
                {{-- Full Screen Toggle button --}}
                <button @click="if (!document.fullscreenElement) { document.documentElement.requestFullscreen() } else { document.exitFullscreen() }"
                    class="group flex items-center gap-2 px-4 py-2 bg-slate-50 hover:bg-indigo-50 border border-slate-200 hover:border-indigo-200 rounded-xl transition-all duration-200">
                    <div class="w-6 h-6 rounded-lg bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center">
                        <i :class="isFullscreen ? 'fas fa-compress text-white text-[10px]' : 'fas fa-expand text-white text-[10px]'"></i>
                    </div>
                    <span class="text-[11px] font-black text-slate-600 group-hover:text-indigo-700 uppercase tracking-wider transition-colors" x-text="isFullscreen ? 'Exit Full' : 'Full Screen'"></span>
                </button>

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
            {{ config('app.name', 'InvoFlow') }} &mdash; Made with <i class="fas fa-heart text-rose-500 mx-0.5 animate-pulse"></i> by <a href="https://gloswebdev.in" target="_blank" class="font-black text-slate-500 hover:text-indigo-600 transition-colors">Glos Webdev</a>
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

    {{-- Resume Full Screen Toast --}}
    <div x-show="showFullscreenToast && !isFullscreen" x-cloak
         class="fixed bottom-6 right-6 z-[100] max-w-sm bg-white/95 border border-slate-200 p-4 rounded-2xl shadow-2xl flex items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-indigo-500 flex items-center justify-center text-white">
                <i class="fas fa-expand text-white text-[10px]"></i>
            </div>
            <div>
                <div class="text-xs font-black text-slate-800 uppercase tracking-tight">Full Screen Mode</div>
                <div class="text-[10px] text-slate-500 font-bold">Resume full screen after update?</div>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <button @click="document.documentElement.requestFullscreen(); showFullscreenToast = false;"
                    class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-[10px] font-black uppercase tracking-wider rounded-xl transition-all shadow-sm">
                Resume
            </button>
            <button @click="showFullscreenToast = false; localStorage.setItem('wasFullscreen', 'false')" 
                    class="text-slate-400 hover:text-slate-600 p-1">
                <i class="fas fa-times text-xs"></i>
            </button>
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
