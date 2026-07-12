<?php
/**
 * Módulo CRM — contatos (pessoas), oportunidades (pipeline) e atividades.
 * Integra com clientes (empresas), orçamentos e vendas do ERP.
 */

function ensureCrmSchema(PDO $db): void
{
    static $checked = false;
    if ($checked) return;
    $checked = true;

    if (function_exists('shouldRunSchemaSync') && !shouldRunSchemaSync('crm', 86400)) {
        return;
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS crm_contatos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cliente_id INT NULL,
            nome VARCHAR(150) NOT NULL,
            cargo VARCHAR(100) NULL,
            email VARCHAR(150) NULL,
            telefone VARCHAR(30) NULL,
            whatsapp VARCHAR(30) NULL,
            cidade VARCHAR(100) NULL,
            observacoes TEXT NULL,
            criado_por INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_crm_contato_cliente (cliente_id),
            INDEX idx_crm_contato_nome (nome),
            FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
            FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON DELETE SET NULL
        ) ENGINE=InnoDB
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS crm_oportunidades (
            id INT AUTO_INCREMENT PRIMARY KEY,
            titulo VARCHAR(200) NOT NULL,
            cliente_id INT NULL,
            contato_id INT NULL,
            valor_estimado DECIMAL(15,2) NOT NULL DEFAULT 0,
            estagio ENUM('lead','contato','proposta','negociacao','ganho','perdido') NOT NULL DEFAULT 'lead',
            origem VARCHAR(60) NULL,
            responsavel_id INT NULL,
            orcamento_id INT NULL,
            venda_id INT NULL,
            previsao_fechamento DATE NULL,
            motivo_perda VARCHAR(255) NULL,
            observacoes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_crm_op_estagio (estagio),
            INDEX idx_crm_op_cliente (cliente_id),
            INDEX idx_crm_op_resp (responsavel_id),
            FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
            FOREIGN KEY (contato_id) REFERENCES crm_contatos(id) ON DELETE SET NULL,
            FOREIGN KEY (responsavel_id) REFERENCES usuarios(id) ON DELETE SET NULL,
            FOREIGN KEY (orcamento_id) REFERENCES orcamentos(id) ON DELETE SET NULL,
            FOREIGN KEY (venda_id) REFERENCES vendas(id) ON DELETE SET NULL
        ) ENGINE=InnoDB
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS crm_atividades (
            id INT AUTO_INCREMENT PRIMARY KEY,
            oportunidade_id INT NULL,
            cliente_id INT NULL,
            contato_id INT NULL,
            tipo ENUM('nota','tarefa','ligacao','email','reuniao','whatsapp') NOT NULL DEFAULT 'nota',
            titulo VARCHAR(200) NOT NULL,
            descricao TEXT NULL,
            data_prevista DATETIME NULL,
            concluida TINYINT(1) NOT NULL DEFAULT 0,
            usuario_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_crm_atv_op (oportunidade_id),
            INDEX idx_crm_atv_cliente (cliente_id),
            INDEX idx_crm_atv_pendente (concluida, data_prevista),
            FOREIGN KEY (oportunidade_id) REFERENCES crm_oportunidades(id) ON DELETE CASCADE,
            FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
            FOREIGN KEY (contato_id) REFERENCES crm_contatos(id) ON DELETE SET NULL,
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
        ) ENGINE=InnoDB
    ");
}

function getCrmEstagios(): array
{
    return [
        'lead'       => ['label' => 'Lead',        'cor' => '#64748b'],
        'contato'    => ['label' => 'Em Contato',  'cor' => '#0284c7'],
        'proposta'   => ['label' => 'Proposta',    'cor' => '#7c3aed'],
        'negociacao' => ['label' => 'Negociação',  'cor' => '#d97706'],
        'ganho'      => ['label' => 'Ganho',       'cor' => '#16a34a'],
        'perdido'    => ['label' => 'Perdido',     'cor' => '#dc2626'],
    ];
}

function getCrmTiposAtividade(): array
{
    return [
        'nota'     => ['label' => 'Nota',     'icon' => 'fa-sticky-note'],
        'tarefa'   => ['label' => 'Tarefa',   'icon' => 'fa-check-square'],
        'ligacao'  => ['label' => 'Ligação',  'icon' => 'fa-phone'],
        'email'    => ['label' => 'E-mail',   'icon' => 'fa-envelope'],
        'reuniao'  => ['label' => 'Reunião',  'icon' => 'fa-users'],
        'whatsapp' => ['label' => 'WhatsApp', 'icon' => 'fa-comment-dots'],
    ];
}

/**
 * Vendedor enxerga apenas as próprias oportunidades; gestão vê todas.
 * Retorna [clausula SQL extra, params].
 */
function crmFiltroResponsavel(array $usuario): array
{
    if (in_array($usuario['tipo'] ?? '', ['master', 'gerente'], true)) {
        return ['', []];
    }
    return [' AND (o.responsavel_id = ? OR o.responsavel_id IS NULL)', [$usuario['id']]];
}
