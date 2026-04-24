# Task 7.0: Smoke Test em Subprocess + Cobertura + README

## Informações Gerais

**Status**: 📦 Pronta para Iniciar
**Prioridade**: Alta
**Dependências**: Task 6.0
**Assignee**: Não atribuído

---

## Objetivo

Fechar o pacote `PzRequest` com três entregáveis que viabilizam o release `1.0.0`:

1. **Smoke test em subprocess** (`tests/Features/Bootstrap/TestSuiteSmokeTest.php`) que executa a suíte `unit` em outro processo PHP via `proc_open`, garantindo isolamento total e detectando regressões silenciosas (RF-10, RNF-05).
2. **Validação de cobertura ≥ 90%** via `composer test:coverage`, alinhado a RNF-05.
3. **README** completo com instalação, exemplos copy-paste, tabela de métodos, notas de segurança/observabilidade e anti-patterns documentados.

Após esta tarefa, o pacote está pronto para tag `1.0.0` e branches `master`/`production` (Tech Spec §11.1 fase 9).

---

## Contexto

O smoke test em subprocess é o padrão `PzPdf` (Tech Spec §13.2): protege contra dois tipos de regressão silenciosa que `composer test` direto não pega:

- Configuração de bootstrap inválida (PHPUnit consegue rodar dentro do mesmo processo, mas em CI fresh falha).
- Autoload PSR-4 quebrado quando suítes são executadas independentemente.

A validação de cobertura ≥ 90% (RNF-05) consolida o trabalho das Tasks 1.0-6.0. Caso a métrica esteja abaixo, esta tarefa **não é considerada concluída** até que testes adicionais sejam adicionados às tarefas anteriores (não nesta).

O README é o "rosto" do pacote — deve permitir onboarding ≤ 15 min (PRD §2.3) com 5 cenários copy-paste.

**Referências**:
- PRD: `tasks/prd-pzrequest/prd.md` - RF-10, RNF-05, RNF-06, §2.3
- Tech Spec: `tasks/prd-pzrequest/techspec.md` - Seções 8.1, 8.3, 9.3, 10.2, 11.1 (Fases 7-9), 13.2

---

## Escopo

### O que ESTÁ no escopo:
- ✅ `tests/Features/Bootstrap/TestSuiteSmokeTest.php` (executa `vendor/bin/phpunit -c phpunit.xml.dist --testsuite unit` via `proc_open`, exige exit code 0)
- ✅ Geração de relatório de cobertura (`composer test:coverage`) e validação ≥ 90%
- ✅ `README.md` completo com:
  - Badge de status (opcional)
  - Instalação (`composer require puzl/pzrequest`)
  - 5 exemplos copy-paste (GET com Bearer, POST JSON, POST form-urlencoded, Upload multipart, Tratamento de erros)
  - Tabela de métodos da `PzRequestInterface`
  - Notas de segurança (RN-04 / OWASP API2:2023)
  - Notas de observabilidade (consumidor é responsável por logging)
  - Anti-pattern: ignorar `statusCode` retornado
  - Seção "Migrando do `App\Library\RestRequest`" (renomeações Tech Spec §10.4)
  - Seção "Compatibilidade" (PHP 8.1+, Laravel 10/11/12, Guzzle 7.x)
  - Seção "Contribuindo" (`composer test`, `composer test:coverage`)
- ✅ Suíte completa verde (`composer test`) servindo como gate de release

### O que NÃO está no escopo:
- ❌ Tag `1.0.0` no Git (operação manual de release, fora do escopo da implementação)
- ❌ Push para branches `master`/`production` (operação manual)
- ❌ Adicionar testes para classes anteriores (se cobertura estiver abaixo de 90%, voltar à task correspondente)
- ❌ Configurar CI externo (escopo de outra tarefa de DevOps)

---

## Subtarefas

### 1. Implementar Smoke Test em Subprocess
**Descrição**: Cria teste features que abre `proc_open`, executa `vendor/bin/phpunit -c phpunit.xml.dist --testsuite unit` e verifica exit code `0`.

**Arquivos afetados**:
- `tests/Features/Bootstrap/TestSuiteSmokeTest.php` (novo)

**Implementação** (espelho do `PzPdf`):
```php
<?php

declare(strict_types=1);

namespace Puzl\PzRequest\Tests\Features\Bootstrap;

use PHPUnit\Framework\TestCase;

final class TestSuiteSmokeTest extends TestCase
{
    public function test_unit_tests_pass_via_phpunit_subprocess(): void
    {
        $repoRoot = dirname(__DIR__, 3);
        $cmd = sprintf(
            '%s -c %s --testsuite unit',
            escapeshellarg($repoRoot . '/vendor/bin/phpunit'),
            escapeshellarg($repoRoot . '/phpunit.xml.dist'),
        );

        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($cmd, $descriptor, $pipes, $repoRoot);
        self::assertIsResource($process, 'Falha ao abrir subprocess do PHPUnit');

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        self::assertSame(
            0,
            $exitCode,
            sprintf("Suíte unit falhou em subprocess.\nSTDOUT:\n%s\nSTDERR:\n%s", $stdout, $stderr),
        );
    }
}
```

> **Detalhes**:
> - Subprocess roda apenas a suíte `unit` (não `features`) para evitar recursão (este próprio teste vive em `features`).
> - Falha mostra STDOUT/STDERR para depuração rápida.
> - Caminho do `phpunit` é resolvido de forma relativa à raiz do repo (`dirname(__DIR__, 3)`).

---

### 2. Validar Cobertura ≥ 90%
**Descrição**: Rodar `composer test:coverage` e validar relatório.

**Arquivos afetados**:
- Nenhum arquivo de produção criado nesta subtarefa — apenas validação.

**Implementação**: documentar no README e em commit message:
```bash
XDEBUG_MODE=coverage composer test:coverage
# Inspecionar coverage/index.html
# Cobertura global de src/ deve ser >= 90%
```

> Se cobertura estiver abaixo, **bloquear release** e abrir tarefa adicional na fase correspondente (2.0-6.0). Não adicionar testes nesta tarefa.

---

### 3. Escrever README Completo
**Descrição**: README com 5 exemplos copy-paste, tabela de métodos, segurança, observabilidade, migração e anti-patterns.

**Arquivos afetados**:
- `README.md` (substitui o atual que tem só "PzRequest")

**Estrutura sugerida**:
```markdown
# PzRequest

Cliente HTTP REST fluente da Puzl, baseado em Guzzle. Uniforma o consumo
de APIs REST entre projetos Laravel da casa.

## Instalação

\`\`\`bash
composer require puzl/pzrequest
\`\`\`

(Auto-discovery do Service Provider em Laravel 10/11/12.)

Para customizar o adapter padrão:

\`\`\`bash
php artisan vendor:publish --tag=pzrequest-config
\`\`\`

## Início Rápido

\`\`\`php
use Puzl\PzRequest\Factory\PzRequestFactory;

$response = PzRequestFactory::make()
    ->endpoint('https://api.example.com/users')
    ->withBearer($token)
    ->addQuery('page', 1)
    ->get();

if ($response->statusCode === 200) {
    $data = $response->getArrayContent();
}
\`\`\`

## Exemplos

### 1. GET com Bearer Token
[código]

### 2. POST com body JSON
[código]

### 3. POST form-urlencoded
[código com `asFormUrlEncoded(true)` ou header `Content-Type`]

### 4. Upload multipart
[código com `addMultiparts([...])`]

### 5. Tratamento de erros
[mostrar 504 em timeout, 500 em exception, e checagem de `statusCode >= 400`]

## API

| Método | Descrição |
|--------|-----------|
| `endpoint(string)` | Define URL completa (com schema). |
| `addHeader(name, value)` | Adiciona um header. |
| `addHeaders(array)` | Adiciona múltiplos headers. |
| `addQuery(name, mixed)` | Adiciona um par na query string. |
| ... (completar com os 30 métodos) ... |

## Segurança

- Tokens (Bearer/Basic) **nunca** vão para a query string.
- TLS habilitado por default (`verify=true` no Guzzle).
- O pacote **não loga** nada — consumidor é responsável.

## Observabilidade

PzRequest é silencioso. Use middlewares do seu APM (Sentry/New Relic) em
volta de \`execute()\` para latência. **Anti-pattern**: ignorar
\`statusCode\` retornado — sempre cheque \`>= 400\` antes de processar.

## Migrando do \`App\\Library\\RestRequest\`

| Antes | Depois |
|-------|--------|
| `RestRequest`, `RestResponse`, `RestRequestFactory` | `PzRequest*`, `PzResponse`, `PzRequestFactory` |
| `$response->sent_body` | `$response->sentBody` |
| `addQuery(string, string)` | `addQuery(string, mixed)` |

## Compatibilidade

- PHP `^8.1` (testado em 8.1, 8.2, 8.3)
- Laravel `^10 | ^11 | ^12` (auto-discovery)
- Guzzle `^7.0`

## Contribuindo

\`\`\`bash
composer install
composer test            # roda unit + features
composer test:coverage   # gera coverage/ (≥ 90% obrigatório)
\`\`\`

## Licença

Proprietária — Puzl. Uso interno.
```

> Os exemplos completos das seções 1-5 devem ser preenchidos com código funcional copy-paste, não pseudocódigo.

---

### 4. Testes Unitários (do Smoke Test em si)
**Descrição**: Esta tarefa **não cria mais testes unitários** — o smoke test é a "validação meta". A validação aqui é que `composer test` continua verde com o smoke incluído.

**Arquivos de teste**:
- N/A (o `TestSuiteSmokeTest` é o próprio teste que valida o conjunto).

---

### 5. Testes de Integração (Conferência Final)
**Descrição**: Garantir que toda a suíte (`unit` + `features`) verde, incluindo o smoke test.

**Arquivos de teste**:
- N/A (validação por linha de comando).

**Cenários de teste obrigatórios**:

1. **`test_unit_tests_pass_via_phpunit_subprocess`** (já implementado em Subtarefa 1)
   - **Fluxo**: PHPUnit invoca `proc_open` da suíte unit.
   - **Expectativa**: Exit code `0`, stdout contendo "OK" ou linha de resumo positivo.

2. **`composer test` verde com smoke incluído**
   - **Fluxo**: `composer test`.
   - **Expectativa**: Exit code `0`, ambas as suítes passam.

3. **`composer test:coverage` mostra ≥ 90%**
   - **Fluxo**: `XDEBUG_MODE=coverage composer test:coverage`.
   - **Expectativa**: Linha "Lines: ≥ 90.00%" no relatório `--coverage-text`.

4. **README contém os elementos obrigatórios**
   - **Fluxo**: Inspeção visual + grep.
   - **Expectativa**: Seções "Instalação", "Início Rápido", "Exemplos" (com 5 sub-seções), "API", "Segurança", "Observabilidade", "Migrando", "Compatibilidade", "Contribuindo".

---

## Critérios de Aceitação

- [ ] `tests/Features/Bootstrap/TestSuiteSmokeTest.php` criado, executa em subprocess
- [ ] Smoke test passa (exit code 0)
- [ ] Cobertura global ≥ 90% medida via `composer test:coverage`
- [ ] `README.md` completo com instalação, 5 exemplos, tabela de métodos, segurança, observabilidade, migração, compatibilidade, contribuindo
- [ ] `composer test` verde com ambas as suítes (`unit` + `features`)
- [ ] Smoke test usa apenas `--testsuite unit` no subprocess (evita recursão)
- [ ] Tipagem PHP estrita no smoke test
- [ ] Code review aprovado
- [ ] Pacote pronto para tag `1.0.0` (operação de release fica para o pipeline humano)

---

## Entregáveis

**Arquivos Criados**:
- [ ] `tests/Features/Bootstrap/TestSuiteSmokeTest.php`

**Arquivos Modificados**:
- [ ] `README.md` (substitui versão minimal por versão completa)
- [ ] Remover `.gitkeep` de `tests/Features/Bootstrap/` se ainda existir

**Documentação**:
- [ ] README com 5 exemplos copy-paste validados (compilam mentalmente)
- [ ] Tabela de métodos completa (30 entradas alinhadas com `PzRequestInterface`)
- [ ] Seção de migração com renomeações (Tech Spec §10.4)

---

## Guia de Implementação

### Passo 1: Implementar smoke test
Crie `tests/Features/Bootstrap/TestSuiteSmokeTest.php` exatamente como na Subtarefa 1. Rode local:
```bash
vendor/bin/phpunit --testsuite features --filter TestSuiteSmokeTest
```
Espere ver dois processos PHPUnit aninhados — o externo é o teste, o interno é a suíte unit invocada por `proc_open`.

### Passo 2: Medir cobertura
```bash
XDEBUG_MODE=coverage composer test:coverage
```
Inspecione `coverage/index.html` ou a saída texto. Confirme que `src/` global está ≥ 90%. Se estiver abaixo, **não adicione testes aqui** — abra issue na task correspondente (2.0-6.0).

### Passo 3: Escrever README
Substitua `README.md` (atualmente só o título) pela estrutura da Subtarefa 3. Para os 5 exemplos, copie-os de `App\Library\RestRequest` adaptado, ou crie do zero validando que `composer test` continua verde se você compilar mentalmente cada exemplo (não há teste automatizado de README).

### Passo 4: Rodar tudo
```bash
composer test
composer test:coverage
```
Ambos devem sair com exit code 0 e cobertura ≥ 90%.

### Passo 5: (Manual) Tag e Release
Não faz parte desta task implementar tag — fica para o release pipeline humano:
```bash
git tag -a 1.0.0 -m "Release inicial PzRequest"
git push origin 1.0.0
git checkout production
git merge --ff-only main
git push origin production
```

---

## Validação

### Checklist de Validação Manual:
- [ ] `vendor/bin/phpunit --testsuite features --filter TestSuiteSmokeTest` passa (vê dois PHPUnit no `ps`)
- [ ] `composer test:coverage` mostra `Lines: 90.00%+` para `src/`
- [ ] README abre sem erros de markdown e tem todas as seções listadas
- [ ] 5 exemplos copy-paste compilam mentalmente (mesmas chamadas dos testes)
- [ ] Tabela de métodos alinha 1:1 com `PzRequestInterface`

### Comandos de Validação:
```bash
composer test
composer test:coverage
vendor/bin/phpunit --testsuite features --filter TestSuiteSmokeTest
grep -c "^### " README.md   # contar seções
```

---

## Riscos e Mitigações

| Risco | Impacto | Probabilidade | Mitigação |
|-------|---------|---------------|-----------|
| Smoke test ficar lento (executa PHPUnit duas vezes) | Baixo | Alta | Aceitável — proteção contra regressão vale o tempo extra (~2 s) |
| Caminho relativo do `vendor/bin/phpunit` quebrar em CI | Médio | Baixa | Usar `dirname(__DIR__, 3)` ancorado e `escapeshellarg` |
| Cobertura cair abaixo de 90% sem aviso | Alto | Média | `composer test:coverage` é gate manual no PR; configurar em CI futuro |
| README desatualizado em relação à API | Alto | Média | Tabela de métodos checada em code review contra `PzRequestInterface`; adicionar (futuro) teste que faz `Reflection` sobre o contrato e bate contra grep do README |
| Recursão acidental se smoke incluir `--testsuite features` | Alto | Baixa | Smoke usa `--testsuite unit` apenas (hardcoded) |

---

## Notas Adicionais

- O smoke test é deliberadamente simples — replicar o `PzPdf` linha-a-linha. Não inventar abstrações.
- Cobertura abaixo de 90%: voltar à task que tem o gap, não adicionar teste solto aqui.
- README é o "produto" do pacote tanto quanto o código. Tratar com mesmo cuidado de revisão.
- Após esta tarefa, o pacote tem **tudo** que `PzMail`/`PzPdf` têm: contrato, base, adapter, factory, provider, config publicável, suíte unit, smoke em subprocess, README. Está pronto para `1.0.0`.

---

## Histórico

| Data | Autor | Mudança |
|------|-------|---------|
| 2026-04-24 | Time Plataforma Puzl | Criação da tarefa |
