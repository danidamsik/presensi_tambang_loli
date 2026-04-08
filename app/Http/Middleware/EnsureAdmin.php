<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role !== 'Admin') {
            abort(Response::HTTP_FORBIDDEN, 'Hanya admin yang dapat mengakses halaman ini.');
        }

        return $next($request);
    }
}
