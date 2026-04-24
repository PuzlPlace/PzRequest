# Task 5.0: Factory (`PzRequestFactory`)

## Informações Gerais

**Status**: 📦 Pronta para Iniciar
**Prioridade**: Alta
**Dependências**: Task 4.0
**Assignee**: Não atribuído

---

## Objetivo

Implementar `PzRequestFactory::make()` — ponto de entrada único para consumidores. Resolve o adapter HTTP em três níveis: (1) argumento explícito do tipo `PzRequestEnum`, (2) configuração `pzrequest.adapter` (Laravel `config()`), (3) variável de ambiente `PZREQUEST_ADAPTER` (via `getenv`). Quando o adapter solicitado não tem implementação concreta (ex.: `SYMFONY_HTTP_CLIENT`, `OTHERS`), faz fallback para `PzRequestGuzzle`. Esta tarefa entrega o consumo idiomático do pacote (`PzRequestFactory::make()->endpoint(...)->get()`).

---

## Contexto

A factory espelha o padrão de `PzMailFactory` (Tech Spec §13.2). Tem que funcionar **com e sem Laravel**: dentro do framework, lê `config('pzrequest.adapter')`; fora, lê `getenv('PZREQUEST_ADAPTER')`. Em ambos os casos, o valor é resolvido como nome do enum via `PzRequestEnum::tryFromName()` (Task 2.0). Adapters não implementados nesta versão (`SYMFONY_HTTP_CLIENT`, `OTHERS`) **não devem lançar exceção** — caem para Guzzle (RF-08).

A presença/ausência da função `config()` é detectada via `function_exists('config')` para evitar dependência rígida do Laravel em testes unitários.

**Referências**:
- PRD: `tasks/prd-pzrequest/prd.md` - RF-08
- Tech Spec: `tasks/prd-pzrequest/techspec.md` - Seções 2.2 (passo 1), 8.2 (`PzRequestFactoryTest`), 11.1 (Fase 4), 13.2

---

## Escopo

### O que ESTÁ no escopo:
- ✅ `src/Factory/PzRequestFactory.php` (classe `final` com método estático `make`)
- ✅ Resolução por argumento explícito (`PzRequestEnum`)
- ✅ Resolução por `config('pzrequest.adapter')` quando disponível
- ✅ Resolução por `getenv('PZREQUEST_ADAPTER')` como fallback
- ✅ Default Guzzle quando nenhum mecanismo retorna valor
- ✅ Fallback para Guzzle quando adapter pedido não está implementado
- ✅ Método helper privado `resolveAdapterFromConfig` espelhando `PzMailFactory`
- ✅ Testes unitários cobrindo todos os branches

### O que NÃO está no escopo:
- ❌ Implementação dos adapters `SYMFONY_HTTP_CLIENT` ou `OTHERS` (fora do PRD §10)
- ❌ Service Provider Laravel (Task 6.0)
- ❌ Cache de instâncias da factory (não solicitado)

---

## Subtarefas

### 1. Implementar `PzRequestFactory`
**Descrição**: Classe `final` com método estático `make`.

**Arquivos afetados**:
- `src/Factory/PzRequestFactory.php` (novo)

**Implementação**:
```php
<?php

declare(strict_types=1);

namespace Puzl\PzRequest\Factory;

use Puzl\PzRequest\Adapter\Guzzle\PzRequestGuzzle;
use Puzl\PzRequest\Contract\PzRequestInterface;

final class PzRequestFactory
{
    public static function make(?PzRequestEnum $adapter = null): PzRequestInterface
    {
        $resolved = $adapter ?? self::resolveAdapterFromConfig() ?? PzRequestEnum::GUZZLE;

        return match ($resolved) {
            PzRequestEnum::GUZZLE => new PzRequestGuzzle(),
            default               => new PzRequestGuzzle(),
        };
    }

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
        if (!is_string($value) || $value === '') {
            return null;
        }
        return PzRequestEnum::tryFromName($value);
    }
}
```

> **Decisões**:
> - `match` com `default => Guzzle` cobre `SYMFONY_HTTP_CLIENT` e `OTHERS` (RF-08 exige fallback).
> - `config()` é função global do Laravel; `function_exists` evita carregar o framework em testes.
> - Ordem de prioridade: argumento → `config()` → env → default Guzzle.

---

### 2. Testes Unitários
**Descrição**: Cobrir todos os caminhos de resolução. Como `function_exists('config')` é checada, criar uma fixture com função `config()` global condicional.

**Arquivos de teste**:
- `tests/Unit/Factory/PzRequestFactoryTest.php`
- `tests/Unit/Factory/Fixtures/config_function.php` (helper opcional definindo `config()` para o teste — incluído por `require_once` no caso correspondente)

**Casos de teste obrigatórios**:

1. **`test_make_without_argument_returns_guzzle_adapter_by_default`**
   - **Cenário**: Sem env, sem `config()`. Chamar `PzRequestFactory::make()`.
   - **Expectativa**: Instância de `PzRequestGuzzle`. Garantir que `PZREQUEST_ADAPTER` não está setado (`putenv('PZREQUEST_ADAPTER')` antes do assert).

2. **`test_make_with_explicit_guzzle_returns_guzzle_adapter`**
   - **Cenário**: `PzRequestFactory::make(PzRequestEnum::GUZZLE)`.
   - **Expectativa**: Instância de `PzRequestGuzzle`.

3. **`test_make_with_symfony_http_client_falls_back_to_guzzle`**
   - **Cenário**: `PzRequestFactory::make(PzRequestEnum::SYMFONY_HTTP_CLIENT)`.
   - **Expectativa**: Instância de `PzRequestGuzzle` (fallback RF-08).

4. **`test_make_with_others_falls_back_to_guzzle`**
   - **Cenário**: `PzRequestFactory::make(PzRequestEnum::OTHERS)`.
   - **Expectativa**: Instância de `PzRequestGuzzle`.

5. **`test_make_returns_pz_request_interface`**
   - **Cenário**: `PzRequestFactory::make()`.
   - **Expectativa**: `assertInstanceOf(PzRequestInterface::class, $instance)`.

6. **`test_make_reads_env_variable_when_argument_absent`**
   - **Cenário**: `putenv('PZREQUEST_ADAPTER=GUZZLE')` antes; chamar `make()` sem argumento.
   - **Expectativa**: Instância de `PzRequestGuzzle`. **`tearDown`** limpa env.

7. **`test_make_falls_back_to_guzzle_when_env_holds_unknown_name`**
   - **Cenário**: `putenv('PZREQUEST_ADAPTER=NONEXISTENT')`.
   - **Expectativa**: Instância de `PzRequestGuzzle` (porque `tryFromName` retorna `null`).

8. **`test_make_falls_back_to_guzzle_when_env_holds_empty_string`**
   - **Cenário**: `putenv('PZREQUEST_ADAPTER=')`.
   - **Expectativa**: Instância de `PzRequestGuzzle`.

9. **`test_make_explicit_argument_overrides_env_variable`**
   - **Cenário**: `putenv('PZREQUEST_ADAPTER=SYMFONY_HTTP_CLIENT')` + `make(PzRequestEnum::GUZZLE)`.
   - **Expectativa**: Instância de `PzRequestGuzzle` (argumento manda; mesmo que ambos resolveriam para Guzzle, o teste prova que **a ordem de prioridade é argumento → env**).

10. **`test_make_uses_config_value_when_function_exists`**
    - **Cenário**: Definir função global `config()` retornando `'GUZZLE'` para `'pzrequest.adapter'`. Importar via `require_once Fixtures/config_function.php`. Chamar `make()`.
    - **Expectativa**: Instância de `PzRequestGuzzle`.

11. **`test_make_falls_back_from_config_to_env_when_config_returns_null`**
    - **Cenário**: `config()` retorna `null`; env tem `GUZZLE`.
    - **Expectativa**: Instância de `PzRequestGuzzle` (lê env como segunda prioridade).

12. **`test_make_returns_a_fresh_instance_per_call`**
    - **Cenário**: `$a = make(); $b = make();`.
    - **Expectativa**: `$a !== $b` (sem singleton interno — quem precisar singleton usa o container).

**Cobertura mínima**: ≥ 95% em `PzRequestFactory`.

---

### 3. Testes de Integração
**Descrição**: Validar fluxo end-to-end (sem rede): `make()` → adapter → execute mockado → `PzResponse`.

**Arquivos de teste**:
- Cenário 3 abaixo é incluído como caso adicional em `PzRequestFactoryTest.php`.

**Cenários de teste obrigatórios**:

1. **`test_factory_creates_adapter_compatible_with_pz_request_base_state`**
   - **Fluxo**: `$pz = PzRequestFactory::make(); $pz->endpoint('https://x')->addHeader('A','1'); $r = (new \ReflectionProperty($pz, 'endpoint'))->getValue($pz);`
   - **Expectativa**: `endpoint === 'https://x'`. Comprova que a factory devolve uma instância plenamente funcional para fluência.

2. **`test_factory_output_implements_full_contract`**
   - **Fluxo**: Usar `Reflection` para verificar que a instância retornada implementa todos os 30 métodos do `PzRequestInterface`.
   - **Expectativa**: Verdadeiro.

3. **Suíte completa verde**
   - **Fluxo**: `composer test`.
   - **Expectativa**: Exit code `0`.

---

## Critérios de Aceitação

- [ ] `src/Factory/PzRequestFactory.php` criado, `final`, com método estático `make`
- [ ] Resolução em 3 níveis: argumento → `config()` → `getenv()` → default Guzzle
- [ ] Fallback para Guzzle quando adapter pedido não tem implementação
- [ ] `function_exists('config')` evita dependência rígida do Laravel
- [ ] Todos os 12 casos de teste passando
- [ ] `tearDown` limpa `PZREQUEST_ADAPTER` para evitar test pollution
- [ ] Cobertura ≥ 95% em `PzRequestFactory`
- [ ] Tipagem PHP estrita
- [ ] PHPDoc em `make()` listando ordem de prioridade da resolução
- [ ] `composer test` verde
- [ ] Code review aprovado

---

## Entregáveis

**Arquivos Criados**:
- [ ] `src/Factory/PzRequestFactory.php`
- [ ] `tests/Unit/Factory/PzRequestFactoryTest.php`
- [ ] `tests/Unit/Factory/Fixtures/config_function.php` (helper para teste 10)

**Arquivos Modificados**:
- Nenhum.

**Documentação**:
- [ ] PHPDoc em `make()` documentando cada nível de resolução
- [ ] PHPDoc em `resolveAdapterFromConfig()` explicando fallback `config → env → null`

---

## Guia de Implementação

### Passo 1: Implementar a factory
Crie `src/Factory/PzRequestFactory.php` seguindo a Subtarefa 1.

### Passo 2: Criar fixture `config()` para o teste 10
```php
<?php
// tests/Unit/Factory/Fixtures/config_function.php
if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        $map = ['pzrequest.adapter' => 'GUZZLE'];
        return $map[$key] ?? $default;
    }
}
```
> **Importante**: Por ser função global, `require_once` desse arquivo afeta todos os testes subsequentes do processo PHPUnit. Para isolar, recomenda-se rodar esse caso via teste em processo isolado (`@runInSeparateProcess`/`#[RunInSeparateProcess]` no PHPUnit 10) **OU** mover essa validação para o `PzRequestServiceProviderTest` (Task 6.0), onde o Laravel naturalmente provê `config()`.
> **Decisão recomendada**: usar `#[RunInSeparateProcess]` para o teste 10 e o teste 11.

### Passo 3: Implementar testes
Cuidado especial com `setUp/tearDown` para limpar `PZREQUEST_ADAPTER`:
```php
protected function setUp(): void
{
    parent::setUp();
    putenv('PZREQUEST_ADAPTER');
}
protected function tearDown(): void
{
    putenv('PZREQUEST_ADAPTER');
    parent::tearDown();
}
```

### Passo 4: Validar cobertura
```bash
composer test:coverage
vendor/bin/phpunit --testsuite unit --filter PzRequestFactory
```

---

## Validação

### Checklist de Validação Manual:
- [ ] Cobertura ≥ 95% em `PzRequestFactory`
- [ ] Inspeção visual: `make()` é o único método público
- [ ] Garantir que testes 10 e 11 rodam em processo isolado (não poluem outros)
- [ ] Confirmar via teste 9 que ordem de prioridade é argumento > env

### Comandos de Validação:
```bash
composer test
composer test:coverage
vendor/bin/phpunit --testsuite unit --filter PzRequestFactory
```

---

## Riscos e Mitigações

| Risco | Impacto | Probabilidade | Mitigação |
|-------|---------|---------------|-----------|
| Definir `config()` global em um teste poluir os outros | Médio | Alta | Usar `#[RunInSeparateProcess]` nos testes 10 e 11 |
| `PZREQUEST_ADAPTER` ficar setada entre testes | Médio | Média | `setUp`/`tearDown` chamam `putenv('PZREQUEST_ADAPTER')` |
| Fallback silencioso esconder typo do consumidor | Baixo | Média | Documentar no README (Task 7.0): nome inválido = silencioso → Guzzle |
| Adicionar futuramente `SYMFONY_HTTP_CLIENT` quebrar API | Baixo | Baixa | `make()` continua retornando `PzRequestInterface`; mudança é additiva |

---

## Notas Adicionais

- A factory **não usa singleton interno** — cada chamada devolve nova instância. Quem quiser cachear deve usar o container Laravel (Task 6.0 não registra como singleton automaticamente; consumidor decide).
- A função `config()` referenciada é a do Laravel (alias para `Container::make('config')->get(...)`); fora do Laravel, ela simplesmente não existe e a factory cai para `getenv`.
- `tryFromName` (Task 2.0) é case-sensitive — `'guzzle'` retorna `null`. Documentar no README.

---

## Histórico

| Data | Autor | Mudança |
|------|-------|---------|
| 2026-04-24 | Time Plataforma Puzl | Criação da tarefa |
