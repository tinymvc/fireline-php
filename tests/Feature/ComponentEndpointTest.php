<?php

namespace Tests\Feature;

use Spark\Fire\Component;
use Spark\Fire\ComponentSecurity;
use Spark\Fire\Fire;
use Spark\Fire\FireMiddleware;
use Spark\Fire\FireServiceProvider;
use Spark\Foundation\Application;
use Spark\Testing\ApplicationTestCase;

class TestCounterComponent extends Component
{
    public int $count = 1;
    public string $message = 'Hello';

    public function increment(int $by = 1): void
    {
        $this->count += $by;
    }

    public function submitInvalid(): void
    {
        $this->validate([
            'message' => 'required|min:10',
        ]);
    }

    public function triggerEvent(): void
    {
        $this->dispatch('counted', $this->count);
    }

    public function render(): string
    {
        $error = $this->getErrorBag()->first('message') ?? '';
        return "<div id=\"content\">Count: {$this->count}, Message: {$this->message}" . ($error ? " <span class=\"error\">{$error}</span>" : '') . "</div>";
    }
}

final class ComponentEndpointTest extends ApplicationTestCase
{
    protected function createApplication(): Application
    {
        return (new Application(dirname(__DIR__)))->withApp(
            config: [
                'app' => [
                    'debug' => false,
                    'key' => str_repeat('a', 32),
                    'views_dir' => dirname(__DIR__) . '/fixtures',
                    'storage_dir' => $this->storagePath,
                ],
            ],
            providers: [FireServiceProvider::class],
        )->withMiddleware(queue: [FireMiddleware::class]);
    }

    public function testMountInitialComponentHtml(): void
    {
        $html = Fire::component(TestCounterComponent::class, ['count' => 10, 'message' => 'Mounted']);

        $this->assertStringContainsString('data-fire-id=', $html);
        $this->assertStringContainsString('x-data="fireComponent(', $html);
        $this->assertStringContainsString('data-fire-content', $html);
        $this->assertStringContainsString('Count: 10, Message: Mounted', $html);
    }

    public function testEndpointStateSync(): void
    {
        $id = 'test-comp-1';
        $class = TestCounterComponent::class;
        $checksum = ComponentSecurity::sign($id, $class);

        $payload = [
            'id' => $id,
            'component' => $class,
            'checksum' => $checksum,
            'state' => ['count' => 20, 'message' => 'Synced'],
            'method' => '__sync',
            'params' => [],
        ];

        $res = $this->postJson('/_fire/component', $payload);

        $res->assertOk()
            ->assertHeader('X-FireLine', '1')
            ->assertJsonPath('id', $id)
            ->assertJsonPath('state.count', 20)
            ->assertJsonPath('state.message', 'Synced')
            ->assertSee('Count: 20, Message: Synced');
    }

    public function testEndpointActionExecution(): void
    {
        $id = 'test-comp-2';
        $class = TestCounterComponent::class;
        $checksum = ComponentSecurity::sign($id, $class);

        $payload = [
            'id' => $id,
            'component' => $class,
            'checksum' => $checksum,
            'state' => ['count' => 5, 'message' => 'Hello'],
            'method' => 'increment',
            'params' => [3],
        ];

        $res = $this->postJson('/_fire/component', $payload);

        $res->assertOk()
            ->assertJsonPath('state.count', 8)
            ->assertSee('Count: 8, Message: Hello');
    }

    public function testEndpointEventDispatching(): void
    {
        $id = 'test-comp-3';
        $class = TestCounterComponent::class;
        $checksum = ComponentSecurity::sign($id, $class);

        $payload = [
            'id' => $id,
            'component' => $class,
            'checksum' => $checksum,
            'state' => ['count' => 7, 'message' => 'Test'],
            'method' => 'triggerEvent',
            'params' => [],
        ];

        $res = $this->postJson('/_fire/component', $payload);

        $res->assertOk()
            ->assertJsonPath('events.0.name', 'counted')
            ->assertJsonPath('events.0.params.0', 7);
    }

    public function testEndpointValidationFailureReturnsInlineErrors(): void
    {
        $id = 'test-comp-4';
        $class = TestCounterComponent::class;
        $checksum = ComponentSecurity::sign($id, $class);

        $payload = [
            'id' => $id,
            'component' => $class,
            'checksum' => $checksum,
            'state' => ['count' => 1, 'message' => 'short'], // fails min:10
            'method' => 'submitInvalid',
            'params' => [],
        ];

        $res = $this->postJson('/_fire/component', $payload);

        // Validation halts method, returns 200 with re-rendered view displaying error
        $res->assertOk();
        $this->assertStringContainsString('class="error"', (string) $res->json('html'));
        $this->assertNotEmpty($res->json('errors.message.0'));
    }

    public function testEndpointRejectsTamperedChecksum(): void
    {
        $payload = [
            'id' => 'comp-hack',
            'component' => TestCounterComponent::class,
            'checksum' => 'forged-checksum',
            'state' => [],
            'method' => 'increment',
        ];

        $this->postJson('/_fire/component', $payload)
            ->assertForbidden()
            ->assertJsonPath('error', 'Invalid component checksum.');
    }

    public function testEndpointRejectsUnauthorizedMethod(): void
    {
        $id = 'comp-bad-method';
        $class = TestCounterComponent::class;
        $checksum = ComponentSecurity::sign($id, $class);

        $payload = [
            'id' => $id,
            'component' => $class,
            'checksum' => $checksum,
            'state' => [],
            'method' => 'getState', // base method is forbidden!
        ];

        $res = $this->postJson('/_fire/component', $payload);

        $res->assertForbidden();
        $this->assertStringContainsString('not allowed', (string) $res->json('error'));
    }
}
