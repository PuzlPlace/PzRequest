<?php

declare(strict_types=1);

namespace Puzl\PzRequest\Factory;

/**
 * Adapters HTTP disponíveis para a `PzRequestFactory`.
 *
 * Apenas `GUZZLE` tem implementação concreta nesta versão. Os demais casos
 * existem para preservar a interface estável quando novos adapters forem
 * adicionados — a Factory aplica fallback silencioso para Guzzle.
 */
enum PzRequestEnum: int
{
    case GUZZLE = 0;
    case SYMFONY_HTTP_CLIENT = 1;
    case OTHERS = 2;

    /**
     * Resolve um caso a partir do nome (case-sensitive).
     *
     * O matching é estritamente igual ao `->name` — `'guzzle'` lowercase
     * retorna `null`. Isso evita ambiguidade entre nomes públicos e
     * variações casuais vindas de env vars / config.
     */
    public static function tryFromName(string $name): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->name === $name) {
                return $case;
            }
        }

        return null;
    }
}
