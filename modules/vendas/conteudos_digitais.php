<?php
require_once '../../config/config.php';
require_once '../../includes/engenharia.php';

requirePermission(['master', 'vendedor']);

$page_title = 'Conteudos Digitais';
$db = getDB();
$usuario_logado = getCurrentUser();
ensureEngenhariaSchema($db);

function ensureConteudosDigitaisSchema(PDO $db): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;

    if (!shouldRunSchemaSync('conteudos_digitais', 86400)) {
        return;
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS conteudos_digitais (
            id INT AUTO_INCREMENT PRIMARY KEY,
            titulo VARCHAR(160) NOT NULL,
            tipo ENUM('catalogo', 'tabela_preco', 'fotos_produtos') NOT NULL DEFAULT 'catalogo',
            categoria VARCHAR(120) NOT NULL DEFAULT '',
            descricao TEXT NULL,
            produto_id INT NULL,
            link_externo TEXT NULL,
            arquivo_nome VARCHAR(255) NULL,
            arquivo_original VARCHAR(255) NULL,
            versao VARCHAR(30) NOT NULL DEFAULT '1.0',
            validade DATE NULL,
            status ENUM('ativo', 'expirado', 'em_revisao') NOT NULL DEFAULT 'em_revisao',
            publico_acesso ENUM('interno', 'comercial', 'gestao') NOT NULL DEFAULT 'comercial',
            fixado TINYINT(1) NOT NULL DEFAULT 0,
            ordem_exibicao INT NOT NULL DEFAULT 0,
            usuario_id INT NULL,
            aprovado_por INT NULL,
            aprovado_em DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $colunas = $db->query("SHOW COLUMNS FROM conteudos_digitais")->fetchAll(PDO::FETCH_COLUMN);
    $alteracoes = [
        'categoria' => "ALTER TABLE conteudos_digitais ADD COLUMN categoria VARCHAR(120) NOT NULL DEFAULT '' AFTER tipo",
        'produto_id' => "ALTER TABLE conteudos_digitais ADD COLUMN produto_id INT NULL AFTER descricao",
        'versao' => "ALTER TABLE conteudos_digitais ADD COLUMN versao VARCHAR(30) NOT NULL DEFAULT '1.0' AFTER arquivo_original",
        'validade' => "ALTER TABLE conteudos_digitais ADD COLUMN validade DATE NULL AFTER versao",
        'publico_acesso' => "ALTER TABLE conteudos_digitais ADD COLUMN publico_acesso ENUM('interno', 'comercial', 'gestao') NOT NULL DEFAULT 'comercial' AFTER status",
        'fixado' => "ALTER TABLE conteudos_digitais ADD COLUMN fixado TINYINT(1) NOT NULL DEFAULT 0 AFTER publico_acesso",
        'aprovado_por' => "ALTER TABLE conteudos_digitais ADD COLUMN aprovado_por INT NULL AFTER usuario_id",
        'aprovado_em' => "ALTER TABLE conteudos_digitais ADD COLUMN aprovado_em DATETIME NULL AFTER aprovado_por",
    ];

    foreach ($alteracoes as $coluna => $sql) {
        if (!in_array($coluna, $colunas, true)) {
            $db->exec($sql);
        }
    }

    $tipoColuna = (string) ($db->query("SHOW COLUMNS FROM conteudos_digitais LIKE 'tipo'")->fetch(PDO::FETCH_ASSOC)['Type'] ?? '');
    if ($tipoColuna !== '' && stripos($tipoColuna, 'fotos_produtos') === false) {
        $db->exec("
            ALTER TABLE conteudos_digitais
            MODIFY COLUMN tipo ENUM('catalogo', 'tabela_preco', 'fotos_produtos') NOT NULL DEFAULT 'catalogo'
        ");
    }

    $statusColuna = (string) ($db->query("SHOW COLUMNS FROM conteudos_digitais LIKE 'status'")->fetch(PDO::FETCH_ASSOC)['Type'] ?? '');
    if ($statusColuna !== '' && stripos($statusColuna, 'em_revisao') === false) {
        $db->exec("
            ALTER TABLE conteudos_digitais
            MODIFY COLUMN status ENUM('ativo', 'expirado', 'em_revisao') NOT NULL DEFAULT 'em_revisao'
        ");
    }

    if ($statusColuna !== '' && stripos($statusColuna, 'inativo') !== false) {
        $db->exec("UPDATE conteudos_digitais SET status = 'expirado' WHERE status = 'inativo'");
    }
}

function getTipoLabelConteudo(string $tipo): string
{
    $labels = [
        'fotos_produtos' => 'Fotos',
        'catalogo' => 'Catalogos',
        'tabela_preco' => 'Planilhas',
    ];
    return $labels[$tipo] ?? 'Arquivos';
}

function getTagConteudo(array $conteudo): array
{
    $criado = strtotime((string) ($conteudo['created_at'] ?? ''));
    $atualizado = strtotime((string) ($conteudo['updated_at'] ?? ''));

    if (!empty($conteudo['fixado'])) {
        return ['texto' => 'oficial', 'classe' => 'tag-oficial'];
    }
    if ($criado >= strtotime('-7 days')) {
        return ['texto' => 'novo', 'classe' => 'tag-novo'];
    }
    if ($atualizado >= strtotime('-7 days')) {
        return ['texto' => 'atualizado', 'classe' => 'tag-atualizado'];
    }

    return ['texto' => '', 'classe' => ''];
}

function normalizarArquivosUpload(array $arquivos): array
{
    $lista = [];

    if (!isset($arquivos['name'])) {
        return $lista;
    }

    if (is_array($arquivos['name'])) {
        $total = count($arquivos['name']);
        for ($i = 0; $i < $total; $i++) {
            $lista[] = [
                'name' => $arquivos['name'][$i] ?? '',
                'type' => $arquivos['type'][$i] ?? '',
                'tmp_name' => $arquivos['tmp_name'][$i] ?? '',
                'error' => $arquivos['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $arquivos['size'][$i] ?? 0,
            ];
        }
        return $lista;
    }

    $lista[] = $arquivos;
    return $lista;
}

$categoriasFotosFixas = [
    'Mobiliario',
    'Refrigeracao',
    'Coccao',
    'Distribuicao',
    'Bar',
    'Hospitalar',
    'Construcao civil',
];

ensureConteudosDigitaisSchema($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $usuario_logado['tipo'] === 'master') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'salvar_conteudo') {
        $titulo = sanitize($_POST['titulo'] ?? '');
        $tipo = sanitize($_POST['tipo'] ?? 'catalogo');
        $categoria = sanitize($_POST['categoria'] ?? '');
        $descricao = sanitize($_POST['descricao'] ?? '');
        $arquivosUpload = array_values(array_filter(normalizarArquivosUpload($_FILES['arquivo'] ?? []), static function (array $arquivo): bool {
            return (int) ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        }));

        if ($titulo === '') {
            setError('Informe um titulo curto para o arquivo.');
        } elseif ($categoria === '') {
            setError('Todo arquivo precisa ter categoria.');
        } elseif (empty($arquivosUpload)) {
            setError('Selecione ao menos um arquivo para enviar.');
        } else {
            try {
                $stmt = $db->prepare("
                    INSERT INTO conteudos_digitais (
                        titulo, tipo, categoria, descricao, arquivo_nome, arquivo_original,
                        versao, status, publico_acesso, fixado, ordem_exibicao, usuario_id, aprovado_por, aprovado_em
                    ) VALUES (?, ?, ?, ?, ?, ?, '1.0', 'ativo', 'comercial', 0, 0, ?, ?, ?)
                ");

                $totalArquivos = count($arquivosUpload);
                foreach ($arquivosUpload as $indice => $arquivoUpload) {
                    if (($arquivoUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                        throw new RuntimeException('Um dos arquivos selecionados nao pode ser enviado.');
                    }

                    $upload = uploadFile($arquivoUpload, 'conteudos_digitais');
                    if (!$upload['success']) {
                        throw new RuntimeException($upload['message']);
                    }

                    $tituloRegistro = $titulo;
                    if ($totalArquivos > 1) {
                        $nomeBaseArquivo = pathinfo((string) ($upload['original_name'] ?? $arquivoUpload['name'] ?? ''), PATHINFO_FILENAME);
                        $tituloRegistro = trim($titulo . ' - ' . $nomeBaseArquivo);
                    }

                    $stmt->execute([
                        $tituloRegistro,
                        $tipo,
                        $categoria,
                        $descricao,
                        $upload['filename'],
                        $upload['original_name'] ?? $arquivoUpload['name'],
                        (int) $usuario_logado['id'],
                        (int) $usuario_logado['id'],
                        date('Y-m-d H:i:s'),
                    ]);
                }

                setSuccess($totalArquivos > 1 ? 'Arquivos enviados com sucesso!' : 'Arquivo enviado com sucesso!');
                header('Location: conteudos_digitais.php');
                exit;
            } catch (Throwable $e) {
                setError('Erro ao enviar arquivo: ' . $e->getMessage());
            }
        }
    } elseif ($acao === 'excluir_conteudo') {
        $id = (int) ($_POST['id'] ?? 0);
        try {
            $stmt = $db->prepare("SELECT arquivo_nome FROM conteudos_digitais WHERE id = ?");
            $stmt->execute([$id]);
            $arquivoNome = $stmt->fetchColumn();

            if ($arquivoNome) {
                @deleteFile(rtrim(UPLOAD_PATH, '/') . '/conteudos_digitais/' . $arquivoNome);
            }

            $stmtDelete = $db->prepare("DELETE FROM conteudos_digitais WHERE id = ?");
            $stmtDelete->execute([$id]);
            setSuccess('Arquivo removido com sucesso!');
            header('Location: conteudos_digitais.php');
            exit;
        } catch (Throwable $e) {
            setError('Erro ao excluir arquivo: ' . $e->getMessage());
        }
    }
}

$busca = trim((string) ($_GET['q'] ?? ''));
$tipoFiltro = trim((string) ($_GET['tipo'] ?? ''));
$fotoCategoriaFiltro = trim((string) ($_GET['categoria_foto'] ?? ''));

$sql = "
    SELECT cd.*, u.nome AS usuario_nome
    FROM conteudos_digitais cd
    LEFT JOIN usuarios u ON u.id = cd.usuario_id
    WHERE cd.status = 'ativo'
";
$params = [];

if ($usuario_logado['tipo'] !== 'master') {
    $sql .= " AND cd.publico_acesso IN ('interno', 'comercial')";
}

if ($busca !== '') {
    $sql .= " AND (cd.titulo LIKE ? OR cd.categoria LIKE ? OR cd.arquivo_original LIKE ?)";
    $params[] = "%{$busca}%";
    $params[] = "%{$busca}%";
    $params[] = "%{$busca}%";
}

if ($tipoFiltro !== '') {
    $sql .= " AND cd.tipo = ?";
    $params[] = $tipoFiltro;
}

if ($fotoCategoriaFiltro !== '') {
    $sql .= " AND cd.categoria = ?";
    $params[] = $fotoCategoriaFiltro;
}

$sql .= " ORDER BY cd.fixado DESC, cd.updated_at DESC, cd.created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$conteudos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$conteudosPorTipo = [
    'fotos_produtos' => array_values(array_filter($conteudos, static fn(array $item): bool => ($item['tipo'] ?? '') === 'fotos_produtos')),
    'catalogo' => array_values(array_filter($conteudos, static fn(array $item): bool => ($item['tipo'] ?? '') === 'catalogo')),
    'tabela_preco' => array_values(array_filter($conteudos, static fn(array $item): bool => ($item['tipo'] ?? '') === 'tabela_preco')),
];

$fotosPorCategoria = [];
foreach (($conteudosPorTipo['fotos_produtos'] ?? []) as $fotoConteudo) {
    $categoriaFoto = trim((string) ($fotoConteudo['categoria'] ?? ''));
    if ($categoriaFoto === '') {
        $categoriaFoto = 'Sem categoria';
    }
    if (!isset($fotosPorCategoria[$categoriaFoto])) {
        $fotosPorCategoria[$categoriaFoto] = [];
    }
    $fotosPorCategoria[$categoriaFoto][] = $fotoConteudo;
}

include '../../includes/header_vendedor.php';
?>

<style>
.conteudos-layout .card-header,
.conteudos-layout .card-body {
    padding: 18px 22px;
}

.conteudos-hero {
    border-radius: 18px;
    padding: 26px;
    background: linear-gradient(135deg, #0f172a, #1d4ed8);
    color: #fff;
}

.conteudos-hero p {
    margin: 8px 0 0 0;
    color: rgba(255,255,255,0.85);
}

.hero-actions {
    margin-top: 18px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn-hero-light {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 10px 16px;
    border-radius: 10px;
    background: #fff;
    color: #0f172a !important;
    font-weight: 700;
    text-decoration: none;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.2);
}

.conteudos-toolbar {
    display: flex;
    gap: 14px;
    align-items: center;
    flex-wrap: wrap;
}

.conteudos-search {
    flex: 1;
    min-width: 280px;
}

.categoria-cards {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
}

.categoria-card {
    display: block;
    padding: 20px;
    border-radius: 16px;
    border: 1px solid #dbe4ee;
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    text-decoration: none;
    color: #0f172a;
    box-shadow: 0 10px 26px rgba(15, 23, 42, 0.05);
}

.categoria-card.active {
    border-color: #2563eb;
    box-shadow: 0 12px 30px rgba(37, 99, 235, 0.12);
}

.categoria-card .titulo {
    display: block;
    font-size: 18px;
    font-weight: 700;
}

.categoria-card .desc {
    display: block;
    margin-top: 6px;
    color: #667085;
    font-size: 13px;
}

.categoria-card .total {
    display: block;
    margin-top: 14px;
    font-size: 28px;
    font-weight: 800;
    color: #1d4ed8;
}

.foto-categorias-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

.foto-categoria-card {
    border: 1px solid #dbe4ee;
    border-radius: 16px;
    background: #fff;
    padding: 16px;
}

.foto-categoria-card-link {
    display: block;
    text-decoration: none;
    color: inherit;
}

.foto-categoria-titulo {
    margin: 0 0 12px 0;
    font-size: 16px;
    color: #0f172a;
}

.foto-miniaturas {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
    gap: 14px;
}

.documentos-lista {
    display: grid;
    gap: 12px;
}

.documento-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 16px;
    border: 1px solid #dbe4ee;
    border-radius: 16px;
    background: #fff;
}

.documento-info {
    min-width: 0;
    flex: 1;
}

.documento-info h4 {
    margin: 0;
    font-size: 16px;
    color: #0f172a;
}

.documento-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 8px;
    color: #667085;
    font-size: 12px;
}

.documento-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.foto-miniatura-item {
    display: block;
    text-decoration: none;
    border: 1px solid #dbe4ee;
    border-radius: 14px;
    background: #fff;
    overflow: hidden;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
    transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
    content-visibility: auto;
    contain-intrinsic-size: 260px;
}

.foto-miniatura-item:hover {
    transform: translateY(-2px);
    border-color: #bfdbfe;
    box-shadow: 0 16px 32px rgba(15, 23, 42, 0.10);
}

.foto-miniatura-thumb {
    position: relative;
    aspect-ratio: 1 / 1;
    background:
        linear-gradient(110deg, #eef2f7 8%, #f8fafc 18%, #eef2f7 33%);
    background-size: 200% 100%;
    animation: fotoThumbPulse 1.4s linear infinite;
}

.foto-miniatura-item img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    background: #f8fafc;
    display: block;
    transition: opacity .18s ease;
}

.foto-miniatura-item img[data-loaded="true"] {
    opacity: 1;
}

.foto-miniatura-item img[data-loaded="false"] {
    opacity: 0;
}

.foto-miniatura-conteudo {
    padding: 10px 12px 12px;
}

.foto-miniatura-item span {
    display: block;
    font-size: 12px;
    line-height: 1.35;
    color: #475467;
    min-height: 34px;
}

.foto-miniatura-acoes {
    margin-top: 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
}

.btn-foto-exibir {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 8px 10px;
    border-radius: 10px;
    border: 0;
    background: #1d4ed8;
    color: #fff;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
}

.galeria-lightbox {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 1200;
    background: rgba(15, 23, 42, 0.82);
    padding: 22px;
}

.galeria-lightbox.show {
    display: flex;
    align-items: center;
    justify-content: center;
}

.galeria-lightbox-dialog {
    width: min(980px, 100%);
    max-height: calc(100vh - 44px);
    overflow: auto;
    border-radius: 18px;
    background: #fff;
    box-shadow: 0 24px 80px rgba(15, 23, 42, 0.35);
}

.galeria-lightbox-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 16px 18px;
    border-bottom: 1px solid #e5e7eb;
}

.galeria-lightbox-titulo {
    margin: 0;
    font-size: 18px;
    color: #0f172a;
}

.galeria-lightbox-close {
    border: 0;
    background: #eef2ff;
    color: #1e3a8a;
    width: 38px;
    height: 38px;
    border-radius: 999px;
    font-size: 18px;
    cursor: pointer;
}

.galeria-lightbox-body {
    padding: 18px;
}

.galeria-lightbox-imagem-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 320px;
    background: #f8fafc;
    border-radius: 16px;
    overflow: hidden;
    position: relative;
}

.galeria-lightbox-nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    z-index: 2;
    width: 46px;
    height: 46px;
    border: 0;
    border-radius: 999px;
    background: rgba(15, 23, 42, 0.72);
    color: #fff;
    font-size: 24px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background .18s ease;
}

.galeria-lightbox-nav:hover {
    background: rgba(15, 23, 42, 0.92);
}

.galeria-lightbox-nav.prev {
    left: 16px;
}

.galeria-lightbox-nav.next {
    right: 16px;
}

.galeria-lightbox-imagem-wrap::before {
    content: '';
    position: absolute;
    inset: 0;
    background:
        linear-gradient(110deg, #eef2f7 8%, #f8fafc 18%, #eef2f7 33%);
    background-size: 200% 100%;
    animation: fotoThumbPulse 1.4s linear infinite;
    opacity: 0;
    transition: opacity .2s ease;
}

.galeria-lightbox-imagem-wrap.is-loading::before {
    opacity: 1;
}

.galeria-lightbox-imagem {
    max-width: 100%;
    max-height: 72vh;
    width: auto;
    height: auto;
    display: block;
    position: relative;
    z-index: 1;
}

@keyframes fotoThumbPulse {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

.galeria-lightbox-meta {
    margin-top: 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}

.upload-panel {
    display: none;
}

.upload-panel.show {
    display: block;
}

.form-grid-conteudo {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.categoria-chip {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 999px;
    background: #e0f2fe;
    color: #075985;
    font-size: 11px;
    font-weight: 700;
}

.foto-subgrupo + .foto-subgrupo {
    margin-top: 18px;
}

.foto-subgrupo-titulo {
    margin: 0 0 12px 0;
    font-size: 15px;
    color: #0f172a;
}

.tag {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
}

.tag-novo {
    background: #dcfce7;
    color: #166534;
}

.tag-atualizado {
    background: #dbeafe;
    color: #1d4ed8;
}

.tag-oficial {
    background: #ede9fe;
    color: #5b21b6;
}

.empty-state {
    padding: 28px;
    border: 1px dashed #cbd5e1;
    border-radius: 16px;
    text-align: center;
    color: #64748b;
    background: #f8fafc;
}

@media (max-width: 960px) {
    .categoria-cards,
    .form-grid-conteudo,
    .foto-categorias-grid {
        grid-template-columns: 1fr;
    }

    .foto-miniaturas {
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    }

}
</style>

<div class="card conteudos-layout" style="margin-bottom:20px;">
    <div class="card-body">
        <div class="conteudos-hero">
            <h3 style="margin:0;">Central Comercial de Conteúdos Digitais</h3>
            <p>Reúna catálogos, tabelas de preços e fotos de produtos em um só lugar para agilizar o atendimento e fortalecer as vendas.</p>
            <div class="hero-actions">
                <a href="index.php" class="btn-hero-light">Voltar para Vendas</a>
                <?php if ($usuario_logado['tipo'] === 'master'): ?>
                    <button type="button" class="btn btn-primary" onclick="toggleUploadPanel()">Enviar arquivo</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card conteudos-layout" style="margin-bottom:20px;">
    <div class="card-header">
        <h3>Busca</h3>
    </div>
    <div class="card-body">
        <form method="GET">
            <div class="conteudos-toolbar">
                <div class="conteudos-search">
                    <input type="text" name="q" class="form-control" value="<?php echo htmlspecialchars($busca); ?>" placeholder="Buscar por nome, categoria ou arquivo...">
                </div>
                <?php if ($tipoFiltro !== ''): ?>
                    <input type="hidden" name="tipo" value="<?php echo htmlspecialchars($tipoFiltro); ?>">
                <?php endif; ?>
                <button type="submit" class="btn btn-primary">Buscar</button>
                <a href="conteudos_digitais.php" class="btn btn-secondary">Limpar</a>
            </div>
        </form>
    </div>
</div>

<?php if ($usuario_logado['tipo'] === 'master'): ?>
<div class="card conteudos-layout upload-panel" id="uploadPanel" style="margin-bottom:20px;">
    <div class="card-header">
        <h3>Enviar arquivo</h3>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="acao" value="salvar_conteudo">
            <div class="form-grid-conteudo">
                <div class="form-group">
                    <label>Título curto *</label>
                    <input type="text" name="titulo" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Categoria *</label>
                    <select name="categoria" id="categoriaConteudo" class="form-control" required>
                        <?php foreach ($categoriasFotosFixas as $categoriaFoto): ?>
                            <option value="<?php echo htmlspecialchars($categoriaFoto); ?>"><?php echo htmlspecialchars($categoriaFoto); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Tipo do arquivo *</label>
                    <select name="tipo" id="tipoConteudo" class="form-control" required>
                        <option value="fotos_produtos">Foto</option>
                        <option value="catalogo">Catalogos</option>
                        <option value="tabela_preco">Planilha</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Arquivo(s) *</label>
                    <input type="file" name="arquivo[]" class="form-control" multiple required>
                </div>
            </div>
            <div class="form-group" style="margin-top:14px;">
                <label>Descrição</label>
                <textarea name="descricao" class="form-control" rows="3" placeholder="Opcional"></textarea>
            </div>
            <div style="margin-top:16px;">
                <button type="submit" class="btn btn-primary">Salvar e publicar</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card conteudos-layout" style="margin-bottom:20px;">
    <div class="card-header">
        <h3>Categorias</h3>
    </div>
    <div class="card-body">
        <div class="categoria-cards">
            <?php
            $tipos = [
                'fotos_produtos' => ['titulo' => 'Fotos', 'desc' => 'Imagens para apresentar os produtos.'],
                'catalogo' => ['titulo' => 'Catalogos', 'desc' => 'Materiais comerciais e institucionais.'],
                'tabela_preco' => ['titulo' => 'Tabelas', 'desc' => 'Preços e referências atualizadas.'],
            ];
            ?>
            <?php foreach ($tipos as $tipo => $dados): ?>
                <?php
                $paramsCard = [];
                if ($busca !== '') {
                    $paramsCard['q'] = $busca;
                }
                $paramsCard['tipo'] = $tipo;
                $urlCard = 'conteudos_digitais.php?' . http_build_query($paramsCard);
                ?>
                <a href="<?php echo htmlspecialchars($urlCard); ?>" class="categoria-card <?php echo $tipoFiltro === $tipo ? 'active' : ''; ?>">
                    <span class="titulo"><?php echo htmlspecialchars($dados['titulo']); ?></span>
                    <span class="desc"><?php echo htmlspecialchars($dados['desc']); ?></span>
                    <span class="total"><?php echo number_format(count($conteudosPorTipo[$tipo] ?? []), 0, ',', '.'); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php if ($tipoFiltro === '' || $tipoFiltro === 'fotos_produtos'): ?>
<div class="card conteudos-layout" style="margin-bottom:20px;">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
        <h3>Fotos por Categoria</h3>
        <?php if ($fotoCategoriaFiltro !== ''): ?>
            <a href="conteudos_digitais.php?tipo=fotos_produtos<?php echo $busca !== '' ? '&q=' . urlencode($busca) : ''; ?>" class="btn btn-secondary">Voltar aos grupos</a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (empty($fotosPorCategoria)): ?>
            <div class="empty-state">Nenhuma foto cadastrada ainda.</div>
        <?php elseif ($fotoCategoriaFiltro === ''): ?>
            <div class="foto-categorias-grid">
                <?php foreach ($fotosPorCategoria as $nomeCategoria => $fotosCategoria): ?>
                    <?php
                    $paramsCategoria = ['tipo' => 'fotos_produtos', 'categoria_foto' => $nomeCategoria];
                    if ($busca !== '') {
                        $paramsCategoria['q'] = $busca;
                    }
                    ?>
                    <a class="foto-categoria-card foto-categoria-card-link" href="conteudos_digitais.php?<?php echo htmlspecialchars(http_build_query($paramsCategoria)); ?>">
                        <h4 class="foto-categoria-titulo">
                            <?php echo htmlspecialchars($nomeCategoria); ?> (<?php echo count($fotosCategoria); ?>)
                        </h4>
                        <div style="color:#667085;font-size:13px;">Entrar no grupo para visualizar as fotos.</div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <?php $fotosCategoriaAtual = $fotosPorCategoria[$fotoCategoriaFiltro] ?? []; ?>
            <?php if (empty($fotosCategoriaAtual)): ?>
                <div class="empty-state">Nenhuma foto encontrada para este grupo.</div>
            <?php else: ?>
                <h4 class="foto-subgrupo-titulo"><?php echo htmlspecialchars($fotoCategoriaFiltro); ?></h4>
                <div class="foto-miniaturas">
                    <?php foreach ($fotosCategoriaAtual as $conteudo): ?>
                        <?php if (empty($conteudo['arquivo_nome'])) continue; ?>
                        <?php
                        $arquivoOriginalUrl = UPLOAD_URL . 'conteudos_digitais/' . rawurlencode($conteudo['arquivo_nome']);
                        $thumbUrl = SITE_URL . '/modules/vendas/conteudo_thumb.php?file=' . rawurlencode($conteudo['arquivo_nome']) . '&w=180&h=180';
                        $thumbUrl2x = SITE_URL . '/modules/vendas/conteudo_thumb.php?file=' . rawurlencode($conteudo['arquivo_nome']) . '&w=320&h=320';
                        ?>
                        <a
                            class="foto-miniatura-item"
                            href="<?php echo htmlspecialchars($arquivoOriginalUrl); ?>"
                            data-full="<?php echo htmlspecialchars($arquivoOriginalUrl); ?>"
                            data-titulo="<?php echo htmlspecialchars($conteudo['titulo']); ?>"
                            data-categoria="<?php echo htmlspecialchars($conteudo['categoria'] ?: 'Sem categoria'); ?>"
                            onclick="return abrirGaleriaFoto(event, this);"
                            title="<?php echo htmlspecialchars($conteudo['titulo']); ?>"
                        >
                            <div class="foto-miniatura-thumb">
                                <img
                                    src="<?php echo htmlspecialchars($thumbUrl); ?>"
                                    srcset="<?php echo htmlspecialchars($thumbUrl); ?> 1x, <?php echo htmlspecialchars($thumbUrl2x); ?> 2x"
                                    sizes="(max-width: 768px) 45vw, 180px"
                                    alt="<?php echo htmlspecialchars($conteudo['titulo']); ?>"
                                    loading="lazy"
                                    decoding="async"
                                    fetchpriority="low"
                                    width="180"
                                    height="180"
                                    data-loaded="false"
                                    onload="this.dataset.loaded='true'"
                                >
                            </div>
                            <div class="foto-miniatura-conteudo">
                                <span><?php echo htmlspecialchars($conteudo['titulo']); ?></span>
                                <div class="foto-miniatura-acoes">
                                    <small class="categoria-chip"><?php echo htmlspecialchars($conteudo['categoria'] ?: 'Sem categoria'); ?></small>
                                    <span class="btn-foto-exibir">Exibir</span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($tipoFiltro === 'catalogo' || $tipoFiltro === 'tabela_preco'): ?>
<div class="card conteudos-layout" style="margin-bottom:20px;">
    <div class="card-header">
        <h3><?php echo htmlspecialchars(getTipoLabelConteudo($tipoFiltro)); ?></h3>
    </div>
    <div class="card-body">
        <?php $documentosTipo = $conteudosPorTipo[$tipoFiltro] ?? []; ?>
        <?php if (empty($documentosTipo)): ?>
            <div class="empty-state">Nenhum arquivo encontrado nesta categoria.</div>
        <?php else: ?>
            <div class="documentos-lista">
                <?php foreach ($documentosTipo as $conteudo): ?>
                    <?php
                    $arquivoUrl = !empty($conteudo['arquivo_nome'])
                        ? UPLOAD_URL . 'conteudos_digitais/' . rawurlencode($conteudo['arquivo_nome'])
                        : '#';
                    $tag = getTagConteudo($conteudo);
                    ?>
                    <div class="documento-item">
                        <div class="documento-info">
                            <h4><?php echo htmlspecialchars($conteudo['titulo']); ?></h4>
                            <div class="documento-meta">
                                <span class="categoria-chip"><?php echo htmlspecialchars($conteudo['categoria'] ?: 'Sem categoria'); ?></span>
                                <span><?php echo htmlspecialchars(getTipoLabelConteudo((string) $conteudo['tipo'])); ?></span>
                                <span>Atualizado em <?php echo date('d/m/Y', strtotime((string) ($conteudo['updated_at'] ?? 'now'))); ?></span>
                                <?php if (!empty($tag['texto'])): ?>
                                    <span class="tag <?php echo htmlspecialchars($tag['classe']); ?>"><?php echo htmlspecialchars($tag['texto']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="documento-actions">
                            <?php if (!empty($conteudo['arquivo_nome'])): ?>
                                <a class="btn btn-secondary" href="<?php echo htmlspecialchars($arquivoUrl); ?>" target="_blank">Abrir</a>
                                <a class="btn btn-primary" href="<?php echo htmlspecialchars($arquivoUrl); ?>" download>Baixar</a>
                            <?php endif; ?>
                            <?php if ($usuario_logado['tipo'] === 'master'): ?>
                                <form method="POST" onsubmit="return confirm('Deseja remover este arquivo?');" style="margin:0;">
                                    <input type="hidden" name="acao" value="excluir_conteudo">
                                    <input type="hidden" name="id" value="<?php echo (int) $conteudo['id']; ?>">
                                    <button type="submit" class="btn btn-danger">Excluir</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div id="galeriaLightbox" class="galeria-lightbox" onclick="fecharGaleriaFoto(event)">
    <div class="galeria-lightbox-dialog" onclick="event.stopPropagation()">
        <div class="galeria-lightbox-header">
            <div>
                <h4 id="galeriaLightboxTitulo" class="galeria-lightbox-titulo">Foto</h4>
            </div>
            <button type="button" class="galeria-lightbox-close" onclick="fecharGaleriaFoto()">&times;</button>
        </div>
        <div class="galeria-lightbox-body">
            <div class="galeria-lightbox-imagem-wrap">
                <button type="button" class="galeria-lightbox-nav prev" onclick="navegarGaleriaFoto(-1)" aria-label="Foto anterior">&#8249;</button>
                <img id="galeriaLightboxImagem" class="galeria-lightbox-imagem" src="" alt="">
                <button type="button" class="galeria-lightbox-nav next" onclick="navegarGaleriaFoto(1)" aria-label="Próxima foto">&#8250;</button>
            </div>
            <div class="galeria-lightbox-meta">
                <small id="galeriaLightboxCategoria" class="categoria-chip">Sem categoria</small>
                <a id="galeriaLightboxDownload" class="btn btn-primary" href="#" target="_blank" rel="noopener">Abrir original</a>
            </div>
        </div>
    </div>
</div>

<script>
let galeriaFotosAtuais = [];
let galeriaFotoIndexAtual = -1;

function toggleUploadPanel() {
    const panel = document.getElementById('uploadPanel');
    if (!panel) return;
    panel.classList.toggle('show');
    if (panel.classList.contains('show')) {
        panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function abrirGaleriaFoto(event, link) {
    if (event) {
        event.preventDefault();
    }

    const modal = document.getElementById('galeriaLightbox');
    const imagem = document.getElementById('galeriaLightboxImagem');
    const imagemWrap = imagem ? imagem.closest('.galeria-lightbox-imagem-wrap') : null;
    const titulo = document.getElementById('galeriaLightboxTitulo');
    const categoria = document.getElementById('galeriaLightboxCategoria');
    const download = document.getElementById('galeriaLightboxDownload');

    if (!modal || !imagem || !link) {
        return false;
    }

    galeriaFotosAtuais = Array.from(document.querySelectorAll('.foto-miniatura-item'));
    galeriaFotoIndexAtual = galeriaFotosAtuais.indexOf(link);

    const full = link.dataset.full || link.getAttribute('href') || '';
    if (imagemWrap) {
        imagemWrap.classList.add('is-loading');
    }
    imagem.removeAttribute('src');
    imagem.alt = link.dataset.titulo || 'Foto';

    const preloader = new Image();
    preloader.decoding = 'async';
    preloader.onload = function() {
        imagem.src = full;
        if (imagemWrap) {
            imagemWrap.classList.remove('is-loading');
        }
    };
    preloader.onerror = function() {
        imagem.src = full;
        if (imagemWrap) {
            imagemWrap.classList.remove('is-loading');
        }
    };
    preloader.src = full;

    if (titulo) {
        titulo.textContent = link.dataset.titulo || 'Foto';
    }

    if (categoria) {
        categoria.textContent = link.dataset.categoria || 'Sem categoria';
    }

    if (download) {
        download.href = full;
    }

    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
    return false;
}

function navegarGaleriaFoto(direcao) {
    if (!galeriaFotosAtuais.length || galeriaFotoIndexAtual === -1) {
        return;
    }

    const total = galeriaFotosAtuais.length;
    galeriaFotoIndexAtual = (galeriaFotoIndexAtual + direcao + total) % total;
    const proximoLink = galeriaFotosAtuais[galeriaFotoIndexAtual];
    if (proximoLink) {
        abrirGaleriaFoto(null, proximoLink);
    }
}

function fecharGaleriaFoto(event) {
    if (event && event.target && !event.target.classList.contains('galeria-lightbox')) {
        return;
    }

    const modal = document.getElementById('galeriaLightbox');
    const imagem = document.getElementById('galeriaLightboxImagem');
    const imagemWrap = imagem ? imagem.closest('.galeria-lightbox-imagem-wrap') : null;
    if (!modal || !imagem) {
        return;
    }

    modal.classList.remove('show');
    imagem.removeAttribute('src');
    if (imagemWrap) {
        imagemWrap.classList.remove('is-loading');
    }
    galeriaFotoIndexAtual = -1;
    document.body.style.overflow = '';
}

document.addEventListener('DOMContentLoaded', function() {
    const tipoConteudo = document.getElementById('tipoConteudo');
    const categoriaConteudo = document.getElementById('categoriaConteudo');

    if (!tipoConteudo || !categoriaConteudo) return;

    function atualizarCampoCategoria() {
        if (tipoConteudo.value === 'fotos_produtos') {
            categoriaConteudo.innerHTML = `
                <?php foreach ($categoriasFotosFixas as $categoriaFoto): ?>
                    <option value="<?php echo htmlspecialchars($categoriaFoto); ?>"><?php echo htmlspecialchars($categoriaFoto); ?></option>
                <?php endforeach; ?>
            `;
            categoriaConteudo.value = 'Mobiliario';
        } else if (tipoConteudo.value === 'catalogo') {
            categoriaConteudo.innerHTML = `
                <option value="Catalogo comercial">Catalogo comercial</option>
                <option value="Catalogo institucional">Catalogo institucional</option>
                <option value="Material promocional">Material promocional</option>
            `;
        } else {
            categoriaConteudo.innerHTML = `
                <option value="Tabela geral">Tabela geral</option>
                <option value="Tabela promocional">Tabela promocional</option>
                <option value="Tabela por linha">Tabela por linha</option>
            `;
        }
    }

    tipoConteudo.addEventListener('change', atualizarCampoCategoria);
    atualizarCampoCategoria();

    document.addEventListener('keydown', function(event) {
        const modal = document.getElementById('galeriaLightbox');
        if (!modal || !modal.classList.contains('show')) {
            return;
        }

        if (event.key === 'Escape') {
            fecharGaleriaFoto();
        } else if (event.key === 'ArrowLeft') {
            event.preventDefault();
            navegarGaleriaFoto(-1);
        } else if (event.key === 'ArrowRight') {
            event.preventDefault();
            navegarGaleriaFoto(1);
        }
    });
});
</script>

<?php include '../../includes/footer_vendedor.php'; ?>
