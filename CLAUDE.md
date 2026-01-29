# CLAUDE.md - AI Assistant Guide

This file provides context for Claude (AI assistant) when working with this codebase.

## Project Overview

**Ketchup Compta** is a PHP legacy accounting application intentionally built with 2006-era patterns. It implements French double-entry bookkeeping (comptabilité en partie double) with features like journal management, VAT handling, bank reconciliation, and financial reports.

## Quick Reference

### Running the Application

```bash
# Start
docker-compose up -d

# Stop
docker-compose down

# View logs
docker-compose logs -f

# Reset database
docker-compose down -v && docker-compose up -d
```

### Default Login
- URL: http://localhost:8080
- Username: `admin`
- Password: `admin123`

### Database Access
```bash
# Access SQLite database
docker-compose exec web sqlite3 /var/www/html/data/compta.db
```

## Architecture Patterns (Legacy by Design)

This codebase deliberately uses outdated patterns. When making changes, **maintain consistency** with existing patterns:

### PHP Patterns
- **Procedural PHP** - No classes (except FPDF library and SQLiteResult)
- **No framework** - Pure PHP with includes
- **Inline SQL** - Queries written directly in page files
- **Global DB connection** - `$db_pdo` variable from `lib/db.php`
- **PDO with SQLite** - Simple file-based database

### Page Structure Pattern
Every page follows this structure:
```php
<?php
require_once '../lib/db.php';
require_once '../lib/auth.php';
require_once '../lib/utils.php';

require_login();
require_role('accountant');

// POST handling at top
if (is_post()) {
    csrf_verify();
    // Process form...
    set_flash('success', 'Action completed');
    redirect('same_page.php');
}

// GET data fetching
$data = db_fetch_all("SELECT * FROM table WHERE ...");

require_once '../header.php';
?>
<!-- HTML output -->
<?php require_once '../footer.php'; ?>
```

### Input Handling
```php
// Escaping for SQL (legacy pattern - not prepared statements)
$value = db_escape($_POST['field']);

// HTML output escaping
echo h($variable);

// POST/GET helpers
$id = get('id');
$name = post('name');
```

### Database Operations
```php
// Query execution
$result = db_query("SELECT * FROM accounts WHERE id = " . db_escape($id));

// Fetch single row
$row = db_fetch_assoc($result);

// Fetch all rows
$rows = db_fetch_all("SELECT * FROM accounts ORDER BY code");

// Insert/Update
db_query("INSERT INTO accounts (code, label) VALUES ('" . db_escape($code) . "', '" . db_escape($label) . "')");
```

## Key Files to Understand

| File | Purpose | Lines |
|------|---------|-------|
| `www/lib/db.php` | Database connection and query functions (SQLite) | ~180 |
| `www/lib/auth.php` | Authentication, authorization, CSRF, audit | ~220 |
| `www/lib/utils.php` | Helpers (flash, formatting, escaping) | ~100 |
| `www/modules/entries/edit.php` | Most complex file - entry creation | ~850 |
| `www/header.php` | Navigation menu and layout start | ~105 |
| `sql/01_schema.sql` | All table definitions (SQLite) | ~190 |

## Database Schema Quick Reference

### Core Tables
- `users` - id, username, password_hash, role (admin/accountant/viewer)
- `company` - Single record with company settings
- `accounts` - Chart of accounts (code unique)
- `journals` - VE (Sales), AC (Purchases), BK (Bank), OD (Misc)

### Transaction Tables
- `entries` - Accounting entry headers (always posted)
- `entry_lines` - Individual debit/credit lines

### Auxiliary
- `audit_log` - Action tracking

## Business Rules

### Double-Entry Accounting
- Every entry must balance: `SUM(debit) = SUM(credit)` (tolerance: 0.01)
- A line has either debit OR credit, not both

### Entry Workflow
- Entries are posted directly upon creation (no draft state)
- Posted entries are immutable and have a piece number
- Continuous accounting (no period restrictions)

### Piece Numbering
Generated on validation: `{prefix}{YYYY}-{number:06d}`
Example: `VE2024-000001`

## Common Tasks

### Adding a New Page
1. Create file in appropriate `www/modules/{module}/` directory
2. Include standard libs and auth at top
3. Add to navigation in `www/header.php`
4. Follow existing page patterns

### Adding a Form Field
1. Add column to table in `sql/01_schema.sql`
2. Update INSERT/UPDATE queries in the page
3. Add HTML input in the form section
4. Handle in POST processing

### Adding a Report
1. Copy existing report file (e.g., `ledger.php`)
2. Modify SQL query for new data
3. Update HTML table columns
4. Create matching `pdf_*.php` for PDF export

### Creating PDF Export
```php
require_once '../../lib/pdf/fpdf.php';

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'Report Title', 0, 1, 'C');
// Add content...
$pdf->Output('I', 'filename.pdf');
```

## Testing Changes

1. Start containers: `docker-compose up -d`
2. Access app at http://localhost:8080
3. Login as admin/admin123
4. Test functionality manually
5. Check `audit_log` table for action tracking

## File Locations

| Content | Location |
|---------|----------|
| Web root | `www/` |
| PHP libraries | `www/lib/` |
| Application modules | `www/modules/` |
| CSS styles | `www/assets/css/style.css` |
| JavaScript | `www/assets/js/app.js` |
| User uploads | `www/uploads/` |
| Generated PDFs | `www/pdf/` |
| Database schema | `sql/01_schema.sql` |
| Seed data | `sql/02_seed.sql` |
| SQLite database | `www/data/compta.db` (in container) |

## Code Conventions

### Naming
- Tables: snake_case plural (`entry_lines`)
- Columns: snake_case (`created_at`)
- PHP variables: snake_case (`$entry_id`)
- Functions: snake_case (`db_query()`, `get_flash()`)

### Comments
- French comments are common in business logic
- English acceptable for technical documentation

### Security Notes
- CSRF tokens required on all forms: `<?php echo csrf_field(); ?>`
- Verify with `csrf_verify()` in POST handler
- Escape all output with `h()` function
- Escape SQL values with `db_escape()`

## Specifications Reference

See `SPECS.md` for complete functional specifications (in French), including:
- Detailed screen specifications
- Business rules for accounting
- Data validation rules
- Import/export formats
