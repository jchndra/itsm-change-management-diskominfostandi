<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->must_change_password) {
            $isAllowedRoute = $request->routeIs('profile.show', 'logout');
            $isLivewireRequest = $request->routeIs('livewire.*') || $request->is('livewire/*');

            if (! $isAllowedRoute && ! $isLivewireRequest) {
                return redirect()
                    ->route('profile.show')
                    ->with('warning', 'Anda harus mengganti kata sandi sementara terlebih dahulu.');
            }
        }

        return $next($request);
    }
}
