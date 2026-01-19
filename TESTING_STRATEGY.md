# Testing Strategy for PHP Legacy Accounting Application

## Overview

This document outlines a comprehensive testing strategy for the legacy PHP accounting application. Given the 2006-style architecture (procedural PHP, inline SQL, no framework), the testing approach must adapt to these constraints while ensuring full coverage of the functional specifications.

---

## 1. Testing Layers

### 1.1 Layer Summary

| Layer | Purpose | Tools | Coverage Target |
|-------|---------|-------|-----------------|
| **Unit Tests** | Test individual functions in `/lib/` | PHPUnit | 80% of utility functions |
| **Integration Tests** | Test database operations and module logic | PHPUnit + MySQL | All CRUD operations |
| **Functional Tests** | Test HTTP endpoints and form submissions | PHPUnit + curl/Guzzle | All pages/routes |
| **End-to-End Tests** | Test complete user workflows | Selenium/Playwright | Critical business flows |
| **Acceptance Tests** | Validate against spec criteria (Section 13) | Manual + Automated | 100% of acceptance criteria |

---

## 2. Unit Tests

### 2.1 Target: `/lib/` Functions

#### 2.1.1 `db.php` Functions
```
Test Cases:
- db_connect(): Successful connection with valid credentials
- db_connect(): Error handling with invalid credentials
- db_query(): Execute valid SELECT query
- db_query(): Handle SQL syntax errors
- db_fetch_assoc(): Return associative array for valid result
- db_fetch_assoc(): Return false/null at end of results
- db_escape(): Escape special characters (', ", \, NULL bytes)
- db_escape(): Handle empty strings and NULL values
```

#### 2.1.2 `auth.php` Functions
```
Test Cases:
- auth_login(): Successful login with valid credentials
- auth_login(): Reject invalid password
- auth_login(): Reject non-existent user
- auth_login(): Set session variables correctly (user_id, role, csrf_token)
- auth_is_logged_in(): Return true when session active
- auth_is_logged_in(): Return false when no session
- auth_has_role(): Admin has access to all roles
- auth_has_role(): Accountant has access to accountant and viewer
- auth_has_role(): Viewer only has viewer access
- require_login(): Redirect when not logged in
- require_role(): Redirect when insufficient permissions
- csrf_token_generate(): Generate unique tokens
- csrf_verify(): Accept valid token
- csrf_verify(): Reject invalid/missing token
- audit_log(): Insert correct data into audit_log table
```

#### 2.1.3 `utils.php` Functions
```
Test Cases:
- format_money(): Format 1234.56 as "1 234,56" (French format)
- format_money(): Handle negative amounts
- format_money(): Handle zero
- parse_date(): Parse DD/MM/YYYY format
- parse_date(): Parse YYYY-MM-DD format
- parse_date(): Reject invalid dates
- paginate(): Calculate correct offset/limit
- paginate(): Handle edge cases (page 0, negative page)
- get_journals(): Return active journals
- get_accounts(): Return accounts filtered by type
- get_periods(): Return periods with correct status
- validate_double_entry(): Accept balanced entries (debit = credit)
- validate_double_entry(): Reject unbalanced entries
- validate_double_entry(): Accept within 0.01 tolerance
- handle_upload(): Accept valid file types (PDF, JPG, PNG, GIF)
- handle_upload(): Reject PHP files
- handle_upload(): Enforce 5MB size limit
- h(): Escape HTML special characters
```

### 2.2 Unit Test Implementation

```php
// tests/unit/UtilsTest.php
class UtilsTest extends PHPUnit\Framework\TestCase
{
    public function testFormatMoneyPositive()
    {
        $this->assertEquals('1 234,56', format_money(1234.56));
    }

    public function testValidateDoubleEntryBalanced()
    {
        $lines = [
            ['debit' => 100.00, 'credit' => 0],
            ['debit' => 0, 'credit' => 100.00]
        ];
        $this->assertTrue(validate_double_entry($lines));
    }

    public function testValidateDoubleEntryTolerance()
    {
        $lines = [
            ['debit' => 100.00, 'credit' => 0],
            ['debit' => 0, 'credit' => 99.99]
        ];
        $this->assertTrue(validate_double_entry($lines)); // Within 0.01 tolerance
    }
}
```

---

## 3. Integration Tests

### 3.1 Database CRUD Operations

#### 3.1.1 Users Module
```
Test Cases:
- Create user with valid data
- Create user with duplicate username (should fail)
- Read user by ID
- Update user role
- Update user password
- Delete user (not self)
- Prevent self-deletion
```

#### 3.1.2 Company Module
```
Test Cases:
- Read company settings (always ID=1)
- Update company name
- Update fiscal year dates
- Verify currency format
```

#### 3.1.3 Periods Module
```
Test Cases:
- Generate monthly periods from fiscal year
- Lock period
- Unlock period
- Verify period covers full fiscal year
```

#### 3.1.4 Accounts Module
```
Test Cases:
- Create account with unique code
- Create account with duplicate code (should fail)
- Update account label
- Deactivate account
- Search accounts by code
- Search accounts by label (LIKE)
- Filter accounts by type (general/customer/vendor)
```

#### 3.1.5 Journals Module
```
Test Cases:
- Create journal with code
- Update journal sequence prefix
- Increment next_number correctly
- Get active journals only
```

#### 3.1.6 Third Parties Module
```
Test Cases:
- Create customer third party
- Create vendor third party
- Auto-create associated account (411xxx for customer, 401xxx for vendor)
- Link third party to existing account
- Update third party email
```

#### 3.1.7 VAT Rates Module
```
Test Cases:
- Create VAT rate with collected/deductible accounts
- Calculate VAT amount (base * rate / 100)
- Activate/deactivate VAT rate
```

#### 3.1.8 Entries Module
```
Test Cases:
- Create draft entry
- Add entry lines
- Delete entry lines (cascade delete pattern)
- Calculate total debit/credit
- Save entry with period assignment
- Post entry (draft → posted)
- Generate piece_number on posting
- Prevent modification of posted entry
- Prevent deletion of posted entry
- Duplicate entry (create new draft)
```

#### 3.1.9 Bank Module
```
Test Cases:
- Create bank account with 512xxx account link
- Import bank statement CSV
- Create bank statement lines
- Match statement line to entry line
- Update matched status
- Calculate reconciliation difference
```

#### 3.1.10 Lettering Module
```
Test Cases:
- Create lettering group
- Add lettering items (entry lines)
- Validate balanced lettering (sum = 0 within 0.01)
- Reject unbalanced lettering
- Query unlettered lines for account
```

#### 3.1.11 Audit Log
```
Test Cases:
- Log login action
- Log entry creation
- Log entry posting
- Log period lock
- Verify user_id, action, entity, entity_id stored correctly
```

---

## 4. Functional Tests (HTTP/Form Tests)

### 4.1 Authentication Endpoints

```
Test Cases:
- GET /login.php: Display login form
- POST /login.php with valid credentials: Redirect to index.php
- POST /login.php with invalid credentials: Show error message
- POST /login.php without CSRF token: Reject request
- GET /logout.php: Destroy session and redirect to login
- Access protected page without session: Redirect to login
```

### 4.2 Setup Module Endpoints

```
/modules/setup/company.php:
- GET: Display company form (admin only)
- POST: Update company settings
- Verify role restriction

/modules/setup/periods.php:
- GET: List periods
- POST (generate): Create monthly periods
- POST (lock): Lock specific period
- POST (unlock): Unlock specific period

/modules/setup/accounts.php:
- GET: List accounts with search/filter
- POST (create): Add new account
- POST (update): Modify existing account
- POST (delete): Remove account (if no entries)

/modules/setup/journals.php:
- GET: List journals
- POST (create/update): Manage journals

/modules/setup/third_parties.php:
- GET: List third parties
- POST (create): Add with auto-account creation
- POST (update): Modify third party

/modules/setup/vat.php:
- GET: List VAT rates
- POST (create/update): Manage VAT rates
```

### 4.3 Entries Module Endpoints

```
/modules/entries/list.php:
- GET: Display paginated entry list
- GET with filters: Filter by journal, period, status, search text
- Verify pagination links

/modules/entries/edit.php:
- GET (new): Display empty entry form
- GET (existing): Display entry with lines
- GET (posted): Display read-only view
- POST (save draft): Create/update draft entry
- POST (validate): Post entry with piece_number
- POST with unbalanced entry: Show error
- POST to locked period: Reject
- File upload: Accept valid attachment
- File upload: Reject PHP files

/modules/entries/import.php:
- GET: Display import form
- POST with valid CSV: Create entries
- POST with invalid CSV: Show error report
- Verify piece_ref grouping

/modules/entries/pdf.php:
- GET with entry_id: Generate PDF
- Verify PDF headers (Content-Type, Content-Disposition)
```

### 4.4 Bank Module Endpoints

```
/modules/bank/accounts.php:
- GET: List bank accounts
- POST: Create/update bank account

/modules/bank/import.php:
- GET: Display import form with bank account selector
- POST with CSV: Create statement and lines
- Verify date parsing (DD/MM/YYYY)
- Verify amount parsing (comma → point)

/modules/bank/reconcile.php:
- GET: Display unmatched lines and available entries
- POST (match): Link statement line to entry line
- POST (unmatch): Remove link
- Verify matched status update
```

### 4.5 Lettering Module Endpoints

```
/modules/letters/select.php:
- GET: Display third party/account selector
- Redirect to letter.php with selection

/modules/letters/letter.php:
- GET: Display entry lines for selected account
- POST (letter): Create lettering group with balanced items
- POST with unbalanced selection: Reject with error
- Display remaining balance
```

### 4.6 Reports Module Endpoints

```
/modules/reports/ledger.php:
- GET: Display general ledger with filters
- Verify progressive balance calculation
- POST/GET for PDF export

/modules/reports/trial_balance.php:
- GET: Display trial balance by account
- Verify debit/credit totals match

/modules/reports/journal.php:
- GET: Display entries grouped by journal
- Filter by period

/modules/reports/vat_summary.php:
- GET: Display VAT summary by rate
- Verify collected/deductible totals

PDF exports (pdf_*.php):
- Verify PDF generation
- Verify content matches HTML report
```

### 4.7 Closure Module Endpoints

```
/modules/close/lock_period.php:
- GET: Display period list with lock status
- POST (lock): Lock period (no draft entries)
- POST (lock) with drafts: Reject
- POST (unlock): Unlock period (admin only)

/modules/close/year_end.php:
- GET: Display year-end status
- POST (generate): Create carry-forward entry
- Precondition check: All periods locked
- Precondition check: No draft entries
- Verify carry-forward entry is balanced
- Verify entry dated first day of new fiscal year
```

### 4.8 Admin Module Endpoints

```
/modules/admin/users.php:
- GET: List users (admin only)
- POST (create): Add new user
- POST (update): Modify user role/password
- POST (delete): Remove user (not self)
- Verify role restriction
```

---

## 5. End-to-End (E2E) Tests

### 5.1 Scenario: Sales Invoice Workflow (Spec 11.1)

```
Steps:
1. Login as accountant
2. Navigate to Entries → New
3. Select journal "VE" (Ventes)
4. Add lines:
   - Account 411xxx (Customer) - Debit TTC
   - Account 707 (Sales) - Credit HT
   - Account 44571 (VAT Collected) - Credit VAT
5. Save as draft
6. Verify totals balance
7. Post entry
8. Verify piece_number generated (VE2024-000001)
9. Verify entry is read-only
10. Generate PDF and verify content

Expected Results:
- Entry saved with correct amounts
- Piece number follows format {prefix}{year}-{sequence}
- Posted entry cannot be modified
- PDF contains all entry details
```

### 5.2 Scenario: Purchase Invoice Workflow (Spec 11.2)

```
Steps:
1. Login as accountant
2. Create entry in journal "AC" (Achats)
3. Add lines:
   - Account 606 (Purchases) - Debit HT
   - Account 44566 (Deductible VAT) - Debit VAT
   - Account 401xxx (Vendor) - Credit TTC
4. Post entry
5. Create payment entry:
   - Account 401xxx - Debit
   - Account 512xxx - Credit
6. Navigate to Lettering
7. Select vendor account
8. Letter invoice and payment lines

Expected Results:
- Both entries posted with correct piece numbers
- Lettering group created
- Lettered amounts sum to zero
```

### 5.3 Scenario: Bank Reconciliation Workflow (Spec 11.3)

```
Steps:
1. Login as accountant
2. Navigate to Bank → Import
3. Upload CSV with bank transactions
4. Navigate to Reconcile
5. Match bank line to posted entry line
6. Verify matched status changes
7. Verify reconciliation balance

Expected Results:
- Bank statement created with lines
- Matching links statement to entry
- Status updated to "matched"
```

### 5.4 Scenario: Period Closure Workflow (Spec 11.4)

```
Steps:
1. Login as admin
2. Post all draft entries in period
3. Navigate to Close → Lock Period
4. Lock the period
5. Attempt to create entry in locked period
6. Lock all periods in fiscal year
7. Navigate to Year End
8. Generate carry-forward entries
9. Verify carry-forward entry in new fiscal year

Expected Results:
- Period lock prevents new entries
- Year-end creates balanced OD entry
- Carry-forward entry dated first day of new year
- Report à nouveau account balances the entry
```

### 5.5 Scenario: CSV Entry Import (Spec 7.3.4)

```
Steps:
1. Prepare CSV with multiple entries (grouped by piece_ref)
2. Navigate to Entries → Import
3. Upload CSV
4. Review import report
5. Verify entries created as drafts
6. Verify piece_ref grouping

Test Cases:
- Valid CSV: All entries created
- Invalid account code: Error in report
- Unbalanced entry: Error flagged
- Invalid date format: Error flagged
- Missing required fields: Error flagged
```

---

## 6. Acceptance Tests (Spec Section 13)

### 6.1 Acceptance Criteria Checklist

| # | Criterion | Test Type | Priority |
|---|-----------|-----------|----------|
| AC1 | Admin can configure company, chart of accounts, journals, VAT, periods | E2E | High |
| AC2 | Accountant can create draft entry, add lines, save, attach file | E2E | High |
| AC3 | Entry cannot be posted if unbalanced or period locked | Integration | High |
| AC4 | Posted entry is numbered and non-modifiable | Integration | High |
| AC5 | CSV entry import creates correct entries with error report | Functional | Medium |
| AC6 | Bank statement import creates statement and lines | Functional | Medium |
| AC7 | Bank reconciliation matches statement line to entry line | E2E | Medium |
| AC8 | Lettering creates balanced lettering group | E2E | Medium |
| AC9 | Reports show correct totals from posted entries | Functional | High |
| AC10 | PDF export works for entry, journal, ledger, balance, VAT | Functional | Medium |
| AC11 | Period lock prevents new entries in period | Integration | High |
| AC12 | Year-end closure generates balanced carry-forward entry | E2E | High |

---

## 7. Test Data Requirements

### 7.1 Base Test Data (from seed.sql)

```
Users:
- admin / admin123 (role: admin)
- comptable / compta123 (role: accountant)
- lecteur / lecture123 (role: viewer)

Journals:
- VE (Ventes)
- AC (Achats)
- BK (Banque)
- OD (Opérations Diverses)

Accounts:
- 401000 (Vendors - general)
- 411000 (Customers - general)
- 512000 (Bank)
- 606000 (Purchases)
- 707000 (Sales)
- 44566 (Deductible VAT)
- 44571 (Collected VAT)
- 110000 (Report à nouveau)

VAT Rates:
- TVA20 (20%)
- TVA10 (10%)

Periods:
- Monthly periods for current fiscal year (open)
```

### 7.2 Test-Specific Data

```
For Integration Tests:
- Test accounts: TEST001, TEST002
- Test third parties: TESTCLIENT, TESTVENDOR
- Test entries with known amounts

For E2E Tests:
- Complete sales cycle data
- Complete purchase cycle data
- Bank statement CSV samples
- Entry import CSV samples
```

---

## 8. Test Environment

### 8.1 Docker Test Environment

```yaml
# docker-compose.test.yml
version: '3'
services:
  web-test:
    build: .
    ports:
      - "8081:80"
    volumes:
      - ./www:/var/www/html
      - ./tests:/var/www/tests
    environment:
      - DB_HOST=db-test
      - DB_NAME=accounting_test
      - PHP_ENV=test
    depends_on:
      - db-test

  db-test:
    image: mysql:5.0
    environment:
      - MYSQL_ROOT_PASSWORD=test
      - MYSQL_DATABASE=accounting_test
    tmpfs:
      - /var/lib/mysql  # Use tmpfs for faster tests

  selenium:
    image: selenium/standalone-chrome
    ports:
      - "4444:4444"
```

### 8.2 Test Database Reset

```php
// tests/bootstrap.php
function resetTestDatabase() {
    $sql = file_get_contents('/sql/01_schema.sql');
    db_query($sql);
    $sql = file_get_contents('/sql/02_seed.sql');
    db_query($sql);
}

// Run before each test class
class TestCase extends PHPUnit\Framework\TestCase {
    public static function setUpBeforeClass(): void {
        resetTestDatabase();
    }
}
```

---

## 9. Test Organization

### 9.1 Directory Structure

```
/tests/
├── bootstrap.php           # Test setup and database initialization
├── phpunit.xml            # PHPUnit configuration
├── unit/
│   ├── AuthTest.php
│   ├── UtilsTest.php
│   └── DbTest.php
├── integration/
│   ├── UsersTest.php
│   ├── AccountsTest.php
│   ├── EntriesTest.php
│   ├── BankTest.php
│   ├── LetteringTest.php
│   └── ClosureTest.php
├── functional/
│   ├── LoginTest.php
│   ├── SetupModuleTest.php
│   ├── EntriesModuleTest.php
│   ├── BankModuleTest.php
│   ├── ReportsModuleTest.php
│   └── CloseModuleTest.php
├── e2e/
│   ├── SalesWorkflowTest.php
│   ├── PurchaseWorkflowTest.php
│   ├── BankReconciliationTest.php
│   └── YearEndClosureTest.php
├── acceptance/
│   └── AcceptanceCriteriaTest.php
├── fixtures/
│   ├── entries_import.csv
│   ├── bank_statement.csv
│   └── chart_of_accounts.csv
└── helpers/
    ├── HttpClient.php
    └── SeleniumHelper.php
```

### 9.2 PHPUnit Configuration

```xml
<!-- phpunit.xml -->
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="bootstrap.php" colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>integration</directory>
        </testsuite>
        <testsuite name="Functional">
            <directory>functional</directory>
        </testsuite>
        <testsuite name="E2E">
            <directory>e2e</directory>
        </testsuite>
        <testsuite name="Acceptance">
            <directory>acceptance</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

---

## 10. Security Tests

### 10.1 SQL Injection Tests

```
Test Cases:
- Login with SQL injection in username: admin'--
- Login with SQL injection in password: ' OR '1'='1
- Account search with injection: %' OR '1'='1'--%
- Entry label with injection attempt
- Verify mysql_real_escape_string escapes all inputs
```

### 10.2 XSS Tests

```
Test Cases:
- Entry label with <script> tag
- Account label with HTML injection
- Third party name with event handlers
- Verify htmlspecialchars on all outputs
```

### 10.3 CSRF Tests

```
Test Cases:
- POST without CSRF token: Reject
- POST with invalid CSRF token: Reject
- POST with expired token (if implemented): Reject
- Verify token regeneration after successful POST
```

### 10.4 Authorization Tests

```
Test Cases:
- Viewer accessing admin pages: Redirect
- Accountant accessing admin/users.php: Redirect
- Direct URL access to protected resources
- Session hijacking protection
```

### 10.5 File Upload Tests

```
Test Cases:
- Upload .php file: Reject
- Upload file > 5MB: Reject
- Upload with manipulated MIME type
- Upload to path traversal: ../../../etc/passwd
- Verify stored filename sanitization
```

---

## 11. Performance Tests

### 11.1 Load Tests

```
Scenarios:
- 100 concurrent users viewing entry list
- 50 concurrent entry creations
- Report generation with 10,000 entries
- Large CSV import (1,000 entries)
```

### 11.2 Pagination Tests

```
Test Cases:
- Entry list with 10,000 entries: Response < 2s
- Ledger report with 5,000 lines: Response < 3s
- Account search with 500 results: Response < 1s
```

---

## 12. Regression Test Suite

### 12.1 Core Regression Tests

These tests should run on every code change:

1. Login/logout functionality
2. Entry creation and posting
3. Double-entry validation
4. Period lock enforcement
5. PDF generation
6. Report calculations

### 12.2 Full Regression Tests

Run before releases:

1. All unit tests
2. All integration tests
3. All functional tests
4. Complete E2E workflows
5. Security test suite

---

## 13. Continuous Integration

### 13.1 CI Pipeline

```yaml
# .github/workflows/test.yml
name: Test Suite

on: [push, pull_request]

jobs:
  unit-tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Run Unit Tests
        run: docker-compose -f docker-compose.test.yml run web-test phpunit --testsuite Unit

  integration-tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Run Integration Tests
        run: docker-compose -f docker-compose.test.yml run web-test phpunit --testsuite Integration

  functional-tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Run Functional Tests
        run: docker-compose -f docker-compose.test.yml run web-test phpunit --testsuite Functional

  e2e-tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Run E2E Tests
        run: docker-compose -f docker-compose.test.yml up -d && sleep 10 && phpunit --testsuite E2E
```

---

## 14. Test Priorities

### Priority 1 (Critical - Must Pass)
- Double-entry validation (debit = credit)
- Entry posting with piece numbering
- Period lock enforcement
- User authentication
- CSRF protection

### Priority 2 (High - Should Pass)
- All CRUD operations
- CSV imports
- Bank reconciliation
- Lettering
- Year-end closure

### Priority 3 (Medium - Nice to Have)
- PDF generation quality
- UI/UX workflows
- Edge cases
- Performance under load

---

## 15. Test Reporting

### 15.1 Test Report Format

```
Test Suite: Integration Tests
Date: 2024-XX-XX
Total: 150 tests
Passed: 148
Failed: 2
Skipped: 0

Failed Tests:
1. EntriesTest::testPostToLockedPeriod - Expected redirect, got 200
2. BankTest::testImportInvalidDate - Date parsing failed

Coverage:
- Statements: 75%
- Branches: 68%
- Functions: 82%
```

### 15.2 Coverage Requirements

| Module | Target Coverage |
|--------|-----------------|
| lib/auth.php | 90% |
| lib/utils.php | 85% |
| lib/db.php | 80% |
| modules/entries/ | 80% |
| modules/close/ | 85% |

---

## 16. Implementation Recommendations

### Phase 1: Foundation
1. Set up test infrastructure (Docker, PHPUnit)
2. Implement unit tests for `/lib/` functions
3. Create test data fixtures

### Phase 2: Core Tests
1. Integration tests for entries and validation
2. Functional tests for authentication
3. Integration tests for period management

### Phase 3: Module Tests
1. Bank module tests
2. Lettering module tests
3. Reports module tests

### Phase 4: E2E and Acceptance
1. Complete workflow E2E tests
2. Acceptance criteria validation
3. Security test suite

### Phase 5: Optimization
1. Performance tests
2. CI/CD pipeline
3. Coverage reporting
