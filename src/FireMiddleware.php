<?php

namespace Spark\Fire;

use Spark\Http\{Request, Response, Middleware};

class FireMiddleware extends Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);

        if (app(FireService::class)->isJs()) {
            // Ensure no-cache and Vary header on all FireLine responses
            $response->noCache();
            $response->withHeaders(['Vary' => 'X-FireLine']);
        }

        return $response;
    }
}
