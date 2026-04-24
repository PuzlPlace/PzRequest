<?php

declare(strict_types=1);

namespace Puzl\PzRequest\Laravel;

use Illuminate\Support\ServiceProvider;

/**
 * Service Provider Laravel do PzRequest.
 *
 * Mantém o pacote "plug-and-play" via auto-discovery (declarado em
 * `composer.json` `extra.laravel.providers`):
 *
 * - `register()`: mescla `config/pzrequest.php` em `config('pzrequest.*')`.
 * - `boot()`: registra o `publishes` com a tag `pzrequest-config` apenas
 *   quando rodando em console (otimização de memória em runtime web).
 *
 * Não vincula `PzRequestInterface` no container — quem precisar de DI
 * via construtor pode fazer no próprio `AppServiceProvider`.
 */
class PzRequestServiceProvider extends ServiceProvider
{
    public const CONFIG_PATH = __DIR__ . '/../../config/pzrequest.php';

    public const PUBLISH_TAG = 'pzrequest-config';

    public function register(): void
    {
        $this->mergeConfigFrom(self::CONFIG_PATH, 'pzrequest');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                self::CONFIG_PATH => $this->configPath('pzrequest.php'),
            ], self::PUBLISH_TAG);
        }
    }

    /**
     * Resolve o destino do publish. Em ambiente Laravel completo usa o
     * helper `config_path()`; em testes sob `Illuminate\Container\Container`
     * puro, faz fallback para `basePath('config/...')`.
     */
    protected function configPath(string $file): string
    {
        return function_exists('config_path')
            ? config_path($file)
            : $this->app->basePath('config/' . $file);
    }
}
