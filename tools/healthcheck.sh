#!/bin/bash
# ============================================================
# healthcheck.sh — Varredura automática de saúde do ERP Cozinca
# Roda: lint de todo PHP + renderização de todas as páginas (caçando
# erros/warnings PHP e erros de SQL) + endpoints + auditoria de dados.
# Uso:  bash tools/healthcheck.sh
# Requer: Apache + MySQL do XAMPP no ar; contas de teste (senha teste123).
# ============================================================
BASE="http://localhost"
PHP="/c/xampp/php/php.exe"
MY="/c/xampp/mysql/bin/mysql.exe -u root dbcozinca -Nse"
cd "$(dirname "$0")/.." || exit 1

FALHAS=0; TESTES=0
falha(){ echo "  [FALHA] $1"; FALHAS=$((FALHAS+1)); }

login(){ local j=$(mktemp); curl -s -c "$j" -X POST -d "email=$1@teste.cozinca.com.br&senha=teste123" -o /dev/null "$BASE/modules/auth/login.php"; echo "$j"; }

# padrão de erro PHP/SQL — CASE-SENSITIVE e no formato real do PHP, para não
# casar com JS legítimo (ex.: 'warning:' minúsculo do sistema de toasts).
ERRPAT='Fatal error|Parse error|Uncaught|<b>Warning</b>|<b>Notice</b>|<b>Deprecated</b>|SQLSTATE\[|Unknown column|Call to undefined|Undefined variable|Undefined array key|Trying to access array offset|</b> on line'

# testa uma página: $1=perfil $2=caminho-relativo (com querystring)
check_page(){
  TESTES=$((TESTES+1))
  local jar=$(login "$1")
  local body=$(curl -s -b "$jar" "$BASE/$2")
  rm -f "$jar"
  local hit=$(echo "$body" | grep -oE "$ERRPAT" | head -1)
  if [ -n "$hit" ]; then
    falha "[$1] /$2  ->  $hit"
    echo "$body" | grep -E "$ERRPAT" | head -2 | sed 's/^/        /'
  fi
}

echo "============================================================"
echo " HEALTHCHECK ERP Cozinca — $(date '+%d/%m/%Y %H:%M')"
echo "============================================================"

# ── 1. LINT ──
echo ""
echo "== 1. Lint de sintaxe (todos os .php) =="
LERR=0
while IFS= read -r f; do
  o=$($PHP -l "$f" 2>&1 | grep -v "No syntax errors")
  [ -n "$o" ] && { echo "  [FALHA] $f: $o"; LERR=$((LERR+1)); FALHAS=$((FALHAS+1)); }
done < <(find modules includes api config index.php logout.php -name "*.php" 2>/dev/null)
echo "  $([ $LERR -eq 0 ] && echo 'OK — 0 erros de sintaxe' || echo "$LERR arquivo(s) com erro")"

# ── 2. RENDER — páginas simples como MASTER (acessa tudo) ──
echo ""
echo "== 2. Render de páginas (erros PHP/SQL em execução) =="
PAGS_MASTER="modules/vendas/dashboard_vendedor.php modules/vendas/index.php modules/vendas/nova_venda.php \
modules/vendas/conteudos_digitais.php modules/orcamentos/index.php modules/orcamentos/criar_orcamento.php \
modules/crm/index.php modules/crm/contatos.php modules/cadastros/clientes.php modules/cadastros/produtos.php \
modules/cadastros/usuarios.php modules/cadastros/logs_exclusao.php modules/cadastros/imprimir_tabela_produtos.php \
modules/financeiro/index.php modules/financeiro/faturamento.php modules/financeiro/contas_pagar.php \
modules/relatorios/index.php modules/admin/logs_retorno.php modules/notificacoes/index.php \
modules/os/vendedor.php modules/os/gerente.php modules/os/producao.php modules/os/projetista.php \
modules/os/dashboard_producao.php modules/os/estatisticas.php modules/os/kanban.php modules/os/scan.php \
modules/os/nova_os_independente.php modules/os/controle_expediente.php modules/os/engenharia_setor.php \
modules/os/programacao.php modules/os/corte.php modules/os/dobra.php modules/os/tubo.php modules/os/solda.php \
modules/os/mobiliario.php modules/os/coccao.php modules/os/refrigeracao.php modules/os/acabamento.php \
modules/os/montagem.php modules/os/embalagem.php modules/os/finalizacao.php modules/engenharia/index.php \
modules/projetista/index.php"
for p in $PAGS_MASTER; do check_page master "$p"; done

# ── 3. RENDER — páginas de detalhe com IDs reais ──
echo ""
echo "== 3. Render de páginas de detalhe (com IDs reais) =="
OSID=$($MY "SELECT id FROM ordens_servico WHERE status='em_producao' LIMIT 1")
OSNUM=$($MY "SELECT numero FROM ordens_servico WHERE status='em_producao' LIMIT 1")
VID=$($MY "SELECT id FROM vendas WHERE status<>'cancelada' LIMIT 1")
if [ -n "$OSID" ]; then
  check_page master "modules/os/os_detalhes.php?os_id=$OSID"
  check_page master "modules/os/imprimir_op.php?os_id=$OSID"
  check_page master "modules/os/imprimir_etiqueta.php?os_id=$OSID"
  check_page gerente "modules/os/checkup.php?os=$OSNUM"
fi
[ -n "$VID" ] && { check_page master "modules/vendas/detalhes_venda.php?id=$VID"; check_page master "modules/vendas/editar_venda.php?id=$VID"; check_page master "modules/vendas/imprimir_venda.php?id=$VID"; }

# ── 4. RENDER — cada setor abre o PRÓPRIO painel (sem erro) ──
echo ""
echo "== 4. Cada setor abre o próprio painel =="
for s in programacao corte dobra tubo solda mobiliario coccao refrigeracao acabamento montagem embalagem finalizacao; do
  check_page "$s" "modules/os/${s}.php"
done

# ── 5. ENDPOINTS respondem sem erro PHP (deslogado = 401/403, ok) ──
echo ""
echo "== 5. Endpoints api/ sem erro fatal =="
for e in realtime dashboard_data os_arquivos; do
  TESTES=$((TESTES+1))
  jar=$(login master)
  body=$(curl -s -b "$jar" "$BASE/api/${e}.php")
  rm -f "$jar"
  echo "$body" | grep -qE "$ERRPAT" && falha "api/${e}.php -> $(echo "$body" | grep -oE "$ERRPAT" | head -1)"
done

# ── 6. AUDITORIA de dados — inconsistências comuns ──
echo ""
echo "== 6. Auditoria de dados =="
TESTES=$((TESTES+1))
ORFAS=$($MY "SELECT COUNT(*) FROM ordens_servico WHERE etapa_atual='' OR etapa_atual IS NULL")
[ "$ORFAS" != "0" ] && falha "O.S. com etapa_atual vazia: $ORFAS (rodar reparo)"
TESTES=$((TESTES+1))
VSEMOS=$($MY "SELECT COUNT(*) FROM vendas v WHERE v.status<>'cancelada' AND NOT EXISTS (SELECT 1 FROM ordens_servico o WHERE o.venda_id=v.id)")
[ "$VSEMOS" != "0" ] && echo "  [INFO] vendas ativas sem O.S.: $VSEMOS (podem ser antigas — não é erro)"
TESTES=$((TESTES+1))
CRSEMV=$($MY "SELECT COUNT(*) FROM contas_receber cr WHERE NOT EXISTS (SELECT 1 FROM vendas v WHERE v.id=cr.venda_id)")
[ "$CRSEMV" != "0" ] && falha "contas_receber órfãs (venda inexistente): $CRSEMV"

# ── RESUMO ──
echo ""
echo "============================================================"
if [ $FALHAS -eq 0 ]; then
  echo " RESULTADO: TUDO OK — $TESTES verificações, 0 falha"
else
  echo " RESULTADO: $FALHAS FALHA(S) em $TESTES verificações"
fi
echo "============================================================"
exit $FALHAS
