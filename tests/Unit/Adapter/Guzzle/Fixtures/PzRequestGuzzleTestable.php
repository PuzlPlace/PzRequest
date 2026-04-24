<?php

declare(strict_types=1);

namespace Puzl\PzRequest\Tests\Unit\Adapter\Guzzle\Fixtures;

use GuzzleHttp\ClientInterface;
use Puzl\PzRequest\Adapter\Guzzle\PzRequestGuzzle;

/**
 * Subclasse de teste que expõe o setter de `clientOverride` (para injetar
 * `MockHandler`) e o `buildOptions()` (para inspecionar o array passado
 * ao Guzzle). Esses pontos ficam `protected` em produção para evitar uso
 * indevido — só os testes precisam deles.
 */
final class PzRequestGuzzleTestable extends PzRequestGuzzle
{
    public function setClientOverride(ClientInterface $client): void
    {
        $this->clientOverride = $client;
    }

    /**
     * @return array<string, mixed>
     */
    public function exposeBuildOptions(): array
    {
        return $this->buildOptions();
    }
}
