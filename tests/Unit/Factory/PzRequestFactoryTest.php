<?php

declare(strict_types=1);

namespace Puzl\PzRequest\Tests\Unit\Factory;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Puzl\PzRequest\Adapter\Guzzle\PzRequestGuzzle;
use Puzl\PzRequest\Contract\PzRequestInterface;
use Puzl\PzRequest\Factory\PzRequestEnum;
use Puzl\PzRequest\Factory\PzRequestFactory;
use ReflectionClass;
use ReflectionMethod;

final class PzRequestFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        putenv('PZREQUEST_ADAPTER');
    }

    protected function tearDown(): void
    {
        putenv('PZREQUEST_ADAPTER');
        parent::tearDown();
    }

    public function test_make_without_argument_returns_guzzle_adapter_by_default(): void
    {
        self::assertInstanceOf(PzRequestGuzzle::class, PzRequestFactory::make());
    }

    public function test_make_with_explicit_guzzle_returns_guzzle_adapter(): void
    {
        self::assertInstanceOf(PzRequestGuzzle::class, PzRequestFactory::make(PzRequestEnum::GUZZLE));
    }

    public function test_make_with_symfony_http_client_falls_back_to_guzzle(): void
    {
        self::assertInstanceOf(PzRequestGuzzle::class, PzRequestFactory::make(PzRequestEnum::SYMFONY_HTTP_CLIENT));
    }

    public function test_make_with_others_falls_back_to_guzzle(): void
    {
        self::assertInstanceOf(PzRequestGuzzle::class, PzRequestFactory::make(PzRequestEnum::OTHERS));
    }

    public function test_make_returns_pz_request_interface(): void
    {
        self::assertInstanceOf(PzRequestInterface::class, PzRequestFactory::make());
    }

    public function test_make_reads_env_variable_when_argument_absent(): void
    {
        putenv('PZREQUEST_ADAPTER=GUZZLE');

        self::assertInstanceOf(PzRequestGuzzle::class, PzRequestFactory::make());
    }

    public function test_make_falls_back_to_guzzle_when_env_holds_unknown_name(): void
    {
        putenv('PZREQUEST_ADAPTER=NONEXISTENT');

        self::assertInstanceOf(PzRequestGuzzle::class, PzRequestFactory::make());
    }

    public function test_make_falls_back_to_guzzle_when_env_holds_empty_string(): void
    {
        putenv('PZREQUEST_ADAPTER=');

        self::assertInstanceOf(PzRequestGuzzle::class, PzRequestFactory::make());
    }

    public function test_make_explicit_argument_overrides_env_variable(): void
    {
        putenv('PZREQUEST_ADAPTER=SYMFONY_HTTP_CLIENT');

        self::assertInstanceOf(
            PzRequestGuzzle::class,
            PzRequestFactory::make(PzRequestEnum::GUZZLE),
        );
    }

    #[RunInSeparateProcess]
    public function test_make_uses_config_value_when_function_exists(): void
    {
        require_once __DIR__ . '/Fixtures/config_function.php';

        self::assertInstanceOf(PzRequestGuzzle::class, PzRequestFactory::make());
    }

    #[RunInSeparateProcess]
    public function test_make_falls_back_from_config_to_env_when_config_returns_null(): void
    {
        require_once __DIR__ . '/Fixtures/config_function_null.php';
        putenv('PZREQUEST_ADAPTER=GUZZLE');

        self::assertInstanceOf(PzRequestGuzzle::class, PzRequestFactory::make());
    }

    public function test_make_returns_a_fresh_instance_per_call(): void
    {
        $a = PzRequestFactory::make();
        $b = PzRequestFactory::make();

        self::assertNotSame($a, $b);
    }

    public function test_factory_creates_adapter_compatible_with_pz_request_base_state(): void
    {
        $pz = PzRequestFactory::make();
        $pz->endpoint('https://x')->addHeader('A', '1');

        $endpointProperty = (new ReflectionClass($pz))->getProperty('endpoint');

        self::assertSame('https://x', $endpointProperty->getValue($pz));
    }

    public function test_factory_output_implements_full_contract(): void
    {
        $pz = PzRequestFactory::make();
        $contractMethods = (new ReflectionClass(PzRequestInterface::class))->getMethods();

        foreach ($contractMethods as $method) {
            self::assertTrue(
                method_exists($pz, $method->getName()),
                "Adapter must implement {$method->getName()} from contract.",
            );
        }

        self::assertCount(30, array_filter(
            $contractMethods,
            static fn (ReflectionMethod $m): bool => true,
        ));
    }
}
