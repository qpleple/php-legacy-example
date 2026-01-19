<?php
/**
 * Journal PDF - Legacy style
 */

require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/utils.php';
require_once __DIR__ . '/../../lib/pdf/fpdf.php';

require_login();

$journal_id = get('journal_id', '');
$period_id = get('period_id', '');

$where = "e.status = 'posted'";
if ($journal_id) {
    $journal_id_int = intval($journal_id);
    $where .= " AND e.journal_id = $journal_id_int";
}
if ($period_id) {
    $period_id_int = intval($period_id);
    $where .= " AND e.period_id = $period_id_int";
}

// Get entries with lines
$sql = "SELECT e.*, j.code as journal_code, j.label as journal_label
        FROM entries e
        LEFT JOIN journals j ON e.journal_id = j.id
        WHERE $where
        ORDER BY j.code, e.entry_date, e.id";
$entries = db_fetch_all(db_query($sql));

foreach ($entries as &$entry) {
    $entry_id = $entry['id'];
    $sql = "SELECT el.*, a.code as account_code
            FROM entry_lines el
            LEFT JOIN accounts a ON el.account_id = a.id
            WHERE el.entry_id = $entry_id
            ORDER BY el.line_no";
    $entry['lines'] = db_fetch_all(db_query($sql));
}

$company = get_company();

// Create PDF
$pdf = new FPDF('P', 'mm', 'A4');
$pdf->SetMargins(10, 10, 10);
$pdf->AddPage();

// Title
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 8, utf8_decode($company ? $company['name'] : 'Comptabilite'), 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 6, 'Journal Comptable', 0, 1, 'C');
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(0, 5, utf8_decode('Imprimé le ' . date('d/m/Y H:i')), 0, 1, 'C');
$pdf->Ln(5);

$grand_debit = 0;
$grand_credit = 0;
$current_journal = '';

foreach ($entries as $entry) {
    // Journal header
    if ($entry['journal_code'] !== $current_journal) {
        if ($current_journal !== '') {
            $pdf->Ln(5);
        }
        $current_journal = $entry['journal_code'];
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetFillColor(0, 51, 102);
        $pdf->SetTextColor(255);
        $pdf->Cell(0, 6, utf8_decode('Journal ' . $entry['journal_code'] . ' - ' . $entry['journal_label']), 1, 1, 'L', true);
        $pdf->SetTextColor(0);
    }

    // Check page break
    if ($pdf->GetY() > 250) {
        $pdf->AddPage();
    }

    // Entry header
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(230, 230, 230);
    $pdf->Cell(0, 5, utf8_decode(format_date($entry['entry_date']) . ' - ' . $entry['piece_number'] . ' - ' . $entry['label']), 1, 1, 'L', true);

    // Lines
    $pdf->SetFont('Arial', '', 8);
    foreach ($entry['lines'] as $line) {
        $pdf->Cell(25, 4, $line['account_code'], 0, 0, 'L');
        $pdf->Cell(95, 4, utf8_decode(substr($line['label'], 0, 55)), 0, 0, 'L');
        $pdf->Cell(30, 4, $line['debit'] > 0 ? number_format($line['debit'], 2, ',', ' ') : '', 0, 0, 'R');
        $pdf->Cell(30, 4, $line['credit'] > 0 ? number_format($line['credit'], 2, ',', ' ') : '', 0, 1, 'R');
    }

    // Entry total
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->Cell(120, 4, 'Total piece:', 0, 0, 'R');
    $pdf->Cell(30, 4, number_format($entry['total_debit'], 2, ',', ' '), 0, 0, 'R');
    $pdf->Cell(30, 4, number_format($entry['total_credit'], 2, ',', ' '), 0, 1, 'R');

    $grand_debit += $entry['total_debit'];
    $grand_credit += $entry['total_credit'];

    $pdf->Ln(2);
}

// Grand total
$pdf->Ln(5);
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(0, 51, 102);
$pdf->SetTextColor(255);
$pdf->Cell(120, 6, 'TOTAL GENERAL', 1, 0, 'R', true);
$pdf->Cell(30, 6, number_format($grand_debit, 2, ',', ' '), 1, 0, 'R', true);
$pdf->Cell(30, 6, number_format($grand_credit, 2, ',', ' '), 1, 1, 'R', true);

$pdf->Output('I', 'journal.pdf');
