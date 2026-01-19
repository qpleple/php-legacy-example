# Legacy Traps

Hidden behaviors for modernization training. These simulate real-world legacy code surprises.

---

## Trap 1: auto_prepend_file (Input Sanitization)

**Files:** `www/.htaccess`, `www/lib/bootstrap.php`

**What it does:**
- Runs before every PHP script via `.htaccess` directive
- Trims all POST values (breaks passwords with intentional spaces)
- Converts French decimals: `1 234,56` → `1234.56` for fields containing `amount`, `debit`, `credit`, `total`, `price`, `rate`
- Blocks any request with `?debug` parameter
- Logs all POST requests to `/tmp/compta_posts.log`

### Test

```bash
# 1. Check htaccess
grep auto_prepend www/.htaccess

# 2. Test decimal conversion - POST an amount with comma
curl -X POST http://localhost:8080/test.php -d "amount=1234,56"
# $_POST['amount'] will be "1234.56" not "1234,56"

# 3. Test debug block
curl http://localhost:8080/index.php?debug=1
# Returns 403 Forbidden

# 4. Check silent logging
docker-compose exec web cat /tmp/compta_posts.log
```

### Fell into trap

Developer reads `edit.php`, sees:
```php
$amount = post('amount');  // Expects raw user input
```
But `$_POST['amount']` was already transformed by bootstrap.php. They add validation for French format that never triggers.

### Saw the trap

Developer notices `.htaccess` has `auto_prepend_file`, reads `bootstrap.php`, understands all input is pre-processed.

---

## Trap 2: Database Triggers

**File:** `sql/03_triggers.sql`

**What they do:**
1. **Amount limit**: Rejects entry_lines with debit/credit > 999999.99
2. **Delete protection**: Blocks deletion of posted entries
3. **Period lock cascade**: Auto-posts all draft entries when period is locked
4. **Silent audit**: Logs account deletions to audit_log

### Test

```bash
# Load triggers
docker-compose exec web sqlite3 /var/www/html/data/compta.db < /var/www/html/../sql/03_triggers.sql

# 1. Test amount limit
docker-compose exec web sqlite3 /var/www/html/data/compta.db \
  "INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit) VALUES (1, 1, 1, 'test', 9999999.99);"
# Error: Montant trop eleve

# 2. Test delete protection (need a posted entry first)
docker-compose exec web sqlite3 /var/www/html/data/compta.db \
  "DELETE FROM entries WHERE status = 'posted' LIMIT 1;"
# Error: Impossible de supprimer une ecriture validee

# 3. Test cascade lock
docker-compose exec web sqlite3 /var/www/html/data/compta.db \
  "UPDATE periods SET status = 'locked' WHERE id = 1;"
# Check: all drafts in period 1 are now 'posted'
```

### Fell into trap

Developer adds PHP code to delete entries:
```php
db_query("DELETE FROM entries WHERE id = $id");
```
Works in testing (drafts). Fails in production (posted entries). Nothing in PHP explains why.

Or: Developer adds max amount validation in PHP (500000), not knowing DB allows up to 999999.99.

Or: Developer locks a period, doesn't understand why drafts became posted.

### Saw the trap

Developer runs `.schema` or checks `03_triggers.sql`, sees triggers, understands hidden business logic lives in the database.

---

## Trap 3: Output Buffer Transformation

**Files:** `www/header.php:14`, `www/footer.php:10`, `www/lib/utils.php:11-29`

**What it does:**
- `ob_start('_compta_transform_output')` in header.php captures all output
- Formats numbers 4+ digits with French thousands separator: `12345.67` → `12 345,67`
- Replaces SQL keywords with `[KEYWORD]` (breaks debug output)

### Test

```php
// Create www/test_output.php
<?php
require_once 'header.php';
echo "<p>Amount: 12345.67</p>";
echo "<p>Query: SELECT * FROM users</p>";
require_once 'footer.php';
```

```bash
curl http://localhost:8080/test_output.php
# Output shows:
#   Amount: 12 345,67      (formatted!)
#   Query: [SELECT] * FROM users   (filtered!)
```

### Fell into trap

Developer sees `12 345,67 €` in browser. Looks at PHP: `echo $row['amount'];` which outputs `12345.67`. Spends hours looking for where formatting happens.

Developer adds SQL debug display, text keeps showing `[SELECT] [FROM]`. Can't figure out why.

Test checking HTML output fails because test hits the function directly but browser goes through buffer.

### Saw the trap

Developer notices `ob_start` in header.php, finds `_compta_transform_output` function, understands all HTML is post-processed.

---

## Summary Table

| Trap | Location | Trigger | Symptom |
|------|----------|---------|---------|
| auto_prepend | .htaccess → bootstrap.php | Every request | Input values differ from what user typed |
| DB triggers | 03_triggers.sql | INSERT/UPDATE/DELETE | Operations fail or data changes unexpectedly |
| Output buffer | header.php → utils.php | Every page render | Display differs from PHP output |

## Finding the Traps

```bash
# Find auto_prepend
grep -r "auto_prepend" www/

# Find triggers
sqlite3 data/compta.db ".schema" | grep -i trigger

# Find output buffers
grep -r "ob_start" www/
```
