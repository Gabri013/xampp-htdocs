<?php
/**
 * Tokens de API — master gera/revoga tokens para integração externa.
 * O token cheio só aparece UMA vez (guardamos só o hash). Acesso: master.
 */

require_once '../../config/config.php';
require_once '../../includes/api_auth.php';
requirePermission(['master']);

$db = getDB();
ensureApiTokensSchema($db);

$tokenNovo = '';
$tokenNovoNome = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    if ($acao === 'criar') {
        $nome = trim($_POST['nome'] ?? '');
        $escopo = ($_POST['escopo'] ?? 'leitura') === 'completo' ? 'completo' : 'leitura';
        if ($nome === '') {
            setError('Dê um nome para o token (ex.: "Integração e-commerce").');
        } else {
            $t = gerarApiToken();
            $stmt = $db->prepare("INSERT INTO api_tokens (nome, token_hash, prefixo, escopo, criado_por) VALUES (?,?,?,?,?)");
            $stmt->execute([$nome, $t['hash'], $t['prefixo'], $escopo, (int) ($_SESSION['usuario_id'] ?? 0)]);
            $tokenNovo = $t['token'];      // mostrado uma única vez
            $tokenNovoNome = $nome;
        }
    } elseif ($acao === 'revogar') {
        $db->prepare("UPDATE api_tokens SET ativo = 0 WHERE id = ?")->execute([(int) ($_POST['id'] ?? 0)]);
        setSuccess('Token revogado.');
        header('Location: api_tokens.php');
        exit;
    } elseif ($acao === 'reativar') {
        $db->prepare("UPDATE api_tokens SET ativo = 1 WHERE id = ?")->execute([(int) ($_POST['id'] ?? 0)]);
        setSuccess('Token reativado.');
        header('Location: api_tokens.php');
        exit;
    } elseif ($acao === 'excluir') {
        $db->prepare("DELETE FROM api_tokens WHERE id = ?")->execute([(int) ($_POST['id'] ?? 0)]);
        setSuccess('Token excluído.');
        header('Location: api_tokens.php');
        exit;
    }
}

$tokens = $db->query("SELECT * FROM api_tokens ORDER BY ativo DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
$baseUrl = SITE_URL . '/api/v1.php';

$page_title = 'Tokens de API';
include '../../includes/header_vendedor.php';
?>
<div class="vend-layout">
    <?php include '../../includes/vend_sidebar.php'; ?>
    <div class="vend-main"><div class="vend-content">

    <div class="dash-head">
        <div>
            <h1 class="dash-head-title"><span class="dash-head-ic"><i class="fas fa-key"></i></span> Tokens de API</h1>
            <p class="dash-head-sub">Integre o ERP com sistemas externos (e-commerce, contabilidade…) via API REST.</p>
        </div>
    </div>

    <?php if ($tokenNovo !== ''): ?>
    <div class="dash-section">
        <div class="dash-section-head" style="background:var(--dash-green-bg)"><h2 style="color:var(--dash-green)"><i class="fas fa-circle-check"></i> Token "<?= htmlspecialchars($tokenNovoNome) ?>" criado</h2></div>
        <div class="dash-section-body">
            <p style="margin:0 0 8px;color:var(--dash-red);font-weight:600"><i class="fas fa-triangle-exclamation"></i> Copie agora — ele não será mostrado de novo.</p>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <code id="tokVal" style="background:#0f172a;color:#e2e8f0;padding:10px 14px;border-radius:8px;font-size:14px;word-break:break-all;flex:1;min-width:280px"><?= htmlspecialchars($tokenNovo) ?></code>
                <button type="button" class="dash-btn" onclick="navigator.clipboard.writeText(document.getElementById('tokVal').textContent).then(()=>{this.innerHTML='<i class=\'fas fa-check\'></i> Copiado'})"><i class="fas fa-copy"></i> Copiar</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="dash-grid cols-2">
        <!-- Criar -->
        <div class="dash-section" style="margin:0">
            <div class="dash-section-head"><h2><i class="fas fa-plus"></i> Gerar novo token</h2></div>
            <div class="dash-section-body">
                <form method="POST">
                    <input type="hidden" name="acao" value="criar">
                    <div class="dash-field grow" style="margin-bottom:12px"><label>Nome / finalidade</label>
                        <input type="text" name="nome" class="dash-input" placeholder="Ex.: Integração e-commerce" required></div>
                    <div class="dash-field grow" style="margin-bottom:14px"><label>Permissão</label>
                        <select name="escopo" class="dash-select">
                            <option value="leitura">Somente leitura (consultar dados)</option>
                            <option value="completo">Completo (consultar + criar registros)</option>
                        </select></div>
                    <button type="submit" class="dash-btn"><i class="fas fa-key"></i> Gerar token</button>
                </form>
            </div>
        </div>

        <!-- Como usar -->
        <div class="dash-section" style="margin:0">
            <div class="dash-section-head"><h2><i class="fas fa-book"></i> Como usar</h2></div>
            <div class="dash-section-body" style="font-size:13px">
                <p style="margin:0 0 8px">Envie o token no cabeçalho de cada requisição:</p>
                <code style="display:block;background:#0f172a;color:#e2e8f0;padding:10px;border-radius:8px;white-space:pre-wrap;word-break:break-all">Authorization: Bearer SEU_TOKEN</code>
                <p style="margin:10px 0 6px">Endpoints (GET):</p>
                <code style="display:block;background:#f1f5f9;padding:10px;border-radius:8px;white-space:pre-wrap;word-break:break-all"><?= htmlspecialchars($baseUrl) ?>?resource=<b>clientes</b>
<?= htmlspecialchars($baseUrl) ?>?resource=<b>produtos</b>
<?= htmlspecialchars($baseUrl) ?>?resource=<b>ordens-servico</b>
<?= htmlspecialchars($baseUrl) ?>?resource=<b>ordens-producao</b>
<?= htmlspecialchars($baseUrl) ?>?resource=<b>estoque</b></code>
                <p style="margin:10px 0 0;color:var(--dash-text-sub)">Chame <a href="<?= htmlspecialchars($baseUrl) ?>" target="_blank"><?= htmlspecialchars($baseUrl) ?></a> com o token para ver a lista completa.</p>
            </div>
        </div>
    </div>

    <div class="dash-section">
        <div class="dash-section-head"><h2><i class="fas fa-list"></i> Tokens (<?= count($tokens) ?>)</h2></div>
        <div class="dash-table-wrap">
            <table class="dash-table">
                <thead><tr><th>Nome</th><th>Prefixo</th><th>Escopo</th><th>Status</th><th>Último uso</th><th>Criado</th><th style="text-align:center">Ações</th></tr></thead>
                <tbody>
                    <?php if (empty($tokens)): ?>
                        <tr><td colspan="7" class="dash-empty">Nenhum token criado ainda.</td></tr>
                    <?php else: foreach ($tokens as $t): ?>
                        <tr style="<?= $t['ativo'] ? '' : 'opacity:.55' ?>">
                            <td style="font-weight:600"><?= htmlspecialchars($t['nome']) ?></td>
                            <td><code><?= htmlspecialchars($t['prefixo']) ?></code></td>
                            <td><span class="dash-chip <?= $t['escopo'] === 'completo' ? 'purple' : 'blue' ?>"><?= htmlspecialchars($t['escopo']) ?></span></td>
                            <td><span class="dash-chip <?= $t['ativo'] ? 'green' : 'red' ?>"><?= $t['ativo'] ? 'ativo' : 'revogado' ?></span></td>
                            <td><?= $t['ultimo_uso'] ? date('d/m/Y H:i', strtotime($t['ultimo_uso'])) : '—' ?></td>
                            <td><?= date('d/m/Y', strtotime($t['created_at'])) ?></td>
                            <td style="text-align:center;white-space:nowrap">
                                <?php if ($t['ativo']): ?>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('Revogar este token? Integrações que o usam vão parar de funcionar.')">
                                        <input type="hidden" name="acao" value="revogar"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                        <button class="dash-btn sm red" type="submit"><i class="fas fa-ban"></i> Revogar</button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="acao" value="reativar"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                        <button class="dash-btn sm slate" type="submit"><i class="fas fa-rotate-left"></i> Reativar</button>
                                    </form>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('Excluir permanentemente?')">
                                        <input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                        <button class="dash-btn sm" type="submit" style="background:var(--dash-slate)"><i class="fas fa-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    </div></div>
</div>
<?php include '../../includes/footer_vendedor.php'; ?>
