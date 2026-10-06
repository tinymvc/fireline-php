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
        Application::$app->instance(Request::class, $request);

        $fire = new FireService($request);

        // Also covers normalized string/array returns, errors and early sends.
        Application::$app->prepareResponseUsing(
            static fn(Response $response) => FireHeaders::apply($response, $fire->isJs(), $fire->assetVersion())
        );

        try {
            $response = $next($request);
        } catch (ValidationException $exception) {
            if (!$fire->isJs()) {
                throw $exception;
            }

            $response = $fire->handleValidation($exception->getMessage(), $exception->getErrors());
        }

        if ($response instanceof Response) {
            return FireHeaders::apply($response, $fire->isJs(), $fire->assetVersion());
        }

        return $response;
    }
}
