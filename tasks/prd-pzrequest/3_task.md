# Task 3.0: Base Abstrata Fluente (`PzRequestBase`)

## Informações Gerais

**Status**: 📦 Pronta para Iniciar
**Prioridade**: Alta
**Dependências**: Task 2.0
**Assignee**: Não atribuído

---

## Objetivo

Implementar `PzRequestBase`, classe abstrata que concentra todo o **estado fluente** reutilizável por adapters concretos: endpoint, headers, queries, params, body, multiparts, autenticações (`withBearer`, `withBasicAuth`), timeouts (default 30s), flag `asFormUrlEncoded`, helper `getBodyAsString` e o método `prepare()` que reseta o estado. Esta tarefa entrega uma classe utilizável (mesmo sem `execute()`, que vem na Task 4.0) e cobre os requisitos RF-01, RF-04, RF-05, RF-07 e RN-02 do PRD.

---

## Contexto

`PzRequestBase` é a "engrenagem" do padrão Adapter desta biblioteca. Ela implementa parcialmente `PzRequestInterface` (Task 2.0): todos os setters/getters e `prepare()`. Os métodos `execute`, `get`, `post`, `put`, `patch`, `delete`, `head`, `options` ficam como `abstract` (implementados pelo `PzRequestGuzzle` na Task 4.0). A base **não conhece Guzzle** — é puro estado.

A base deve detectar quando o consumidor passa o header `Content-Type: application/x-www-form-urlencoded` e ativar `asFormUrlEncoded` automaticamente (RF-07). Os timeouts seguem default 30s (RF-05).

**Referências**:
- PRD: `tasks/prd-pzrequest/prd.md` - RF-01, RF-04, RF-05, RF-07, RN-02, RN-04
- Tech Spec: `tasks/prd-pzrequest/techspec.md` - Seções 2.2 (passos 3 e 7), 7.2, 8.2 (`PzRequestBaseTest`), 11.1 (Fase 3)

---

## Escopo

### O que ESTÁ no escopo:
- ✅ `src/Adapter/PzRequestBase.php` (classe `abstract`, implementa `PzRequestInterface`)
- ✅ Propriedades protegidas: `$endpoint`, `$params`, `$queries`, `$headers`, `$body`, `$multiparts`, `$basicAuth`, `$asFormUrlEncoded`, `$connectTimeout`, `$readTimeout`, `$timeout`
- ✅ Implementação de **todos os setters fluentes** retornando `$this`
- ✅ Implementação de **todos os getters de timeout** + `getAsFormUrlEncoded()` + `getBodyAsString()`
- ✅ Implementação de `withBearer()` e `withBasicAuth()`
- ✅ Implementação de `prepare()` que reseta endpoint, params, queries, multiparts, body e flag form-urlencoded (não reseta timeouts nem auth — paridade com original)
- ✅ Métodos HTTP (`execute`, `get`, ...) declarados como `abstract`
- ✅ Detecção automática de form-urlencoded via header `Content-Type`
- ✅ Helper protegido para subclasses lerem o estado (`getCurConfig()`/`getCurOptions()` ou propriedades visíveis)
- ✅ Testes unitários cobrindo todos os comportamentos

### O que NÃO está no escopo:
- ❌ Implementação concreta de `execute()` e atalhos HTTP (Task 4.0)
- ❌ Integração com Guzzle (Task 4.0)
- ❌ Factory (Task 5.0)

---

## Subtarefas

### 1. Implementar `PzRequestBase`
**Descrição**: Classe abstrata com todo o estado fluente.

**Arquivos afetados**:
- `src/Adapter/PzRequestBase.php` (novo)

**Implementação** (esqueleto — preencher conforme `App\Library\RestRequest\Adapter\RestRequestBase` original com renomeações):
```php
<?php

declare(strict_types=1);

namespace Puzl\PzRequest\Adapter;

use Puzl\PzRequest\Contract\PzRequestInterface;
use Puzl\PzRequest\Enum\PzRequestMethodEnum;
use Puzl\PzRequest\PzResponse;

abstract class PzRequestBase implements PzRequestInterface
{
    protected const DEFAULT_TIMEOUT_SECONDS = 30;

    protected string $endpoint = '';
    protected array $params = [];
    protected array $queries = [];
    protected array $headers = [];
    protected array|string $body = [];
    protected array $multiparts = [];
    protected ?array $basicAuth = null;
    protected bool $asFormUrlEncoded = false;
    protected int $connectTimeout = self::DEFAULT_TIMEOUT_SECONDS;
    protected int $readTimeout = self::DEFAULT_TIMEOUT_SECONDS;
    protected int $timeout = self::DEFAULT_TIMEOUT_SECONDS;

    public function endpoint(string $value): self { $this->endpoint = $value; return $this; }

    public function addHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        if (strcasecmp($name, 'Content-Type') === 0
            && stripos($value, 'application/x-www-form-urlencoded') !== false) {
            $this->asFormUrlEncoded = true;
        }
        return $this;
    }

    public function addHeaders(array $keyValue): self
    {
        foreach ($keyValue as $name => $value) {
            $this->addHeader((string) $name, (string) $value);
        }
        return $this;
    }

    public function addQuery(string $name, mixed $value): self { $this->queries[$name] = $value; return $this; }
    public function addQueries(array $keyValue): self { foreach ($keyValue as $k => $v) { $this->addQuery((string) $k, $v); } return $this; }
    public function addParam(string $name, string $value): self { $this->params[$name] = $value; return $this; }
    public function addBody(array|string $value): self { $this->body = $value; return $this; }

    public function addMultipart(string $name, string $contents, ?string $filename = null): self
    {
        $part = ['name' => $name, 'contents' => $contents];
        if ($filename !== null) { $part['filename'] = $filename; }
        $this->multiparts[] = $part;
        return $this;
    }

    public function addMultiparts(array $multiparts): self
    {
        foreach ($multiparts as $m) {
            $this->addMultipart((string) $m['name'], (string) $m['contents'], $m['filename'] ?? null);
        }
        return $this;
    }

    public function asFormUrlEncoded(bool $value = true): self { $this->asFormUrlEncoded = $value; return $this; }

    public function withBearer(string $token): self { $this->headers['Authorization'] = 'Bearer ' . $token; return $this; }
    public function withBasicAuth(string $username, string $password): self { $this->basicAuth = [$username, $password]; return $this; }

    public function setConnectTimeout(int $seconds): self { $this->connectTimeout = $seconds; return $this; }
    public function setReadTimeout(int $seconds): self { $this->readTimeout = $seconds; return $this; }
    public function setTimeout(int $seconds): self { $this->timeout = $seconds; return $this; }
    public function setTimeouts(int $seconds): self
    {
        $this->connectTimeout = $seconds;
        $this->readTimeout = $seconds;
        $this->timeout = $seconds;
        return $this;
    }

    public function getConnectTimeout(): int { return $this->connectTimeout; }
    public function getReadTimeout(): int { return $this->readTimeout; }
    public function getTimeout(): int { return $this->timeout; }

    public function getAsFormUrlEncoded(): bool { return $this->asFormUrlEncoded; }

    public function getBodyAsString(): string
    {
        if (is_string($this->body)) { return $this->body; }
        return $this->asFormUrlEncoded
            ? http_build_query($this->body)
            : (json_encode($this->body) ?: '');
    }

    public function prepare(): self
    {
        $this->endpoint = '';
        $this->params = [];
        $this->queries = [];
        $this->multiparts = [];
        $this->body = [];
        $this->asFormUrlEncoded = false;
        return $this;
    }

    abstract public function execute(PzRequestMethodEnum $httpMethod = PzRequestMethodEnum::GET): PzResponse;
    abstract public function get(): PzResponse;
    abstract public function post(): PzResponse;
    abstract public function put(): PzResponse;
    abstract public function patch(): PzResponse;
    abstract public function delete(): PzResponse;
    abstract public function head(): PzResponse;
    abstract public function options(): PzResponse;
}
```

> **Decisões**:
> - `prepare()` **não** reseta headers nem auth nem timeouts — paridade com `RestRequestBase` original (consumidor pode reusar credenciais entre chamadas).
> - `addHeader('Content-Type', '... form-urlencoded ...')` ativa flag (RF-07).
> - `getBodyAsString()` evita `json_encode` quando body já é string (§7.2 Tech Spec).

---

### 2. Testes Unitários
**Descrição**: Cobrir 100% dos setters/getters via subclasse de teste concreta (já que `PzRequestBase` é abstrata).

**Arquivos de teste**:
- `tests/Unit/Adapter/PzRequestBaseTest.php`
- `tests/Unit/Adapter/Fixtures/PzRequestBaseStub.php` (subclasse mínima que implementa abstratos com `throw new \LogicException`)

**Casos de teste obrigatórios**:

1. **`test_default_timeouts_are_thirty_seconds`**
   - **Cenário**: Instanciar stub.
   - **Expectativa**: `getConnectTimeout() === 30 && getReadTimeout() === 30 && getTimeout() === 30`.

2. **`test_endpoint_setter_returns_self_and_stores_value`**
   - **Cenário**: `$r = $stub->endpoint('https://api.test/x'); assertSame($stub, $r)`.
   - **Expectativa**: Mesmo objeto + leitura via reflexão confirma `endpoint === 'https://api.test/x'`.

3. **`test_add_header_returns_self_and_stores_pair`**
   - **Cenário**: `$stub->addHeader('X-Trace', 'abc')`.
   - **Expectativa**: Header armazenado.

4. **`test_add_headers_iterates_array`**
   - **Cenário**: `$stub->addHeaders(['A' => '1', 'B' => '2'])`.
   - **Expectativa**: Ambos armazenados.

5. **`test_add_header_content_type_form_urlencoded_activates_flag`**
   - **Cenário**: `$stub->addHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8')`.
   - **Expectativa**: `getAsFormUrlEncoded() === true`.

6. **`test_add_header_content_type_form_urlencoded_is_case_insensitive`**
   - **Cenário**: `$stub->addHeader('content-type', 'APPLICATION/X-WWW-FORM-URLENCODED')`.
   - **Expectativa**: `getAsFormUrlEncoded() === true` (testar `strcasecmp`/`stripos`).

7. **`test_add_header_content_type_json_does_not_activate_form_flag`**
   - **Cenário**: `$stub->addHeader('Content-Type', 'application/json')`.
   - **Expectativa**: `getAsFormUrlEncoded() === false`.

8. **`test_add_query_accepts_mixed_value`**
   - **Cenário**: `$stub->addQuery('ids', [1, 2, 3])`.
   - **Expectativa**: Query armazenada como array.

9. **`test_add_queries_iterates_array`**
   - **Cenário**: `$stub->addQueries(['a' => 1, 'b' => 'x'])`.
   - **Expectativa**: Ambas armazenadas.

10. **`test_add_param_returns_self_and_stores_value`**
    - **Cenário**: `$stub->addParam('id', '42')`.
    - **Expectativa**: Param armazenado.

11. **`test_add_body_accepts_array`** e **`test_add_body_accepts_string`**
    - **Cenário**: Setar com array e com string.
    - **Expectativa**: Tipo preservado.

12. **`test_add_multipart_stores_part_with_optional_filename`**
    - **Cenário**: `$stub->addMultipart('file', 'binary', 'doc.pdf')`.
    - **Expectativa**: Multiparts contém `['name' => 'file', 'contents' => 'binary', 'filename' => 'doc.pdf']`.

13. **`test_add_multipart_without_filename_omits_key`**
    - **Cenário**: `$stub->addMultipart('field', 'value')`.
    - **Expectativa**: Item sem chave `filename`.

14. **`test_add_multiparts_iterates_array`**
    - **Cenário**: `$stub->addMultiparts([['name' => 'a', 'contents' => '1'], ['name' => 'b', 'contents' => '2', 'filename' => 'b.txt']])`.
    - **Expectativa**: Ambos itens armazenados corretamente.

15. **`test_as_form_url_encoded_setter_with_default_true`**
    - **Cenário**: `$stub->asFormUrlEncoded()`.
    - **Expectativa**: `getAsFormUrlEncoded() === true`.

16. **`test_as_form_url_encoded_setter_with_explicit_false`**
    - **Cenário**: `$stub->asFormUrlEncoded(false)`.
    - **Expectativa**: `getAsFormUrlEncoded() === false`.

17. **`test_with_bearer_sets_authorization_header`**
    - **Cenário**: `$stub->withBearer('abc.def.ghi')`.
    - **Expectativa**: Header `Authorization === 'Bearer abc.def.ghi'`.

18. **`test_with_basic_auth_stores_credentials_array`**
    - **Cenário**: `$stub->withBasicAuth('user', 'secret')`.
    - **Expectativa**: Propriedade `basicAuth === ['user', 'secret']` (via reflexão).

19. **`test_with_basic_auth_does_not_add_credentials_to_queries`** (RN-04)
    - **Cenário**: `$stub->withBasicAuth('user', 'secret')`.
    - **Expectativa**: Propriedade `queries === []` (verifica via reflexão).

20. **`test_set_timeouts_applies_same_value_to_all_three`**
    - **Cenário**: `$stub->setTimeouts(45)`.
    - **Expectativa**: `getConnectTimeout() === 45 && getReadTimeout() === 45 && getTimeout() === 45`.

21. **`test_individual_timeout_setters_are_independent`**
    - **Cenário**: `$stub->setConnectTimeout(5)->setReadTimeout(10)->setTimeout(15)`.
    - **Expectativa**: Cada getter retorna seu próprio valor.

22. **`test_get_body_as_string_returns_string_body_unchanged`**
    - **Cenário**: `$stub->addBody('raw=payload')`.
    - **Expectativa**: `getBodyAsString() === 'raw=payload'`.

23. **`test_get_body_as_string_encodes_array_as_json_by_default`**
    - **Cenário**: `$stub->addBody(['a' => 1])`.
    - **Expectativa**: `getBodyAsString() === '{"a":1}'`.

24. **`test_get_body_as_string_encodes_array_as_form_when_flag_active`**
    - **Cenário**: `$stub->addBody(['a' => 1, 'b' => 'x'])->asFormUrlEncoded()`.
    - **Expectativa**: `getBodyAsString() === 'a=1&b=x'`.

25. **`test_prepare_resets_endpoint_params_queries_multiparts_body_and_form_flag`**
    - **Cenário**: Setar tudo, então chamar `prepare()`.
    - **Expectativa**: Via reflexão, todas essas propriedades voltam aos valores iniciais (`''` ou `[]` ou `false`).

26. **`test_prepare_does_not_reset_timeouts_or_basic_auth_or_headers`**
    - **Cenário**: Setar timeouts, basic auth, headers, então `prepare()`.
    - **Expectativa**: Esses três permanecem (paridade com original).

27. **`test_prepare_returns_self_for_chaining`**
    - **Cenário**: `$r = $stub->prepare()`.
    - **Expectativa**: `$r === $stub`.

28. **`test_all_setters_are_chainable`** (smoke)
    - **Cenário**: Encadear todos os setters em uma única expressão.
    - **Expectativa**: Resultado final é instância de `PzRequestBase`.

**Cobertura mínima**: ≥ 95% de `PzRequestBase` (somente métodos abstratos ficam fora).

---

### 3. Testes de Integração
**Descrição**: Validar que a base se compõe corretamente com o contrato (`PzRequestInterface`) — ainda sem rede.

**Arquivos de teste**:
- Cobertura está dentro do `PzRequestBaseTest` (não há outro componente para integrar nesta tarefa).

**Cenários de teste obrigatórios**:
1. **`test_base_implements_pz_request_interface`**
   - **Fluxo**: `assertInstanceOf(PzRequestInterface::class, $stub)`.
   - **Expectativa**: Verdadeiro.

2. **`composer test` verde**
   - **Fluxo**: Rodar suíte completa.
   - **Expectativa**: Exit code `0`, todos os 28 casos novos + os anteriores passando.

---

## Critérios de Aceitação

- [ ] `src/Adapter/PzRequestBase.php` criado e abstrato
- [ ] Implementa todos os métodos não-abstratos do `PzRequestInterface`
- [ ] Propriedades protegidas com tipos estritos
- [ ] `prepare()` reseta apenas o que está documentado (RN-02)
- [ ] Header `Content-Type: application/x-www-form-urlencoded` ativa flag automaticamente
- [ ] Defaults de timeout = 30s
- [ ] `getBodyAsString()` evita `json_encode` quando body é string
- [ ] Todos os 28 casos de teste passando
- [ ] Cobertura ≥ 95% em `PzRequestBase`
- [ ] Tipagem PHP estrita, sem `mixed` exceto onde o contrato exige (`addQuery`)
- [ ] PHPDoc em métodos não-óbvios (ex.: detecção de header form-urlencoded)
- [ ] `composer test` verde
- [ ] Code review aprovado

---

## Entregáveis

**Arquivos Criados**:
- [ ] `src/Adapter/PzRequestBase.php`
- [ ] `tests/Unit/Adapter/PzRequestBaseTest.php`
- [ ] `tests/Unit/Adapter/Fixtures/PzRequestBaseStub.php`

**Arquivos Modificados**:
- [ ] Remover `.gitkeep` de `src/Adapter/` se ainda existir

**Documentação**:
- [ ] PHPDoc em `addHeader` explicando o gatilho de form-urlencoded
- [ ] PHPDoc em `prepare` listando o que NÃO é resetado e justificando

---

## Guia de Implementação

### Passo 1: Criar a classe base
Implemente `PzRequestBase` exatamente como na Subtarefa 1. Use o `App\Library\RestRequest\Adapter\RestRequestBase` como referência de comportamento, renomeando classes (`Rest*` → `Pz*`) e ajustando tipos (RF-01..07).

### Passo 2: Criar stub para testes
Crie `tests/Unit/Adapter/Fixtures/PzRequestBaseStub.php`:
```php
<?php
declare(strict_types=1);
namespace Puzl\PzRequest\Tests\Unit\Adapter\Fixtures;

use Puzl\PzRequest\Adapter\PzRequestBase;
use Puzl\PzRequest\Enum\PzRequestMethodEnum;
use Puzl\PzRequest\PzResponse;

final class PzRequestBaseStub extends PzRequestBase
{
    public function execute(PzRequestMethodEnum $httpMethod = PzRequestMethodEnum::GET): PzResponse
    { throw new \LogicException('not under test'); }
    public function get(): PzResponse { throw new \LogicException('not under test'); }
    public function post(): PzResponse { throw new \LogicException('not under test'); }
    public function put(): PzResponse { throw new \LogicException('not under test'); }
    public function patch(): PzResponse { throw new \LogicException('not under test'); }
    public function delete(): PzResponse { throw new \LogicException('not under test'); }
    public function head(): PzResponse { throw new \LogicException('not under test'); }
    public function options(): PzResponse { throw new \LogicException('not under test'); }
}
```

### Passo 3: Implementar testes
Implemente cada um dos 28 casos. Use `ReflectionClass`/`ReflectionProperty` para inspecionar propriedades protegidas.

### Passo 4: Validar
```bash
composer test:coverage
vendor/bin/phpunit --testsuite unit --filter PzRequestBaseTest
```

---

## Validação

### Checklist de Validação Manual:
- [ ] Cobertura de `PzRequestBase` ≥ 95% (relatório HTML em `coverage/`)
- [ ] Nenhum teste depende de Guzzle ou de rede
- [ ] Stub não vaza para namespace de produção (fica em `tests/Unit/Adapter/Fixtures`)

### Comandos de Validação:
```bash
composer test
composer test:coverage
vendor/bin/phpunit --testsuite unit --filter PzRequestBaseTest
```

---

## Riscos e Mitigações

| Risco | Impacto | Probabilidade | Mitigação |
|-------|---------|---------------|-----------|
| Esquecer de manter paridade no `prepare()` (resetar headers/timeouts inadvertidamente) | Alto | Média | Casos de teste 25 e 26 atestam o que é/não é resetado |
| `addHeader` não detectar variações de capitalização do `Content-Type` | Médio | Média | Caso de teste 6 valida case-insensitivity |
| Vazamento de credenciais para queries por bug futuro | Alto | Baixa | Caso de teste 19 (RN-04) — falha imediata se alguém adicionar `withBasicAuth` em queries |
| `getBodyAsString()` quebrar com body inválido para JSON | Médio | Baixa | Fallback `?: ''` no `json_encode` + casos de teste 23 e 24 |

---

## Notas Adicionais

- Esta tarefa **não importa Guzzle** — a base é puramente estado.
- Ao reusar uma instância via DI singleton (Laravel), `prepare()` chamado pelo `execute()` (Task 4.0) garante que o estado da chamada anterior não vaze.
- A política de "headers persistirem após `prepare`" é deliberada: permite `withBearer` configurado uma vez e reutilizado em N chamadas (caso de uso comum). Documentar no README (Task 7.0).
- Se for descoberto algum método do contrato faltando aqui, o caso de teste 14 da Task 2.0 já falharia — então essa tarefa só pode passar se a anterior estiver verde.

---

## Histórico

| Data | Autor | Mudança |
|------|-------|---------|
| 2026-04-24 | Time Plataforma Puzl | Criação da tarefa |
