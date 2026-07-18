<?php
/**
 * TESTE - Geração Corrigida de Códigos
 */

require_once '../config/config.php';
require_once '../includes/padrao_jotec.php';

$db = getDB();

echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║  🧪 TESTE - GERAÇÃO CORRIGIDA DE CÓDIGOS                      ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// ============================================================
// TESTE 1: Verificar Sequência Atual
// ============================================================
echo "1️⃣  VERIFICAR SEQUÊNCIA ATUAL\n";
echo "════════════════════════════════════════════════════════════════\n";

$stmt = $db->query("
    SELECT MAX(CAST(RIGHT(codigo, 6) AS UNSIGNED)) as max_seq
    FROM materias_primas
");
$resultado = $stmt->fetch(PDO::FETCH_ASSOC);
$max_atual = $resultado['max_seq'] ?? 0;

echo "Sequência máxima atual no banco: $max_atual\n";
echo "Próxima esperada: " . ($max_atual + 1) . "\n\n";

// ============================================================
// TESTE 2: Testar obterProximaSequencia
// ============================================================
echo "2️⃣  TESTAR FUNÇÃO obterProximaSequencia()\n";
echo "════════════════════════════════════════════════════════════════\n";

$prox1 = PadraoJOTEC::obterProximaSequencia($db, 'materias_primas', 'codigo');
echo "Chamada 1: $prox1\n";

$prox2 = PadraoJOTEC::obterProximaSequencia($db, 'materias_primas', 'codigo');
echo "Chamada 2: $prox2\n";

$prox3 = PadraoJOTEC::obterProximaSequencia($db, 'materias_primas', 'codigo');
echo "Chamada 3: $prox3\n";

if ($prox1 === $prox2 && $prox2 === $prox3) {
    echo "\n✅ Função retorna mesmo valor (esperado)\n";
} else {
    echo "\n⚠️  Função retorna valores diferentes\n";
}

echo "\n";

// ============================================================
// TESTE 3: Gerar Códigos e Inserir
// ============================================================
echo "3️⃣  GERAR E INSERIR CÓDIGOS\n";
echo "════════════════════════════════════════════════════════════════\n";

$codigos_inseridos = [];

for ($i = 1; $i <= 3; $i++) {
    try {
        $codigo = PadraoJOTEC::criarCodigoUnico($db, 'material');

        // Inserir no banco para realmente usar o código
        $stmt = $db->prepare("
            INSERT INTO materias_primas
            (codigo, descricao, preco, unidade, aba_origem)
            VALUES (?, ?, ?, ?, ?)
        ");

        $descricao = "Teste Auto $i - " . date('Y-m-d H:i:s');
        $stmt->execute([$codigo, $descricao, 10.00 + $i, 'un', 'TESTE-AUTO']);

        $codigos_inseridos[] = $codigo;

        echo "✅ Inserido $i: $codigo\n";

    } catch (Exception $e) {
        echo "❌ Erro ao inserir $i: " . $e->getMessage() . "\n";
    }
}

echo "\n";

// ============================================================
// TESTE 4: Verificar Unicidade
// ============================================================
echo "4️⃣  VERIFICAR UNICIDADE DOS CÓDIGOS\n";
echo "════════════════════════════════════════════════════════════════\n";

$todos_unicos = count($codigos_inseridos) === count(array_unique($codigos_inseridos));

if ($todos_unicos) {
    echo "✅ Todos os " . count($codigos_inseridos) . " códigos inseridos são únicos\n";
    echo "\nCódigos inseridos:\n";
    foreach ($codigos_inseridos as $i => $cod) {
        echo "  " . ($i + 1) . ". $cod\n";
    }
} else {
    echo "❌ Detectadas duplicatas entre os códigos gerados!\n";
}

echo "\n";

// ============================================================
// TESTE 5: Verificar no Banco
// ============================================================
echo "5️⃣  VERIFICAR NO BANCO\n";
echo "════════════════════════════════════════════════════════════════\n";

$stmt = $db->query("
    SELECT COUNT(*) as total
    FROM materias_primas
    WHERE aba_origem = 'TESTE-AUTO'
");
$teste_auto_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

echo "Materiais com aba_origem='TESTE-AUTO': $teste_auto_count\n";

if ($teste_auto_count === count($codigos_inseridos)) {
    echo "✅ Todos os materiais foram inseridos com sucesso\n";
} else {
    echo "❌ Nem todos os materiais foram inseridos\n";
}

echo "\n";

// ============================================================
// RESUMO
// ============================================================
echo "════════════════════════════════════════════════════════════════\n";
echo "🔍 RESUMO DO TESTE\n";
echo "════════════════════════════════════════════════════════════════\n\n";

echo "Status da Geração: ";
if ($todos_unicos && $teste_auto_count === count($codigos_inseridos)) {
    echo "✅ OK!\n";
} else {
    echo "❌ COM ERROS\n";
}

echo "\nPróximas Ações Recomendadas:\n";
if ($prox1 === $max_atual + 1) {
    echo "  ✅ Função obterProximaSequencia() está correta\n";
} else {
    echo "  ⚠️  Revisar função obterProximaSequencia()\n";
}

if ($todos_unicos) {
    echo "  ✅ Função criarCodigoUnico() está gerando códigos únicos\n";
} else {
    echo "  ❌ Revisar função criarCodigoUnico()\n";
}

echo "\n════════════════════════════════════════════════════════════════\n";

?>
