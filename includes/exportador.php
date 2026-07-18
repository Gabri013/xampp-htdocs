<?php
/**
 * Sistema de Exportação de Dados - Cozinka ERP
 * Suporte para Excel, PDF e CSV com validação de integridade
 *
 * Uso:
 *   $exp = new Exportador($db);
 *   $exp->exportar('vendas', 'xlsx', $filtros);
 */

class Exportador
{
    private $db;
    private $usuario;
    private $erros = [];
    private $avisos = [];

    // Mapas de setores permitidos por tipo de usuário
    private $acessoSetores = [
        'master'      => ['vendas', 'orcamentos', 'os', 'estoque', 'producao', 'qualidade', 'expedicao', 'financeiro'],
        'gerente'     => ['vendas', 'orcamentos', 'os', 'estoque', 'producao', 'financeiro'],
        'vendedor'    => ['vendas', 'orcamentos', 'clientes'],
        'projetista'  => ['os', 'orcamentos'],
        'producao'    => ['os', 'estoque', 'producao'],
        'qualidade'   => ['producao', 'os'],
        'expedicao'   => ['os', 'vendas', 'estoque'],
        'contador'    => ['financeiro', 'vendas', 'os'],
    ];

    public function __construct(PDO $db, array $usuario = [])
    {
        $this->db = $db;
        $this->usuario = $usuario;
    }

    /**
     * Exporta dados em formato especificado
     */
    public function exportar(string $tabela, string $formato, array $filtros = []): array|false
    {
        // Validação de acesso
        if (!$this->validarAcesso($tabela)) {
            $this->adicionarErro("Acesso negado à tabela: {$tabela}");
            return false;
        }

        // Validação de formato
        if (!in_array($formato, ['csv', 'xlsx', 'pdf', 'json'])) {
            $this->adicionarErro("Formato inválido: {$formato}");
            return false;
        }

        // Buscar dados
        $dados = $this->buscarDados($tabela, $filtros);
        if ($dados === false) {
            return false;
        }

        // Validar integridade
        if (!$this->validarIntegridade($tabela, $dados)) {
            return false;
        }

        // Exportar conforme formato
        switch ($formato) {
            case 'csv':
                return $this->exportarCSV($tabela, $dados);
            case 'xlsx':
                return $this->exportarXLSX($tabela, $dados);
            case 'pdf':
                return $this->exportarPDF($tabela, $dados);
            case 'json':
                return $this->exportarJSON($tabela, $dados);
            default:
                return false;
        }
    }

    /**
     * Valida se o usuário tem acesso à tabela
     */
    private function validarAcesso(string $tabela): bool
    {
        // Master tem acesso total
        if (($this->usuario['tipo'] ?? '') === 'master') {
            return true;
        }

        // Verificar permissão do usuário
        $tipo = $this->usuario['tipo'] ?? '';
        $setoresPermitidos = $this->acessoSetores[$tipo] ?? [];

        if (!in_array($tabela, $setoresPermitidos)) {
            return false;
        }

        // Validação adicional: vendedor só vê seus próprios dados
        if ($tipo === 'vendedor' && $tabela === 'vendas') {
            return true; // Será filtrado em buscarDados
        }

        return true;
    }

    /**
     * Busca dados da tabela com filtros aplicados
     */
    private function buscarDados(string $tabela, array $filtros): array|false
    {
        try {
            switch ($tabela) {
                case 'vendas':
                    return $this->buscarVendas($filtros);
                case 'orcamentos':
                    return $this->buscarOrcamentos($filtros);
                case 'os':
                    return $this->buscarOS($filtros);
                case 'clientes':
                    return $this->buscarClientes($filtros);
                case 'estoque':
                    return $this->buscarEstoque($filtros);
                case 'producao':
                    return $this->buscarProducao($filtros);
                case 'financeiro':
                    return $this->buscarFinanceiro($filtros);
                default:
                    $this->adicionarErro("Tabela não suportada: {$tabela}");
                    return false;
            }
        } catch (Exception $e) {
            $this->adicionarErro("Erro ao buscar dados: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Busca dados de Vendas
     */
    private function buscarVendas(array $filtros): array
    {
        $sql = "SELECT v.id, v.numero, v.data_venda, c.razao_social as cliente,
                       v.valor_total, v.status, u.nome as vendedor, os.numero as os_numero,
                       os.status as os_status, v.observacoes
                FROM vendas v
                INNER JOIN clientes c ON v.cliente_id = c.id
                INNER JOIN usuarios u ON v.usuario_id = u.id
                LEFT JOIN ordens_servico os ON os.venda_id = v.id
                WHERE 1=1";

        $params = [];

        // Filtro por vendedor (se não for master)
        if ($this->usuario['tipo'] === 'vendedor') {
            $sql .= " AND v.usuario_id = ?";
            $params[] = $this->usuario['id'];
        }

        // Filtros adicionais
        if (!empty($filtros['status'])) {
            $sql .= " AND v.status = ?";
            $params[] = $filtros['status'];
        }

        if (!empty($filtros['data_inicio'])) {
            $sql .= " AND DATE(v.data_venda) >= ?";
            $params[] = $filtros['data_inicio'];
        }

        if (!empty($filtros['data_fim'])) {
            $sql .= " AND DATE(v.data_venda) <= ?";
            $params[] = $filtros['data_fim'];
        }

        if (!empty($filtros['cliente_id'])) {
            $sql .= " AND v.cliente_id = ?";
            $params[] = (int)$filtros['cliente_id'];
        }

        $sql .= " ORDER BY v.id DESC LIMIT 10000";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca dados de Orçamentos
     */
    private function buscarOrcamentos(array $filtros): array
    {
        $sql = "SELECT o.id, o.numero, o.data_criacao, c.razao_social as cliente,
                       o.valor_total, o.status, u.nome as criado_por, o.observacoes
                FROM orcamentos o
                INNER JOIN clientes c ON o.cliente_id = c.id
                INNER JOIN usuarios u ON o.usuario_id = u.id
                WHERE 1=1";

        $params = [];

        if (!empty($filtros['status'])) {
            $sql .= " AND o.status = ?";
            $params[] = $filtros['status'];
        }

        if (!empty($filtros['data_inicio'])) {
            $sql .= " AND DATE(o.data_criacao) >= ?";
            $params[] = $filtros['data_inicio'];
        }

        if (!empty($filtros['data_fim'])) {
            $sql .= " AND DATE(o.data_criacao) <= ?";
            $params[] = $filtros['data_fim'];
        }

        $sql .= " ORDER BY o.id DESC LIMIT 10000";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca dados de Ordens de Serviço
     */
    private function buscarOS(array $filtros): array
    {
        $sql = "SELECT os.id, os.numero, os.data_criacao, c.razao_social as cliente,
                       os.status, os.etapa_atual, u.nome as projetista,
                       COUNT(oi.id) as total_itens, os.data_entrega_estimada
                FROM ordens_servico os
                INNER JOIN clientes c ON os.cliente_id = c.id
                LEFT JOIN usuarios u ON os.usuario_id = u.id
                LEFT JOIN os_itens oi ON os.id = oi.os_id
                WHERE 1=1";

        $params = [];

        if (!empty($filtros['status'])) {
            $sql .= " AND os.status = ?";
            $params[] = $filtros['status'];
        }

        if (!empty($filtros['etapa'])) {
            $sql .= " AND os.etapa_atual = ?";
            $params[] = $filtros['etapa'];
        }

        $sql .= " GROUP BY os.id ORDER BY os.id DESC LIMIT 10000";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca dados de Clientes
     */
    private function buscarClientes(array $filtros): array
    {
        $sql = "SELECT id, razao_social, cpf_cnpj, email, telefone,
                       endereco, cidade, estado, status, created_at
                FROM clientes
                WHERE 1=1";

        $params = [];

        if (!empty($filtros['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filtros['status'];
        }

        if (!empty($filtros['busca'])) {
            $sql .= " AND (razao_social LIKE ? OR cpf_cnpj LIKE ?)";
            $params[] = "%{$filtros['busca']}%";
            $params[] = "%{$filtros['busca']}%";
        }

        $sql .= " ORDER BY razao_social ASC LIMIT 10000";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca dados de Estoque
     */
    private function buscarEstoque(array $filtros): array
    {
        $sql = "SELECT e.id, e.numero, m.descricao as material,
                       e.quantidade, e.valor_unitario, e.total,
                       e.localizacao, e.status, e.data_entrada
                FROM estoque e
                LEFT JOIN materiais m ON e.material_id = m.id
                WHERE 1=1";

        $params = [];

        if (!empty($filtros['status'])) {
            $sql .= " AND e.status = ?";
            $params[] = $filtros['status'];
        }

        if (!empty($filtros['material_id'])) {
            $sql .= " AND e.material_id = ?";
            $params[] = (int)$filtros['material_id'];
        }

        $sql .= " ORDER BY e.numero ASC LIMIT 10000";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca dados de Produção
     */
    private function buscarProducao(array $filtros): array
    {
        $sql = "SELECT op.id, op.numero, os.numero as os_numero,
                       op.status, op.etapa_atual, u.nome as responsavel,
                       op.data_inicio, op.data_fim, op.observacoes
                FROM ordens_producao op
                INNER JOIN ordens_servico os ON op.os_id = os.id
                LEFT JOIN usuarios u ON op.usuario_id = u.id
                WHERE 1=1";

        $params = [];

        if (!empty($filtros['status'])) {
            $sql .= " AND op.status = ?";
            $params[] = $filtros['status'];
        }

        if (!empty($filtros['etapa'])) {
            $sql .= " AND op.etapa_atual = ?";
            $params[] = $filtros['etapa'];
        }

        $sql .= " ORDER BY op.id DESC LIMIT 10000";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca dados de Financeiro
     */
    private function buscarFinanceiro(array $filtros): array
    {
        $sql = "SELECT cf.id, cf.numero, cf.descricao, cf.valor,
                       cf.data_vencimento, cf.status, cf.tipo,
                       c.razao_social as cliente, cf.data_lancamento
                FROM contas_financeiras cf
                LEFT JOIN clientes c ON cf.cliente_id = c.id
                WHERE 1=1";

        $params = [];

        if (!empty($filtros['status'])) {
            $sql .= " AND cf.status = ?";
            $params[] = $filtros['status'];
        }

        if (!empty($filtros['tipo'])) {
            $sql .= " AND cf.tipo = ?";
            $params[] = $filtros['tipo'];
        }

        if (!empty($filtros['data_inicio'])) {
            $sql .= " AND DATE(cf.data_vencimento) >= ?";
            $params[] = $filtros['data_inicio'];
        }

        if (!empty($filtros['data_fim'])) {
            $sql .= " AND DATE(cf.data_vencimento) <= ?";
            $params[] = $filtros['data_fim'];
        }

        $sql .= " ORDER BY cf.data_vencimento ASC LIMIT 10000";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Valida integridade dos dados (FK, campos obrigatórios)
     */
    private function validarIntegridade(string $tabela, array $dados): bool
    {
        if (empty($dados)) {
            $this->adicionarAviso("Nenhum dado encontrado para exportar");
            return true;
        }

        $errosEncontrados = 0;

        foreach ($dados as $indice => $linha) {
            // Validar campos obrigatórios
            $camposObrigatorios = $this->getCamposObrigatorios($tabela);

            foreach ($camposObrigatorios as $campo) {
                if (empty($linha[$campo])) {
                    $this->adicionarAviso("Campo obrigatório vazio: {$campo} (linha {$indice})");
                    $errosEncontrados++;
                }
            }

            // Validar tipos de dados
            $validacoes = $this->getValidacoesCampos($tabela);
            foreach ($validacoes as $campo => $tipo) {
                if (isset($linha[$campo]) && !$this->validarTipo($linha[$campo], $tipo)) {
                    $this->adicionarAviso("Tipo inválido em {$campo}: {$linha[$campo]} (linha {$indice})");
                    $errosEncontrados++;
                }
            }
        }

        // Limitar a 100 avisos
        if ($errosEncontrados > 100) {
            $this->adicionarAviso("Total de {$errosEncontrados} problemas de integridade encontrados");
        }

        return $errosEncontrados < 1000; // Permitir exportação mesmo com avisos
    }

    /**
     * Retorna campos obrigatórios por tabela
     */
    private function getCamposObrigatorios(string $tabela): array
    {
        return match($tabela) {
            'vendas' => ['numero', 'cliente', 'valor_total', 'status'],
            'orcamentos' => ['numero', 'cliente', 'status'],
            'os' => ['numero', 'cliente', 'status'],
            'clientes' => ['razao_social', 'cpf_cnpj'],
            'estoque' => ['numero', 'material', 'quantidade'],
            'producao' => ['numero', 'os_numero', 'status'],
            'financeiro' => ['numero', 'valor', 'status'],
            default => [],
        };
    }

    /**
     * Retorna validações de tipo por tabela
     */
    private function getValidacoesCampos(string $tabela): array
    {
        return match($tabela) {
            'vendas' => ['id' => 'int', 'valor_total' => 'numeric'],
            'orcamentos' => ['id' => 'int', 'valor_total' => 'numeric'],
            'os' => ['id' => 'int'],
            'estoque' => ['id' => 'int', 'quantidade' => 'numeric', 'valor_unitario' => 'numeric'],
            'producao' => ['id' => 'int'],
            'financeiro' => ['id' => 'int', 'valor' => 'numeric'],
            default => [],
        };
    }

    /**
     * Valida tipo de dados
     */
    private function validarTipo($valor, string $tipo): bool
    {
        return match($tipo) {
            'int' => is_numeric($valor),
            'numeric' => is_numeric($valor),
            'string' => is_string($valor) || is_numeric($valor),
            'date' => preg_match('/^\d{4}-\d{2}-\d{2}/', (string)$valor),
            default => true,
        };
    }

    /**
     * Neutraliza injeção de fórmula (CSV/Excel): células que começam com
     * = + - @ (ou tab/CR) são prefixadas com apóstrofo para serem tratadas
     * como texto ao abrir na planilha.
     */
    private function neutralizarFormula($valor): string
    {
        $valor = (string) ($valor ?? '');
        // Números legítimos (inclusive negativos, ex.: -2500.00) passam intactos.
        if ($valor === '' || is_numeric($valor)) {
            return $valor;
        }
        if (strpbrk($valor[0], "=+-@\t\r") !== false) {
            return "'" . $valor;
        }
        return $valor;
    }

    /**
     * Exporta para CSV
     */
    private function exportarCSV(string $tabela, array $dados): array|false
    {
        if (empty($dados)) {
            $this->adicionarErro("Nenhum dado para exportar");
            return false;
        }

        // Preparar cabeçalhos
        $cabecalhos = array_keys($dados[0]);

        // Preparar conteúdo
        $linhas = [];
        foreach ($dados as $linha) {
            $linhas[] = array_values($linha);
        }

        // Gerar CSV
        $csv = '';
        $csv .= implode(',', array_map(fn($v) => '"' . str_replace('"', '""', $v) . '"', $cabecalhos)) . "\n";

        foreach ($linhas as $linha) {
            $csv .= implode(',', array_map(fn($v) => '"' . str_replace('"', '""', $this->neutralizarFormula($v)) . '"', $linha)) . "\n";
        }

        return [
            'conteudo' => $csv,
            'tipo_mime' => 'text/csv',
            'extensao' => 'csv',
            'nome' => $tabela . '_' . date('Y-m-d_His') . '.csv'
        ];
    }

    /**
     * Exporta para XLSX (formato Excel aberto)
     */
    private function exportarXLSX(string $tabela, array $dados): array|false
    {
        if (empty($dados)) {
            $this->adicionarErro("Nenhum dado para exportar");
            return false;
        }

        // Preparar dados XML
        $cabecalhos = array_keys($dados[0]);
        $xml = $this->gerarXMLPlanilha($tabela, $cabecalhos, $dados);

        // Criar arquivo ZIP (XLSX é um ZIP com XMLs)
        $temp_dir = sys_get_temp_dir() . '/xlsx_' . uniqid();
        mkdir($temp_dir, 0777, true);

        try {
            // Estrutura básica do XLSX
            $this->criarEstruturaxlsx($temp_dir, $tabela, $cabecalhos, $dados);

            // Criar ZIP
            $zip_path = $temp_dir . '.zip';
            $zip = new ZipArchive();
            $zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

            // Adicionar arquivos
            $this->adicionarArquivosZip($zip, $temp_dir);
            $zip->close();

            // Ler conteúdo
            $conteudo = file_get_contents($zip_path);

            // Limpar
            $this->removerDiretorio($temp_dir);
            @unlink($zip_path);

            return [
                'conteudo' => $conteudo,
                'tipo_mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'extensao' => 'xlsx',
                'nome' => $tabela . '_' . date('Y-m-d_His') . '.xlsx'
            ];
        } catch (Exception $e) {
            $this->removerDiretorio($temp_dir);
            $this->adicionarErro("Erro ao criar XLSX: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Cria estrutura básica do XLSX
     */
    private function criarEstruturaxlsx(string $temp_dir, string $tabela, array $cabecalhos, array $dados): void
    {
        // Criar diretórios
        mkdir($temp_dir . '/_rels', 0777, true);
        mkdir($temp_dir . '/xl/_rels', 0777, true);
        mkdir($temp_dir . '/xl/worksheets', 0777, true);
        mkdir($temp_dir . '/docProps', 0777, true);

        // [Content_Types].xml
        file_put_contents($temp_dir . '/[Content_Types].xml', $this->gerarContentTypes());

        // .rels
        file_put_contents($temp_dir . '/_rels/.rels', $this->gerarRels());

        // workbook.xml
        file_put_contents($temp_dir . '/xl/workbook.xml', $this->gerarWorkbook($tabela));

        // worksheet1.xml
        file_put_contents($temp_dir . '/xl/worksheets/sheet1.xml', $this->gerarWorksheet($cabecalhos, $dados));

        // styles.xml
        file_put_contents($temp_dir . '/xl/styles.xml', $this->gerarStyles());

        // workbook.xml.rels
        file_put_contents($temp_dir . '/xl/_rels/workbook.xml.rels', $this->gerarWorkbookRels());

        // docProps/core.xml
        file_put_contents($temp_dir . '/docProps/core.xml', $this->gerarCoreProps());
    }

    private function gerarContentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
</Types>';
    }

    private function gerarRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
</Relationships>';
    }

    private function gerarWorkbook(string $tabela): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<fileVersion appName="xl" lastEdited="4" lowestEdited="4" rupBuild="4505"/>
<workbookPr defaultTheme="1"/>
<sheets>
<sheet name="' . htmlspecialchars($tabela) . '" sheetId="1" r:id="rId1"/>
</sheets>
</workbook>';
    }

    private function gerarWorksheet(array $cabecalhos, array $dados): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<sheetData>';

        // Linha de cabeçalhos
        $xml .= '<row r="1">';
        $col = 'A';
        foreach ($cabecalhos as $cabecalho) {
            $xml .= '<c r="' . $col . '1" t="str"><v>' . htmlspecialchars($cabecalho) . '</v></c>';
            $col++;
        }
        $xml .= '</row>';

        // Linhas de dados
        $linha = 2;
        foreach ($dados as $item) {
            $xml .= '<row r="' . $linha . '">';
            $col = 'A';
            foreach ($cabecalhos as $cabecalho) {
                $valor = $item[$cabecalho] ?? '';
                $tipo = is_numeric($valor) && !preg_match('/^0/', (string)$valor) ? 'n' : 'str';
                $xml .= '<c r="' . $col . $linha . '" t="' . $tipo . '"><v>' . htmlspecialchars($valor) . '</v></c>';
                $col++;
            }
            $xml .= '</row>';
            $linha++;
        }

        $xml .= '</sheetData></worksheet>';
        return $xml;
    }

    private function gerarStyles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<fonts count="1"><font><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font></fonts>
<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>
<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>
<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>
</styleSheet>';
    }

    private function gerarWorkbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
</Relationships>';
    }

    private function gerarCoreProps(): string
    {
        $data = date('c');
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/officeDocument/2006/customProperties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
<dc:creator>Cozinka ERP</dc:creator>
<cp:lastModifiedBy>Cozinka ERP</cp:lastModifiedBy>
<dcterms:created xsi:type="dcterms:W3CDTF">' . $data . '</dcterms:created>
<dcterms:modified xsi:type="dcterms:W3CDTF">' . $data . '</dcterms:modified>
</cp:coreProperties>';
    }

    /**
     * Adiciona arquivos ao ZIP
     */
    private function adicionarArquivosZip(ZipArchive $zip, string $dir): void
    {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($dir) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }
    }

    /**
     * Remove diretório recursivamente
     */
    private function removerDiretorio(string $dir): void
    {
        if (!is_dir($dir)) return;

        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                $path = $dir . '/' . $file;
                if (is_dir($path)) {
                    $this->removerDiretorio($path);
                } else {
                    @unlink($path);
                }
            }
        }
        @rmdir($dir);
    }

    /**
     * Exporta para PDF
     */
    private function exportarPDF(string $tabela, array $dados): array|false
    {
        if (empty($dados)) {
            $this->adicionarErro("Nenhum dado para exportar");
            return false;
        }

        $html = $this->gerarHTMLPDF($tabela, $dados);
        $pdf = $this->converterHTMLPDF($html);

        if ($pdf === false) {
            return false;
        }

        return [
            'conteudo' => $pdf,
            'tipo_mime' => 'application/pdf',
            'extensao' => 'pdf',
            'nome' => $tabela . '_' . date('Y-m-d_His') . '.pdf'
        ];
    }

    /**
     * Gera HTML para PDF
     */
    private function gerarHTMLPDF(string $tabela, array $dados): string
    {
        $cabecalhos = array_keys($dados[0]);
        $titulo = ucfirst(str_replace('_', ' ', $tabela));
        $data = date('d/m/Y H:i:s');

        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>' . htmlspecialchars($titulo) . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: #333; border-bottom: 2px solid #3b82f6; padding-bottom: 10px; }
        .info { color: #666; font-size: 12px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background-color: #3b82f6; color: white; padding: 10px; text-align: left; font-size: 12px; }
        td { padding: 8px; border-bottom: 1px solid #ddd; font-size: 11px; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .rodape { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ccc; font-size: 10px; color: #666; }
    </style>
</head>
<body>
    <h1>' . htmlspecialchars($titulo) . '</h1>
    <div class="info">
        <p><strong>Data da Exportação:</strong> ' . $data . '</p>
        <p><strong>Total de Registros:</strong> ' . count($dados) . '</p>
    </div>
    <table>
        <thead>
            <tr>';

        foreach ($cabecalhos as $cabecalho) {
            $html .= '<th>' . htmlspecialchars($cabecalho) . '</th>';
        }

        $html .= '</tr>
        </thead>
        <tbody>';

        foreach ($dados as $linha) {
            $html .= '<tr>';
            foreach ($cabecalhos as $cabecalho) {
                $valor = $linha[$cabecalho] ?? '';
                $html .= '<td>' . htmlspecialchars($valor) . '</td>';
            }
            $html .= '</tr>';
        }

        $html .= '</tbody>
    </table>
    <div class="rodape">
        <p>Relatório gerado por Cozinka ERP - ' . date('Y') . '</p>
    </div>
</body>
</html>';

        return $html;
    }

    /**
     * Converte HTML para PDF usando método simples
     */
    private function converterHTMLPDF(string $html): string|false
    {
        // Se TCPDF estiver disponível, usar
        if (file_exists(__DIR__ . '/../vendor/tcpdf/tcpdf.php')) {
            require_once __DIR__ . '/../vendor/tcpdf/tcpdf.php';
            try {
                $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
                $pdf->AddPage();
                $pdf->writeHTML($html, true, false, true, false, '');
                return $pdf->Output('', 'S');
            } catch (Exception $e) {
                $this->adicionarErro("Erro ao gerar PDF com TCPDF: " . $e->getMessage());
                return false;
            }
        }

        // Fallback: retornar HTML como string (será enviado como text/html)
        $this->adicionarAviso("TCPDF não disponível. Retornando HTML. Use LibreOffice/Chromium no servidor para PDF real.");
        return $html;
    }

    /**
     * Exporta para JSON
     */
    private function exportarJSON(string $tabela, array $dados): array|false
    {
        $json = [
            'exportacao' => [
                'tabela' => $tabela,
                'data_exportacao' => date('Y-m-d H:i:s'),
                'total_registros' => count($dados),
                'usuario' => $this->usuario['nome'] ?? 'Desconhecido',
                'dados' => $dados
            ]
        ];

        return [
            'conteudo' => json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'tipo_mime' => 'application/json',
            'extensao' => 'json',
            'nome' => $tabela . '_' . date('Y-m-d_His') . '.json'
        ];
    }

    /**
     * Adiciona erro à lista
     */
    private function adicionarErro(string $mensagem): void
    {
        $this->erros[] = $mensagem;
    }

    /**
     * Adiciona aviso à lista
     */
    private function adicionarAviso(string $mensagem): void
    {
        $this->avisos[] = $mensagem;
    }

    /**
     * Retorna erros
     */
    public function getErros(): array
    {
        return $this->erros;
    }

    /**
     * Retorna avisos
     */
    public function getAvisos(): array
    {
        return $this->avisos;
    }

    /**
     * Limpa erros e avisos
     */
    public function limpar(): void
    {
        $this->erros = [];
        $this->avisos = [];
    }
}
