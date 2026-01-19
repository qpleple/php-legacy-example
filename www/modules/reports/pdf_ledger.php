<?php
/**
 * General Ledger PDF - Legacy style
 */

require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/utils.php';
require_once __DIR__ . '/../../lib/pdf/fpdf.php';

require_login();

// Filters
$period_id = get('period_id', '');
$account_from = get('account_from', '');
$account_to = get('account_to', '');
$journal_id = get('journal_id', '');

// Build query conditions
$where = "e.status = 'posted'";
if ($period_id) {
    $period_id = intval($period_id);
    $where .= " AND e.period_id = $period_id";
}
if ($account_from) {
    $account_from_esc = db_escape($account_from);
    $where .= " AND a.code >= '$account_from_esc'";
}
if ($account_to) {
    $account_to_esc = db_escape($account_to);
    $where .= " AND a.code <= '$account_to_esc'";
}
if ($journal_id) {
    $journal_id = intval($journal_id);
    $where .= " AND e.journal_id = $journal_id";
}

// Get ledger data
$sql = "SELECT a.id as account_id, a.code as account_code, a.label as account_label,
               el.id as line_id, e.entry_date, e.piece_number, j.code as journal_code,
               el.label as line_label, el.debit, el.credit
        FROM entry_lines el
        INNER JOIN entries e ON el.entry_id = e.id
        INNER JOIN accounts a ON el.account_id = a.id
        LEFT JOIN journals j ON e.journal_id = j.id
        WHERE $where
        ORDER BY a.code, e.entry_date, e.id, el.line_no";
$result = db_query($sql);

// Group by account
$ledger = array();
while ($row = db_fetch_assoc($result)) {
    $acc_code = $row['account_code'];
    if (!isset($ledger[$acc_code])) {
        $ledger[$acc_code] = array(
            'code' => $row['account_code'],
            'label' => $row['account_label'],
            'lines' => array(),
            'total_debit' => 0,
            'total_credit' => 0
        );
    }
    $ledger[$acc_code]['lines'][] = $row;
    $ledger[$acc_code]['total_debit'] += $row['debit'];
    $ledger[$acc_code]['total_credit'] += $row['credit'];
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
$pdf->Cell(0, 6, 'Grand Livre', 0, 1, 'C');
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(0, 5, utf8_decode('Imprimé le ' . date('d/m/Y H:i')), 0, 1, 'C');
$pdf->Ln(5);

foreach ($ledger as $acc) {
    // Check if we need a new page
    if ($pdf->GetY() > 250) {
        $pdf->AddPage();
    }

    // Account header
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(0, 51, 102);
    $pdf->SetTextColor(255);
    $pdf->Cell(0, 6, utf8_decode($acc['code'] . ' - ' . $acc['label']), 1, 1, 'L', true);

    // Column headers
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(220, 220, 220);
    $pdf->SetTextColor(0);
    $pdf->Cell(20, 5, 'Date', 1, 0, 'C', true);
    $pdf->Cell(15, 5, 'Jnl', 1, 0, 'C', true);
    $pdf->Cell(25, 5, 'Piece', 1, 0, 'C', true);
    $pdf->Cell(70, 5, utf8_decode('Libellé'), 1, 0, 'C', true);
    $pdf->Cell(25, 5, utf8_decode('Débit'), 1, 0, 'C', true);
    $pdf->Cell(25, 5, utf8_decode('Crédit'), 1, 0, 'C', true);
    $pdf->Cell(25, 5, 'Solde', 1, 1, 'C', true);

    // Lines
    $pdf->SetFont('Arial', '', 8);
    $running_balance = 0;
    foreach ($acc['lines'] as $line) {
        $running_balance += $line['debit'] - $line['credit'];

        if ($pdf->GetY() > 270) {
            $pdf->AddPage();
            // Repeat header
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->SetFillColor(220, 220, 220);
            $pdf->Cell(20, 5, 'Date', 1, 0, 'C', true);
            $pdf->Cell(15, 5, 'Jnl', 1, 0, 'C', true);
            $pdf->Cell(25, 5, 'Piece', 1, 0, 'C', true);
            $pdf->Cell(70, 5, utf8_decode('Libellé'), 1, 0, 'C', true);
            $pdf->Cell(25, 5, utf8_decode('Débit'), 1, 0, 'C', true);
            $pdf->Cell(25, 5, utf8_decode('Crédit'), 1, 0, 'C', true);
            $pdf->Cell(25, 5, 'Solde', 1, 1, 'C', true);
            $pdf->SetFont('Arial', '', 8);
        }

        $pdf->Cell(20, 5, format_date($line['entry_date']), 1, 0, 'C');
        $pdf->Cell(15, 5, $line['journal_code'], 1, 0, 'C');
        $pdf->Cell(25, 5, $line['piece_number'], 1, 0, 'L');
        $pdf->Cell(70, 5, utf8_decode(substr($line['line_label'], 0, 40)), 1, 0, 'L');
        $pdf->Cell(25, 5, $line['debit'] > 0 ? number_format($line['debit'], 2, ',', ' ') : '', 1, 0, 'R');
        $pdf->Cell(25, 5, $line['credit'] > 0 ? number_format($line['credit'], 2, ',', ' ') : '', 1, 0, 'R');
        $pdf->Cell(25, 5, number_format($running_balance, 2, ',', ' '), 1, 1, 'R');
    }

    // Account total
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(130, 5, 'Total ' . $acc['code'], 1, 0, 'R', true);
    $pdf->Cell(25, 5, number_format($acc['total_debit'], 2, ',', ' '), 1, 0, 'R', true);
    $pdf->Cell(25, 5, number_format($acc['total_credit'], 2, ',', ' '), 1, 0, 'R', true);
    $pdf->Cell(25, 5, number_format($acc['total_debit'] - $acc['total_credit'], 2, ',', ' '), 1, 1, 'R', true);

    $pdf->Ln(3);
}

$pdf->Output('I', 'grand_livre.pdf');
