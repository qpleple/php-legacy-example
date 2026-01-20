<?php
/**
 * AJAX endpoint for lettering auto-suggestion
 * Legacy style (2006) - Returns JSON
 *
 * Algorithm:
 * 1. Find exact 1-to-1 matches (one debit = one credit)
 * 2. Find N-to-1 matches (multiple debits = one credit, or vice versa)
 * 3. Score matches by: amount proximity, same third party, reference similarity, date proximity
 * 4. Return best suggestions sorted by score
 */

require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/utils.php';

// Set JSON header
header('Content-Type: application/json');

// Check auth
auth_start_session();
if (!auth_is_logged_in()) {
    echo json_encode(array('success' => false, 'error' => 'Non authentifie'));
    exit;
}

$account_id = intval(get('account_id', 0));
$third_party_id = intval(get('third_party_id', 0));

if ($account_id <= 0) {
    echo json_encode(array('success' => false, 'error' => 'Compte non specifie'));
    exit;
}

// Get company tolerance
$company = get_company();
$tolerance = isset($company['lettering_tolerance']) ? floatval($company['lettering_tolerance']) : 0.05;

/**
 * Get all unlettered lines for the account
 */
function get_unlettered_lines($account_id, $third_party_filter = 0) {
    $account_id = intval($account_id);

    $sql = "SELECT el.id, el.entry_id, el.account_id, el.third_party_id, el.label,
                   el.debit, el.credit,
                   e.entry_date, e.piece_number, e.label as entry_label,
                   (el.debit + el.credit) as line_total,
                   (el.debit + el.credit) - COALESCE(
                       (SELECT SUM(ABS(amount)) FROM lettering_items WHERE entry_line_id = el.id), 0
                   ) as available_amount
            FROM entry_lines el
            INNER JOIN entries e ON el.entry_id = e.id
            WHERE el.account_id = $account_id
              AND e.status = 'posted'
              AND (el.debit + el.credit) - COALESCE(
                  (SELECT SUM(ABS(amount)) FROM lettering_items WHERE entry_line_id = el.id), 0
              ) > 0.001";

    if ($third_party_filter > 0) {
        $sql .= " AND el.third_party_id = $third_party_filter";
    }

    $sql .= " ORDER BY e.entry_date, el.id";

    return db_fetch_all(db_query($sql));
}

/**
 * Calculate similarity score between two lines
 * Score components:
 * - Amount match (40 points max)
 * - Same third party (25 points)
 * - Reference similarity (20 points)
 * - Date proximity (15 points)
 */
function calculate_score($debit_line, $credit_line, $tolerance) {
    $score = 0;

    // Amount match (40 points if within tolerance)
    $debit_amount = floatval($debit_line['available_amount']);
    $credit_amount = floatval($credit_line['available_amount']);
    $amount_diff = abs($debit_amount - $credit_amount);

    if ($amount_diff <= $tolerance) {
        $score += 40;
    } elseif ($amount_diff <= $tolerance * 10) {
        // Partial score for close amounts
        $score += max(0, 40 - ($amount_diff / $tolerance) * 4);
    }

    // Same third party (25 points)
    if ($debit_line['third_party_id'] && $debit_line['third_party_id'] == $credit_line['third_party_id']) {
        $score += 25;
    }

    // Reference similarity (20 points)
    // Check if piece number or label contains common reference
    $debit_ref = $debit_line['piece_number'] . ' ' . $debit_line['label'];
    $credit_ref = $credit_line['piece_number'] . ' ' . $credit_line['label'];

    // Extract numbers from labels (invoice numbers, etc.)
    preg_match_all('/\d{4,}/', $debit_ref, $debit_numbers);
    preg_match_all('/\d{4,}/', $credit_ref, $credit_numbers);

    if (!empty($debit_numbers[0]) && !empty($credit_numbers[0])) {
        $common = array_intersect($debit_numbers[0], $credit_numbers[0]);
        if (!empty($common)) {
            $score += 20;
        }
    }

    // Check for FA/AV/BL references
    if (preg_match('/FA[-\s]?(\d+)/i', $debit_ref, $m1) && preg_match('/FA[-\s]?(\d+)/i', $credit_ref, $m2)) {
        if ($m1[1] == $m2[1]) {
            $score += 20;
        }
    }

    // Date proximity (15 points max)
    $debit_date = strtotime($debit_line['entry_date']);
    $credit_date = strtotime($credit_line['entry_date']);
    $day_diff = abs($debit_date - $credit_date) / 86400; // Days

    if ($day_diff <= 7) {
        $score += 15;
    } elseif ($day_diff <= 30) {
        $score += 10;
    } elseif ($day_diff <= 90) {
        $score += 5;
    }

    return $score;
}

/**
 * Find exact 1-to-1 matches
 */
function find_exact_matches($debit_lines, $credit_lines, $tolerance) {
    $suggestions = array();

    foreach ($debit_lines as $debit) {
        $debit_amount = floatval($debit['available_amount']);

        foreach ($credit_lines as $credit) {
            $credit_amount = floatval($credit['available_amount']);

            // Check if amounts match within tolerance
            if (abs($debit_amount - $credit_amount) <= $tolerance) {
                $score = calculate_score($debit, $credit, $tolerance);

                $suggestions[] = array(
                    'type' => 'exact_1_1',
                    'score' => $score,
                    'ecart' => abs($debit_amount - $credit_amount),
                    'total_debit' => $debit_amount,
                    'total_credit' => $credit_amount,
                    'lines' => array(
                        array('id' => intval($debit['id']), 'amount' => $debit_amount, 'type' => 'debit'),
                        array('id' => intval($credit['id']), 'amount' => $credit_amount, 'type' => 'credit')
                    )
                );
            }
        }
    }

    return $suggestions;
}

/**
 * Find N-to-1 matches (multiple lines matching one line)
 * Uses a simple greedy algorithm to find combinations
 */
function find_multi_matches($debit_lines, $credit_lines, $tolerance, $max_lines = 5) {
    $suggestions = array();

    // Try to match multiple debits to one credit
    foreach ($credit_lines as $credit) {
        $target = floatval($credit['available_amount']);
        $combinations = find_combinations($debit_lines, $target, $tolerance, $max_lines);

        foreach ($combinations as $combo) {
            $lines = array();
            $total_debit = 0;
            $avg_score = 0;

            foreach ($combo as $debit) {
                $amount = floatval($debit['available_amount']);
                $lines[] = array('id' => intval($debit['id']), 'amount' => $amount, 'type' => 'debit');
                $total_debit += $amount;
                $avg_score += calculate_score($debit, $credit, $tolerance);
            }

            $lines[] = array('id' => intval($credit['id']), 'amount' => floatval($credit['available_amount']), 'type' => 'credit');
            $avg_score = $avg_score / count($combo);

            $suggestions[] = array(
                'type' => 'n_to_1',
                'score' => $avg_score,
                'ecart' => abs($total_debit - $target),
                'total_debit' => $total_debit,
                'total_credit' => $target,
                'lines' => $lines
            );
        }
    }

    // Try to match multiple credits to one debit
    foreach ($debit_lines as $debit) {
        $target = floatval($debit['available_amount']);
        $combinations = find_combinations($credit_lines, $target, $tolerance, $max_lines);

        foreach ($combinations as $combo) {
            $lines = array(
                array('id' => intval($debit['id']), 'amount' => floatval($debit['available_amount']), 'type' => 'debit')
            );
            $total_credit = 0;
            $avg_score = 0;

            foreach ($combo as $credit) {
                $amount = floatval($credit['available_amount']);
                $lines[] = array('id' => intval($credit['id']), 'amount' => $amount, 'type' => 'credit');
                $total_credit += $amount;
                $avg_score += calculate_score($debit, $credit, $tolerance);
            }

            $avg_score = $avg_score / count($combo);

            $suggestions[] = array(
                'type' => '1_to_n',
                'score' => $avg_score,
                'ecart' => abs($target - $total_credit),
                'total_debit' => $target,
                'total_credit' => $total_credit,
                'lines' => $lines
            );
        }
    }

    return $suggestions;
}

/**
 * Find combinations of lines that sum to target amount (within tolerance)
 * Simple recursive algorithm with pruning
 */
function find_combinations($lines, $target, $tolerance, $max_lines, $start = 0, $current = array(), $current_sum = 0) {
    $results = array();

    // Check if current combination matches target
    if (count($current) >= 2 && abs($current_sum - $target) <= $tolerance) {
        $results[] = $current;
    }

    // Stop if we have too many lines
    if (count($current) >= $max_lines) {
        return $results;
    }

    // Try adding more lines
    for ($i = $start; $i < count($lines); $i++) {
        $amount = floatval($lines[$i]['available_amount']);
        $new_sum = $current_sum + $amount;

        // Prune if we've exceeded target by too much
        if ($new_sum > $target + $tolerance) {
            continue;
        }

        $new_current = $current;
        $new_current[] = $lines[$i];

        $sub_results = find_combinations($lines, $target, $tolerance, $max_lines, $i + 1, $new_current, $new_sum);
        $results = array_merge($results, $sub_results);

        // Limit results to avoid memory issues
        if (count($results) > 20) {
            break;
        }
    }

    return $results;
}

/**
 * Remove suggestions that use the same lines
 */
function filter_unique_suggestions($suggestions) {
    $used_lines = array();
    $filtered = array();

    foreach ($suggestions as $suggestion) {
        $lines_ids = array();
        foreach ($suggestion['lines'] as $line) {
            $lines_ids[] = $line['id'];
        }

        // Check if any line is already used
        $overlap = false;
        foreach ($lines_ids as $id) {
            if (isset($used_lines[$id])) {
                $overlap = true;
                break;
            }
        }

        if (!$overlap) {
            $filtered[] = $suggestion;
            foreach ($lines_ids as $id) {
                $used_lines[$id] = true;
            }
        }
    }

    return $filtered;
}

// Main execution
try {
    // Get all unlettered lines
    $all_lines = get_unlettered_lines($account_id, $third_party_id);

    // Separate into debits and credits
    $debit_lines = array();
    $credit_lines = array();

    foreach ($all_lines as $line) {
        if (floatval($line['debit']) > 0) {
            $debit_lines[] = $line;
        } else {
            $credit_lines[] = $line;
        }
    }

    // If no lines on one side, no suggestions possible
    if (empty($debit_lines) || empty($credit_lines)) {
        echo json_encode(array(
            'success' => true,
            'tolerance' => $tolerance,
            'suggestions' => array(),
            'message' => 'Pas assez de lignes pour suggerer un lettrage'
        ));
        exit;
    }

    // Find suggestions
    $suggestions = array();

    // 1. Find exact 1-to-1 matches
    $exact_matches = find_exact_matches($debit_lines, $credit_lines, $tolerance);
    $suggestions = array_merge($suggestions, $exact_matches);

    // 2. Find N-to-1 matches (limit to avoid performance issues)
    if (count($debit_lines) <= 20 && count($credit_lines) <= 20) {
        $multi_matches = find_multi_matches($debit_lines, $credit_lines, $tolerance, 4);
        $suggestions = array_merge($suggestions, $multi_matches);
    }

    // 3. Sort by score (descending)
    usort($suggestions, function($a, $b) {
        if ($a['score'] == $b['score']) {
            return $a['ecart'] - $b['ecart']; // Lower ecart is better
        }
        return $b['score'] - $a['score']; // Higher score is better
    });

    // 4. Filter to get unique suggestions (no overlapping lines)
    $suggestions = filter_unique_suggestions($suggestions);

    // 5. Limit to top 10 suggestions
    $suggestions = array_slice($suggestions, 0, 10);

    echo json_encode(array(
        'success' => true,
        'tolerance' => $tolerance,
        'debit_count' => count($debit_lines),
        'credit_count' => count($credit_lines),
        'suggestions' => $suggestions
    ));

} catch (Exception $e) {
    echo json_encode(array(
        'success' => false,
        'error' => $e->getMessage()
    ));
}
