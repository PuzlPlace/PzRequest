# Task 1.0: Bootstrap do Pacote

## Informações Gerais

**Status**: ✅ Concluída
**Prioridade**: Alta
**Dependências**: Nenhuma
**Assignee**: Não atribuído

---

## Objetivo

Preparar a base do pacote Composer `puzl/pzrequest`: criar `composer.json`, `phpunit.xml.dist`, `.gitignore` e a estrutura de pastas (`src/{Contract,Adapter,Factory,Enum,Laravel}`, `config/`, `tests/{Unit,Features}`) de forma que `composer install` resolva todas as dependências e `composer test` execute uma suíte vazia com sucesso. Esta tarefa entrega a "infraestrutura mínima" que habilita todas as próximas: sem ela, nenhum código de produção pode ser carregado nem testado.

---

## Contexto

O pacote segue o padrão arquitetural já consolidado em `PzMail`/`PzPdf` (mesmo grupo Puzl). Replicamos a topologia de pastas de `PzMail` (mais enxuta) porque o `PzRequest` é puramente runtime, sem comandos artisan ou templates. A configuração do PHPUnit deve permitir duas suítes (`unit` e `features`) para que a Task 7.0 consiga rodar a `unit` em subprocess. A `extra.laravel.providers` precisa ser registrada já agora para auto-discovery futuro (Task 6.0).

**Referências**:
- PRD: `tasks/prd-pzrequest/prd.md` - Seções 1.3, 8.3, RNF-04
- Tech Spec: `tasks/prd-pzrequest/techspec.md` - Seções 1.3, 10.1, 10.3, 11.1 (Fase 1)

---

## Escopo

### O que ESTÁ no escopo:
- ✅ `composer.json` com `require`, `require-dev`, autoload PSR-4 (`Puzl\PzRequest\` → `src/`, `Puzl\PzRequest\Tests\` → `tests/`), scripts `test` e `test:coverage`, `extra.laravel.providers`
- ✅ `phpunit.xml.dist` com suítes `unit` (`tests/Unit`) e `features` (`tests/Features`), bootstrap via `vendor/autoload.php`, source whitelist `src/` para cobertura
- ✅ `.gitignore` com `/vendor/`, `/.phpunit.cache/`, `/coverage/`, `composer.lock` (alinhar com `PzMail`)
- ✅ Estrutura de pastas vazia (com `.gitkeep` em pastas que ficariam sem arquivo)
- ✅ `composer install` rodando localmente
- ✅ Teste único de bootstrap validando autoload e namespaces

### O que NÃO está no escopo:
- ❌ Qualquer classe de produção (Enums, Contract, DTO, Base, Adapter, Factory, Provider) — vai nas Tasks 2.0-6.0
- ❌ Configuração `config/pzrequest.php` (Task 6.0)
- ❌ README detalhado (Task 7.0)
- ❌ Smoke test em subprocess (Task 7.0)

---

## Subtarefas

### 1. Criar `composer.json`
**Descrição**: Definir manifesto do pacote com dependências, autoload PSR-4 e auto-discovery Laravel.

**Arquivos afetados**:
- `composer.json` (novo)

**Implementação**:
```json
{
  "name": "puzl/pzrequest",
  "description": "Cliente HTTP REST fluente da Puzl (Guzzle adapter).",
  "type": "library",
  "license": "proprietary",
  "require": {
    "php": "^8.1",
    "guzzlehttp/guzzle": "^7.0",
    "illuminate/support": "^10.0|^11.0|^12.0"
  },
  "require-dev": {
    "phpunit/phpunit": "^10.5"
  },
  "autoload": {
    "psr-4": { "Puzl\\PzRequest\\": "src/" }
  },
  "autoload-dev": {
    "psr-4": { "Puzl\\PzRequest\\Tests\\": "tests/" }
  },
  "scripts": {
    "test": "phpunit",
    "test:coverage": "XDEBUG_MODE=coverage phpunit --coverage-text --coverage-html=coverage"
  },
  "extra": {
    "laravel": {
      "providers": ["Puzl\\PzRequest\\Laravel\\PzRequestServiceProvider"]
    }
  },
  "minimum-stability": "stable",
  "prefer-stable": true
}
```

---

### 2. Criar `phpunit.xml.dist`
**Descrição**: Configurar PHPUnit 10.5 com duas suítes nomeadas (`unit` e `features`) e cobertura sobre `src/`.

**Arquivos afetados**:
- `phpunit.xml.dist` (novo)

**Implementação**:
```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         cacheDirectory=".phpunit.cache">
    <testsuites>
        <testsuite name="unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="features">
            <directory>tests/Features</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>src</directory>
        </include>
    </source>
</phpunit>
```

---

### 3. Criar `.gitignore` e estrutura de pastas
**Descrição**: Adicionar entradas padrão Puzl ao `.gitignore` e criar pastas com `.gitkeep` quando necessário.

**Arquivos afetados**:
- `.gitignore` (novo ou atualizado)
- `src/Contract/.gitkeep`
- `src/Adapter/Guzzle/.gitkeep`
- `src/Factory/.gitkeep`
- `src/Enum/.gitkeep`
- `src/Laravel/.gitkeep`
- `config/.gitkeep`
- `tests/Unit/.gitkeep`
- `tests/Features/.gitkeep`

**Implementação**:
```gitignore
/vendor/
/.phpunit.cache/
/.phpunit.result.cache
/coverage/
composer.lock
```

> Os `.gitkeep` serão removidos automaticamente conforme as Tasks 2.0-7.0 adicionarem arquivos reais.

---

### 4. Testes Unitários
**Descrição**: Criar um teste mínimo de bootstrap que valida autoload PSR-4, presença dos namespaces declarados e disponibilidade das classes do PHPUnit.

**Arquivos de teste**:
- `tests/Unit/BootstrapTest.php`

**Casos de teste obrigatórios**:
1. **`test_psr4_autoload_resolves_package_namespace`**
   - **Cenário**: Executar `(new \Composer\Autoload\ClassLoader())` ou usar o autoloader já carregado pelo PHPUnit; chamar `class_exists('Puzl\\PzRequest\\Tests\\BootstrapTest')`.
   - **Expectativa**: Retornar `true` — comprova que o namespace `Puzl\PzRequest\Tests\` está registrado.

2. **`test_composer_json_declares_required_keys`**
   - **Cenário**: Ler `composer.json` da raiz via `file_get_contents`, decodificar e inspecionar.
   - **Expectativa**: Conter chaves `require.php`, `require.guzzlehttp/guzzle`, `require.illuminate/support`, `require-dev.phpunit/phpunit`, `autoload.psr-4['Puzl\\PzRequest\\']`, `extra.laravel.providers[0] === 'Puzl\\PzRequest\\Laravel\\PzRequestServiceProvider'`, `scripts.test`, `scripts.test:coverage`.

3. **`test_phpunit_xml_dist_declares_unit_and_features_suites`**
   - **Cenário**: Carregar `phpunit.xml.dist` com `simplexml_load_file`.
   - **Expectativa**: Existir `testsuite` com `name="unit"` apontando para `tests/Unit` e `testsuite` com `name="features"` apontando para `tests/Features`.

**Cobertura mínima**: N/A para esta tarefa (sem código em `src/`); o teste valida infra. Para o pacote inteiro, alvo é ≥ 90% (RNF-05), validado na Task 7.0.

---

### 5. Testes de Integração
**Descrição**: Validar que o ciclo `composer install` + `composer test` roda sem erro com a suíte recém-criada.

**Arquivos de teste**:
- Não há arquivo dedicado nesta tarefa — a "integração" é validada via comandos abaixo.

**Cenários de teste obrigatórios**:
1. **`composer install` resolve dependências**
   - **Fluxo**: Em ambiente limpo, executar `composer install`.
   - **Expectativa**: Exit code `0`, `vendor/` populado com `guzzlehttp/guzzle`, `illuminate/support`, `phpunit/phpunit`.

2. **`composer test` executa suíte vazia/bootstrap com sucesso**
   - **Fluxo**: Executar `composer test`.
   - **Expectativa**: Exit code `0`, todos os 3 testes do `BootstrapTest` passando, sem testes na suíte `features` (vazia, mas declarada).

---

## Critérios de Aceitação

- [x] `composer.json` criado com todas as chaves listadas em §10.3 da Tech Spec
- [x] `phpunit.xml.dist` declara as suítes `unit` e `features` e bootstrap via `vendor/autoload.php`
- [x] `.gitignore` ignora `vendor/`, `.phpunit.cache/`, `coverage/` e `composer.lock`
- [x] Estrutura de pastas `src/{Contract,Adapter/Guzzle,Factory,Enum,Laravel}`, `config/`, `tests/{Unit,Features}` existe
- [x] `composer install` roda sem erro
- [x] `composer test` roda sem erro (3 testes do `BootstrapTest` passam — 28 asserções)
- [x] Tipagem PHP estrita (`declare(strict_types=1)`) no arquivo de teste
- [x] Sem uso de `mixed` no teste de bootstrap
- [ ] Code review aprovado

---

## Entregáveis

**Arquivos Criados**:
- [x] `composer.json`
- [x] `phpunit.xml.dist`
- [x] `.gitignore` (atualizado)
- [x] `src/Contract/.gitkeep`
- [x] `src/Adapter/Guzzle/.gitkeep`
- [x] `src/Factory/.gitkeep`
- [x] `src/Enum/.gitkeep`
- [x] `src/Laravel/.gitkeep`
- [x] `config/.gitkeep`
- [x] `tests/Unit/BootstrapTest.php`
- [x] `tests/Features/.gitkeep`

**Arquivos Modificados**:
- `.gitignore` (já existia com defaults Laravel; reescrito alinhado ao padrão `PzMail`)

**Documentação**:
- [x] PHPDoc no `BootstrapTest` explicando o objetivo de cada caso

---

## Guia de Implementação

### Passo 1: Criar manifesto Composer
Crie `composer.json` na raiz do repositório copiando o template da Subtarefa 1. Confirme as versões de Guzzle, Laravel Support e PHPUnit conforme §1.3 da Tech Spec.

```bash
composer install
```

### Passo 2: Configurar PHPUnit
Crie `phpunit.xml.dist` na raiz copiando o XML da Subtarefa 2.

### Passo 3: Estruturar pastas e `.gitignore`
Crie cada pasta listada na Subtarefa 3 com um arquivo `.gitkeep` vazio. Crie `.gitignore` com as entradas listadas.

### Passo 4: Implementar testes de bootstrap
Crie `tests/Unit/BootstrapTest.php` estendendo `PHPUnit\Framework\TestCase` com os 3 casos descritos. Use `declare(strict_types=1)`.

### Passo 5: Validar
Rode `composer test` localmente — deve sair com 3 verdes e zero falhas.

```bash
composer install
composer test
```

---

## Validação

### Checklist de Validação Manual:
- [ ] `composer install` finaliza sem erros nem warnings
- [ ] `composer test` retorna exit code `0`
- [ ] `vendor/bin/phpunit --list-suites` mostra `unit` e `features`
- [ ] Inspeção visual do `composer.json` confirma todas as chaves obrigatórias

### Comandos de Validação:
```bash
composer install
composer test
vendor/bin/phpunit --list-suites
vendor/bin/phpunit --testsuite unit
```

---

## Riscos e Mitigações

| Risco | Impacto | Probabilidade | Mitigação |
|-------|---------|---------------|-----------|
| Versões incompatíveis de Guzzle/Laravel Support | Alto | Baixa | Pinar exatamente como §10.3 da Tech Spec; testar com `composer install` em ambiente limpo |
| `auto-discovery` falhar por typo no namespace do provider | Médio | Baixa | Caso de teste 2 valida `extra.laravel.providers[0]` literalmente |
| Suíte `features` ficar oculta por erro de configuração XML | Médio | Baixa | Caso de teste 3 valida via `simplexml_load_file` |

---

## Notas Adicionais

- Esta tarefa **não cria** classes de produção — fazer isso é antecipar Tasks 2.0-6.0 e quebra o princípio "cada tarefa entrega valor incremental e isolado".
- Os arquivos `.gitkeep` serão removidos naturalmente quando as próximas tarefas adicionarem arquivos reais nas pastas.
- A versão de PHP local pode ser 8.1, 8.2 ou 8.3 (todas suportadas conforme §12.1 da Tech Spec).
- Não rodar `composer require` no escopo desta tarefa — manifestar manualmente o `composer.json` é mais previsível e replica o padrão `PzMail`.

---

## Histórico

| Data | Autor | Mudança |
|------|-------|---------|
| 2026-04-24 | Time Plataforma Puzl | Criação da tarefa |
