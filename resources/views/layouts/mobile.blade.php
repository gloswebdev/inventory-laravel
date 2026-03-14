<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#4f46e5">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>InvoFlow Mobile</title>

    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <link rel="apple-touch-icon" href="{{ asset('app_icon_512.png') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Outfit', 'sans-serif'] },
                    animation: {
                        'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                    }
                }
            }
        }
    </script>

    <style>
        [x-cloak] { display: none !important; }
        body { 
            -webkit-tap-highlight-color: transparent; 
            background: #fdf2f8; /* Soft pinkish base */
            overflow-x: hidden;
        }
        .glass {
            background: rgba(255, 255, 255, 0.4);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }
        .glass-premium {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.8);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.07);
        }
        .safe-top { padding-top: env(safe-area-inset-top); }
        .safe-bottom { padding-bottom: env(safe-area-inset-bottom); }

        /* Premium Gradients */
        .grad-indigo { background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); }
        .grad-emerald { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
        .grad-amber { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
        .grad-rose { background: linear-gradient(135deg, #f43f5e 0%, #e11d48 100%); }
        .grad-cyan { background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); }
        .grad-violet { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); }
        .grad-slate { background: linear-gradient(135deg, #475569 0%, #1e293b 100%); }

        @keyframes blob-bounce {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(30px, -50px) scale(1.1); }
            66% { transform: translate(-20px, 20px) scale(0.9); }
        }
        .animate-blob {
            animation: blob-bounce 10s infinite alternate cubic-bezier(0.445, 0.05, 0.55, 0.95);
        }
    </style>

    <!-- Alpine.js -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="font-sans text-slate-900">
    <div x-data="{ showAccessModal: false }">
    
    <!-- Dynamic Background Accents -->
    <div class="fixed top-0 left-0 w-full h-full -z-10 bg-[#fdf2f8] overflow-hidden">
        <div class="absolute -top-20 -left-20 w-[500px] h-[500px] bg-indigo-200 rounded-full blur-[100px] opacity-40 animate-blob"></div>
        <div class="absolute top-[20%] -right-20 w-[400px] h-[400px] bg-rose-200 rounded-full blur-[100px] opacity-30 animate-blob" style="animation-delay: 2s"></div>
        <div class="absolute bottom-[-10%] left-[10%] w-[600px] h-[600px] bg-blue-200 rounded-full blur-[120px] opacity-40 animate-blob" style="animation-delay: 4s"></div>
    </div>

    <!-- Header -->
    <header class="fixed top-0 left-0 w-full z-40 glass safe-top border-b border-white/20">
        <div class="px-6 py-4 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 grad-indigo rounded-2xl flex items-center justify-center shadow-lg shadow-indigo-200">
                    <i class="fas fa-cubes text-white text-lg"></i>
                </div>
                <div>
                    <h1 class="text-xl font-900 tracking-tighter text-slate-800 leading-none">InvoFlow<span class="text-indigo-600">.</span></h1>
                    <p class="text-[8px] font-black uppercase tracking-[0.2em] text-slate-400 mt-1">Smart Inventory</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button @click="showAccessModal = true" class="w-10 h-10 rounded-2xl flex items-center justify-center bg-white/60 shadow-sm border border-white text-indigo-600 transition-all active:scale-90">
                    <i class="fas fa-shield-alt text-sm"></i>
                </button>
                <div class="w-[1px] h-6 bg-slate-200/50 mx-1"></div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-10 h-10 rounded-2xl flex items-center justify-center bg-rose-50/80 text-rose-500 border border-rose-100/50 active:scale-90 transition-transform">
                        <i class="fas fa-power-off text-sm"></i>
                    </button>
                </form>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="pt-28 pb-32 px-6 min-h-screen">
        @yield('content')
    </main>

    <!-- Bottom Navigation -->
    <nav class="fixed bottom-6 left-6 right-6 z-40 glass-premium rounded-[2.5rem] safe-bottom border border-white/50">
        <div class="px-6 py-3 flex justify-between items-center">
            <a href="{{ route('mobile.dashboard') }}" class="flex flex-col items-center p-2 rounded-2xl transition-all {{ request()->routeIs('mobile.dashboard') ? 'text-indigo-600 bg-indigo-50/50' : 'text-slate-400 hover:text-slate-600' }}">
                <i class="fas fa-home-alt text-xl"></i>
                <span class="text-[8px] font-black uppercase tracking-widest mt-1">Home</span>
            </a>
            @if(Auth::user()->hasPermission('mobile_stock', 'view'))
            <a href="{{ route('mobile.stock') }}" class="flex flex-col items-center p-2 rounded-2xl transition-all {{ request()->routeIs('mobile.stock') ? 'text-indigo-600 bg-indigo-50/50' : 'text-slate-400 hover:text-slate-600' }}">
                <i class="fas fa-boxes text-xl"></i>
                <span class="text-[8px] font-black uppercase tracking-widest mt-1">Stock</span>
            </a>
            @endif
            
            @if(Auth::user()->hasPermission('mobile_production', 'view'))
            <a href="{{ route('mobile.production') }}" class="flex flex-col items-center justify-center w-14 h-14 grad-indigo rounded-2xl text-white shadow-xl shadow-indigo-200 border-2 border-white transform transition active:scale-90 -mt-8">
                <i class="fas fa-plus text-xl"></i>
            </a>
            @endif

            @if(Auth::user()->hasPermission('mobile_planning', 'view'))
            <a href="{{ route('mobile.planning') }}" class="flex flex-col items-center p-2 rounded-2xl transition-all {{ request()->routeIs('mobile.planning') ? 'text-indigo-600 bg-indigo-50/50' : 'text-slate-400 hover:text-slate-600' }}">
                <i class="fas fa-calendar-alt text-xl"></i>
                <span class="text-[8px] font-black uppercase tracking-widest mt-1">Plan</span>
            </a>
            @endif

            @if(Auth::user()->hasPermission('mobile_indents', 'view'))
            <a href="{{ route('mobile.indents') }}" class="flex flex-col items-center p-2 rounded-2xl transition-all {{ request()->routeIs('mobile.indents') ? 'text-indigo-600 bg-indigo-50/50' : 'text-slate-400 hover:text-slate-600' }}">
                <i class="fas fa-list-ul text-xl"></i>
                <span class="text-[8px] font-black uppercase tracking-widest mt-1">Indents</span>
            </a>
            @endif
        </div>
    </nav>

    <!-- My Access Modal -->
    <div x-show="showAccessModal" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        
        <div class="bg-white w-full max-w-sm rounded-[2.5rem] shadow-2xl overflow-hidden flex flex-col max-h-[85vh] animate-in slide-in-from-bottom duration-300"
             @click.outside="showAccessModal = false">
            
            <div class="bg-indigo-600 p-8 text-white relative">
                <div class="flex items-center gap-4">
                    <div class="bg-white/20 p-3 rounded-2xl">
                        <i class="fas fa-id-card-clip text-2xl"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-900 italic tracking-tighter uppercase leading-tight">My Access</h2>
                        <p class="text-indigo-100 font-bold text-[9px] uppercase tracking-widest mt-1">Granted Permissions</p>
                    </div>
                </div>
                <button @click="showAccessModal = false" class="absolute top-8 right-8 text-white/50 hover:text-white transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-6 space-y-6">
                <div class="p-4 bg-slate-50 rounded-3xl border border-slate-100 flex items-center gap-4">
                    <div class="w-10 h-10 rounded-full bg-indigo-600 flex items-center justify-center text-white font-black italic text-xs">
                        {{ substr(Auth::user()->name, 0, 1) }}
                    </div>
                    <div>
                        <div class="font-900 text-slate-800 italic uppercase tracking-tighter text-sm">{{ Auth::user()->name }}</div>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="px-2 py-0.5 bg-blue-100 text-blue-600 rounded-md text-[8px] font-black uppercase tracking-tighter italic">{{ Auth::user()->role }}</span>
                            <span class="px-2 py-0.5 bg-purple-100 text-purple-600 rounded-md text-[8px] font-black uppercase tracking-tighter italic">
                                {{ Auth::user()->interface_type === 'mobile' ? 'Mobile PWA' : 'Desktop' }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="space-y-3">
                    <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest px-2">Enabled Modules</div>
                    
                    <div class="space-y-2">
                        @foreach(Auth::user()->permissions as $perm)
                        <div class="flex items-center justify-between p-4 bg-white border border-slate-100 rounded-2xl">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center text-indigo-600">
                                    <i class="fas {{ str_starts_with($perm->page_key, 'mobile_') ? 'fa-mobile-screen' : 'fa-laptop' }} text-[10px]"></i>
                                </div>
                                <div class="text-[10px] font-black italic uppercase text-slate-700 tracking-tight">
                                    {{ ucwords(str_replace(['mobile_', '_'], ['', ' '], $perm->page_key)) }}
                                </div>
                            </div>
                            <div class="w-1.5 h-1.5 bg-green-500 rounded-full shadow-[0_0_8px_rgba(34,197,94,0.5)]"></div>
                        </div>
                        @endforeach

                        @if(Auth::user()->role === 'admin')
                        <div class="p-4 bg-amber-50 border border-amber-100 rounded-2xl flex items-center gap-3">
                            <i class="fas fa-crown text-amber-500 text-xs"></i>
                            <div class="text-[10px] font-black italic uppercase text-amber-800 tracking-tight">Full System Bypass</div>
                        </div>
                        @endif
                    </div>
                </div>

                <div id="installAppContainer" class="hidden space-y-3 pt-6 border-t border-slate-100">
                    <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest px-2">Web App</div>
                    <button id="installAppBtn" class="w-full p-4 bg-indigo-50 border border-indigo-100 rounded-2xl flex items-center justify-between group active:scale-95 transition-all text-left">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg grad-indigo flex items-center justify-center text-white">
                                <i class="fas fa-download text-[10px]"></i>
                            </div>
                            <div class="text-[10px] font-black italic uppercase text-indigo-700 tracking-tight">Install InvoFlow App</div>
                        </div>
                        <i class="fas fa-chevron-right text-[10px] text-indigo-300 group-hover:translate-x-1 transition-transform"></i>
                    </button>
                </div>
            </div>

            <div class="p-6 border-t bg-slate-50">
                <button @click="showAccessModal = false" class="w-full py-4 bg-white border-2 border-slate-100 text-slate-500 rounded-2xl font-black italic tracking-tighter uppercase text-xs">
                    Close Details
                </button>
            </div>
        </div>
    </div>

    <script>
        // PWA Service Worker Registration
        let deferredPrompt;
        
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register("{{ asset('service-worker.js') }}")
                    .then(reg => {
                        console.log('PWA: Service Worker registered!', reg);
                        
                        // Check for updates
                        reg.addEventListener('updatefound', () => {
                            const newWorker = reg.installing;
                            newWorker.addEventListener('statechange', () => {
                                if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                    console.log('PWA: New content is available; please refresh.');
                                }
                            });
                        });
                    })
                    .catch(err => console.error('PWA: Service Worker failed!', err));
            });
        }

        // Handle PWA Installation Prompt
        window.addEventListener('beforeinstallprompt', (e) => {
            // Prevent Chrome 67 and earlier from automatically showing the prompt
            e.preventDefault();
            // Stash the event so it can be triggered later.
            deferredPrompt = e;
            console.log('PWA: Install prompt stashed');

            // Show install button in UI
            const installBtnCont = document.getElementById('installAppContainer');
            if (installBtnCont) {
                installBtnCont.classList.remove('hidden');
            }
        });

        const installBtn = document.getElementById('installAppBtn');
        if (installBtn) {
            installBtn.addEventListener('click', async () => {
                if (deferredPrompt) {
                    deferredPrompt.prompt();
                    const { outcome } = await deferredPrompt.userChoice;
                    console.log(`PWA: User response to install prompt: ${outcome}`);
                    deferredPrompt = null;
                    document.getElementById('installAppContainer').classList.add('hidden');
                }
            });
        }

        window.addEventListener('appinstalled', (evt) => {
            console.log('PWA: App installed successfully');
            const installBtnCont = document.getElementById('installAppContainer');
            if (installBtnCont) {
                installBtnCont.classList.add('hidden');
            }
        });
    </script>
    </div>
</body>
</html>
