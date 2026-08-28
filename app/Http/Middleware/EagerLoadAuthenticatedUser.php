<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EagerLoadAuthenticatedUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof \App\Models\User) {
            $user->loadMissing(['role', 'company', 'site']);
        }

        return $next($request);
    }
}
