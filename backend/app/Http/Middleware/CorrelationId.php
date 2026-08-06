<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CorrelationId
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->headers->get('X-Correlation-ID');
        if (! is_string($id) || ! Str::isUuid($id)) {
            $id = (string) Str::uuid();
        }

        $request->attributes->set('correlation_id', $id);
        $response = $next($request);
        $response->headers->set('X-Correlation-ID', $id);

        return $response;
    }
}
