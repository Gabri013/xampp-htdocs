<?php
// api/export.php
require_once '../config/config.php';
require_once '../includes/auth.php';

if (!isLoggedIn()) {
    http_response_code(401);
    exit('Não autorizado');
}

$formato = $_GET['format'] ?? 'csv';
$modulo = $_GET['modulo'] ?? 'vendas';

$db = getDB();
$usuario = getCurrentUser();

// Query base por módulo
$thead = [];
$rows = [];

if ($modulo === 'vendas') {
    $where = '';
    $params = [];
    if (!hasPermission(['master'])) {
        $where = 'WHERE usuario_id = ?';
        $params[] = $usuario['id'];
    }
    
    $stmt = $db->prepare("
        SELECT v.*, c.razao_social 
        FROM vendas v 
        LEFT JOIN clientes c ON v.cliente_id = c.id
        $where
        ORDER BY v.id DESC
        LIMIT 5000
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    
    $thead = ['ID', 'Número', 'Cliente', 'Valor', 'Status', 'Data'];
}

if ($formato === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $modulo . '_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, $thead);
    foreach ($rows as $row) {
        fputcsv($output, [
            $row['id'],
            $row['numero'],
            $row['razao_social'],
            $row['valor_total'],
            $row['status'],
            $row['created_at']
        ]);
    }
    fclose($output);
}

if ($formato === 'pdf') {
    if (!file_exists('../vendor/tcpdf/tcpdf.php')) {
        http_response_code(501);
        exit('Exportação em PDF indisponível: biblioteca TCPDF não instalada. Use format=csv.');
    }
    require_once '../vendor/tcpdf/tcpdf.php';
    
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Relatório - ' . ucfirst($modulo), 0, 1);
    
    $pdf->SetFont('helvetica', '', 10);
    foreach ($rows as $row) {
        $pdf->Cell(0, 8, implode(' | ', [
            $row['id'],
            $row['numero'],
            $row['razao_social'],
            $row['valor_total'],
            $row['status'],
            $row['created_at']
        ]), 0, 1);
    }
    
    $pdf->Output($modulo . '_' . date('Y-m-d') . '.pdf', 'D');
}