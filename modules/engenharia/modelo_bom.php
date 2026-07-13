<?php
require_once '../../config/config.php';
requirePermission(['master', 'gerente', 'producao', 'projetista', 'engenharia']);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="modelo-esqueleto-produto.csv"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM p/ Excel abrir com acento
fputcsv($out, ['Codigo', 'Descricao', 'Material', 'QTD', 'Unidade', 'Custo Unitario'], ';');
fputcsv($out, ['1007906', 'Chapa inox 430 0.8mm', 'INOX', '2', 'un', '85,00'], ';');
fputcsv($out, ['1006554', 'Rebite pop 4,8x8', 'ALUMINIO', '30', 'un', '0,15'], ';');
fputcsv($out, ['1008297', 'Unidade condensadora 1/4 HP', 'CONF. FORN.', '1', 'un', '650,00'], ';');
fclose($out);
