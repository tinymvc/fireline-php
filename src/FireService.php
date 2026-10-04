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
                ->withHeaders(['Vary' => 'X-Fire', 'X-Fire' => 'true'])
                ->noCache();

        }

        return view($template, $props)
            ->withHeaders(['Vary' => 'X-Fire']);
    }

    public function redirect(string $url, int $status = 302): Response
    {
    }

    public function navigate(string $url, int $status = 302): Response
    {
    }

    public function isJs(): bool
    {
        return $this->request->expectsJson() &&
            in_array((string) $this->request->header('X-Fire', ''), ['true', '1', 'yes', 'on'], true);
    }
}