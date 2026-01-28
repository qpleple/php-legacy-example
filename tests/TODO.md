# Test Suite - Simplified

## Summary

Tests for the simplified accounting application (no periods, no drafts, no PDF, no bank, no lettering).

| Suite | Test File | Description |
|-------|-----------|-------------|
| Unit | AuthTest.php | Authentication functions |
| Unit | UtilsTest.php | Utility functions |
| Functional | LoginTest.php | Login/logout flow |
| Functional | EntriesTest.php | Entry list and view |
| Functional | ReportsTest.php | Journal, ledger, trial balance |
| Functional | SetupTest.php | Company, accounts, journals |
| Functional | AdminTest.php | User management |

## Test Coverage by Module

### entries/
- list.php - list, filters
- edit.php - new entry form, view posted entry

### reports/
- journal.php - report, filters, totals
- ledger.php - report, filters, running balance
- trial_balance.php - report, soldes, balance status

### setup/
- company.php - settings, form
- accounts.php - CRUD, search
- journals.php - CRUD

### admin/
- users.php - CRUD, roles

## Run Tests

```bash
# All tests
bash tests/run_all_tests.sh

# Functional tests only
cd tests && ./vendor/bin/phpunit --testsuite Functional

# Unit tests only
cd tests && ./vendor/bin/phpunit --testsuite Unit
```
