# PzRequest

Cliente HTTP REST fluente da Puzl, baseado em Guzzle. Uniforma o consumo de
APIs REST entre os projetos Laravel da casa, com tipagem estrita e cobertura
de testes ≥ 90%.

- **Sem exceções vazadas** — erros viram `PzResponse` com `statusCode`
  semântico (504 timeout, 500 genérico, status real para 4xx/5xx).
- **Auto-reset** — `prepare()` é chamado em `finally` no `execute()`; estado
  nunca vaza entre chamadas.
- **Sem credenciais em URL** — Bearer/Basic vão sempre em header.
- **Plug-and-play em Laravel 10/11/12/13** via auto-discovery do Service Provider.

---

## Instalação

O repositório `PuzlPlace/PzRequest` é público, mas o pacote **não está no Packagist**: é distribuído pelo próprio
repositório Git. São dois passos.

### 1. Declare o repositório

No `composer.json` da aplicação, no mesmo nível de `require` (já tem outros pacotes `Pz*`? acrescente mais um objeto):

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/PuzlPlace/PzRequest.git"
    }
]
```

### 2. Instale por versão

```bash
composer require puzl/pzrequest:^1.0
```

| Restrição | O que instala |
|---|---|
| `^1.0` | última `1.x`: correções e novidades compatíveis. **Recomendado.** |
| `~1.2.0` | última `1.2.x`: só correções de patch. |
| `v1.2.3` | exatamente essa tag. |
| `dev-production` | topo da branch, sem versão. Modo legado: evite. |

Auto-discovery do Service Provider em Laravel 10/11/12/13 — não precisa registrar manualmente.

### Versionamento

Não há versão escrita em lugar nenhum deste pacote: a fonte é a tag do Git. Toda entrega na branch `production` que
passa no CI gera sozinha a próxima tag *patch* e o release (`.github/workflows/tests.yml`, job `Release`). Para subir
*minor* ou *major*, crie a tag na mão uma vez (`git tag v1.1.0 && git push origin v1.1.0`); o cálculo segue dela.

Para customizar o adapter HTTP padrão:

```bash
php artisan vendor:publish --tag=pzrequest-config
```

Edite `config/pzrequest.php` ou exporte `PZREQUEST_ADAPTER` no `.env`:

```dotenv
PZREQUEST_ADAPTER=GUZZLE
```

---

## Início rápido

```php
use Puzl\PzRequest\Factory\PzRequestFactory;

$response = PzRequestFactory::make()
    ->endpoint('https://api.example.com/users')
    ->withBearer($token)
    ->addQuery('page', 1)
    ->get();

if ($response->statusCode === 200) {
    $data = $response->getArrayContent();
}
```

---

## Exemplos

### 1. GET com Bearer Token

```php
use Puzl\PzRequest\Factory\PzRequestFactory;

$response = PzRequestFactory::make()
    ->endpoint('https://api.example.com/me')
    ->withBearer($token)
    ->get();

$me = $response->getArrayContent();
```

### 2. POST com body JSON

```php
$response = PzRequestFactory::make()
    ->endpoint('https://api.example.com/users')
    ->addHeader('Accept', 'application/json')
    ->addBody([
        'name' => 'Maria',
        'email' => 'maria@example.com',
    ])
    ->post();

if ($response->statusCode >= 400) {
    Log::warning('Falha ao criar usuário', [
        'status' => $response->statusCode,
        'body' => $response->content,
    ]);
}
```

### 3. POST `application/x-www-form-urlencoded`

```php
$response = PzRequestFactory::make()
    ->endpoint('https://oauth.example.com/token')
    ->addHeader('Content-Type', 'application/x-www-form-urlencoded')
    ->addBody([
        'grant_type' => 'client_credentials',
        'client_id' => $id,
        'client_secret' => $secret,
    ])
    ->post();
```

> Declarar o header `Content-Type: application/x-www-form-urlencoded` ativa
> o modo `form_params` automaticamente — equivalente a chamar
> `->asFormUrlEncoded(true)`.

### 4. Upload multipart

```php
$response = PzRequestFactory::make()
    ->endpoint('https://api.example.com/files')
    ->withBearer($token)
    ->addMultiparts([
        ['name' => 'file', 'contents' => $binary, 'filename' => 'doc.pdf'],
        ['name' => 'metadata', 'contents' => json_encode(['kind' => 'invoice'])],
    ])
    ->post();
```

### 5. Tratamento de erros

```php
$response = PzRequestFactory::make()
    ->endpoint('https://api.example.com/slow')
    ->setConnectTimeout(2)
    ->setTimeout(5)
    ->get();

match (true) {
    $response->statusCode === 504 => Log::warning('Timeout: ' . $response->statusText),
    $response->statusCode === 500 => Log::error('Erro genérico: ' . $response->content),
    $response->statusCode >= 400  => Log::warning("Erro {$response->statusCode}: {$response->statusText}"),
    default                       => null,
};
```

---

## API

| Método | Descrição |
|---|---|
| `endpoint(string)` | URL completa do recurso (com schema). |
| `addHeader(name, value)` | Adiciona um header. `Content-Type: application/x-www-form-urlencoded` ativa o modo form. |
| `addHeaders(array)` | Adiciona múltiplos headers de uma vez. |
| `addQuery(name, mixed)` | Adiciona um par na query string (aceita escalar ou array). |
| `addQueries(array)` | Adiciona múltiplos pares de query. |
| `addParam(name, value)` | Param de path/route — uso pelo consumidor antes de `endpoint()`. |
| `addBody(array\|string)` | Define o body. Array → JSON ou form; string → cru. |
| `addMultipart(name, contents, ?filename)` | Adiciona um item multipart. |
| `addMultiparts(array)` | Adiciona vários itens multipart. |
| `asFormUrlEncoded(bool = true)` | Liga/desliga modo `form_params`. |
| `withBearer(token)` | Adiciona `Authorization: Bearer <token>`. |
| `withBasicAuth(user, pass)` | Configura Basic Auth via header (nunca em query). |
| `setConnectTimeout(seconds)` | Timeout de conexão TCP (default 30). |
| `setReadTimeout(seconds)` | Timeout de leitura (default 30). |
| `setTimeout(seconds)` | Timeout total (default 30). |
| `setTimeouts(seconds)` | Aplica o mesmo valor aos três timeouts. |
| `getConnectTimeout()` | Lê o timeout de conexão atual. |
| `getReadTimeout()` | Lê o timeout de leitura atual. |
| `getTimeout()` | Lê o timeout total atual. |
| `getAsFormUrlEncoded()` | Lê a flag de form-urlencoded. |
| `getBodyAsString()` | Serializa o body atual em string (JSON ou query-string). |
| `execute(PzRequestMethodEnum = GET)` | Executa a requisição. |
| `get()` / `post()` / `put()` / `patch()` / `delete()` / `head()` / `options()` | Atalhos para os 7 verbos HTTP. |
| `prepare()` | Reseta endpoint, params, queries, multiparts, body e flag form. |

`PzResponse` (DTO `final`):

| Propriedade / método | Descrição |
|---|---|
| `int $statusCode` | Código HTTP (ou 504/500 mapeados). |
| `string $statusText` | Reason phrase (ou mensagem do mapeamento). |
| `string $content` | Body bruto da resposta. |
| `string $sentBody` | Snapshot do body enviado (útil para logs/auditoria). |
| `getArrayContent(): array` | Decodifica `content` como JSON em array. Fallback `['content' => $raw]` se inválido. |
| `getSimpleXmlElement(): SimpleXMLElement\|false` | Decodifica `content` como XML, normalizando ISO-8859-1 → UTF-8. |

---

## Segurança

- **Bearer/Basic em header, nunca em query** — coberto por `PzRequestGuzzleSecurityTest`.
- **TLS habilitado por default** (`verify=true` do Guzzle).
- **Sem logging interno** — o pacote nunca grava nada. O consumidor é
  responsável por logs e por **não** registrar `Authorization`/credenciais.

---

## Observabilidade

`PzRequest` é silencioso por design. Use middlewares do seu APM
(Sentry/New Relic/Datadog) em volta de `execute()` para medir latência.

**Anti-pattern**: ignorar `statusCode` retornado e processar `getArrayContent()`
diretamente. Sempre cheque `statusCode` (ou `>= 400`) antes — `PzRequest`
nunca lança, então o seu código é a única defesa.

---

## Compatibilidade

- PHP `^8.1` (testado em 8.1, 8.2, 8.3)
- Laravel `^10 | ^11 | ^12` (auto-discovery)
- Guzzle `^7.0`

---

## Contribuindo

Ambiente local roda em **Docker (Laradock)** — todos os comandos abaixo são
executados dentro do container `laradock-workspace-1`:

```bash
docker exec -it laradock-workspace-1 bash
cd /var/www/puzl/PzRequest

composer install
composer test            # roda unit + features
XDEBUG_MODE=coverage composer test:coverage   # gera coverage/ (≥ 90% obrigatório)
```

---

## Licença

Proprietária — Puzl. Uso interno.
