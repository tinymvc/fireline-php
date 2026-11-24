# FireLine PHP

TinyMVC/Spark adapter for FireLine JS 2.1. Requires PHP 8.2+ and `tinymvc/tinycore` 4.0.7+ within 4.x.

## Install and register

```sh
composer require tinymvc/fireline-php
```

Install and register the [FireLine JS plugin](https://github.com/shahinmoyshan/fireline#readme) separately. Its request header is `X-FireLine: 1`. Detection accepts `1`, `true`, `yes`, or `on` case-insensitively, with surrounding whitespace ignored; it does not require a second Accept-header check. This header selects a representation, not authorization.

## Render a full page or a fragment

```php
use Spark\Fire\Fire;

return Fire::render('pages/about', ['message' => 'About us']);
// Equivalent:
return fire('pages/about', ['message' => 'About us']);
```

Ordinary requests receive HTML. FireLine requests receive JSON containing `html` and `title`. **The adapter renders the template you provide; it does not extract a fragment from a full HTML document.** Arrange the template to return a full layout on an ordinary request and one root element on a FireLine request.

For example, `pages/about.blade.php`:

```blade
@if (!is_fire_js())
    @extends('layouts/app')
@endif

@section('title')About us@endsection

@section('content')
<div>
    <h1>{{ $message }}</h1>
    <a x-navigate href="/">Home</a>
</div>
@endsection

@if (is_fire_js())
    @yield('content')
@endif
```

And `layouts/app.blade.php`:

```blade
<!doctype html>
<html>
<head>
    <title>@yield('title')</title>
    <!-- Load your Alpine + FireLine assets here. -->
</head>
<body>
    <div id="app">@yield('content')</div>
</body>
</html>
```

### Preload and partial requests

`x-preload` sends `X-FireLine-Preload: 1`; `$partial` sends `X-FireLine-Partial: 1`. Both require `X-FireLine` to be enabled. They use the same accepted boolean values as `isJs()`.

```php
// Required data and markup must be identical for preload and navigation.
// Optional page-view analytics can be omitted for speculative requests.
if (!is_fire_preload()) {
    Event::fire('page_viewed', ['page' => '/about']);
}

// Or skip wrapping components if it's a partial load
if (is_fire_partial()) {
    // Return a JSON render envelope containing the fragment.
    return Fire::render('components/comments', ['data' => $data]);
}
```

Partials retain their host and reconcile its children; return the content to place inside it. Partial fragments may have multiple roots or be empty. Do not return raw HTML to `$partial`.

The default JS target is `#app > div`. The returned fragment replaces that element and must continue to match the configured selector. Escape user-provided content with Blade's `{{ ... }}`. The fragment title comes from `@section('title')`; pass an explicit third argument when needed:

```php
return Fire::render('pages/about', ['message' => 'About us'], title: 'About');
```

## API

`Fire` is `Spark\Fire\Fire`. Calling `fire()` without a template resolves the current `FireService`.

| Method | Result |
| --- | --- |
| `component(string $class, array $props = []): string` | Mount a reactive component and return its HTML wrapper |
| `render(string $template, array $props = [], ?string $title = null): Response` | HTML or a JSON render envelope |
| `redirect(string $url, int $status = 302): Response` | Native HTTP redirect, or `{redirect: url}` for full browser navigation |
| `navigate(string $url, int $status = 302): Response` | Native HTTP redirect, or `{navigate: url}` for FireLine navigation |
| `success(string $message = '', array $data = []): Response` | Always JSON: `{status: 'success', message, data}` |
| `error(string $message, int $status = 400): Response` | Always JSON: `{status: 'error', message}` |
| `handleValidation(string $message, array $errors, int $status = 422): Response` | Always JSON: `{message, errors}` |
| `isJs(): bool` | Whether this is a FireLine request |
| `isPreload(): bool` | Whether this is a FireLine `x-preload` request |
| `isPartial(): bool` | Whether this is a FireLine `$partial` load request |
| `version(string $version): self` | Set the asset version for the current request |
| `assetVersion(): ?string` | Read the current request’s explicit version |

`is_fire_js()`, `is_fire_preload()`, and `is_fire_partial()` are the helper equivalents. Each resolution uses the current request, including in applications that handle several requests in one process. Each response is a fresh instance, preventing status or redirect headers from leaking between calls.

Use HTTP 422 for client field-validation handling. String field messages are converted to arrays; even an empty error bag encodes as a JSON object. The JS client keeps additional success payloads in `envelope.raw.data`; it does not copy them into form state.

For FireLine `navigate`/`redirect`, the envelope uses HTTP 200; `$status` applies only to the native HTTP redirect. Return trusted HTTP(S) destinations. Native fallbacks for success/errors/validation remain JSON; implement an application-specific post/redirect/get flow if those forms must also work without JavaScript.

## Routes

The provider registers a GET-only `fire` macro:

```php
use Spark\Facades\Route;

Route::fire('/about', 'pages/about', ['message' => 'About us'])
    ->name('about');
```

It returns Spark's real route object, so route naming and middleware chaining work. This macro comes from the adapter; it is not a core Spark 4 route helper.

## Forms and validation

```blade
<form x-data="{ form: $form() }" x-form action="/register" method="post">
    @csrf
    <input name="email" type="email">
    <p x-text="form.firstError('email')"></p>
    <p x-text="form.message"></p>
    <button :disabled="form.processing">Register</button>
</form>
```

A controller can return validation explicitly:

```php
return Fire::handleValidation('Please fix the errors.', [
    'email' => ['This email is already taken.'],
]);
```

`FireMiddleware` also catches Spark's `ValidationException` from downstream handlers and converts it to the same HTTP 422 envelope for FireLine requests. Ordinary requests rethrow it for the framework's normal handling. Validation thrown outside the middleware's scope needs the application's normal exception mapping.

After saving:

```php
return fire()->success('Account created!', ['id' => $user->id]);
// Or fetch and display a new page:
return fire()->navigate('/dashboard');
```

## Reactive components

Create a component and its Blade view from the application directory:

```sh
php spark make:fire Counter
php spark make:fire Admin/UserProfile
```

The generator writes `app/Components/Counter.php` and `resources/views/components/counter.blade.php`. Namespaced names create matching subdirectories. Existing files are not overwritten.

A component declares initialized public state and public action methods:

```php
namespace App\Components;

use Spark\Fire\Component;

class Counter extends Component
{
    public int $count = 0;
    public string $label = 'Counter';

    protected function view(): string
    {
        return 'components.counter';
    }

    public function increment(int $by = 1): void
    {
        $this->count += $by;
    }

    public function saveLabel(): void
    {
        $this->validate(['label' => 'required|min:3']);
        $this->dispatch('label-saved', $this->label);
    }
}
```

In `resources/views/components/counter.blade.php`:

```blade
<div>
    <h2>{{ $label }}: {{ $count }}</h2>
    <input fire:model="label">
    <p>{{ $errors->first('label') }}</p>
    <button type="button" fire:click="increment(2)">Add two</button>
    <form fire:submit="saveLabel">
        <button fire:loading.attr="disabled" fire:target="saveLabel">Save</button>
        <span fire:loading fire:target="saveLabel">Saving…</span>
    </form>
</div>
```

Mount it in a page:

```blade
@reactive(\App\Components\Counter::class, ['count' => 3])
```

The PHP equivalents are `Fire::component(Counter::class, ['count' => 3])` and `fire_component(Counter::class, ['count' => 3])`. They return HTML strings, while `Fire::render()` returns a page response. The wrapper owns its Alpine scope and stable content host; do not add another `x-data` to it.

The template returned by a protected `view(): string` receives public state, `$component`, and the component-local `$errors` bag. Alternatively, override `render(): string|Blade`. Return trusted HTML fragments; escape user values with Blade's `{{ ... }}`. Components may return several child elements or an empty fragment because the adapter supplies the stable host.

### State and lifecycle

Only initialized, public, non-static, non-readonly properties whose names do not start with `_` are included in state. Use JSON-compatible scalars, nullable values, and arrays. Do not use public state for services, PHP models, file objects, or secrets. Private/protected properties are recreated each request and are not synchronized.

| Phase | Order |
| --- | --- |
| Initial mount | Instantiate → `boot()` → `mount(...$props)` or `fill($props)` → `dehydrate()` → `render()` → serialize state |
| Update/action | Verify identity → instantiate → check action → `boot()` → restore state → `hydrate()` → apply updates → run action → `dehydrate()` → `render()` → serialize state |
| Property update | `updatingName($value)` → `updating($property, $value)` → assignment → `updated($property, $value)` → `updatedName($value)` |

`mount()` is optional and runs only on initial mount. Named `$props` become named arguments when `mount()` exists; otherwise they fill matching public properties. `boot()` runs before state restoration, so checks that depend on restored values belong in `hydrate()` or the action itself. Restoring the previous state with `fill()` does not run property-update hooks. `__sync` refreshes without calling an action or a synthetic `updated()` hook.

`fire:model` updates browser state immediately and synchronizes after 250 ms by default. `.debounce.500ms` changes that delay; `.lazy` / `.change` listens to `change`. `fire:model` accepts top-level public property names. Alpine bindings such as `x-model="state.filters.query"` can edit nested arrays represented as objects. Requests are serialized per component, and typing during a request is retained. See the [JS component reference](https://github.com/shahinmoyshan/fireline#reactive-php-components) for controls, loading, polling, and keys.

### Validation and actions

`$this->validate($rules)` uses TinyCore's validator and returns only validated fields. Use it in actions or property-update hooks. On failure it throws `ValidationException`, which the endpoint captures to return HTTP 200 with the error bag and rendered HTML. Update-hook validation failures skip the action while retaining other edits in the same request. Ordinary page/form validation still uses HTTP 422.

Use `$this->addError('label', 'Message')`, `$this->resetErrors()`, or `$this->getErrorBag()`. The bag supports `any()`, `has($field)`, `first($field)`, `get($field)`, `all()`, iteration, counting, and `$errors['label']` for the first message. Errors belong to the current request; revalidate when subsequent requests need to display them.

Only public, non-static action methods can be called remotely. Base component methods, underscore-prefixed methods, `mount`, `view`, and `updating*` / `updated*` hooks are blocked, including case variants. HMAC signatures protect the component class and instance ID; **they do not make public state or action arguments trustworthy**. Validate inputs and authorize access in your actions. Authorization performed only in a page's `mount()` does not protect later requests to the shared component endpoint. Keep application authentication and CSRF protection enabled for that endpoint.

### Events and navigation

```php
$this->dispatch('label-saved', $this->label);
$this->navigate('/dashboard'); // FireLine page navigation.
$this->redirect('/login');     // Full browser navigation.
```

Dispatch positional parameters. Browser events are sent on `window` with `event.detail.params`; declare `protected array $listeners = ['refresh-counter' => 'refresh'];` to map an event to a public action. Listener mappings are fixed at mount. Events are emitted after an update renders; redirect/navigation responses take precedence. Mount-time events and navigation effects are not delivered by the initial HTML wrapper.

### File uploads and scope

**File uploads are outside the reactive component API.** The browser ignores `fire:model` on file inputs. The component endpoint accepts JSON state and action arguments and does not receive or store uploaded files. Developers should implement their own native upload form or multipart endpoint, including validation, authorization, storage, progress, and cleanup. A resulting application-defined file identifier can be assigned to component state; verify ownership before using it.

There are no temporary-upload objects, automatic model hydration, locked-property attributes, or persistent nested component instances. A child component can remount when its parent returns a new wrapper. Use independently mounted sibling components when their state must remain separate.

## CSRF and cache behavior

FireLine serializes the form's fields; it does not generate CSRF tokens. Include Spark's `@csrf` hidden field, or set `FireLine.settings.csrfToken` to your server-provided token to send `X-CSRF-TOKEN`. Keep Spark's CSRF middleware enabled for writes. Reactive component requests do not serialize hidden form fields: set `FireLine.settings.csrfToken` or provide a `meta[name="csrf-token"]` element containing your application's token. For method overrides, use the framework's hidden `_method` field in a POST form.

### Asset Versioning

If you are using FireLine's `assetVersion` feature on the frontend to force hard reloads when assets change, you can set the version backend-side using the `version()` method anywhere before sending the response:

```php
return Fire::version('build-2026-10-06')->render('pages/about');
// Or in an application middleware, after FireMiddleware:
if (($version = config('app.asset_version')) !== null) {
    fire()->version((string) $version);
}
return $next($request);
```

A version set through `fire()->version()` is shared by later facade/service resolutions in that request, including normalized and early responses handled by the middleware. It is not retained for later requests in a worker. Empty values and HTTP control characters are rejected. The configuration key above is an application convention; the adapter does not read it automatically.

Embed the same build identifier in the initial page's `FireLine.settings.assetVersion` using your framework's safe JSON encoding. A preload never causes a reload on its own. On a mismatch, the JS client makes a full navigation to the requested page; partial loads reload the current document.

Adapter responses merge `X-FireLine`, `X-FireLine-Preload` and `X-FireLine-Partial` into `Vary`. Existing values such as `Accept-Encoding` and `Vary: *` are preserved. FireLine responses also get `X-FireLine: 1` and `no-store, no-cache` headers. Ordinary renders and redirects use the same Vary fields so caches separate HTML, JSON and partial representations.

The middleware applies this policy to explicit responses and registers Spark response preparation for normalized string/array returns, handled errors and early sends. It does not automatically convert arbitrary HTML into FireLine envelopes; use `Fire::render()` for page routes.

## Development

```sh
composer install
composer validate --strict
composer test
composer test -- --testsuite Unit
composer test -- --testsuite Feature
composer test -- --filter Validation
composer test -- --list-tests
composer archive --format=zip --dir=/tmp/fireline-release
```

Release archives include the component generator stubs and exclude tests, installed dependencies, and the local lockfile.

The PHP suite uses TinyCore’s built-in `Spark\Testing\Runner`, `TestCase`, `ApplicationTestCase` and `TestResponse`; no custom assertion runner or extra test dependency is required. Unit tests live in `tests/Unit`, and feature tests in `tests/Feature`. Each feature test gets an isolated application and temporary storage, cleaned up by the framework. The tests exercise component state, actions, signatures, inline validation, command generation, helper loading, both render representations, titles (including empty and zero), redirects, sequential requests, asset-version isolation, advanced request headers, validation, cache headers, and actual HTTP dispatch through the provider, router and middleware (including normalized and early responses). The sibling JS repository also provides a real browser/PHP test:

```sh
cd ../fireline-js
npm ci
npx playwright install chromium
npm run test:integration
```

An installed Chrome can be used with `FIRELINE_BROWSER_CHANNEL=chrome npm run test:integration`. The PHP fixture server is for tests only, listens on loopback, and uses port 18994 by default (`FIRELINE_PHP_PORT` overrides it).
