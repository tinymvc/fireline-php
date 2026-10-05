<?php

use Spark\Fire\Fire;
use Spark\Fire\FireService;
use Spark\Http\Response;

/**
 * Helper function to access the FireService or render a component.
 *
 * @param string|null $component The name of the component to render. 
 *              If null, returns the FireService instance.
 * 
 * @param array $props An associative array of properties to pass to the component.
 * 
 * @return ($component is null ? FireService : Response) 
 *          Returns the FireService instance if $component is null, 
 *          otherwise returns a Response object.
 */
function fire(null|string $component = null, array $props = []): FireService|Response
{
    if ($component === null) {
        return app(FireService::class);
    }

    return Fire::render($component, $props);
}

/**
 * Helper function to check if the current request is a Fire.js request.
 *
 * @return bool Returns true if the current request is a Fire.js request, false otherwise.
 */
function is_fire_js(): bool
{
    return Fire::isJs();
}

/**
 * Helper function to check if the current request is a Fire.js prefetch/preload request.
 *
 * @return bool Returns true if the current request is a preload request, false otherwise.
 */
function is_fire_preload(): bool
{
    return Fire::isPreload();
}

/**
 * Helper function to check if the current request is a Fire.js partial load request.
 *
 * @return bool Returns true if the current request is a partial request, false otherwise.
 */
function is_fire_partial(): bool
{
    return Fire::isPartial();
}