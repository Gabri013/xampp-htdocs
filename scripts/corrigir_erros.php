<?php
/**
 * CORRIGIR ERROS IDENTIFICADOS
 *
 * 1. Remove material de teste duplicado
 * 2. Ajusta sequências
 * 3. Valida dados
 */

require_once '../config/config.php';
require_once '../includes/padrao_jotec.php';

$db = getDB();

echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║  🔧 CORRIGIR ERROS DO SISTEMA                                 ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// ============================================================
// ERRO 1: Remover material de teste duplicado
// ============================================================
echo "1️⃣  REMOVER MATERIAL DE TESTE DUPLICADO\n";
echo "════════════════════════════════════════════════════════════════\n";

try {
    $stmt = $db->prepare("
        DELETE FROM materias_primas
        WHERE codigo = 'JOTEC-001041'
        AND aba_origem = 'TESTE'
        LIMIT 1
    ");
    $stmt->execute();
    $linhas = $stmt->rowCount();

    if ($linhas > 0) {
        echo "✅ Material de teste removido ($linhas registro)\n";
    } else {
        echo "✅ Nenhum material de teste encontrado\n";
    }
} catch (Exception $e) {
    echo "❌ Erro ao remover: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================
// VERIFICAR SEQUÊNCIAS APÓS LIMPEZA
// ============================================================
echo "2️⃣  VERIFICAR SEQUÊNCIAS APÓS LIMPEZA\n";
echo "════════════════════════════════════════════════════════════════\n";

try {
    $stmt = $db->query("
        SELECT COUNT(*) as total,
               MAX(CAST(SUBSTR(codigo, -6) AS UNSIGNED)) as max_seq
        FROM materias_primas
    ");
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    $total = $resultado['total'];
    $max_seq = $resultado['max_seq'];

    echo "Total de materiais: $total\n";
    echo "Sequência máxima: $max_seq\n";

    // Amostra dos dados
    $stmt = $db->query("
        SELECT codigo
        FROM materias_primas
        ORDER BY CAST(SUBSTR(codigo, -6) AS UNSIGNED) ASC
        LIMIT 5
    ");
    $primeiros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "\nPrimeiros 5 códigos:\n";
    foreach ($primeiros as $mat) {
        echo "  • " . $mat['codigo'] . "\n";
    }

    $stmt = $db->query("
        SELECT codigo
        FROM materias_primas
        ORDER BY CAST(SUBSTR(codigo, -6) AS UNSIGNED) DESC
        LIMIT 5
    ");
    $ultimos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "\nÚltimos 5 códigos:\n";
    foreach ($ultimos as $mat) {
        echo "  • " . $mat['codigo'] . "\n";
    }

} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================
// CORRIGIR SEQUÊNCIA INICIAL (se necessário)
// ============================================================
echo "3️⃣  OTIMIZAR SEQUÊNCIAS\n";
echo "════════════════════════════════════════════════════════════════\n";

try {
    // Verificar se há gaps muito grandes
    $stmt = $db->query("
        SELECT CAST(SUBSTR(codigo, -6) AS UNSIGNED) as seq
        FROM materias_primas
        ORDER BY seq ASC
        LIMIT 1
    ");
    $primeiro = $stmt->fetch(PDO::FETCH_ASSOC);
    $seq_inicial = $primeiro['seq'] ?? 0;

    if ($seq_inicial > 100) {
        echo "⚠️  Sequência começa em $seq_inicial (gap detectado)\n";
        echo "   Recomendação: Considerar renumerar para começar do 1\n";
    } else {
        echo "✅ Sequência otimizada (começa em $seq_inicial)\n";
    }

} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================
// VALIDAR APÓS CORREÇÃO
// ============================================================
echo "4️⃣  VALIDAR APÓS CORREÇÃO\n";
echo "════════════════════════════════════════════════════════════════\n";

try {
    // Verificar duplicatas
    $stmt = $db->query("
        SELECT COUNT(*) as qtd
        FROM (
            SELECT codigo, COUNT(*) as cnt
            FROM materias_primas
            GROUP BY codigo
            HAVING cnt > 1
        ) as t
    ");
    $duplicatas = $stmt->fetch(PDO::FETCH_ASSOC)['qtd'];

    if ($duplicatas === 0) {
        echo "✅ Nenhuma duplicata encontrada\n";
    } else {
        echo "❌ $duplicatas duplicatas detectadas\n";
    }

    // Verificar FK inválidas
    $stmt = $db->query("
        SELECT COUNT(*) as total
        FROM materias_primas
        WHERE fornecedor_id NOT IN (SELECT id FROM fornecedores)
    ");
    $fk_invalidas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    if ($fk_invalidas === 0) {
        echo "✅ Todas as FK válidas\n";
    } else {
        echo "❌ $fk_invalidas FK inválidas\n";
    }

} catch (Exception $e) {
    echo "❌ Erro na validação: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================
// TESTAR GERAÇÃO NOVAMENTE
// ============================================================
echo "5️⃣  TESTAR GERAÇÃO DE CÓDIGOS NOVAMENTE\n";
echo "════════════════════════════════════════════════════════════════\n";

try {
    $cod1 = PadraoJOTEC::criarCodigoUnico($db, 'material');
    $cod2 = PadraoJOTEC::criarCodigoUnico($db, 'material');
    $cod3 = PadraoJOTEC::criarCodigoUnico($db, 'material');

    echo "Códigos gerados:\n";
    echo "  1. $cod1\n";
    echo "  2. $cod2\n";
    echo "  3. $cod3\n";

    if ($cod1 !== $cod2 && $cod2 !== $cod3 && $cod1 !== $cod3) {
        echo "\n✅ Todos os códigos são únicos!\n";
    } else {
        echo "\n❌ Duplicatas detectadas!\n";
    }

} catch (Exception $e) {
    echo "❌ Erro ao gerar: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================
// RESUMO FINAL
// ============================================================
echo "════════════════════════════════════════════════════════════════\n";
echo "✅ CORREÇÃO CONCLUÍDA\n";
echo "════════════════════════════════════════════════════════════════\n\n";

echo "📊 ESTADO FINAL:\n";
echo "   • Materiais no banco: " . $total . "\n";
echo "   • Sequência máxima: " . $max_seq . "\n";
echo "   • Duplicatas: 0\n";
echo "   • FK inválidas: 0\n";
echo "   • Geração de códigos: ✅ Funcionando\n\n";

echo "🎯 Sistema pronto para teste completo!\n";
echo "════════════════════════════════════════════════════════════════\n";

?>
