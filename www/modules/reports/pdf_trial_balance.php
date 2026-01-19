<?php
/**
 * Trial Balance PDF - Legacy style
 */

require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/utils.php';
require_once __DIR__ . '/../../lib/pdf/fpdf.php';

require_login();

$period_id = get('period_id', '');

$where = "e.status = 'posted'";
if ($period_id) {
    $period_id = intval($period_id);
    $where .= " AND e.period_id = $period_id";
}

// Get trial balance data
$sql = "SELECT a.code, a.label, a.type,
               SUM(el.debit) as total_debit,
               SUM(el.credit) as total_credit
        FROM accounts a
        LEFT JOIN entry_lines el ON el.account_id = a.id
        LEFT JOIN entries e ON el.entry_id = e.id AND $where
        GROUP BY a.id, a.code, a.label, a.type
        HAVING total_debit > 0 OR total_credit > 0
        ORDER BY a.code";
$balance = db_fetch_all(db_query($sql));

$company = get_company();

// Calculate totals
$grand_debit = 0;
$grand_credit = 0;
$grand_solde_debit = 0;
$grand_solde_credit = 0;

foreach ($balance as &$row) {
    $row['solde'] = $row['total_debit'] - $row['total_credit'];
    $row['solde_debit'] = $row['solde'] > 0 ? $row['solde'] : 0;
    $row['solde_credit'] = $row['solde'] < 0 ? abs($row['solde']) : 0;

    $grand_debit += $row['total_debit'];
    $grand_credit += $row['total_credit'];
    $grand_solde_debit += $row['solde_debit'];
    $grand_solde_credit += $row['solde_credit'];
}

// Create PDF
$pdf = new FPDF('P', 'mm', 'A4');
$pdf->SetMargins(10, 10, 10);
$pdf->AddPage();

// Title
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 8, utf8_decode($company ? $company['name'] : 'Ketchup Compta'), 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 6, utf8_decode('Balance Générale'), 0, 1, 'C');
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(0, 5, utf8_decode('Imprimé le ' . date('d/m/Y H:i')), 0, 1, 'C');
$pdf->Ln(5);

// Column headers
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(0, 51, 102);
$pdf->SetTextColor(255);
$pdf->Cell(20, 6, 'Compte', 1, 0, 'C', true);
$pdf->Cell(60, 6, utf8_decode('Libellé'), 1, 0, 'C', true);
$pdf->Cell(27, 6, utf8_decode('Mvt Débit'), 1, 0, 'C', true);
$pdf->Cell(27, 6, utf8_decode('Mvt Crédit'), 1, 0, 'C', true);
$pdf->Cell(27, 6, utf8_decode('Solde D'), 1, 0, 'C', true);
$pdf->Cell(27, 6, utf8_decode('Solde C'), 1, 1, 'C', true);

// Lines
$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(0);
$fill = false;

if (empty($balance)) {
    $pdf->SetFont('Arial', 'I', 9);
    $pdf->Cell(188, 6, utf8_decode('Aucune écriture trouvée'), 1, 1, 'C');
}

foreach ($balance as $row) {
    $pdf->SetFillColor(245, 245, 245);

    $pdf->Cell(20, 5, $row['code'], 1, 0, 'L', $fill);
    $pdf->Cell(60, 5, utf8_decode(substr($row['label'], 0, 35)), 1, 0, 'L', $fill);
    $pdf->Cell(27, 5, number_format($row['total_debit'], 2, ',', ' '), 1, 0, 'R', $fill);
    $pdf->Cell(27, 5, number_format($row['total_credit'], 2, ',', ' '), 1, 0, 'R', $fill);
    $pdf->Cell(27, 5, $row['solde_debit'] > 0 ? number_format($row['solde_debit'], 2, ',', ' ') : '', 1, 0, 'R', $fill);
    $pdf->Cell(27, 5, $row['solde_credit'] > 0 ? number_format($row['solde_credit'], 2, ',', ' ') : '', 1, 1, 'R', $fill);

    $fill = !$fill;
}

// Totals
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(220, 220, 220);
$pdf->Cell(80, 6, 'TOTAUX', 1, 0, 'R', true);
$pdf->Cell(27, 6, number_format($grand_debit, 2, ',', ' '), 1, 0, 'R', true);
$pdf->Cell(27, 6, number_format($grand_credit, 2, ',', ' '), 1, 0, 'R', true);
$pdf->Cell(27, 6, number_format($grand_solde_debit, 2, ',', ' '), 1, 0, 'R', true);
$pdf->Cell(27, 6, number_format($grand_solde_credit, 2, ',', ' '), 1, 1, 'R', true);

// Balance check
$pdf->Ln(5);
if (abs($grand_solde_debit - $grand_solde_credit) <= 0.01) {
    $pdf->SetTextColor(0, 128, 0);
    $pdf->Cell(0, 5, utf8_decode('Balance équilibrée'), 0, 1, 'C');
} else {
    $pdf->SetTextColor(255, 0, 0);
    $pdf->Cell(0, 5, utf8_decode('Écart: ' . number_format(abs($grand_solde_debit - $grand_solde_credit), 2, ',', ' ')), 0, 1, 'C');
}

$pdf->Output('I', 'balance.pdf');
