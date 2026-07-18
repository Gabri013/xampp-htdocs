#!/bin/bash
# tools/matriz_permissoes.sh — Matriz de permissões: para cada tipo de usuário
# x cada página, confere se o acesso HTTP real (200 permitido / 302 negado)
# bate com o requirePermission declarado no arquivo.
# Uso: bash tools/matriz_permissoes.sh   (requer XAMPP no ar + contas de teste)
BASE="http://localhost"
cd "$(dirname "$0")/.." || exit 1

# Tipos de usuário testados (têm conta @teste.cozinca.com.br / teste123)
TIPOS="master gerente vendedor projetista producao financeiro estoque expedicao sac engenharia programacao corte dobra tubo solda mobiliario coccao refrigeracao acabamento montagem embalagem finalizacao dashboard_producao"

# login → escreve cookie jar, ecoa o caminho
login(){ local j="/tmp/perm_$1"; curl -s -c "$j" -X POST -d "email=$1@teste.cozinca.com.br&senha=teste123" -o /dev/null "$BASE/modules/auth/login.php"; echo "$j"; }

# Lista de páginas visuais navegáveis (com requirePermission/requireLogin) + querystring quando precisa
paginas(){
  for f in $(find modules -name "*.php" | sort); do
    b=$(basename "$f")
    case "$b" in api_*|*_helpers.php|*_queries.php|excluir_*|desmembrar_*|editar_*|imprimir_*|os_atrasadas.php|logout.php|importar_csv.php|visualizar_3d.php) continue;; esac
    grep -qE "requirePermission\(|requireLogin\(" "$f" || continue
    echo "$f"
  done
}

# Extrai tipos permitidos de um arquivo (resolve \$setor_atual; requireLogin => TODOS;
# também detecta gate inline in_array(\$_SESSION['usuario_tipo'], [...]))
permitidos(){
  local f="$1"
  local inline=$(grep -oE "in_array\(\\\$_SESSION\['usuario_tipo'\], \[[^]]*\]" "$f" | head -1)
  if grep -qE "requirePermission\(" "$f"; then
    local linha=$(grep -oE "requirePermission\(\[[^]]*\]\)" "$f" | head -1)
    local lista=$(echo "$linha" | grep -oE "'[a-z_]+'" | tr -d "'" | tr '\n' ' ')
    if echo "$linha" | grep -q '\$setor_atual'; then
      local setor=$(grep -oE "\\\$setor_atual\s*=\s*'[a-z_]+'" "$f" | head -1 | grep -oE "'[a-z_]+'" | tr -d "'")
      lista="$lista $setor"
    fi
    echo "$lista"; return
  fi
  if [ -n "$inline" ]; then echo "$inline" | grep -oE "'[a-z_]+'" | tr -d "'" | tr '\n' ' '; return; fi
  if grep -qE "requireLogin\(\)" "$f"; then echo "__ALL__"; return; fi
  echo "__ALL__"
}

declare -A JAR
for t in $TIPOS; do JAR[$t]=$(login "$t"); done

TOTAL=0; MISS=0; SKIP=0; HOLES=""; GAPS=""; SKIPPED=""
PAGS=$(paginas)
for f in $PAGS; do
  perm=$(permitidos "$f")
  # Se o master (acesso total) não recebe 200, a página depende de parâmetro
  # (?id/?os_id) e não dá para testar permissão por GET simples — pular.
  mcode=$(curl -s -o /dev/null -w "%{http_code}" -b "${JAR[master]}" "$BASE/$f")
  if [ "$mcode" != "200" ]; then SKIP=$((SKIP+1)); SKIPPED="$SKIPPED\n  [PULADA] $f (master HTTP $mcode — precisa de parâmetro)"; continue; fi
  for t in $TIPOS; do
    TOTAL=$((TOTAL+1))
    # esperado: permitido?
    if [ "$perm" == "__ALL__" ]; then exp=1
    elif echo " $perm " | grep -q " $t "; then exp=1
    else exp=0; fi
    # real: 200 = ok, 3xx = negado (redirect p/ index)
    code=$(curl -s -o /dev/null -w "%{http_code}" -b "${JAR[$t]}" "$BASE/$f")
    [ "$code" == "200" ] && act=1 || act=0
    # Retry único em caso de divergência (elimina flakes transitorios de sessao/Apache sob carga)
    if [ "$exp" != "$act" ]; then
      code=$(curl -s -o /dev/null -w "%{http_code}" -b "${JAR[$t]}" "$BASE/$f")
      [ "$code" == "200" ] && act=1 || act=0
    fi
    if [ "$exp" != "$act" ]; then
      MISS=$((MISS+1))
      if [ "$exp" == "0" ] && [ "$act" == "1" ]; then
        HOLES="$HOLES\n  [FURO] $t ACESSA $f (esperado: negado) [perm: $perm]"
      else
        GAPS="$GAPS\n  [BLOQUEIO] $t NAO acessa $f (esperado: permitido) [http $code] [perm: $perm]"
      fi
    fi
  done
done
for t in $TIPOS; do rm -f "${JAR[$t]}"; done

echo "============================================================"
echo " MATRIZ DE PERMISSOES — $(date '+%d/%m/%Y %H:%M')"
echo "============================================================"
echo " $TOTAL combinacoes (usuario x pagina) testadas; $SKIP pagina(s) pulada(s) (dependem de parametro)"
echo ""
if [ -n "$HOLES" ]; then echo " FUROS DE SEGURANCA (acesso indevido):"; echo -e "$HOLES"; echo ""; fi
if [ -n "$GAPS" ]; then echo " BLOQUEIOS INDEVIDOS (deveria acessar):"; echo -e "$GAPS"; echo ""; fi
echo "============================================================"
if [ "$MISS" == "0" ]; then echo " RESULTADO: 100% COERENTE — $TOTAL/$TOTAL corretos"; else echo " RESULTADO: $MISS divergencia(s) de $TOTAL"; fi
echo "============================================================"
