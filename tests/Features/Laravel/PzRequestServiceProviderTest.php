<?php

declare(strict_types=1);

namespace Puzl\PzRequest\Tests\Features\Laravel;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Puzl\PzRequest\Adapter\Guzzle\PzRequestGuzzle;
use Puzl\PzRequest\Factory\PzRequestFactory;
use Puzl\PzRequest\Laravel\PzRequestServiceProvider;
use Puzl\PzRequest\Tests\Features\Laravel\Fixtures\AppStub;

final class PzRequestServiceProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        putenv('PZREQUEST_ADAPTER');
        ServiceProvider::$publishGroups = [];
        ServiceProvider::$publishes = [];
    }

    protected function tearDown(): void
    {
        putenv('PZREQUEST_ADAPTER');
        ServiceProvider::$publishGroups = [];
        ServiceProvider::$publishes = [];
        Container::setInstance(null);
        parent::tearDown();
    }

    private function makeApp(bool $runningInConsole = true): AppStub
    {
        $app = new AppStub($runningInConsole);
        $app->instance('config', new Repository());
        Container::setInstance($app);

        return $app;
    }

    public function test_register_merges_config_into_container(): void
    {
        $app = $this->makeApp();
        $provider = new PzRequestServiceProvider($app);

        $provider->register();

        $config = $app->make('config');
        self::assertSame('GUZZLE', $config->get('pzrequest.adapter'));
    }

    public function test_boot_registers_publishes_under_pzrequest_config_tag(): void
    {
        $app = $this->makeApp(true);
        $provider = new PzRequestServiceProvider($app);

        $provider->boot();

        $paths = ServiceProvider::pathsToPublish(PzRequestServiceProvider::class, 'pzrequest-config');
        self::assertNotEmpty($paths);

        $sources = array_keys($paths);
        $resolved = realpath(PzRequestServiceProvider::CONFIG_PATH);
        $foundSource = false;
        foreach ($sources as $source) {
            if (realpath($source) === $resolved) {
                $foundSource = true;
                break;
            }
        }
        self::assertTrue($foundSource, 'Publish source must point to the package config file.');
    }

    public function test_boot_does_nothing_outside_console_context(): void
    {
        $app = $this->makeApp(false);
        $provider = new PzRequestServiceProvider($app);

        $provider->boot();

        $paths = ServiceProvider::pathsToPublish(PzRequestServiceProvider::class, 'pzrequest-config');
        self::assertSame([], $paths);
    }

    public function test_composer_json_declares_provider_in_laravel_auto_discovery(): void
    {
        $composerPath = realpath(__DIR__ . '/../../../composer.json');
        self::assertNotFalse($composerPath);

        $raw = file_get_contents($composerPath);
        self::assertIsString($raw);

        $manifest = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(
            ['Puzl\\PzRequest\\Laravel\\PzRequestServiceProvider'],
            $manifest['extra']['laravel']['providers'],
        );
    }

    #[RunInSeparateProcess]
    public function test_factory_resolves_adapter_using_config_after_provider_registers(): void
    {
        $app = $this->makeApp();
        $provider = new PzRequestServiceProvider($app);
        $provider->register();

        $app->make('config')->set('pzrequest.adapter', 'GUZZLE');

        self::assertInstanceOf(PzRequestGuzzle::class, PzRequestFactory::make());
    }
}
