#!/bin/bash
set -euo pipefail

# =============================================================================
# run-tasks-cursor.sh — Executa todas as tasks de uma pasta PRD via Cursor Agent CLI
# Uso: ./run-tasks-cursor.sh tasks/prd-fazer-tal-coisa [opções]
# =============================================================================

# Cores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
DIM='\033[2m'
BOLD='\033[1m'
NC='\033[0m'

# Defaults
SKIP_COMPLETED=true
STOP_ON_ERROR=true
MAX_TURNS=50
YOLO_MODE=true
CURSOR_MODEL=""
PRD_DIR=""
SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$SCRIPT_DIR"
FOOTER_SUPPORTED=false
FOOTER_ENABLED=false
SPINNER_INDEX=0
CURRENT_TASK_LABEL="Preparando"
RESOLVED_MODEL=""
SPINNER_FRAMES=('|' '/' '-' '\')
OUTPUT_FORMAT="text"
APPROVE_MCPS=true

# Contadores
TOTAL=0
EXECUTED=0
SKIPPED=0
FAILED=0

usage() {
  cat <<EOF
Uso: ./run-tasks-cursor.sh <pasta-prd> [opções]

Executa todas as tasks de uma pasta PRD sequencialmente via Cursor Agent CLI.

Argumentos:
  <pasta-prd>                    Caminho da pasta PRD (ex: tasks/prd-fazer-tal-coisa)

Opções:
  --no-skip-completed            Executa mesmo tasks já marcadas como [x]
  --no-stop-on-error             Continua execução mesmo se uma task falhar
  --max-turns <N>                Hint de budget para o agente (default: 50)
  --model <MODEL>                Força o modelo (ex: sonnet-4, gpt-5, sonnet-4-thinking)
  --yolo                         Executa sem aprovações (default)
  --sandboxed                    Força execução com sandbox habilitado
  --output-format <FORMAT>       Formato de saída: text | json | stream-json (default: text)
  --no-approve-mcps              Não auto-aprovar servidores MCP
  -h, --help                     Mostra esta mensagem

Exemplos:
  ./run-tasks-cursor.sh tasks/prd-fazer-tal-coisa
  ./run-tasks-cursor.sh tasks/prd-fazer-tal-coisa --no-skip-completed --max-turns 80
  ./run-tasks-cursor.sh tasks/prd-fazer-tal-coisa --model sonnet-4-thinking
  ./run-tasks-cursor.sh tasks/prd-fazer-tal-coisa --sandboxed
EOF
  exit 0
}

print_line() {
  local format="$1"
  local message="${2-}"

  if [[ "$FOOTER_SUPPORTED" == true && "$FOOTER_ENABLED" == true ]]; then
    printf '\r\033[2K'
  fi

  if [[ "$format" == "__BLANK__" ]]; then
    printf '\n'
  else
    printf "${format}\n" "$message"
  fi

  if [[ "$FOOTER_SUPPORTED" == true && "$FOOTER_ENABLED" == true ]]; then
    render_footer
  fi
}

print_blank_line() { print_line "__BLANK__"; }
log_info()    { print_line '%b' "${BLUE}[INFO]${NC} $1"; }
log_success() { print_line '%b' "${GREEN}[OK]${NC} $1"; }
log_warn()    { print_line '%b' "${YELLOW}[SKIP]${NC} $1"; }
log_error()   { print_line '%b' "${RED}[ERRO]${NC} $1"; }

footer_text() {
  local spinner="${SPINNER_FRAMES[$SPINNER_INDEX]}"
  printf '%b' "${BLUE}[RUN]${NC} ${spinner} Processo em execucao | Modelo: ${RESOLVED_MODEL} | Tarefa: ${CURRENT_TASK_LABEL}"
}

render_footer() {
  if [[ "$FOOTER_SUPPORTED" != true || "$FOOTER_ENABLED" != true ]]; then
    return
  fi

  printf '\r\033[2K%b' "$(footer_text)"
}

start_footer() {
  if [[ "$FOOTER_SUPPORTED" != true ]]; then
    return
  fi

  FOOTER_ENABLED=true
  render_footer
}

stop_footer() {
  if [[ "$FOOTER_SUPPORTED" != true || "$FOOTER_ENABLED" != true ]]; then
    return
  fi

  FOOTER_ENABLED=false
  printf '\r\033[2K'
}

advance_footer() {
  if [[ "$FOOTER_SUPPORTED" != true || "$FOOTER_ENABLED" != true ]]; then
    return
  fi

  SPINNER_INDEX=$(((SPINNER_INDEX + 1) % ${#SPINNER_FRAMES[@]}))
  render_footer
}

cleanup_footer() {
  stop_footer
}

run_cursor_agent() {
  "${CURSOR_CMD[@]}" "$PROMPT"
}

strip_ansi() {
  perl -pe 's/\e\[[0-9;]*[a-zA-Z]//g; s/\e\[?[0-9;]*[a-zA-Z]//g'
}

pick_model_interactive() {
  log_info "Carregando modelos disponíveis..."

  local raw_output
  raw_output="$(cursor agent --list-models 2>&1 | strip_ansi)" || {
    log_error "Falha ao listar modelos do Cursor."
    exit 1
  }

  local MODEL_IDS=()
  local MODEL_NAMES=()
  local MODEL_TAGS=()
  local DEFAULT_IDX=""
  local CURRENT_IDX=""

  while IFS= read -r line; do
    [[ -z "$line" ]] && continue
    [[ "$line" == Available* ]] && continue
    [[ "$line" == Tip:* ]] && continue
    [[ "$line" == Loading* ]] && continue
    [[ ! "$line" =~ ^[a-zA-Z0-9] ]] && continue
    [[ ! "$line" =~ " - " ]] && continue

    local model_id="${line%% - *}"
    local rest="${line#* - }"

    local tag=""
    local display_name="$rest"
    local tag_regex='\(([^)]+)\)'
    if [[ "$rest" =~ $tag_regex ]]; then
      tag="${BASH_REMATCH[1]}"
      display_name="${rest%%  (*}"
      display_name="${display_name%% }"
    fi

    MODEL_IDS+=("$model_id")
    MODEL_NAMES+=("$display_name")
    MODEL_TAGS+=("$tag")

    local idx=$(( ${#MODEL_IDS[@]} - 1 ))
    if [[ "$tag" == *default* ]]; then
      DEFAULT_IDX="$idx"
    fi
    if [[ "$tag" == *current* ]]; then
      CURRENT_IDX="$idx"
    fi
  done <<< "$raw_output"

  if [[ ${#MODEL_IDS[@]} -eq 0 ]]; then
    log_error "Nenhum modelo encontrado."
    exit 1
  fi

  printf '\n'
  printf '%b\n' "${BOLD}Modelos disponíveis:${NC}"
  printf '%s\n' "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

  for i in "${!MODEL_IDS[@]}"; do
    local num=$((i + 1))
    local id="${MODEL_IDS[$i]}"
    local name="${MODEL_NAMES[$i]}"
    local tag="${MODEL_TAGS[$i]}"
    local badge=""

    if [[ "$tag" == *current* ]]; then
      badge=" ${CYAN}← current${NC}"
    elif [[ "$tag" == *default* ]]; then
      badge=" ${GREEN}← default${NC}"
    fi

    if [[ -n "$badge" ]]; then
      printf "  ${BOLD}%3d${NC})  %-40s ${DIM}%s${NC}${badge}\n" "$num" "$name" "$id"
    else
      printf "  %3d)  %-40s ${DIM}%s${NC}\n" "$num" "$name" "$id"
    fi
  done

  printf '%s\n' "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

  local hint=""
  if [[ -n "$CURRENT_IDX" ]]; then
    hint="${MODEL_NAMES[$CURRENT_IDX]} (current)"
  elif [[ -n "$DEFAULT_IDX" ]]; then
    hint="${MODEL_NAMES[$DEFAULT_IDX]} (default)"
  fi

  printf '\n'
  if [[ -n "$hint" ]]; then
    printf '%b' "${BLUE}Escolha um modelo ${DIM}[Enter = ${hint}]${NC}: "
  else
    printf '%b' "${BLUE}Escolha um modelo (número ou ID): ${NC}"
  fi

  local choice
  read -r choice </dev/tty

  if [[ -z "$choice" ]]; then
    if [[ -n "$CURRENT_IDX" ]]; then
      CURSOR_MODEL="${MODEL_IDS[$CURRENT_IDX]}"
    elif [[ -n "$DEFAULT_IDX" ]]; then
      CURSOR_MODEL="${MODEL_IDS[$DEFAULT_IDX]}"
    else
      CURSOR_MODEL="${MODEL_IDS[0]}"
    fi
  elif [[ "$choice" =~ ^[0-9]+$ ]]; then
    local idx=$((choice - 1))
    if [[ $idx -ge 0 && $idx -lt ${#MODEL_IDS[@]} ]]; then
      CURSOR_MODEL="${MODEL_IDS[$idx]}"
    else
      log_error "Número inválido: $choice"
      exit 1
    fi
  else
    local found=false
    for id in "${MODEL_IDS[@]}"; do
      if [[ "$id" == "$choice" ]]; then
        CURSOR_MODEL="$choice"
        found=true
        break
      fi
    done
    if [[ "$found" == false ]]; then
      log_error "Modelo não encontrado: $choice"
      exit 1
    fi
  fi

  RESOLVED_MODEL="$CURSOR_MODEL"
  printf '\n'
  log_success "Modelo selecionado: ${BOLD}$RESOLVED_MODEL${NC}"
  printf '\n'
}

trap cleanup_footer EXIT

# --- Parse de argumentos ---
while [[ $# -gt 0 ]]; do
  case "$1" in
    --no-skip-completed)
      SKIP_COMPLETED=false
      shift
      ;;
    --no-stop-on-error)
      STOP_ON_ERROR=false
      shift
      ;;
    --max-turns)
      MAX_TURNS="$2"
      shift 2
      ;;
    --model)
      CURSOR_MODEL="$2"
      shift 2
      ;;
    --yolo|--force)
      YOLO_MODE=true
      shift
      ;;
    --sandboxed)
      YOLO_MODE=false
      shift
      ;;
    --output-format)
      OUTPUT_FORMAT="$2"
      shift 2
      ;;
    --no-approve-mcps)
      APPROVE_MCPS=false
      shift
      ;;
    -h|--help)
      usage
      ;;
    -*)
      log_error "Flag desconhecida: $1"
      usage
      ;;
    *)
      if [[ -z "$PRD_DIR" ]]; then
        PRD_DIR="$1"
      else
        log_error "Argumento extra inesperado: $1"
        usage
      fi
      shift
      ;;
  esac
done

# --- Validação ---
if [[ -z "$PRD_DIR" ]]; then
  log_error "Pasta PRD não informada."
  usage
fi

PRD_DIR="${PRD_DIR%/}"

if [[ "$PRD_DIR" != /* ]]; then
  PRD_DIR="$REPO_ROOT/$PRD_DIR"
fi

PRD_DIR="${PRD_DIR%/}"

if [[ ! -d "$PRD_DIR" ]]; then
  log_error "Pasta não encontrada: $PRD_DIR"
  exit 1
fi

for required_file in tasks.md prd.md techspec.md; do
  if [[ ! -f "$PRD_DIR/$required_file" ]]; then
    log_error "Arquivo obrigatório não encontrado: $PRD_DIR/$required_file"
    exit 1
  fi
done

# --- Verificar que Cursor CLI está disponível ---
if ! command -v cursor &> /dev/null; then
  log_error "Cursor CLI não encontrado. Verifique se o Cursor está instalado e no PATH."
  exit 1
fi

if [[ -t 1 ]]; then
  FOOTER_SUPPORTED=true
fi

# --- Seleção interativa de modelo ---
if [[ -z "$CURSOR_MODEL" ]]; then
  if [[ -t 0 ]]; then
    pick_model_interactive
  else
    RESOLVED_MODEL="default-do-cursor"
  fi
else
  RESOLVED_MODEL="$CURSOR_MODEL"
fi

# --- Descobrir tasks ---
TASK_FILES=()
for f in "$PRD_DIR"/*_task.md; do
  [[ -f "$f" ]] || continue
  basename_f=$(basename "$f")
  if [[ "$basename_f" =~ ^[0-9]+_task\.md$ ]]; then
    TASK_FILES+=("$f")
  fi
done

if [[ ${#TASK_FILES[@]} -eq 0 ]]; then
  log_error "Nenhuma task encontrada em $PRD_DIR (padrão: N_task.md)"
  exit 1
fi

# Ordenar numericamente
IFS=$'\n' TASK_FILES=($(for f in "${TASK_FILES[@]}"; do echo "$f"; done | sort -t/ -k2 -V))
unset IFS

TOTAL=${#TASK_FILES[@]}
log_info "Encontradas $TOTAL task(s) em $PRD_DIR"
if [[ "$YOLO_MODE" == true ]]; then
  log_warn "Modo yolo ativo: Cursor Agent sem aprovações"
else
  log_info "Modo sandboxed ativo: Cursor Agent com sandbox"
fi
log_info "Modelo efetivo: $RESOLVED_MODEL"
print_blank_line

# --- Função para verificar se task está completa ---
is_task_completed() {
  local task_num="$1"
  grep -qE "^[[:space:]]*-[[:space:]]*\[x\][[:space:]]*${task_num}\.0" "$PRD_DIR/tasks.md" 2>/dev/null
}

# --- Loop principal ---
for task_file in "${TASK_FILES[@]}"; do
  basename_f=$(basename "$task_file")
  task_num="${basename_f%%_task.md}"
  CURRENT_TASK_LABEL="Task ${task_num} (${basename_f})"
  SPINNER_INDEX=0

  print_line '%s' "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  log_info "Task $task_num — $task_file"

  # Check se já completa
  if [[ "$SKIP_COMPLETED" == true ]] && is_task_completed "$task_num"; then
    log_warn "Task $task_num já completa — pulando"
    ((SKIPPED++))
    print_blank_line
    continue
  fi

  # Montar prompt
  PROMPT=$(cat <<PROMPT_EOF
Voce e um assistente IA responsavel por implementar a task documentada abaixo no repositorio atual.

Ative e siga a skill \`executar-task\`.
Leia e siga as regras locais em \`AGENTS.md\` e \`CLAUDE.md\`.
Leia obrigatoriamente o arquivo da task, o \`prd.md\` e o \`techspec.md\` antes de implementar.
Use as skills locais adicionais quando fizer sentido para UI, migracao, docs, QA, review ou bugfix.
Ao final, execute uma revisao final com a skill \`task-review\`.

Arquivos da tarefa:
- PRD: ${PRD_DIR}/prd.md
- Tech Spec: ${PRD_DIR}/techspec.md
- Tasks: ${PRD_DIR}/tasks.md
- Task file: ${PRD_DIR}/${task_num}_task.md

Regras operacionais:
- Inicie a implementacao logo apos o planejamento.
- Marque a task como completa em tasks.md apenas quando a implementacao e a revisao final estiverem prontas.
- Se \`npm run lint\` ou \`npm run build\` falharem por problemas preexistentes fora do escopo, registre isso claramente na resposta final.
- Use o budget de iteracoes apenas como hint de objetividade. Budget sugerido: ${MAX_TURNS}.
PROMPT_EOF
)

  # Montar comando cursor agent
  CURSOR_CMD=(
    cursor
    agent
    --print
    --trust
    --workspace "$REPO_ROOT"
    --output-format "$OUTPUT_FORMAT"
  )

  if [[ "$YOLO_MODE" == true ]]; then
    CURSOR_CMD+=(--yolo)
  else
    CURSOR_CMD+=(--sandbox enabled)
  fi

  if [[ "$APPROVE_MCPS" == true ]]; then
    CURSOR_CMD+=(--approve-mcps)
  fi

  if [[ -n "$CURSOR_MODEL" ]]; then
    CURSOR_CMD+=(--model "$CURSOR_MODEL")
  fi

  log_info "Executando cursor agent para task $task_num com modelo $RESOLVED_MODEL..."
  print_blank_line

  # Executar
  set +e
  run_cursor_agent
  exit_code=$?
  set -e

  if [[ $exit_code -eq 0 ]]; then
    log_success "Task $task_num concluída com sucesso"
    ((EXECUTED++))
  else
    log_error "Task $task_num falhou (exit code: $exit_code)"
    ((FAILED++))

    if [[ "$STOP_ON_ERROR" == true ]]; then
      log_error "Interrompendo execucao (use --no-stop-on-error para continuar)"
      break
    fi
  fi

  print_blank_line
done

# --- Resumo ---
print_blank_line
print_line '%s' "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
print_line '%b' "${BLUE}RESUMO${NC}"
print_line '%s' "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
print_line '%b' "  Total:      $TOTAL"
print_line '%b' "  Executadas: ${GREEN}$EXECUTED${NC}"
print_line '%b' "  Puladas:    ${YELLOW}$SKIPPED${NC}"
print_line '%b' "  Falhas:     ${RED}$FAILED${NC}"
print_line '%s' "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if [[ $FAILED -gt 0 ]]; then
  exit 1
fi
