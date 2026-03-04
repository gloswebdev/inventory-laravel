<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <script src="https://cdn.tailwindcss.com"></script>
        
        <style>
            [x-cloak] { display: none !important; }
            body {
                font-family: 'Inter', sans-serif;
                background: radial-gradient(circle at top right, #1e1b4b, #0f172a);
                background-attachment: fixed;
            }
            .glass-card {
                background: rgba(255, 255, 255, 1);
                border: 1px solid rgba(255, 255, 255, 0.1);
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            }
            .logo-glow {
                box-shadow: 0 0 30px rgba(99, 102, 241, 0.3);
            }
        </style>
    </head>
    <body class="antialiased text-slate-900 overflow-x-hidden">
        <div class="min-h-screen flex flex-col justify-center items-center py-12 px-4 shadow-inner">
            <!-- Logo Section -->
            <div class="mb-10 text-center">
                <div class="inline-flex p-5 rounded-[2.5rem] bg-white logo-glow transform hover:scale-105 transition-all duration-300 border-4 border-white/10">
                    <x-application-logo />
                </div>
            </div>

            <!-- Login Card -->
            <div class="w-full sm:max-w-[440px] glass-card p-10 rounded-[2.5rem] relative overflow-hidden">
                <div class="absolute top-0 left-0 w-full h-2 bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500"></div>
                
                <div class="relative z-10">
                    {{ $slot }}
                </div>
            </div>
            
            <div class="mt-12 text-center">
                <p class="text-white/40 text-[10px] font-black tracking-[0.4em] uppercase">
                    Integrated Inventory System
                </p>
                <p class="mt-2 text-white/20 text-[9px] font-bold">
                    &copy; {{ date('Y') }} {{ config('app.name') }}. Version 2.0
                </p>
            </div>
        </div>
    </body>
</html>
