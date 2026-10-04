<?php

namespace Spark\Fire;

use Spark\Facades\Facade;
use Spark\Http\Response;

/**
 * Facade for the FireService class.
 *
 * This facade provides a static interface to the FireService, allowing you 
 * to call its methods without needing to instantiate the service directly. 
 *
 * @method static Response render(string $template, array $props = [])
 * @method static Response redirect(string $url, int $status = 302)
 * @method static Response navigate(string $url, int $status = 302)
 * @method static Response success(string $message = '', array $data = [])
 * @method static Response error(string $message, int $status = 400)
 * @method static Response handleValidation(string $message, array $errors, int $status = 422)
 * @method static bool isJs()
 */
class Fire extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return FireService::class;
    }
}