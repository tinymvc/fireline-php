<?php

namespace Spark\Fire;

use Spark\Contracts\Http\MiddlewareInterface;
use Spark\Foundation\Application;
use Spark\Foundation\Exceptions\ValidationException;
use Spark\Http\{Request, Response};

class FireMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, \Closure $next): mixed
    {
        // Also covers normalized string/array returns, errors and early sends.
        Application::$app->prepareResponseUsing(
            static fn(Response $response) => FireHeaders::apply($response, Fire::isJs())
        );

        try {
            $response = $next($request);
        } catch (ValidationException $exception) {
            if (!Fire::isJs()) {
                throw $exception;
            }

            $response = Fire::handleValidation($exception->getMessage(), $exception->getErrors());
        }

        if ($response instanceof Response) {
            return FireHeaders::apply($response, Fire::isJs());
        }

        return $response;
    }
}
