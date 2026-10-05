<?php
require __DIR__ . '/bootstrap.php';
use Spark\Fire\{Fire, FireMiddleware};
use Spark\Foundation\Exceptions\ValidationException;
use Spark\Http\Request;

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$assets = [
    '/fireline.js' => __DIR__ . '/../../fireline-js/dist/cdn.min.js',
    '/alpine.js' => __DIR__ . '/../../fireline-js/node_modules/alpinejs/dist/cdn.min.js',
];

if (isset($assets[$path])) {
    header('Content-Type: application/javascript');
    readfile($assets[$path]);
    return;
}

$app = testApp();
$response = (new FireMiddleware())->handle(app(Request::class), function () use ($path) {
    if ($path === '/submit') {
        if (($_POST['email'] ?? '') !== 'valid@example.test') {
            throw ValidationException::withMessages(['email' => ['Enter a valid email.']]);
        }
        return Fire::success('Saved: ' . ($_POST['intent'] ?? 'missing'));
    }
    if ($path === '/navigate')
        return Fire::navigate('/next');
    return Fire::render('page', ['message' => $path === '/next' ? 'Next page' : 'Home page']);
});

http_response_code($response->getStatusCode());
foreach ($response->getHeaders() as $name => $value)
    header("$name: $value");
echo $response->getContent();
