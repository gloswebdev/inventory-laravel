<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100;400;900&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background-color: #0f172a;
            overflow: hidden;
        }

        .bg-gradient-animate {
            background: linear-gradient(-45deg, #0f172a, #0b132b, #1c2541, #0b132b);
            background-size: 400% 400%;
            animation: gradient 15s ease infinite;
        }

        @keyframes gradient {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .floating {
            animation: floating 4s ease-in-out infinite;
        }

        @keyframes floating {
            0% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-15px) rotate(3deg); }
            100% { transform: translateY(0px) rotate(0deg); }
        }

        .glass {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .text-glow {
            text-shadow: 0 0 20px rgba(56, 189, 248, 0.6);
        }

        .neon-border {
            box-shadow: 0 0 15px rgba(56, 189, 248, 0.3), inset 0 0 15px rgba(56, 189, 248, 0.3);
        }

        .stars {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(white, rgba(255,255,255,.2) 2px, transparent 40px);
            background-size: 550px 550px;
            opacity: 0.15;
        }
    </style>
</head>
<body class="bg-gradient-animate min-h-screen flex items-center justify-center relative">
    <div class="stars"></div>
    
    <!-- Background Glowing Orbs -->
    <div class="absolute top-1/4 left-1/4 w-[30rem] h-[30rem] bg-sky-600/10 rounded-full blur-[120px] animate-pulse"></div>
    <div class="absolute bottom-1/4 right-1/4 w-[30rem] h-[30rem] bg-indigo-600/10 rounded-full blur-[120px] animate-pulse" style="animation-delay: 2.5s"></div>

    <div class="relative z-20 text-center px-6">
        <div class="floating mb-10 relative">
            <h1 class="text-[12rem] font-black italic tracking-tighter text-transparent bg-clip-text bg-gradient-to-br from-sky-400 via-white to-indigo-400 opacity-20 leading-none select-none">404</h1>
            <div class="absolute inset-0 flex items-center justify-center">
                <div class="bg-sky-500/20 p-6 rounded-full neon-border animate-ping absolute"></div>
                <div class="bg-sky-500 text-white w-24 h-24 rounded-full flex items-center justify-center shadow-2xl shadow-sky-500/50 relative">
                    <i class="fas fa-satellite-dish text-4xl"></i>
                </div>
            </div>
        </div>

        <div class="glass p-8 sm:p-12 rounded-[3rem] max-w-lg mx-auto transform transition hover:scale-102 duration-500 shadow-2xl">
            <h2 class="text-3xl font-black italic text-white uppercase tracking-tighter mb-4 text-glow">LOST IN SPACE</h2>
            <div class="h-1 w-20 bg-gradient-to-r from-sky-500 to-transparent mx-auto mb-6 rounded-full"></div>
            
            <p class="text-sky-100/60 font-bold text-sm uppercase tracking-[0.2em] mb-8 leading-relaxed">
                The transmission you are looking for does not exist or has been relocated to another coordinate.
            </p>

            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ url()->previous() }}" class="px-8 py-4 bg-white/5 border border-white/10 text-white rounded-2xl font-black italic tracking-tighter hover:bg-white/10 transition flex items-center justify-center gap-3 uppercase text-xs">
                    <i class="fas fa-arrow-left"></i> Go Back
                </a>
                <a href="/" class="px-10 py-4 bg-sky-500 text-slate-900 rounded-2xl font-black italic tracking-tighter hover:bg-sky-400 transition shadow-xl shadow-sky-500/20 flex items-center justify-center gap-3 uppercase text-xs">
                    <i class="fas fa-home"></i> Dashboard
                </a>
            </div>
        </div>

        <div class="mt-12">
            <p class="text-[9px] font-black text-white/20 uppercase tracking-[0.5em] italic">System Error: Page_Not_Found_404</p>
        </div>
    </div>

    <!-- Decorative Corner Elements -->
    <div class="absolute top-10 left-10 w-20 h-20 border-t-2 border-l-2 border-white/10 rounded-tl-3xl"></div>
    <div class="absolute top-10 right-10 w-20 h-20 border-t-2 border-r-2 border-white/10 rounded-tr-3xl"></div>
    <div class="absolute bottom-10 left-10 w-20 h-20 border-b-2 border-l-2 border-white/10 rounded-bl-3xl"></div>
    <div class="absolute bottom-10 right-10 w-20 h-20 border-b-2 border-r-2 border-white/10 rounded-br-3xl"></div>

</body>
</html>
