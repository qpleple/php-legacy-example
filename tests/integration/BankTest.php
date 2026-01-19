<?php
/**
 * Integration tests for bank module
 * Requires database connection
 */

require_once dirname(__DIR__) . '/bootstrap.php';

class BankTest extends PHPUnit\Framework\TestCase
{
    private static $dbAvailable = false;

    public static function setUpBeforeClass(): void
    {
        self::$dbAvailable = connectTestDatabase();

        if (self::$dbAvailable) {
            if (getTestDatabaseType() === 'mysql') {
                require_once WWW_PATH . '/lib/db.php';
            }
            require_once WWW_PATH . '/lib/auth.php';
            require_once WWW_PATH . '/lib/utils.php';
            resetTestDatabase();
        }
    }

    protected function setUp(): void
    {
        if (!self::$dbAvailable) {
            $this->markTestSkipped('Database not available');
        }

        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'admin';
    }

    protected function tearDown(): void
    {
        clearSession();
    }

    // ==================== Bank Account Tests ====================

    public function testCreateBankAccount()
    {
        // Get bank account (512xxx)
        $result = db_query("SELECT id FROM accounts WHERE code LIKE '512%' LIMIT 1");
        $account = db_fetch_assoc($result);
        $account_id = $account['id'];

        $sql = "INSERT INTO bank_accounts (label, account_id, is_active)
                VALUES ('Test Bank Account', $account_id, 1)";
        db_query($sql);
        $id = db_insert_id();

        $this->assertGreaterThan(0, $id);

        $result = db_query("SELECT * FROM bank_accounts WHERE id = $id");
        $bank = db_fetch_assoc($result);

        $this->assertEquals('Test Bank Account', $bank['label']);
        $this->assertEquals($account_id, $bank['account_id']);
    }

    // ==================== Bank Statement Import Tests ====================

    public function testImportBankStatement()
    {
        // First ensure we have a bank account
        $result = db_query("SELECT id FROM bank_accounts LIMIT 1");
        if (db_num_rows($result) == 0) {
            $result = db_query("SELECT id FROM accounts WHERE code LIKE '512%' LIMIT 1");
            $account = db_fetch_assoc($result);
            db_query("INSERT INTO bank_accounts (label, account_id, is_active) VALUES ('Main Bank', {$account['id']}, 1)");
        }
        $result = db_query("SELECT id FROM bank_accounts LIMIT 1");
        $bank = db_fetch_assoc($result);
        $bank_account_id = $bank['id'];

        // Create bank statement
        $sql = "INSERT INTO bank_statements (bank_account_id, imported_at, source_filename)
                VALUES ($bank_account_id, NOW(), 'test_statement.csv')";
        db_query($sql);
        $statement_id = db_insert_id();

        $this->assertGreaterThan(0, $statement_id);
    }

    public function testImportBankStatementLines()
    {
        // Create bank account and statement
        $result = db_query("SELECT id FROM accounts WHERE code LIKE '512%' LIMIT 1");
        $account = db_fetch_assoc($result);

        db_query("INSERT INTO bank_accounts (label, account_id, is_active) VALUES ('Test Bank 2', {$account['id']}, 1)");
        $bank_account_id = db_insert_id();

        db_query("INSERT INTO bank_statements (bank_account_id, imported_at, source_filename) VALUES ($bank_account_id, NOW(), 'test.csv')");
        $statement_id = db_insert_id();

        // Add statement lines
        $lines = [
            ['date' => '2024-01-15', 'label' => 'Client Payment', 'amount' => 1200.00, 'ref' => 'PAY001'],
            ['date' => '2024-01-16', 'label' => 'Supplier Payment', 'amount' => -500.00, 'ref' => 'PAY002'],
            ['date' => '2024-01-17', 'label' => 'Bank Fee', 'amount' => -25.50, 'ref' => 'FEE001'],
        ];

        foreach ($lines as $line) {
            $sql = "INSERT INTO bank_statement_lines (statement_id, line_date, label, amount, ref, status)
                    VALUES ($statement_id, '{$line['date']}', '" . db_escape($line['label']) . "', {$line['amount']}, '{$line['ref']}', 'unmatched')";
            db_query($sql);
        }

        // Verify
        $result = db_query("SELECT COUNT(*) as cnt FROM bank_statement_lines WHERE statement_id = $statement_id");
        $row = db_fetch_assoc($result);

        $this->assertEquals(3, $row['cnt']);
    }

    public function testBankStatementLineAmountParsing()
    {
        // Test that amounts with commas are parsed correctly
        $amount_str = '1 234,56';
        $parsed = parse_number($amount_str);

        $this->assertEquals(1234.56, $parsed);
    }

    // ==================== Bank Reconciliation Tests ====================

    public function testMatchBankLineToEntry()
    {
        // Create bank account and statement
        $result = db_query("SELECT id FROM accounts WHERE code LIKE '512%' LIMIT 1");
        $account = db_fetch_assoc($result);
        $account_id = $account['id'];

        db_query("INSERT INTO bank_accounts (label, account_id, is_active) VALUES ('Reconcile Bank', $account_id, 1)");
        $bank_account_id = db_insert_id();

        db_query("INSERT INTO bank_statements (bank_account_id, imported_at, source_filename) VALUES ($bank_account_id, NOW(), 'reconcile.csv')");
        $statement_id = db_insert_id();

        // Add statement line
        db_query("INSERT INTO bank_statement_lines (statement_id, line_date, label, amount, ref, status)
                  VALUES ($statement_id, '2024-01-15', 'Payment', 1000.00, 'REF001', 'unmatched')");
        $statement_line_id = db_insert_id();

        // Create a posted entry with bank line
        db_query("INSERT INTO entries (journal_id, entry_date, period_id, label, status, total_debit, total_credit, created_by, created_at)
                  VALUES (3, '2024-01-15', 1, 'Bank Entry', 'posted', 1000, 1000, 1, NOW())");
        $entry_id = db_insert_id();

        db_query("INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
                  VALUES ($entry_id, 1, $account_id, 'Bank debit', 1000.00, 0)");
        $entry_line_id = db_insert_id();

        // Match the bank line to entry line
        $sql = "UPDATE bank_statement_lines
                SET matched_entry_line_id = $entry_line_id, status = 'matched'
                WHERE id = $statement_line_id";
        db_query($sql);

        // Verify
        $result = db_query("SELECT status, matched_entry_line_id FROM bank_statement_lines WHERE id = $statement_line_id");
        $line = db_fetch_assoc($result);

        $this->assertEquals('matched', $line['status']);
        $this->assertEquals($entry_line_id, $line['matched_entry_line_id']);
    }

    public function testUnmatchedLinesQuery()
    {
        // Get unmatched lines for a statement
        $result = db_query("SELECT id FROM bank_statements LIMIT 1");
        if (db_num_rows($result) > 0) {
            $statement = db_fetch_assoc($result);
            $statement_id = $statement['id'];

            $sql = "SELECT * FROM bank_statement_lines WHERE statement_id = $statement_id AND status = 'unmatched'";
            $result = db_query($sql);
            $lines = db_fetch_all($result);

            // Verify query returns an array
            $this->assertIsArray($lines);

            foreach ($lines as $line) {
                $this->assertEquals('unmatched', $line['status']);
            }
        } else {
            $this->markTestSkipped('No bank statements available');
        }
    }
}
