<?php
session_start();
require 'db.php';
$timezone = new DateTimeZone('America/Sao_Paulo');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) { die('ID do orçamento não informado ou inválido.'); }
$id_orcamento = (int)$_GET['id'];

$stmt = $conn->prepare("SELECT o.*, u.nome AS vendedor FROM orcamentos o JOIN usuarios u ON o.id_usuario = u.id WHERE o.id = ?");
$stmt->bind_param('i', $id_orcamento);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) { die('Nenhum orçamento encontrado.'); }
$orcamento = $res->fetch_assoc();

$data = new DateTime($orcamento['data_criacao']);
$data->setTimezone($timezone);

$itens = [];
$stmt_i = $conn->prepare("SELECT * FROM orcamento_itens WHERE id_orcamento = ? ORDER BY COALESCE(NULLIF(setor,''), 'zzz'), id");
$stmt_i->bind_param('i', $id_orcamento);
$stmt_i->execute();
$r_i = $stmt_i->get_result();
while($row = $r_i->fetch_assoc()){ $itens[] = $row; }
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Proposta Comercial - Orçamento <?= htmlspecialchars($orcamento['codigo_orcamento']) ?></title>
<style>
    @page { size: A4; margin: 10mm 15mm 20mm 15mm; }
    body { font-family: 'Segoe UI', Roboto, sans-serif; margin:0; background:#f4f6f8; color:#333; -webkit-print-color-adjust:exact; print-color-adjust:exact }
    .container { max-width:1000px; margin:30px auto; background:#fff; padding:15px; border-radius:10px }
    .header { display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid #ff530d; padding-bottom:15px; margin-bottom:10px }
    .header img { height:120px }
    .orcamento-numero { text-align:center; font-size:22px; font-weight:700; margin-bottom:15px; color:#ff530d }
    .orcamento-numero small { display:block; font-size:14px; color:#333 }
    .info-cliente { width:100%; margin-bottom:20px; font-size:13px; border:none }
    .info-cliente td { padding:4px 0; border:none }
    table { width:100%; border-collapse:collapse; margin-top:14px }
    thead { background:#ff530d; color:#fff }
    th, td { padding:10px; border-bottom:1px solid #ddd; font-size:12px; text-align:left; vertical-align:middle }
    .setor-row { background:#333; color:#fff; font-weight:700 }
    .setor-empty { background:#999 }
    td img { height:50px; border-radius:4px }
    .resumo { margin-top:20px; background:#fef6f2; border:1px solid #ff530d; border-radius:8px; padding:10px }
    .resumo p { margin:5px 0; font-size:14px; display:flex; justify-content:space-between }
    .resumo .total-final { font-size:16px; font-weight:700; color:#111 }
    .condicoes { margin-top:20px; padding:12px; background:#fef6f2; border:1px solid #ff530d; border-radius:8px }
    footer { text-align:center; margin-top:14px; font-size:12px; color:#555 }
    .empresa-info { text-align:center; font-size:12px; color:#555; margin-top:12px }
    .empresa-info p { margin:3px 0 }
    .no-print { text-align:center; margin-top:18px }
    .no-print button { background:#ff530d; color:#fff; border:0; padding:10px 20px; border-radius:5px; font-size:14px; cursor:pointer }
    .no-print button:hover { background:#e0480c }
    @media print { .no-print { display:none } }
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <img src="imagens/logo_cozinca.png" alt="Logo Cozinca Inox">
        <h1>Proposta Comercial</h1>
    </div>

    <div class="orcamento-numero">
        Orçamento Nº <?= htmlspecialchars($orcamento['codigo_orcamento']) ?>
        <small>Vendedor: <?= htmlspecialchars($orcamento['vendedor']) ?></small>
    </div>

    <table class="info-cliente">
        <tr><td><strong>Data:</strong> <?= $data->format('d/m/Y H:i') ?></td><td><strong>Telefone:</strong> <?= htmlspecialchars($orcamento['telefone']) ?></td></tr>
        <tr><td><strong>Cliente:</strong> <?= htmlspecialchars($orcamento['nome_cliente']) ?></td><td><strong>CNPJ:</strong> <?= htmlspecialchars($orcamento['cnpj']) ?></td></tr>
        <tr><td colspan="2"><strong>Endereço:</strong> <?= htmlspecialchars($orcamento['endereco']) ?></td></tr>
        <tr><td colspan="2"><strong>Email:</strong> <?= htmlspecialchars($orcamento['email']) ?></td></tr>
    </table>

    <table>
        <thead>
            <tr><th>Imagem</th><th>Item</th><th>Descrição</th><th>Qtd</th><th>Unitário</th><th>Total</th></tr>
        </thead>
        <tbody>
            <?php
            $totalProdutos = 0; $currentSetor = null;
            foreach ($itens as $it):
                $totalProdutos += (float)$it['preco_total'];
                $s = trim((string)$it['setor']);
                if ($s === '') $s = 'Sem setor';
                if ($currentSetor !== $s):
                    $currentSetor = $s;
            ?>
            <tr class="setor-row <?= $s==='Sem setor' ? 'setor-empty' : '' ?>">
                <td colspan="6">Setor: <?= htmlspecialchars($s) ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td>
                    <?php $caminho = 'uploads/' . basename($it['imagem'] ?? '');
                    if (!empty($it['imagem']) && file_exists($caminho)): ?>
                        <img src="<?= $caminho ?>" alt="Produto">
                    <?php else: ?>
                        <span>—</span>
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

    <?php
        $valor_frete = (float)$orcamento['frete'];
        $percentual_desconto = (float)$orcamento['desconto'];
        $valor_desconto = ($totalProdutos + $valor_frete) * ($percentual_desconto / 100);
        $total_final = ($totalProdutos + $valor_frete) - $valor_desconto;
    ?>
    <div class="resumo">
        <p><span>Total Produtos:</span> <span>R$ <?= number_format($totalProdutos, 2, ',', '.') ?></span></p>
        <p><span>Frete:</span> <span>R$ <?= number_format($valor_frete, 2, ',', '.') ?></span></p>
        <p><span>Desconto (<?= number_format($percentual_desconto, 2, ',', '.') ?>%):</span> <span>- R$ <?= number_format($valor_desconto, 2, ',', '.') ?></span></p>
        <p class="total-final"><span>Total Final:</span> <span>R$ <?= number_format($total_final, 2, ',', '.') ?></span></p>
    </div>
    <div class="condicoes">
        <h2>Condições da Proposta</h2>
       <p><strong>Condições de Pagamento:</strong> <?= htmlspecialchars($orcamento['pagamento']) ?></p>
        <p><strong>Dados Bancários:</strong> Banco Santander - AG 4416 - CC 13003186-1 - CNPJ: 49.996.211/0001-15</p>
        <p><strong>Chave Pix Santander  (CNPJ):</strong> 49996211000115</p>
        <p><strong>Prazo de Entrega:</strong> Em até <?= htmlspecialchars($orcamento['entrega']) ?> corridos, de acordo com a disponibilidade da fábrica, após confirmação do pedido e eventuais condições de medição em obra, bem como a aprovação dos desenhos técnicos de fabricação, que somente serão iniciados após pagamento do sinal e assinatura de contrato. Caso os equipamentos não possam ser entregues por eventuais atraso de obra, os mesmos serão faturados para futura entrega a ser programada pelo cliente podendo ser cobrada uma taxa de armazenagem dos mesmos.</p>
        
        <p><strong>Observações</strong> <?= htmlspecialchars($orcamento['assinatura']) ?></p>
       
    </div>

    <div class="condicoes">
         <p><strong>Embalagem:</strong> Nossos produtos serão entregues embalados em plástico bolha e filme strech, sendo que qualquer outro material de embalagem só será utilizado após prévio orçamento aprovado pelo cliente.</p>
        <p><strong>ITENS NÃO INCLUSOS EM NOSSO ESCOPO DE FORNECIMENTO:</strong> O deslocamento vertical e horizontal na obra; Dutos, exaustores, sistemas de proteção contra incêndio e passagem de tubulações de refrigeração para compressores remotos; Andaimes ou plataformas articuladas para instalação. Obs.: É de responsabilidade do cliente garantir a correta execução dos pontos, dimensionais e detalhes técnicos fornecidos em nossos desenhos executivos (plantas de pontos).</p>
        <p><strong>GARANTIA:</strong> Duração 12 meses (sendo 3 meses de garantia legal, conforme Código de Defesa do Consumidor e + 9 de garantia especial concedida pelo fabricante) contra eventuais defeitos de fabricação conforme "termo de garantia" que se inicia a partir da data de emissão da nota fiscal, nos termos da Lei 8078 de 11 de setembro de 1990, sabendo-se que todos os produtos fabricados pela Cozinca são testados e garantidos através da sua fábrica e de empresas autorizadas. O serviço de assistência técnica incluso na garantia será prestado por nossos técnicos num prazo máximo de até 72 horas após a solicitação no departamento de assistência técnica, em horário comercial (de segunda à sexta-feira das 8 às 17 horas).</p>
        <p><strong>NÃO ESTÁ INCLUSO EM NOSSA GARANTIA:</strong> Componentes com vida útil, variáveis como: Gaxetas, lâmpadas, fusíveis, resistências, correias, botões, manípulos, vidros, espelhos, Regulagem dos equipamentos que tenham se desregulado por mal uso ou por mudanças desejadas por seu usuário, tais como: queimadores, termostatos, pressostatos, termômetros e sensores de chama.</p>
        <p><strong>MAL FUNCIONAMENTO CAUSADO POR:</strong> Falta ou excesso de pressão de água; Oscilação e interrupção de energia elétrica; Variação de tensão; Falta de pressão ou vazão de gás; Falta de limpeza dos equipamentos; Curto circuito elétrico causado por falta de limpeza, bem como agressões das partes elétricas causados por água, detergentes e soluções cáusticas; Bloqueio de evaporadores e condensadores de refrigeradores e freezers; Descalibragem de controladores por operação incorreta; Uso inadequado dos equipamentos. Obs: Para equipamentos de revenda, o período de duração da garantia e a assistência técnica são de responsabilidade de seus fabricantes.</p>
        <p><strong>A GARANTIA PERDE A VALIDADE QUANDO:</strong> A instalação dos equipamentos é efetuada por outros que não sejam técnicos autorizados ou empresas credenciadas. Obs.: A instalação poderá ser efetuada por técnicos capacitados pela própria empresa mediante a prévia solicitação do cliente e autorização da Cozinca, salvo que o mesmo deverá enviar documento se responsabilizando pelo profissional e serviços prestados. Acidentes de transporte ou acidentes naturais, como inundações, incêndios ou outros; Mudança de local onde serão instalados os equipamentos sem acompanhamento dos nossos técnicos; Quando os equipamentos sofrerem alterações sem autorização da Cozinca. A Cozinca Cozinhas Profissionais não autoriza nenhum profissional ou empresa a assumir, em seu nome, qualquer outra responsabilidade relativa a garantia de seus produtos além das aqui informadas. A Cozinca se reserva ao direito de alterar as características técnicas e estéticas de seus produtos sem aviso prévio.</p>
        <p><strong>Desistencia:</strong> Apos o início da fabricação, quando os produtos forem personalizados, desenvolvidos e produzidos especialmente para o projeto, não poderá o comprador desistir da compra sem o pagamento integral do valor acordado; Por se tratar de equipamentos sob encomenda, não será aceita a devolução após a entrega, a não ser que não estejam de acordo com o pedido assinado pelo cliente; O acima mencionado se aplica igualmente aos itens de revenda.</p>
        <p><strong>VALIDADE DA PROPOSTA:</strong> 5 Dias</p>
    </div>
     <div class="empresa-info">
        <p><img src="imagens/instagram.png" alt="Instagram" style="height:14px;vertical-align:middle;margin-right:5px"> @cozinca.br</p>
        <p><img src="imagens/facebook.png" alt="Facebook" style="height:14px;vertical-align:middle;margin-right:5px"> /cozinca.br</p>
        <p>🌐 www.cozinca.com.br</p>
        <p>📍 R. Sebastiao Ferreira de Pinho, 219 - Boa Esperança - Santa Luzia - MG 33035-220</p>
        <p>CNPJ: 49.996.211/0001-15</p>
    </div>

    <footer>
        <p>Proposta emitida por Cozinca Inox - Todos os direitos reservados</p>
    </footer>

    <div class="no-print"><button onclick="window.print()">🖨 Imprimir Proposta</button></div>
</div>
</body>
</html>
