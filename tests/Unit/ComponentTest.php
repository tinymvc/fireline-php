<?php

namespace Tests\Unit;

use Spark\Fire\Component;
use Spark\Fire\ComponentErrorBag;
use Spark\Fire\ComponentSecurity;
use Spark\Fire\FireMiddleware;
use Spark\Fire\FireServiceProvider;
use Spark\Foundation\Application;
use Spark\Foundation\Exceptions\ValidationException;
use Spark\Testing\ApplicationTestCase;

class DummyCounter extends Component
{
    public int $count = 0;
    public string $name = 'initial';
    public bool $active = false;
    public array $tags = [];
    protected string $secret = 'hidden';

    protected bool $_hookRun = false;
    protected ?string $_updatedPropName = null;

    public function updatingName(string $val): void
    {
        $this->_hookRun = true;
    }

    public function updatedName(string $val): void
    {
        $this->_updatedPropName = $val;
    }

    public function getHookRun(): bool
    {
        return $this->_hookRun;
    }

    public function getUpdatedPropName(): ?string
    {
        return $this->_updatedPropName;
    }

    public function increment(int $by = 1): void
    {
        $this->count += $by;
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|min:3',
        ]);
        $this->dispatch('saved', ['count' => $this->count]);
    }

    public function triggerRedirect(): void
    {
        $this->redirect('/home');
    }

    public function triggerNavigate(): void
    {
        $this->navigate('/dashboard');
    }

    public function render(): string
    {
        return "<div><h1>{$this->name}</h1><span>{$this->count}</span></div>";
    }
}

final class ComponentTest extends ApplicationTestCase
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

    public function testGetStateExtractsPublicPropertiesOnly(): void
    {
        $component = new DummyCounter();
        $state = $component->getState();

        $this->assertSame(0, $state['count']);
        $this->assertSame('initial', $state['name']);
        $this->assertSame(false, $state['active']);
        $this->assertSame([], $state['tags']);
        $this->assertArrayNotHasKey('secret', $state);
        $this->assertArrayNotHasKey('_hookRun', $state);
    }

    public function testFillHydratesAndCoercesTypes(): void
    {
        $component = new DummyCounter();
        $component->fill([
            'count' => '42',
            'name' => 'updated',
            'active' => '1',
            'tags' => ['php', 'spark'],
            'secret' => 'hacked',
        ]);

        $this->assertSame(42, $component->count);
        $this->assertSame('updated', $component->name);
        $this->assertSame(true, $component->active);
        $this->assertSame(['php', 'spark'], $component->tags);
        $this->assertSame(['count' => 42, 'name' => 'updated', 'active' => true, 'tags' => ['php', 'spark']], $component->getState());
    }

    public function testSyncPropertyRunsLifecycleHooks(): void
    {
        $component = new DummyCounter();
        $component->syncProperty('name', 'new-name');

        $this->assertTrue($component->getHookRun());
        $this->assertSame('new-name', $component->getUpdatedPropName());
        $this->assertSame('new-name', $component->name);
    }

    public function testValidationThrowsExceptionAndCapturesErrors(): void
    {
        $component = new DummyCounter();
        $component->name = 'a'; // fails min:3

        $this->expectException(ValidationException::class);
        $component->save();
    }

    public function testValidationCapturesErrorsInBag(): void
    {
        $component = new DummyCounter();
        $component->name = 'a';

        try {
            $component->save();
        } catch (ValidationException) {
            $this->assertTrue($component->getErrorBag()->has('name'));
            $this->assertNotEmpty($component->getErrorBag()->first('name'));
        }
    }

    public function testEventsDispatching(): void
    {
        $component = new DummyCounter();
        $component->name = 'valid-name';
        $component->count = 5;
        $component->save();

        $dispatched = $component->getDispatched();
        $this->assertCount(1, $dispatched);
        $this->assertSame('saved', $dispatched[0]['name']);
        $this->assertSame([['count' => 5]], $dispatched[0]['params']);
    }

    public function testRedirectAndNavigate(): void
    {
        $component = new DummyCounter();
        $component->triggerRedirect();
        $this->assertSame('/home', $component->getRedirect());

        $component->triggerNavigate();
        $this->assertSame('/dashboard', $component->getNavigate());
    }

    public function testSecuritySignatureAndVerification(): void
    {
        $id = 'comp-test-123';
        $class = DummyCounter::class;

        $sig = ComponentSecurity::sign($id, $class);
        $this->assertNotEmpty($sig);
        $this->assertTrue(ComponentSecurity::verify($id, $class, $sig));
        $this->assertFalse(ComponentSecurity::verify($id, $class, 'invalid-signature'));
        $this->assertFalse(ComponentSecurity::verify('other-id', $class, $sig));
    }

    public function testSecurityMethodCallable(): void
    {
        $component = new DummyCounter();

        $this->assertTrue(ComponentSecurity::isMethodCallable($component, 'increment'));
        $this->assertTrue(ComponentSecurity::isMethodCallable($component, 'save'));

        // Forbidden base methods
        $this->assertFalse(ComponentSecurity::isMethodCallable($component, 'getState'));
        $this->assertFalse(ComponentSecurity::isMethodCallable($component, 'fill'));
        $this->assertFalse(ComponentSecurity::isMethodCallable($component, 'render'));
        $this->assertFalse(ComponentSecurity::isMethodCallable($component, 'validate'));
        $this->assertFalse(ComponentSecurity::isMethodCallable($component, 'boot'));

        // Forbidden magic or non-existent methods
        $this->assertFalse(ComponentSecurity::isMethodCallable($component, '__construct'));
        $this->assertFalse(ComponentSecurity::isMethodCallable($component, 'nonExistent'));
    }

    public function testErrorBagOperations(): void
    {
        $bag = new ComponentErrorBag(['email' => ['Invalid email format.'], 'name' => 'Name is required.']);

        $this->assertTrue($bag->any());
        $this->assertTrue($bag->has('email'));
        $this->assertTrue($bag->has('name'));
        $this->assertFalse($bag->has('password'));

        $this->assertSame('Invalid email format.', $bag->first('email'));
        $this->assertSame('Name is required.', $bag->first('name'));
        $this->assertSame(['Invalid email format.'], $bag->get('email'));
        $this->assertSame('Invalid email format.', $bag['email']);
    }
}
