<?php

declare(strict_types=1);

if (! function_exists('config')) {
    /**
     * Variante da fixture que faz `config('pzrequest.adapter')` retornar
     * `null`. Usada para validar que a Factory cai para `getenv` como
     * segunda prioridade quando o Laravel está presente mas a chave não
     * está populada.
     */
    function config(string $key, mixed $default = null): mixed
    {
        return $default;
    }
}
