<?php
/**
 * VAT Summary PDF - Legacy style
 */

require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/utils.php';
require_once __DIR__ . '/../../lib/pdf/fpdf.php';

require_login();

$period_id = get('period_id', '');

$where = "e.status = 'posted'";
if ($period_id) {
    $period_id_int = intval($period_id);
    $where .= " AND e.period_id = $period_id_int";
}

$vat_rates = get_vat_rates(false);

$vat_data = array();
foreach ($vat_rates as $vr) {
    $vat_id = $vr['id'];

    $sql = "SELECT SUM(el.vat_amount) as total
            FROM entry_lines el
            INNER JOIN entries e ON el.entry_id = e.id
            INNER JOIN accounts a ON el.account_id = a.id
            WHERE $where AND el.vat_rate_id = $vat_id AND el.credit > 0 AND a.code LIKE '7%'";
    $collected = db_fetch_assoc(db_query($sql))['total'] ?: 0;

    $sql = "SELECT SUM(el.vat_amount) as total
            FROM entry_lines el
            INNER JOIN entries e ON el.entry_id = e.id
            INNER JOIN accounts a ON el.account_id = a.id
            WHERE $where AND el.vat_rate_id = $vat_id AND el.debit > 0 AND a.code LIKE '6%'";
    $deductible = db_fetch_assoc(db_query($sql))['total'] ?: 0;

    $sql = "SELECT SUM(el.vat_base) as total
            FROM entry_lines el
            INNER JOIN entries e ON el.entry_id = e.id
            INNER JOIN accounts a ON el.account_id = a.id
            WHERE $where AND el.vat_rate_id = $vat_id AND el.credit > 0 AND a.code LIKE '7%'";
    $base_collected = db_fetch_assoc(db_query($sql))['total'] ?: 0;

    $sql = "SELECT SUM(el.vat_base) as total
            FROM entry_lines el
            INNER JOIN entries e ON el.entry_id = e.id
            INNER JOIN accounts a ON el.account_id = a.id
            WHERE $where AND el.vat_rate_id = $vat_id AND el.debit > 0 AND a.code LIKE '6%'";
    $base_deductible = db_fetch_assoc(db_query($sql))['total'] ?: 0;

    if ($collected > 0 || $deductible > 0) {
        $vat_data[] = array(
            'label' => $vr['label'],
            'rate' => $vr['rate'],
            'base_collected' => $base_collected,
            'collected' => $collected,
            'base_deductible' => $base_deductible,
            'deductible' => $deductible,
            'balance' => $collected - $deductible
        );
    }
}

$total_collected = 0;
$total_deductible = 0;
foreach ($vat_data as $vd) {
    $total_collected += $vd['collected'];
    $total_deductible += $vd['deductible'];
}

$company = get_company();

// Create PDF
$pdf = new FPDF('P', 'mm', 'A4');
$pdf->SetMargins(15, 15, 15);
$pdf->AddPage();

// Title
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 8, utf8_decode($company ? $company['name'] : 'Ketchup Compta'), 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 6, utf8_decode('Synthèse TVA'), 0, 1, 'C');
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(0, 5, utf8_decode('Imprimé le ' . date('d/m/Y H:i')), 0, 1, 'C');
$pdf->Ln(10);

// Summary boxes
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(60, 8, utf8_decode('TVA Collectée:'), 1, 0, 'L');
$pdf->Cell(40, 8, number_format($total_collected, 2, ',', ' ') . ' EUR', 1, 1, 'R');
$pdf->Cell(60, 8, utf8_decode('TVA Déductible:'), 1, 0, 'L');
$pdf->Cell(40, 8, number_format($total_deductible, 2, ',', ' ') . ' EUR', 1, 1, 'R');

$balance = $total_collected - $total_deductible;
$pdf->SetFillColor($balance >= 0 ? 255 : 200, $balance >= 0 ? 200 : 255, 200);
$pdf->Cell(60, 8, utf8_decode('TVA à reverser:'), 1, 0, 'L', true);
$pdf->Cell(40, 8, number_format($balance, 2, ',', ' ') . ' EUR', 1, 1, 'R', true);

$pdf->Ln(10);

// Detail table
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(0, 51, 102);
$pdf->SetTextColor(255);
$pdf->Cell(30, 6, 'Taux', 1, 0, 'C', true);
$pdf->Cell(30, 6, 'Base Ventes', 1, 0, 'C', true);
$pdf->Cell(30, 6, utf8_decode('TVA Coll.'), 1, 0, 'C', true);
$pdf->Cell(30, 6, 'Base Achats', 1, 0, 'C', true);
$pdf->Cell(30, 6, utf8_decode('TVA Déd.'), 1, 0, 'C', true);
$pdf->Cell(30, 6, 'Solde', 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(0);

if (empty($vat_data)) {
    $pdf->SetFont('Arial', 'I', 9);
    $pdf->Cell(180, 6, utf8_decode('Aucune donnée TVA pour la période sélectionnée'), 1, 1, 'C');
}

foreach ($vat_data as $vd) {
    $pdf->Cell(30, 5, utf8_decode($vd['label']), 1, 0, 'L');
    $pdf->Cell(30, 5, number_format($vd['base_collected'], 2, ',', ' '), 1, 0, 'R');
    $pdf->Cell(30, 5, number_format($vd['collected'], 2, ',', ' '), 1, 0, 'R');
    $pdf->Cell(30, 5, number_format($vd['base_deductible'], 2, ',', ' '), 1, 0, 'R');
    $pdf->Cell(30, 5, number_format($vd['deductible'], 2, ',', ' '), 1, 0, 'R');
    $pdf->Cell(30, 5, number_format($vd['balance'], 2, ',', ' '), 1, 1, 'R');
}

$pdf->Output('I', 'synthese_tva.pdf');
