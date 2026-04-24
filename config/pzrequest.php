<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Adapter HTTP padrão
    |--------------------------------------------------------------------------
    |
    | Define qual adapter o `PzRequestFactory::make()` instancia por padrão.
    | Valores aceitos: 'GUZZLE', 'SYMFONY_HTTP_CLIENT', 'OTHERS'.
    | Apenas 'GUZZLE' tem implementação concreta nesta versão; os demais
    | sofrem fallback silencioso para Guzzle.
    |
    */
    'adapter' => env('PZREQUEST_ADAPTER', 'GUZZLE'),
];
