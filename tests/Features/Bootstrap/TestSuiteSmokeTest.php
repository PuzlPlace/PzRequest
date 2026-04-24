<?php

declare(strict_types=1);

namespace Puzl\PzRequest\Tests\Features\Bootstrap;

use PHPUnit\Framework\TestCase;

/**
 * Smoke test: executa a suíte `unit` em um processo PHP separado via
 * `proc_open`. Garante isolamento total e detecta regressões silenciosas
 * que `composer test` direto poderia mascarar — bootstrap inválido,
 * autoload PSR-4 quebrado quando suítes rodam isoladamente, etc.
 *
 * O subprocess invoca **somente** a suíte `unit` para evitar recursão
 * (este teste vive em `features`).
 */
final class TestSuiteSmokeTest extends TestCase
{
    public function test_unit_tests_pass_via_phpunit_subprocess(): void
    {
        $repoRoot = dirname(__DIR__, 3);
        $cmd = sprintf(
            '%s -c %s --testsuite unit',
            escapeshellarg($repoRoot . '/vendor/bin/phpunit'),
            escapeshellarg($repoRoot . '/phpunit.xml.dist'),
        );

        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        // Herdamos todas as envs do processo atual (PATH etc.) e só
        // sobrescrevemos XDEBUG_MODE — assim o `proc_open` interno do
        // PHPUnit (#[RunInSeparateProcess]) consegue achar o binário PHP.
        $env = array_merge(
            array_filter(getenv(), 'is_string'),
            ['XDEBUG_MODE' => 'off'],
        );

        $process = proc_open($cmd, $descriptor, $pipes, $repoRoot, $env);
        self::assertIsResource($process, 'Falha ao abrir subprocess do PHPUnit.');

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        self::assertSame(
            0,
            $exitCode,
            sprintf("Suíte unit falhou em subprocess.\nSTDOUT:\n%s\nSTDERR:\n%s", $stdout, $stderr),
        );
    }
}
