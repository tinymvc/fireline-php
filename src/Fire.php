<?php

namespace Spark\Fire;

use Spark\Facades\Facade;

/*
 * 
 */
class Fire extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return FireService::class;
    }
}