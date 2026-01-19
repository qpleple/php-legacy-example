<?php
/**
 * Entry PDF generation - Legacy style
 */

require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/utils.php';
require_once __DIR__ . '/../../lib/pdf/fpdf.php';

require_login();

$entry_id = intval(get('id', 0));
if ($entry_id == 0) {
    die('ID manquant');
}

// Get entry
$sql = "SELECT e.*, j.code as journal_code, j.label as journal_label, u.username as created_by_name
        FROM entries e
        LEFT JOIN journals j ON e.journal_id = j.id
        LEFT JOIN users u ON e.created_by = u.id
        WHERE e.id = $entry_id";
$result = db_query($sql);
if (db_num_rows($result) == 0) {
    die('Piece introuvable');
}
$entry = db_fetch_assoc($result);

// Get lines
$sql = "SELECT el.*, a.code as account_code, a.label as account_label,
               tp.name as third_party_name
        FROM entry_lines el
        LEFT JOIN accounts a ON el.account_id = a.id
        LEFT JOIN third_parties tp ON el.third_party_id = tp.id
        WHERE el.entry_id = $entry_id
        ORDER BY el.line_no";
$lines = db_fetch_all(db_query($sql));

// Get company
$company = get_company();

// Create PDF
$pdf = new FPDF('P', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetMargins(15, 15, 15);

// Header
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, utf8_decode($company ? $company['name'] : 'Comptabilite'), 0, 1, 'C');

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, 'Piece Comptable', 0, 1, 'C');
$pdf->Ln(5);

// Entry info
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(40, 6, 'Journal:', 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, utf8_decode($entry['journal_code'] . ' - ' . $entry['journal_label']), 0, 1);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(40, 6, 'Date:', 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, format_date($entry['entry_date']), 0, 1);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(40, 6, utf8_decode('N° Piece:'), 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, $entry['piece_number'] ? $entry['piece_number'] : '(Brouillon)', 0, 1);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(40, 6, utf8_decode('Libellé:'), 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, utf8_decode($entry['label']), 0, 1);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(40, 6, 'Statut:', 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, $entry['status'] === 'posted' ? utf8_decode('Validé') : 'Brouillon', 0, 1);

$pdf->Ln(5);

// Lines table
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(0, 51, 102);
$pdf->SetTextColor(255, 255, 255);

$pdf->Cell(25, 7, 'Compte', 1, 0, 'C', true);
$pdf->Cell(60, 7, utf8_decode('Libellé'), 1, 0, 'C', true);
$pdf->Cell(35, 7, 'Tiers', 1, 0, 'C', true);
$pdf->Cell(30, 7, utf8_decode('Débit'), 1, 0, 'C', true);
$pdf->Cell(30, 7, utf8_decode('Crédit'), 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(0, 0, 0);

$fill = false;
foreach ($lines as $line) {
    $pdf->SetFillColor(245, 245, 245);

    $pdf->Cell(25, 6, $line['account_code'], 1, 0, 'L', $fill);
    $pdf->Cell(60, 6, utf8_decode(substr($line['label'], 0, 35)), 1, 0, 'L', $fill);
    $pdf->Cell(35, 6, utf8_decode(substr($line['third_party_name'], 0, 20)), 1, 0, 'L', $fill);
    $pdf->Cell(30, 6, $line['debit'] > 0 ? number_format($line['debit'], 2, ',', ' ') : '', 1, 0, 'R', $fill);
    $pdf->Cell(30, 6, $line['credit'] > 0 ? number_format($line['credit'], 2, ',', ' ') : '', 1, 1, 'R', $fill);

    $fill = !$fill;
}

// Totals
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(220, 220, 220);
$pdf->Cell(120, 7, 'TOTAUX', 1, 0, 'R', true);
$pdf->Cell(30, 7, number_format($entry['total_debit'], 2, ',', ' '), 1, 0, 'R', true);
$pdf->Cell(30, 7, number_format($entry['total_credit'], 2, ',', ' '), 1, 1, 'R', true);

// Balance check
$pdf->Ln(3);
$balance = $entry['total_debit'] - $entry['total_credit'];
if (abs($balance) <= 0.01) {
    $pdf->SetTextColor(0, 128, 0);
    $pdf->Cell(0, 6, utf8_decode('Pièce équilibrée'), 0, 1, 'R');
} else {
    $pdf->SetTextColor(255, 0, 0);
    $pdf->Cell(0, 6, utf8_decode('Écart: ') . number_format($balance, 2, ',', ' '), 0, 1, 'R');
}

// Footer
$pdf->SetTextColor(128, 128, 128);
$pdf->SetFont('Arial', 'I', 8);
$pdf->Ln(10);
$pdf->Cell(0, 5, utf8_decode('Créé par: ' . $entry['created_by_name'] . ' le ' . format_datetime($entry['created_at'])), 0, 1);
if ($entry['posted_at']) {
    $pdf->Cell(0, 5, utf8_decode('Validé le: ' . format_datetime($entry['posted_at'])), 0, 1);
}
$pdf->Cell(0, 5, utf8_decode('Imprimé le: ' . date('d/m/Y H:i')), 0, 1);

// Output
$filename = 'piece_' . ($entry['piece_number'] ? $entry['piece_number'] : 'draft_' . $entry_id) . '.pdf';
$pdf->Output('I', $filename);
