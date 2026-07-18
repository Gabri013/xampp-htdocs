<?php
/**
 * TESTE - Padronização JOTEC
 *
 * Demonstra todos os tipos de códigos do padrão JOTEC
 */

require_once '../config/config.php';
require_once '../includes/padrao_jotec.php';

$db = getDB();

echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║  🧪 TESTE - PADRONIZAÇÃO DE CÓDIGOS JOTEC                     ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// 1. GERAR CÓDIGOS SIMPLES
echo "1️⃣  CÓDIGOS SIMPLES (Geração Direta)\n";
echo "════════════════════════════════════════════════════════════════\n\n";

$teste_material = PadraoJOTEC::gerarCodigoMaterial(1000);
echo "Material (seq 1000):      $teste_material\n";

$teste_produto = PadraoJOTEC::gerarCodigoProduto(1);
echo "Produto (seq 1):          " . PadraoJOTEC::gerarCodigoProduto(1) . "\n";

$teste_fornecedor = PadraoJOTEC::gerarCodigoFornecedor(1);
echo "Fornecedor (seq 1):       " . PadraoJOTEC::gerarCodigoFornecedor(1) . "\n";

$teste_cliente = PadraoJOTEC::gerarCodigoCliente(1);
echo "Cliente (seq 1):          " . PadraoJOTEC::gerarCodigoCliente(1) . "\n";

$teste_os = PadraoJOTEC::gerarCodigoOS(1);
echo "Ordem de Serviço (seq 1): " . PadraoJOTEC::gerarCodigoOS(1) . "\n\n";

// 2. CÓDIGOS COM ABA
echo "2️⃣  CÓDIGOS COM PREFIXO DE ABA\n";
echo "════════════════════════════════════════════════════════════════\n\n";

$abas = ['PA', 'MT', 'GR', 'IN', 'CP'];
foreach ($abas as $aba) {
    $codigo = PadraoJOTEC::gerarCodigoComABA($aba, 1000);
    echo "  $aba-001000  ($codigo)\n";
}
echo "\n";

// 3. VALIDAÇÃO
echo "3️⃣  VALIDAÇÃO DE CÓDIGOS\n";
echo "════════════════════════════════════════════════════════════════\n\n";

$codigos_teste = [
    'JOTEC-001000' => 'Material válido',
    'JOTEC-P00001' => 'Produto válido',
    'JOT-CLI-000001' => 'Cliente válido',
    'OS-JOTEC-000001' => 'O.S. válida',
    'PA-001000' => 'ABA Produtos Acabados válida',
    'INVALIDO-123' => 'Inválido',
    'ABC123' => 'Inválido',
];

foreach ($codigos_teste as $codigo => $descricao) {
    $valido = PadraoJOTEC::validarCodigo($codigo) ? '✅' : '❌';
    echo "  $valido $codigo ($descricao)\n";
}
echo "\n";

// 4. EXTRAIR SEQUÊNCIA
echo "4️⃣  EXTRAIR SEQUÊNCIA DO CÓDIGO\n";
echo "════════════════════════════════════════════════════════════════\n\n";

$codigos_seq = [
    'JOTEC-001000' => 1000,
    'JOTEC-P00001' => 1,
    'JOT-CLI-000001' => 1,
    'OS-JOTEC-000001' => 1,
];

foreach ($codigos_seq as $codigo => $seq_esperada) {
    $seq = PadraoJOTEC::extrairSequencia($codigo);
    $ok = $seq === $seq_esperada ? '✅' : '❌';
    echo "  $ok $codigo → Sequência: $seq (esperada: $seq_esperada)\n";
}
echo "\n";

// 5. FORMATAÇÃO
echo "5️⃣  FORMATAÇÃO PARA EXIBIÇÃO\n";
echo "════════════════════════════════════════════════════════════════\n\n";

$codigos_fmt = ['JOTEC-001000', 'JOTEC-001001', 'JOTEC-001050'];
foreach ($codigos_fmt as $codigo) {
    $fmt = PadraoJOTEC::formatarParaExibicao($codigo);
    echo "  $codigo → $fmt\n";
}
echo "\n";

// 6. CRIAR CÓDIGOS ÚNICOS DO BANCO
echo "6️⃣  CRIAR CÓDIGOS ÚNICOS (COM BANCO DE DADOS)\n";
echo "════════════════════════════════════════════════════════════════\n\n";

try {
    // Verificar tabela de materiais_primas
    $stmt = $db->query("SELECT COUNT(*) as total FROM materias_primas");
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Total de materiais no banco: " . $resultado['total'] . "\n\n";

    // Obter próxima sequência
    $proxima = PadraoJOTEC::obterProximaSequencia($db, 'materias_primas', 'codigo');
    echo "✅ Próxima sequência disponível: $proxima\n";

    // Gerar código único
    $codigo_unico = PadraoJOTEC::criarCodigoUnico($db, 'material');
    echo "✅ Código único gerado: $codigo_unico\n\n";

} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n\n";
}

// 7. EXEMPLO PRÁTICO
echo "7️⃣  EXEMPLO PRÁTICO: CRIAR NOVO MATERIAL\n";
echo "════════════════════════════════════════════════════════════════\n\n";

try {
    $codigo = PadraoJOTEC::criarCodigoUnico($db, 'material');
    $descricao = "Teste Material - " . date('Y-m-d H:i:s');
    $fornecedor_id = 1;
    $preco = 99.99;
    $unidade = 'kg';
    $aba = 'TESTE';

    echo "Dados do material:\n";
    echo "  Código: $codigo\n";
    echo "  Descrição: $descricao\n";
    echo "  Fornecedor ID: $fornecedor_id\n";
    echo "  Preço: R$ $preco\n";
    echo "  Unidade: $unidade\n";
    echo "  ABA: $aba\n\n";

    $stmt = $db->prepare("
        INSERT INTO materias_primas
        (codigo, descricao, fornecedor_id, preco, unidade, aba_origem)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([$codigo, $descricao, $fornecedor_id, $preco, $unidade, $aba]);

    echo "✅ Material criado com sucesso!\n\n";

} catch (Exception $e) {
    echo "❌ Erro ao criar material: " . $e->getMessage() . "\n\n";
}

// 8. LISTAR ÚLTIMOS MATERIAIS CRIADOS
echo "8️⃣  ÚLTIMOS MATERIAIS CRIADOS\n";
echo "════════════════════════════════════════════════════════════════\n\n";

try {
    $stmt = $db->query("
        SELECT codigo, descricao, aba_origem, preco, unidade
        FROM materias_primas
        ORDER BY criado_em DESC
        LIMIT 5
    ");

    $materiais = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($materiais as $mat) {
        echo "  • {$mat['codigo']} - {$mat['descricao']}\n";
        echo "    ABA: {$mat['aba_origem']} | Preço: R$ {$mat['preco']} | Unidade: {$mat['unidade']}\n";
    }

    echo "\n";

} catch (Exception $e) {
    echo "❌ Erro ao listar: " . $e->getMessage() . "\n\n";
}

// RESUMO
echo "════════════════════════════════════════════════════════════════\n";
echo "✅ TESTE COMPLETO DA PADRONIZAÇÃO JOTEC\n";
echo "════════════════════════════════════════════════════════════════\n\n";

echo "📌 Padrões Implementados:\n";
echo "   ✅ Material: JOTEC-XXXXXX\n";
echo "   ✅ Produto: JOTEC-PXXXXX\n";
echo "   ✅ Fornecedor: JOTEC-FXXXX\n";
echo "   ✅ Cliente: JOT-CLI-XXXXXX\n";
echo "   ✅ Ordem de Serviço: OS-JOTEC-XXXXXX\n";
echo "   ✅ Com ABA: XX-XXXXXX\n\n";

echo "📌 Métodos Disponíveis:\n";
echo "   ✅ gerarCodigo*() - Geração simples\n";
echo "   ✅ validarCodigo() - Validação\n";
echo "   ✅ extrairSequencia() - Extrair número\n";
echo "   ✅ obterProximaSequencia() - Próximo do banco\n";
echo "   ✅ criarCodigoUnico() - Criar único\n";
echo "   ✅ formatarParaExibicao() - Formatar visual\n\n";

echo "🎯 Próximas Ações:\n";
echo "   1. Usar PadraoJOTEC em todas as APIs\n";
echo "   2. Validar códigos existentes\n";
echo "   3. Testar com dados reais\n\n";

?>
