<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-6" :status="session('status')" />

    <div class="mb-8">
        <h2 class="text-2xl font-extrabold text-slate-800 tracking-tight">Welcome Back</h2>
        <p class="text-slate-500 text-sm font-medium mt-1">Please enter your credentials to continue</p>
    </div>

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Username -->
        <div>
            <label for="username" class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Username</label>
            <input id="username" type="text" name="username" value="{{ old('username') }}" required autofocus autocomplete="username" 
                   class="w-full border-slate-200 rounded-xl py-3 px-4 focus:ring-2 focus:ring-indigo-500 outline-none transition text-sm font-medium text-slate-700 bg-slate-50/50">
            <x-input-error :messages="$errors->get('username')" class="mt-1" />
        </div>

        <!-- Password -->
        <div class="mt-6">
            <label for="password" class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Password</label>
            <input id="password" type="password" name="password" required autocomplete="current-password"
                   class="w-full border-slate-200 rounded-xl py-3 px-4 focus:ring-2 focus:ring-indigo-500 outline-none transition text-sm font-medium text-slate-700 bg-slate-50/50">
            <x-input-error :messages="$errors->get('password')" class="mt-1" />
        </div>

        <div class="flex items-center justify-between mt-8">
            <div class="block">
                <label for="remember_me" class="inline-flex items-center group cursor-pointer">
                    <input id="remember_me" type="checkbox" class="rounded-md border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500 transition cursor-pointer" name="remember">
                    <span class="ms-2 text-xs font-bold text-slate-500 uppercase tracking-tighter group-hover:text-slate-700 transition">{{ __('Remember me') }}</span>
                </label>
            </div>

            @if (Route::has('password.request'))
                <a class="text-xs font-bold text-indigo-500 hover:text-indigo-700 transition uppercase tracking-tighter" href="{{ route('password.request') }}">
                    {{ __('Forgot password?') }}
                </a>
            @endif
        </div>

        <div class="mt-10">
            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-black py-4 rounded-xl shadow-lg hover:shadow-indigo-200 transition transform active:scale-[0.98] uppercase tracking-widest text-sm">
                {{ __('Secure Log in') }}
            </button>
        </div>
    </form>
</x-guest-layout>
