<?php

namespace Spark\Fire;

use Spark\Http\{Request, Response};
use Spark\View\Blade;
use function in_array;

class FireService
{
    /** Versions follow the request, without retaining completed worker requests. */
    private static ?\WeakMap $versions = null;

    public function __construct(private readonly Request $request)
    {
    }

    public function version(string $version): self
    {
        if ($version === '' || preg_match('/[\x00-\x1F\x7F]/', $version)) {
            throw new \InvalidArgumentException('Asset version must be a nonempty HTTP header value.');
        }
        self::$versions ??= new \WeakMap();
        self::$versions[$this->request] = $version;
        return $this;
    }

    public function assetVersion(): ?string
    {
        return self::$versions[$this->request] ?? null;
    }

    public function render(string $template, array $props = [], ?string $title = null): Response
    {
        $engine = app(Blade::class);
        $html = $engine->render($template, $props);

        if ($this->isJs()) {
            return $this->envelope([
                'html' => $html,
                'title' => $title ?? (($section = trim($engine->yieldSection('title', ''))) === '' ? null : $section),
            ]);
        }

        return FireHeaders::apply($this->resp($html), false);
    }

    public function redirect(string $url, int $status = 302): Response
    {
        if ($this->isJs()) {
            return $this->envelope(['redirect' => $url]);
        }

        return FireHeaders::apply($this->resp()->redirect($url, $status), false);
    }

    public function navigate(string $url, int $status = 302): Response
    {
        if ($this->isJs()) {
            return $this->envelope(['navigate' => $url]);
        }

        return FireHeaders::apply($this->resp()->redirect($url, $status), false);
    }

    public function success(string $message = '', array $data = []): Response
    {
        return $this->envelope(['status' => 'success', 'message' => $message, 'data' => $data]);
    }

    public function error(string $message, int $status = 400): Response
    {
        return $this->envelope(['status' => 'error', 'message' => $message], $status);
    }

    public function handleValidation(string $message, array $errors, int $status = 422): Response
    {
        $errors = array_map(static fn($messages) => array_values((array) $messages), $errors);
        return $this->envelope(['message' => $message, 'errors' => (object) $errors], $status);
    }

    public function isJs(): bool
    {
        return in_array(strtolower(trim((string) $this->request->header('X-FireLine', ''))), ['true', '1', 'yes', 'on'], true);
    }

    public function isPreload(): bool
    {
        return $this->isJs() && in_array(strtolower(trim((string) $this->request->header('X-FireLine-Preload', ''))), ['true', '1', 'yes', 'on'], true);
    }

    public function isPartial(): bool
    {
        return $this->isJs() && in_array(strtolower(trim((string) $this->request->header('X-FireLine-Partial', ''))), ['true', '1', 'yes', 'on'], true);
    }

    private function envelope(array $data, int $status = 200): Response
    {
        return FireHeaders::apply(
            $this->resp()->json($data, $status, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            true
        );
    }

    private function resp(mixed $content = '', int $statusCode = 200, array $headers = []): Response
    {
        $response = new Response($content, $statusCode, $headers);
        if (($version = $this->assetVersion()) !== null) {
            $response->setHeader('X-FireLine-Asset-Version', $version);
        }

        return $response;
    }
}
