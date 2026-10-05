<?php

namespace Tests\Feature;

use Spark\Facades\Route;
use Spark\Fire\{Fire, FireMiddleware, FireService, FireServiceProvider};
use Spark\Foundation\Application;
use Spark\Foundation\Exceptions\ValidationException;
use Spark\Http\{Request, Response};
use Spark\Testing\{ApplicationTestCase, TestResponse};

final class FireAdapterTest extends ApplicationTestCase
{
    protected function createApplication(): Application
    {
        $app = (new Application(dirname(__DIR__)))->withApp(
            config: [
                'app' => [
                    'debug' => false,
                    'key' => str_repeat('a', 32),
                    'views_dir' => dirname(__DIR__) . '/fixtures',
                    'storage_dir' => $this->storagePath,
                ]
            ],
            providers: [FireServiceProvider::class],
        )->withMiddleware(queue: [FireMiddleware::class]);

        Route::fire('/about', 'page', ['message' => '<Hello>'])->name('about');
        Route::get('/explicit-title', fn() => Fire::render('page', ['message' => 'Hello'], 'Explicit'));
        Route::get('/navigate', fn() => Fire::navigate('/next', 303));
        Route::get('/redirect', fn() => Fire::redirect('/login', 307));
        Route::post('/success', fn() => Fire::success('0', ['count' => 0]));
        Route::get('/error', fn() => Fire::error('Failure', 503));
        Route::post('/validation', fn() => throw ValidationException::withMessages(['email' => 'Invalid']));
        Route::get('/raw', fn() => 'raw response');
        Route::get('/array', fn() => ['message' => 'raw array']);
        Route::get('/early', function () {
            (new Response('early response'))->send();
        });
        return $app;
    }

    public function testComposerAutoloadsHelpers(): void
    {
        $this->assertTrue(function_exists('fire'));
        $this->assertTrue(function_exists('is_fire_js'));
        $this->assertTrue(function_exists('is_fire_preload'));
        $this->assertTrue(function_exists('is_fire_partial'));
        $this->assertInstanceOf(FireService::class, fire());
        $this->assertFalse(is_fire_js());
        $this->assertFalse(is_fire_preload());
        $this->assertFalse(is_fire_partial());
        (new TestResponse(fire('page', ['message' => 'Helper render'])))
            ->assertOk()->assertSee('<h1>Helper render</h1>');
    }

    public function testNativePageUsesFullLayout(): void
    {
        $this->get('/about')->assertOk()
            ->assertSee('<!doctype html>')
            ->assertSee('<h1>&lt;Hello&gt;</h1>')
            ->assertHeader('Vary', 'X-FireLine')
            ->assertHeaderMissing('X-FireLine');
    }

    public function testFirePageUsesEscapedFragmentAndSectionTitle(): void
    {
        $response = $this->getJson('/about', ['X-FireLine' => '1'])
            ->assertOk()->assertJsonPath('title', 'Adapter page')
            ->assertHeader('X-FireLine', '1')->assertHeader('Vary', 'X-FireLine');
        $this->assertStringStartsWith('<div ', trim($response->json('html')));
        $this->assertStringNotContainsString('<html>', $response->json('html'));
        $this->assertStringContainsString('&lt;Hello&gt;', $response->json('html'));
        $this->assertStringContainsString('no-store', $response->response->getHeaders()['Cache-Control']);
    }

    public function testExplicitTitleOverridesSection(): void
    {
        $this->getJson('/explicit-title', ['X-FireLine' => '1'])
            ->assertOk()->assertJsonPath('title', 'Explicit');
    }

    public function testServiceUsesCurrentRequestAcrossSequentialRequests(): void
    {
        $this->get('/about')->assertSee('<!doctype html>');
        $this->get('/about', ['X-FireLine' => ' YES '])->assertJsonPath('title', 'Adapter page');
        $this->get('/about')->assertSee('<!doctype html>')->assertHeaderMissing('X-FireLine');
    }

    public function testServiceDetectsAdvancedHeaders(): void
    {
        $this->get('/about', ['X-FireLine-Preload' => '1', 'X-FireLine-Partial' => '0']);
        $this->assertTrue(Fire::isPreload());
        $this->assertFalse(Fire::isPartial());

        $this->get('/about', ['X-FireLine-Preload' => '0', 'X-FireLine-Partial' => '1']);
        $this->assertFalse(Fire::isPreload());
        $this->assertTrue(Fire::isPartial());
    }

    public function testNativeRedirectStatusAndDestination(): void
    {
        $this->get('/navigate')->assertRedirect('/next', 303)->assertHeader('Vary', 'X-FireLine');
        $this->get('/redirect')->assertRedirect('/login', 307);
    }

    public function testFireRedirectsUseSuccessfulEnvelopes(): void
    {
        $this->withHeaders(['X-FireLine' => '1']);
        $this->get('/navigate')->assertOk()->assertExactJson(['navigate' => '/next']);
        $this->get('/redirect')->assertOk()->assertExactJson(['redirect' => '/login']);
    }

    public function testSuccessRetainsFalseyDataWithoutLeakingRedirectHeaders(): void
    {
        $this->get('/redirect')->assertRedirect('/login', 307);
        $this->post('/success')->assertOk()->assertHeaderMissing('Location')
            ->assertExactJson(['status' => 'success', 'message' => '0', 'data' => ['count' => 0]]);
    }

    public function testErrorKeepsHttpStatus(): void
    {
        $this->getJson('/error')->assertStatus(503)
            ->assertExactJson(['status' => 'error', 'message' => 'Failure']);
    }

    public function testMiddlewareConvertsValidationThroughHttpPipeline(): void
    {
        $this->postJson('/validation', [], ['X-FireLine' => '1'])
            ->assertUnprocessable()->assertJsonValidationErrors(['email'])
            ->assertJsonPath('errors.email', ['Invalid'])->assertHeader('X-FireLine', '1');
    }

    public function testEmptyValidationBagIsJsonObject(): void
    {
        (new TestResponse(Fire::handleValidation('Invalid', [])))
            ->assertUnprocessable()->assertSee('"errors":{}');
    }

    public function testNativeValidationIsRethrownByMiddleware(): void
    {
        $this->expectException(ValidationException::class);
        (new FireMiddleware())->handle(
            $this->app->make(Request::class),
            fn() => throw ValidationException::withMessages([]),
        );
    }

    public function testNormalizedAndEarlyResponsesReceiveMiddlewareHeaders(): void
    {
        $this->withHeaders(['X-FireLine' => '1']);
        $this->get('/raw')->assertOk()->assertContent('raw response')->assertHeader('X-FireLine', '1');
        $this->get('/array')->assertOk()->assertExactJson(['message' => 'raw array'])->assertHeader('X-FireLine', '1');
        $this->get('/early')->assertOk()->assertContent('early response')->assertHeader('X-FireLine', '1');
    }

    public function testFireRouteDoesNotAcceptPost(): void
    {
        // Spark treats an unmatched HTTP method as a route-not-found response.
        $this->postJson('/about')->assertNotFound();
    }
}
