<?php
require_once '../../config/config.php';
requirePermission(['master', 'vendedor']);

$ids_str = $_GET['ids'] ?? '';
$ocultar_valores = isset($_GET['sem_valores']) && $_GET['sem_valores'] == '1';

if (empty($ids_str)) {
    die('Nenhuma venda selecionada.');
}

$ids = explode(',', $ids_str);
$ids = array_map('intval', $ids);
$placeholders = implode(',', array_fill(0, count($ids), '?'));

$db = getDB();

// Buscar todas as vendas selecionadas
$stmt = $db->prepare("
    SELECT v.*, c.razao_social, c.cnpj_cpf, c.telefone, c.email, c.endereco, c.cidade, c.estado, c.cep, u.nome as usuario_nome
    FROM vendas v
    INNER JOIN clientes c ON v.cliente_id = c.id
    INNER JOIN usuarios u ON v.usuario_id = u.id
    WHERE v.id IN ($placeholders)
    ORDER BY v.id DESC
");
$stmt->execute($ids);
$vendas = $stmt->fetchAll();

if (empty($vendas)) {
    die('Vendas não encontradas.');
}

$pagamentos = ['avista' => 'À Vista', 'cartao' => 'Cartão', 'boleto' => 'Boleto'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Impressão em Lote - Cozinca</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #333; line-height: 1.4; margin: 0; padding: 0; }
        .venda-page { padding: 20px; page-break-after: always; border-bottom: 1px dashed #ccc; margin-bottom: 20px; }
        .venda-page:last-child { page-break-after: auto; border-bottom: none; }
        
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
        .logo-area { width: 200px; height: 80px; border: 1px dashed #ccc; display: flex; align-items: center; justify-content: center; font-weight: bold; color: #999; }
        .company-info { text-align: right; }
        .company-info h2 { margin: 0; color: #000; }
        .company-info p { margin: 2px 0; font-size: 11px; }
        
        .doc-title { text-align: center; background: #f0f0f0; padding: 5px; margin-bottom: 20px; border: 1px solid #ddd; }
        .doc-title h3 { margin: 0; text-transform: uppercase; }
        
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .info-box { border: 1px solid #ddd; padding: 10px; border-radius: 5px; }
        .info-box h4 { margin: 0 0 10px 0; border-bottom: 1px solid #eee; padding-bottom: 5px; text-transform: uppercase; font-size: 11px; color: #666; }
        .info-box p { margin: 3px 0; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; table-layout: fixed; }
        table th { background: #f0f0f0; border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 11px; }
        table td { border: 1px solid #ddd; padding: 8px; vertical-align: top; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .item-descricao { white-space: pre-wrap; overflow-wrap: anywhere; word-break: break-word; }
        
        .totals { width: 300px; margin-left: auto; }
        .totals table td { border: none; padding: 5px; }
        .totals .grand-total { font-size: 16px; font-weight: bold; border-top: 2px solid #333; }
        
        .footer { margin-top: 50px; text-align: center; font-size: 10px; color: #777; border-top: 1px solid #eee; padding-top: 10px; }
        
        @media print {
            .no-print { display: none; }
            .venda-page { border-bottom: none; margin-bottom: 0; }
        }
        
        .controls { position: fixed; bottom: 20px; right: 20px; background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.2); display: flex; flex-direction: column; gap: 10px; border: 1px solid #ddd; z-index: 1000; }
        .btn-print { background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: bold; }
        .btn-print:hover { background: #218838; }
        .toggle-container { display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; }
    </style>
</head>
<body>

    <div class="controls no-print">
        <label class="toggle-container">
            <input type="checkbox" id="toggleValores" <?php echo $ocultar_valores ? 'checked' : ''; ?>>
            Ocultar Valores
        </label>
        <button class="btn-print" onclick="window.print()">
            <i class="fas fa-print"></i> Imprimir Todas as Vendas
        </button>
    </div>

    <?php foreach ($vendas as $venda): ?>
        <?php
        // Buscar itens da venda
        $stmt_itens = $db->prepare("SELECT * FROM vendas_itens WHERE venda_id = ?");
        $stmt_itens->execute([$venda['id']]);
        $itens = $stmt_itens->fetchAll();

        // Buscar O.S relacionada
        $stmt_os = $db->prepare("SELECT * FROM ordens_servico WHERE venda_id = ?");
        $stmt_os->execute([$venda['id']]);
        $os = $stmt_os->fetch();
        ?>
        <div class="venda-page">
            <div class="header">
                <div class="logo-area">
                    LOGO COZINCA
                </div>
                <div class="company-info">
                    <h2>COZINCA</h2>
                    <p>CNPJ: 49.996.211/0001-15</p>
                    <p>📍 R. Sebastiao Ferreira de Pinho, 219 - Boa Esperança</p>
                    <p>Santa Luzia - MG | CEP: 33035-220</p>
                    <p>🌐 www.cozinca.com.br | 📧 contato@cozinca.com.br</p>
                    <p>📸 @cozinca.br | 📘 /cozinca.br</p>
                </div>
            </div>

            <div class="doc-title">
                <h3>Comprovante de Venda nº <?php echo $venda['numero']; ?></h3>
            </div>

            <div class="info-grid">
                <div class="info-box">
                    <h4>Dados do Cliente</h4>
                    <p><strong>Razão Social:</strong> <?php echo htmlspecialchars($venda['razao_social']); ?></p>
                    <p><strong>CNPJ/CPF:</strong> <?php echo htmlspecialchars($venda['cnpj_cpf']); ?></p>
                    <p><strong>Endereço:</strong> <?php echo htmlspecialchars($venda['endereco'] . ', ' . $venda['cidade'] . '/' . $venda['estado']); ?></p>
                    <p><strong>Telefone:</strong> <?php echo htmlspecialchars($venda['telefone']); ?></p>
                </div>
                <div class="info-box">
                    <h4>Informações do Pedido</h4>
                    <p><strong>Data da Venda:</strong> <?php echo formatDate($venda['data_venda']); ?></p>
                    <p><strong>Vendedor:</strong> <?php echo htmlspecialchars($venda['usuario_nome']); ?></p>
                    <?php if ($os): ?>
                        <p><strong>Ordem de Serviço:</strong> <?php echo $os['numero']; ?></p>
                        <p><strong>Prioridade:</strong> <?php echo strtoupper($os['prioridade']); ?></p>
                    <?php endif; ?>
                    <?php if (!$ocultar_valores): ?>
                        <p><strong>Forma de Pagamento:</strong> 
                            <?php echo $pagamentos[$venda['forma_pagamento']] ?? 'Não informada'; ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Descrição do Item</th>
                        <th width="80" class="text-center">Qtd</th>
                        <?php if (!$ocultar_valores): ?>
                            <th width="120" class="text-right">Vlr. Unitário</th>
                            <th width="120" class="text-right">Vlr. Total</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($itens as $item): ?>
                        <tr>
                            <td class="item-descricao"><?php echo nl2br(htmlspecialchars($item['descricao_manual'])); ?></td>
                            <td class="text-center"><?php echo number_format($item['quantidade'], 2, ',', '.'); ?></td>
                            <?php if (!$ocultar_valores): ?>
                                <td class="text-right"><?php echo formatMoney($item['valor_unitario']); ?></td>
                                <td class="text-right"><?php echo formatMoney($item['valor_total']); ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div style="display: flex; justify-content: space-between;">
                <div style="width: <?php echo $ocultar_valores ? '100%' : '60%'; ?>;">
                    <?php if (!empty($venda['observacoes_venda'])): ?>
                        <div class="info-box">
                            <h4>Observações da Venda</h4>
                            <p><?php echo nl2br(htmlspecialchars($venda['observacoes_venda'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if (!$ocultar_valores): ?>
                    <div class="totals">
                        <table>
                            <tr>
                                <td>SUBTOTAL:</td>
                                <td class="text-right"><?php echo formatMoney($venda['valor_total'] + $venda['desconto']); ?></td>
                            </tr>
                            <?php if ($venda['desconto'] > 0): ?>
                            <tr>
                                <td style="color: #d9534f;">DESCONTO:</td>
                                <td class="text-right" style="color: #d9534f;">- <?php echo formatMoney($venda['desconto']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr class="grand-total">
                                <td>TOTAL GERAL:</td>
                                <td class="text-right"><?php echo formatMoney($venda['valor_total']); ?></td>
                            </tr>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div style="margin-top: 40px; display: flex; justify-content: space-around;">
                <div style="text-align: center; width: 200px; border-top: 1px solid #333; padding-top: 5px;">
                    Assinatura do Cliente
                </div>
                <div style="text-align: center; width: 200px; border-top: 1px solid #333; padding-top: 5px;">
                    Cozinca
                </div>
            </div>

            <div class="footer">
                Documento gerado em <?php echo date('d/m/Y H:i:s'); ?> pelo Sistema de Gestão Cozinca.
            </div>
        </div>
    <?php endforeach; ?>

    <script>
        document.getElementById('toggleValores').addEventListener('change', function() {
            const url = new URL(window.location.href);
            if (this.checked) {
                url.searchParams.set('sem_valores', '1');
            } else {
                url.searchParams.delete('sem_valores');
            }
            window.location.href = url.toString();
        });
    </script>
</body>
</html>
