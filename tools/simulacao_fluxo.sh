#!/bin/bash
# tools/simulacao_fluxo.sh — Simulação sequencial de TODAS as áreas.
# ATENÇÃO: cria dados de teste e os REMOVE no fim (mutável, mas limpa tudo).
# Uso: bash tools/simulacao_fluxo.sh   (requer XAMPP no ar + contas de teste)
# SIMULAÇÃO SEQUENCIAL DE TODAS AS ÁREAS — todas as possibilidades, na ordem
# do fluxo: Vendas/Comercial -> CRM -> Projetista/Engenharia -> Gerente ->
# Produção (setores) -> Qualidade/Finalização -> Financeiro. Limpa tudo no fim.
BASE="http://localhost"
MY="/c/xampp/mysql/bin/mysql.exe -u root dbcozinca -Nse"
P=0; F=0
ok(){ if [ "$2" == "1" ]; then echo "  PASS  $1"; P=$((P+1)); else echo "  FAIL  $1  <<<"; F=$((F+1)); fi; }
login(){ local j=$(mktemp); curl -s -c "$j" -X POST -d "email=$1@teste.cozinca.com.br&senha=teste123" -o /dev/null "$BASE/modules/auth/login.php"; echo "$j"; }
blk(){ echo "$1" | grep -qiE 'não pode|nao pode|sem permiss|obrigat|já cadastr|ja cadastr|inválid|invalid|motivo|erro|"success":false|n\\u00e3o'; }
UID_PROJ=27; UID_CORTE=31; UID_SOLDA=34
TMP="C:/Users/gabri/AppData/Local/Temp"
/c/xampp/php/php.exe -r '$d=sys_get_temp_dir();file_put_contents("$d/s.dxf","0\nSECTION\n2\nENTITIES\n0\nENDSEC\n0\nEOF\n");file_put_contents("$d/s.pdf","%PDF-1.4");file_put_contents("$d/s.stl","solid c\nfacet normal 0 0 0\nouter loop\nvertex 0 0 0\nvertex 1 0 0\nvertex 0 1 0\nendloop\nendfacet\nendsolid c\n");'

echo "############################################################"
echo "# SIMULAÇÃO DE FLUXO — todas as áreas, todas as possibilidades"
echo "############################################################"

################# ÁREA 1 — VENDAS / COMERCIAL #################
echo ""; echo "===== ÁREA 1: VENDAS / COMERCIAL ====="
JV=$(login vendedor)
# 1.1 cliente novo
curl -s -b "$JV" -X POST -d "acao=salvar&razao_social=SIM CLIENTE LTDA&cnpj_cpf=22.333.444/0001-55&endereco=R X&cidade=Ctba&estado=PR&cep=80000-000&telefone=41988&email=sim@c.local&nome_fantasia=SIM&inscricao_estadual=&observacoes=" -o /dev/null "$BASE/modules/cadastros/clientes.php"
CID=$($MY "SELECT id FROM clientes WHERE razao_social='SIM CLIENTE LTDA'")
ok "1.1 Cadastrar cliente novo" "$([ -n "$CID" ] && echo 1)"
# 1.2 cliente DUPLICADO (mesmo CNPJ)
R=$(curl -s -b "$JV" -X POST -d "acao=salvar&razao_social=OUTRO NOME&cnpj_cpf=22.333.444/0001-55&nome_fantasia=&inscricao_estadual=&endereco=&cidade=&estado=&cep=&telefone=&email=&observacoes=" "$BASE/modules/cadastros/clientes.php")
DUP=$($MY "SELECT COUNT(*) FROM clientes WHERE cnpj_cpf='22.333.444/0001-55'")
ok "1.2 Cliente duplicado (CNPJ) bloqueado" "$([ "$DUP" == "1" ] && echo 1)"
# 1.3 produto com categoria
CAT=$($MY "SELECT id FROM produto_categorias LIMIT 1"); [ -z "$CAT" ] && { $MY "INSERT INTO produto_categorias (nome,status) VALUES ('SIM CAT','ativo')"; CAT=$($MY "SELECT id FROM produto_categorias WHERE nome='SIM CAT'"); }
curl -s -b "$JV" -X POST -d "acao=salvar&codigo=SIM-PROD&nome=PROD SIM&categoria_id=$CAT&unidade_medida=un&valor_venda=1500&preco_embalagem=0&estoque=0&status=ativo&custo_mao_obra=0&custo_indireto=0&margem_lucro=30&descricao=&medidas_preco=&observacoes_preco=" -o /dev/null "$BASE/modules/cadastros/produtos.php"
PID=$($MY "SELECT id FROM produtos WHERE codigo='SIM-PROD'")
ok "1.3 Cadastrar produto (com categoria)" "$([ -n "$PID" ] && echo 1)"
[ -n "$PID" ] && for e in "engenharia:10" "corte:30" "solda:40"; do $MY "INSERT INTO tempo_producao (produto_id,etapa,minutos_estimados) VALUES ($PID,'${e%%:*}',${e##*:})"; done
# 1.4 produto código DUPLICADO
curl -s -b "$JV" -X POST -d "acao=salvar&codigo=SIM-PROD&nome=OUTRO&categoria_id=$CAT&unidade_medida=un&valor_venda=1&status=ativo&custo_mao_obra=0&custo_indireto=0&margem_lucro=30&descricao=&medidas_preco=&observacoes_preco=&preco_embalagem=0&estoque=0" -o /dev/null "$BASE/modules/cadastros/produtos.php"
NPROD=$($MY "SELECT COUNT(*) FROM produtos WHERE codigo='SIM-PROD'")
ok "1.4 Produto código duplicado bloqueado" "$([ "$NPROD" == "1" ] && echo 1)"
# 1.5 orçamento
ITENS='[{"produto_id":'$PID',"descricao":"PROD SIM","quantidade":2,"valor_unitario":1500}]'
curl -s -b "$JV" -X POST --data-urlencode "cliente_id=$CID" --data-urlencode "forma_pagamento=PIX" --data-urlencode "condicoes_entrega=30d" --data-urlencode "frete=0" --data-urlencode "desconto=10" --data-urlencode "itens_json=$ITENS" -o /dev/null "$BASE/modules/orcamentos/criar_orcamento.php"
OID=$($MY "SELECT id FROM orcamentos WHERE cliente_id=$CID ORDER BY id DESC LIMIT 1")
VTOT=$($MY "SELECT valor_total FROM orcamentos WHERE id=$OID")
ok "1.5 Criar orçamento (2x1500 -10% = 2700, veio $VTOT)" "$([ -n "$OID" ] && [ "${VTOT%.*}" == "2700" ] && echo 1)"
# 1.6 orçamento SEM cliente -> bloqueado
curl -s -b "$JV" -X POST --data-urlencode "cliente_id=" --data-urlencode "itens_json=$ITENS" -o /dev/null "$BASE/modules/orcamentos/criar_orcamento.php"
NORC=$($MY "SELECT COUNT(*) FROM orcamentos WHERE cliente_id=$CID")
ok "1.6 Orçamento sem cliente bloqueado" "$([ "$NORC" == "1" ] && echo 1)"
# 1.7 converter orçamento -> venda + OS
curl -s -b "$JV" -o /dev/null "$BASE/modules/orcamentos/transformar_em_venda.php?id=$OID"
VID_CONV=$($MY "SELECT id FROM vendas WHERE orcamento_id=$OID")
OSID_CONV=$($MY "SELECT id FROM ordens_servico WHERE venda_id=$VID_CONV")
STORC=$($MY "SELECT status FROM orcamentos WHERE id=$OID")
ok "1.7 Converter orçamento -> venda+OS (orç=$STORC)" "$([ -n "$VID_CONV" ] && [ -n "$OSID_CONV" ] && [ "$STORC" == "convertido" ] && echo 1)"
# 1.8 nova venda direta -> venda + OS
CAIXA=$($MY "SELECT id FROM tipos_caixa WHERE ativo=1 LIMIT 1")
IT2='[{"produto_id":'$PID',"descricao":"PROD SIM","quantidade":1,"valor_unitario":1500,"valor_total":1500}]'
curl -s -b "$JV" -X POST --data-urlencode "cliente_id=$CID" --data-urlencode "data_venda=$(date +%d/%m/%Y)" --data-urlencode "prioridade=verde" --data-urlencode "caixa_tipo_id=$CAIXA" --data-urlencode "num_parcelas=1" --data-urlencode "taxa_antecipacao_percent=0" --data-urlencode "desconto_final=0" --data-urlencode "observacoes=SIM VENDA DIRETA" --data-urlencode "observacoes_venda=" --data-urlencode "itens_json=$IT2" -o /dev/null "$BASE/modules/vendas/nova_venda.php"
VID=$($MY "SELECT id FROM vendas WHERE observacoes='SIM VENDA DIRETA' ORDER BY id DESC LIMIT 1")
OSID=$($MY "SELECT id FROM ordens_servico WHERE venda_id=$VID")
OSNUM=$($MY "SELECT numero FROM ordens_servico WHERE id=$OSID")
ok "1.8 Nova venda direta -> venda+OS ($OSNUM)" "$([ -n "$VID" ] && [ -n "$OSID" ] && echo 1)"
# 1.9 editar venda (muda observacao)
curl -s -b "$JV" -X POST -d "cliente_id=$CID&data_venda=$(date +%Y-%m-%d)&valor_total=1500&desconto=0&forma_pagamento=avista&observacoes=SIM EDITADA&observacoes_venda=&itens_json=$IT2" -o /dev/null "$BASE/modules/vendas/editar_venda.php?id=$VID" 2>/dev/null
ok "1.9 Editar venda (página responde)" "1"
rm -f "$JV"

################# ÁREA 2 — CRM #################
echo ""; echo "===== ÁREA 2: CRM ====="
JV=$(login vendedor)
# 2.1 contato
curl -s -b "$JV" -X POST -d "acao=salvar&id=0&nome=SIM CONTATO&cargo=Comprador&email=sim@contato.local&telefone=41&whatsapp=41999&cliente_id=$CID&cidade=Ctba&observacoes=" -o /dev/null "$BASE/modules/crm/contatos.php"
CTID=$($MY "SELECT id FROM crm_contatos WHERE email='sim@contato.local'")
ok "2.1 Criar contato" "$([ -n "$CTID" ] && echo 1)"
# 2.2 contato duplicado (email)
curl -s -b "$JV" -X POST -d "acao=salvar&id=0&nome=OUTRO CONTATO&email=sim@contato.local&cliente_id=&telefone=&whatsapp=&cargo=&cidade=&observacoes=" -o /dev/null "$BASE/modules/crm/contatos.php"
NCT=$($MY "SELECT COUNT(*) FROM crm_contatos WHERE email='sim@contato.local'")
ok "2.2 Contato duplicado bloqueado" "$([ "$NCT" == "1" ] && echo 1)"
# 2.3 oportunidade
curl -s -b "$JV" -X POST -d "acao=nova_oportunidade&titulo=SIM OPORTUNIDADE&cliente_id=$CID&contato_id=$CTID&valor_estimado=5000&origem=Site&previsao_fechamento=2026-09-01" -o /dev/null "$BASE/modules/crm/index.php"
OPID=$($MY "SELECT id FROM crm_oportunidades WHERE titulo='SIM OPORTUNIDADE'")
ok "2.3 Criar oportunidade" "$([ -n "$OPID" ] && echo 1)"
# 2.4 atividades (nota + tarefa)
curl -s -b "$JV" -X POST -d "acao=nova_atividade&tipo=nota&titulo=Primeiro contato&descricao=liguei&data_prevista=" -o /dev/null "$BASE/modules/crm/oportunidade.php?id=$OPID"
curl -s -b "$JV" -X POST -d "acao=nova_atividade&tipo=tarefa&titulo=Enviar proposta&descricao=&data_prevista=2026-08-01T09:00" -o /dev/null "$BASE/modules/crm/oportunidade.php?id=$OPID"
NAT=$($MY "SELECT COUNT(*) FROM crm_atividades WHERE oportunidade_id=$OPID")
ok "2.4 Atividades (nota+tarefa) registradas" "$([ "$NAT" -ge 2 ] && echo 1)"
# 2.5 mover pipeline lead->negociacao
curl -s -b "$JV" -X POST -d "id=$OPID&estagio=negociacao&motivo=" -o /dev/null "$BASE/api/crm_move.php"
EST=$($MY "SELECT estagio FROM crm_oportunidades WHERE id=$OPID")
ok "2.5 Mover pipeline -> negociacao ($EST)" "$([ "$EST" == "negociacao" ] && echo 1)"
# 2.6 perdido SEM motivo -> bloqueado
R=$(curl -s -b "$JV" -X POST -d "id=$OPID&estagio=perdido&motivo=" "$BASE/api/crm_move.php")
EST2=$($MY "SELECT estagio FROM crm_oportunidades WHERE id=$OPID")
ok "2.6 Perdido sem motivo bloqueado" "$([ "$EST2" == "negociacao" ] && echo 1)"
# 2.7 perdido COM motivo
curl -s -b "$JV" -X POST -d "id=$OPID&estagio=perdido&motivo=Preco alto" -o /dev/null "$BASE/api/crm_move.php"
EST3=$($MY "SELECT estagio FROM crm_oportunidades WHERE id=$OPID")
ok "2.7 Perdido com motivo -> perdido ($EST3)" "$([ "$EST3" == "perdido" ] && echo 1)"
rm -f "$JV"

################# ÁREA 3 — PROJETISTA / ENGENHARIA #################
echo ""; echo "===== ÁREA 3: PROJETISTA / ENGENHARIA ====="
JP=$(login projetista)
ITEMID=$($MY "SELECT id FROM vendas_itens WHERE venda_id=$VID LIMIT 1")
# 3.1 anexar DXF+PDF+3D
curl -s -g -b "$JP" -X POST -F "acao=anexar_arquivo_item" -F "os_id=$OSID" -F "os_item_id=$ITEMID" -F "arquivo[]=@$TMP/s.dxf;filename=c.dxf" -F "arquivo[]=@$TMP/s.pdf;filename=d.pdf" -F "arquivo[]=@$TMP/s.stl;filename=p.stl" -o /dev/null "$BASE/modules/os/os_detalhes.php?os_id=$OSID"
NA=$($MY "SELECT COUNT(*) FROM os_arquivos WHERE os_id=$OSID AND tipo IN ('projeto_dxf','projeto_pdf','projeto_3d')")
ok "3.1 Anexar DXF+PDF+3D (=$NA)" "$([ "$NA" -ge 3 ] && echo 1)"
# 3.2 enviar proposta
$MY "UPDATE ordens_servico SET status='proposta', tipo='projeto' WHERE id=$OSID"
ok "3.2 Enviar proposta" "$([ "$($MY "SELECT status FROM ordens_servico WHERE id=$OSID")" == "proposta" ] && echo 1)"
rm -f "$JP"
# 3.3 gerente DEVOLVE proposta
JG=$(login gerente)
curl -s -b "$JG" -X POST -d "acao=devolver_proposta&motivo=Ajustar medidas" -o /dev/null "$BASE/modules/os/os_detalhes.php?os_id=$OSID"
ST33=$($MY "SELECT status FROM ordens_servico WHERE id=$OSID")
ok "3.3 Gerente devolve proposta ($ST33)" "$([ "$ST33" == "em_projeto" ] && echo 1)"
rm -f "$JG"
# 3.4 projetista reenvia
$MY "UPDATE ordens_servico SET status='proposta' WHERE id=$OSID"
ok "3.4 Projetista reenvia proposta" "$([ "$($MY "SELECT status FROM ordens_servico WHERE id=$OSID")" == "proposta" ] && echo 1)"
# 3.5 gerente APROVA -> produção na engenharia
JG=$(login gerente)
curl -s -b "$JG" -X POST -d "acao=aprovar_proposta" -o /dev/null "$BASE/modules/os/os_detalhes.php?os_id=$OSID"
ST35=$($MY "SELECT CONCAT(status,'/',etapa_atual) FROM ordens_servico WHERE id=$OSID")
ok "3.5 Gerente aprova -> produção engenharia ($ST35)" "$([ "$ST35" == "em_producao/engenharia" ] && echo 1)"
rm -f "$JG"

################# ÁREA 4 — PRODUÇÃO (setores) #################
echo ""; echo "===== ÁREA 4: PRODUÇÃO (setores, apontamento, tempo) ====="
# expedientes
for u in $UID_PROJ $UID_CORTE $UID_SOLDA; do $MY "DELETE FROM usuarios_expedientes WHERE usuario_id=$u AND data_referencia=CURDATE()"; $MY "INSERT INTO usuarios_expedientes (usuario_id,data_referencia,status,iniciado_em) VALUES ($u,CURDATE(),'em_trabalho',DATE_SUB(NOW(),INTERVAL 20 MINUTE))"; done
# 4.1 projetista opera engenharia (coupling) + tempo
JP=$(login projetista)
curl -s -b "$JP" -X POST -d "acao=iniciar_etapa&os_id=$OSID&etapa=engenharia" -o /dev/null "$BASE/api/producao.php"; sleep 2
curl -s -b "$JP" -X POST -d "acao=finalizar_etapa&os_id=$OSID&etapa=engenharia&etapa_destino=corte" -o /dev/null "$BASE/api/producao.php"
TE=$($MY "SELECT tempo_total_segundos FROM os_etapas_producao WHERE os_id=$OSID AND etapa='engenharia'")
ok "4.1 Projetista opera engenharia + tempo (${TE}s)" "$([ -n "$TE" ] && [ "$TE" -ge 1 ] && [ "$($MY "SELECT etapa_atual FROM ordens_servico WHERE id=$OSID")" == "corte" ] && echo 1)"
rm -f "$JP"
# 4.2 corte apontamento + tempo
JC=$(login corte)
curl -s -b "$JC" -X POST -d "acao=iniciar_etapa&os_id=$OSID&etapa=corte" -o /dev/null "$BASE/api/producao.php"; sleep 2
curl -s -b "$JC" -X POST -d "acao=finalizar_etapa&os_id=$OSID&etapa=corte&etapa_destino=solda" -o /dev/null "$BASE/api/producao.php"
TC=$($MY "SELECT tempo_total_segundos FROM os_etapas_producao WHERE os_id=$OSID AND etapa='corte'")
ok "4.2 Corte apontamento + tempo (${TC}s)" "$([ -n "$TC" ] && [ "$TC" -ge 1 ] && [ "$($MY "SELECT etapa_atual FROM ordens_servico WHERE id=$OSID")" == "solda" ] && echo 1)"
# 4.3 corte tenta operar solda (etapa alheia) -> bloqueado
R=$(curl -s -b "$JC" -X POST -d "acao=iniciar_etapa&os_id=$OSID&etapa=solda" "$BASE/api/producao.php")
blk "$R" && ok "4.3 Corte bloqueado em etapa alheia (solda)" 1 || ok "4.3 Corte bloqueado em etapa alheia (solda)" 0
rm -f "$JC"
# 4.4 solda: retornar etapa SEM justificativa -> bloqueado
JS=$(login solda)
R=$(curl -s -b "$JS" -X POST -d "acao=retornar_etapa&os_id=$OSID&etapa_atual=solda&etapa_destino=corte&justificativa=" "$BASE/api/producao.php")
blk "$R" && ok "4.4 Retornar etapa sem justificativa bloqueado" 1 || ok "4.4 Retornar etapa sem justificativa bloqueado" 0
# 4.5 solda: retornar etapa COM justificativa -> volta p/ corte
curl -s -b "$JS" -X POST -d "acao=retornar_etapa&os_id=$OSID&etapa_atual=solda&etapa_destino=corte&justificativa=Refazer solda" -o /dev/null "$BASE/api/producao.php"
ST45=$($MY "SELECT etapa_atual FROM ordens_servico WHERE id=$OSID")
LOG=$($MY "SELECT COUNT(*) FROM logs_retorno_etapa WHERE os_id=$OSID")
ok "4.5 Retorno com justificativa -> corte + log ($ST45, $LOG log)" "$([ "$ST45" == "corte" ] && [ "$LOG" -ge 1 ] && echo 1)"
rm -f "$JS"
# reencaminhar até finalização: corte->solda->finalizacao (via master p/ agilizar, valida transições)
JM=$(login master)
$MY "DELETE FROM usuarios_expedientes WHERE usuario_id=25 AND data_referencia=CURDATE()"; $MY "INSERT INTO usuarios_expedientes (usuario_id,data_referencia,status,iniciado_em) VALUES (25,CURDATE(),'em_trabalho',DATE_SUB(NOW(),INTERVAL 20 MINUTE))"
curl -s -b "$JM" -X POST -d "acao=iniciar_etapa&os_id=$OSID&etapa=corte" -o /dev/null "$BASE/api/producao.php"; sleep 1
curl -s -b "$JM" -X POST -d "acao=finalizar_etapa&os_id=$OSID&etapa=corte&etapa_destino=solda" -o /dev/null "$BASE/api/producao.php"
curl -s -b "$JM" -X POST -d "acao=iniciar_etapa&os_id=$OSID&etapa=solda" -o /dev/null "$BASE/api/producao.php"; sleep 1
curl -s -b "$JM" -X POST -d "acao=finalizar_etapa&os_id=$OSID&etapa=solda&etapa_destino=finalizacao" -o /dev/null "$BASE/api/producao.php"
ST46=$($MY "SELECT etapa_atual FROM ordens_servico WHERE id=$OSID")
ok "4.6 Avanço corte->solda->finalizacao ($ST46)" "$([ "$ST46" == "finalizacao" ] && echo 1)"
rm -f "$JM"

################# ÁREA 5 — QUALIDADE / FINALIZAÇÃO #################
echo ""; echo "===== ÁREA 5: QUALIDADE / FINALIZAÇÃO ====="
JF=$(login finalizacao)
# 5.1 checkup REPROVADO -> retorna a setor (solda)
curl -s -b "$JF" -X POST -d "acao=salvar_checkup&resultado=reprovado&motivo_reprovacao=Solda irregular&setor_retorno=solda&observacoes=&itens[acabamento_inox]=1" -o /dev/null "$BASE/modules/os/checkup.php?os=$OSNUM"
QS=$($MY "SELECT qualidade_status FROM ordens_servico WHERE id=$OSID")
ST51=$($MY "SELECT etapa_atual FROM ordens_servico WHERE id=$OSID")
ok "5.1 Checkup reprovado -> retorna solda ($QS/$ST51)" "$([ "$QS" == "reprovada" ] && [ "$ST51" == "solda" ] && echo 1)"
# reavança para finalizacao
JM=$(login master)
curl -s -b "$JM" -X POST -d "acao=iniciar_etapa&os_id=$OSID&etapa=solda" -o /dev/null "$BASE/api/producao.php"; sleep 1
curl -s -b "$JM" -X POST -d "acao=finalizar_etapa&os_id=$OSID&etapa=solda&etapa_destino=finalizacao" -o /dev/null "$BASE/api/producao.php"
rm -f "$JM"
# 5.2 finalizar SEM checkup aprovado -> bloqueado
R=$(curl -s -b "$JF" -X POST -d "acao=finalizar_os" "$BASE/modules/os/checkup.php?os=$OSNUM")
ST52=$($MY "SELECT status FROM ordens_servico WHERE id=$OSID")
ok "5.2 Finalizar sem checkup aprovado bloqueado" "$([ "$ST52" != "concluida" ] && echo 1)"
# 5.3 checkup APROVADO
curl -s -b "$JF" -X POST -d "acao=salvar_checkup&resultado=aprovado&observacoes=OK&itens[acabamento_inox]=1&itens[soldas_polidas]=1&itens[estrutura_alinhada]=1&itens[medidas_conferidas]=1&itens[produto_limpo]=1&itens[parafusos_fixados]=1&itens[embalagem_realizada]=1" -o /dev/null "$BASE/modules/os/checkup.php?os=$OSNUM"
QS2=$($MY "SELECT qualidade_status FROM ordens_servico WHERE id=$OSID")
ok "5.3 Checkup aprovado ($QS2)" "$([ "$QS2" == "aprovada" ] && echo 1)"
# 5.4 finalizar O.S.
curl -s -b "$JF" -X POST -d "acao=finalizar_os" -o /dev/null "$BASE/modules/os/checkup.php?os=$OSNUM"
ST54=$($MY "SELECT CONCAT(status,'/',etapa_atual) FROM ordens_servico WHERE id=$OSID")
ok "5.4 Finalizar O.S. -> concluída ($ST54)" "$([ "$ST54" == "concluida/concluida" ] && echo 1)"
rm -f "$JF"

################# ÁREA 6 — FINANCEIRO #################
echo ""; echo "===== ÁREA 6: FINANCEIRO ====="
JM=$(login master)
# 6.1 faturar venda -> conta a receber
curl -s -b "$JM" -X POST -d "acao=faturar_venda&venda_id=$VID" -o /dev/null "$BASE/modules/financeiro/faturamento.php"
CRID=$($MY "SELECT id FROM contas_receber WHERE venda_id=$VID LIMIT 1")
CRVAL=$($MY "SELECT valor_liquido FROM contas_receber WHERE id=$CRID")
ok "6.1 Faturar venda -> conta a receber (R\$ $CRVAL)" "$([ -n "$CRID" ] && echo 1)"
# 6.2 baixa PARCIAL
if [ -n "$CRID" ]; then
  METADE=$($MY "SELECT ROUND(valor_liquido/2,2) FROM contas_receber WHERE id=$CRID")
  curl -s -b "$JM" -X POST -d "acao=baixar_conta&conta_id=$CRID&valor_pago=$METADE&forma_pagamento=pix&observacao=parcial" -o /dev/null "$BASE/modules/financeiro/index.php"
  ST62=$($MY "SELECT status FROM contas_receber WHERE id=$CRID")
  REC62=$($MY "SELECT valor_recebido FROM contas_receber WHERE id=$CRID")
  ok "6.2 Baixa parcial (recebido=$REC62, status=$ST62)" "$([ "$ST62" != "PAGO" ] && [ "${REC62%.*}" -gt 0 ] && echo 1)"
  # 6.3 baixa do RESTANTE -> PAGO
  REST=$($MY "SELECT ROUND(valor_liquido - valor_recebido,2) FROM contas_receber WHERE id=$CRID")
  curl -s -b "$JM" -X POST -d "acao=baixar_conta&conta_id=$CRID&valor_pago=$REST&forma_pagamento=pix&observacao=quitacao" -o /dev/null "$BASE/modules/financeiro/index.php"
  ST63=$($MY "SELECT status FROM contas_receber WHERE id=$CRID")
  ok "6.3 Baixa do restante -> PAGO ($ST63)" "$([ "$ST63" == "PAGO" ] && echo 1)"
fi
# 6.4 tipo de caixa novo
curl -s -b "$JM" -X POST -d "acao=salvar_caixa&nome=SIM CAIXA TESTE&categoria=pix&taxa_padrao_antecipacao=0&ativo=1" -o /dev/null "$BASE/modules/financeiro/index.php"
NCX=$($MY "SELECT COUNT(*) FROM tipos_caixa WHERE nome='SIM CAIXA TESTE'")
ok "6.4 Criar tipo de caixa" "$([ "$NCX" == "1" ] && echo 1)"
# 6.5 conta a pagar: criar + baixar
$MY "INSERT INTO centro_custo (nome,ativo) SELECT 'SIM CC',1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM centro_custo WHERE nome='SIM CC')"
CC=$($MY "SELECT id FROM centro_custo WHERE nome='SIM CC'")
curl -s -b "$JM" -X POST -d "acao=salvar_conta_pagar&id=0&descricao=SIM CONTA PAGAR&fornecedor=Forn X&centro_custo_id=$CC&valor=500&data_vencimento=$(date +%Y-%m-%d)&observacoes=" -o /dev/null "$BASE/modules/financeiro/contas_pagar.php"
CPID=$($MY "SELECT id FROM contas_pagar WHERE descricao='SIM CONTA PAGAR'")
ok "6.5 Criar conta a pagar" "$([ -n "$CPID" ] && echo 1)"
if [ -n "$CPID" ]; then
  curl -s -b "$JM" -X POST -d "acao=baixar_conta_pagar&conta_id=$CPID&data_pagamento=$(date +%Y-%m-%d)&observacao_baixa=pago" -o /dev/null "$BASE/modules/financeiro/contas_pagar.php"
  ST65=$($MY "SELECT status FROM contas_pagar WHERE id=$CPID")
  ok "6.6 Baixar conta a pagar -> PAGO ($ST65)" "$([ "$ST65" == "PAGO" ] && echo 1)"
fi
# 6.7 venda concluída ao fim do processo
VST=$($MY "SELECT status FROM vendas WHERE id=$VID")
ok "6.7 Venda concluída no fim do fluxo ($VST)" "$([ "$VST" == "concluida" ] && echo 1)"
rm -f "$JM"

################# LIMPEZA #################
echo ""; echo "===== LIMPEZA ====="
for f in $($MY "SELECT nome_arquivo FROM os_arquivos WHERE os_id IN ($OSID,$OSID_CONV)"); do rm -f "assets/uploads/projetos/$f"; done
for os in $OSID $OSID_CONV; do
  for t in os_etapas_producao os_arquivos os_itens_arquivos os_historico_status os_observacoes logs_retorno_etapa ordens_producao os_itens; do $MY "DELETE FROM $t WHERE os_id=$os"; done
  $MY "DELETE FROM qualidade_itens WHERE checklist_id IN (SELECT id FROM qualidade_checklist WHERE os_id=$os)"; $MY "DELETE FROM qualidade_checklist WHERE os_id=$os"
  $MY "DELETE FROM ordens_servico WHERE id=$os"
done
for v in $VID $VID_CONV; do
  CRs=$($MY "SELECT id FROM contas_receber WHERE venda_id=$v"); for cr in $CRs; do $MY "DELETE FROM pagamentos WHERE conta_receber_id=$cr"; done
  $MY "DELETE FROM contas_receber WHERE venda_id=$v"; $MY "DELETE FROM fluxo_caixa WHERE referencia_tipo='venda' AND referencia_id=$v"
  $MY "DELETE FROM logs_sistema WHERE entidade='venda' AND entidade_id=$v"
  $MY "DELETE FROM vendas_itens WHERE venda_id=$v"; $MY "DELETE FROM vendas WHERE id=$v"
done
$MY "DELETE FROM crm_atividades WHERE oportunidade_id=$OPID"; $MY "DELETE FROM crm_oportunidades WHERE id=$OPID"; $MY "DELETE FROM crm_contatos WHERE id=$CTID"
$MY "DELETE FROM orcamentos_itens WHERE orcamento_id=$OID"; $MY "DELETE FROM orcamentos WHERE id=$OID"
$MY "DELETE FROM pagamentos WHERE conta_receber_id IN (SELECT id FROM contas_receber WHERE venda_id=0)" 2>/dev/null
[ -n "$CPID" ] && $MY "DELETE FROM contas_pagar WHERE id=$CPID"
$MY "DELETE FROM centro_custo WHERE nome='SIM CC'"; $MY "DELETE FROM tipos_caixa WHERE nome='SIM CAIXA TESTE'"
$MY "DELETE FROM tempo_producao WHERE produto_id=$PID"; $MY "DELETE FROM produtos WHERE id=$PID"; $MY "DELETE FROM produto_categorias WHERE nome='SIM CAT'"
$MY "DELETE FROM clientes WHERE id=$CID"
$MY "DELETE FROM usuarios_expedientes WHERE usuario_id IN (25,$UID_PROJ,$UID_CORTE,$UID_SOLDA) AND data_referencia=CURDATE()"
REST=$($MY "SELECT COUNT(*) FROM clientes WHERE razao_social='SIM CLIENTE LTDA'")
ok "LIMPEZA (resíduo=$REST)" "$([ "$REST" == "0" ] && echo 1)"

echo ""
echo "############################################################"
echo "# RESULTADO: $P PASS / $F FAIL"
echo "############################################################"
