<?php

namespace Spark\Fire;

use InvalidArgumentException;
use Spark\Foundation\Exceptions\ValidationException;
use Spark\Http\Request;
use Spark\Http\Response;
use function array_is_list;
use function is_array;
use function is_string;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

class ComponentController
{
    public function __construct(
        protected readonly Request $request,
        protected readonly Response $response
    ) {
    }

    public function handle(): Response
    {
        $payload = $this->request->getPostParams()->all();
        foreach (['id', 'component', 'checksum'] as $field) {
            if (!is_string($payload[$field] ?? null) || $payload[$field] === '') {
                return $this->errorResponse("Missing or invalid '{$field}'.", 400);
            }
        }

        $id = $payload['id'];
        $class = $payload['component'];
        $checksum = $payload['checksum'];
        $state = $payload['state'] ?? [];
        $method = $payload['method'] ?? '__sync';
        $params = $payload['params'] ?? [];
        $updates = $payload['updates'] ?? [];

        if (
            !is_array($state) || !is_array($updates) || !is_string($method)
            || !is_array($params) || !array_is_list($params)
        ) {
            return $this->errorResponse('Invalid component payload.', 400);
        }

        if (!ComponentSecurity::verify($id, $class, $checksum)) {
            return $this->errorResponse('Invalid component checksum.', 403);
        }

        if (!ComponentSecurity::isComponentClass($class)) {
            return $this->errorResponse('Invalid or forbidden component class.', 403);
        }

        /** @var Component $component */
        $component = new $class();
        if ($method !== '__sync' && !ComponentSecurity::isMethodCallable($component, $method)) {
            return $this->errorResponse("Method '{$method}' is not allowed or does not exist.", 403);
        }

        $component->setId($id);
        $component->boot();

        try {
            $component->fill($state);
            $component->hydrate();
            foreach ($updates as $property => $value) {
                $component->syncProperty((string) $property, $value);
            }
        } catch (InvalidArgumentException) {
            return $this->errorResponse('Invalid component state.', 400);
        } catch (ValidationException $exception) {
            $component->setErrors($exception->getErrors());
        }

        if (!$component->getErrorBag()->any() && $method !== '__sync') {
            try {
                $component->$method(...$params);
            } catch (ValidationException $exception) {
                $component->setErrors($exception->getErrors());
            }
        }

        $component->dehydrate();
        $html = (string) $component->render();

        return FireHeaders::apply(
            $this->response->json([
                'id' => $component->getId(),
                'state' => (object) $component->getState(),
                'html' => $html,
                'events' => $component->getDispatched(),
                'errors' => (object) $component->getErrorBag()->all(),
                'redirect' => $component->getRedirect(),
                'navigate' => $component->getNavigate(),
            ], 200, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            true
        );
    }

    private function errorResponse(string $message, int $status): Response
    {
        return FireHeaders::apply(
            $this->response->json(['error' => $message], $status),
            true
        );
    }
}
