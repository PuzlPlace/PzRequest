# Tasks - PzRequest (Pacote Cliente HTTP REST)

## Visão Geral

**PRD**: `tasks/prd-pzrequest/prd.md`
**Tech Spec**: `tasks/prd-pzrequest/techspec.md`

**Resumo**: Empacotar o atual `App\Library\RestRequest` (do monolito `puzl/api`) como pacote Composer privado `puzl/pzrequest`, replicando a arquitetura **Contract + Base abstrata + Adapter + Factory + Service Provider Laravel** já consolidada em `PzMail`/`PzPdf`. As tarefas abaixo dividem o trabalho em entregáveis funcionais e incrementais, cada um com seu próprio conjunto de testes (unit + integração quando aplicável), garantindo cobertura ≥ 90% (RNF-05) e paridade funcional com o `RestRequest` original.

---

## Lista de Tarefas

### Legenda
- ✅ Concluída
- 🚧 Em Progresso
- ⏳ Aguardando
- 📦 Pronta para Iniciar

---

### Task 1.0: Bootstrap do Pacote
**Status**: ✅
**Dependências**: Nenhuma
**Descrição**: Configurar `composer.json`, `phpunit.xml.dist`, `.gitignore` e a estrutura de pastas (`src/{Contract,Adapter,Factory,Enum,Laravel}`, `config/`, `tests/{Unit,Features}`) para que `composer install` e `composer test` rodem com sucesso (suíte vazia).

**Entregáveis**:
- [x] `composer.json` com require/require-dev, autoload PSR-4, scripts `test` e `test:coverage`, `extra.laravel.providers`
- [x] `phpunit.xml.dist` com suítes `unit` e `features`
- [x] `.gitignore` alinhado ao padrão `PzMail`
- [x] Estrutura de pastas vazia criada (com `.gitkeep` quando necessário)
- [x] Teste de bootstrap validando autoload, namespaces e scripts composer

**Arquivo**: `tasks/prd-pzrequest/1_task.md`

---

### Task 2.0: Camada de Tipos e Contratos (Enums + DTO + Interface)
**Status**: ⏳
**Dependências**: Task 1.0
**Descrição**: Implementar os tipos puros do pacote: `PzRequestMethodEnum`, `PzRequestEnum` (com `tryFromName`), DTO `PzResponse` (com `getArrayContent` e `getSimpleXmlElement`) e o contrato público `PzRequestInterface`. Esta camada não tem comportamento HTTP, mas já é utilizável de forma independente.

**Entregáveis**:
- [ ] `src/Enum/PzRequestMethodEnum.php`
- [ ] `src/Factory/PzRequestEnum.php`
- [ ] `src/PzResponse.php`
- [ ] `src/Contract/PzRequestInterface.php`
- [ ] Testes unitários cobrindo enums, `PzResponse` (JSON válido/inválido, XML UTF-8/ISO-8859-1) e teste estrutural por reflexão validando paridade do contrato

**Arquivo**: `tasks/prd-pzrequest/2_task.md`

---

### Task 3.0: Base Abstrata Fluente (`PzRequestBase`)
**Status**: ⏳
**Dependências**: Task 2.0
**Descrição**: Implementar `PzRequestBase` com todo o estado fluente reutilizável por adapters: `endpoint`, headers, queries, params, body, multiparts, autenticações (`withBearer`, `withBasicAuth`), timeouts (default 30s), `asFormUrlEncoded`, `getBodyAsString` e `prepare()` que reseta estado.

**Entregáveis**:
- [ ] `src/Adapter/PzRequestBase.php` (abstrata, implementa parcialmente `PzRequestInterface`)
- [ ] Testes unitários cobrindo todos os setters retornando `self`, `prepare()` resetando estado, defaults de timeout, comportamento de `asFormUrlEncoded` via header, `getBodyAsString` para string e array

**Arquivo**: `tasks/prd-pzrequest/3_task.md`

---

### Task 4.0: Adapter Guzzle (`PzRequestGuzzle`)
**Status**: ⏳
**Dependências**: Task 3.0
**Descrição**: Implementar `PzRequestGuzzle` herdando `PzRequestBase`. Ele monta `$config`/`$options` Guzzle, executa a requisição via `GuzzleHttp\Client::request()`, mapeia erros de transporte para `PzResponse` (504/500/status real) e garante `prepare()` em `finally`. Implementa atalhos `get`/`post`/`put`/`patch`/`delete`/`head`/`options`.

**Entregáveis**:
- [ ] `src/Adapter/Guzzle/PzRequestGuzzle.php`
- [ ] Testes unitários com `MockHandler` cobrindo 2xx/3xx, 4xx (`ClientException`), 5xx (`BadResponseException`), `ConnectException` → 504, `\Throwable` → 500, body json/form_params/string, multipart, basic auth, `prepare()` em falha
- [ ] Teste de segurança garantindo que `withBearer`/`withBasicAuth` nunca vazam para query string (RN-04, RNF-02)

**Arquivo**: `tasks/prd-pzrequest/4_task.md`

---

### Task 5.0: Factory (`PzRequestFactory`)
**Status**: ⏳
**Dependências**: Task 4.0
**Descrição**: Implementar `PzRequestFactory::make()` que resolve o adapter a partir de argumento explícito, `config('pzrequest.adapter')` ou `getenv('PZREQUEST_ADAPTER')`, com fallback para Guzzle quando o adapter pedido não estiver implementado. Espelha o padrão de `PzMailFactory`.

**Entregáveis**:
- [ ] `src/Factory/PzRequestFactory.php`
- [ ] Testes unitários cobrindo: sem argumento → Guzzle, `make(GUZZLE)` → Guzzle, `make(SYMFONY_HTTP_CLIENT)`/`make(OTHERS)` → fallback Guzzle, leitura via `getenv` quando `config()` ausente, nome inválido → fallback

**Arquivo**: `tasks/prd-pzrequest/5_task.md`

---

### Task 6.0: Integração Laravel (Config + Service Provider)
**Status**: ⏳
**Dependências**: Task 5.0
**Descrição**: Criar `config/pzrequest.php` (com chave `adapter` lida de `PZREQUEST_ADAPTER`) e `PzRequestServiceProvider` no padrão dos pacotes irmãos: `mergeConfigFrom` em `register()`, `publishes` com tag `pzrequest-config` em `boot()`. Habilitar auto-discovery via `extra.laravel.providers` no `composer.json`.

**Entregáveis**:
- [ ] `config/pzrequest.php`
- [ ] `src/Laravel/PzRequestServiceProvider.php`
- [ ] Atualização de `composer.json` para registrar o provider via auto-discovery
- [ ] Testes de integração instanciando o provider sob `Illuminate\Container\Container` (sem subir Laravel completo) validando `register`/`boot` e paths publicáveis

**Arquivo**: `tasks/prd-pzrequest/6_task.md`

---

### Task 7.0: Smoke Test em Subprocess + Cobertura + README
**Status**: ⏳
**Dependências**: Task 6.0
**Descrição**: Implementar `tests/Features/Bootstrap/TestSuiteSmokeTest.php` (`proc_open` invocando `vendor/bin/phpunit -c phpunit.xml.dist --testsuite unit`, exigindo exit code 0). Validar cobertura ≥ 90% via `composer test:coverage`. Expandir `README.md` com instalação, exemplos copy-paste, tabela de métodos, notas de segurança/observabilidade. Pacote pronto para release `1.0.0`.

**Entregáveis**:
- [ ] `tests/Features/Bootstrap/TestSuiteSmokeTest.php`
- [ ] Validação de cobertura ≥ 90% (`composer test:coverage`)
- [ ] `README.md` completo com seções de instalação, uso, métodos, segurança, observabilidade e anti-patterns
- [ ] Suíte completa verde (`composer test`) servindo como gate de release `1.0.0`

**Arquivo**: `tasks/prd-pzrequest/7_task.md`

---

## Sequenciamento e Dependências

```
Task 1.0 (Bootstrap)
  ↓
Task 2.0 (Tipos + Contrato)
  ↓
Task 3.0 (Base Fluente)
  ↓
Task 4.0 (Adapter Guzzle)
  ↓
Task 5.0 (Factory)
  ↓
Task 6.0 (Laravel Provider)
  ↓
Task 7.0 (Smoke Subprocess + Cobertura + README → release 1.0.0)
```

Sequência **estritamente linear** — cada fase deixa `composer test` verde e habilita a próxima. Nenhuma tarefa pode ser executada em paralelo nesta versão (todas têm dependência direta da anterior, conforme §11.2/§11.3 da Tech Spec).

---

## Cronograma Sugerido

| Task | Descrição | Dependências | Pode Iniciar |
|------|-----------|--------------|--------------|
| 1.0 | Bootstrap do Pacote | - | Imediato |
| 2.0 | Camada de Tipos e Contratos | 1.0 | Após 1.0 |
| 3.0 | Base Abstrata Fluente | 2.0 | Após 2.0 |
| 4.0 | Adapter Guzzle | 3.0 | Após 3.0 |
| 5.0 | Factory | 4.0 | Após 4.0 |
| 6.0 | Integração Laravel | 5.0 | Após 5.0 |
| 7.0 | Smoke Test em Subprocess + README | 6.0 | Após 6.0 |

---

## Notas Importantes

- **Paridade funcional obrigatória**: o pacote deve substituir o `App\Library\RestRequest` original sem alteração de comportamento percebido. Tasks 3.0 e 4.0 carregam essa responsabilidade — qualquer divergência precisa ser justificada na Tech Spec (apenas correções de tipo e renomeação de propriedade `sent_body` → `sentBody` são esperadas).
- **Sem rede em testes**: todos os testes do adapter Guzzle usam `GuzzleHttp\Handler\MockHandler` para evitar flakiness e dependência de internet no CI.
- **Segurança não negociável**: a Task 4.0 inclui `PzRequestGuzzleSecurityTest` garantindo que `withBearer`/`withBasicAuth` nunca vazam para query string (RN-04 / OWASP API2:2023).
- **Cobertura ≥ 90%** validada na Task 7.0 via `composer test:coverage` antes do release `1.0.0`.
- **Sem implementação prematura**: cada arquivo só é criado dentro da tarefa correspondente — não antecipar nada de outras fases.
- **Referência arquitetural**: usar `PzMail` como template estrutural (não `PzPdf`, que tem `Console`/`Component`/`Templates` desnecessários aqui).

---

## Checklist de Conclusão da Funcionalidade

- [ ] Todas as 7 tarefas individuais concluídas
- [ ] Testes unitários passando (cobertura ≥ 90%)
- [ ] Testes de integração passando (incluindo smoke em subprocess)
- [ ] Code review aprovado
- [ ] README atualizado com instalação, exemplos e tabela de métodos
- [ ] `composer test` verde em PHP 8.1, 8.2 e 8.3
- [ ] Tag `1.0.0` criada e branch `production` atualizada (padrão `PzMail`)

---

## Histórico de Versões

| Versão | Data | Autor | Descrição |
|--------|------|-------|-----------|
| 1.0 | 2026-04-24 | Time Plataforma Puzl | Lista inicial de tarefas para o pacote `PzRequest` |
