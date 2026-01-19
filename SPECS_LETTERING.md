# Spécifications Fonctionnelles - Lettrage Automatique

## 1. Vue d'ensemble

### 1.1 Objectif
Le lettrage permet de rapprocher les factures (écritures au débit des comptes clients ou au crédit des comptes fournisseurs) avec leurs règlements correspondants. Cette fonctionnalité permet de suivre les créances et dettes non soldées.

### 1.2 Emplacement
- Menu: **Écritures > Lettrage**
- Fichiers à créer:
  - `www/modules/lettering/index.php` - Liste des comptes à lettrer
  - `www/modules/lettering/letter.php` - Écran de lettrage manuel + auto
  - `www/modules/lettering/history.php` - Historique des lettrages
  - `www/modules/lettering/ajax_suggest.php` - Suggestions automatiques (XMLHttpRequest 2006-style)

---

## 2. Règles Métier

### 2.1 Règles de Base

| Règle | Description | Validation |
|-------|-------------|------------|
| **R1** | Le lettrage ne concerne que les comptes clients (411xxx) et fournisseurs (401xxx) | Vérifier `account.code LIKE '411%' OR account.code LIKE '401%'` |
| **R2** | Seules les écritures validées (status='posted') peuvent être lettrées | Vérifier `entries.status = 'posted'` |
| **R3** | Une ligne d'écriture ne peut appartenir qu'à un seul groupe de lettrage | Contrainte d'unicité sur `lettering_items.entry_line_id` |
| **R4** | Le lettrage doit concerner le même compte comptable | Toutes les lignes d'un groupe ont le même `account_id` |
| **R5** | Le lettrage doit concerner le même tiers (si renseigné) | Toutes les lignes d'un groupe ont le même `third_party_id` (ou tous NULL) |
| **R6** | Un groupe de lettrage complet doit être équilibré (somme = 0) avec tolérance | `ABS(SUM(debit) - SUM(credit)) <= tolerance` |
| **R7** | La tolérance par défaut est de 0.05 EUR | Configurable dans `company.lettering_tolerance` |
| **R8** | Les écritures en période verrouillée peuvent être lettrées mais pas dé-lettrées | Vérifier `periods.status` avant unletter |

### 2.2 Règles de Lettrage Partiel

| Règle | Description |
|-------|-------------|
| **R9** | Une ligne peut être partiellement lettrée | Le champ `lettering_items.amount` peut être < montant total de la ligne |
| **R10** | Le montant lettré ne peut pas dépasser le montant de la ligne | `lettering_items.amount <= entry_lines.debit + entry_lines.credit` |
| **R11** | Le solde non lettré d'une ligne = montant total - somme des montants lettrés | Calculé dynamiquement |
| **R12** | Un lettrage partiel génère un code avec suffixe 'P' | Ex: "AA" (complet) vs "AAP" (partiel) |

### 2.3 Règles de Code de Lettrage

| Règle | Description |
|-------|-------------|
| **R13** | Le code de lettrage est généré par compte | Séquence indépendante par `account_id` |
| **R14** | Format: 2 lettres majuscules (AA, AB, AC... AZ, BA, BB...) | 676 combinaisons possibles par compte |
| **R15** | Le code est stocké dans `lettering_groups` (nouvelle colonne à ajouter) | Champ `letter_code` |

### 2.4 Règles Chronologiques

| Règle | Description |
|-------|-------------|
| **R16** | Aucune contrainte de date entre facture et règlement | Un acompte peut précéder la facture |
| **R17** | La date du lettrage est la date système | Stockée dans `lettering_groups.created_at` |

---

## 3. Écrans

### 3.1 Liste des Comptes à Lettrer (`index.php`)

#### Affichage
```
+------------------------------------------------------------------+
| LETTRAGE                                           [Historique]   |
+------------------------------------------------------------------+
| Filtres: [Clients v] [Fournisseurs v] [Non soldés uniquement ☑]  |
+------------------------------------------------------------------+
| Compte     | Libellé              | Lignes  | Solde non    | Act.|
|            |                      | à lettr.| lettré       |     |
+------------+----------------------+---------+--------------+-----+
| 411DURAND  | Client Durand SARL   |      12 |    1 250,00  | [→] |
| 411MARTIN  | Client Martin & Fils |       3 |      450,00  | [→] |
| 401FOURN01 | Fournisseur ABC      |       8 |   -2 100,00  | [→] |
+------------------------------------------------------------------+
| Total: 23 lignes non lettrées                                    |
+------------------------------------------------------------------+
```

#### Requête SQL
```sql
SELECT
    a.id,
    a.code,
    a.label,
    COUNT(el.id) as unlettered_count,
    SUM(el.debit - el.credit) - COALESCE(SUM(li.amount), 0) as unlettered_balance
FROM accounts a
INNER JOIN entry_lines el ON el.account_id = a.id
INNER JOIN entries e ON e.id = el.entry_id AND e.status = 'posted'
LEFT JOIN lettering_items li ON li.entry_line_id = el.id
WHERE (a.code LIKE '411%' OR a.code LIKE '401%')
  AND a.is_active = 1
GROUP BY a.id
HAVING unlettered_balance != 0 OR :show_all = 1
ORDER BY a.code
```

#### Actions
- Clic sur ligne → ouvre `letter.php?account_id=X`
- Bouton "Historique" → ouvre `history.php`

---

### 3.2 Écran de Lettrage (`letter.php`)

#### Paramètres URL
- `account_id` (obligatoire) - Compte à lettrer
- `third_party_id` (optionnel) - Filtrer par tiers

#### Affichage Principal
```
+------------------------------------------------------------------------+
| LETTRAGE - 411DURAND - Client Durand SARL                    [Retour]  |
+------------------------------------------------------------------------+
| Tiers: [Tous ▼] [Durand SARL] [Durand Négoce]                          |
+------------------------------------------------------------------------+
| Tolérance: [0.05] EUR    Sélection: 0 lignes = 0,00 EUR                |
+------------------------------------------------------------------------+

LIGNES NON LETTRÉES (12)
+------------------------------------------------------------------------+
| ☐ | Date       | Pièce         | Libellé              | Débit  | Crédit|
+---+------------+---------------+----------------------+--------+-------+
| ☐ | 15/01/2024 | VE2024-000012 | Facture FA-001       | 1200,00|       |
| ☐ | 18/01/2024 | VE2024-000015 | Facture FA-002       |  500,00|       |
| ☐ | 22/01/2024 | BK2024-000089 | Règlement CHQ 12345  |        | 1200,00|
| ☐ | 25/01/2024 | VE2024-000018 | Avoir AV-001         |        |   50,00|
+------------------------------------------------------------------------+

LIGNES PARTIELLEMENT LETTRÉES (2)
+------------------------------------------------------------------------+
| ☐ | Date       | Pièce         | Libellé        | Total  | Reste | Let.|
+---+------------+---------------+----------------+--------+-------+-----+
| ☐ | 10/01/2024 | VE2024-000008 | Facture FA-000 | 800,00 | 200,00| AAP |
+------------------------------------------------------------------------+

ACTIONS
+------------------------------------------------------------------------+
| [Suggérer automatiquement]  [Lettrer la sélection]  [Effacer sélection]|
+------------------------------------------------------------------------+

LETTRAGE EN COURS (sélection)
+------------------------------------------------------------------------+
| Pièce         | Libellé              | Montant à lettrer               |
+---------------+----------------------+---------------------------------+
| VE2024-000012 | Facture FA-001       | [1200,00______]                 |
| BK2024-000089 | Règlement CHQ 12345  | [1200,00______]                 |
+---------------+----------------------+---------------------------------+
| TOTAL         |                      | Débit: 1200,00  Crédit: 1200,00 |
| ÉCART         |                      | 0,00 ✓                          |
+------------------------------------------------------------------------+
| [Valider le lettrage]                                                   |
+------------------------------------------------------------------------+
```

#### Logique de Sélection (JavaScript 2006-style)
```javascript
// www/assets/js/lettering.js
var selectedLines = [];
var tolerance = 0.05;

function toggleLine(lineId, debit, credit) {
    var checkbox = document.getElementById('chk_' + lineId);
    var idx = selectedLines.indexOf(lineId);

    if (checkbox.checked && idx == -1) {
        selectedLines.push(lineId);
    } else if (!checkbox.checked && idx > -1) {
        selectedLines.splice(idx, 1);
    }

    updateSelectionTotal();
}

function updateSelectionTotal() {
    var totalDebit = 0;
    var totalCredit = 0;

    for (var i = 0; i < selectedLines.length; i++) {
        var lineId = selectedLines[i];
        totalDebit += parseFloat(document.getElementById('debit_' + lineId).value) || 0;
        totalCredit += parseFloat(document.getElementById('credit_' + lineId).value) || 0;
    }

    var ecart = Math.abs(totalDebit - totalCredit);
    document.getElementById('total_debit').innerHTML = formatMoney(totalDebit);
    document.getElementById('total_credit').innerHTML = formatMoney(totalCredit);
    document.getElementById('ecart').innerHTML = formatMoney(ecart);

    if (ecart <= tolerance) {
        document.getElementById('ecart').className = 'balanced';
        document.getElementById('btn_validate').disabled = false;
    } else {
        document.getElementById('ecart').className = 'unbalanced';
        document.getElementById('btn_validate').disabled = true;
    }
}

function formatMoney(amount) {
    return amount.toFixed(2).replace('.', ',');
}
```

---

### 3.3 Algorithme de Suggestion Automatique

#### Critères de Scoring (sur 100 points)

| Critère | Points | Description |
|---------|--------|-------------|
| Montant exact | 40 | `ABS(debit - credit) <= tolerance` |
| Même tiers | 25 | `third_party_id` identique |
| Référence similaire | 20 | Numéro de facture dans le libellé du règlement |
| Proximité de date | 15 | Bonus décroissant selon écart de jours |

#### Pseudo-code de l'Algorithme
```
FONCTION suggerer_lettrage(account_id, tolerance):

    // 1. Récupérer toutes les lignes non lettrées
    lignes_debit = SELECT * FROM entry_lines
                   WHERE account_id = account_id
                   AND debit > 0
                   AND non_lettre

    lignes_credit = SELECT * FROM entry_lines
                    WHERE account_id = account_id
                    AND credit > 0
                    AND non_lettre

    suggestions = []

    // 2. Pour chaque débit, chercher des crédits correspondants
    POUR CHAQUE debit IN lignes_debit:

        // 2a. Chercher correspondance exacte 1-1
        POUR CHAQUE credit IN lignes_credit:
            SI ABS(debit.montant - credit.montant) <= tolerance:
                score = calculer_score(debit, credit)
                suggestions.ajouter({
                    type: 'exact_1_1',
                    lignes: [debit, credit],
                    score: score,
                    ecart: ABS(debit.montant - credit.montant)
                })

        // 2b. Chercher correspondance N-1 (plusieurs crédits = 1 débit)
        combinaisons = trouver_combinaisons(lignes_credit, debit.montant, tolerance)
        POUR CHAQUE combo IN combinaisons:
            score = calculer_score_groupe(debit, combo)
            suggestions.ajouter({
                type: 'n_to_1',
                lignes: [debit] + combo,
                score: score,
                ecart: ABS(debit.montant - somme(combo))
            })

    // 3. Trier par score décroissant
    suggestions.trier_par(score, DESC)

    // 4. Éliminer les suggestions avec lignes déjà utilisées
    suggestions_finales = []
    lignes_utilisees = []

    POUR CHAQUE s IN suggestions:
        SI aucune_ligne_utilisee(s.lignes, lignes_utilisees):
            suggestions_finales.ajouter(s)
            lignes_utilisees.ajouter_toutes(s.lignes)

    RETOURNER suggestions_finales
```

#### Requête SQL pour Suggestions (ajax_suggest.php)
```sql
-- Trouver les correspondances exactes potentielles
SELECT
    d.id as debit_line_id,
    d.entry_id as debit_entry_id,
    d.debit as debit_amount,
    d.label as debit_label,
    d.third_party_id as debit_tp,
    ed.entry_date as debit_date,
    ed.piece_number as debit_piece,
    c.id as credit_line_id,
    c.entry_id as credit_entry_id,
    c.credit as credit_amount,
    c.label as credit_label,
    c.third_party_id as credit_tp,
    ec.entry_date as credit_date,
    ec.piece_number as credit_piece,
    ABS(d.debit - c.credit) as ecart,
    -- Score calculation
    CASE WHEN ABS(d.debit - c.credit) <= :tolerance THEN 40 ELSE 0 END +
    CASE WHEN d.third_party_id = c.third_party_id AND d.third_party_id IS NOT NULL THEN 25 ELSE 0 END +
    CASE WHEN c.label LIKE '%' || SUBSTR(d.label, -6) || '%' THEN 20 ELSE 0 END +
    MAX(0, 15 - ABS(JULIANDAY(ed.entry_date) - JULIANDAY(ec.entry_date))) as score
FROM entry_lines d
INNER JOIN entries ed ON ed.id = d.entry_id AND ed.status = 'posted'
INNER JOIN entry_lines c ON c.account_id = d.account_id AND c.credit > 0
INNER JOIN entries ec ON ec.id = c.entry_id AND ec.status = 'posted'
LEFT JOIN lettering_items lid ON lid.entry_line_id = d.id
LEFT JOIN lettering_items lic ON lic.entry_line_id = c.id
WHERE d.account_id = :account_id
  AND d.debit > 0
  AND lid.id IS NULL  -- Non lettré
  AND lic.id IS NULL  -- Non lettré
  AND ABS(d.debit - c.credit) <= :tolerance
ORDER BY score DESC, ecart ASC
LIMIT 50
```

---

### 3.4 Historique des Lettrages (`history.php`)

#### Affichage
```
+------------------------------------------------------------------------+
| HISTORIQUE DES LETTRAGES                                     [Retour]  |
+------------------------------------------------------------------------+
| Période: [01/01/2024] au [31/12/2024]    Compte: [Tous ▼]              |
+------------------------------------------------------------------------+
| Code | Compte     | Tiers        | Lignes | Montant  | Date     | Par  |
+------+------------+--------------+--------+----------+----------+------+
| AA   | 411DURAND  | Durand SARL  |      2 | 1 200,00 | 22/01/24 | admin|
| AB   | 411DURAND  | Durand SARL  |      3 |   750,00 | 25/01/24 | admin|
| AA   | 401FOURN01 | Fournisseur  |      2 | 2 100,00 | 28/01/24 | admin|
+------+------------+--------------+--------+----------+----------+------+
                                                        [Exporter CSV]
```

#### Actions
- Clic sur ligne → affiche détail du groupe avec option "Délettrer"
- "Délettrer" → supprime le groupe après confirmation (si période non verrouillée)

---

## 4. Modifications de la Base de Données

### 4.1 Nouvelle Colonne
```sql
-- Ajouter le code de lettrage au groupe
ALTER TABLE lettering_groups ADD COLUMN letter_code TEXT;
CREATE INDEX idx_lettering_groups_code ON lettering_groups (account_id, letter_code);

-- Ajouter la tolérance de lettrage à la configuration
ALTER TABLE company ADD COLUMN lettering_tolerance REAL DEFAULT 0.05;
```

### 4.2 Vue pour Soldes Non Lettrés
```sql
-- Vue des soldes non lettrés par ligne
CREATE VIEW v_unlettered_balances AS
SELECT
    el.id as entry_line_id,
    el.entry_id,
    el.account_id,
    el.third_party_id,
    el.label,
    el.debit,
    el.credit,
    (el.debit + el.credit) - COALESCE(SUM(li.amount), 0) as unlettered_amount,
    e.entry_date,
    e.piece_number
FROM entry_lines el
INNER JOIN entries e ON e.id = el.entry_id AND e.status = 'posted'
INNER JOIN accounts a ON a.id = el.account_id
    AND (a.code LIKE '411%' OR a.code LIKE '401%')
LEFT JOIN lettering_items li ON li.entry_line_id = el.id
GROUP BY el.id
HAVING unlettered_amount > 0.001;
```

---

## 5. Traitement POST - Validation du Lettrage

### 5.1 Structure de `letter.php` (POST)
```php
<?php
require_once '../../lib/db.php';
require_once '../../lib/auth.php';
require_once '../../lib/utils.php';

require_login();
require_role('accountant');

$account_id = get('account_id');
if (!$account_id) {
    redirect('index.php');
}

// Vérifier que le compte est lettrable (411 ou 401)
$account = db_fetch_assoc(db_query(
    "SELECT * FROM accounts WHERE id = " . db_escape($account_id)
));
if (!$account || (substr($account['code'], 0, 3) != '411' && substr($account['code'], 0, 3) != '401')) {
    set_flash('error', 'Ce compte ne peut pas être lettré');
    redirect('index.php');
}

// Récupérer la tolérance
$company = db_fetch_assoc(db_query("SELECT * FROM company WHERE id = 1"));
$tolerance = isset($company['lettering_tolerance']) ? $company['lettering_tolerance'] : 0.05;

// POST: Créer un nouveau lettrage
if (is_post() && post('action') == 'letter') {
    csrf_verify();

    $line_ids = post('line_ids'); // Array d'IDs
    $amounts = post('amounts');   // Array de montants (pour lettrage partiel)

    if (!is_array($line_ids) || count($line_ids) < 2) {
        set_flash('error', 'Sélectionnez au moins 2 lignes');
        redirect('letter.php?account_id=' . $account_id);
    }

    // Validation des lignes
    $total_debit = 0;
    $total_credit = 0;
    $third_party_id = null;
    $first_line = true;
    $lines_data = array();

    foreach ($line_ids as $idx => $line_id) {
        $line_id = intval($line_id);
        $amount = isset($amounts[$idx]) ? floatval($amounts[$idx]) : null;

        // Récupérer la ligne avec vérifications
        $sql = "SELECT el.*, e.status, e.entry_date, e.piece_number,
                       (el.debit + el.credit) - COALESCE(
                           (SELECT SUM(amount) FROM lettering_items WHERE entry_line_id = el.id), 0
                       ) as available_amount
                FROM entry_lines el
                INNER JOIN entries e ON e.id = el.entry_id
                WHERE el.id = " . db_escape($line_id) . "
                  AND el.account_id = " . db_escape($account_id);
        $line = db_fetch_assoc(db_query($sql));

        if (!$line) {
            set_flash('error', 'Ligne #' . $line_id . ' non trouvée ou compte incorrect');
            redirect('letter.php?account_id=' . $account_id);
        }

        if ($line['status'] != 'posted') {
            set_flash('error', 'La ligne #' . $line_id . ' n\'est pas validée');
            redirect('letter.php?account_id=' . $account_id);
        }

        // Déterminer le montant à lettrer
        if ($amount === null || $amount <= 0) {
            $amount = $line['available_amount'];
        }
        if ($amount > $line['available_amount'] + 0.001) {
            set_flash('error', 'Montant trop élevé pour la ligne #' . $line_id);
            redirect('letter.php?account_id=' . $account_id);
        }

        // Vérifier cohérence du tiers
        if ($first_line) {
            $third_party_id = $line['third_party_id'];
            $first_line = false;
        } else {
            if ($line['third_party_id'] != $third_party_id) {
                set_flash('error', 'Toutes les lignes doivent concerner le même tiers');
                redirect('letter.php?account_id=' . $account_id);
            }
        }

        // Accumuler les totaux
        if ($line['debit'] > 0) {
            $total_debit += $amount;
        } else {
            $total_credit += $amount;
        }

        $lines_data[] = array(
            'id' => $line_id,
            'amount' => $amount,
            'is_partial' => ($amount < $line['available_amount'] - 0.001)
        );
    }

    // Vérifier l'équilibre
    $ecart = abs($total_debit - $total_credit);
    if ($ecart > $tolerance) {
        set_flash('error', 'Écart de ' . format_amount($ecart) . ' EUR supérieur à la tolérance');
        redirect('letter.php?account_id=' . $account_id);
    }

    // Déterminer si lettrage partiel
    $is_partial = false;
    foreach ($lines_data as $ld) {
        if ($ld['is_partial']) {
            $is_partial = true;
            break;
        }
    }

    // Générer le code de lettrage
    $letter_code = generate_letter_code($account_id, $is_partial);

    // Créer le groupe
    $now = date('Y-m-d H:i:s');
    db_query("INSERT INTO lettering_groups (account_id, third_party_id, letter_code, created_at, created_by)
              VALUES (" . db_escape($account_id) . ", " . db_escape($third_party_id) . ",
                      '" . db_escape($letter_code) . "', '" . db_escape($now) . "', " . db_escape($_SESSION['user_id']) . ")");

    $group_id = db_last_insert_id();

    // Créer les items
    foreach ($lines_data as $ld) {
        db_query("INSERT INTO lettering_items (group_id, entry_line_id, amount)
                  VALUES (" . db_escape($group_id) . ", " . db_escape($ld['id']) . ", " . db_escape($ld['amount']) . ")");
    }

    // Audit
    audit_log('letter', 'lettering_groups', $group_id,
              'Lettrage ' . $letter_code . ': ' . count($lines_data) . ' lignes, ' . format_amount($total_debit) . ' EUR');

    set_flash('success', 'Lettrage ' . $letter_code . ' créé avec succès');
    redirect('letter.php?account_id=' . $account_id);
}

// POST: Délettrer
if (is_post() && post('action') == 'unletter') {
    csrf_verify();

    $group_id = intval(post('group_id'));

    // Vérifier que le groupe existe et appartient à ce compte
    $group = db_fetch_assoc(db_query(
        "SELECT lg.*, p.status as period_status
         FROM lettering_groups lg
         LEFT JOIN lettering_items li ON li.group_id = lg.id
         LEFT JOIN entry_lines el ON el.id = li.entry_line_id
         LEFT JOIN entries e ON e.id = el.entry_id
         LEFT JOIN periods p ON p.id = e.period_id
         WHERE lg.id = " . db_escape($group_id) . "
           AND lg.account_id = " . db_escape($account_id) . "
         LIMIT 1"
    ));

    if (!$group) {
        set_flash('error', 'Groupe de lettrage non trouvé');
        redirect('letter.php?account_id=' . $account_id);
    }

    if ($group['period_status'] == 'locked') {
        set_flash('error', 'Impossible de délettrer: période verrouillée');
        redirect('letter.php?account_id=' . $account_id);
    }

    // Supprimer les items puis le groupe
    db_query("DELETE FROM lettering_items WHERE group_id = " . db_escape($group_id));
    db_query("DELETE FROM lettering_groups WHERE id = " . db_escape($group_id));

    audit_log('unletter', 'lettering_groups', $group_id, 'Délettrage ' . $group['letter_code']);

    set_flash('success', 'Lettrage ' . $group['letter_code'] . ' supprimé');
    redirect('letter.php?account_id=' . $account_id);
}

/**
 * Génère le prochain code de lettrage pour un compte
 * Format: AA, AB, AC... AZ, BA, BB... ZZ puis AAP, ABP pour partiels
 */
function generate_letter_code($account_id, $is_partial = false) {
    // Récupérer le dernier code utilisé pour ce compte
    $last = db_fetch_assoc(db_query(
        "SELECT letter_code FROM lettering_groups
         WHERE account_id = " . db_escape($account_id) . "
           AND letter_code NOT LIKE '%P'
         ORDER BY letter_code DESC LIMIT 1"
    ));

    if (!$last || !$last['letter_code']) {
        $code = 'AA';
    } else {
        $last_code = preg_replace('/P$/', '', $last['letter_code']);
        $first = ord($last_code[0]);
        $second = ord($last_code[1]);

        $second++;
        if ($second > ord('Z')) {
            $second = ord('A');
            $first++;
        }
        if ($first > ord('Z')) {
            // Recommencer (ne devrait jamais arriver)
            $first = ord('A');
            $second = ord('A');
        }

        $code = chr($first) . chr($second);
    }

    return $is_partial ? $code . 'P' : $code;
}

// GET: Récupérer les données pour l'affichage
// ... (suite du code)
```

---

## 6. Cas de Test

### 6.1 Tests Fonctionnels

| ID | Cas | Entrée | Résultat attendu |
|----|-----|--------|------------------|
| T1 | Lettrage exact 1-1 | Facture 1000€, Règlement 1000€ | Lettrage créé, code AA |
| T2 | Lettrage avec tolérance | Facture 1000€, Règlement 999.97€ | Lettrage créé (écart 0.03 < 0.05) |
| T3 | Lettrage hors tolérance | Facture 1000€, Règlement 999.90€ | Erreur "écart supérieur à tolérance" |
| T4 | Lettrage N-1 | Règlements 300€+400€+300€, Facture 1000€ | Lettrage créé, 4 lignes |
| T5 | Lettrage partiel | Facture 1000€, Règlement partiel 600€ | Lettrage AAP, reste 400€ non lettré |
| T6 | Tiers différents | Facture Client A, Règlement Client B | Erreur "même tiers requis" |
| T7 | Compte non lettrable | Compte 512000 (banque) | Erreur "compte ne peut pas être lettré" |
| T8 | Écriture brouillon | Facture non validée | Erreur "écriture non validée" |
| T9 | Délettrage | Groupe AA existant | Groupe supprimé, audit créé |
| T10 | Délettrage période close | Groupe en période verrouillée | Erreur "période verrouillée" |

### 6.2 Tests de Suggestion Automatique

| ID | Cas | Données | Résultat attendu |
|----|-----|---------|------------------|
| S1 | Correspondance exacte | 1 facture 500€, 1 règlement 500€ | Score 80+ (40 montant + 25 tiers + 15 date) |
| S2 | Pas de correspondance | 1 facture 500€, 1 règlement 300€ | Aucune suggestion |
| S3 | Plusieurs possibilités | 1 facture 500€, 2 règlements 500€ | 2 suggestions, triées par score |
| S4 | Combinaison N-1 | 1 facture 1000€, règlements 400€+600€ | Suggestion de combinaison |

---

## 7. Sécurité et Audit

### 7.1 Contrôles d'Accès
- Rôle minimum: `accountant`
- Actions `letter` et `unletter` tracées dans `audit_log`

### 7.2 CSRF
- Token requis sur tous les formulaires POST
- Vérification via `csrf_verify()`

### 7.3 Validation des Entrées
- Tous les IDs échappés via `db_escape()`
- Montants convertis en `floatval()`
- Vérification d'appartenance des lignes au compte sélectionné

---

## 8. Points d'Attention pour l'Implémentation

### 8.1 Performance
- Index sur `lettering_items.entry_line_id` (existant)
- La vue `v_unlettered_balances` peut être lente sur gros volumes → utiliser requête directe avec LIMIT

### 8.2 Concurrence
- Pas de gestion de verrou (acceptable pour usage mono-utilisateur typique 2006)
- En cas d'accès concurrent, le premier lettrage gagne (contrainte unique sur entry_line_id)

### 8.3 Compatibilité Navigateur (2006)
- JavaScript compatible IE6+
- Pas de `addEventListener`, utiliser `onclick`
- XMLHttpRequest pour AJAX (pas fetch)
- Tables HTML pour mise en page
