<?php
/**
 * Lettering page - Legacy style with full business logic
 *
 * Features:
 * - Letter code generation (AA, AB, AC... ZZ)
 * - Partial lettering support
 * - Auto-suggestion algorithm
 * - Business rule validation
 */

require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/utils.php';

require_login();
require_role('accountant');

$third_party_id = intval(get('third_party_id', 0));
$account_id = intval(get('account_id', 0));

$third_party = null;
$account = null;

// Get company settings for tolerance
$company = get_company();
$tolerance = isset($company['lettering_tolerance']) ? floatval($company['lettering_tolerance']) : 0.05;

// Get third party or account info
if ($third_party_id > 0) {
    $sql = "SELECT tp.*, a.id as account_id, a.code as account_code, a.label as account_label
            FROM third_parties tp
            LEFT JOIN accounts a ON tp.account_id = a.id
            WHERE tp.id = $third_party_id";
    $result = db_query($sql);
    if (db_num_rows($result) > 0) {
        $third_party = db_fetch_assoc($result);
        $account_id = intval($third_party['account_id']);
    }
} elseif ($account_id > 0) {
    $sql = "SELECT * FROM accounts WHERE id = $account_id";
    $result = db_query($sql);
    if (db_num_rows($result) > 0) {
        $account = db_fetch_assoc($result);
    }
}

if ($account_id == 0) {
    set_flash('error', 'Compte non trouve.');
    redirect('/modules/letters/select.php');
}

// Verify account is letterable (411 or 401)
$account_check = $account ? $account : $third_party;
$account_code = $account ? $account['code'] : $third_party['account_code'];
if (substr($account_code, 0, 3) != '411' && substr($account_code, 0, 3) != '401') {
    set_flash('error', 'Ce compte ne peut pas etre lettre (seulement 411xxx et 401xxx).');
    redirect('/modules/letters/select.php');
}

/**
 * Generate next letter code for an account
 * Format: AA, AB, AC... AZ, BA, BB... ZZ
 * Partial lettering adds 'P' suffix: AAP, ABP...
 */
function generate_letter_code($account_id, $is_partial = false) {
    // Get the last letter code for this account (excluding partial suffix)
    $sql = "SELECT letter_code FROM lettering_groups
            WHERE account_id = " . intval($account_id) . "
            ORDER BY id DESC LIMIT 1";
    $result = db_query($sql);
    $last = db_fetch_assoc($result);

    if (!$last || empty($last['letter_code'])) {
        $code = 'AA';
    } else {
        // Remove 'P' suffix if present
        $last_code = preg_replace('/P$/', '', $last['letter_code']);

        // Handle case where last_code is less than 2 chars
        if (strlen($last_code) < 2) {
            $code = 'AA';
        } else {
            $first = ord($last_code[0]);
            $second = ord($last_code[1]);

            // Increment
            $second++;
            if ($second > ord('Z')) {
                $second = ord('A');
                $first++;
            }
            if ($first > ord('Z')) {
                // Wrap around (unlikely to happen)
                $first = ord('A');
                $second = ord('A');
            }

            $code = chr($first) . chr($second);
        }
    }

    return $is_partial ? $code . 'P' : $code;
}

/**
 * Get available (unlettered) amount for a line
 */
function get_available_amount($line_id) {
    $line_id = intval($line_id);

    // Get line total
    $sql = "SELECT (debit + credit) as total FROM entry_lines WHERE id = $line_id";
    $result = db_query($sql);
    $line = db_fetch_assoc($result);

    if (!$line) {
        return 0;
    }

    $total = floatval($line['total']);

    // Get already lettered amount
    $sql = "SELECT COALESCE(SUM(ABS(amount)), 0) as lettered FROM lettering_items WHERE entry_line_id = $line_id";
    $result = db_query($sql);
    $lettered = db_fetch_assoc($result);

    return $total - floatval($lettered['lettered']);
}

/**
 * Check if a line can be lettered (posted entry, correct account, not fully lettered)
 */
function can_letter_line($line_id, $account_id) {
    $line_id = intval($line_id);
    $account_id = intval($account_id);

    $sql = "SELECT el.*, e.status, e.period_id, p.status as period_status
            FROM entry_lines el
            INNER JOIN entries e ON e.id = el.entry_id
            LEFT JOIN periods p ON p.id = e.period_id
            WHERE el.id = $line_id";
    $result = db_query($sql);
    $line = db_fetch_assoc($result);

    if (!$line) {
        return array('ok' => false, 'error' => 'Ligne non trouvee');
    }

    if ($line['account_id'] != $account_id) {
        return array('ok' => false, 'error' => 'La ligne n\'appartient pas a ce compte');
    }

    if ($line['status'] != 'posted') {
        return array('ok' => false, 'error' => 'L\'ecriture n\'est pas validee');
    }

    $available = get_available_amount($line_id);
    if ($available < 0.001) {
        return array('ok' => false, 'error' => 'Ligne deja entierement lettree');
    }

    return array('ok' => true, 'line' => $line, 'available' => $available);
}

// Handle create lettering group
if (is_post() && post('action') === 'create_lettering') {
    csrf_verify();

    $selected_lines = isset($_POST['lines']) ? $_POST['lines'] : array();
    $amounts = isset($_POST['amounts']) ? $_POST['amounts'] : array();

    if (count($selected_lines) < 2) {
        set_flash('error', 'Selectionnez au moins 2 lignes pour le lettrage.');
        $redirect_url = $third_party_id > 0 ? "?third_party_id=$third_party_id" : "?account_id=$account_id";
        redirect('/modules/letters/letter.php' . $redirect_url);
    }

    // Validate all lines and calculate totals
    $total_debit = 0;
    $total_credit = 0;
    $lines_data = array();
    $first_third_party_id = null;
    $first_line = true;
    $is_partial = false;
    $errors = array();

    foreach ($selected_lines as $idx => $line_id) {
        $line_id = intval($line_id);

        // Validate line
        $check = can_letter_line($line_id, $account_id);
        if (!$check['ok']) {
            $errors[] = "Ligne #$line_id: " . $check['error'];
            continue;
        }

        $line = $check['line'];
        $available = $check['available'];

        // Determine amount to letter
        $requested_amount = isset($amounts[$idx]) ? floatval(str_replace(',', '.', $amounts[$idx])) : 0;
        if ($requested_amount <= 0 || $requested_amount > $available + 0.001) {
            $requested_amount = $available;
        }

        // Check if this is partial lettering
        if ($requested_amount < $available - 0.001) {
            $is_partial = true;
        }

        // Check third party consistency
        if ($first_line) {
            $first_third_party_id = $line['third_party_id'];
            $first_line = false;
        } else {
            if ($line['third_party_id'] != $first_third_party_id) {
                $errors[] = "Toutes les lignes doivent concerner le meme tiers";
            }
        }

        // Calculate totals (debit is positive, credit is negative for balance)
        if ($line['debit'] > 0) {
            $total_debit += $requested_amount;
            $signed_amount = $requested_amount;
        } else {
            $total_credit += $requested_amount;
            $signed_amount = -$requested_amount;
        }

        $lines_data[] = array(
            'id' => $line_id,
            'amount' => $requested_amount,
            'signed_amount' => $signed_amount,
            'is_partial' => ($requested_amount < $available - 0.001)
        );
    }

    if (count($errors) > 0) {
        set_flash('error', implode('<br>', $errors));
        $redirect_url = $third_party_id > 0 ? "?third_party_id=$third_party_id" : "?account_id=$account_id";
        redirect('/modules/letters/letter.php' . $redirect_url);
    }

    // Check balance with tolerance
    $ecart = abs($total_debit - $total_credit);
    if ($ecart > $tolerance) {
        set_flash('error', 'Ecart de ' . number_format($ecart, 2, ',', ' ') . ' EUR superieur a la tolerance (' . number_format($tolerance, 2, ',', ' ') . ' EUR)');
        $redirect_url = $third_party_id > 0 ? "?third_party_id=$third_party_id" : "?account_id=$account_id";
        redirect('/modules/letters/letter.php' . $redirect_url);
    }

    // Generate letter code
    $letter_code = generate_letter_code($account_id, $is_partial);

    // Create lettering group
    $user_id = auth_user_id();
    $tp_id = $first_third_party_id ? intval($first_third_party_id) : 'NULL';
    $is_partial_int = $is_partial ? 1 : 0;

    $sql = "INSERT INTO lettering_groups (account_id, third_party_id, letter_code, is_partial, created_at, created_by)
            VALUES ($account_id, $tp_id, '" . db_escape($letter_code) . "', $is_partial_int, datetime('now'), $user_id)";
    db_query($sql);
    $group_id = db_insert_id();

    // Add lettering items
    foreach ($lines_data as $ld) {
        $sql = "INSERT INTO lettering_items (group_id, entry_line_id, amount)
                VALUES ($group_id, " . $ld['id'] . ", " . $ld['signed_amount'] . ")";
        db_query($sql);
    }

    audit_log('CREATE', 'lettering_groups', $group_id,
              'Lettrage ' . $letter_code . ': ' . count($lines_data) . ' lignes, ' .
              number_format($total_debit, 2, ',', ' ') . ' EUR');

    set_flash('success', 'Lettrage ' . h($letter_code) . ' cree avec succes (' . count($lines_data) . ' lignes).');

    $redirect_url = $third_party_id > 0 ? "?third_party_id=$third_party_id" : "?account_id=$account_id";
    redirect('/modules/letters/letter.php' . $redirect_url);
}

// Handle delete lettering group
if (is_post() && post('action') === 'delete_lettering') {
    csrf_verify();

    $group_id = intval(post('group_id'));

    // Check if any line is in a locked period
    $sql = "SELECT lg.letter_code, p.status as period_status
            FROM lettering_groups lg
            INNER JOIN lettering_items li ON li.group_id = lg.id
            INNER JOIN entry_lines el ON el.id = li.entry_line_id
            INNER JOIN entries e ON e.id = el.entry_id
            LEFT JOIN periods p ON p.id = e.period_id
            WHERE lg.id = $group_id AND lg.account_id = $account_id
            LIMIT 1";
    $result = db_query($sql);
    $group = db_fetch_assoc($result);

    if (!$group) {
        set_flash('error', 'Groupe de lettrage non trouve.');
    } elseif ($group['period_status'] == 'locked') {
        set_flash('error', 'Impossible de delettrer: une ecriture est en periode verrouillee.');
    } else {
        $letter_code = $group['letter_code'];

        db_query("DELETE FROM lettering_items WHERE group_id = $group_id");
        db_query("DELETE FROM lettering_groups WHERE id = $group_id");

        audit_log('DELETE', 'lettering_groups', $group_id, 'Delettrage ' . $letter_code);
        set_flash('success', 'Lettrage ' . h($letter_code) . ' supprime.');
    }

    $redirect_url = $third_party_id > 0 ? "?third_party_id=$third_party_id" : "?account_id=$account_id";
    redirect('/modules/letters/letter.php' . $redirect_url);
}

// Get unlettered lines (fully unlettered)
$sql = "SELECT el.*, e.entry_date, e.piece_number, e.label as entry_label,
               tp.name as third_party_name,
               (el.debit + el.credit) as line_total,
               (el.debit + el.credit) - COALESCE(
                   (SELECT SUM(ABS(amount)) FROM lettering_items WHERE entry_line_id = el.id), 0
               ) as available_amount
        FROM entry_lines el
        INNER JOIN entries e ON el.entry_id = e.id
        LEFT JOIN third_parties tp ON el.third_party_id = tp.id
        WHERE el.account_id = $account_id
          AND e.status = 'posted'
          AND (el.debit + el.credit) - COALESCE(
              (SELECT SUM(ABS(amount)) FROM lettering_items WHERE entry_line_id = el.id), 0
          ) > 0.001
        ORDER BY e.entry_date, el.id";
$unlettered_lines = db_fetch_all(db_query($sql));

// Calculate unlettered totals
$unlettered_debit = 0;
$unlettered_credit = 0;
foreach ($unlettered_lines as $line) {
    if ($line['debit'] > 0) {
        $unlettered_debit += $line['available_amount'];
    } else {
        $unlettered_credit += $line['available_amount'];
    }
}

// Get existing lettering groups for this account
$sql = "SELECT lg.*, u.username as created_by_name,
               (SELECT SUM(ABS(amount)) FROM lettering_items li WHERE li.group_id = lg.id AND amount > 0) as total_debit,
               (SELECT SUM(ABS(amount)) FROM lettering_items li WHERE li.group_id = lg.id AND amount < 0) as total_credit,
               (SELECT COUNT(*) FROM lettering_items li WHERE li.group_id = lg.id) as line_count
        FROM lettering_groups lg
        LEFT JOIN users u ON lg.created_by = u.id
        WHERE lg.account_id = $account_id
        ORDER BY lg.created_at DESC
        LIMIT 50";
$lettering_groups = db_fetch_all(db_query($sql));

$page_title = 'Lettrage';
require_once __DIR__ . '/../../header.php';
?>

<h2>Lettrage</h2>

<div class="mb-20" style="background: #f9f9f9; padding: 15px; border: 1px solid #ccc;">
    <?php if ($third_party): ?>
    <p><strong>Tiers:</strong> <?php echo h($third_party['name']); ?>
       (<?php echo $third_party['type'] === 'customer' ? 'Client' : 'Fournisseur'; ?>)</p>
    <p><strong>Compte:</strong> <?php echo h($third_party['account_code'] . ' - ' . $third_party['account_label']); ?></p>
    <?php else: ?>
    <p><strong>Compte:</strong> <?php echo h($account['code'] . ' - ' . $account['label']); ?></p>
    <?php endif; ?>
    <p><strong>Solde non lettre:</strong>
        Debit: <?php echo format_money($unlettered_debit); ?> |
        Credit: <?php echo format_money($unlettered_credit); ?> |
        <span style="<?php echo ($unlettered_debit - $unlettered_credit) >= 0 ? 'color: green;' : 'color: red;'; ?> font-weight: bold;">
            Solde: <?php echo format_money($unlettered_debit - $unlettered_credit); ?>
        </span>
    </p>
    <p><strong>Tolerance:</strong> <?php echo number_format($tolerance, 2, ',', ' '); ?> EUR</p>
</div>

<h3>Lignes a lettrer (<?php echo count($unlettered_lines); ?>)</h3>

<div class="mb-10">
    <button type="button" id="btn-auto-suggest" class="btn btn-primary" onclick="autoSuggest()"<?php echo count($unlettered_lines) == 0 ? ' disabled' : ''; ?>>
        Suggestion automatique
    </button>
    <button type="button" class="btn" onclick="clearSelection()"<?php echo count($unlettered_lines) == 0 ? ' disabled' : ''; ?>>
        Effacer la selection
    </button>
</div>

<form method="post" action="" id="lettering-form">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="create_lettering">

    <table class="data-table" id="unlettered-table">
        <thead>
            <tr>
                <th style="width: 30px;">
                    <input type="checkbox" id="select-all" onclick="toggleSelectAll(this)"<?php echo count($unlettered_lines) == 0 ? ' disabled' : ''; ?>>
                </th>
                <th>Date</th>
                <th>Piece</th>
                <th>Libelle</th>
                <th>Tiers</th>
                <th>Debit</th>
                <th>Credit</th>
                <th>Disponible</th>
                <th>Montant a lettrer</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($unlettered_lines) > 0): ?>
            <?php foreach ($unlettered_lines as $idx => $line): ?>
            <tr class="lettering-line"
                id="line-<?php echo $line['id']; ?>"
                data-id="<?php echo $line['id']; ?>"
                data-debit="<?php echo $line['debit']; ?>"
                data-credit="<?php echo $line['credit']; ?>"
                data-available="<?php echo $line['available_amount']; ?>"
                data-third-party="<?php echo $line['third_party_id']; ?>">
                <td>
                    <input type="checkbox"
                           name="lines[]"
                           value="<?php echo $line['id']; ?>"
                           class="line-checkbox"
                           id="chk-<?php echo $line['id']; ?>"
                           onchange="updateSelection()">
                </td>
                <td><?php echo format_date($line['entry_date']); ?></td>
                <td>
                    <a href="/modules/entries/edit.php?id=<?php echo $line['entry_id']; ?>" target="_blank">
                        <?php echo h($line['piece_number']); ?>
                    </a>
                </td>
                <td><?php echo h($line['label']); ?></td>
                <td><?php echo h($line['third_party_name']); ?></td>
                <td class="number"><?php echo $line['debit'] > 0 ? format_money($line['debit']) : ''; ?></td>
                <td class="number"><?php echo $line['credit'] > 0 ? format_money($line['credit']) : ''; ?></td>
                <td class="number"><?php echo format_money($line['available_amount']); ?></td>
                <td>
                    <input type="text"
                           name="amounts[]"
                           class="amount-input"
                           id="amount-<?php echo $line['id']; ?>"
                           value="<?php echo number_format($line['available_amount'], 2, ',', ''); ?>"
                           style="width: 80px; text-align: right;"
                           onchange="updateSelection()"
                           onkeyup="updateSelection()">
                </td>
            </tr>
            <?php endforeach; ?>
            <?php else: ?>
            <tr>
                <td colspan="9" style="text-align: center; padding: 20px; color: #666;">
                    Toutes les lignes sont lettrees pour ce compte.
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr class="report-totals">
                <td colspan="5">Totaux non lettres</td>
                <td class="number"><?php echo format_money($unlettered_debit); ?></td>
                <td class="number"><?php echo format_money($unlettered_credit); ?></td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>

    <div class="lettering-summary" style="background: #eee; padding: 15px; margin-top: 10px; border: 1px solid #ccc;">
        <table style="width: 100%;">
            <tr>
                <td><strong>Selection:</strong> <span id="selection-count">0</span> lignes</td>
                <td class="number"><strong>Debit:</strong> <span id="selection-debit">0,00</span> EUR</td>
                <td class="number"><strong>Credit:</strong> <span id="selection-credit">0,00</span> EUR</td>
                <td class="number">
                    <strong>Ecart:</strong>
                    <span id="selection-ecart" class="balanced">0,00</span> EUR
                    <span id="ecart-status"></span>
                </td>
                <td style="text-align: right;">
                    <button type="submit" id="btn-create-lettering" class="btn btn-success" disabled>
                        Creer le lettrage
                    </button>
                </td>
            </tr>
        </table>
    </div>
</form>

<h3 class="mt-20">Lettrages existants (<?php echo count($lettering_groups); ?>)</h3>

<?php if (count($lettering_groups) > 0): ?>
<table class="data-table">
    <thead>
        <tr>
            <th>Code</th>
            <th>Date</th>
            <th>Cree par</th>
            <th>Lignes</th>
            <th>Debit</th>
            <th>Credit</th>
            <th>Type</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($lettering_groups as $lg): ?>
        <tr>
            <td><strong><?php echo h($lg['letter_code']); ?></strong></td>
            <td><?php echo format_datetime($lg['created_at']); ?></td>
            <td><?php echo h($lg['created_by_name']); ?></td>
            <td><?php echo $lg['line_count']; ?></td>
            <td class="number"><?php echo format_money($lg['total_debit']); ?></td>
            <td class="number"><?php echo format_money($lg['total_credit']); ?></td>
            <td>
                <?php if ($lg['is_partial']): ?>
                <span style="color: orange;">Partiel</span>
                <?php else: ?>
                <span style="color: green;">Complet</span>
                <?php endif; ?>
            </td>
            <td>
                <a href="/modules/letters/history.php?group_id=<?php echo $lg['id']; ?>" class="btn btn-small">
                    Detail
                </a>
                <form method="post" action="" style="display: inline;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="delete_lettering">
                    <input type="hidden" name="group_id" value="<?php echo $lg['id']; ?>">
                    <button type="submit" class="btn btn-small btn-danger confirm-action"
                            data-confirm="Supprimer le lettrage <?php echo h($lg['letter_code']); ?> ?">
                        Delettrer
                    </button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<p>Aucun lettrage existant pour ce compte.</p>
<?php endif; ?>

<div class="mt-20">
    <a href="/modules/letters/select.php" class="btn">Retour a la selection</a>
    <a href="/modules/letters/history.php?account_id=<?php echo $account_id; ?>" class="btn">Historique complet</a>
</div>

<script type="text/javascript">
var tolerance = <?php echo $tolerance; ?>;
var accountId = <?php echo $account_id; ?>;
var thirdPartyId = <?php echo $third_party_id; ?>;

function parseAmount(str) {
    if (!str) return 0;
    str = str.toString().replace(/\s/g, '').replace(',', '.');
    return parseFloat(str) || 0;
}

function formatAmount(num) {
    return num.toFixed(2).replace('.', ',');
}

function updateSelection() {
    var totalDebit = 0;
    var totalCredit = 0;
    var count = 0;

    var checkboxes = document.getElementsByClassName('line-checkbox');
    for (var i = 0; i < checkboxes.length; i++) {
        if (checkboxes[i].checked) {
            count++;
            var lineId = checkboxes[i].value;
            var row = document.getElementById('line-' + lineId);
            var amountInput = document.getElementById('amount-' + lineId);
            var amount = parseAmount(amountInput.value);

            var debit = parseFloat(row.getAttribute('data-debit')) || 0;
            var credit = parseFloat(row.getAttribute('data-credit')) || 0;

            if (debit > 0) {
                totalDebit += amount;
            } else {
                totalCredit += amount;
            }

            // Highlight selected row
            row.style.backgroundColor = '#ffffcc';
        } else {
            var lineId = checkboxes[i].value;
            var row = document.getElementById('line-' + lineId);
            row.style.backgroundColor = '';
        }
    }

    var ecart = Math.abs(totalDebit - totalCredit);

    document.getElementById('selection-count').innerHTML = count;
    document.getElementById('selection-debit').innerHTML = formatAmount(totalDebit);
    document.getElementById('selection-credit').innerHTML = formatAmount(totalCredit);
    document.getElementById('selection-ecart').innerHTML = formatAmount(ecart);

    var ecartSpan = document.getElementById('selection-ecart');
    var statusSpan = document.getElementById('ecart-status');
    var btn = document.getElementById('btn-create-lettering');

    if (count >= 2 && ecart <= tolerance) {
        ecartSpan.style.color = 'green';
        ecartSpan.style.fontWeight = 'bold';
        statusSpan.innerHTML = ' ✓';
        statusSpan.style.color = 'green';
        btn.disabled = false;
    } else {
        ecartSpan.style.color = 'red';
        ecartSpan.style.fontWeight = 'bold';
        if (ecart > tolerance) {
            statusSpan.innerHTML = ' (tolerance: ' + formatAmount(tolerance) + ')';
        } else if (count < 2) {
            statusSpan.innerHTML = ' (min 2 lignes)';
        } else {
            statusSpan.innerHTML = '';
        }
        statusSpan.style.color = 'red';
        btn.disabled = true;
    }
}

function toggleSelectAll(checkbox) {
    var checkboxes = document.getElementsByClassName('line-checkbox');
    for (var i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = checkbox.checked;
    }
    updateSelection();
}

function clearSelection() {
    var checkboxes = document.getElementsByClassName('line-checkbox');
    for (var i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = false;
    }
    document.getElementById('select-all').checked = false;
    updateSelection();
}

function autoSuggest() {
    // AJAX call to get suggestions
    var xhr = new XMLHttpRequest();
    var url = '/modules/letters/ajax_suggest.php?account_id=' + accountId;
    if (thirdPartyId > 0) {
        url += '&third_party_id=' + thirdPartyId;
    }

    xhr.open('GET', url, true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState == 4) {
            if (xhr.status == 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success && response.suggestions && response.suggestions.length > 0) {
                        applySuggestion(response.suggestions[0]);
                    } else {
                        alert('Aucune suggestion trouvee pour un lettrage equilibre.');
                    }
                } catch (e) {
                    alert('Erreur lors de la suggestion: ' + e.message);
                }
            } else {
                alert('Erreur serveur lors de la suggestion.');
            }
        }
    };
    xhr.send();
}

function applySuggestion(suggestion) {
    // Clear current selection
    clearSelection();

    // Select suggested lines
    if (suggestion.lines && suggestion.lines.length > 0) {
        for (var i = 0; i < suggestion.lines.length; i++) {
            var lineId = suggestion.lines[i].id;
            var amount = suggestion.lines[i].amount;

            var checkbox = document.getElementById('chk-' + lineId);
            var amountInput = document.getElementById('amount-' + lineId);

            if (checkbox && amountInput) {
                checkbox.checked = true;
                amountInput.value = formatAmount(amount);
            }
        }
    }

    updateSelection();
}

// Initialize on page load
if (document.getElementById('unlettered-table')) {
    updateSelection();
}
</script>

<?php require_once __DIR__ . '/../../footer.php'; ?>
