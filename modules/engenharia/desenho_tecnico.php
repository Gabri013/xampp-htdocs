<?php
/**
 * Módulo de Desenho Técnico e Aprovação
 *
 * Funcionalidades:
 * - Upload de arquivos (PDF, DWG, PNG, JPG, 3D, DXF)
 * - Pré-visualização de imagens
 * - Versionamento (v1.0, v1.1, etc)
 * - Histórico completo de mudanças
 * - Fluxo de aprovação (Projetista → Gerente → Produção)
 * - Rastreamento de quem enviou, aprovou e quando
 *
 * Acesso: Projetista, Gerente, Produção, Master
 */

require_once '../../config/config.php';
require_once '../../includes/workflow.php';
require_once '../../includes/engenharia.php';

requirePermission(['master', 'gerente', 'producao', 'projetista', 'engenharia']);

$page_title = 'Desenho Técnico e Aprovação';
$db = getDB();
ensureEngenhariaSchema($db);

// Diretório de upload
$uploadDir = '../../assets/uploads/desenhos/';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}

// ===== AÇÕES =====
$acao = $_POST['acao'] ?? $_GET['acao'] ?? null;
$osId = (int) ($_POST['os_id'] ?? $_GET['os_id'] ?? 0);
$desenhoId = (int) ($_POST['desenho_id'] ?? $_GET['desenho_id'] ?? 0);

// Obter usuário atual
$usuarioId = $_SESSION['usuario_id'] ?? 0;
$usuarioPerfil = $_SESSION['perfil'] ?? 'usuario';

// ===== OBTER DADOS DA O.S. =====
if ($osId > 0) {
    $stmt = $db->prepare("SELECT * FROM ordens_servico WHERE id = ?");
    $stmt->execute([$osId]);
    $ordemServico = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ordemServico) {
        http_response_code(404);
        die("Ordem de Serviço não encontrada.");
    }
} else {
    $ordemServico = null;
}

// ===== OBTER DESENHO =====
$desenho = null;
if ($desenhoId > 0) {
    $stmt = $db->prepare("
        SELECT d.*,
               u_proj.nome AS projetista_nome,
               u_ger.nome AS gerente_nome,
               u_prod.nome AS producao_nome
        FROM desenhos_tecnicos d
        LEFT JOIN usuarios u_proj ON u_proj.id = d.usuario_projetista_id
        LEFT JOIN usuarios u_ger ON u_ger.id = d.usuario_gerente_id
        LEFT JOIN usuarios u_prod ON u_prod.id = d.usuario_producao_id
        WHERE d.id = ?
    ");
    $stmt->execute([$desenhoId]);
    $desenho = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($desenho) {
        $osId = $desenho['os_id'];
    }
}

// ===== LISTAR DESENHOS DA O.S. =====
$desenhos = [];
if ($osId > 0) {
    $stmt = $db->prepare("
        SELECT d.*,
               u_proj.nome AS projetista_nome,
               u_ger.nome AS gerente_nome,
               u_prod.nome AS producao_nome,
               COUNT(da.id) AS total_arquivos
        FROM desenhos_tecnicos d
        LEFT JOIN usuarios u_proj ON u_proj.id = d.usuario_projetista_id
        LEFT JOIN usuarios u_ger ON u_ger.id = d.usuario_gerente_id
        LEFT JOIN usuarios u_prod ON u_prod.id = d.usuario_producao_id
        LEFT JOIN desenhos_arquivos da ON da.desenho_id = d.id
        WHERE d.os_id = ?
        GROUP BY d.id
        ORDER BY d.created_at DESC
    ");
    $stmt->execute([$osId]);
    $desenhos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ===== CORES POR STATUS =====
$statusCores = [
    'rascunho' => '#6b7280',
    'submetido' => '#f59e0b',
    'em_revisao' => '#3b82f6',
    'aprovado' => '#10b981',
    'rejeitado' => '#dc2626',
    'obsoleto' => '#9ca3af',
];

$statusLabels = [
    'rascunho' => 'Rascunho',
    'submetido' => 'Submetido',
    'em_revisao' => 'Em Revisão',
    'aprovado' => 'Aprovado',
    'rejeitado' => 'Rejeitado',
    'obsoleto' => 'Obsoleto',
];

?>
<?php include '../../includes/header_vendedor.php'; ?>
<!-- Tailwind mantido para as classes utilitárias desta página -->
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="/assets/css/nomus-theme.css">
<style>
        .badge-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            border: none;
            transition: all 0.3s ease;
        }
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        .btn-primary:hover {
            background: #2563eb;
        }
        .btn-success {
            background: #10b981;
            color: white;
        }
        .btn-success:hover {
            background: #059669;
        }
        .btn-danger {
            background: #dc2626;
            color: white;
        }
        .btn-danger:hover {
            background: #b91c1c;
        }
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        .btn-secondary:hover {
            background: #4b5563;
        }
        .preview-box {
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            background: #f9fafb;
        }
        .file-preview {
            max-width: 400px;
            max-height: 300px;
            margin: 10px auto;
            border-radius: 6px;
        }
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        .timeline::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e5e7eb;
        }
        .timeline-item {
            position: relative;
            margin-bottom: 20px;
            padding-bottom: 20px;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -38px;
            top: 0;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #3b82f6;
            border: 3px solid white;
        }
    </style>
<div class="vend-layout">
    <?php include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main"><div class="vend-content">

    <div class="container mx-auto max-w-6xl p-4">
        <!-- Breadcrumb -->
        <nav class="mb-6 flex items-center gap-2 text-sm text-gray-600">
            <a href="../../index.php" class="hover:text-blue-600">Painel</a>
            <span>/</span>
            <a href="index.php" class="hover:text-blue-600">Engenharia</a>
            <span>/</span>
            <span>Desenho Técnico</span>
            <?php if ($osId > 0): ?>
                <span>/</span>
                <span>OS #<?= htmlspecialchars($ordemServico['numero'] ?? $osId) ?></span>
            <?php endif; ?>
        </nav>

        <!-- Cabeçalho -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Desenho Técnico e Aprovação</h1>
            <p class="text-gray-600 mt-2">Gerencie desenhos, versões e aprovações para suas ordens de produção</p>
        </div>

        <?php if ($osId > 0 && $ordemServico): ?>
            <!-- Informações da O.S. -->
            <div class="card bg-blue-50 border-blue-200 mb-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Ordem de Serviço #<?= htmlspecialchars($ordemServico['numero']) ?></h2>
                        <p class="text-sm text-gray-600 mt-1"><?= htmlspecialchars($ordemServico['descricao'] ?? 'Sem descrição') ?></p>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl font-bold text-blue-600">#<?= (int)$osId ?></div>
                        <div class="text-sm text-gray-600">Status: <strong><?= getEtapaLabel($ordemServico['etapa_atual'] ?? 'autorizacao') ?></strong></div>
                    </div>
                </div>
            </div>

            <!-- Abas de Navegação -->
            <div class="flex gap-4 mb-6 border-b border-gray-200">
                <button class="tab-btn active px-4 py-3 border-b-2 border-blue-600 text-blue-600 font-medium" data-tab="lista">
                    <i class="fas fa-list mr-2"></i>Desenhos
                </button>
                <button class="tab-btn px-4 py-3 border-b-2 border-transparent text-gray-600 font-medium hover:text-gray-900" data-tab="novo">
                    <i class="fas fa-plus mr-2"></i>Novo Desenho
                </button>
                <button class="tab-btn px-4 py-3 border-b-2 border-transparent text-gray-600 font-medium hover:text-gray-900" data-tab="aprovacoes">
                    <i class="fas fa-check-circle mr-2"></i>Aprovações
                </button>
            </div>

            <!-- Aba: Lista de Desenhos -->
            <div id="tab-lista" class="tab-content active">
                <?php if (!empty($desenhos)): ?>
                    <div class="space-y-4">
                        <?php foreach ($desenhos as $d): ?>
                            <div class="card hover:shadow-lg transition-shadow">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-3 mb-2">
                                            <h3 class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($d['titulo']) ?></h3>
                                            <span class="badge-status" style="background: <?= htmlspecialchars($statusCores[$d['status']] ?? '#6b7280') ?>; color: white;">
                                                <?= htmlspecialchars($statusLabels[$d['status']] ?? $d['status']) ?>
                                            </span>
                                            <span class="text-xs bg-gray-200 text-gray-800 px-2 py-1 rounded">
                                                <?= htmlspecialchars($d['versao']) ?>
                                            </span>
                                        </div>

                                        <p class="text-sm text-gray-600 mb-3">
                                            <?= !empty($d['descricao']) ? htmlspecialchars(substr($d['descricao'], 0, 100)) . (strlen($d['descricao']) > 100 ? '...' : '') : 'Sem descrição' ?>
                                        </p>

                                        <div class="text-xs text-gray-500 space-y-1">
                                            <div><i class="fas fa-user mr-2"></i>Projetista: <?= htmlspecialchars($d['projetista_nome'] ?? 'N/A') ?></div>
                                            <div><i class="fas fa-calendar mr-2"></i>Criado em: <?= date('d/m/Y H:i', strtotime($d['created_at'])) ?></div>
                                            <?php if ($d['data_submissao']): ?>
                                                <div><i class="fas fa-paper-plane mr-2"></i>Submetido em: <?= date('d/m/Y H:i', strtotime($d['data_submissao'])) ?></div>
                                            <?php endif; ?>
                                            <div><i class="fas fa-paperclip mr-2"></i>Arquivos: <?= (int)$d['total_arquivos'] ?></div>
                                        </div>
                                    </div>

                                    <div class="flex gap-2 ml-4">
                                        <a href="?os_id=<?= (int)$osId ?>&desenho_id=<?= (int)$d['id'] ?>" class="btn btn-primary">
                                            <i class="fas fa-eye mr-1"></i>Visualizar
                                        </a>
                                        <?php if (in_array($usuarioPerfil, ['master', 'projetista', 'engenharia'])): ?>
                                            <button onclick="editarDesenho(<?= (int)$d['id'] ?>)" class="btn btn-secondary">
                                                <i class="fas fa-edit mr-1"></i>Editar
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="card text-center py-12">
                        <i class="fas fa-file-alt text-4xl text-gray-300 mb-4"></i>
                        <p class="text-gray-600">Nenhum desenho técnico cadastrado para esta O.S.</p>
                        <p class="text-sm text-gray-500 mt-2">Crie um novo desenho para começar o fluxo de aprovação.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Aba: Novo Desenho -->
            <div id="tab-novo" class="tab-content hidden">
                <div class="card max-w-3xl">
                    <h2 class="text-xl font-semibold text-gray-900 mb-6">Criar Novo Desenho Técnico</h2>
                    <form method="POST" action="../../api/desenho.php" enctype="multipart/form-data" id="form-novo-desenho">
                        <input type="hidden" name="acao" value="criar_desenho">
                        <input type="hidden" name="os_id" value="<?= (int)$osId ?>">

                        <!-- Título -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Título do Desenho *</label>
                            <input type="text" name="titulo" required placeholder="Ex: Dimensões Estrutura Principal"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <!-- Descrição -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Descrição</label>
                            <textarea name="descricao" rows="4" placeholder="Detalhes sobre o desenho, especificações, observações..."
                                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                        </div>

                        <!-- Qualidade Exigida -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Qualidade Exigida</label>
                            <select name="qualidade_exigida" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="normal">Normal</option>
                                <option value="certificada">Certificada</option>
                                <option value="alimentar">Alimentar</option>
                                <option value="clinica">Clínica</option>
                            </select>
                        </div>

                        <!-- Prioridade -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Prioridade</label>
                            <select name="prioridade" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="baixa">Baixa</option>
                                <option value="normal" selected>Normal</option>
                                <option value="alta">Alta</option>
                                <option value="critica">Crítica</option>
                            </select>
                        </div>

                        <!-- Upload de Arquivos -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Arquivos do Desenho</label>
                            <div class="preview-box">
                                <input type="file" id="arquivo-input" name="arquivos[]" multiple
                                       accept=".pdf,.dwg,.png,.jpg,.jpeg,.dxf,.3ds,.obj"
                                       style="display: none;">
                                <button type="button" onclick="document.getElementById('arquivo-input').click()" class="btn btn-primary">
                                    <i class="fas fa-cloud-upload-alt mr-2"></i>Selecionar Arquivos
                                </button>
                                <p class="text-sm text-gray-600 mt-3">PDF, DWG, PNG, JPG, DXF, 3D</p>
                                <div id="arquivo-lista" class="mt-4"></div>
                            </div>
                        </div>

                        <!-- Botões -->
                        <div class="flex gap-4">
                            <button type="submit" name="enviar" value="rascunho" class="btn btn-secondary">
                                <i class="fas fa-save mr-2"></i>Salvar como Rascunho
                            </button>
                            <button type="submit" name="enviar" value="submetido" class="btn btn-primary">
                                <i class="fas fa-paper-plane mr-2"></i>Enviar para Revisão
                            </button>
                            <button type="button" onclick="history.back()" class="btn btn-secondary">Cancelar</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Aba: Aprovações -->
            <div id="tab-aprovacoes" class="tab-content hidden">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="card">
                        <div class="text-sm text-gray-600 mb-2">Gerência</div>
                        <div class="text-2xl font-bold text-orange-600">
                            <?php
                            $stmt = $db->prepare("SELECT COUNT(*) FROM desenhos_tecnicos WHERE os_id = ? AND status IN ('submetido', 'em_revisao')");
                            $stmt->execute([$osId]);
                            echo $stmt->fetchColumn();
                            ?>
                        </div>
                        <p class="text-xs text-gray-500">Aguardando revisão</p>
                    </div>
                    <div class="card">
                        <div class="text-sm text-gray-600 mb-2">Aprovado</div>
                        <div class="text-2xl font-bold text-green-600">
                            <?php
                            $stmt = $db->prepare("SELECT COUNT(*) FROM desenhos_tecnicos WHERE os_id = ? AND status = 'aprovado'");
                            $stmt->execute([$osId]);
                            echo $stmt->fetchColumn();
                            ?>
                        </div>
                        <p class="text-xs text-gray-500">Desenhos liberados</p>
                    </div>
                    <div class="card">
                        <div class="text-sm text-gray-600 mb-2">Rejeitados</div>
                        <div class="text-2xl font-bold text-red-600">
                            <?php
                            $stmt = $db->prepare("SELECT COUNT(*) FROM desenhos_tecnicos WHERE os_id = ? AND status = 'rejeitado'");
                            $stmt->execute([$osId]);
                            echo $stmt->fetchColumn();
                            ?>
                        </div>
                        <p class="text-xs text-gray-500">Retornar ao projetista</p>
                    </div>
                </div>

                <div class="card">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Status das Aprovações</h3>
                    <div class="space-y-6">
                        <?php
                        $stmt = $db->prepare("
                            SELECT da.*, d.titulo, d.versao,
                                   u.nome AS usuario_nome
                            FROM desenhos_aprovaes da
                            INNER JOIN desenhos_tecnicos d ON d.id = da.desenho_id
                            LEFT JOIN usuarios u ON u.id = da.usuario_id
                            WHERE d.os_id = ?
                            ORDER BY da.created_at DESC
                        ");
                        $stmt->execute([$osId]);
                        $aprovaes = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        if (!empty($aprovaes)):
                            foreach ($aprovaes as $aprv):
                                $statusColor = $aprv['status'] === 'aprovado' ? '#10b981' : ($aprv['status'] === 'rejeitado' ? '#dc2626' : '#f59e0b');
                        ?>
                            <div class="border-l-4 pl-4" style="border-color: <?= htmlspecialchars($statusColor) ?>;">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-medium text-gray-900"><?= htmlspecialchars($aprv['titulo']) ?> (<?= htmlspecialchars($aprv['versao']) ?>)</p>
                                        <p class="text-sm text-gray-600 mt-1">
                                            <strong><?= ucfirst($aprv['etapa']) ?>:</strong>
                                            <span style="color: <?= htmlspecialchars($statusColor) ?>;">
                                                <?= ucfirst($aprv['status']) ?>
                                            </span>
                                        </p>
                                        <?php if ($aprv['usuario_nome']): ?>
                                            <p class="text-xs text-gray-500 mt-1">Por <?= htmlspecialchars($aprv['usuario_nome']) ?> em <?= date('d/m/Y H:i', strtotime($aprv['data_resposta'] ?? 'now')) ?></p>
                                        <?php endif; ?>
                                        <?php if ($aprv['observacoes']): ?>
                                            <p class="text-sm text-gray-700 mt-2 bg-gray-50 p-2 rounded">
                                                <strong>Observação:</strong> <?= htmlspecialchars($aprv['observacoes']) ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php
                            endforeach;
                        else:
                        ?>
                            <div class="text-center py-8 text-gray-600">
                                <i class="fas fa-inbox text-3xl text-gray-300 mb-3"></i>
                                <p>Nenhuma aprovação registrada</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- Sem O.S. selecionada -->
            <div class="card text-center py-12">
                <i class="fas fa-folder-open text-4xl text-gray-300 mb-4"></i>
                <p class="text-gray-600 text-lg">Selecione uma Ordem de Serviço para continuar</p>
                <p class="text-sm text-gray-500 mt-2">Acesse uma O.S. da listagem principal para gerenciar seus desenhos técnicos.</p>
                <a href="index.php" class="btn btn-primary mt-4 inline-block">
                    <i class="fas fa-arrow-left mr-2"></i>Voltar à Engenharia
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Abas
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const tab = btn.getAttribute('data-tab');

                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active', 'border-blue-600', 'text-blue-600'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));

                btn.classList.add('active', 'border-blue-600', 'text-blue-600');
                document.getElementById('tab-' + tab).classList.remove('hidden');
            });
        });

        // Upload de arquivos
        document.getElementById('arquivo-input')?.addEventListener('change', (e) => {
            const lista = document.getElementById('arquivo-lista');
            lista.innerHTML = '';

            Array.from(e.target.files).forEach(file => {
                const div = document.createElement('div');
                div.className = 'text-sm text-gray-700 p-2 bg-blue-50 rounded mt-2';
                div.textContent = file.name + ' (' + (file.size / 1024 / 1024).toFixed(2) + ' MB)';
                lista.appendChild(div);
            });
        });

        function editarDesenho(id) {
            alert('Edição de desenho: ' + id);
            // TODO: Implementar edição
        }
    </script>
    </div></div>
</div>
<?php include '../../includes/footer_vendedor.php'; ?>
