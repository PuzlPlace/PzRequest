# PRD - PzRequest (Pacote Cliente HTTP REST)

## 1. Visão Geral

### 1.1 Contexto
A Puzl mantém um cliente HTTP fluente chamado `RestRequest` dentro do monolito `puzl/api` (em `app/Library/RestRequest`). Esse código é replicado por cópia em outros projetos sempre que precisa ser reutilizado, gerando divergência de versões, ausência de testes automatizados e custo de manutenção. Os pacotes `PzMail` e `PzPdf` já consolidaram um padrão Puzl de "biblioteca instalável via Composer" com Factory + Contract + Adapter + Service Provider Laravel + suíte PHPUnit.

### 1.2 Problema
Não existe hoje um cliente HTTP unificado, versionado e testado para os projetos Puzl. Cada serviço acaba duplicando o código de `RestRequest`, sem garantia de paridade comportamental, sem testes automatizados e sem evolução centralizada (ex.: novos timeouts, novas autenticações, correções de bugs). Isso gera regressões silenciosas e atrasa integrações com APIs de parceiros.

### 1.3 Solução Proposta
Publicar `PzRequest` como pacote Composer privado (`puzl/pzrequest`) seguindo o mesmo padrão arquitetural de `PzMail` e `PzPdf`: contrato único, base abstrata fluente, adapter inicial baseado em Guzzle, factory configurável e Service Provider para Laravel. O pacote oferece a mesma API do `RestRequest` atual (renomeada para `PzRequest`) com cobertura de testes alta para impedir regressões.

---

## 2. Objetivos

### 2.1 Objetivos de Negócio
- Eliminar duplicação de código HTTP entre projetos Puzl.
- Reduzir o tempo de integração com novas APIs de parceiros.
- Estabelecer base reutilizável para futuras políticas (timeouts, segurança, observabilidade).

### 2.2 Objetivos do Usuário
- Permitir que devs Laravel consumam APIs REST com uma API fluente, previsível e documentada.
- Garantir que upgrades do pacote não quebrem integrações existentes.

### 2.3 Critérios de Sucesso
- Pacote instalável em ≥ 3 projetos Puzl em até 30 dias após release.
- Cobertura de testes ≥ 90% nas classes de `src/`.
- 100% dos métodos do `RestRequest` original suportados (paridade funcional).
- Zero regressão na suíte CI (`composer test`) ao longo das versões patch.
- Tempo médio de onboarding em novo projeto ≤ 15 minutos.

---

## 3. Requisitos Funcionais

### RF-01: Construção fluente de requisições
**Descrição**: O pacote deve oferecer API fluente para configurar endpoint, headers, query string, body (array ou string), multipart e parâmetros.

**Critérios de Aceitação**:
- [ ] Métodos `endpoint`, `addHeader(s)`, `addQuery(ies)`, `addBody`, `addMultipart(s)`, `addParam` retornam a própria instância.
- [ ] Valores configurados são preservados até a execução.
- [ ] `prepare()` reseta endpoint, params, queries, multiparts, body e flag form-urlencoded.

**Prioridade**: Alta

### RF-02: Execução de métodos HTTP
**Descrição**: O pacote deve executar requisições HTTP padrão e expor atalhos por método.

**Critérios de Aceitação**:
- [ ] Suporta `GET`, `POST`, `PUT`, `PATCH`, `DELETE`, `OPTIONS`, `HEAD`.
- [ ] Atalhos `get()`, `post()`, `put()`, `patch()`, `delete()` chamam `execute()` com o método correto.
- [ ] `execute()` aceita um enum de método como argumento.

**Prioridade**: Alta

### RF-03: Resposta padronizada
**Descrição**: Toda requisição retorna um objeto de resposta padronizado.

**Critérios de Aceitação**:
- [ ] Resposta expõe `statusCode`, `statusText`, `content` e `sentBody`.
- [ ] Método `getArrayContent()` decodifica JSON; quando inválido, retorna `['content' => <conteúdo>]`.
- [ ] Método `getSimpleXmlElement()` decodifica XML, normalizando codificação para UTF-8.

**Prioridade**: Alta

### RF-04: Autenticação
**Descrição**: O pacote deve suportar autenticações comuns sem expor credenciais em URL.

**Critérios de Aceitação**:
- [ ] `withBearer(token)` adiciona header `Authorization: Bearer <token>`.
- [ ] `withBasicAuth(user, pass)` configura autenticação básica via mecanismo nativo do adapter.
- [ ] Credenciais nunca são adicionadas à query string.

**Prioridade**: Alta

### RF-05: Timeouts configuráveis
**Descrição**: O pacote deve permitir configurar tempos de conexão, leitura e timeout total.

**Critérios de Aceitação**:
- [ ] `setConnectTimeout`, `setReadTimeout`, `setTimeout` aceitam segundos (int).
- [ ] `setTimeouts(seg)` aplica o mesmo valor aos três.
- [ ] Valor padrão é 30 segundos.
- [ ] Getters retornam o valor configurado.

**Prioridade**: Alta

### RF-06: Tratamento de erros mapeado
**Descrição**: Erros de transporte e HTTP devem ser convertidos em objetos de resposta consistentes (não devem lançar exceções para o consumidor).

**Critérios de Aceitação**:
- [ ] Erros 4xx/5xx do servidor preservam `statusCode` e `statusText` reais.
- [ ] Timeout de conexão retorna `statusCode = 504` com texto contendo o tempo configurado.
- [ ] Exceção genérica retorna `statusCode = 500` com texto "Uncategorised exception occurred." e `content` com a mensagem.
- [ ] Em qualquer caminho, `prepare()` é chamado ao final para reuso seguro da instância.

**Prioridade**: Alta

### RF-07: Body como JSON ou form-urlencoded
**Descrição**: O pacote deve enviar body como JSON por padrão e como `application/x-www-form-urlencoded` quando solicitado.

**Critérios de Aceitação**:
- [ ] Sem flag, body array é enviado como JSON.
- [ ] `asFormUrlEncoded(true)` ou header `Content-Type: application/x-www-form-urlencoded` força envio form-encoded.
- [ ] Body string é enviado como corpo bruto.
- [ ] `getBodyAsString()` retorna o body serializado.

**Prioridade**: Alta

### RF-08: Factory e seleção de adapter
**Descrição**: A criação da instância deve ocorrer por meio de uma Factory com enum de adapters.

**Critérios de Aceitação**:
- [ ] `PzRequestFactory::make()` retorna implementação padrão (Guzzle).
- [ ] Aceita enum `PzRequestEnum` para escolher o adapter.
- [ ] Adapters não implementados retornam fallback para o adapter padrão.

**Prioridade**: Alta

### RF-09: Integração com Laravel
**Descrição**: O pacote deve expor Service Provider e arquivo de configuração publicável.

**Critérios de Aceitação**:
- [ ] Service Provider registrado via auto-discovery (`extra.laravel.providers`).
- [ ] `config/pzrequest.php` publicável com tag `pzrequest-config`.
- [ ] Configuração permite definir adapter padrão via `PZREQUEST_ADAPTER`.

**Prioridade**: Média

### RF-10: Suíte de testes automatizada
**Descrição**: O pacote deve possuir suíte PHPUnit dividida em `unit` e `features`, com smoke test garantindo execução em subprocess.

**Critérios de Aceitação**:
- [ ] `composer test` executa todas as suítes com sucesso.
- [ ] Existe teste smoke que executa a suíte `unit` em subprocess (padrão `PzPdf`).
- [ ] Cobertura mensurada via `composer test:coverage`.

**Prioridade**: Alta

---

## 4. Requisitos Não-Funcionais

### RNF-01: Performance
**Descrição**: A latência adicional do wrapper sobre o cliente HTTP subjacente deve ser desprezível (< 5 ms por chamada em ambiente local).

### RNF-02: Segurança
**Descrição**: Credenciais (Bearer, Basic) só podem trafegar em headers; nunca em query string ou logs do pacote. Conformidade com OWASP API Security Top 10 2023 (API2: Broken Authentication).

### RNF-03: Compatibilidade
**Descrição**: Suportar PHP `^8.1` e Laravel `^10 | ^11 | ^12`, Guzzle `^7.0`.

### RNF-04: Manutenibilidade
**Descrição**: Estrutura idêntica a `PzMail`/`PzPdf` (`src/Contract`, `src/Adapter`, `src/Factory`, `src/Laravel`, `config/`, `tests/Unit`, `tests/Features`).

### RNF-05: Confiabilidade
**Descrição**: Cobertura mínima de 90% e suíte CI verde como pré-requisito para release.

### RNF-06: Documentação
**Descrição**: README com instalação, exemplos de uso e tabela de métodos suportados.

---

## 5. Casos de Uso

### 5.1 Caso de Uso Principal — Consumir API REST com Bearer Token
**Ator**: Desenvolvedor backend Laravel.

**Fluxo Principal**:
1. Dev instala o pacote via Composer.
2. Cria instância via `PzRequestFactory::make()`.
3. Configura endpoint, headers e Bearer.
4. Executa `get()` ou outro método.
5. Lê resposta com `getArrayContent()`.

**Fluxo Alternativo**:
- Dev configura `asFormUrlEncoded(true)` antes de `post()` para enviar body form-encoded.

**Fluxo de Exceção**:
- Servidor remoto fora do ar: resposta retorna `statusCode = 504` e o consumidor decide a estratégia.

### 5.2 Caso de Uso Secundário — Upload Multipart
**Ator**: Desenvolvedor backend.

**Fluxo Principal**:
1. Dev configura endpoint.
2. Adiciona múltiplos arquivos via `addMultiparts([...])`.
3. Executa `post()`.
4. Lê `statusCode` para confirmar sucesso.

---

## 6. Regras de Negócio

### RN-01: Idempotência por método (RFC 9110)
`GET`, `HEAD`, `OPTIONS`, `PUT` e `DELETE` são idempotentes. O pacote não deve introduzir efeitos colaterais que quebrem essa propriedade. `POST` e `PATCH` permanecem não idempotentes.

### RN-02: Reset automático após execução
Toda execução (sucesso ou falha) chama `prepare()` ao final, evitando vazamento de estado entre requisições reutilizando a mesma instância.

### RN-03: Mapeamento de falhas para status HTTP
Erros de transporte são convertidos em respostas com status HTTP coerente: timeout → 504, exceção genérica → 500, erro HTTP do servidor → status real recebido.

### RN-04: Credenciais fora da URL
Tokens e senhas só são enviados via headers ou mecanismo nativo de auth do adapter, nunca como query string (OWASP API2:2023).

### RN-05: Padrão de nomenclatura Puzl
Todas as classes públicas começam com o prefixo `Pz` (`PzRequest`, `PzResponse`, `PzRequestFactory`, `PzRequestEnum`, `PzRequestMethodEnum`).

---

## 7. Interface e Experiência do Usuário

### 7.1 Wireframes/Mockups
Não aplicável — pacote backend sem UI. A "interface" é a API pública das classes.

### 7.2 Fluxo de Navegação
Não aplicável.

### 7.3 Diretrizes de UX (Developer Experience)
- API fluente com encadeamento previsível (todos os setters retornam `self`).
- Mensagens de erro padronizadas e em inglês técnico no `statusText`.
- README com exemplos copy-paste para os 5 cenários mais comuns.
- Acessibilidade do código: nomes de método autoexplicativos e tipos estritos (`declare(strict_types=1)`).

---

## 8. Integrações e Dependências

### 8.1 APIs Externas
- Qualquer API HTTP/HTTPS que o consumidor desejar acessar (REST, SOAP/XML, form-encoded).

### 8.2 Dependências Internas
- Projetos consumidores Puzl (ex.: `puzl/api`, integrações com parceiros).

### 8.3 Dependências Técnicas
- `php ^8.1`
- `guzzlehttp/guzzle ^7.0`
- `illuminate/support ^10.0 | ^11.0 | ^12.0`
- `phpunit/phpunit ^10.5` (dev)

---

## 9. Restrições e Limitações

### 9.1 Restrições Técnicas
- Adapter inicial restrito a Guzzle.
- Apenas requisições síncronas.
- Sem suporte nativo a streaming de download/upload nesta versão.

### 9.2 Restrições de Negócio
- Pacote privado (licença proprietária Puzl).
- Releases controlados por SemVer; mudanças quebra-contrato exigem `MAJOR`.

---

## 10. Fora do Escopo

**O que NÃO será implementado nesta versão**:
- Retry automático ou backoff exponencial.
- Circuit breaker.
- Execução assíncrona (promises, pool de requisições).
- OAuth 1.0/2.0 flows automáticos.
- Logging estruturado integrado.
- Suporte a HTTP/2 server push ou WebSockets.
- Cache de respostas.
- Mock server embutido para testes do consumidor.

---

## 11. Riscos e Mitigações

| Risco | Impacto | Probabilidade | Mitigação |
|-------|---------|---------------|-----------|
| Quebra de paridade com `RestRequest` original | Alto | Média | Testes de paridade cobrindo todos os métodos públicos |
| Atualização do Guzzle quebrar adapter | Médio | Baixa | Pinagem por major (`^7.0`) e testes em CI |
| Adoção lenta pelos times | Médio | Média | README claro + migração documentada |
| Vazamento de credenciais em logs do consumidor | Alto | Baixa | RN-04 + recomendações no README |
| Regressão silenciosa em release patch | Alto | Média | Smoke test em subprocess + cobertura ≥ 90% |

---

## 12. Anexos

### 12.1 Referências
- RFC 9110 — HTTP Semantics: https://www.rfc-editor.org/rfc/rfc9110.html
- OWASP API Security Top 10 2023: https://owasp.org/API-Security/editions/2023/en/0x11-t10/
- Pacote `PzMail` (referência arquitetural): repositório interno Puzl.
- Pacote `PzPdf` (referência arquitetural e de testes): repositório interno Puzl.
- Código-fonte original: `puzl/api` em `app/Library/RestRequest`.

### 12.2 Glossário
- **Adapter**: Implementação concreta de um cliente HTTP (ex.: Guzzle).
- **Factory**: Classe responsável por instanciar o adapter correto.
- **Contract**: Interface pública que define o comportamento exigido.
- **Idempotente**: Operação cujo efeito final é o mesmo após N execuções (RFC 9110).
- **Paridade funcional**: Capacidade de substituir o `RestRequest` original sem alteração de comportamento percebido.

---

## Histórico de Versões

| Versão | Data | Autor | Descrição |
|--------|------|-------|-----------|
| 1.0 | 2026-04-24 | Time Plataforma Puzl | Versão inicial do PRD do pacote `PzRequest` |
