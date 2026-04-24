# Task 6.0: Integração Laravel (Config + Service Provider)

## Informações Gerais

**Status**: 📦 Pronta para Iniciar
**Prioridade**: Média
**Dependências**: Task 5.0
**Assignee**: Não atribuído

---

## Objetivo

Habilitar o uso plug-and-play do `PzRequest` em projetos Laravel 10/11/12: criar `config/pzrequest.php` (com chave `adapter` lida da env `PZREQUEST_ADAPTER`), implementar `PzRequestServiceProvider` no padrão dos pacotes irmãos (`mergeConfigFrom` em `register()`, `publishes` com tag `pzrequest-config` em `boot()`), e garantir que o auto-discovery via `composer.json` funcione. Após esta tarefa, basta `composer require puzl/pzrequest` em qualquer Laravel para o pacote ser ativado automaticamente, e `php artisan vendor:publish --tag=pzrequest-config` deve copiar a config.

---

## Contexto

A integração Laravel é mínima por design (Tech Spec §2.3): o pacote é puramente runtime, sem comandos artisan, sem migrations, sem rotas. Apenas a config publicável e o registro do provider para que `PzRequestFactory::make()` consiga ler `config('pzrequest.adapter')` (Task 5.0). O Service Provider espelha exatamente o `PzMailServiceProvider`: `register()` mescla a config; `boot()` registra `publishes` com tag.

O `composer.json` (Task 1.0) já declara o provider em `extra.laravel.providers`. Esta tarefa só **valida** que essa declaração está correta e implementa o provider em si.

**Referências**:
- PRD: `tasks/prd-pzrequest/prd.md` - RF-09, RNF-03
- Tech Spec: `tasks/prd-pzrequest/techspec.md` - Seções 1.2, 2.3, 8.3 (`PzRequestServiceProviderTest`), 11.1 (Fase 5), 13.2

---

## Escopo

### O que ESTÁ no escopo:
- ✅ `config/pzrequest.php` com chave `adapter` lendo `PZREQUEST_ADAPTER`
- ✅ `src/Laravel/PzRequestServiceProvider.php` (estende `Illuminate\Support\ServiceProvider`)
- ✅ `register()`: `mergeConfigFrom(__DIR__.'/../../config/pzrequest.php', 'pzrequest')`
- ✅ `boot()`: `publishes([... => config_path('pzrequest.php')], 'pzrequest-config')`
- ✅ Validação de que `composer.json` (Task 1.0) declara o provider em `extra.laravel.providers`
- ✅ Teste de integração instanciando o provider sob `Illuminate\Container\Container` (sem subir Laravel completo)
- ✅ Teste de paths publicáveis e tag

### O que NÃO está no escopo:
- ❌ Vincular `PzRequestInterface` no container como singleton (consumidor pode fazer no app dele se quiser)
- ❌ Comando artisan (não há nenhum no PRD)
- ❌ Publicação de migrations/rotas/views (nenhum desses existe)

---

## Subtarefas

### 1. Criar arquivo de configuração
**Descrição**: Config publicável com a única chave necessária.

**Arquivos afetados**:
- `config/pzrequest.php` (novo)

**Implementação**:
```php
<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Adapter HTTP padrão
    |--------------------------------------------------------------------------
    |
    | Define qual adapter o PzRequestFactory::make() instancia por padrão.
    | Valores aceitos: 'GUZZLE', 'SYMFONY_HTTP_CLIENT', 'OTHERS'.
    | Apenas 'GUZZLE' tem implementação concreta nesta versão; os demais
    | sofrem fallback para Guzzle (RF-08).
    |
    */
    'adapter' => env('PZREQUEST_ADAPTER', 'GUZZLE'),
];
```

---

### 2. Implementar `PzRequestServiceProvider`
**Descrição**: Provider mínimo seguindo padrão `PzMailServiceProvider`.

**Arquivos afetados**:
- `src/Laravel/PzRequestServiceProvider.php` (novo)

**Implementação**:
```php
<?php

declare(strict_types=1);

namespace Puzl\PzRequest\Laravel;

use Illuminate\Support\ServiceProvider;

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

    protected function configPath(string $file): string
    {
        return function_exists('config_path')
            ? config_path($file)
            : $this->app->basePath('config/' . $file);
    }
}
```

> **Notas**:
> - `runningInConsole()` evita registrar `publishes` em runtime web (otimização de memória).
> - `configPath()` envelopa `config_path()` (helper Laravel) com fallback para `basePath()`, facilitando teste sob `Illuminate\Container\Container` puro (sem `Application`).
> - Constantes `CONFIG_PATH` e `PUBLISH_TAG` públicas facilitam asserções nos testes (sem reflexão).

---

### 3. Validar declaração no `composer.json`
**Descrição**: Confirmar que o `composer.json` (Task 1.0) declara o provider corretamente. Se a Task 1.0 já fez isso, esta subtarefa é apenas verificação.

**Arquivos afetados**:
- `composer.json` (verificação; alterar se faltar)

**Implementação**: garantir que existe:
```json
"extra": {
  "laravel": {
    "providers": ["Puzl\\PzRequest\\Laravel\\PzRequestServiceProvider"]
  }
}
```

---

### 4. Testes Unitários
**Descrição**: Validar `register()` e `boot()` sob `Illuminate\Container\Container`. Como o Service Provider é simples, há poucos casos.

**Arquivos de teste**:
- `tests/Unit/Laravel/PzRequestServiceProviderTest.php`

**Casos de teste obrigatórios**:

1. **`test_constants_match_expected_paths_and_tag`**
   - **Cenário**: `assertSame(__DIR__.'/../../../src/Laravel/../../config/pzrequest.php', PzRequestServiceProvider::CONFIG_PATH)` (resolver via `realpath`).
   - **Expectativa**: `realpath(CONFIG_PATH)` aponta para `config/pzrequest.php` real do pacote. `PUBLISH_TAG === 'pzrequest-config'`.

2. **`test_config_file_returns_array_with_adapter_key`**
   - **Cenário**: `$cfg = require PzRequestServiceProvider::CONFIG_PATH;`
   - **Expectativa**: `is_array($cfg) && array_key_exists('adapter', $cfg)`.

3. **`test_config_default_adapter_is_guzzle_when_env_absent`**
   - **Cenário**: `putenv('PZREQUEST_ADAPTER')` antes; `$cfg = require ...;`.
   - **Expectativa**: `$cfg['adapter'] === 'GUZZLE'` (default da função `env()`).

> Observação: o teste 3 só funciona se houver fallback `env()` disponível. Como o pacote depende de `illuminate/support`, `env()` global está disponível via autoload do Composer no PHPUnit.

---

### 5. Testes de Integração (Service Provider)
**Descrição**: Instanciar o provider sob `Illuminate\Container\Container` puro (sem subir aplicação Laravel completa) e validar `register`/`boot` + paths publicáveis.

**Arquivos de teste**:
- `tests/Features/Laravel/PzRequestServiceProviderTest.php`

**Casos de teste obrigatórios**:

1. **`test_register_merges_config_into_container`**
   - **Fluxo**:
     ```php
     $container = new \Illuminate\Container\Container();
     \Illuminate\Container\Container::setInstance($container);
     $container->instance('config', new \Illuminate\Config\Repository());
     $container->instance('app', $appStub);  // ver fixture
     $provider = new PzRequestServiceProvider($appStub);
     $provider->register();
     ```
   - **Expectativa**: `$container->make('config')->get('pzrequest.adapter')` retorna `'GUZZLE'` (após mergeConfigFrom).

2. **`test_boot_registers_publishes_under_pzrequest_config_tag`**
   - **Fluxo**: Após `boot()` em ambiente "console", inspecionar `ServiceProvider::pathsToPublish(get_class($provider), 'pzrequest-config')`.
   - **Expectativa**: Array contém entrada cuja chave aponta para `realpath(CONFIG_PATH)` e o valor é o destino (`config/pzrequest.php`).

3. **`test_boot_does_nothing_outside_console_context`**
   - **Fluxo**: Provider com `runningInConsole()` retornando `false` (via `$appStub`); chamar `boot()`.
   - **Expectativa**: `ServiceProvider::pathsToPublish(...)` retorna `[]` para a tag `pzrequest-config`.

4. **`test_composer_json_declares_provider_in_laravel_auto_discovery`**
   - **Fluxo**: Decodificar `composer.json` da raiz.
   - **Expectativa**: `data.extra.laravel.providers === ['Puzl\\PzRequest\\Laravel\\PzRequestServiceProvider']`.

5. **`test_factory_resolves_adapter_using_config_after_provider_registers`**
   - **Fluxo**: Após `register()` no `Container`, definir função global `config()` (via wrapper/process isolado) ou popular `Container::make('config')->set('pzrequest.adapter', 'GUZZLE')` e chamar `PzRequestFactory::make()`.
   - **Expectativa**: Instância de `PzRequestGuzzle`. **Comprova integração Factory + Provider**.

> Para o teste 5, usar `#[RunInSeparateProcess]` PHPUnit para não interferir com outros testes que dependem da ausência de `config()`.

**Cobertura mínima**: ≥ 90% em `PzRequestServiceProvider` (classe pequena; meta facilmente atingível).

---

### 6. Fixture de teste (App Stub)
**Descrição**: Stub mínimo do app Laravel para passar ao provider sem subir framework completo.

**Arquivos afetados**:
- `tests/Features/Laravel/Fixtures/AppStub.php`

**Implementação**:
```php
<?php
declare(strict_types=1);
namespace Puzl\PzRequest\Tests\Features\Laravel\Fixtures;

use Illuminate\Container\Container;

final class AppStub extends Container
{
    private bool $console;

    public function __construct(bool $runningInConsole = true)
    {
        $this->console = $runningInConsole;
    }

    public function runningInConsole(): bool { return $this->console; }
    public function basePath(string $path = ''): string { return sys_get_temp_dir() . '/pzrequest-test/' . ltrim($path, '/'); }
}
```

---

## Critérios de Aceitação

- [ ] `config/pzrequest.php` criado com chave `adapter` lendo `PZREQUEST_ADAPTER`
- [ ] `src/Laravel/PzRequestServiceProvider.php` criado, `register()` mescla config, `boot()` registra `publishes` com tag `pzrequest-config`
- [ ] `composer.json` declara o provider em `extra.laravel.providers`
- [ ] `runningInConsole()` evita `publishes` em runtime web
- [ ] Constantes `CONFIG_PATH` e `PUBLISH_TAG` expostas como `public const` para facilitar testes
- [ ] Todos os 8 casos de teste passando (3 unit + 5 integração)
- [ ] Cobertura ≥ 90% em `PzRequestServiceProvider`
- [ ] Tipagem PHP estrita
- [ ] PHPDoc em `register`/`boot` documentando comportamento
- [ ] `composer test` verde
- [ ] Code review aprovado

---

## Entregáveis

**Arquivos Criados**:
- [ ] `config/pzrequest.php`
- [ ] `src/Laravel/PzRequestServiceProvider.php`
- [ ] `tests/Unit/Laravel/PzRequestServiceProviderTest.php`
- [ ] `tests/Features/Laravel/PzRequestServiceProviderTest.php`
- [ ] `tests/Features/Laravel/Fixtures/AppStub.php`

**Arquivos Modificados**:
- [ ] `composer.json` (apenas se Task 1.0 não tiver registrado o provider — se já registrou, sem alteração)
- [ ] Remover `.gitkeep` de `config/`, `src/Laravel/`, `tests/Features/`

**Documentação**:
- [ ] PHPDoc em `register` apontando que apenas mescla config (sem bind no container)
- [ ] PHPDoc em `boot` mencionando `runningInConsole`

---

## Guia de Implementação

### Passo 1: Criar config
Crie `config/pzrequest.php` conforme Subtarefa 1.

### Passo 2: Criar provider
Implemente `src/Laravel/PzRequestServiceProvider.php` conforme Subtarefa 2.

### Passo 3: Verificar `composer.json`
Confirme `extra.laravel.providers`. Se faltar, adicione.

### Passo 4: Implementar testes (Unit + Features)
Comece pelo unit (mais simples), depois pelo features. Use `Illuminate\Container\Container` + `Illuminate\Config\Repository`.

```bash
# composer.json já trouxe illuminate/support; garantir que illuminate/container e illuminate/config estão acessíveis
composer show illuminate/container
composer show illuminate/config
```
> Ambos vêm como dependências transitivas de `illuminate/support`.

### Passo 5: Validar publish
Em ambiente Laravel real (validação manual opcional):
```bash
# em um app Laravel de teste:
composer require puzl/pzrequest
php artisan vendor:publish --tag=pzrequest-config
# config/pzrequest.php deve aparecer no app
```

---

## Validação

### Checklist de Validação Manual:
- [ ] `register()` chamado mescla config (validar via `Container::make('config')`)
- [ ] `boot()` em modo console adiciona entrada em `pathsToPublish` com tag correta
- [ ] `boot()` fora do console não adiciona entrada
- [ ] Auto-discovery: `composer.json` na chave correta
- [ ] (Opcional) Teste manual em app Laravel real: `vendor:publish` cria `config/pzrequest.php`

### Comandos de Validação:
```bash
composer test
composer test:coverage
vendor/bin/phpunit --testsuite unit --filter PzRequestServiceProvider
vendor/bin/phpunit --testsuite features --filter PzRequestServiceProvider
```

---

## Riscos e Mitigações

| Risco | Impacto | Probabilidade | Mitigação |
|-------|---------|---------------|-----------|
| Path do `mergeConfigFrom` errado quebrar config no consumidor | Alto | Baixa | Constante `CONFIG_PATH` testada via `realpath` (caso 1) |
| Tag `pzrequest-config` divergir entre código e docs | Médio | Baixa | Constante `PUBLISH_TAG` única referência; teste valida |
| Auto-discovery falhar por typo no namespace | Alto | Baixa | Caso de teste 4 valida string exata em `composer.json` |
| Provider depender de método não disponível em `Illuminate\Container\Container` puro | Médio | Média | `configPath()` faz fallback para `basePath()`; `AppStub` controla `runningInConsole` |

---

## Notas Adicionais

- O provider **não** registra `PzRequestInterface` como singleton no container. Consumidores que quiserem injeção via construtor podem adicionar no próprio `AppServiceProvider`:
  ```php
  $this->app->bind(PzRequestInterface::class, fn() => PzRequestFactory::make());
  ```
  Isso é deliberado — manter a factory como ponto de entrada único e não criar mágica adicional.
- A combinação Task 5.0 + Task 6.0 forma o "fluxo Laravel completo": config define adapter → provider mescla config → factory lê via `config('pzrequest.adapter')` → consumidor recebe adapter correto.
- `PzMail` e `PzPdf` seguem este mesmo padrão — manter compatibilidade visual ajuda o time a aprender uma vez e usar em todos.

---

## Histórico

| Data | Autor | Mudança |
|------|-------|---------|
| 2026-04-24 | Time Plataforma Puzl | Criação da tarefa |
