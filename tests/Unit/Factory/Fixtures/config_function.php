<?php

declare(strict_types=1);

if (! function_exists('config')) {
    /**
     * Fixture que simula a função global `config()` do Laravel para os testes
     * da `PzRequestFactory`. Retorna 'GUZZLE' para a chave esperada e o
     * default para qualquer outra. Por ser função global, este arquivo deve
     * ser carregado via `require_once` apenas dentro de testes em processo
     * isolado (`#[RunInSeparateProcess]`).
     */
    function config(string $key, mixed $default = null): mixed
    {
        $map = ['pzrequest.adapter' => 'GUZZLE'];

        return $map[$key] ?? $default;
    }
}
