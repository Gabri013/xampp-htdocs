<?php
/**
 * DIAGNÓSTICO COMPLETO DO SISTEMA
 *
 * Identifica todos os erros e inconsistências
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/padrao_jotec.php';

$db = getDB();

echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║  🔍 DIAGNÓSTICO COMPLETO - COZINKA ERP                        ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$erros = [];
$avisos = [];
$sucessos = 0;

// ============================================================
// 1. VERIFICAR BANCO DE DADOS
// ============================================================
echo "1️⃣  VERIFICAR BANCO DE DADOS\n";
echo "════════════════════════════════════════════════════════════════\n";

try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM materias_primas");
    $materiais = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "✅ Tabela materias_primas: $materiais registros\n";
    $sucessos++;
} catch (Exception $e) {
    $erros[] = "Erro ao contar materiais: " . $e->getMessage();
    echo "❌ Tabela materias_primas: " . $e->getMessage() . "\n";
}

try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM fornecedores");
    $fornecedores = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "✅ Tabela fornecedores: $fornecedores registros\n";
    $sucessos++;
} catch (Exception $e) {
    $erros[] = "Erro ao contar fornecedores: " . $e->getMessage();
    echo "❌ Tabela fornecedores: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================
// 2. VALIDAR CÓDIGOS JOTEC
// ============================================================
echo "2️⃣  VALIDAR CÓDIGOS JOTEC\n";
echo "════════════════════════════════════════════════════════════════\n";

try {
    $stmt = $db->query("SELECT codigo, descricao FROM materias_primas LIMIT 10");
    $materiais_amostra = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $codigos_invalidos = 0;
    foreach ($materiais_amostra as $mat) {
        if (!PadraoJOTEC::validarCodigo($mat['codigo'])) {
            $codigos_invalidos++;
            $erros[] = "Código inválido: " . $mat['codigo'] . " (" . $mat['descricao'] . ")";
        }
    }

    if ($codigos_invalidos === 0) {
        echo "✅ Todos os " . count($materiais_amostra) . " códigos testados são válidos\n";
        $sucessos++;
    } else {
        echo "❌ $codigos_invalidos códigos inválidos encontrados\n";
    }
} catch (Exception $e) {
    $erros[] = "Erro ao validar códigos: " . $e->getMessage();
    echo "❌ Erro ao validar códigos: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================
// 3. VERIFICAR DUPLICATAS
// ============================================================
echo "3️⃣  VERIFICAR DUPLICATAS\n";
echo "════════════════════════════════════════════════════════════════\n";

try {
    $stmt = $db->query("
        SELECT codigo, COUNT(*) as qtd
        FROM materias_primas
        GROUP BY codigo
        HAVING qtd > 1
    ");
    $duplicatas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($duplicatas) === 0) {
        echo "✅ Nenhuma duplicata de código encontrada\n";
        $sucessos++;
    } else {
        echo "❌ Encontradas " . count($duplicatas) . " duplicatas:\n";
        foreach ($duplicatas as $dup) {
            echo "   - Código: " . $dup['codigo'] . " (aparece " . $dup['qtd'] . "x)\n";
            $erros[] = "Duplicata: " . $dup['codigo'];
        }
    }
} catch (Exception $e) {
    $avisos[] = "Não foi possível verificar duplicatas: " . $e->getMessage();
    echo "⚠️  Não foi possível verificar duplicatas\n";
}

echo "\n";

// ============================================================
// 4. VERIFICAR SEQUÊNCIAS
// ============================================================
echo "4️⃣  VERIFICAR SEQUÊNCIAS\n";
echo "════════════════════════════════════════════════════════════════\n";

try {
    $stmt = $db->query("
        SELECT codigo,
               CAST(SUBSTR(codigo, -6) AS UNSIGNED) as seq
        FROM materias_primas
        ORDER BY seq DESC
        LIMIT 1
    ");
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($resultado) {
        $seq_max = $resultado['seq'];
        $stmt = $db->query("SELECT COUNT(*) as total FROM materias_primas");
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        $gap = $seq_max - $total + 1;
        echo "✅ Sequência máxima: " . $resultado['codigo'] . " ($seq_max)\n";
        echo "   Total de registros: $total\n";

        if ($gap <= 5) {
            echo "✅ Sequências contínuas (gap: $gap registros)\n";
            $sucessos++;
        } else {
            echo "⚠️  Possível gap em sequências (gap: $gap registros)\n";
            $avisos[] = "Gap detectado em sequências: $gap registros";
        }
    }
} catch (Exception $e) {
    $erros[] = "Erro ao verificar sequências: " . $e->getMessage();
    echo "❌ Erro ao verificar sequências\n";
}

echo "\n";

// ============================================================
// 5. VALIDAR FOREIGN KEYS
// ============================================================
echo "5️⃣  VALIDAR FOREIGN KEYS\n";
echo "════════════════════════════════════════════════════════════════\n";

try {
    $stmt = $db->query("
        SELECT COUNT(*) as total
        FROM materias_primas
        WHERE fornecedor_id NOT IN (
            SELECT id FROM fornecedores
        ) OR fornecedor_id IS NULL
    ");
    $orfaos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    if ($orfaos === 0) {
        echo "✅ Todas as FK de fornecedor estão válidas\n";
        $sucessos++;
    } else {
        echo "❌ $orfaos registros com FK inválida de fornecedor\n";
        $erros[] = "FK inválida: $orfaos materiais sem fornecedor válido";
    }
} catch (Exception $e) {
    $avisos[] = "Não foi possível validar FKs: " . $e->getMessage();
    echo "⚠️  Não foi possível validar FKs\n";
}

echo "\n";

// ============================================================
// 6. VERIFICAR DADOS VAZIOS
// ============================================================
echo "6️⃣  VERIFICAR DADOS VAZIOS\n";
echo "════════════════════════════════════════════════════════════════\n";

try {
    $stmt = $db->query("
        SELECT COUNT(*) as total
        FROM materias_primas
        WHERE codigo IS NULL OR TRIM(codigo) = ''
           OR descricao IS NULL OR TRIM(descricao) = ''
    ");
    $vazios = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    if ($vazios === 0) {
        echo "✅ Nenhum campo obrigatório vazio\n";
        $sucessos++;
    } else {
        echo "❌ $vazios registros com campos vazios\n";
        $erros[] = "Campos vazios: $vazios registros";
    }
} catch (Exception $e) {
    $avisos[] = "Erro ao verificar campos vazios";
}

echo "\n";

// ============================================================
// 7. VERIFICAR ENCODING
// ============================================================
echo "7️⃣  VERIFICAR ENCODING\n";
echo "════════════════════════════════════════════════════════════════\n";

try {
    $stmt = $db->query("SELECT @@character_set_database as charset");
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    $charset = $resultado['charset'] ?? 'desconhecido';

    if (strpos($charset, 'utf8') !== false) {
        echo "✅ Banco usando $charset (OK para acentuação)\n";
        $sucessos++;
    } else {
        echo "⚠️  Banco usando $charset (cuidado com acentos)\n";
        $avisos[] = "Charset do banco não é UTF8: $charset";
    }
} catch (Exception $e) {
    $avisos[] = "Não foi possível verificar charset";
}

echo "\n";

// ============================================================
// 8. TESTAR GERAÇÃO DE CÓDIGOS
// ============================================================
echo "8️⃣  TESTAR GERAÇÃO DE CÓDIGOS\n";
echo "════════════════════════════════════════════════════════════════\n";

try {
    $cod1 = PadraoJOTEC::criarCodigoUnico($db, 'material');

    // INSERIR o código 1 para que não seja reutilizado
    $stmt = $db->prepare("
        INSERT INTO materias_primas
        (codigo, descricao, fornecedor_id, preco, unidade, aba_origem)
        VALUES (?, ?, 1, 1.00, 'un', 'DIAG')
    ");
    $stmt->execute([$cod1, 'Diagnóstico Teste 1']);

    $cod2 = PadraoJOTEC::criarCodigoUnico($db, 'material');

    if ($cod1 !== $cod2) {
        echo "✅ Códigos gerados são únicos\n";
        echo "   Código 1: $cod1\n";
        echo "   Código 2: $cod2\n";
        $sucessos++;
    } else {
        echo "❌ Códigos duplicados gerados: $cod1\n";
        $erros[] = "Geração de códigos duplicados";
    }

    // Limpar inserção de teste
    $db->prepare("DELETE FROM materias_primas WHERE aba_origem = 'DIAG'")->execute();

} catch (Exception $e) {
    $erros[] = "Erro ao testar geração: " . $e->getMessage();
    echo "❌ Erro ao testar geração: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================
// 9. TESTAR VALIDAÇÃO
// ============================================================
echo "9️⃣  TESTAR VALIDAÇÃO\n";
echo "════════════════════════════════════════════════════════════════\n";

$testes_validacao = [
    'JOTEC-001000' => true,
    'JOTEC-P00001' => true,
    'JOT-CLI-000001' => true,
    'INVALIDO-123' => false,
];

$validacoes_ok = 0;
foreach ($testes_validacao as $codigo => $esperado) {
    $resultado = PadraoJOTEC::validarCodigo($codigo);
    if ($resultado === $esperado) {
        $validacoes_ok++;
    } else {
        $erros[] = "Validação incorreta: $codigo (esperava $esperado, obteve " . ($resultado ? 'true' : 'false') . ")";
    }
}

echo "✅ Validação: $validacoes_ok/" . count($testes_validacao) . " testes passados\n";
if ($validacoes_ok === count($testes_validacao)) {
    $sucessos++;
}

echo "\n";

// ============================================================
// 10. RESUMO FINAL
// ============================================================
echo "════════════════════════════════════════════════════════════════\n";
echo "🔍 RESUMO DO DIAGNÓSTICO\n";
echo "════════════════════════════════════════════════════════════════\n\n";

echo "✅ Sucessos: $sucessos/9 testes\n";
echo "❌ Erros: " . count($erros) . "\n";
echo "⚠️  Avisos: " . count($avisos) . "\n\n";

if (count($erros) > 0) {
    echo "ERROS ENCONTRADOS:\n";
    foreach ($erros as $erro) {
        echo "  ❌ $erro\n";
    }
    echo "\n";
}

if (count($avisos) > 0) {
    echo "AVISOS:\n";
    foreach ($avisos as $aviso) {
        echo "  ⚠️  $aviso\n";
    }
    echo "\n";
}

// Calcular score
$score = ($sucessos / 9) * 100;
echo "════════════════════════════════════════════════════════════════\n";
echo "📊 SCORE GERAL: " . round($score, 1) . "%\n";

if ($score === 100) {
    echo "🎉 SISTEMA 100% OK!\n";
} elseif ($score >= 80) {
    echo "✅ SISTEMA OPERACIONAL (com pequenas correções recomendadas)\n";
} elseif ($score >= 50) {
    echo "⚠️  SISTEMA COM PROBLEMAS (correções necessárias)\n";
} else {
    echo "❌ SISTEMA COM PROBLEMAS CRÍTICOS\n";
}

echo "════════════════════════════════════════════════════════════════\n";

?>
