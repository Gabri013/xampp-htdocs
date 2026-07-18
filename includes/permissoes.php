<?php
/**
 * Sistema de Permissões Granular
 * Cada permissão pode ser atribuída individualmente aos usuários
 */

function ensurePermissoesSchema(PDO $db): void
{
    $db->exec("
        CREATE TABLE IF NOT EXISTS permissoes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            chave VARCHAR(60) NOT NULL UNIQUE,
            nome VARCHAR(120) NOT NULL,
            modulo VARCHAR(60) NOT NULL,
            descricao TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_permissao_chave (chave),
            INDEX idx_permissao_modulo (modulo)
        ) ENGINE=InnoDB
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS usuario_permissoes (
            usuario_id INT NOT NULL,
            permissao_id INT NOT NULL,
            concedido_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (usuario_id, permissao_id),
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
            FOREIGN KEY (permissao_id) REFERENCES permissoes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB
    ");
}

function getPermissoesPadrao(): array
{
    return [
        // Módulo Vendas
        'vendas.visualizar' => 'Visualizar Vendas',
        'vendas.criar' => 'Criar Vendas',
        'vendas.editar' => 'Editar Vendas',
        'vendas.excluir' => 'Excluir/Cancelar Vendas',

        // Módulo Orçamentos
        'orcamentos.visualizar' => 'Visualizar Orçamentos',
        'orcamentos.criar' => 'Criar Orçamentos',
        'orcamentos.editar' => 'Editar Orçamentos',

        // Módulo OS
        'os.visualizar' => 'Visualizar Ordens de Serviço',
        'os.criar' => 'Criar Ordens de Serviço',
        'os.editar' => 'Editar Ordens de Serviço',
        'os.projeto' => 'Módulo Projetista',
        'os.producao' => 'Módulo Produção',
        'os.gerente' => 'Aprovação de Projetos',

        // Módulo Cadastros
        'clientes.visualizar' => 'Visualizar Clientes',
        'clientes.criar' => 'Criar Clientes',
        'clientes.editar' => 'Editar Clientes',
        'produtos.visualizar' => 'Visualizar Produtos',
        'produtos.criar' => 'Criar Produtos',
        'produtos.editar' => 'Editar Produtos',

        // Módulo Financeiro
        'financeiro.visualizar' => 'Visualizar Financeiro',
        'financeiro.lancar' => 'Lançar Contas',

        // Admin
        'admin.usuarios' => 'Gerenciar Usuários',
        'admin.permissoes' => 'Gerenciar Permissões',
    ];
}

/**
 * Acessos extras por usuário (modelo aditivo): cada linha é um tipo/setor que
 * o master liberou para o usuário ALÉM do cargo dele. Consumido por
 * hasPermission() (via sessão) e pela sidebar.
 */
function ensureAcessosExtrasSchema(PDO $db): void
{
    $db->exec("
        CREATE TABLE IF NOT EXISTS usuario_acessos_extras (
            usuario_id INT NOT NULL,
            acesso VARCHAR(40) NOT NULL,
            concedido_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (usuario_id, acesso),
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
        ) ENGINE=InnoDB
    ");
}

function getAcessosExtras(PDO $db, int $usuarioId): array
{
    $stmt = $db->prepare("SELECT acesso FROM usuario_acessos_extras WHERE usuario_id = ?");
    $stmt->execute([$usuarioId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

/**
 * Substitui os acessos extras do usuário pelo conjunto informado.
 * 'master' nunca é gravável como extra (evita escalonar para admin).
 */
function setAcessosExtras(PDO $db, int $usuarioId, array $acessos): void
{
    ensureAcessosExtrasSchema($db);
    $acessos = array_values(array_unique(array_filter($acessos, fn($a) => $a !== '' && $a !== 'master')));

    $db->prepare("DELETE FROM usuario_acessos_extras WHERE usuario_id = ?")->execute([$usuarioId]);
    if (!empty($acessos)) {
        $stmt = $db->prepare("INSERT INTO usuario_acessos_extras (usuario_id, acesso) VALUES (?, ?)");
        foreach ($acessos as $acesso) {
            $stmt->execute([$usuarioId, $acesso]);
        }
    }
}

function getPermissoesUsuario(PDO $db, int $usuarioId): array
{
    $stmt = $db->prepare("
        SELECT p.chave 
        FROM usuario_permissoes up
        JOIN permissoes p ON up.permissao_id = p.id
        WHERE up.usuario_id = ?
    ");
    $stmt->execute([$usuarioId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function hasPermissao(PDO $db, int $usuarioId, string $chave): bool
{
    // Master tem todas as permissões
    if (isMaster()) return true;

    $stmt = $db->prepare("
        SELECT 1 FROM usuario_permissoes up
        JOIN permissoes p ON up.permissao_id = p.id
        WHERE up.usuario_id = ? AND p.chave = ?
    ");
    $stmt->execute([$usuarioId, $chave]);
    return (bool) $stmt->fetchColumn();
}

function concederPermissao(PDO $db, int $usuarioId, string $chave): void
{
    $stmt = $db->prepare("
        INSERT IGNORE INTO usuario_permissoes (usuario_id, permissao_id)
        SELECT ?, id FROM permissoes WHERE chave = ?
    ");
    $stmt->execute([$usuarioId, $chave]);
}

function revogarPermissao(PDO $db, int $usuarioId, string $chave): void
{
    $stmt = $db->prepare("
        DELETE up FROM usuario_permissoes up
        JOIN permissoes p ON up.permissao_id = p.id
        WHERE up.usuario_id = ? AND p.chave = ?
    ");
    $stmt->execute([$usuarioId, $chave]);
}

function inicializarPermissoes(PDO $db): void
{
    ensurePermissoesSchema($db);
    foreach (getPermissoesPadrao() as $chave => $nome) {
        $stmt = $db->prepare("INSERT IGNORE INTO permissoes (chave, nome, modulo) VALUES (?, ?, ?)");
        $stmt->execute([$chave, $nome, explode('.', $chave)[0]]);
    }
    
    // Seed inicial: garantir permissões básicas para tipos existentes
    $tipos_permissoes = [
        'vendedor' => ['vendas.visualizar', 'vendas.criar', 'vendas.editar', 'orcamentos.visualizar', 'orcamentos.criar', 'os.visualizar', 'clientes.visualizar', 'clientes.criar', 'produtos.visualizar', 'financeiro.visualizar'],
        'projetista' => ['os.visualizar', 'os.projeto', 'clientes.visualizar', 'produtos.visualizar', 'financeiro.visualizar'],
        'gerente' => ['os.visualizar', 'os.gerente', 'os.producao', 'clientes.visualizar', 'produtos.visualizar', 'financeiro.visualizar'],
    ];
    
    foreach ($tipos_permissoes as $tipo => $perms) {
        if (!empty($perms)) {
            $stmt = $db->prepare("SELECT id FROM usuarios WHERE tipo = ? AND status = 'ativo'");
            $stmt->execute([$tipo]);
            $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($ids as $uid) {
                foreach ($perms as $p) {
                    concederPermissao($db, $uid, $p);
                }
            }
        }
    }
}