<?php

declare(strict_types=1);

namespace Puzl\PzRequest\Factory;

use Puzl\PzRequest\Adapter\Guzzle\PzRequestGuzzle;
use Puzl\PzRequest\Contract\PzRequestInterface;

/**
 * Ponto de entrada único para criar instâncias do `PzRequest`.
 *
 * Resolução do adapter (ordem de prioridade):
 * 1. Argumento explícito (`$adapter`).
 * 2. `config('pzrequest.adapter')` quando rodando em Laravel.
 * 3. Variável de ambiente `PZREQUEST_ADAPTER`.
 * 4. Default `PzRequestEnum::GUZZLE`.
 *
 * Adapters declarados na enum mas sem implementação concreta nesta versão
 * (`SYMFONY_HTTP_CLIENT`, `OTHERS`) **não lançam** — caem em fallback para
 * `PzRequestGuzzle`.
 */
final class PzRequestFactory
{
    public static function make(?PzRequestEnum $adapter = null): PzRequestInterface
    {
        $resolved = $adapter ?? self::resolveAdapterFromConfig() ?? PzRequestEnum::GUZZLE;

        return match ($resolved) {
            PzRequestEnum::GUZZLE => new PzRequestGuzzle(),
            default => new PzRequestGuzzle(),
        };
    }

    /**
     * Lê `config('pzrequest.adapter')` se a função `config()` existir
     * (ambiente Laravel), com fallback para `getenv('PZREQUEST_ADAPTER')`.
     * Retorna `null` quando ambos estão vazios ou contêm nome desconhecido.
     */
    private static function resolveAdapterFromConfig(): ?PzRequestEnum
    {
        $value = null;

        if (function_exists('config')) {
            $value = config('pzrequest.adapter');
        }

        if ($value === null || $value === '') {
            $envValue = getenv('PZREQUEST_ADAPTER');
            $value = $envValue === false ? null : $envValue;
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        return PzRequestEnum::tryFromName($value);
    }
}
