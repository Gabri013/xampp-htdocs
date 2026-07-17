<?php
/**
 * API de Exportação de Dados - Cozinka ERP
 *
 * Endpoints:
 *   POST /api/exportacao.php
 *   acao: exportar
 *   tabela: vendas|orcamentos|os|clientes|estoque|producao|financeiro
 *   formato: csv|xlsx|pdf|json
 *   filtros: {} (JSON com filtros específicos)
 *
 * Exemplo:
 *   curl -X POST http://localhost/api/exportacao.php \
 *     -d "acao=exportar&tabela=vendas&formato=xlsx&filtros={\"status\":\"confirmada\"}"
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/exportador.php';

header('Content-Type: application/json; charset=utf-8');

// Validações de segurança
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'sucesso' => false,
        'erro' => 'Sessão expirada. Faça login novamente.'
    ]);
    exit;
}

$usuario = getCurrentUser();
$db = getDB();
$acao = $_REQUEST['acao'] ?? null;

// ============================================================================
// AÇÃO: EXPORTAR
// ============================================================================
if ($acao === 'exportar') {
    try {
        // Validar e sanitizar entrada
        $tabela = trim($_REQUEST['tabela'] ?? '');
        $formato = trim(strtolower($_REQUEST['formato'] ?? 'csv'));

        if (empty($tabela)) {
            http_response_code(400);
            echo json_encode([
                'sucesso' => false,
                'erro' => 'Parâmetro "tabela" obrigatório.'
            ]);
            exit;
        }

        // Validar tabela permitida
        $tabelasPermitidas = [
            'vendas', 'orcamentos', 'os', 'clientes', 'estoque',
            'producao', 'financeiro'
        ];

        if (!in_array($tabela, $tabelasPermitidas)) {
            http_response_code(400);
            echo json_encode([
                'sucesso' => false,
                'erro' => "Tabela não suportada: {$tabela}"
            ]);
            exit;
        }

        // Processar filtros
        $filtros = [];
        if (!empty($_REQUEST['filtros'])) {
            $filtrosStr = $_REQUEST['filtros'];
            // Se for JSON string
            if (is_string($filtrosStr) && $filtrosStr[0] === '{') {
                $filtrosDecoded = json_decode($filtrosStr, true);
                if ($filtrosDecoded !== null) {
                    $filtros = $filtrosDecoded;
                }
            } else {
                // Se for array POST
                parse_str(http_build_query($_REQUEST), $temp);
                if (isset($temp['filtros'])) {
                    $filtros = $temp['filtros'];
                }
            }
        }

        // Sanitizar filtros (PDO prepare já cuida, mas validar tipos)
        $filtros = $this->sanitizarFiltros($filtros);

        // Criar exportador e exportar
        $exportador = new Exportador($db, $usuario);
        $resultado = $exportador->exportar($tabela, $formato, $filtros);

        if ($resultado === false) {
            $erros = $exportador->getErros();
            http_response_code(400);
            echo json_encode([
                'sucesso' => false,
                'erro' => implode('; ', $erros),
                'avisos' => $exportador->getAvisos()
            ]);
            exit;
        }

        // Registrar exportação no log
        registrarExportacao($db, $usuario['id'], $tabela, $formato, count($filtros));

        // Retornar resultado
        // Se for download, enviar arquivo diretamente
        if (isset($_REQUEST['download']) && $_REQUEST['download'] === '1') {
            enviarArquivo($resultado);
            exit;
        }

        // Caso contrário, retornar dados para JavaScript fazer download
        echo json_encode([
            'sucesso' => true,
            'tabela' => $tabela,
            'formato' => $formato,
            'nome_arquivo' => $resultado['nome'],
            'tipo_mime' => $resultado['tipo_mime'],
            'tamanho' => strlen($resultado['conteudo']),
            'data_exportacao' => date('Y-m-d H:i:s'),
            'avisos' => $exportador->getAvisos(),
            'conteudo_base64' => base64_encode($resultado['conteudo'])
        ]);
        exit;

    } catch (Exception $e) {
        error_log("Erro em exportacao.php: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'sucesso' => false,
            'erro' => 'Erro interno ao processar exportação.',
            'debug' => (isset($_ENV['DEBUG']) ? $e->getMessage() : null)
        ]);
        exit;
    }
}

// ============================================================================
// AÇÃO: LISTAR TABELAS EXPORTÁVEIS
// ============================================================================
if ($acao === 'listar_tabelas') {
    try {
        $usuario = getCurrentUser();
        $acessoSetores = [
            'master'      => ['vendas', 'orcamentos', 'os', 'estoque', 'producao', 'qualidade', 'expedicao', 'financeiro', 'clientes'],
            'gerente'     => ['vendas', 'orcamentos', 'os', 'estoque', 'producao', 'financeiro', 'clientes'],
            'vendedor'    => ['vendas', 'orcamentos', 'clientes'],
            'projetista'  => ['os', 'orcamentos'],
            'producao'    => ['os', 'estoque', 'producao'],
            'qualidade'   => ['producao', 'os'],
            'expedicao'   => ['os', 'vendas', 'estoque'],
            'contador'    => ['financeiro', 'vendas', 'os'],
        ];

        $tipo = $usuario['tipo'] ?? 'vendedor';
        $tabelas = $acessoSetores[$tipo] ?? ['vendas'];

        $tabelasInfo = [
            'vendas' => [
                'nome' => 'Vendas',
                'descricao' => 'Relatório de vendas realizadas',
                'icone' => 'shopping-cart'
            ],
            'orcamentos' => [
                'nome' => 'Orçamentos',
                'descricao' => 'Orçamentos enviados para clientes',
                'icone' => 'file-text'
            ],
            'os' => [
                'nome' => 'Ordens de Serviço',
                'descricao' => 'Ordens de serviço em andamento',
                'icone' => 'list-check'
            ],
            'clientes' => [
                'nome' => 'Clientes',
                'descricao' => 'Cadastro de clientes',
                'icone' => 'users'
            ],
            'estoque' => [
                'nome' => 'Estoque',
                'descricao' => 'Inventário de materiais',
                'icone' => 'package'
            ],
            'producao' => [
                'nome' => 'Produção',
                'descricao' => 'Ordens de produção',
                'icone' => 'factory'
            ],
            'financeiro' => [
                'nome' => 'Financeiro',
                'descricao' => 'Contas a pagar/receber',
                'icone' => 'dollar-sign'
            ]
        ];

        $resultado = [];
        foreach ($tabelas as $t) {
            if (isset($tabelasInfo[$t])) {
                $resultado[] = array_merge(['chave' => $t], $tabelasInfo[$t]);
            }
        }

        echo json_encode([
            'sucesso' => true,
            'tabelas' => $resultado,
            'formatos_suportados' => ['csv', 'xlsx', 'pdf', 'json'],
            'usuario' => [
                'nome' => $usuario['nome'],
                'tipo' => $usuario['tipo']
            ]
        ]);
        exit;

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'sucesso' => false,
            'erro' => 'Erro ao listar tabelas.'
        ]);
        exit;
    }
}

// ============================================================================
// AÇÃO: OBTER FILTROS DISPONÍVEIS
// ============================================================================
if ($acao === 'filtros_disponiveis') {
    try {
        $tabela = trim($_REQUEST['tabela'] ?? '');

        if (empty($tabela)) {
            http_response_code(400);
            echo json_encode([
                'sucesso' => false,
                'erro' => 'Parâmetro "tabela" obrigatório.'
            ]);
            exit;
        }

        $filtros = [
            'vendas' => [
                ['chave' => 'status', 'tipo' => 'select', 'label' => 'Status', 'opcoes' => ['confirmada', 'pendente', 'cancelada']],
                ['chave' => 'data_inicio', 'tipo' => 'date', 'label' => 'Data Inicial'],
                ['chave' => 'data_fim', 'tipo' => 'date', 'label' => 'Data Final'],
                ['chave' => 'cliente_id', 'tipo' => 'number', 'label' => 'ID do Cliente']
            ],
            'orcamentos' => [
                ['chave' => 'status', 'tipo' => 'select', 'label' => 'Status', 'opcoes' => ['rascunho', 'enviado', 'aprovado', 'rejeitado']],
                ['chave' => 'data_inicio', 'tipo' => 'date', 'label' => 'Data Inicial'],
                ['chave' => 'data_fim', 'tipo' => 'date', 'label' => 'Data Final']
            ],
            'os' => [
                ['chave' => 'status', 'tipo' => 'select', 'label' => 'Status', 'opcoes' => ['pendente', 'em_projeto', 'em_producao', 'finalizada', 'cancelada']],
                ['chave' => 'etapa', 'tipo' => 'select', 'label' => 'Etapa', 'opcoes' => ['corte', 'solda', 'montagem', 'acabamento', 'qualidade']]
            ],
            'clientes' => [
                ['chave' => 'status', 'tipo' => 'select', 'label' => 'Status', 'opcoes' => ['ativo', 'inativo']],
                ['chave' => 'busca', 'tipo' => 'text', 'label' => 'Busca (nome/CNPJ)']
            ],
            'estoque' => [
                ['chave' => 'status', 'tipo' => 'select', 'label' => 'Status', 'opcoes' => ['disponivel', 'reservado', 'danificado']],
                ['chave' => 'material_id', 'tipo' => 'number', 'label' => 'ID do Material']
            ],
            'producao' => [
                ['chave' => 'status', 'tipo' => 'select', 'label' => 'Status', 'opcoes' => ['pendente', 'em_producao', 'finalizada', 'cancelada']],
                ['chave' => 'etapa', 'tipo' => 'select', 'label' => 'Etapa', 'opcoes' => ['corte', 'solda', 'montagem', 'acabamento']]
            ],
            'financeiro' => [
                ['chave' => 'status', 'tipo' => 'select', 'label' => 'Status', 'opcoes' => ['aberto', 'parcial', 'pago', 'cancelado']],
                ['chave' => 'tipo', 'tipo' => 'select', 'label' => 'Tipo', 'opcoes' => ['a_receber', 'a_pagar']],
                ['chave' => 'data_inicio', 'tipo' => 'date', 'label' => 'Data Inicial'],
                ['chave' => 'data_fim', 'tipo' => 'date', 'label' => 'Data Final']
            ]
        ];

        $filtrosTabe = $filtros[$tabela] ?? [];

        echo json_encode([
            'sucesso' => true,
            'tabela' => $tabela,
            'filtros' => $filtrosTabe
        ]);
        exit;

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'sucesso' => false,
            'erro' => 'Erro ao obter filtros.'
        ]);
        exit;
    }
}

// ============================================================================
// AÇÃO: TESTE DE CONEXÃO
// ============================================================================
if ($acao === 'teste') {
    try {
        $conexao = $db->query("SELECT 1")->fetch();
        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Conexão com banco de dados OK',
            'usuario_logado' => $usuario['nome'],
            'tipo_usuario' => $usuario['tipo']
        ]);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'sucesso' => false,
            'erro' => 'Erro de conexão com banco de dados.'
        ]);
        exit;
    }
}

// ============================================================================
// PADRÃO: Sem ação especificada
// ============================================================================
http_response_code(400);
echo json_encode([
    'sucesso' => false,
    'erro' => 'Ação não especificada.',
    'acoes_disponíveis' => [
        'exportar' => 'Exportar dados em formato especificado',
        'listar_tabelas' => 'Listar tabelas disponíveis para exportação',
        'filtros_disponiveis' => 'Obter filtros disponíveis para uma tabela',
        'teste' => 'Testar conexão'
    ]
]);
exit;

// ============================================================================
// FUNÇÕES AUXILIARES
// ============================================================================

/**
 * Obtém usuário atual da sessão
 */
function getCurrentUser(): array
{
    return [
        'id' => $_SESSION['usuario_id'] ?? 0,
        'nome' => $_SESSION['usuario_nome'] ?? 'Desconhecido',
        'email' => $_SESSION['usuario_email'] ?? '',
        'tipo' => $_SESSION['usuario_tipo'] ?? 'vendedor'
    ];
}

/**
 * Sanitiza filtros para evitar SQL injection
 */
function sanitizarFiltros(array $filtros): array
{
    $filtrosValidos = [
        'status', 'data_inicio', 'data_fim', 'cliente_id',
        'material_id', 'etapa', 'busca', 'tipo'
    ];

    $resultado = [];
    foreach ($filtros as $chave => $valor) {
        if (in_array($chave, $filtrosValidos)) {
            // Sanitizar valor
            if (is_string($valor)) {
                $valor = trim($valor);
                $valor = preg_replace('/[<>]/', '', $valor); // Remove possíveis tags HTML
            }
            if (!empty($valor)) {
                $resultado[$chave] = $valor;
            }
        }
    }

    return $resultado;
}

/**
 * Registra exportação no log
 */
function registrarExportacao(PDO $db, int $usuarioId, string $tabela, string $formato, int $filtros_count): void
{
    try {
        // Tabela de log de exportações
        $sql = "INSERT INTO exportacoes_log (usuario_id, tabela, formato, filtros_count, data_exportacao)
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE data_exportacao = NOW()";

        // Se a tabela não existir, criar
        try {
            $stmt = $db->prepare($sql);
            $stmt->execute([$usuarioId, $tabela, $formato, $filtros_count]);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'exportacoes_log') !== false) {
                // Tabela não existe, criar
                $db->exec("
                    CREATE TABLE IF NOT EXISTS exportacoes_log (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        usuario_id INT NOT NULL,
                        tabela VARCHAR(100),
                        formato VARCHAR(20),
                        filtros_count INT DEFAULT 0,
                        data_exportacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_usuario (usuario_id),
                        INDEX idx_data (data_exportacao),
                        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
                // Tentar novamente
                $stmt = $db->prepare($sql);
                $stmt->execute([$usuarioId, $tabela, $formato, $filtros_count]);
            }
        }
    } catch (Exception $e) {
        error_log("Erro ao registrar exportação: " . $e->getMessage());
    }
}

/**
 * Envia arquivo como download
 */
function enviarArquivo(array $resultado): void
{
    $conteudo = $resultado['conteudo'];
    $tipo_mime = $resultado['tipo_mime'];
    $nome = $resultado['nome'];

    header('Content-Type: ' . $tipo_mime);
    header('Content-Disposition: attachment; filename="' . $nome . '"');
    header('Content-Length: ' . strlen($conteudo));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo $conteudo;
}
