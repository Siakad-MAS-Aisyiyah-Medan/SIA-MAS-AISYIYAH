<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidatePagination
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:2000',
            'page' => 'sometimes|integer|min:1|max:100000',
        ]);

        return $next($request);
    }
}
