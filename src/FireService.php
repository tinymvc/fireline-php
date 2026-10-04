<?php

namespace Spark\Fire;

use Spark\Http\{Request, Response};
use Spark\View\Blade;
use function in_array;

class FireService
{
    public function __construct(
        private readonly Request $request,
    ) {

    }

    public function render(string $template, array $props = []): Response
    {
        if ($this->isJs()) {
            // Get the template engine
            $engine = app(Blade::class);

            // Return a JSON response with the rendered HTML and title
            return json([
                'html' => $engine->render($template, $props),
                'title' => $engine->yieldSection('title'),
            ])
                ->withHeaders(['Vary' => 'X-FireLine', 'X-FireLine' => '1'])
                ->noCache();
        }

        return view($template, $props)
            ->withHeaders(['Vary' => 'X-FireLine']);
    }

    public function redirect(string $url, int $status = 302): Response
    {
        if ($this->isJs()) {
            return json(['redirect' => $url], 200)
                ->withHeaders(['Vary' => 'X-FireLine', 'X-FireLine' => '1'])
                ->noCache();
        }

        return redirect($url, $status);
    }

    public function navigate(string $url, int $status = 302): Response
    {
        if ($this->isJs()) {
            return json(['navigate' => $url], 200)
                ->withHeaders(['Vary' => 'X-FireLine', 'X-FireLine' => '1'])
                ->noCache();
        }

        return redirect($url, $status);
    }

    public function success(string $message = '', array $data = []): Response
    {
        return json(array_filter([
            'status' => 'success',
            'message' => $message ?: null,
            'data' => $data ?: null,
        ]))->withHeaders(['Vary' => 'X-FireLine', 'X-FireLine' => '1'])->noCache();
    }

    public function error(string $message, int $status = 400): Response
    {
        return json(['status' => 'error', 'message' => $message], $status)
            ->withHeaders(['Vary' => 'X-FireLine', 'X-FireLine' => '1'])->noCache();
    }

    public function handleValidation(string $message, array $errors, int $status = 422): Response
    {
        return json(['message' => $message, 'errors' => $errors], $status)
            ->withHeaders(['Vary' => 'X-FireLine', 'X-FireLine' => '1'])
            ->noCache();
    }

    public function isJs(): bool
    {
        return $this->request->expectsJson() &&
            in_array((string) $this->request->header('X-FireLine', ''), ['true', '1', 'yes', 'on'], true);
    }
}