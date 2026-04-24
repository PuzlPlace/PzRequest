<?php

declare(strict_types=1);

namespace Puzl\PzRequest\Tests\Unit\Laravel;

use PHPUnit\Framework\TestCase;
use Puzl\PzRequest\Laravel\PzRequestServiceProvider;

final class PzRequestServiceProviderTest extends TestCase
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

    public function test_constants_match_expected_paths_and_tag(): void
    {
        $resolved = realpath(PzRequestServiceProvider::CONFIG_PATH);
        $expected = realpath(__DIR__ . '/../../../config/pzrequest.php');

        self::assertNotFalse($resolved, 'CONFIG_PATH must resolve to a real file.');
        self::assertSame($expected, $resolved);
        self::assertSame('pzrequest-config', PzRequestServiceProvider::PUBLISH_TAG);
    }

    public function test_config_file_returns_array_with_adapter_key(): void
    {
        $cfg = require PzRequestServiceProvider::CONFIG_PATH;

        self::assertIsArray($cfg);
        self::assertArrayHasKey('adapter', $cfg);
    }

    public function test_config_default_adapter_is_guzzle_when_env_absent(): void
    {
        $cfg = require PzRequestServiceProvider::CONFIG_PATH;

        self::assertSame('GUZZLE', $cfg['adapter']);
    }
}
