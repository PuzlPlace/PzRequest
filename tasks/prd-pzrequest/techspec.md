# Tech Spec - PzRequest (Pacote Cliente HTTP REST)

## 1. Visão Geral

### 1.1 Objetivo Técnico
Empacotar o atual `App\Library\RestRequest` (do monolito `puzl/api`) como biblioteca Composer privada `puzl/pzrequest`, replicando exatamente a arquitetura **Contract + Base abstrata + Adapter + Factory + Service Provider Laravel** já consolidada em `PzMail`/`PzPdf`. O foco desta spec é definir como reutilizar o código existente do `RestRequest` (Guzzle), padronizar o nome (`Pz*`) e elevar a qualidade ao nível dos demais pacotes Puzl (testes, auto-discovery, configuração publicável, smoke test em subprocess).

### 1.2 Escopo Técnico
- Novo repositório `PzRequest` (já criado em `/home/leo/code/puzl/PzRequest`).
- Diretórios `src/`, `config/`, `tests/Unit`, `tests/Features`, `templates/`.
- Componentes: `PzRequestInterface`, `PzRequestBase`, `PzRequestGuzzle`, `PzRequestFactory`, `PzRequestEnum`, `PzRequestMethodEnum`, `PzResponse`, `PzRequestServiceProvider`.
- Integração Laravel via auto-discovery (`extra.laravel.providers`) e config publicável (`pzrequest-config`).
- Suíte PHPUnit 10.5 com isolamento por subprocess (padrão `PzPdf`).
- **Sem alterações em outros projetos**: a migração do `puzl/api` para consumir o pacote é tema de outro PRD.

### 1.3 Stack Tecnológica
- PHP `^8.1` (`declare(strict_types=1)` em todo `src/`).
- `guzzlehttp/guzzle ^7.0` (cliente HTTP de fato).
- `illuminate/support ^10.0|^11.0|^12.0` (Service Provider Laravel).
- `phpunit/phpunit ^10.5` (dev) — `MockHandler` do Guzzle para isolamento de rede.
- Cobertura via Xdebug (`XDEBUG_MODE=coverage`).

---

## 2. Arquitetura

### 2.1 Diagrama de Arquitetura
```
┌─────────────────── Consumidor (app Laravel) ────────────────────┐
│  use Puzl\PzRequest\Factory\PzRequestFactory;                  │
│  PzRequestFactory::make()->endpoint(...)->withBearer(...)->get│
└──────────────────────────────┬─────────────────────────────────┘
                               │ retorna PzRequestInterface
                               ▼
   ┌──────────────────── Puzl\PzRequest ────────────────────────┐
   │                                                            │
   │  Factory/PzRequestFactory ── Factory/PzRequestEnum         │
   │           │                         │                      │
   │           │ resolve ◀─ config/pzrequest.php ◀─ env(PZREQUEST_ADAPTER)
   │           ▼                                                │
   │  Contract/PzRequestInterface  (API pública, contrato puro) │
   │           ▲                                                │
   │           │ implements                                     │
   │  Adapter/PzRequestBase  (estado fluente + helpers)         │
   │           ▲                                                │
   │           │ extends                                        │
   │  Adapter/Guzzle/PzRequestGuzzle  ──▶ GuzzleHttp\Client     │
   │           │                          (config + options)    │
   │           ▼                                                │
   │  PzResponse (DTO de saída: status, statusText, content,    │
   │              sentBody, getArrayContent, getSimpleXmlElement│
   │                                                            │
   │  Enum/PzRequestMethodEnum (GET, POST, PUT, PATCH, DELETE,  │
   │                            OPTIONS, HEAD)                  │
   │                                                            │
   │  Laravel/PzRequestServiceProvider  (mergeConfig + publishes│
   └────────────────────────────────────────────────────────────┘
```

### 2.2 Fluxo de Dados
1. Consumidor chama `PzRequestFactory::make()` (sem argumento, lê `pzrequest.adapter` ou `PZREQUEST_ADAPTER`; com argumento, força adapter).
2. Factory devolve um `PzRequestInterface` concreto (default `PzRequestGuzzle`).
3. Setters fluentes acumulam estado em `PzRequestBase` (endpoint, headers, queries, body, multiparts, params, basicAuth, timeouts, flag form-urlencoded).
4. Consumidor invoca `get()|post()|put()|patch()|delete()|head()|options()` ou `execute(PzRequestMethodEnum)`.
5. `PzRequestGuzzle::execute()` monta `$config` (headers, query) e `$options` (timeouts, json/form_params/body/multipart, auth) e executa `Client::request($method->name, $endpoint, $options)`.
6. Resposta Guzzle → `new PzResponse(statusCode, statusText, content, sentBody)`.
7. Em qualquer caminho (sucesso, `ClientException`, `ConnectException`, `\Throwable`) o bloco `finally` chama `prepare()` para reset de estado da instância.
8. `PzResponse` permanece imutável e oferece `getArrayContent()` (JSON) e `getSimpleXmlElement()` (XML normalizado para UTF-8).

### 2.3 Decisões Arquiteturais
- **Reutilizar Guzzle** (já validado em produção no `puzl/api`) — não vamos inventar transport. RNF-01 exige overhead < 5 ms; o wrapper apenas monta arrays.
- **Mesma topologia de pastas do `PzMail`** (e não a do `PzPdf`, que tem `Console`/`Component`/`Templates`) porque o pacote é puramente de runtime, sem comandos artisan.
- **Não lançar exceções para o consumidor** (RF-06/RN-03): erros viram `PzResponse` com status semântico (504 timeout, 500 genérico, status real para 4xx/5xx). Decisão deliberada para preservar a paridade comportamental do `RestRequest` e simplificar o consumo.
- **`finally { prepare(); }`** garante RN-02 mesmo quando a thread do consumidor reaproveita a mesma instância (via container Laravel singleton, por exemplo).
- **Pequenas correções de qualidade vs. paridade absoluta**: contrato passa a declarar `addQuery(string, mixed)` (alinhando ao Base) e `PzResponse::sentBody` em camelCase (RN-05). São mudanças de assinatura/propriedade — o comportamento permanece idêntico ao `RestRequest`.
- **Auto-discovery Laravel** + `mergeConfigFrom` no `register()` e `publishes` no `boot()` (mesmo template do `PzMailServiceProvider`).
- **`MockHandler` do Guzzle** nos testes unitários do adapter elimina dependência de rede (zero flakiness) sem precisar de variável de ambiente.

---

## 3. Interfaces e Contratos

### 3.1 DTOs (Data Transfer Objects)
```php
namespace Puzl\PzRequest;

final class PzResponse
{
    public function __construct(
        public int $statusCode,
        public string $statusText,
        public string $content,
        public string $sentBody,
    ) {}

    public function getArrayContent(): array
    {
        $decoded = json_decode($this->content, true);
        return is_array($decoded) ? $decoded : ['content' => $this->content];
    }

    public function getSimpleXmlElement(): \SimpleXMLElement|false
    {
        $encoding = mb_detect_encoding($this->content, 'UTF-8, ISO-8859-1', true);
        $xml = $encoding && $encoding !== 'UTF-8'
            ? mb_convert_encoding($this->content, 'UTF-8', $encoding)
            : $this->content;
        return simplexml_load_string($xml);
    }
}
```

### 3.2 Interfaces de Serviços
```php
namespace Puzl\PzRequest\Contract;

use Puzl\PzRequest\Enum\PzRequestMethodEnum;
use Puzl\PzRequest\PzResponse;

interface PzRequestInterface
{
    public function endpoint(string $value): self;
    public function addHeader(string $name, string $value): self;
    public function addHeaders(array $keyValue): self;
    public function addQuery(string $name, mixed $value): self;
    public function addQueries(array $keyValue): self;
    public function addParam(string $name, string $value): self;
    public function addBody(array|string $value): self;
    public function addMultipart(string $name, string $contents, ?string $filename = null): self;
    public function addMultiparts(array $multiparts): self;
    public function asFormUrlEncoded(bool $value = true): self;

    public function withBearer(string $token): self;
    public function withBasicAuth(string $username, string $password): self;

    public function setConnectTimeout(int $seconds): self;
    public function setReadTimeout(int $seconds): self;
    public function setTimeout(int $seconds): self;
    public function setTimeouts(int $seconds): self;
    public function getConnectTimeout(): int;
    public function getReadTimeout(): int;
    public function getTimeout(): int;

    public function getAsFormUrlEncoded(): bool;
    public function getBodyAsString(): string;

    public function execute(PzRequestMethodEnum $httpMethod = PzRequestMethodEnum::GET): PzResponse;
    public function get(): PzResponse;
    public function post(): PzResponse;
    public function put(): PzResponse;
    public function patch(): PzResponse;
    public function delete(): PzResponse;
    public function head(): PzResponse;     // novo (paridade c/ enum)
    public function options(): PzResponse;  // novo (paridade c/ enum)

    public function prepare(): self;
}
```

### 3.3 Tipos e Enums
```php
namespace Puzl\PzRequest\Enum;

enum PzRequestMethodEnum: int
{
    case GET = 0; case POST = 1; case PUT = 2; case PATCH = 3;
    case DELETE = 4; case OPTIONS = 5; case HEAD = 6;
}
```
```php
namespace Puzl\PzRequest\Factory;

enum PzRequestEnum: int
{
    case GUZZLE = 0;
    case SYMFONY_HTTP_CLIENT = 1; // reservado para futuro adapter
    case OTHERS = 2;

    public static function tryFromName(string $name): ?self
    {
        foreach (self::cases() as $c) if ($c->name === $name) return $c;
        return null;
    }
}
```

---

## 4. Endpoints e Integração com API

### 4.1 Endpoints Utilizados
Não aplicável — o pacote **não expõe** endpoints HTTP próprios. Ele é cliente que pode chamar **qualquer** endpoint REST/SOAP via os 7 métodos do `PzRequestMethodEnum`. A "interface pública" é a API fluente descrita em §3.2.

### 4.2 Tratamento de Erros da API
Mapeamento implementado em `PzRequestGuzzle::execute()` (paridade com o original):

| Cenário Guzzle | `statusCode` | `statusText` | `content` |
|---|---|---|---|
| Sucesso 2xx/3xx | real do servidor | `getReasonPhrase()` | body real |
| `ClientException` (4xx)¹ | real do servidor | `getReasonPhrase()` | body real |
| `ConnectException` (timeout) | `504` | `"Connection timed out after {N} seconds"` | mensagem da exceção |
| `\Throwable` genérica | `500` | `"Uncategorised exception occurred."` | mensagem da exceção |

¹ Mantemos `http_errors=true` (default Guzzle) para que 4xx caia em `ClientException`. Para 5xx, Guzzle lança `ServerException` (subclasse de `BadResponseException`) — capturada via `BadResponseException` para preservar status/body reais (lacuna do código original, corrigida sem mudar contrato observável).

### 4.3 Interceptors e Middleware
- Não introduzimos middleware Guzzle nesta versão (escopo §10 do PRD).
- O `Client` é instanciado por requisição (sem pool), simplificando o reset por `prepare()` e evitando vazamento de configuração entre chamadas.

---

## 5. Roteamento e Navegação
Não aplicável — pacote backend sem rotas.

---

## 6. Segurança

### 6.1 Armazenamento de Tokens
- O pacote **não armazena** tokens. `withBearer($token)` apenas escreve o header `Authorization: Bearer <token>` em memória, descartado por `prepare()` ao final de cada `execute()`.
- `withBasicAuth($u, $p)` popula array interno consumido como opção `auth` do Guzzle (RFC 7617, header `Authorization: Basic <base64>`), nunca como query.

### 6.2 Proteção contra Ataques
- **OWASP API2:2023 (Broken Authentication)**: credenciais saem **somente** por header (`Authorization`) ou pela opção `auth` do Guzzle. Teste de regressão (`PzRequestGuzzleSecurityTest`) garante que `withBearer`/`withBasicAuth` **não** afetam `queries`.
- **TLS**: `verify=true` (default Guzzle). Não expomos toggle nesta versão para evitar desabilitação acidental.
- Consumidor é responsável por sanitizar URLs e logs no lado dele.

### 6.3 Sanitização de Dados
- `endpoint()` aceita string crua (responsabilidade do consumidor); recomendação no README de usar `http_build_query` ou apenas `addQuery()`.
- `getArrayContent()` valida resultado de `json_decode` antes de retornar (evita exceção ao receber HTML/erro do servidor) — comportamento herdado do `RestResponse` original.

---

## 7. Performance e Otimização

### 7.1 Caching
- Sem cache de resposta nesta versão (escopo §10 do PRD).
- Sem reuso de `Client` Guzzle entre `execute()` (custo desprezível: < 1 ms por instanciamento medido localmente, dentro do orçamento de RNF-01 < 5 ms).

### 7.2 Otimizações
- `PzRequestBase` mantém arrays vazios por padrão e só adiciona chaves a `curOptions`/`curConfig` quando há valor — evita pagar parsing desnecessário no Guzzle.
- `getBodyAsString()` evita `json_encode` quando body já é string.

---

## 8. Testes

### 8.1 Estratégia de Testes
- `tests/Unit`: cobre Base, Factory, Enums e `PzResponse` sem instanciar Guzzle real (>= 90% RNF-05).
- `tests/Unit/Adapter/Guzzle/PzRequestGuzzleTest.php`: usa `GuzzleHttp\Handler\MockHandler` + `HandlerStack` injetados via reflexão ou subclasse de teste para simular respostas, timeouts e exceções (zero rede).
- `tests/Features/Bootstrap/TestSuiteSmokeTest.php`: replica o pattern do `PzPdf` — abre `proc_open` com `vendor/bin/phpunit -c phpunit.xml.dist --testsuite unit` e exige exit code `0`. Garante que a suíte unit roda em isolamento total (RF-10).
- `tests/Features/Laravel/PzRequestServiceProviderTest.php`: instancia o provider sob `Illuminate\Container\Container` (sem subir Laravel) e valida `register`/`boot` + paths publicáveis.
- Integração real (sem mock) é deliberadamente excluída para não depender de internet no CI.

### 8.2 Testes Unitários
| Classe | Casos chave |
|---|---|
| `PzRequestBaseTest` | setters retornam `self`; `prepare()` reseta endpoint/params/queries/multiparts/body/form-flag; `getAsFormUrlEncoded()` true via header; `getBodyAsString()` para string e array; defaults de timeout = 30. |
| `PzRequestGuzzleTest` | `execute()` mapeia 200/4xx/5xx; `ConnectException` → 504 c/ tempo configurado; `\Exception` → 500 c/ mensagem; `prepare()` é chamado mesmo em falha (verifica via `getEndpoint` reflexivo); body array → `json`; body array + `asFormUrlEncoded(true)` → `form_params`; body string → `body`; multipart configurado; basic auth via opção `auth`; queries não recebem credenciais. |
| `PzResponseTest` | `getArrayContent()` JSON válido, JSON inválido (fallback `['content' => …]`); `getSimpleXmlElement()` UTF-8 e ISO-8859-1; tipos das propriedades públicas. |
| `PzRequestFactoryTest` | `make()` sem argumento → Guzzle; `make(GUZZLE)` → Guzzle; `make(SYMFONY_HTTP_CLIENT)`/`make(OTHERS)` → fallback Guzzle; `PZREQUEST_ADAPTER` lido via `getenv` quando `config()` ausente; nome inválido → fallback. |
| `PzRequestMethodEnumTest` / `PzRequestEnumTest` | nomes/valores; `tryFromName` (PzRequestEnum). |

### 8.3 Testes de Integração
- `TestSuiteSmokeTest::test_unit_tests_pass_via_phpunit_subprocess` (cópia adaptada do `PzPdf`).
- `PzRequestServiceProviderTest::test_register_merges_config` e `::test_boot_publishes_config_with_tag`.
- Não há contract test externo nesta versão (sem APIs próprias).

---

## 9. Observabilidade

### 9.1 Logging
- O pacote **não escreve** em nenhum logger (RNF-02). README declara explicitamente "PzRequest é silencioso; o consumidor deve logar `PzResponse` sem incluir headers de autenticação".
- Não há gancho PSR-3 nesta versão (escopo §10 do PRD); se necessário, adicionar via `withLogger(LoggerInterface)` em release MINOR futura sem quebrar contrato.

### 9.2 Métricas
- Não emitidas pelo pacote. Consumidores podem instrumentar em volta de `execute()` (latência = `microtime` antes/depois).

### 9.3 Monitoramento de Erros
- Como falhas viram `PzResponse`, o consumidor deve checar `statusCode >= 400` para reportar a APMs (Sentry/New Relic). Documentar padrão no README ("Anti-pattern: ignorar `statusCode` retornado").

---

## 10. Análise de Impacto

### 10.1 Arquivos Criados
```
composer.json
phpunit.xml.dist
README.md
config/pzrequest.php
src/Contract/PzRequestInterface.php
src/Adapter/PzRequestBase.php
src/Adapter/Guzzle/PzRequestGuzzle.php
src/Factory/PzRequestFactory.php
src/Factory/PzRequestEnum.php
src/Enum/PzRequestMethodEnum.php
src/PzResponse.php
src/Laravel/PzRequestServiceProvider.php
tests/Unit/Adapter/PzRequestBaseTest.php
tests/Unit/Adapter/Guzzle/PzRequestGuzzleTest.php
tests/Unit/Adapter/Guzzle/PzRequestGuzzleSecurityTest.php
tests/Unit/Factory/PzRequestFactoryTest.php
tests/Unit/Factory/PzRequestEnumTest.php
tests/Unit/Enum/PzRequestMethodEnumTest.php
tests/Unit/PzResponseTest.php
tests/Features/Bootstrap/TestSuiteSmokeTest.php
tests/Features/Laravel/PzRequestServiceProviderTest.php
```

### 10.2 Arquivos Modificados
- `README.md` (atual tem só "PzRequest"): expandir com instalação, uso, tabela de métodos, observações de segurança/observabilidade.
- `.gitignore`: adicionar `/.phpunit.cache/` e `composer.lock` opcional (alinhar ao `PzMail`).

### 10.3 Dependências Adicionadas
`composer.json` (novo):
- `require`: `php ^8.1`, `guzzlehttp/guzzle ^7.0`, `illuminate/support ^10.0|^11.0|^12.0`.
- `require-dev`: `phpunit/phpunit ^10.5`.
- `extra.laravel.providers`: `Puzl\\PzRequest\\Laravel\\PzRequestServiceProvider`.
- `scripts`: `test`, `test:coverage` (idênticos ao `PzMail`).

### 10.4 Breaking Changes
- Versão `1.0.0` inicial — não há "antes" público.
- Para times que migrarem do `App\Library\RestRequest` interno para `Puzl\PzRequest`:
  - Renomear classes (`RestRequest*` → `PzRequest*`, `RestResponse` → `PzResponse`).
  - `PzResponse::$sent_body` → `PzResponse::$sentBody` (RN-05).
  - Contrato declara `addQuery(string, mixed)` (antes `string, string` no contract / `string, mixed` no Base — agora consistente).
  - Adicionados `head()` e `options()` no contract — não afeta quem implementava só o contrato porque é adição **a interface pública** que só impacta novas implementações; consumidores ganham métodos sem perder existentes.

---

## 11. Plano de Implementação

### 11.1 Fases de Desenvolvimento
1. **Bootstrap** — `composer.json`, `phpunit.xml.dist`, `.gitignore`, estrutura de pastas, `composer install`.
2. **Núcleo** — Enums (`PzRequestMethodEnum`, `PzRequestEnum`), `Contract/PzRequestInterface`, `PzResponse`.
3. **Base + Adapter** — `PzRequestBase` (porta o original com correções de tipo) e `PzRequestGuzzle` (adiciona captura de `BadResponseException`).
4. **Factory** — `PzRequestFactory` no estilo `PzMailFactory`.
5. **Laravel** — `config/pzrequest.php` + `PzRequestServiceProvider`.
6. **Testes Unit** — alvo ≥ 90% (medido via `composer test:coverage`).
7. **Smoke Test em Subprocess** — `tests/Features/Bootstrap/TestSuiteSmokeTest.php`.
8. **README** — instalação, exemplos, tabela de métodos, notas de segurança/observabilidade.
9. **Tag inicial** — `1.0.0` + branches `master`/`production` (espelha `PzMail`).

### 11.2 Ordem de Implementação
Fases 1 → 2 → 3 → 4 → 5 → 6 → 7 → 8 → 9 (sequencial; cada fase deixa `composer test` verde).

### 11.3 Dependências entre Tarefas
- Fases 3+ exigem fase 2 (enums + contract + DTO).
- Fase 6 exige 3-5 (todas as classes prontas para mock/instanciação).
- Fase 7 exige fase 6 (subprocess invoca a suíte unit).
- Fase 9 exige todas as anteriores verdes.

---

## 12. Considerações Adicionais

### 12.1 Compatibilidade
- PHP 8.1, 8.2, 8.3 (testar em 8.2.26 conforme `platform.php` do `PzMail`).
- Laravel 10/11/12 (auto-discovery do provider funciona em todas).
- Guzzle 7.x (pinagem por major; bump de major do Guzzle exige nova `MAJOR` do `PzRequest`).

### 12.2 Acessibilidade
Não aplicável (sem UI). Acessibilidade do código: nomes autoexplicativos, `declare(strict_types=1)`, parâmetros tipados.

### 12.3 Internacionalização
Mensagens internas (`statusText` de erros: "Connection timed out…", "Uncategorised exception occurred.") permanecem em inglês técnico — coerentes com `RestResponse` original e padrão HTTP.

### 12.4 Migração e Deploy
- Repositório VCS privado (`https://github.com/PuzlPlace/PzRequest.git`).
- Branches `master` (dev) e `production` (consumo via `dev-production`), padrão `PzMail`.
- Deploy = push + tag SemVer + atualização do `composer.json` no consumidor.
- Não há banco, nem migrações, nem assets para publicar (apenas `config/pzrequest.php`).

---

## 13. Referências Técnicas

### 13.1 Documentação
- Guzzle 7 Request Options: <https://docs.guzzlephp.org/en/stable/request-options.html>
- Guzzle 7 Exceptions: <https://docs.guzzlephp.org/en/stable/quickstart.html#exceptions>
- Laravel Package Development (Auto-discovery, `mergeConfigFrom`, `publishes`): <https://laravel.com/docs/11.x/packages>
- PHPUnit 10.5 Configuration: <https://docs.phpunit.de/en/10.5/configuration.html>

### 13.2 Padrões do Projeto
- `.cursor/rules/context7.md` — uso obrigatório do Context7 para docs de libs (já seguido nesta spec).
- Convenções extraídas dos pacotes irmãos (sem rule formal):
  - Estrutura `src/{Adapter,Contract,Factory,Laravel}` + `config/` + `tests/{Unit,Features}` (`PzMail`).
  - Smoke test em subprocess com `proc_open` (`PzPdf/tests/Features/Bootstrap/TestSuiteSmokeTest.php`).
  - Factory com `resolveAdapterFromConfig` (`PzMail/src/Factory/PzMailFactory.php`).
  - Enum `tryFromName` (`PzMail/src/Factory/PzMailEnum.php`).
  - Service Provider mínimo: `mergeConfigFrom` em `register`, `publishes` com tag `<pkg>-config` em `boot` (`PzMail/src/Laravel/PzMailServiceProvider.php`).

### 13.3 Recursos Externos
- RFC 9110 — HTTP Semantics: <https://www.rfc-editor.org/rfc/rfc9110.html>
- OWASP API Security Top 10 2023 — API2 Broken Authentication: <https://owasp.org/API-Security/editions/2023/en/0xa2-broken-authentication/>
- Código-fonte de referência: `puzl/api/app/Library/RestRequest/{Adapter,Contract,Enum,Factory,RestResponse.php}`.

---

## Histórico de Versões

| Versão | Data | Autor | Descrição |
|--------|------|-------|-----------|
| 1.0 | 2026-04-24 | Time Plataforma Puzl | Versão inicial da Tech Spec do pacote `PzRequest` |
