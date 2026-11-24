<?php

namespace Tests\Unit;

use Spark\Fire\Console\MakeComponentCommand;
use Spark\Testing\ApplicationTestCase;
use Spark\Utils\File;

final class MakeComponentCommandTest extends ApplicationTestCase
{
    private string $tempAppDir;

    protected function createApplication(): \Spark\Foundation\Application
    {
        return (new \Spark\Foundation\Application(dirname(__DIR__)))->withApp(
            config: [
                'app' => [
                    'debug' => false,
                    'key' => str_repeat('b', 32),
                    'views_dir' => dirname(__DIR__) . '/fixtures',
                    'storage_dir' => $this->storagePath,
                ],
            ],
            providers: [\Spark\Fire\FireServiceProvider::class],
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempAppDir = sys_get_temp_dir() . '/fireline_test_' . uniqid();
        mkdir($this->tempAppDir . '/app/Components', 0777, true);
        mkdir($this->tempAppDir . '/resources/views/components', 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempAppDir)) {
            File::deleteDirectory($this->tempAppDir);
        }
        parent::tearDown();
    }

    public function testMakeComponentCreatesFiles(): void
    {
        $cwd = getcwd();
        chdir($this->tempAppDir);

        try {
            $cmd = new MakeComponentCommand();
            $cmd->handle(['_args' => ['UserProfile']]);

            $this->assertFileExists($this->tempAppDir . '/app/Components/UserProfile.php');
            $this->assertFileExists($this->tempAppDir . '/resources/views/components/user-profile.blade.php');

            $classContent = file_get_contents($this->tempAppDir . '/app/Components/UserProfile.php');
            $this->assertStringContainsString('namespace App\Components;', $classContent);
            $this->assertStringContainsString('class UserProfile extends Component', $classContent);
            $this->assertStringContainsString('return \'components.user-profile\';', $classContent);

            $viewContent = file_get_contents($this->tempAppDir . '/resources/views/components/user-profile.blade.php');
            $this->assertStringContainsString('UserProfile Component', $viewContent);
            $this->assertStringContainsString('fire:click="increment"', $viewContent);
        } finally {
            chdir($cwd);
        }
    }
}
