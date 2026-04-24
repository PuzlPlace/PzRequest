# Task 2.0: Camada de Tipos e Contratos (Enums + DTO + Interface)

## Informações Gerais

**Status**: 📦 Pronta para Iniciar
**Prioridade**: Alta
**Dependências**: Task 1.0
**Assignee**: Não atribuído

---

## Objetivo

Implementar a camada de tipos puros do pacote: dois enums (`PzRequestMethodEnum`, `PzRequestEnum`), um DTO imutável (`PzResponse`) e o contrato público (`PzRequestInterface`). Estes elementos não têm comportamento HTTP de fato, mas constituem a "linguagem" do pacote. Após esta tarefa, a interface pública estará declarada e congelada — qualquer mudança implica `MAJOR` por SemVer (RN-05 / §9.2 PRD).

---

## Contexto

Os enums e o DTO são reaproveitados em todas as próximas tarefas. O contrato `PzRequestInterface` é a "fonte da verdade" da API fluente — `PzRequestBase` (Task 3.0) e `PzRequestGuzzle` (Task 4.0) o implementarão. Há duas decisões já tomadas pela Tech Spec que precisam ser respeitadas literalmente:
1. `addQuery(string, mixed)` no contrato (correção de tipo vs. original).
2. `PzResponse::$sentBody` em camelCase (renomeação de `sent_body`, RN-05).

**Referências**:
- PRD: `tasks/prd-pzrequest/prd.md` - RF-01, RF-02, RF-03, RN-05
- Tech Spec: `tasks/prd-pzrequest/techspec.md` - Seções 1.2, 2.3, 3.1, 3.2, 3.3, 10.4, 11.1 (Fase 2)

---

## Escopo

### O que ESTÁ no escopo:
- ✅ `src/Enum/PzRequestMethodEnum.php` (enum int com 7 casos: GET=0, POST=1, PUT=2, PATCH=3, DELETE=4, OPTIONS=5, HEAD=6)
- ✅ `src/Factory/PzRequestEnum.php` (enum int com 3 casos: GUZZLE=0, SYMFONY_HTTP_CLIENT=1, OTHERS=2 + método estático `tryFromName`)
- ✅ `src/PzResponse.php` (DTO imutável `final` com 4 propriedades públicas + `getArrayContent()` + `getSimpleXmlElement()`)
- ✅ `src/Contract/PzRequestInterface.php` (interface com **toda** a API fluente listada em §3.2 da Tech Spec)
- ✅ Testes unitários cobrindo cada classe acima
- ✅ Teste estrutural por reflexão validando que o contrato declara exatamente os métodos esperados

### O que NÃO está no escopo:
- ❌ Implementação concreta da interface (Tasks 3.0 e 4.0)
- ❌ Factory funcional (Task 5.0)
- ❌ Integração com Guzzle (Task 4.0)

---

## Subtarefas

### 1. Implementar `PzRequestMethodEnum`
**Descrição**: Enum dos métodos HTTP suportados, com nomes alinhados ao verbo HTTP.

**Arquivos afetados**:
- `src/Enum/PzRequestMethodEnum.php` (novo)

**Implementação**:
```php
<?php

declare(strict_types=1);

namespace Puzl\PzRequest\Enum;

enum PzRequestMethodEnum: int
{
    case GET = 0;
    case POST = 1;
    case PUT = 2;
    case PATCH = 3;
    case DELETE = 4;
    case OPTIONS = 5;
    case HEAD = 6;
}
```

---

### 2. Implementar `PzRequestEnum` (com `tryFromName`)
**Descrição**: Enum dos adapters disponíveis. Inclui método estático `tryFromName(string $name): ?self` para resolver enum a partir de string (usado pela Factory para ler env var).

**Arquivos afetados**:
- `src/Factory/PzRequestEnum.php` (novo)

**Implementação**:
```php
<?php

declare(strict_types=1);

namespace Puzl\PzRequest\Factory;

enum PzRequestEnum: int
{
    case GUZZLE = 0;
    case SYMFONY_HTTP_CLIENT = 1;
    case OTHERS = 2;

    public static function tryFromName(string $name): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->name === $name) {
                return $case;
            }
        }
        return null;
    }
}
```

---

### 3. Implementar `PzResponse` (DTO)
**Descrição**: DTO `final` com 4 propriedades públicas readonly-like (via `__construct` promovido) e dois decodificadores.

**Arquivos afetados**:
- `src/PzResponse.php` (novo)

**Implementação**:
```php
<?php

declare(strict_types=1);

namespace Puzl\PzRequest;

use SimpleXMLElement;

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

    public function getSimpleXmlElement(): SimpleXMLElement|false
    {
        $encoding = mb_detect_encoding($this->content, 'UTF-8, ISO-8859-1', true);
        $xml = ($encoding && $encoding !== 'UTF-8')
            ? mb_convert_encoding($this->content, 'UTF-8', $encoding)
            : $this->content;
        return simplexml_load_string($xml);
    }
}
```

> Nota sobre `mixed`: o critério de aceitação proíbe `mixed`. Aqui, `$decoded` é local e não tipado em assinatura (PHP infere). Nenhum parâmetro nem retorno público usa `mixed`.

---

### 4. Implementar `PzRequestInterface`
**Descrição**: Contrato fluente com 100% dos métodos da API pública (§3.2 da Tech Spec). Todos os setters retornam `self`.

**Arquivos afetados**:
- `src/Contract/PzRequestInterface.php` (novo)

**Implementação**:
```php
<?php

declare(strict_types=1);

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
    public function head(): PzResponse;
    public function options(): PzResponse;

    public function prepare(): self;
}
```

> **Atenção**: `mixed` aparece em `addQuery` como decisão deliberada da Tech Spec (§2.3) para alinhamento com o Base. Esta exceção é a única no contrato.

---

### 5. Testes Unitários
**Descrição**: Cobrir cada arquivo com testes que validam comportamento e estrutura.

**Arquivos de teste**:
- `tests/Unit/Enum/PzRequestMethodEnumTest.php`
- `tests/Unit/Factory/PzRequestEnumTest.php`
- `tests/Unit/PzResponseTest.php`
- `tests/Unit/Contract/PzRequestInterfaceTest.php`

**Casos de teste obrigatórios**:

1. **`PzRequestMethodEnumTest::test_has_all_seven_http_methods_with_expected_int_values`**
   - **Cenário**: Iterar `PzRequestMethodEnum::cases()` comparando `name` e `value`.
   - **Expectativa**: Conter exatamente `GET=0, POST=1, PUT=2, PATCH=3, DELETE=4, OPTIONS=5, HEAD=6`.

2. **`PzRequestMethodEnumTest::test_name_matches_http_verb_for_each_case`**
   - **Cenário**: Para cada caso, comparar `$case->name` com o verbo HTTP correspondente.
   - **Expectativa**: Igualdade case-sensitive.

3. **`PzRequestEnumTest::test_has_three_adapter_cases_with_expected_int_values`**
   - **Cenário**: Iterar `PzRequestEnum::cases()`.
   - **Expectativa**: Conter `GUZZLE=0, SYMFONY_HTTP_CLIENT=1, OTHERS=2`.

4. **`PzRequestEnumTest::test_try_from_name_returns_matching_case`**
   - **Cenário**: Chamar `PzRequestEnum::tryFromName('GUZZLE')`.
   - **Expectativa**: Retornar `PzRequestEnum::GUZZLE`.

5. **`PzRequestEnumTest::test_try_from_name_returns_null_for_unknown_name`**
   - **Cenário**: Chamar `PzRequestEnum::tryFromName('NONEXISTENT')`.
   - **Expectativa**: Retornar `null`.

6. **`PzRequestEnumTest::test_try_from_name_is_case_sensitive`**
   - **Cenário**: Chamar `PzRequestEnum::tryFromName('guzzle')` (lowercase).
   - **Expectativa**: Retornar `null` (consistência com `enum->name`).

7. **`PzResponseTest::test_constructor_assigns_all_four_properties`**
   - **Cenário**: `new PzResponse(200, 'OK', 'body', 'sent')`.
   - **Expectativa**: Propriedades `statusCode/statusText/content/sentBody` correspondem aos argumentos.

8. **`PzResponseTest::test_get_array_content_decodes_valid_json_object`**
   - **Cenário**: `content = '{"a":1,"b":[2,3]}'`.
   - **Expectativa**: `getArrayContent() === ['a' => 1, 'b' => [2, 3]]`.

9. **`PzResponseTest::test_get_array_content_returns_fallback_for_invalid_json`**
   - **Cenário**: `content = '<html>error</html>'`.
   - **Expectativa**: `getArrayContent() === ['content' => '<html>error</html>']`.

10. **`PzResponseTest::test_get_array_content_returns_fallback_for_json_scalar`**
    - **Cenário**: `content = '"only-string"'` (JSON válido mas não-array).
    - **Expectativa**: `getArrayContent() === ['content' => '"only-string"']`.

11. **`PzResponseTest::test_get_simple_xml_element_parses_utf8_content`**
    - **Cenário**: `content = '<?xml version="1.0" encoding="UTF-8"?><root><a>ok</a></root>'`.
    - **Expectativa**: Retornar `SimpleXMLElement` com `(string)$xml->a === 'ok'`.

12. **`PzResponseTest::test_get_simple_xml_element_normalizes_iso_8859_1_to_utf8`**
    - **Cenário**: Conteúdo XML com caracteres acentuados em ISO-8859-1 (`mb_convert_encoding(..., 'ISO-8859-1', 'UTF-8')`).
    - **Expectativa**: `SimpleXMLElement` carregado com texto em UTF-8 (`(string)$xml->a` com acento correto).

13. **`PzResponseTest::test_get_simple_xml_element_returns_false_for_invalid_xml`**
    - **Cenário**: `content = 'not xml'`.
    - **Expectativa**: Suprimir warnings via `libxml_use_internal_errors(true)` e validar que retorno é `false`.

14. **`PzRequestInterfaceTest::test_interface_declares_all_expected_fluent_methods`**
    - **Cenário**: Usar `ReflectionClass(PzRequestInterface::class)->getMethods()`.
    - **Expectativa**: Conjunto de nomes de método contém **exatamente** os 30 métodos listados em §3.2 da Tech Spec (nem mais, nem menos).

15. **`PzRequestInterfaceTest::test_setter_methods_return_self`**
    - **Cenário**: Inspecionar via reflexão o tipo de retorno declarado de `endpoint`, `addHeader`, `addHeaders`, `addQuery`, `addQueries`, `addParam`, `addBody`, `addMultipart`, `addMultiparts`, `asFormUrlEncoded`, `withBearer`, `withBasicAuth`, `setConnectTimeout`, `setReadTimeout`, `setTimeout`, `setTimeouts`, `prepare`.
    - **Expectativa**: Para cada um, `getReturnType()->getName() === 'self'`.

16. **`PzRequestInterfaceTest::test_http_action_methods_return_pzresponse`**
    - **Cenário**: Inspecionar via reflexão o tipo de retorno de `execute`, `get`, `post`, `put`, `patch`, `delete`, `head`, `options`.
    - **Expectativa**: `getReturnType()->getName() === 'Puzl\\PzRequest\\PzResponse'`.

17. **`PzRequestInterfaceTest::test_execute_default_method_is_get`**
    - **Cenário**: Reflexão do parâmetro `$httpMethod` de `execute`.
    - **Expectativa**: Default value é `PzRequestMethodEnum::GET`.

**Cobertura mínima**: ≥ 95% para `PzResponse` e enums (sem ramos complexos).

---

### 6. Testes de Integração
**Descrição**: Não há integração externa nesta tarefa (só tipos puros). A "integração" desta camada é validada implicitamente quando Tasks 3.0+ implementarem o contrato.

**Arquivos de teste**:
- N/A nesta tarefa.

**Cenários de teste obrigatórios**:
1. **Suíte completa verde**
   - **Fluxo**: Executar `composer test`.
   - **Expectativa**: Exit code `0` somando os 17 casos novos + os 3 da Task 1.0.

---

## Critérios de Aceitação

- [ ] `src/Enum/PzRequestMethodEnum.php` criado com 7 casos
- [ ] `src/Factory/PzRequestEnum.php` criado com 3 casos + `tryFromName`
- [ ] `src/PzResponse.php` criado como `final` com 4 propriedades + 2 métodos decodificadores
- [ ] `src/Contract/PzRequestInterface.php` criado com **exatamente** 30 métodos
- [ ] Todos os 17 casos de teste passando
- [ ] Tipagem PHP estrita (`declare(strict_types=1)`) em todos os arquivos
- [ ] `mixed` usado **apenas** em `PzRequestInterface::addQuery` (decisão da Tech Spec §2.3)
- [ ] PHPDoc nos métodos de `PzRequestInterface` explicando o efeito esperado
- [ ] Cobertura ≥ 95% nas classes desta tarefa
- [ ] `composer test` verde
- [ ] Code review aprovado
- [ ] Sem erros de lint/type-check

---

## Entregáveis

**Arquivos Criados**:
- [ ] `src/Enum/PzRequestMethodEnum.php`
- [ ] `src/Factory/PzRequestEnum.php`
- [ ] `src/PzResponse.php`
- [ ] `src/Contract/PzRequestInterface.php`
- [ ] `tests/Unit/Enum/PzRequestMethodEnumTest.php`
- [ ] `tests/Unit/Factory/PzRequestEnumTest.php`
- [ ] `tests/Unit/PzResponseTest.php`
- [ ] `tests/Unit/Contract/PzRequestInterfaceTest.php`

**Arquivos Modificados**:
- [ ] Remover `.gitkeep` de `src/Enum/`, `src/Factory/`, `src/Contract/` (tornaram-se desnecessários)

**Documentação**:
- [ ] PHPDoc em `PzRequestInterface` explicando o que cada método faz e o que retorna
- [ ] PHPDoc em `PzResponse::getArrayContent` explicando o fallback para conteúdo não-JSON

---

## Guia de Implementação

### Passo 1: Criar enums
Crie `PzRequestMethodEnum` e `PzRequestEnum` exatamente conforme as Subtarefas 1 e 2. Não invente métodos extras (ex.: `from`, `tryFrom` já vêm do PHP enum nativo — não precisa redeclarar).

### Passo 2: Criar `PzResponse`
Implemente conforme Subtarefa 3. Atenção ao `final` — proíbe extensão e garante imutabilidade da assinatura.

### Passo 3: Criar `PzRequestInterface`
Copie a assinatura completa da Subtarefa 4. Não adicione nem remova métodos sem alinhar com a Tech Spec.

### Passo 4: Implementar testes
Crie um arquivo de teste por classe, com namespace `Puzl\PzRequest\Tests\<Subpasta>`. Use `PHPUnit\Framework\TestCase` como base.

### Passo 5: Validar
```bash
composer test
vendor/bin/phpunit --testsuite unit --filter PzResponse
```

---

## Validação

### Checklist de Validação Manual:
- [ ] Inspeção visual: `PzRequestInterface` tem exatamente 30 métodos
- [ ] `PzResponse::$sentBody` em camelCase (não `sent_body`)
- [ ] `PzRequestEnum::tryFromName('GUZZLE')` retorna o caso correto em REPL/teste
- [ ] Nenhuma classe importa Guzzle ainda (camada totalmente desacoplada)

### Comandos de Validação:
```bash
composer test
vendor/bin/phpunit --testsuite unit --filter PzResponse
vendor/bin/phpunit --testsuite unit --filter PzRequestInterface
```

---

## Riscos e Mitigações

| Risco | Impacto | Probabilidade | Mitigação |
|-------|---------|---------------|-----------|
| Faltar um método na interface, quebrando paridade | Alto | Média | Caso de teste 14 lista os 30 métodos esperados; falha imediatamente se algum sumir |
| `getSimpleXmlElement` lançar warning em XML inválido | Baixo | Baixa | Caso de teste 13 envolve `libxml_use_internal_errors(true)` para validar comportamento |
| `PzResponse` deixar de ser imutável (alguém remover `final`) | Médio | Baixa | Adicionar caso de teste opcional: `(new ReflectionClass(PzResponse::class))->isFinal() === true` |

---

## Notas Adicionais

- A interface aceita `mixed` apenas em `addQuery` — qualquer outro `mixed` em assinatura pública é regressão e deve ser rejeitada em code review.
- O enum `PzRequestEnum` vive em `src/Factory/` (e não em `src/Enum/`) para espelhar `PzMail` (Tech Spec §13.2).
- Não exportar Guzzle a partir desta camada — manter pureza facilita testes e abertura para outros adapters no futuro.

---

## Histórico

| Data | Autor | Mudança |
|------|-------|---------|
| 2026-04-24 | Time Plataforma Puzl | Criação da tarefa |
