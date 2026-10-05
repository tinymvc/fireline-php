<?php
// Bootstrap for the local browser fixture server. PHP tests use ApplicationTestCase.
require __DIR__ . '/../vendor/autoload.php';
putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = 'testing';

use Spark\Fire\FireServiceProvider;
use Spark\Foundation\Application;
use Spark\Http\Request;
use Spark\View\Blade;

function testApp(): Application
{
    $app = new Application(__DIR__);
    $app->setConfig('app.debug', false);
    $app->setConfig('app.key', str_repeat('a', 32));
    $app->instance(Request::class, new Request());
    (new FireServiceProvider())->register();
    $app->instance(Blade::class, new Blade(__DIR__ . '/fixtures', __DIR__ . '/.cache'));
    return $app;
}

