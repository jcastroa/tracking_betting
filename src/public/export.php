<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

// Apply same filters as index
$filtros = [
    'confianza' => $_GET['confianza'] ?? '',
    'riesgo'    => $_GET['riesgo']    ?? '',
    'resultado' => $_GET['resultado'] ?? '',
    'fecha'     => $_GET['fecha']     ?? '',
];

try {
    $pdo = Database::getConnection();

    $where = ['1=1'];
    $params = [];
    if (!empty($filtros['confianza'])) { $where[] = 't.confianza = :confianza'; $params['confianza'] = $filtros['confianza']; }
    if (!empty($filtros['riesgo']))    { $where[] = 't.riesgo = :riesgo';       $params['riesgo']    = $filtros['riesgo']; }
    if (!empty($filtros['resultado'])) { $where[] = 't.resultado = :resultado'; $params['resultado'] = $filtros['resultado']; }
    if (!empty($filtros['fecha']))     { $where[] = 'a.fecha_analisis = :fecha'; $params['fecha']    = $filtros['fecha']; }

    $sql = "SELECT t.*, a.fecha_analisis, a.casa_apuestas
            FROM tickets t
            JOIN analisis a ON t.analisis_id = a.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY a.fecha_analisis DESC, t.value_pct DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll();

} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Tickets Apuestas');

// ── HEADER ROW ──────────────────────────────────────────────────────────────
$headers = [
    'A' => '#',
    'B' => 'Fecha',
    'C' => 'Casa Apuestas',
    'D' => 'Partido',
    'E' => 'Mercado',
    'F' => 'Selección',
    'G' => 'Cuota',
    'H' => 'Value %',
    'I' => 'Prob. Estimada',
    'J' => 'Prob. Implícita',
    'K' => 'Confianza',
    'L' => 'Stake (S/)',
    'M' => 'Riesgo',
    'N' => 'Resultado',
    'O' => 'Ganancia (S/)',
    'P' => 'Razón',
];

$headerStyle = [
    'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 11],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF212529']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF444444']]],
];

foreach ($headers as $col => $label) {
    $sheet->setCellValue($col . '1', $label);
}
$sheet->getStyle('A1:P1')->applyFromArray($headerStyle);
$sheet->getRowDimension(1)->setRowHeight(24);

// ── DATA ROWS ────────────────────────────────────────────────────────────────
$colorMap = [
    'confianza' => ['ALTA' => 'FF198754', 'MEDIA' => 'FFFD7E14', 'BAJA' => 'FFDC3545'],
    'riesgo'    => ['bajo' => 'FF0DCAF0', 'medio' => 'FFFFC107', 'alto' => 'FFDC3545'],
    'resultado' => ['ganado' => 'FF198754', 'perdido' => 'FFDC3545', 'pendiente' => 'FF6C757D', 'void' => 'FFADB5BD'],
];

foreach ($tickets as $i => $t) {
    $row = $i + 2;

    $sheet->setCellValue('A' . $row, $i + 1);
    $sheet->setCellValue('B' . $row, $t['fecha_analisis']);
    $sheet->setCellValue('C' . $row, $t['casa_apuestas']);
    $sheet->setCellValue('D' . $row, $t['partido']);
    $sheet->setCellValue('E' . $row, $t['mercado']);
    $sheet->setCellValue('F' . $row, $t['seleccion']);
    $sheet->setCellValue('G' . $row, (float)$t['cuota_betano']);
    $sheet->setCellValue('H' . $row, (float)$t['value_pct']);
    $sheet->setCellValue('I' . $row, (float)$t['prob_estimada']);
    $sheet->setCellValue('J' . $row, (float)$t['prob_implicita_cuota']);
    $sheet->setCellValue('K' . $row, $t['confianza']);
    $sheet->setCellValue('L' . $row, (float)$t['stake_soles']);
    $sheet->setCellValue('M' . $row, ucfirst($t['riesgo']));
    $sheet->setCellValue('N' . $row, ucfirst($t['resultado']));
    $sheet->setCellValue('O' . $row, $t['ganancia_soles'] !== null ? (float)$t['ganancia_soles'] : '');
    $sheet->setCellValue('P' . $row, $t['razon']);

    // Number formats
    $sheet->getStyle('G' . $row)->getNumberFormat()->setFormatCode('0.000');
    $sheet->getStyle('H' . $row)->getNumberFormat()->setFormatCode('0.00"%"');
    $sheet->getStyle('I' . $row)->getNumberFormat()->setFormatCode('0.0000');
    $sheet->getStyle('J' . $row)->getNumberFormat()->setFormatCode('0.0000');
    $sheet->getStyle('L' . $row)->getNumberFormat()->setFormatCode('"S/ "0.00');
    $sheet->getStyle('O' . $row)->getNumberFormat()->setFormatCode('"S/ "0.00');

    // Alternating row color
    $bg = ($i % 2 === 0) ? 'FFF8F9FA' : 'FFFFFFFF';
    $sheet->getStyle('A' . $row . ':P' . $row)->getFill()
          ->setFillType(Fill::FILL_SOLID)
          ->getStartColor()->setARGB($bg);

    // Colored badges for confianza, riesgo, resultado
    foreach (['K' => ['confianza', $t['confianza']], 'M' => ['riesgo', strtolower($t['riesgo'])], 'N' => ['resultado', strtolower($t['resultado'])]] as $col => [$type, $val]) {
        $argb = $colorMap[$type][$val] ?? 'FF6C757D';
        $sheet->getStyle($col . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($argb);
        $sheet->getStyle($col . $row)->getFont()->setColor(new Color('FFFFFFFF'))->setBold(true);
        $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    // Value% color
    $vp = (float)$t['value_pct'];
    $valueColor = $vp >= 20 ? 'FF198754' : ($vp >= 10 ? 'FFFD7E14' : 'FFDC3545');
    $sheet->getStyle('H' . $row)->getFont()->setColor(new Color($valueColor))->setBold(true);

    // Borders
    $sheet->getStyle('A' . $row . ':P' . $row)->getBorders()->getAllBorders()
          ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFDDDDDD');

    // Row height
    $sheet->getRowDimension($row)->setRowHeight(20);
}

// ── COLUMN WIDTHS ────────────────────────────────────────────────────────────
$widths = ['A'=>4,'B'=>12,'C'=>16,'D'=>36,'E'=>22,'F'=>18,'G'=>8,'H'=>9,
           'I'=>14,'J'=>14,'K'=>12,'L'=>12,'M'=>10,'N'=>12,'O'=>14,'P'=>50];
foreach ($widths as $col => $w) {
    $sheet->getColumnDimension($col)->setWidth($w);
}

// Wrap text for Partido and Razón
$sheet->getStyle('D2:D' . ($i + 2))->getAlignment()->setWrapText(true);
$sheet->getStyle('P2:P' . ($i + 2))->getAlignment()->setWrapText(true);

// ── SUMMARY SHEET ─────────────────────────────────────────────────────────────
$summary = $spreadsheet->createSheet();
$summary->setTitle('Resumen');

$ganados  = array_filter($tickets, fn($t) => $t['resultado'] === 'ganado');
$perdidos = array_filter($tickets, fn($t) => $t['resultado'] === 'perdido');
$pendientes = array_filter($tickets, fn($t) => $t['resultado'] === 'pendiente');

$ganancia_bruta = array_sum(array_column(array_filter($tickets, fn($t) => $t['resultado'] === 'ganado'), 'ganancia_soles'));
$stake_perdido  = array_sum(array_column(array_filter($tickets, fn($t) => $t['resultado'] === 'perdido'), 'stake_soles'));

$summaryData = [
    ['Métrica', 'Valor'],
    ['Total Tickets', count($tickets)],
    ['Ganados', count($ganados)],
    ['Perdidos', count($perdidos)],
    ['Pendientes', count($pendientes)],
    ['Ganancia Bruta (S/)', $ganancia_bruta],
    ['Stake Perdido (S/)', $stake_perdido],
    ['Ganancia Neta (S/)', $ganancia_bruta - $stake_perdido],
    ['ROI (%)', count($tickets) > 0 ? round(($ganancia_bruta - $stake_perdido) / max(array_sum(array_column($tickets, 'stake_soles')), 0.01) * 100, 2) : 0],
];

foreach ($summaryData as $ri => $row_data) {
    $summary->setCellValue('A' . ($ri + 1), $row_data[0]);
    $summary->setCellValue('B' . ($ri + 1), $row_data[1]);
}

$summary->getStyle('A1:B1')->applyFromArray($headerStyle);
$summary->getColumnDimension('A')->setWidth(22);
$summary->getColumnDimension('B')->setWidth(16);
$summary->getStyle('B6:B9')->getNumberFormat()->setFormatCode('"S/ "0.00');
$summary->getStyle('B10')->getNumberFormat()->setFormatCode('0.00"%"');

// Freeze pane on main sheet
$sheet->freezePane('A2');
$sheet->setAutoFilter('A1:P1');

// ── OUTPUT ───────────────────────────────────────────────────────────────────
$filename = 'tickets_apuestas_' . date('Ymd_His') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
