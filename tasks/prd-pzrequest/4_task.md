# Task 4.0: Adapter Guzzle (`PzRequestGuzzle`)

## Informações Gerais

**Status**: 📦 Pronta para Iniciar
**Prioridade**: Alta
**Dependências**: Task 3.0
**Assignee**: Não atribuído

---

## Objetivo

Implementar o adapter concreto `PzRequestGuzzle` que estende `PzRequestBase` (Task 3.0) e executa requisições HTTP reais usando `GuzzleHttp\Client`. O adapter monta `$config` (base_uri, headers, query) e `$options` (timeouts, json/form_params/body/multipart, auth), executa a requisição, mapeia erros de transporte para `PzResponse` (504/500/status real) e garante que `prepare()` seja chamado em `finally`. Implementa todos os atalhos `get`/`post`/`put`/`patch`/`delete`/`head`/`options`. Esta é a tarefa mais crítica do pacote: ela entrega o cliente HTTP funcional e a paridade comportamental com o `RestRequest` original.

---

## Contexto

`PzRequestGuzzle` é o coração do pacote. Aqui as decisões mais sensíveis da Tech Spec entram em jogo:

1. **Não lançar exceções** para o consumidor (RF-06/RN-03): erros viram `PzResponse` com status semântico.
2. **Captura de `BadResponseException`** (e não só `ClientException`) para preservar status reais de 4xx **e** 5xx — correção sobre o original (§4.2 Tech Spec).
3. **Mapeamento de erros**:
   - `ConnectException` → `PzResponse(504, "Connection timed out after {N} seconds", ...)`.
   - `\Throwable` genérica → `PzResponse(500, "Uncategorised exception occurred.", ...)`.
4. **`finally { prepare(); }`** — garante reset mesmo em falha (RN-02).
5. **Body**: array → `json` (default) ou `form_params` (se `asFormUrlEncoded`); string → `body` cru; multipart se houver itens.
6. **Auth**: bearer já é header (vem da Base); basic auth vai para opção `auth` do Guzzle.
7. **Credenciais nunca em query** — teste de regressão obrigatório (RN-04 / RNF-02).

**Referências**:
- PRD: `tasks/prd-pzrequest/prd.md` - RF-02, RF-03, RF-06, RF-07, RN-01, RN-02, RN-03, RN-04, RNF-02
- Tech Spec: `tasks/prd-pzrequest/techspec.md` - Seções 2.2 (passos 4-7), 2.3, 4.2, 6.1, 6.2, 8.2 (`PzRequestGuzzleTest`), 11.1 (Fase 3)

---

## Escopo

### O que ESTÁ no escopo:
- ✅ `src/Adapter/Guzzle/PzRequestGuzzle.php` (estende `PzRequestBase`)
- ✅ Implementação de `execute(PzRequestMethodEnum $httpMethod = GET): PzResponse` com Guzzle
- ✅ Implementação de **todos os 7 atalhos** (`get`, `post`, `put`, `patch`, `delete`, `head`, `options`)
- ✅ Montagem de `$config` Guzzle a partir do estado da Base (headers, query, base_uri/endpoint)
- ✅ Montagem de `$options` Guzzle (timeouts, json/form_params/body, multipart, auth)
- ✅ Mapeamento de exceções: `ConnectException` → 504, `BadResponseException` → status real, `\Throwable` → 500
- ✅ `finally { prepare(); }` em todas as paths
- ✅ `Client` instanciado por chamada (sem pool — Tech Spec §4.3)
- ✅ Testes unitários com `MockHandler` para todos os cenários
- ✅ `PzRequestGuzzleSecurityTest` validando RN-04/RNF-02

### O que NÃO está no escopo:
- ❌ Factory (Task 5.0)
- ❌ Service Provider Laravel (Task 6.0)
- ❌ Retry, circuit breaker, async (fora do PRD §10)

---

## Subtarefas

### 1. Implementar `PzRequestGuzzle`
**Descrição**: Adapter concreto Guzzle. O método `execute()` é o ponto central — todos os atalhos delegam para ele.

**Arquivos afetados**:
- `src/Adapter/Guzzle/PzRequestGuzzle.php` (novo)

**Implementação** (esqueleto):
```php
<?php

declare(strict_types=1);

namespace Puzl\PzRequest\Adapter\Guzzle;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ConnectException;
use Puzl\PzRequest\Adapter\PzRequestBase;
use Puzl\PzRequest\Enum\PzRequestMethodEnum;
use Puzl\PzRequest\PzResponse;
use Throwable;

class PzRequestGuzzle extends PzRequestBase
{
    protected ?ClientInterface $clientOverride = null;

    public function execute(PzRequestMethodEnum $httpMethod = PzRequestMethodEnum::GET): PzResponse
    {
        $sentBody = $this->getBodyAsString();
        try {
            $client = $this->resolveClient();
            $response = $client->request(
                $httpMethod->name,
                $this->endpoint,
                $this->buildOptions(),
            );
            return new PzResponse(
                $response->getStatusCode(),
                $response->getReasonPhrase(),
                (string) $response->getBody(),
                $sentBody,
            );
        } catch (BadResponseException $e) {
            $resp = $e->getResponse();
            return new PzResponse(
                $resp->getStatusCode(),
                $resp->getReasonPhrase(),
                (string) $resp->getBody(),
                $sentBody,
            );
        } catch (ConnectException $e) {
            return new PzResponse(
                504,
                sprintf('Connection timed out after %d seconds', $this->connectTimeout),
                $e->getMessage(),
                $sentBody,
            );
        } catch (Throwable $e) {
            return new PzResponse(
                500,
                'Uncategorised exception occurred.',
                $e->getMessage(),
                $sentBody,
            );
        } finally {
            $this->prepare();
        }
    }

    public function get(): PzResponse     { return $this->execute(PzRequestMethodEnum::GET); }
    public function post(): PzResponse    { return $this->execute(PzRequestMethodEnum::POST); }
    public function put(): PzResponse     { return $this->execute(PzRequestMethodEnum::PUT); }
    public function patch(): PzResponse   { return $this->execute(PzRequestMethodEnum::PATCH); }
    public function delete(): PzResponse  { return $this->execute(PzRequestMethodEnum::DELETE); }
    public function head(): PzResponse    { return $this->execute(PzRequestMethodEnum::HEAD); }
    public function options(): PzResponse { return $this->execute(PzRequestMethodEnum::OPTIONS); }

    protected function resolveClient(): ClientInterface
    {
        return $this->clientOverride ?? new Client([
            'headers' => $this->headers,
            'query'   => $this->queries,
        ]);
    }

    protected function buildOptions(): array
    {
        $options = [
            'connect_timeout' => $this->connectTimeout,
            'read_timeout'    => $this->readTimeout,
            'timeout'         => $this->timeout,
            'http_errors'     => true,
        ];

        if ($this->basicAuth !== null) {
            $options['auth'] = $this->basicAuth;
        }
        if (!empty($this->multiparts)) {
            $options['multipart'] = $this->multiparts;
        } elseif (is_string($this->body) && $this->body !== '') {
            $options['body'] = $this->body;
        } elseif (is_array($this->body) && $this->body !== []) {
            $options[$this->asFormUrlEncoded ? 'form_params' : 'json'] = $this->body;
        }

        return $options;
    }
}
```

> **Notas**:
> - `clientOverride` permite injetar um `Client` com `MockHandler` em testes. Não é exposto na API pública (protected).
> - Uma alternativa mais ortodoxa é usar `ReflectionProperty` nos testes; aqui escolhemos um método protegido por simplicidade — manter `protected` evita uso indevido em produção.
> - `http_errors=true` (default Guzzle) — 4xx/5xx caem em `BadResponseException`, capturada acima.

---

### 2. Testes Unitários (Comportamento Geral)
**Descrição**: Cobrir todos os cenários de `execute()` e os 7 atalhos com `MockHandler`.

**Arquivos de teste**:
- `tests/Unit/Adapter/Guzzle/PzRequestGuzzleTest.php`
- `tests/Unit/Adapter/Guzzle/Fixtures/PzRequestGuzzleTestable.php` (subclasse com setter público de `clientOverride`)

**Casos de teste obrigatórios**:

1. **`test_execute_returns_pzresponse_for_2xx`**
   - **Cenário**: `MockHandler` retorna `Response(200, [], 'ok')`.
   - **Expectativa**: `statusCode === 200`, `statusText === 'OK'`, `content === 'ok'`.

2. **`test_execute_returns_pzresponse_for_4xx_with_real_status`**
   - **Cenário**: Mock retorna `Response(404, [], '{"error":"not found"}')` — Guzzle lança `ClientException` (subclasse de `BadResponseException`).
   - **Expectativa**: `statusCode === 404`, `statusText === 'Not Found'`, `content === '{"error":"not found"}'`.

3. **`test_execute_returns_pzresponse_for_5xx_with_real_status`**
   - **Cenário**: Mock retorna `Response(503, [], 'maintenance')` — Guzzle lança `ServerException` (subclasse de `BadResponseException`).
   - **Expectativa**: `statusCode === 503`, `statusText === 'Service Unavailable'`, `content === 'maintenance'`.

4. **`test_execute_maps_connect_exception_to_504_with_configured_seconds`**
   - **Cenário**: Mock injeta `ConnectException`. `setConnectTimeout(7)` antes.
   - **Expectativa**: `statusCode === 504`, `statusText === 'Connection timed out after 7 seconds'`, `content` contém a mensagem da exceção.

5. **`test_execute_maps_generic_throwable_to_500`**
   - **Cenário**: Mock injeta `\RuntimeException('boom')`.
   - **Expectativa**: `statusCode === 500`, `statusText === 'Uncategorised exception occurred.'`, `content === 'boom'`.

6. **`test_execute_calls_prepare_on_success`**
   - **Cenário**: Setar endpoint/queries/body, executar, sucesso.
   - **Expectativa**: Após retorno, `endpoint === ''`, `queries === []`, `body === []` (via reflexão).

7. **`test_execute_calls_prepare_on_bad_response`**
   - **Cenário**: Mock 404, setar estado antes.
   - **Expectativa**: Estado resetado após execução.

8. **`test_execute_calls_prepare_on_connect_exception`**
   - **Cenário**: Mock `ConnectException`, setar estado.
   - **Expectativa**: Estado resetado.

9. **`test_execute_calls_prepare_on_generic_exception`**
   - **Cenário**: Mock `\RuntimeException`, setar estado.
   - **Expectativa**: Estado resetado.

10. **`test_execute_uses_method_name_from_enum`**
    - **Cenário**: Usar `History` middleware do Guzzle para capturar a requisição enviada. `execute(PzRequestMethodEnum::PUT)`.
    - **Expectativa**: Request capturado tem `method === 'PUT'`.

11. **Atalhos HTTP (uma asserção por método)**:
    - `test_get_shortcut_uses_get_method` / `test_post_shortcut_uses_post_method` / etc. para os 7 atalhos.
    - **Cenário**: Capturar request enviado.
    - **Expectativa**: Método HTTP correto.

12. **`test_body_array_without_form_flag_is_sent_as_json`**
    - **Cenário**: `addBody(['a' => 1])`.
    - **Expectativa**: Header `Content-Type: application/json` (definido pelo Guzzle ao usar opção `json`); body recebido contém `{"a":1}`.

13. **`test_body_array_with_form_flag_is_sent_as_form_params`**
    - **Cenário**: `addBody(['a' => 1, 'b' => 2])->asFormUrlEncoded()`.
    - **Expectativa**: `Content-Type: application/x-www-form-urlencoded`; body é `a=1&b=2`.

14. **`test_body_string_is_sent_as_raw_body`**
    - **Cenário**: `addBody('raw payload')`.
    - **Expectativa**: Body recebido pelo mock é exatamente `'raw payload'`.

15. **`test_multipart_overrides_body`**
    - **Cenário**: `addMultipart('file', 'data', 'doc.txt')` e também `addBody(['x' => 1])`.
    - **Expectativa**: Request enviado é `multipart/form-data` (multipart tem precedência).

16. **`test_basic_auth_is_set_via_guzzle_auth_option`**
    - **Cenário**: `withBasicAuth('user', 'secret')`. Capturar via History middleware.
    - **Expectativa**: Header `Authorization: Basic <base64('user:secret')>` está presente.

17. **`test_bearer_token_is_set_via_authorization_header`**
    - **Cenário**: `withBearer('abc.def.ghi')`.
    - **Expectativa**: Header `Authorization: Bearer abc.def.ghi`.

18. **`test_timeouts_are_passed_to_guzzle_options`**
    - **Cenário**: `setConnectTimeout(5)->setReadTimeout(10)->setTimeout(15)` + capturar `Request::getOptions()` via subclasse de teste que expõe `buildOptions()`.
    - **Expectativa**: `connect_timeout === 5`, `read_timeout === 10`, `timeout === 15`.

19. **`test_pz_response_sent_body_reflects_serialized_request_body`**
    - **Cenário**: `addBody(['k' => 'v'])` + execute.
    - **Expectativa**: `PzResponse->sentBody === '{"k":"v"}'`.

20. **`test_pz_response_sent_body_reflects_form_encoded_request_body`**
    - **Cenário**: `addBody(['k' => 'v'])->asFormUrlEncoded()` + execute.
    - **Expectativa**: `PzResponse->sentBody === 'k=v'`.

**Cobertura mínima**: ≥ 90% de `PzRequestGuzzle`.

---

### 3. Testes Unitários de Segurança (`PzRequestGuzzleSecurityTest`)
**Descrição**: Testes de regressão garantindo OWASP API2:2023 — credenciais fora da URL.

**Arquivos de teste**:
- `tests/Unit/Adapter/Guzzle/PzRequestGuzzleSecurityTest.php`

**Casos de teste obrigatórios**:

1. **`test_with_bearer_does_not_add_token_to_query_string`**
   - **Cenário**: `withBearer('secret-token')` + `endpoint('https://api/x')` + execute via mock + capturar request.
   - **Expectativa**: URL final **não contém** `secret-token` em query string. Header `Authorization` presente.

2. **`test_with_basic_auth_does_not_add_credentials_to_query_string`**
   - **Cenário**: `withBasicAuth('user', 'secret')` + capturar request.
   - **Expectativa**: URL final **não contém** `user` nem `secret` em query string. Auth via header.

3. **`test_credentials_are_not_logged_in_pz_response_sent_body`**
   - **Cenário**: `withBearer('top-secret')->addBody(['k' => 'v'])` + execute.
   - **Expectativa**: `PzResponse->sentBody` (que reflete body) **não contém** `top-secret`.

4. **`test_query_string_only_contains_user_supplied_keys`**
   - **Cenário**: `addQuery('page', 1)` + `withBearer('x')` + capturar request.
   - **Expectativa**: Query da URL final tem **apenas** `page=1`.

5. **`test_executing_after_basic_auth_does_not_pollute_subsequent_calls_with_query_credentials`**
   - **Cenário**: Duas execuções sequenciais com `withBasicAuth` na primeira.
   - **Expectativa**: Nenhuma das duas tem credenciais em query (segunda confirma que `prepare()` não vaza para queries acidentalmente).

**Cobertura mínima**: contribui para a meta global ≥ 90%.

---

### 4. Testes de Integração
**Descrição**: Validar que `PzRequestGuzzle` se compõe com `PzRequestBase` (herança correta) e com `PzResponse` (DTO retornado).

**Arquivos de teste**:
- Compreendidos dentro dos arquivos acima — não há outro componente para integrar nesta tarefa.

**Cenários de teste obrigatórios**:
1. **`test_pz_request_guzzle_is_pz_request_interface_implementation`**
   - **Fluxo**: `assertInstanceOf(PzRequestInterface::class, new PzRequestGuzzle())`.
   - **Expectativa**: Verdadeiro.

2. **`test_pz_request_guzzle_extends_pz_request_base`**
   - **Fluxo**: `assertInstanceOf(PzRequestBase::class, new PzRequestGuzzle())`.
   - **Expectativa**: Verdadeiro.

3. **Suíte completa verde**
   - **Fluxo**: `composer test`.
   - **Expectativa**: Exit code `0`, todos os testes anteriores + os ~25 novos passando, **sem rede**.

---

## Critérios de Aceitação

- [ ] `src/Adapter/Guzzle/PzRequestGuzzle.php` criado e funcional
- [ ] Estende `PzRequestBase` e implementa todos os métodos abstratos
- [ ] `execute()` mapeia 2xx, 4xx, 5xx, `ConnectException` (504), `\Throwable` (500)
- [ ] `prepare()` é chamado em **todos** os caminhos (sucesso e falhas)
- [ ] Body array → `json` por default, `form_params` quando flag ativa
- [ ] Body string → `body` cru
- [ ] Multipart tem precedência sobre body
- [ ] Basic auth via opção `auth` do Guzzle (não em query)
- [ ] Bearer via header `Authorization`
- [ ] Todos os ~25 casos de teste passando (20 de comportamento + 5 de segurança)
- [ ] Cobertura ≥ 90% em `PzRequestGuzzle`
- [ ] Zero testes dependem de rede (todos via `MockHandler` ou `History` middleware)
- [ ] Tipagem PHP estrita
- [ ] PHPDoc em `execute()` listando o mapeamento de erros
- [ ] `composer test` verde
- [ ] Code review aprovado

---

## Entregáveis

**Arquivos Criados**:
- [ ] `src/Adapter/Guzzle/PzRequestGuzzle.php`
- [ ] `tests/Unit/Adapter/Guzzle/PzRequestGuzzleTest.php`
- [ ] `tests/Unit/Adapter/Guzzle/PzRequestGuzzleSecurityTest.php`
- [ ] `tests/Unit/Adapter/Guzzle/Fixtures/PzRequestGuzzleTestable.php`

**Arquivos Modificados**:
- [ ] Remover `.gitkeep` de `src/Adapter/Guzzle/` se ainda existir

**Documentação**:
- [ ] PHPDoc em `execute()` com tabela de mapeamento de erros (referência §4.2 Tech Spec)
- [ ] PHPDoc em `buildOptions()` explicando precedência multipart > body string > body array

---

## Guia de Implementação

### Passo 1: Criar testable fixture
```php
<?php
declare(strict_types=1);
namespace Puzl\PzRequest\Tests\Unit\Adapter\Guzzle\Fixtures;

use GuzzleHttp\ClientInterface;
use Puzl\PzRequest\Adapter\Guzzle\PzRequestGuzzle;

final class PzRequestGuzzleTestable extends PzRequestGuzzle
{
    public function setClientOverride(ClientInterface $client): void
    {
        $this->clientOverride = $client;
    }

    public function exposeBuildOptions(): array
    {
        return $this->buildOptions();
    }
}
```

### Passo 2: Padrão de teste com `MockHandler` + `History`
```php
$history = [];
$mock = new \GuzzleHttp\Handler\MockHandler([
    new \GuzzleHttp\Psr7\Response(200, [], 'ok'),
]);
$stack = \GuzzleHttp\HandlerStack::create($mock);
$stack->push(\GuzzleHttp\Middleware::history($history));
$client = new \GuzzleHttp\Client(['handler' => $stack]);

$pz = new PzRequestGuzzleTestable();
$pz->setClientOverride($client);
$pz->endpoint('https://api.test/x')->get();

assertSame('GET', $history[0]['request']->getMethod());
```

### Passo 3: Implementar `PzRequestGuzzle`
Copie a Subtarefa 1. Compare com `App\Library\RestRequest\Adapter\Guzzle\RestRequestGuzzle` original para validar paridade.

### Passo 4: Validar cobertura
```bash
composer test:coverage
```
Inspecione `coverage/index.html` — confirmar que `PzRequestGuzzle` tem ≥ 90% e que **todos** os branches de `execute()` (try/catches/finally) estão cobertos.

---

## Validação

### Checklist de Validação Manual:
- [ ] Cobertura ≥ 90% na classe (relatório HTML)
- [ ] Nenhum teste faz chamada real de rede (verificar ausência de `Client` sem `handler`)
- [ ] Inspeção visual de `execute()` confirma `prepare()` em `finally`
- [ ] Confirmar via teste que `BadResponseException` (não só `ClientException`) está sendo capturada — testa tanto 4xx quanto 5xx

### Comandos de Validação:
```bash
composer test:coverage
vendor/bin/phpunit --testsuite unit --filter PzRequestGuzzle
vendor/bin/phpunit --testsuite unit --filter PzRequestGuzzleSecurityTest
```

---

## Riscos e Mitigações

| Risco | Impacto | Probabilidade | Mitigação |
|-------|---------|---------------|-----------|
| Capturar só `ClientException` (4xx) e deixar 5xx vazar como `\Throwable` (500) | Alto | Média | Caso de teste 3 valida 5xx → status real, não 500 |
| `prepare()` não ser chamado em alguma path | Alto | Média | Casos 6-9 cobrem sucesso, 4xx, ConnectException e Throwable |
| Credenciais vazarem para query por bug futuro | Alto | Baixa | `PzRequestGuzzleSecurityTest` (5 casos) |
| Acoplar testes à versão exata do Guzzle (status text muda) | Baixo | Baixa | Usar `getReasonPhrase()` real do mock, não strings hardcoded |
| Testes dependerem de rede acidentalmente | Médio | Baixa | Code review obrigatório busca por `new Client(` sem `handler` |
| `clientOverride` ser usado em produção indevidamente | Baixo | Baixa | Mantido `protected` e setter só na fixture de teste |

---

## Notas Adicionais

- A captura de `BadResponseException` (que abrange `ClientException` e `ServerException`) é a "lacuna corrigida" mencionada no original (§4.2 Tech Spec). Sem isso, 5xx viraria 500 genérico — comportamento indesejado.
- O `Client` é instanciado por chamada (não há pool). Custo: < 1 ms (Tech Spec §7.1) — irrelevante diante do RNF-01 (< 5 ms overhead total).
- `History` middleware do Guzzle é a forma mais limpa de inspecionar o request enviado pelo adapter — usar amplamente nos testes.
- Cuidado para não assumir ordem de chaves em arrays de queries (PHP arrays associativos preservam ordem de inserção, mas asserções `assertEquals` em arrays não são sensíveis à ordem; usar `assertSame` se a ordem importar).

---

## Histórico

| Data | Autor | Mudança |
|------|-------|---------|
| 2026-04-24 | Time Plataforma Puzl | Criação da tarefa |
