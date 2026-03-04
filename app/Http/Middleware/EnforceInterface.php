<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class EnforceInterface
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $requiredInterface  'desktop' or 'mobile'
     */
    public function handle(Request $request, Closure $next, string $requiredInterface): Response
    {
        if (!Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();
        
        // Admins can bypass interface restrictions if needed, 
        // but typically they stay in their chosen interface.
        // For now, let's enforce it strictly based on user->interface_type.
        
        if ($user->interface_type !== $requiredInterface) {
            if ($user->interface_type === 'mobile') {
                return redirect()->route('mobile.dashboard');
            } else {
                return redirect()->route('dashboard');
            }
        }

        return $next($request);
    }
}
