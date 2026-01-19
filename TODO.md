# PHP Legacy Accounting Application - Implementation TODO

## Overview
Implementation of a 2006-style PHP accounting application (procedural PHP, mysql_* functions, inline SQL, jQuery).

---

## Phase 1: Core Infrastructure

### Docker & Database
- [x] Create docker-compose.yml (Apache + PHP 5.x, MySQL 5.0.x)
- [x] Create Dockerfile for PHP/Apache
- [x] Create /sql/schema.sql (all tables)
- [x] Create /sql/seed.sql (initial data)

### Library Files (/www/lib/)
- [x] Create db.php (MySQL connection)
- [x] Create auth.php (authentication functions)
- [x] Create utils.php (helper functions)
- [x] Setup FPDF library in /www/lib/pdf/

### Core Layout Files (/www/)
- [x] Create header.php (includes menu, flash messages)
- [x] Create footer.php (closes HTML, includes JS)
- [x] Create login.php
- [x] Create logout.php
- [x] Create index.php (dashboard)

### Assets (/www/assets/)
- [x] Create /assets/css/style.css
- [x] Create /assets/js/jquery.js (jQuery 1.2.x)
- [x] Create /assets/js/app.js

---

## Phase 2: Setup Module (/www/modules/setup/)

- [x] company.php - Company settings
- [x] periods.php - Fiscal periods management
- [x] accounts.php - Chart of accounts CRUD
- [x] journals.php - Journals CRUD
- [x] third_parties.php - Third parties (customers/vendors) CRUD
- [x] vat.php - VAT rates CRUD

---

## Phase 3: Entries Module (/www/modules/entries/)

- [x] list.php - List accounting entries with filters
- [x] edit.php - Create/edit accounting entry (with jQuery lines)
- [x] import.php - Import CSV entries
- [x] pdf.php - Generate PDF for single entry
- [x] ajax_accounts.php - AJAX autocomplete for accounts (optional)
- [x] ajax_third_parties.php - AJAX autocomplete for third parties (optional)

---

## Phase 4: Bank Module (/www/modules/bank/)

- [x] accounts.php - Bank accounts CRUD
- [x] import.php - Import bank statement CSV
- [x] reconcile.php - Bank reconciliation (matching)

---

## Phase 5: Lettering Module (/www/modules/letters/)

- [x] select.php - Select account/third party for lettering
- [x] letter.php - Manual lettering screen

---

## Phase 6: Reports Module (/www/modules/reports/)

### HTML Reports
- [x] ledger.php - General ledger
- [x] trial_balance.php - Trial balance
- [x] journal.php - Journal report
- [x] vat_summary.php - VAT summary

### PDF Reports
- [x] pdf_ledger.php - General ledger PDF
- [x] pdf_trial_balance.php - Trial balance PDF
- [x] pdf_journal.php - Journal PDF
- [x] pdf_vat.php - VAT summary PDF

---

## Phase 7: Close Module (/www/modules/close/)

- [x] lock_period.php - Lock/unlock periods
- [x] year_end.php - Year-end closing and carry-forward entries

---

## Phase 8: Admin Module (/www/modules/admin/)

- [x] users.php - User management CRUD

---

## Phase 9: Testing & Documentation

- [ ] Test all CRUD operations
- [ ] Test double-entry validation
- [ ] Test entry posting and numbering
- [ ] Test CSV imports
- [ ] Test bank reconciliation
- [ ] Test lettering
- [ ] Test all reports
- [ ] Test PDF generation
- [ ] Test period locking
- [ ] Test year-end closing

---

## Technical Requirements Checklist

### Authentication & Security
- [x] MD5/SHA1 password hashing (legacy style)
- [x] CSRF token on all forms
- [x] Role-based access (admin, accountant, viewer)
- [x] mysql_real_escape_string for input sanitization

### Accounting Rules
- [x] Double-entry validation (debit = credit)
- [x] Draft/Posted status management
- [x] Sequential piece numbering on validation
- [x] Period-based entry restrictions
- [x] VAT calculation on lines

### Data Management
- [x] Audit logging for all major actions
- [x] Pagination on list pages
- [x] Flash messages for user feedback

---

## File Structure

```
/www/
├── index.php
├── login.php
├── logout.php
├── header.php
├── footer.php
├── modules/
│   ├── setup/
│   │   ├── company.php
│   │   ├── periods.php
│   │   ├── accounts.php
│   │   ├── journals.php
│   │   ├── third_parties.php
│   │   └── vat.php
│   ├── entries/
│   │   ├── list.php
│   │   ├── edit.php
│   │   ├── import.php
│   │   ├── pdf.php
│   │   ├── ajax_accounts.php
│   │   └── ajax_third_parties.php
│   ├── bank/
│   │   ├── accounts.php
│   │   ├── import.php
│   │   └── reconcile.php
│   ├── letters/
│   │   ├── select.php
│   │   └── letter.php
│   ├── reports/
│   │   ├── ledger.php
│   │   ├── trial_balance.php
│   │   ├── journal.php
│   │   ├── vat_summary.php
│   │   ├── pdf_ledger.php
│   │   ├── pdf_trial_balance.php
│   │   ├── pdf_journal.php
│   │   └── pdf_vat.php
│   ├── close/
│   │   ├── lock_period.php
│   │   └── year_end.php
│   └── admin/
│       └── users.php
├── assets/
│   ├── css/
│   │   └── style.css
│   └── js/
│       ├── jquery.js
│       └── app.js
├── uploads/
├── pdf/
└── lib/
    ├── db.php
    ├── auth.php
    ├── utils.php
    └── pdf/
        └── fpdf.php
/sql/
├── 01_schema.sql
└── 02_seed.sql
docker-compose.yml
Dockerfile
```

---

## Progress Tracking

| Phase | Status | Completion |
|-------|--------|------------|
| Phase 1: Core Infrastructure | Complete | 100% |
| Phase 2: Setup Module | Complete | 100% |
| Phase 3: Entries Module | Complete | 100% |
| Phase 4: Bank Module | Complete | 100% |
| Phase 5: Lettering Module | Complete | 100% |
| Phase 6: Reports Module | Complete | 100% |
| Phase 7: Close Module | Complete | 100% |
| Phase 8: Admin Module | Complete | 100% |
| Phase 9: Testing | Not Started | 0% |

---

## Notes

- All PHP code is procedural (no classes except FPDF)
- Uses mysqli_* functions (compatible with PHP 5.6+)
- SQL inline in pages, no prepared statements
- jQuery 1.2.x compatible implementation for client-side interactions
- FPDF for PDF generation
- Sessions for authentication state
- Flash messages via $_SESSION['flash']

## Running the Application

1. Start Docker containers:
   ```bash
   docker-compose up -d
   ```

2. Access the application at http://localhost:8080

3. Default login:
   - Username: admin
   - Password: admin123

## Features Implemented

- User authentication with role-based access (admin, accountant, viewer)
- Company settings management
- Fiscal periods management with lock/unlock
- Chart of accounts (Plan Comptable) CRUD
- Accounting journals management
- Third parties (customers/vendors) management
- VAT rates management
- Double-entry accounting entries with draft/posted workflow
- CSV import for accounting entries
- Bank accounts and statement import
- Bank reconciliation (matching)
- Manual lettering for customer/vendor accounts
- Reports: General Ledger, Trial Balance, Journal, VAT Summary
- PDF export for all reports
- Period locking and year-end closing with carry-forward entries
- User management (admin only)
- Audit logging for all major actions
