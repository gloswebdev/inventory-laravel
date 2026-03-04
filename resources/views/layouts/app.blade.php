<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'InvoFlow') }}</title>

    <!-- Tailwind CSS (CDN as per legacy) -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body { font-family: 'Inter', sans-serif; }
        [x-cloak] { display: none !important; }
        .sidebar-gradient { background: linear-gradient(180deg, #1f2937 0%, #111827 100%); }
        .nav-item { transition: all 0.3s ease; }
        .nav-item:hover { transform: translateX(5px); }
        .glass-header { background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(10px); }
        .active-nav-border { border-left: 4px solid #3b82f6; background: rgba(59, 130, 246, 0.1); color: #60a5fa; }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 antialiased">
    <div class="flex h-screen overflow-hidden" x-data="{ showAccessModal: false }">
        <!-- Sidebar -->
        <div class="w-64 sidebar-gradient text-white flex flex-col flex-shrink-0 shadow-2xl z-20">
            <div class="px-6 py-8 border-b border-gray-700/50">
                <h1 class="text-2xl font-extrabold tracking-tight flex items-center text-blue-400">
                    <i class="fas fa-cubes mr-3 text-3xl"></i> {{ config('app.name', 'InvoFlow') }}
                </h1>
            </div>
            
            <nav class="flex-grow py-4 overflow-y-auto">
                <a href="{{ route('dashboard') }}" class="nav-item block px-6 py-3.5 mx-2 rounded-lg hover:bg-gray-700/50 {{ request()->routeIs('dashboard') ? 'active-nav-border' : '' }}">
                    <i class="fas fa-tachometer-alt mr-3 w-5"></i> Dashboard
                </a>
                
                @if(Auth::user()->hasPermission('products', 'view'))
                <a href="{{ route('products.index') }}" class="nav-item block px-6 py-3.5 mx-2 rounded-lg hover:bg-gray-700/50 {{ request()->routeIs('products.*') ? 'active-nav-border' : '' }}">
                    <i class="fas fa-box mr-3 w-5"></i> Product Master
                </a>
                @endif

                @if(Auth::user()->hasPermission('recipes', 'view'))
                <a href="{{ route('recipes.index') }}" class="nav-item block px-6 py-3.5 mx-2 rounded-lg hover:bg-gray-700/50 {{ request()->routeIs('recipes.*') ? 'active-nav-border' : '' }}">
                    <i class="fas fa-receipt mr-3 w-5"></i> Recipe Master
                </a>
                @endif
                
                {{-- Production Planning (Advanced Calculator) --}}
                @if(Auth::user()->hasPermission('indent', 'view'))
                 <a href="{{ route('planning.index') }}" class="nav-item block px-6 py-3.5 mx-2 rounded-lg hover:bg-gray-700/50 {{ request()->routeIs('planning.index') ? 'active-nav-border' : '' }}">
                    <i class="fas fa-tasks mr-3 w-5"></i> Production Planning
                </a>
                @endif
 
                {{-- Indent Manager (Sub-menu) --}}
                @if(Auth::user()->hasPermission('planning_bulk', 'view') || Auth::user()->hasPermission('planning_process', 'view'))
                <div x-data="{ open: {{ request()->routeIs('indent.*') ? 'true' : 'false' }} }" class="mb-1">
                    <button @click="open = !open" class="nav-item w-[calc(100%-1rem)] flex items-center justify-between px-6 py-3.5 mx-2 rounded-lg hover:bg-gray-700/50 transition-colors {{ request()->routeIs('indent.*') ? 'bg-gray-700/30' : '' }}">
                        <div class="flex items-center">
                            <i class="fas fa-list-alt mr-3 w-5 text-indigo-400"></i>
                            <span class="font-bold tracking-wide">Indent Manager</span>
                        </div>
                        <i class="fas fa-chevron-down text-xs transition-transform duration-300" :class="open ? 'rotate-180' : ''"></i>
                    </button>
                    
                    <div x-show="open" x-cloak 
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 -translate-y-2"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         class="bg-black/20 mx-4 rounded-b-lg py-2">
                        @if(Auth::user()->hasPermission('planning_bulk', 'view'))
                        <a href="{{ route('indent.index') }}" class="block px-10 py-2.5 text-sm font-bold hover:text-white transition-colors {{ request()->routeIs('indent.index') ? 'text-blue-400' : 'text-gray-400' }}">
                             <i class="fas fa-circle mr-2 text-[6px] align-middle"></i> Bulk Entry
                        </a>
                        @endif
                        @if(Auth::user()->hasPermission('planning_process', 'view'))
                        <a href="{{ route('indent.process.list') }}" class="block px-10 py-2.5 text-sm font-bold hover:text-white transition-colors {{ request()->routeIs('indent.process.list') || request()->routeIs('indent.process') ? 'text-blue-400' : 'text-gray-400' }}">
                             <i class="fas fa-circle mr-2 text-[6px] align-middle"></i> Process Indents
                        </a>
                        @endif
                    </div>
                </div>
                @endif

                @if(Auth::user()->hasPermission('production', 'view'))
                <a href="{{ route('production.index') }}" class="nav-item block px-6 py-3.5 mx-2 rounded-lg hover:bg-gray-700/50 {{ request()->routeIs('production.*') ? 'active-nav-border' : '' }}">
                    <i class="fas fa-industry mr-3 w-5"></i> Production
                </a>
                @endif

                @if(Auth::user()->hasPermission('reports', 'view'))
                <div x-data="{ open: {{ request()->is('reports/*') ? 'true' : 'false' }} }" class="mb-1">
                    <button @click="open = !open" class="nav-item w-[calc(100%-1rem)] flex items-center justify-between px-6 py-3.5 mx-2 rounded-lg hover:bg-gray-700/50 transition-colors {{ request()->is('reports/*') ? 'bg-gray-700/30' : '' }}">
                        <div class="flex items-center">
                            <i class="fas fa-chart-pie mr-3 w-5"></i> Reports
                        </div>
                        <i class="fas fa-chevron-down text-xs transition-transform duration-300" :class="open ? 'rotate-180' : ''"></i>
                    </button>
                    <div x-show="open" x-cloak class="bg-black/20 mx-4 rounded-b-lg py-2">
                        <a href="{{ route('reports.stock-ledger') }}" class="block px-10 py-2 text-sm font-bold hover:text-white {{ request()->routeIs('reports.stock-ledger') ? 'text-blue-400' : 'text-gray-400' }}">
                            <i class="fas fa-circle mr-2 text-[6px] align-middle"></i> Stock Ledger
                        </a>
                        <a href="{{ route('reports.live-stock') }}" class="block px-10 py-2 text-sm font-bold hover:text-white {{ request()->routeIs('reports.live-stock') ? 'text-blue-400' : 'text-gray-400' }}">
                            <i class="fas fa-circle mr-2 text-[6px] align-middle"></i> Live Stock Report
                        </a>
                    </div>
                </div>
                @endif

                @if(Auth::user()->hasPermission('adjustments', 'view'))
                <a href="{{ route('adjustments.index') }}" class="nav-item block px-6 py-3.5 mx-2 rounded-lg hover:bg-gray-700/50 {{ request()->routeIs('adjustments.*') ? 'active-nav-border' : '' }}">
                    <i class="fas fa-sliders-h mr-3 w-5"></i> Adjustment
                </a>
                @endif

                {{-- User Management --}}
                @if(Auth::user()->hasPermission('users', 'view'))
                <div x-data="{ open: {{ request()->routeIs('users.*') ? 'true' : 'false' }} }" class="mb-1">
                    <button @click="open = !open" class="nav-item w-[calc(100%-1rem)] flex items-center justify-between px-6 py-3.5 mx-2 rounded-lg hover:bg-gray-700/50 transition-colors {{ request()->routeIs('users.*') ? 'bg-gray-700/30' : '' }}">
                        <div class="flex items-center">
                            <i class="fas fa-users-cog mr-3 w-5"></i> User Manager
                        </div>
                        <i class="fas fa-chevron-down text-xs transition-transform duration-300" :class="open ? 'rotate-180' : ''"></i>
                    </button>
                    <div x-show="open" x-cloak class="bg-black/20 mx-4 rounded-b-lg py-2">
                        <a href="{{ route('users.index', ['type' => 'desktop']) }}" class="block px-10 py-2.5 text-sm font-bold hover:text-white transition-colors {{ request()->get('type') === 'desktop' ? 'text-blue-400' : 'text-gray-400' }}">
                             <i class="fas fa-desktop mr-2 text-[10px] align-middle"></i> Desktop Users
                        </a>
                        <a href="{{ route('users.index', ['type' => 'mobile']) }}" class="block px-10 py-2.5 text-sm font-bold hover:text-white transition-colors {{ request()->get('type') === 'mobile' ? 'text-blue-400' : 'text-gray-400' }}">
                             <i class="fas fa-mobile-alt mr-2 text-[10px] align-middle"></i> Mobile Users
                        </a>
                    </div>
                </div>
                @endif

                <div class="border-t border-gray-700/50 my-4 mx-6"></div>
                
                @if(Auth::user()->hasPermission('settings', 'view'))
                <a href="{{ route('settings.branches.index') }}" class="nav-item block px-6 py-3.5 mx-2 rounded-lg hover:bg-gray-700/50 {{ request()->routeIs('settings.*') ? 'active-nav-border' : '' }}">
                    <i class="fas fa-cog mr-3 w-5 text-indigo-400"></i> Settings
                </a>
                @endif
            </nav>

            <div class="px-6 py-6 border-t border-gray-700/50">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full flex items-center justify-center bg-red-500 hover:bg-red-600 text-white font-bold py-2.5 px-4 rounded-xl transition-colors shadow-lg shadow-red-500/20">
                        <i class="fas fa-sign-out-alt mr-2"></i> Logout
                    </button>
                </form>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <header class="glass-header shadow-sm border-b px-8 py-4 flex justify-between items-center sticky top-0 z-10">
                <h2 class="text-xl font-bold text-gray-800 tracking-tight">
                    @yield('header', 'Dashboard')
                </h2>
                <div class="flex items-center space-x-4">
                    <button @click="showAccessModal = true" class="bg-indigo-50 hover:bg-indigo-100 px-4 py-2 rounded-xl border border-indigo-100 flex items-center gap-2 transition-colors text-indigo-700">
                        <i class="fas fa-shield-alt text-xs"></i>
                        <span class="text-xs font-black uppercase tracking-tighter">My Access</span>
                    </button>

                    <div class="bg-blue-50 px-4 py-2 rounded-full border border-blue-100 flex items-center">
                        <div class="w-2 h-2 bg-green-500 rounded-full mr-2"></div>
                        <span class="text-sm font-medium text-blue-700">Online: {{ Auth::user()->name ?? Auth::user()->username }}</span>
                    </div>
                </div>
            </header>

            <main class="flex-1 overflow-x-hidden overflow-y-auto p-8">
                {{-- Flash Messages --}}
                @if(session('success'))
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <strong class="font-bold">Success!</strong>
                        <span class="block sm:inline">{{ session('success') }}</span>
                    </div>
                @endif

                @if(session('error'))
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <strong class="font-bold">Error!</strong>
                        <span class="block sm:inline">{{ session('error') }}</span>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </main>
            
            <footer class="text-center p-4 text-gray-500 text-sm border-t bg-white">
                Made with ❤️ by &nbsp;
            </footer>
        </div>
        <!-- My Access Modal -->
        <div x-show="showAccessModal" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        
        <div class="bg-white w-full max-w-lg rounded-[2.5rem] shadow-2xl overflow-hidden flex flex-col max-h-[85vh]"
             @click.outside="showAccessModal = false">
            
            <div class="bg-indigo-600 p-8 text-white relative">
                <div class="flex items-center gap-4">
                    <div class="bg-white/20 p-3 rounded-2xl">
                        <i class="fas fa-id-card-clip text-2xl"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-black italic tracking-tighter uppercase">My System Access</h2>
                        <p class="text-indigo-100 font-bold text-[10px] uppercase tracking-widest mt-1">Review your credentials and permissions</p>
                    </div>
                </div>
                <button @click="showAccessModal = false" class="absolute top-8 right-8 text-white/50 hover:text-white transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-8 custom-scrollbar">
                <div class="mb-8 p-6 bg-slate-50 rounded-3xl border border-slate-100">
                    <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-4">Account Profile</div>
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-full bg-indigo-600 flex items-center justify-center text-white font-black italic">
                            {{ substr(Auth::user()->name, 0, 1) }}
                        </div>
                        <div>
                            <div class="font-900 text-slate-800 italic uppercase tracking-tighter">{{ Auth::user()->name }}</div>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="px-2 py-0.5 bg-blue-100 text-blue-600 rounded-md text-[9px] font-black uppercase tracking-tighter italic">{{ Auth::user()->role }}</span>
                                <span class="px-2 py-0.5 bg-purple-100 text-purple-600 rounded-md text-[9px] font-black uppercase tracking-tighter italic">
                                    {{ Auth::user()->interface_type === 'mobile' ? 'Mobile PWA' : 'Desktop Interface' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest px-2 flex justify-between">
                        <span>Enabled Modules</span>
                        <span>Status</span>
                    </div>
                    
                    <div class="space-y-2">
                        @foreach(Auth::user()->permissions as $perm)
                        <div class="flex items-center justify-between p-4 bg-white border border-slate-100 rounded-2xl group hover:border-indigo-300 transition-colors">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center text-slate-400 group-hover:text-indigo-600 transition-colors">
                                    <i class="fas {{ str_starts_with($perm->page_key, 'mobile_') ? 'fa-mobile-screen' : 'fa-laptop' }} text-xs"></i>
                                </div>
                                <div class="text-[11px] font-black italic uppercase text-slate-700 tracking-tight">
                                    {{ ucwords(str_replace(['mobile_', '_'], ['', ' '], $perm->page_key)) }}
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-[8px] font-black text-green-500 uppercase tracking-widest">Active</span>
                                <div class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></div>
                            </div>
                        </div>
                        @endforeach

                        @if(Auth::user()->role === 'admin')
                        <div class="p-4 bg-amber-50 border border-amber-100 rounded-2xl flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-amber-100 flex items-center justify-center text-amber-600">
                                <i class="fas fa-crown text-xs"></i>
                            </div>
                            <div>
                                <div class="text-[11px] font-black italic uppercase text-amber-800 tracking-tight">Super Admin Privilege</div>
                                <div class="text-[8px] font-bold text-amber-600/80 uppercase tracking-widest">Full System Bypass Enabled</div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="p-6 border-t bg-slate-50 flex justify-end">
                <button @click="showAccessModal = false" class="px-8 py-3 bg-white border-2 border-slate-100 text-slate-500 rounded-xl font-black italic tracking-tighter hover:bg-slate-200 transition uppercase text-xs">
                    Dismiss
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
