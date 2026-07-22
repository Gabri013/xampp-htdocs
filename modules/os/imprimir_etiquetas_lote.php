<?php
/**
 * Etiquetas em LOTE de um pedido/O.S. — uma etiqueta por item, com logo da
 * empresa, nº da O.S., cliente, descrição do item, QR + código de barras e as
 * informações do pedido. Layout de impressão (várias por folha A4).
 *
 * Uso: imprimir_etiquetas_lote.php?os_id=123  (ou &item_id= para uma só)
 * Acesso: quem já vê a O.S. (mesma regra do os_detalhes).
 */

require_once '../../config/config.php';
require_once '../../includes/engenharia.php';
requirePermission(['master', 'vendedor', 'projetista', 'gerente', 'producao', 'dashboard_producao', 'finalizacao']);

$db = getDB();
$osId = (int) ($_GET['os_id'] ?? 0);
$itemId = (int) ($_GET['item_id'] ?? 0);
if ($osId <= 0) { die('O.S. não informada.'); }

$stmt = $db->prepare("SELECT os.*, c.razao_social, c.cidade, c.estado, v.numero AS venda_numero
    FROM ordens_servico os
    JOIN clientes c ON c.id = os.cliente_id
    LEFT JOIN vendas v ON v.id = os.venda_id
    WHERE os.id = ?");
$stmt->execute([$osId]);
$os = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$os) { die('O.S. não encontrada.'); }

$itens = getItensComerciaisOS($db, $osId, (int) ($os['venda_id'] ?? 0), $itemId);
if (empty($itens)) {
    // Sem itens: gera 1 etiqueta da própria O.S.
    $itens = [['id' => 0, 'produto_codigo' => '', 'produto_nome' => 'Pedido ' . $os['numero'], 'descricao_manual' => '', 'quantidade' => 1, 'produto_descricao' => '']];
}

$prioridadeLabel = ['verde' => 'Normal', 'amarelo' => 'Emergente', 'vermelho' => 'Urgente'][$os['prioridade']] ?? ucfirst((string) $os['prioridade']);
$entrega = $os['data_termino'] ? date('d/m/Y', strtotime($os['data_termino'])) : '—';
$logo = SITE_URL . '/assets/img/logo_cozinca_op.png';
$totalItens = count($itens);

header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Etiquetas <?= htmlspecialchars($os['numero']) ?></title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: Arial, Helvetica, sans-serif; background: #eef1f4; color: #111; }
    .barra { position: sticky; top: 0; background: #fff; border-bottom: 1px solid #ddd; padding: 10px 16px; display: flex; gap: 10px; align-items: center; }
    .barra button, .barra a { font: inherit; font-size: 13px; font-weight: 700; padding: 8px 16px; border-radius: 8px; border: none; cursor: pointer; text-decoration: none; }
    .b-print { background: #D85A30; color: #fff; }
    .b-back { background: #64748b; color: #fff; }
    .barra .tit { margin-left: auto; color: #64748b; font-size: 13px; }

    .folha { max-width: 210mm; margin: 12px auto; display: grid; grid-template-columns: 1fr 1fr; gap: 6mm; padding: 8mm; }

    .etq { border: 2px solid #000; border-radius: 4px; padding: 3mm; display: flex; flex-direction: column; gap: 2mm; background: #fff; break-inside: avoid; }
    .etq-top { display: flex; align-items: center; gap: 3mm; border-bottom: 1.5px solid #000; padding-bottom: 2mm; }
    .etq-top img { height: 12mm; width: auto; }
    .etq-top .os { margin-left: auto; text-align: right; }
    .etq-top .os .n { font-size: 17px; font-weight: 800; letter-spacing: .5px; }
    .etq-top .os .l { font-size: 8px; color: #555; text-transform: uppercase; letter-spacing: 1px; }

    .etq-cli { font-size: 12px; font-weight: 700; }
    .etq-cli .sub { font-size: 9px; font-weight: 400; color: #555; }

    .etq-item { border: 1px solid #999; border-radius: 3px; padding: 2mm; background: #f7f7f7; }
    .etq-item .desc { font-size: 11px; font-weight: 700; line-height: 1.25; }
    .etq-item .meta { font-size: 9px; color: #444; margin-top: 1mm; display: flex; justify-content: space-between; }

    .etq-codes { display: flex; align-items: center; gap: 3mm; }
    .etq-codes .bc { flex: 1; }
    .etq-codes .bc svg { width: 100%; height: 11mm; }
    .etq-codes .bc .bcnum { font-size: 9px; text-align: center; letter-spacing: 1px; margin-top: .5mm; }
    .etq-codes .qr { width: 17mm; height: 17mm; flex: none; }
    .etq-codes .qr img { width: 100%; height: 100%; }

    .etq-foot { display: flex; justify-content: space-between; font-size: 9px; border-top: 1px dashed #999; padding-top: 1.5mm; }
    .etq-foot .prio { font-weight: 800; }
    .prio.verde { color: #16a34a; } .prio.amarelo { color: #d97706; } .prio.vermelho { color: #dc2626; }

    @media print {
        body { background: #fff; }
        .barra { display: none; }
        .folha { margin: 0; padding: 4mm; }
        @page { size: A4; margin: 6mm; }
    }
</style>
</head>
<body>
    <div class="barra">
        <button class="b-print" onclick="window.print()">🖨️ Imprimir <?= $totalItens ?> etiqueta<?= $totalItens > 1 ? 's' : '' ?></button>
        <a class="b-back" href="os_detalhes.php?os_id=<?= $osId ?>">← Voltar ao pedido</a>
        <span class="tit">Etiquetas do pedido <?= htmlspecialchars($os['numero']) ?> · <?= htmlspecialchars($os['razao_social']) ?></span>
    </div>

    <div class="folha">
        <?php $n = 0; foreach ($itens as $it): $n++;
            $desc = trim(($it['produto_codigo'] ? $it['produto_codigo'] . ' · ' : '') . ($it['produto_nome'] ?: $it['descricao_manual'] ?: 'Item'));
            $codigoEtq = $os['numero'] . ($it['id'] ? '-' . str_pad((string) $n, 2, '0', STR_PAD_LEFT) : '');
            $qrConteudo = 'OS|' . $os['numero'] . '|' . $osId;
        ?>
        <div class="etq">
            <div class="etq-top">
                <img src="<?= $logo ?>" alt="Cozinca" onerror="this.style.display='none'">
                <div class="os">
                    <div class="l">Ordem de Serviço</div>
                    <div class="n"><?= htmlspecialchars($os['numero']) ?></div>
                </div>
            </div>

            <div class="etq-cli">
                <?= htmlspecialchars($os['razao_social']) ?>
                <div class="sub"><?= htmlspecialchars(trim(($os['cidade'] ?? '') . ($os['estado'] ? '/' . $os['estado'] : ''))) ?><?= $os['venda_numero'] ? ' · Venda ' . htmlspecialchars($os['venda_numero']) : '' ?></div>
            </div>

            <div class="etq-item">
                <div class="desc"><?= htmlspecialchars($desc) ?></div>
                <div class="meta">
                    <span>Qtd: <strong><?= htmlspecialchars(rtrim(rtrim((string) $it['quantidade'], '0'), '.')) ?></strong></span>
                    <span>Item <?= $n ?>/<?= $totalItens ?></span>
                </div>
            </div>

            <div class="etq-codes">
                <div class="bc">
                    <?= gerarCode128Svg($codigoEtq, 40) ?>
                    <div class="bcnum"><?= htmlspecialchars($codigoEtq) ?></div>
                </div>
                <div class="qr"><img src="<?= gerarQrDataUri($qrConteudo, 200) ?>" alt="QR"></div>
            </div>

            <div class="etq-foot">
                <span class="prio <?= htmlspecialchars($os['prioridade']) ?>">Prioridade: <?= htmlspecialchars($prioridadeLabel) ?></span>
                <span>Entrega: <strong><?= $entrega ?></strong></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <script>window.addEventListener('load', function(){ setTimeout(function(){ try{ window.print(); }catch(e){} }, 400); });</script>
</body>
</html>
