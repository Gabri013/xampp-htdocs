<?php
// includes/vend_sidebar.php
// Sidebar reutilizável para todos os módulos do ERP.
// Cada link só aparece para os tipos de usuário aceitos pelo
// requirePermission() da página de destino.

$tipo_usuario = $_SESSION['usuario_tipo'] ?? 'vendedor';

// Grupos de visibilidade (espelham o requirePermission de cada página)
$ve_dashboard      = hasPermission(['master', 'vendedor']);
$ve_gerente        = hasPermission(['master', 'gerente']);
$ve_projetista     = hasPermission(['master', 'projetista', 'engenharia']);
$ve_os_lista       = hasPermission(['master', 'vendedor', 'projetista', 'gerente']);
$ve_producao_gestao = hasPermission(['master', 'gerente', 'producao']);
$ve_dash_producao  = hasPermission(['master', 'dashboard_producao', 'gerente', 'producao']);
$ve_vendas         = hasPermission(['master', 'vendedor']);
$ve_nova_os        = hasPermission(['master', 'vendedor', 'projetista', 'gerente']);
$ve_engenharia     = hasPermission(['master', 'gerente', 'producao', 'projetista', 'engenharia']);
$ve_cadastros      = hasPermission(['master', 'vendedor']);
$ve_usuarios       = hasPermission(['master']);
$ve_faturamento    = hasPermission(['master', 'vendedor']);
$ve_contas         = hasPermission(['master']);
$ve_relatorios     = hasPermission(['master', 'vendedor']);
$ve_expediente     = hasPermission(['master', 'gerente']);
$ve_admin          = hasPermission(['master']);

// Painéis integrados (espelham o requirePermission de cada página)
$ve_gestao_prod    = hasPermission(['master', 'gerente', 'dashboard_producao', 'producao']);
$ve_op             = hasPermission(['master', 'gerente', 'producao', 'projetista', 'programacao']);
$ve_etiquetas      = hasPermission(['master', 'gerente', 'dashboard_producao', 'producao', 'projetista']);
$ve_apontamento    = hasPermission(['master', 'gerente', 'producao', 'engenharia', 'programacao', 'corte', 'dobra', 'tubo', 'solda', 'mobiliario', 'coccao', 'refrigeracao', 'acabamento', 'montagem', 'embalagem', 'finalizacao']);
$ve_mrp            = hasPermission(['master', 'gerente', 'producao']);
$ve_estoque        = hasPermission(['master', 'gerente', 'dashboard_producao', 'producao']);
$ve_importar_jotec = hasPermission(['master', 'estoque', 'gerente']);
$ve_expedicao      = hasPermission(['master', 'gerente', 'expedicao', 'dashboard_producao']);
$ve_sac            = hasPermission(['master', 'gerente', 'sac', 'dashboard_producao']);
$ve_custos         = hasPermission(['master', 'gerente', 'financeiro']);
$ve_desenho        = hasPermission(['master', 'gerente', 'producao', 'projetista', 'engenharia']);

// Setores de produção (ordem do fluxo): gestão vê todos; cada setor vê o seu.
// O setor de engenharia foi unificado no Projetista — a etapa é operada no
// Painel do Projetista (modules/projetista/index.php), não em painel próprio.
$setores_sidebar = [
    'programacao'  => ['label' => 'Programação',  'icon' => 'fa-calendar-alt',  'page' => 'programacao.php'],
    'corte'        => ['label' => 'Corte',        'icon' => 'fa-cut',           'page' => 'corte.php'],
    'dobra'        => ['label' => 'Dobra',        'icon' => 'fa-dharmachakra',  'page' => 'dobra.php'],
    'tubo'         => ['label' => 'Tubo',         'icon' => 'fa-grip-lines',    'page' => 'tubo.php'],
    'solda'        => ['label' => 'Solda',        'icon' => 'fa-fire',          'page' => 'solda.php'],
    'mobiliario'   => ['label' => 'Mobiliário',   'icon' => 'fa-couch',         'page' => 'mobiliario.php'],
    'coccao'       => ['label' => 'Cocção',       'icon' => 'fa-burn',          'page' => 'coccao.php'],
    'refrigeracao' => ['label' => 'Refrigeração', 'icon' => 'fa-snowflake',     'page' => 'refrigeracao.php'],
    'acabamento'   => ['label' => 'Acabamento',   'icon' => 'fa-paint-roller',  'page' => 'acabamento.php'],
    'montagem'     => ['label' => 'Montagem',     'icon' => 'fa-tools',         'page' => 'montagem.php'],
    'embalagem'    => ['label' => 'Embalagem',    'icon' => 'fa-box-open',      'page' => 'embalagem.php'],
    'finalizacao'  => ['label' => 'Finalização',  'icon' => 'fa-flag-checkered', 'page' => 'finalizacao.php'],
];
// Só a gestão vê a lista completa de setores na sidebar. O projetista
// acompanha a produção pelo Kanban e pela lista de O.S. (mantém permissão
// de leitura dos painéis se navegar direto), e sua engenharia já está no
// grupo Principal — não precisa da lista de setores poluindo o menu.
$ve_todos_setores = hasPermission(['master', 'gerente', 'producao']);
$setores_visiveis = [];
foreach ($setores_sidebar as $setor_key => $setor_info) {
    // finalizacao.php não aceita projetista/producao (requirePermission da página)
    if ($setor_key === 'finalizacao' && !hasPermission(['master', 'gerente', 'producao', 'finalizacao'])) {
        continue;
    }
    if ($ve_todos_setores || hasPermission([$setor_key])) {
        $setores_visiveis[$setor_key] = $setor_info;
    }
}
$usuario = getCurrentUser();
$qtd_notificacoes_nao_lidas = $GLOBALS['qtd_notificacoes_nao_lidas'] ?? 0;

if ($usuario && !empty($usuario['id'])) {
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM notificacoes WHERE usuario_id = ? AND lida = 0");
        $stmt->execute([$usuario['id']]);
        $qtd_notificacoes_nao_lidas = (int) $stmt->fetchColumn();
    } catch (Exception $e) {}
}

$modulo_tipo = $GLOBALS['modulo_tipo'] ?? '';
$current_path = $_SERVER['PHP_SELF'] ?? '';
$current_file = basename($current_path);

function czNavActive(string $file, string $dir = ''): string {
    $path = $_SERVER['PHP_SELF'] ?? '';
    if (basename($path) !== $file) return '';
    if ($dir !== '' && strpos($path, "/$dir/") === false) return '';
    return 'active';
}

$logo_sub = getTipoUsuarioNome($tipo_usuario);
?>
<aside class="vend-sidebar" id="czSidebar">
    <button class="vend-sidebar-toggle" id="czSidebarToggle" title="Colapsar/Expandir" style="position:absolute;top:10px;right:-12px;background:#fff;border:1px solid #e9ecef;border-radius:50%;width:24px;height:24px;display:flex;align-items:center;justify-content:center;cursor:pointer"><i class="fas fa-chevron-left" style="font-size:10px"></i></button>
    <div class="vend-sidebar-logo">
        <div class="vend-logo-icon"><i class="fas fa-fire"></i></div>
        <div>
            <div class="vend-logo-text">Cozinca Inox</div>
            <div class="vend-logo-sub"><?php echo htmlspecialchars($logo_sub); ?></div>
        </div>
    </div>

    <div class="vend-nav-group">
        <span class="vend-nav-label">Principal</span>
        <?php if ($ve_dashboard): ?>
        <a href="<?php echo SITE_URL; ?>/modules/vendas/dashboard_vendedor.php" class="vend-nav-item <?php echo czNavActive('dashboard_vendedor.php'); ?>"><i class="fas fa-th-large"></i> Dashboard</a>
        <?php endif; ?>
        <?php if ($ve_gerente): ?>
        <a href="<?php echo SITE_URL; ?>/modules/os/gerente.php" class="vend-nav-item <?php echo czNavActive('gerente.php'); ?>"><i class="fas fa-user-tie"></i> Painel do Gerente</a>
        <?php endif; ?>
        <?php if ($ve_projetista): ?>
        <a href="<?php echo SITE_URL; ?>/modules/projetista/index.php" class="vend-nav-item <?php echo czNavActive('index.php', 'projetista') ?: czNavActive('engenharia_setor.php'); ?>"><i class="fas fa-drafting-compass"></i> Projetista</a>
        <?php endif; ?>
        <?php if ($ve_os_lista): ?>
        <a href="<?php echo SITE_URL; ?>/modules/os/vendedor.php" class="vend-nav-item <?php echo czNavActive('vendedor.php'); ?>"><i class="fas fa-clipboard-list"></i> O.S.</a>
        <?php endif; ?>
        <?php if (hasPermission(['master', 'gerente', 'producao', 'vendedor', 'projetista'])): ?>
        <a href="<?php echo SITE_URL; ?>/modules/os/kanban.php" class="vend-nav-item <?php echo czNavActive('kanban.php'); ?>"><i class="fas fa-columns"></i> Kanban</a>
        <?php endif; ?>
        <?php if ($ve_producao_gestao): ?>
        <a href="<?php echo SITE_URL; ?>/modules/os/producao.php" class="vend-nav-item <?php echo czNavActive('producao.php', 'os'); ?>"><i class="fas fa-industry"></i> Produção</a>
        <a href="<?php echo SITE_URL; ?>/modules/os/estatisticas.php" class="vend-nav-item <?php echo czNavActive('estatisticas.php'); ?>"><i class="fas fa-chart-line"></i> Estatísticas</a>
        <?php endif; ?>
        <?php if ($ve_dash_producao): ?>
        <a href="<?php echo SITE_URL; ?>/modules/os/dashboard_producao.php" class="vend-nav-item <?php echo czNavActive('dashboard_producao.php'); ?>"><i class="fas fa-tv"></i> Dashboard Produção</a>
        <?php endif; ?>
    </div>

    <?php if (hasPermission(['master', 'vendedor', 'gerente'])): ?>
    <hr class="vend-nav-divider">
    <div class="vend-nav-group">
        <span class="vend-nav-label">CRM</span>
        <a href="<?php echo SITE_URL; ?>/modules/crm/index.php" class="vend-nav-item <?php echo czNavActive('index.php', 'crm'); ?>"><i class="fas fa-filter"></i> Pipeline</a>
        <a href="<?php echo SITE_URL; ?>/modules/crm/contatos.php" class="vend-nav-item <?php echo czNavActive('contatos.php'); ?>"><i class="fas fa-address-book"></i> Contatos</a>
    </div>
    <?php endif; ?>

    <?php if ($ve_vendas): ?>
    <hr class="vend-nav-divider">
    <div class="vend-nav-group">
        <span class="vend-nav-label">Vendas</span>
        <a href="<?php echo SITE_URL; ?>/modules/vendas/index.php" class="vend-nav-item <?php echo czNavActive('index.php', 'vendas'); ?>"><i class="fas fa-shopping-cart"></i> Vendas</a>
        <a href="<?php echo SITE_URL; ?>/modules/vendas/nova_venda.php" class="vend-nav-item <?php echo czNavActive('nova_venda.php'); ?>"><i class="fas fa-cart-plus"></i> Nova Venda</a>
        <a href="<?php echo SITE_URL; ?>/modules/orcamentos/index.php" class="vend-nav-item <?php echo czNavActive('index.php', 'orcamentos'); ?>"><i class="fas fa-file-invoice"></i> Orçamentos</a>
        <a href="<?php echo SITE_URL; ?>/modules/orcamentos/criar_orcamento.php" class="vend-nav-item <?php echo czNavActive('criar_orcamento.php'); ?>"><i class="fas fa-file-medical"></i> Novo Orçamento</a>
        <a href="<?php echo SITE_URL; ?>/modules/vendas/conteudos_digitais.php" class="vend-nav-item <?php echo czNavActive('conteudos_digitais.php'); ?>"><i class="fas fa-photo-video"></i> Conteúdos Digitais</a>
    </div>
    <?php endif; ?>

    <?php if (!empty($setores_visiveis)): ?>
    <hr class="vend-nav-divider">
    <div class="vend-nav-group">
        <span class="vend-nav-label">Setores</span>
        <?php foreach ($setores_visiveis as $setor_key => $setor_info): ?>
        <a href="<?php echo SITE_URL; ?>/modules/os/<?php echo $setor_info['page']; ?>" class="vend-nav-item <?php echo czNavActive($setor_info['page']); ?>"><i class="fas <?php echo $setor_info['icon']; ?>"></i> <?php echo $setor_info['label']; ?></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($ve_gestao_prod || $ve_op || $ve_apontamento || $ve_etiquetas || $ve_mrp): ?>
    <hr class="vend-nav-divider">
    <div class="vend-nav-group">
        <span class="vend-nav-label">Gestão de Produção</span>
        <?php if ($ve_gestao_prod): ?>
        <a href="<?php echo SITE_URL; ?>/modules/os/painel_gestao.php" class="vend-nav-item <?php echo czNavActive('painel_gestao.php'); ?>"><i class="fas fa-tachometer-alt"></i> Painel de Gestão</a>
        <?php endif; ?>
        <?php if ($ve_op): ?>
        <a href="<?php echo SITE_URL; ?>/modules/os/ordem_producao.php" class="vend-nav-item <?php echo czNavActive('ordem_producao.php'); ?>"><i class="fas fa-clipboard-check"></i> Ordens de Produção</a>
        <?php endif; ?>
        <?php if ($ve_apontamento): ?>
        <a href="<?php echo SITE_URL; ?>/modules/os/apontamento_visual.php" class="vend-nav-item <?php echo czNavActive('apontamento_visual.php'); ?>"><i class="fas fa-stopwatch"></i> Apontamento Visual</a>
        <?php endif; ?>
        <?php if ($ve_etiquetas): ?>
        <a href="<?php echo SITE_URL; ?>/modules/os/gerar_etiquetas.php" class="vend-nav-item <?php echo czNavActive('gerar_etiquetas.php'); ?>"><i class="fas fa-tags"></i> Etiquetas &amp; QR</a>
        <?php endif; ?>
        <?php if ($ve_gestao_prod): ?>
        <a href="<?php echo SITE_URL; ?>/modules/os/timeline_os.php" class="vend-nav-item <?php echo czNavActive('timeline_os.php'); ?>"><i class="fas fa-stream"></i> Timeline de O.S.</a>
        <a href="<?php echo SITE_URL; ?>/modules/producao/bom_produto.php" class="vend-nav-item <?php echo czNavActive('bom_produto.php'); ?>"><i class="fas fa-sitemap"></i> BOM de Produtos</a>
        <?php endif; ?>
        <?php if ($ve_mrp): ?>
        <a href="<?php echo SITE_URL; ?>/modules/producao/mrp_dashboard.php" class="vend-nav-item <?php echo czNavActive('mrp_dashboard.php'); ?>"><i class="fas fa-boxes"></i> MRP</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($ve_estoque || $ve_expedicao || $ve_sac || $ve_importar_jotec): ?>
    <hr class="vend-nav-divider">
    <div class="vend-nav-group">
        <span class="vend-nav-label">Operações</span>
        <?php if ($ve_estoque): ?>
        <a href="<?php echo SITE_URL; ?>/modules/estoque/dashboard_estoque.php" class="vend-nav-item <?php echo czNavActive('dashboard_estoque.php'); ?>"><i class="fas fa-warehouse"></i> Estoque</a>
        <?php endif; ?>
        <?php if ($ve_expedicao): ?>
        <a href="<?php echo SITE_URL; ?>/modules/expedicao/dashboard_expedicao.php" class="vend-nav-item <?php echo czNavActive('dashboard_expedicao.php'); ?>"><i class="fas fa-truck"></i> Expedição</a>
        <?php endif; ?>
        <?php if ($ve_sac): ?>
        <a href="<?php echo SITE_URL; ?>/modules/sac/dashboard_chamados.php" class="vend-nav-item <?php echo czNavActive('dashboard_chamados.php'); ?>"><i class="fas fa-headset"></i> SAC / Chamados</a>
        <?php endif; ?>
        <?php if ($ve_importar_jotec): ?>
        <a href="<?php echo SITE_URL; ?>/modules/estoque/importar_jotec.php" class="vend-nav-item <?php echo czNavActive('importar_jotec.php'); ?>"><i class="fas fa-file-import"></i> Importar JOTEC</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($ve_nova_os || $ve_engenharia || $ve_expediente || $ve_desenho): ?>
    <hr class="vend-nav-divider">
    <div class="vend-nav-group">
        <span class="vend-nav-label">Ações</span>
        <?php if ($ve_nova_os): ?>
        <a href="<?php echo SITE_URL; ?>/modules/os/nova_os_independente.php" class="vend-nav-item <?php echo czNavActive('nova_os_independente.php'); ?>"><i class="fas fa-plus-square"></i> Lançar O.S.</a>
        <?php endif; ?>
        <?php if ($ve_engenharia): ?>
        <a href="<?php echo SITE_URL; ?>/modules/engenharia/index.php" class="vend-nav-item <?php echo czNavActive('index.php', 'engenharia'); ?>"><i class="fas fa-cogs"></i> Engenharia de Produto</a>
        <?php endif; ?>
        <?php if ($ve_desenho): ?>
        <a href="<?php echo SITE_URL; ?>/modules/engenharia/desenho_tecnico.php" class="vend-nav-item <?php echo czNavActive('desenho_tecnico.php'); ?>"><i class="fas fa-pencil-ruler"></i> Desenho Técnico</a>
        <?php endif; ?>
        <?php if ($ve_expediente): ?>
        <a href="<?php echo SITE_URL; ?>/modules/os/controle_expediente.php" class="vend-nav-item <?php echo czNavActive('controle_expediente.php'); ?>"><i class="fas fa-user-clock"></i> Expediente</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($ve_cadastros || $ve_usuarios): ?>
    <hr class="vend-nav-divider">
    <div class="vend-nav-group">
        <span class="vend-nav-label">Cadastros</span>
        <?php if ($ve_cadastros): ?>
        <a href="<?php echo SITE_URL; ?>/modules/cadastros/clientes.php" class="vend-nav-item <?php echo czNavActive('clientes.php'); ?>"><i class="fas fa-users"></i> Clientes</a>
        <a href="<?php echo SITE_URL; ?>/modules/cadastros/produtos.php" class="vend-nav-item <?php echo czNavActive('produtos.php'); ?>"><i class="fas fa-box"></i> Produtos</a>
        <?php endif; ?>
        <?php if ($ve_usuarios): ?>
        <a href="<?php echo SITE_URL; ?>/modules/cadastros/usuarios.php" class="vend-nav-item <?php echo czNavActive('usuarios.php'); ?>"><i class="fas fa-user-cog"></i> Usuários</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($ve_faturamento || $ve_contas || $ve_relatorios || $ve_custos): ?>
    <hr class="vend-nav-divider">
    <div class="vend-nav-group">
        <span class="vend-nav-label">Financeiro</span>
        <?php if ($ve_faturamento): ?>
        <a href="<?php echo SITE_URL; ?>/modules/financeiro/faturamento.php" class="vend-nav-item <?php echo czNavActive('faturamento.php'); ?>"><i class="fas fa-file-invoice-dollar"></i> Faturamento</a>
        <?php endif; ?>
        <?php if ($ve_contas): ?>
        <a href="<?php echo SITE_URL; ?>/modules/financeiro/index.php" class="vend-nav-item <?php echo czNavActive('index.php', 'financeiro'); ?>"><i class="fas fa-arrow-down"></i> Contas a Receber</a>
        <a href="<?php echo SITE_URL; ?>/modules/financeiro/contas_pagar.php" class="vend-nav-item <?php echo czNavActive('contas_pagar.php'); ?>"><i class="fas fa-arrow-up"></i> Contas a Pagar</a>
        <?php endif; ?>
        <?php if ($ve_custos): ?>
        <a href="<?php echo SITE_URL; ?>/modules/financeiro/dashboard_custos.php" class="vend-nav-item <?php echo czNavActive('dashboard_custos.php'); ?>"><i class="fas fa-coins"></i> Custos</a>
        <?php endif; ?>
        <?php if ($ve_relatorios): ?>
        <a href="<?php echo SITE_URL; ?>/modules/relatorios/index.php" class="vend-nav-item <?php echo czNavActive('index.php', 'relatorios'); ?>"><i class="fas fa-chart-bar"></i> Relatórios</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($ve_admin): ?>
    <hr class="vend-nav-divider">
    <div class="vend-nav-group">
        <span class="vend-nav-label">Administração</span>
        <a href="<?php echo SITE_URL; ?>/modules/admin/exportador_interface.php" class="vend-nav-item <?php echo czNavActive('exportador_interface.php'); ?>"><i class="fas fa-download"></i> Exportador de Dados</a>
        <a href="<?php echo SITE_URL; ?>/modules/dashboard/builder.php" class="vend-nav-item <?php echo czNavActive('builder.php'); ?>"><i class="fas fa-palette"></i> Dashboard Builder</a>
        <a href="<?php echo SITE_URL; ?>/modules/admin/logs_retorno.php" class="vend-nav-item <?php echo czNavActive('logs_retorno.php'); ?>"><i class="fas fa-history"></i> Logs do Sistema</a>
        <a href="<?php echo SITE_URL; ?>/modules/cadastros/logs_exclusao.php" class="vend-nav-item <?php echo czNavActive('logs_exclusao.php'); ?>"><i class="fas fa-trash-restore"></i> Logs de Exclusão</a>
    </div>
    <?php endif; ?>

    <hr class="vend-nav-divider">

    <div class="vend-nav-group">
        <span class="vend-nav-label">Alertas</span>
        <a href="<?php echo SITE_URL; ?>/modules/os/scan.php" class="vend-nav-item <?php echo czNavActive('scan.php'); ?>"><i class="fas fa-qrcode"></i> Escanear O.P.</a>
        <a href="<?php echo SITE_URL; ?>/modules/notificacoes/index.php" class="vend-nav-item <?php echo czNavActive('index.php', 'notificacoes'); ?>">
            <i class="fas fa-bell"></i> Notificações
            <span class="vend-nav-badge" id="czNotifBadge" <?php echo $qtd_notificacoes_nao_lidas > 0 ? '' : 'style="display:none"'; ?>><?php echo $qtd_notificacoes_nao_lidas; ?></span>
        </a>
    </div>

    <hr class="vend-nav-divider">

    <div class="vend-nav-group">
        <a href="<?php echo SITE_URL; ?>/logout.php" class="vend-nav-item"><i class="fas fa-sign-out-alt"></i> Sair</a>
    </div>
</aside>
