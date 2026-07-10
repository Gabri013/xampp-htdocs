<?php
// imprimir_venda.php — Impressão de Venda (agrupa por Setor, mostra frete/desc e fotos)
session_start();
if (!isset($_SESSION['usuario'])) { header("Location: login.php"); exit; }
require 'includes/db.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // ajuda no debug
$timezone = new DateTimeZone('America/Sao_Paulo');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
  die("ID da venda não informado ou inválido.");
}
$id_venda = (int)$_GET['id'];

/* ===== Venda + usuário (vendedor) ===== */
$sqlV = "SELECT v.*, u.nome AS vendedor_nome, u.usuario AS vendedor_login
         FROM vendas v
         JOIN usuarios u ON u.id = v.id_usuario
         WHERE v.id = ?";
$stmt = $conn->prepare($sqlV);
$stmt->bind_param('i', $id_venda);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) { die("Venda não encontrada."); }
$venda = $res->fetch_assoc();

$data = new DateTime($venda['criado_em']);
$data->setTimezone($timezone);

/* ===== Itens da venda (ordenados por Setor) ===== */
$sqlI = "SELECT id, item, descricao, quantidade, preco_unitario, preco_total, imagem, setor
         FROM venda_itens
         WHERE id_venda = ?
         ORDER BY COALESCE(NULLIF(setor,''), 'zzz'), id";
$stmtI = $conn->prepare($sqlI);
$stmtI->bind_param('i', $id_venda);
$stmtI->execute();
$rI = $stmtI->get_result();
$itens = [];
while ($row = $rI->fetch_assoc()) { $itens[] = $row; }

/* ===== Totais (confiamos nos campos gravados na venda) ===== */
$total_produtos = (float)($venda['total_produtos'] ?? 0);
$frete          = (float)($venda['frete'] ?? 0);
$desconto_perc  = (float)($venda['desconto'] ?? 0);
$base           = $total_produtos + $frete;
$valor_desc     = $base * ($desconto_perc/100);
$total_final    = (float)($venda['total_final'] ?? ($base - $valor_desc));
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Imprimir Venda - <?= htmlspecialchars($venda['codigo_venda']) ?></title>
<style>
  @page { size: A4; margin: 10mm 15mm 20mm 15mm; }
  body { font-family:'Segoe UI', Roboto, Arial, sans-serif; margin:0; background:#f4f6f8; color:#333;
         -webkit-print-color-adjust:exact; print-color-adjust:exact; }
  .container { max-width:1000px; margin:30px auto; background:#fff; padding:15px; border-radius:10px; }
  .header { display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid #ff530d;
            padding-bottom:12px; margin-bottom:10px; }
  .header img { height:110px; }
  h1 { margin:0; font-size:1.4rem; color:#ff530d; }
  .doc-id { text-align:center; font-size:20px; font-weight:700; color:#ff530d; margin:8px 0 12px; }
  .doc-id small { display:block; font-size:13px; color:#333; font-weight:400; }

  .info { width:100%; border-collapse:collapse; margin:6px 0 10px; }
  .info td { padding:4px 0; font-size:13px; border:none; }
  .badge { display:inline-block; background:#fff3e8; color:#8a3b12; border:1px solid #ffd6b8; border-radius:12px;
           padding:2px 8px; font-size:12px; margin-left:6px; }

  table { width:100%; border-collapse:collapse; margin-top:12px; }
  thead { background:#1c1c1c; color:#fff; }
  th, td { padding:10px; font-size:12px; border-bottom:1px solid #ddd; text-align:left; vertical-align:middle; }
  tr:nth-child(even) { background:#fafafa; }

  .row-setor { background:#333; color:#fff; font-weight:700; }
  .row-setor.sem { background:#999; }

  td img.thumb { height:50px; width:auto; border-radius:4px; display:block; }

  .resumo { margin-top:18px; background:#fef6f2; border:1px solid #ff530d; border-radius:8px; padding:10px; }
  .resumo p { margin:5px 0; font-size:14px; display:flex; justify-content:space-between; }
  .resumo .final { font-size:16px; font-weight:800; color:#111; }

  .condicoes { margin-top:16px; padding:12px; background:#fef6f2; border:1px solid #ff530d; border-radius:8px; }
  .condicoes p { margin:6px 0; }

  .empresa-info { text-align:center; font-size:12px; color:#555; margin-top:12px; }
  .empresa-info p { margin:3px 0; }
  footer { text-align:center; margin-top:10px; font-size:12px; color:#555; }

  .no-print { text-align:center; margin-top:16px; }
  .no-print button { background:#ff530d; color:#fff; border:0; padding:10px 20px; border-radius:5px; font-size:14px; cursor:pointer; }
  .no-print button:hover { background:#e0480c; }
  @media print { .no-print { display:none } }
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <img src="imagens/logo_cozinca.png" alt="Logo Cozinca Inox">
    <h1>Comprovante de Venda</h1>
  </div>

  <div class="doc-id">
    Venda: <?= htmlspecialchars($venda['codigo_venda']) ?>
    <small>Vendedor: <?= htmlspecialchars($venda['vendedor_nome'] ?: $venda['vendedor_login']) ?></small>
  </div>

  <table class="info">
    <tr>
      <td><strong>Data:</strong> <?= $data->format('d/m/Y H:i') ?></td>
      <td><strong>Status:</strong> <?= htmlspecialchars($venda['status']) ?></td>
    </tr>
    <tr>
      <td><strong>Cliente:</strong> <?= htmlspecialchars($venda['nome_cliente']) ?></td>
      <td><strong>Telefone:</strong> <?= htmlspecialchars($venda['telefone']) ?></td>
    </tr>
    <tr>
      <td colspan="2"><strong>Endereço:</strong> <?= htmlspecialchars($venda['endereco']) ?></td>
    </tr>
    <tr>
      <td><strong>Email:</strong> <?= htmlspecialchars($venda['email']) ?></td>
      <td><strong>CPF/CNPJ:</strong> <?= htmlspecialchars($venda['cnpj']) ?></td>
    </tr>
  </table>

  <table>
    <thead>
      <tr>
        <th>Imagem</th>
        <th>Item</th>
        <th>Descrição</th>
        <th>Qtd</th>
        <th>Unitário</th>
        <th>Total</th>
      </tr>
    </thead>
    <tbody>
      <?php
        $curSetor = null;
        foreach ($itens as $it):
          $s = trim((string)$it['setor']);
          if ($s === '') $s = 'Sem setor';
          if ($curSetor !== $s):
            $curSetor = $s; ?>
            <tr class="row-setor <?= $s==='Sem setor'?'sem':'' ?>">
              <td colspan="6">Setor: <?= htmlspecialchars($s) ?></td>
            </tr>
          <?php endif; ?>
          <tr>
            <td>
              <?php
                // A imagem salva em venda_itens já deve estar com caminho relativo (ex.: uploads/xxxxx.jpg)
                $img = $it['imagem'];
                if (!empty($img) && file_exists($img)): ?>
                  <img src="<?= htmlspecialchars($img) ?>" class="thumb" alt="produto">
              <?php else: ?>
                  <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($it['item']) ?></td>
            <td><?= htmlspecialchars($it['descricao']) ?></td>
            <td><?= (int)$it['quantidade'] ?></td>
            <td>R$ <?= number_format((float)$it['preco_unitario'], 2, ',', '.') ?></td>
            <td>R$ <?= number_format((float)$it['preco_total'], 2, ',', '.') ?></td>
          </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="resumo">
    <p><span>Total Produtos:</span><span>R$ <?= number_format($total_produtos, 2, ',', '.') ?></span></p>
    <p><span>Frete:</span><span>R$ <?= number_format($frete, 2, ',', '.') ?></span></p>
    <p><span>Desconto (<?= number_format($desconto_perc, 2, ',', '.') ?>%):</span><span>- R$ <?= number_format($valor_desc, 2, ',', '.') ?></span></p>
    <p class="final"><span>Total Final:</span><span>R$ <?= number_format($total_final, 2, ',', '.') ?></span></p>
  </div>

  <div class="condicoes">
    <p><strong>Pagamento:</strong> <?= htmlspecialchars($venda['pagamento']) ?></p>
    <p><strong>Entrega:</strong> <?= htmlspecialchars($venda['entrega']) ?></p>
    <p><strong>Assinatura:</strong> <?= htmlspecialchars($venda['assinatura']) ?></p>
  </div>

  <div class="empresa-info">
    <p><img src="imagens/instagram.png" alt="Instagram" style="height:14px;vertical-align:middle;margin-right:6px">@cozinca.br</p>
    <p><img src="imagens/facebook.png" alt="Facebook" style="height:14px;vertical-align:middle;margin-right:6px">/cozinca.br</p>
    <p>🌐 www.cozinca.com.br</p>
    <p>📍 R. Sebastiao Ferreira de Pinho, 219 - Boa Esperança - Santa Luzia - MG 33035-220</p>
    <p>CNPJ: 49.996.211/0001-15</p>
  </div>

  <footer>
    <p>Proposta/Venda emitida por Cozinca Inox - Todos os direitos reservados</p>
  </footer>

  <div class="no-print">
    <button onclick="window.print()">🖨 Imprimir</button>
  </div>
</div>
</body>
</html>
