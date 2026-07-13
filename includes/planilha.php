<?php
/**
 * Leitura de planilhas de materiais (CSV/XLSX) para o import do BOM
 * (esqueleto) do produto na Engenharia de Produto.
 */

/**
 * Lê a tabela de materiais do SolidWorks (.sldmat, XML UTF-16) e devolve
 * os insumos/matéria-prima no padrão da Cozinca:
 * nome "#<material>-<espessura>-<código>" -> [codigo, material, espessura,
 * nome, unidade]. O código é a referência de compra da matéria-prima.
 */
function lerMateriaisSldmat(string $caminho): array
{
    $raw = file_get_contents($caminho);
    if ($raw === false) {
        return [];
    }
    // .sldmat é XML UTF-16
    if (strncmp($raw, "\xFF\xFE", 2) === 0 || strncmp($raw, "\xFE\xFF", 2) === 0) {
        $raw = mb_convert_encoding($raw, 'UTF-8', 'UTF-16');
    }

    if (!preg_match_all('/<material\s+name="([^"]+)"/', $raw, $m)) {
        return [];
    }

    $itens = [];
    $vistos = [];
    foreach ($m[1] as $nomeBruto) {
        $nome = html_entity_decode(trim($nomeBruto), ENT_QUOTES, 'UTF-8');
        if ($nome === '') {
            continue;
        }

        // código de compra: 5-8 dígitos no fim (após "-") OU no começo do nome
        $codigo = '';
        $semCod = $nome;
        if (preg_match('/-\s*(\d{5,8})\s*$/', $nome, $mc)) {
            $codigo = $mc[1];
            $semCod = preg_replace('/-\s*' . preg_quote($codigo, '/') . '\s*$/', '', $nome);
        } elseif (preg_match('/^\s*(\d{5,8})\b\s*(.*)$/', $nome, $mc)) {
            $codigo = $mc[1];
            $semCod = trim($mc[2]) !== '' ? trim($mc[2]) : $nome;
        }

        // espessura: número com vírgula/ponto (0,5 / 1,0 / 4,8)
        $espessura = '';
        if (preg_match('/(\d+[.,]\d+)/', $semCod, $me)) {
            $espessura = str_replace('.', ',', $me[1]);
        }

        // material: primeiro trecho, sem '#', sem CÓDIGO (5-8 díg.) no início
        $material = ltrim($semCod, '# ');
        $material = trim(preg_replace('/^\d{5,8}[\s\-]*/', '', $material)); // tira só código inicial
        $material = trim(preg_replace('/[-].*$/', '', $material));          // até o primeiro '-'
        if ($material === '') {
            $material = trim(preg_replace('/^\d{5,8}[\s\-]*/', '', ltrim($nome, '# ')));
        }

        // nome limpo para o insumo
        $nomeLimpo = ltrim($semCod, '# ');
        $nomeLimpo = trim(str_replace('-', ' ', $nomeLimpo));
        if ($espessura !== '' && stripos($nomeLimpo, 'mm') === false) {
            $nomeLimpo .= 'mm';
        }
        if ($nomeLimpo === '') {
            $nomeLimpo = $material;
        }

        // evita duplicar (por código quando houver, senão por nome)
        $chave = $codigo !== '' ? 'c:' . $codigo : 'n:' . mb_strtolower($nomeLimpo);
        if (isset($vistos[$chave])) {
            continue;
        }
        $vistos[$chave] = true;

        $itens[] = [
            'codigo' => $codigo,
            'material' => $material,
            'espessura' => $espessura,
            'nome' => $nomeLimpo,
            'unidade' => 'un',
        ];
    }
    return $itens;
}

/**
 * Lê CSV ou XLSX e devolve linhas normalizadas como componentes:
 * [codigo, descricao, quantidade, unidade, custo_unitario, dimensoes].
 * Detecta a linha de cabeçalho pelos nomes das colunas.
 */
function lerPlanilhaMateriais(string $caminho, string $extensao): array
{
    $extensao = strtolower($extensao);
    if (in_array($extensao, ['xlsx', 'xlsm'], true)) {
        $matriz = lerLinhasXlsx($caminho);
    } else {
        $matriz = lerLinhasCsv($caminho);
    }
    if (empty($matriz)) {
        return [];
    }

    $chaves = ['codig', 'descri', 'material', 'quant', 'qtd', 'unid', 'custo', 'valor', 'preco', 'dimens', 'medida'];
    $headerIdx = -1;
    $melhor = 0;
    foreach ($matriz as $i => $linha) {
        if ($i > 25) {
            break;
        }
        $acertos = 0;
        foreach ($linha as $cel) {
            $c = normalizarCabecalho((string) $cel);
            if ($c === '') {
                continue;
            }
            foreach ($chaves as $k) {
                if (strpos($c, $k) !== false) {
                    $acertos++;
                    break;
                }
            }
        }
        if ($acertos > $melhor) {
            $melhor = $acertos;
            $headerIdx = $i;
        }
    }

    // Mapeamento por cabeçalho. Ordem de prioridade específica pois no formato
    // SolidWorks convivem MATERIAL (tipo) e DESCRIÇÃO (peça), e QTD x QTD.TOTAL.
    $map = [];
    $dimsX = null;
    $dimsY = null;
    if ($headerIdx >= 0 && $melhor >= 2) {
        foreach ($matriz[$headerIdx] as $col => $cel) {
            $c = normalizarCabecalho((string) $cel);
            if ($c === '') {
                continue;
            }
            $temTotal = strpos($c, 'total') !== false;
            if (strpos($c, 'codig') !== false && !isset($map['codigo'])) {
                $map['codigo'] = $col;
            } elseif ((strpos($c, 'descri') !== false || strpos($c, 'nome') !== false) && !isset($map['descricao'])) {
                $map['descricao'] = $col; // DESCRICAO da peca (prioridade sobre material)
            } elseif (strpos($c, 'material') !== false && !isset($map['material'])) {
                $map['material'] = $col; // tipo de material (aco, aluminio...)
            } elseif ((strpos($c, 'quant') !== false || $c === 'qtd') && !$temTotal && !isset($map['quantidade'])) {
                $map['quantidade'] = $col; // QTD por peca (ignora QTD. TOTAL)
            } elseif (strpos($c, 'unid') !== false && !isset($map['unidade'])) {
                $map['unidade'] = $col;
            } elseif ((strpos($c, 'custo') !== false || strpos($c, 'valor') !== false || strpos($c, 'preco') !== false) && !isset($map['custo'])) {
                $map['custo'] = $col;
            } elseif ((strpos($c, 'dimens') !== false || strpos($c, 'medida') !== false) && !isset($map['dimensoes'])) {
                $map['dimensoes'] = $col;
            } elseif ($c === 'x' && $dimsX === null) {
                $dimsX = $col;
            } elseif ($c === 'y' && $dimsY === null) {
                $dimsY = $col;
            }
        }
        // fallback: se não achou QTD sem "total", usa a primeira coluna de quantidade
        if (!isset($map['quantidade'])) {
            foreach ($matriz[$headerIdx] as $col => $cel) {
                $c = normalizarCabecalho((string) $cel);
                if (strpos($c, 'qtd') !== false || strpos($c, 'quant') !== false) { $map['quantidade'] = $col; break; }
            }
        }
    }

    $linhasDados = $headerIdx >= 0 ? array_slice($matriz, $headerIdx + 1) : $matriz;
    $itens = [];
    foreach ($linhasDados as $linha) {
        if (!empty($map)) {
            $codigo = isset($map['codigo']) ? trim((string) ($linha[$map['codigo']] ?? '')) : '';
            $descricao = isset($map['descricao']) ? trim((string) ($linha[$map['descricao']] ?? '')) : '';
            $material = isset($map['material']) ? trim((string) ($linha[$map['material']] ?? '')) : '';
            $qtd = isset($map['quantidade']) ? parseNumeroPlanilha((string) ($linha[$map['quantidade']] ?? '')) : 1;
            $unidade = isset($map['unidade']) ? trim((string) ($linha[$map['unidade']] ?? '')) : '';
            $custo = isset($map['custo']) ? parseNumeroPlanilha((string) ($linha[$map['custo']] ?? '')) : 0;
            $dimensoes = isset($map['dimensoes']) ? trim((string) ($linha[$map['dimensoes']] ?? '')) : '';
            if ($dimensoes === '' && ($dimsX !== null || $dimsY !== null)) {
                $x = $dimsX !== null ? trim((string) ($linha[$dimsX] ?? '')) : '';
                $y = $dimsY !== null ? trim((string) ($linha[$dimsY] ?? '')) : '';
                if ($x !== '' || $y !== '') {
                    $dimensoes = trim($x . ($y !== '' ? ' x ' . $y : ''));
                }
            }
            // acrescenta o tipo de material à descrição (contexto útil no BOM)
            if ($material !== '' && $descricao !== '' && mb_stripos($descricao, $material) === false) {
                $descricao .= ' — ' . $material;
            } elseif ($descricao === '' && $material !== '') {
                $descricao = $material;
            }
        } else {
            $vals = array_values($linha);
            $codigo = trim((string) ($vals[0] ?? ''));
            $descricao = trim((string) ($vals[1] ?? ''));
            $qtd = parseNumeroPlanilha((string) ($vals[2] ?? '1'));
            $unidade = trim((string) ($vals[3] ?? ''));
            $custo = parseNumeroPlanilha((string) ($vals[4] ?? '0'));
            $dimensoes = '';
        }

        if ($descricao === '' && $codigo === '') {
            continue;
        }
        if ($descricao === '') {
            $descricao = $codigo;
        }
        if ($qtd <= 0) {
            $qtd = 1;
        }

        $itens[] = [
            'codigo' => $codigo,
            'descricao' => $descricao,
            'quantidade' => $qtd,
            'unidade' => $unidade !== '' ? $unidade : 'un',
            'custo_unitario' => $custo,
            'dimensoes' => $dimensoes,
        ];
    }
    return $itens;
}

/** Normaliza cabeçalho: minúsculo, sem acento, sem espaços nas pontas. */
function normalizarCabecalho(string $texto): string
{
    $t = mb_strtolower(trim($texto), 'UTF-8');
    $de = ['á','à','â','ã','ä','é','è','ê','ë','í','ì','î','ï','ó','ò','ô','õ','ö','ú','ù','û','ü','ç'];
    $para = ['a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','c'];
    return str_replace($de, $para, $t);
}

/** Converte "1.234,56", "1234.56" ou "1,5" em float. */
function parseNumeroPlanilha(string $valor): float
{
    $v = trim($valor);
    if ($v === '') {
        return 0.0;
    }
    $v = preg_replace('/[^\d,.\-]/', '', $v);
    if ($v === '') {
        return 0.0;
    }
    if (strpos($v, ',') !== false && strpos($v, '.') !== false) {
        $v = str_replace('.', '', $v);
        $v = str_replace(',', '.', $v);
    } elseif (strpos($v, ',') !== false) {
        $v = str_replace(',', '.', $v);
    }
    return (float) $v;
}

/** Lê CSV (auto-detecta ; ou ,) em matriz linhas -> células. */
function lerLinhasCsv(string $caminho): array
{
    $conteudo = file_get_contents($caminho);
    if ($conteudo === false) {
        return [];
    }
    $conteudo = str_replace("\xEF\xBB\xBF", '', $conteudo);
    $enc = mb_detect_encoding($conteudo, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
    if ($enc && $enc !== 'UTF-8') {
        $conteudo = mb_convert_encoding($conteudo, 'UTF-8', $enc);
    }

    $delim = (substr_count($conteudo, ';') >= substr_count($conteudo, ',')) ? ';' : ',';
    $linhas = [];
    foreach (preg_split('/\r\n|\r|\n/', $conteudo) as $linha) {
        if (trim($linha) === '') {
            continue;
        }
        $linhas[] = str_getcsv($linha, $delim);
    }
    return $linhas;
}

/** Lê a primeira planilha de um XLSX em matriz linhas -> células. */
function lerLinhasXlsx(string $caminho): array
{
    if (!class_exists('ZipArchive')) {
        return [];
    }
    $zip = new ZipArchive();
    if ($zip->open($caminho) !== true) {
        return [];
    }

    $shared = [];
    $ss = $zip->getFromName('xl/sharedStrings.xml');
    if ($ss !== false) {
        $xml = @simplexml_load_string($ss);
        if ($xml !== false) {
            foreach ($xml->si as $si) {
                $txt = '';
                if (isset($si->t)) {
                    $txt = (string) $si->t;
                } else {
                    foreach ($si->r as $r) {
                        $txt .= (string) $r->t;
                    }
                }
                $shared[] = $txt;
            }
        }
    }

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($sheetXml === false) {
        for ($i = 1; $i <= 9; $i++) {
            $tmp = $zip->getFromName("xl/worksheets/sheet$i.xml");
            if ($tmp !== false) {
                $sheetXml = $tmp;
                break;
            }
        }
    }
    $zip->close();
    if (!$sheetXml) {
        return [];
    }

    $xml = @simplexml_load_string($sheetXml);
    if ($xml === false) {
        return [];
    }

    $matriz = [];
    foreach ($xml->sheetData->row as $row) {
        $linha = [];
        foreach ($row->c as $c) {
            $ref = (string) $c['r'];
            $col = colLetraParaIndice(preg_replace('/\d+/', '', $ref));
            $tipo = (string) $c['t'];
            if ($tipo === 's') {
                $val = $shared[(int) $c->v] ?? '';
            } elseif ($tipo === 'inlineStr') {
                $val = (string) $c->is->t;
            } else {
                $val = (string) $c->v;
            }
            $linha[$col] = $val;
        }
        if (!empty($linha)) {
            ksort($linha);
            $matriz[] = $linha;
        }
    }
    return $matriz;
}

/** "A"->0, "B"->1, "AA"->26 ... */
function colLetraParaIndice(string $letras): int
{
    $letras = strtoupper($letras);
    $n = 0;
    $len = strlen($letras);
    for ($i = 0; $i < $len; $i++) {
        $n = $n * 26 + (ord($letras[$i]) - 64);
    }
    return $n - 1;
}
