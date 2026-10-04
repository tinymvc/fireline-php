# Fireline PHP

The official PHP adapter for the FireLine Alpine.js plugin, designed for TinyMVC/Spark.

## Installation

```bash
composer require tinymvc/fireline-php
```

Register the service provider in your TinyMVC application (e.g., `bootstrap/providers.php`):

```php
[
    // ...
    \Spark\Fire\FireServiceProvider::class,
],
```

## What it does

This adapter bridges TinyMVC with the FireLine JS client. It detects FireLine AJAX requests via the `X-FireLine` header and intelligently returns JSON envelopes (for DOM morphing, validation, redirects, etc.) instead of standard HTML pages or HTTP redirects.

## `Fire` Facade API Reference

| Method | Signature | Description |
|---|---|---|
| `render` | `render(string $template, array $props = []): Response` | Renders a view; returns HTML or JSON based on request type |
| `redirect` | `redirect(string $url, int $status = 302): Response` | Hard redirect or JSON redirect for FireLine |
| `navigate` | `navigate(string $url, int $status = 302): Response` | SPA navigate (pushState without page reload) |
| `success` | `success(string $message = '', array $data = []): Response` | Success JSON response |
| `error` | `error(string $message, int $status = 400): Response` | Error JSON response |
| `handleValidation` | `handleValidation(string $message, array $errors, int $status = 422): Response` | Validation error response |
| `isJs` | `isJs(): bool` | Detects if current request is a FireLine AJAX request |

## Global Helpers

For convenience, the package provides global helper functions that wrap the `Fire` facade:

- **`fire(string $component = null, array $props = [])`**
  If a component is provided, it returns a `Response` (equivalent to `Fire::render($component, $props)`).
  If called without arguments, it returns the underlying `FireService` instance so you can chain methods:
  ```php
  // Render a view
  return fire('dashboard/index', ['user' => $user]);

  // Chain other methods
  return fire()->navigate('/settings');
  return fire()->success('Done!');
  ```

- **`is_fire_js(): bool`**
  Returns `true` if the current request is a FireLine AJAX request (equivalent to `Fire::isJs()`).

## Controller Examples

### Standard Page Render

```php
use Spark\Fire\Fire;

public function index(): Response
{
    return Fire::render('dashboard/index', ['user' => auth()->user()]);
}
```

### Form Submission with Validation

```php
use Spark\Fire\Fire;
use Spark\Http\Request;

public function store(Request $request): Response
{
    // TinyCore throws ValidationException automatically on failure,
    // which should be converted to Fire::handleValidation(...) if mapped globally.
    // Or you can validate manually:
    
    if ($someCondition) {
        return Fire::handleValidation('Please fix the errors.', [
            'email' => ['This email is already taken.'],
        ]);
    }

    // Process submission...

    return Fire::success('Account created!');
}
```

### Navigation After Action

```php
use Spark\Fire\Fire;

public function destroy(int $id): Response
{
    Post::findOrFail($id)->remove();
    return Fire::navigate('/posts');
}
```

## Middleware

You can apply the `FireMiddleware` to ensure standard `no-cache` and `Vary: X-FireLine` headers are appended correctly to all FireLine responses:

```php
// bootstrap/app.php

->withMiddleware(
    load: __DIR__ . '/middlewares.php',
    queue: ['csrf', Spark\Fire\FireMiddleware::class]
)

```

## Router Macro

The service provider automatically registers a `fire` macro on the Router for simple view rendering:

```php
use Spark\Facades\Route;

Route::fire('/about', 'pages/about');
```

## CSRF Protection

When using `interceptForms: true` in JS, TinyMVC's `CsrfProtection` middleware reads the `_token` field from FormData or the `X-CSRF-TOKEN` header. FireLine sends CSRF via FormData by default on form submissions, but for JSON-body fetch requests, configure `settings.csrfToken` in your JS setup to send the token.
