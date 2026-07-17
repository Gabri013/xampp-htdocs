<?php
/**
 * Script para criar contas de teste para cada setor do ERP
 *
 * Uso: php criar_contas_teste.php [--delete]
 *
 * Cria 19 contas de teste (uma para cada tipo de usuário):
 * - teste_master@cozinca.local
 * - teste_vendedor@cozinca.local
 * - teste_projetista@cozinca.local
 * ... etc para cada setor
 *
 * Senha padrão: 123
 * Status: ativo
 *
 * Opções:
 * --delete : Remove as contas de teste antes de criar novas (limpar e recrear)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Verificar se é uma requisição via CLI
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("Este script deve ser executado via linha de comando.\n");
}

// Função para formatar o nome do tipo (ex: "engenharia" -> "Engenharia")
function formatarNomeTipo($tipo) {
    $nomes_customizados = [
        'master' => 'Administrador',
        'vendedor' => 'Vendedor',
        'projetista' => 'Projetista',
        'gerente' => 'Gerente de Produção',
        'producao' => 'Produção Geral',
        'dashboard_producao' => 'Dashboard de Produção',
    ];

    if (isset($nomes_customizados[$tipo])) {
        return $nomes_customizados[$tipo];
    }

    return ucfirst(str_replace('_', ' ', $tipo));
}

// Função para retornar os tipos de usuários disponíveis
function getTiposUsuarioDisponiveis() {
    return [
        'master' => 'Administrador',
        'vendedor' => 'Vendedor',
        'projetista' => 'Projetista',
        'gerente' => 'Gerente de Produção',
        'producao' => 'Produção Geral',
        'engenharia' => 'Setor de Engenharia',
        'programacao' => 'Setor de Programação',
        'corte' => 'Setor de Corte',
        'dobra' => 'Setor de Dobra',
        'tubo' => 'Setor de Tubo',
        'solda' => 'Setor de Solda',
        'mobiliario' => 'Setor de Mobiliário',
        'coccao' => 'Setor de Cocção',
        'refrigeracao' => 'Setor de Refrigeração',
        'acabamento' => 'Setor de Acabamento',
        'montagem' => 'Setor de Montagem',
        'embalagem' => 'Setor de Embalagem',
        'finalizacao' => 'Setor de Finalização',
        'dashboard_producao' => 'Dashboard de Produção'
    ];
}

try {
    $db = getDB();
    $tipos = getTiposUsuarioDisponiveis();
    $senha_padrao = '123';
    $senha_hash = password_hash($senha_padrao, PASSWORD_DEFAULT);
    $deletar_existentes = isset($argv[1]) && $argv[1] === '--delete';

    echo "\n========================================\n";
    echo "Criador de Contas de Teste do ERP\n";
    echo "========================================\n\n";

    if ($deletar_existentes) {
        echo "Deletando contas de teste existentes...\n";
        $stmt = $db->prepare("DELETE FROM usuarios WHERE email LIKE ?");
        $stmt->execute(['teste_%@cozinca.local']);
        echo "Contas deletadas.\n\n";
    }

    echo "Criando " . count($tipos) . " contas de teste...\n";
    echo "Senha padrão: " . $senha_padrao . "\n\n";

    $criadas = 0;
    $erros = 0;
    $credenciais = [];

    foreach ($tipos as $tipo => $descricao) {
        $email = 'teste_' . $tipo . '@cozinca.local';
        $nome = 'Teste ' . formatarNomeTipo($tipo);

        try {
            // Verificar se já existe
            $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);

            if ($stmt->fetch()) {
                echo "⚠️  Conta já existe: $email\n";
            } else {
                // Criar nova conta
                $stmt = $db->prepare(
                    "INSERT INTO usuarios (nome, email, senha, tipo, status) VALUES (?, ?, ?, ?, ?)"
                );
                $stmt->execute([$nome, $email, $senha_hash, $tipo, 'ativo']);
                $criadas++;

                echo "✓ Criada: $email (Tipo: $tipo)\n";
                $credenciais[] = [
                    'email' => $email,
                    'tipo' => $tipo,
                    'descricao' => $descricao
                ];
            }
        } catch (Exception $e) {
            echo "✗ Erro ao criar $email: " . $e->getMessage() . "\n";
            $erros++;
        }
    }

    echo "\n========================================\n";
    echo "Resumo:\n";
    echo "- Contas criadas: $criadas\n";
    echo "- Erros: $erros\n";
    echo "========================================\n\n";

    echo "Credenciais para teste:\n";
    echo "Senha: " . $senha_padrao . "\n\n";

    echo "| Email | Tipo | Descrição |\n";
    echo "|-------|------|----------|\n";

    foreach ($credenciais as $cred) {
        printf("| %s | %s | %s |\n", $cred['email'], $cred['tipo'], $cred['descricao']);
    }

    echo "\n========================================\n";
    echo "✓ Script executado com sucesso!\n";
    echo "========================================\n\n";

} catch (Exception $e) {
    echo "\n❌ Erro: " . $e->getMessage() . "\n\n";
    exit(1);
}
?>
