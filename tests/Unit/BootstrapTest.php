<?php

declare(strict_types=1);

namespace Puzl\PzRequest\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Bootstrap test: garante que a infraestrutura mínima do pacote está em pé
 * — autoload PSR-4, manifesto Composer e configuração PHPUnit —, servindo
 * como gate antes que qualquer outro teste tente carregar código.
 */
final class BootstrapTest extends TestCase
{
    private function packageRoot(): string
    {
        $root = realpath(__DIR__ . '/../..');
        self::assertIsString($root, 'Could not resolve package root.');

        return $root;
    }

    /**
     * Valida que o autoloader PSR-4 do pacote registra o namespace
     * `Puzl\PzRequest\Tests\` apontando para `tests/`. Se o autoload-dev
     * estiver mal configurado em `composer.json`, esta classe nem sequer
     * seria carregada — então a checagem via `class_exists` confirma que
     * o registro do namespace está vivo.
     */
    public function test_psr4_autoload_resolves_package_namespace(): void
    {
        self::assertTrue(
            class_exists(self::class, false),
            'Test namespace Puzl\\PzRequest\\Tests\\ is not autoloaded via PSR-4.'
        );

        self::assertTrue(
            class_exists(\PHPUnit\Framework\TestCase::class),
            'PHPUnit\\Framework\\TestCase must be available via Composer autoload.'
        );
    }

    /**
     * Valida que o `composer.json` declara todas as chaves obrigatórias
     * para o pacote: dependências runtime e dev, autoload PSR-4 (produção
     * e testes), scripts de teste e providers Laravel para auto-discovery.
     */
    public function test_composer_json_declares_required_keys(): void
    {
        $path = $this->packageRoot() . '/composer.json';
        self::assertFileExists($path, 'composer.json must exist at package root.');

        $raw = file_get_contents($path);
        self::assertIsString($raw, 'composer.json must be readable.');

        $manifest = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($manifest, 'composer.json must decode to an associative array.');

        self::assertIsArray($manifest['require'] ?? null, 'Missing "require" section.');
        self::assertArrayHasKey('php', $manifest['require']);
        self::assertArrayHasKey('guzzlehttp/guzzle', $manifest['require']);
        self::assertArrayHasKey('illuminate/support', $manifest['require']);

        self::assertIsArray($manifest['require-dev'] ?? null, 'Missing "require-dev" section.');
        self::assertArrayHasKey('phpunit/phpunit', $manifest['require-dev']);

        self::assertIsArray($manifest['autoload']['psr-4'] ?? null, 'Missing autoload.psr-4.');
        self::assertSame('src/', $manifest['autoload']['psr-4']['Puzl\\PzRequest\\'] ?? null);

        self::assertIsArray($manifest['autoload-dev']['psr-4'] ?? null, 'Missing autoload-dev.psr-4.');
        self::assertSame('tests/', $manifest['autoload-dev']['psr-4']['Puzl\\PzRequest\\Tests\\'] ?? null);

        self::assertIsArray($manifest['scripts'] ?? null, 'Missing "scripts" section.');
        self::assertArrayHasKey('test', $manifest['scripts']);
        self::assertArrayHasKey('test:coverage', $manifest['scripts']);

        $providers = $manifest['extra']['laravel']['providers'] ?? null;
        self::assertIsArray($providers, 'Missing extra.laravel.providers for auto-discovery.');
        self::assertSame(
            'Puzl\\PzRequest\\Laravel\\PzRequestServiceProvider',
            $providers[0] ?? null,
            'PzRequestServiceProvider must be registered for Laravel auto-discovery.'
        );
    }

    /**
     * Valida que `phpunit.xml.dist` declara as suítes `unit` e `features`
     * apontando, respectivamente, para `tests/Unit` e `tests/Features`.
     * Sem isso, o smoke test em subprocess não conseguiria invocar
     * `--testsuite unit` corretamente.
     */
    public function test_phpunit_xml_dist_declares_unit_and_features_suites(): void
    {
        $path = $this->packageRoot() . '/phpunit.xml.dist';
        self::assertFileExists($path, 'phpunit.xml.dist must exist at package root.');

        $previousErrors = libxml_use_internal_errors(true);
        $xml = simplexml_load_file($path);
        libxml_use_internal_errors($previousErrors);

        self::assertNotFalse($xml, 'phpunit.xml.dist must be valid XML.');

        $suites = [];
        foreach ($xml->testsuites->testsuite as $suite) {
            $name = (string) $suite['name'];
            $suites[$name] = trim((string) $suite->directory);
        }

        self::assertArrayHasKey('unit', $suites, 'Test suite "unit" must be declared.');
        self::assertSame('tests/Unit', $suites['unit']);

        self::assertArrayHasKey('features', $suites, 'Test suite "features" must be declared.');
        self::assertSame('tests/Features', $suites['features']);
    }
}
