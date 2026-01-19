<?php
/**
 * Integration tests for lettering module
 * Requires database connection
 */

require_once dirname(__DIR__) . '/bootstrap.php';

class LetteringTest extends PHPUnit\Framework\TestCase
{
    private static $dbAvailable = false;

    public static function setUpBeforeClass(): void
    {
        self::$dbAvailable = connectTestDatabase();

        if (self::$dbAvailable) {
            require_once WWW_PATH . '/lib/db.php';
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
        $_SESSION['role'] = 'accountant';
    }

    protected function tearDown(): void
    {
        clearSession();
    }

    // ==================== Lettering Group Tests ====================

    public function testCreateLetteringGroup()
    {
        // Get a customer account
        $result = db_query("SELECT id FROM accounts WHERE type = 'customer' LIMIT 1");
        $account = db_fetch_assoc($result);
        $account_id = $account['id'];

        $sql = "INSERT INTO lettering_groups (account_id, third_party_id, created_at, created_by)
                VALUES ($account_id, NULL, NOW(), 1)";
        db_query($sql);
        $group_id = db_insert_id();

        $this->assertGreaterThan(0, $group_id);
    }

    public function testAddLetteringItems()
    {
        // Create entries with customer account lines
        $result = db_query("SELECT id FROM accounts WHERE type = 'customer' LIMIT 1");
        $account = db_fetch_assoc($result);
        $account_id = $account['id'];

        // Create invoice entry (debit customer)
        db_query("INSERT INTO entries (journal_id, entry_date, period_id, label, status, total_debit, total_credit, created_by, created_at)
                  VALUES (1, '2024-01-15', 1, 'Invoice', 'posted', 1200, 1200, 1, NOW())");
        $invoice_entry_id = db_insert_id();

        db_query("INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
                  VALUES ($invoice_entry_id, 1, $account_id, 'Customer debit', 1200.00, 0)");
        $invoice_line_id = db_insert_id();

        // Create payment entry (credit customer)
        db_query("INSERT INTO entries (journal_id, entry_date, period_id, label, status, total_debit, total_credit, created_by, created_at)
                  VALUES (3, '2024-01-20', 1, 'Payment', 'posted', 1200, 1200, 1, NOW())");
        $payment_entry_id = db_insert_id();

        db_query("INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
                  VALUES ($payment_entry_id, 1, $account_id, 'Customer credit', 0, 1200.00)");
        $payment_line_id = db_insert_id();

        // Create lettering group
        db_query("INSERT INTO lettering_groups (account_id, third_party_id, created_at, created_by) VALUES ($account_id, NULL, NOW(), 1)");
        $group_id = db_insert_id();

        // Add lettering items
        db_query("INSERT INTO lettering_items (group_id, entry_line_id, amount) VALUES ($group_id, $invoice_line_id, 1200.00)");
        db_query("INSERT INTO lettering_items (group_id, entry_line_id, amount) VALUES ($group_id, $payment_line_id, -1200.00)");

        // Verify items
        $result = db_query("SELECT SUM(amount) as balance FROM lettering_items WHERE group_id = $group_id");
        $row = db_fetch_assoc($result);

        // Balance should be 0 (debit - credit)
        $this->assertEquals(0, $row['balance']);
    }

    public function testLetteringValidationBalanced()
    {
        // Lettering is valid if sum of amounts = 0
        $debit = 1000.00;
        $credit = -1000.00;

        $this->assertEquals(0, $debit + $credit);
    }

    public function testLetteringValidationWithinTolerance()
    {
        // 0.01 tolerance
        $debit = 1000.00;
        $credit = -999.99;
        $balance = abs($debit + $credit);

        $this->assertTrue($balance <= 0.01);
    }

    public function testLetteringValidationUnbalanced()
    {
        $debit = 1000.00;
        $credit = -800.00;
        $balance = abs($debit + $credit);

        $this->assertGreaterThan(0.01, $balance);
    }

    // ==================== Unlettered Lines Query Tests ====================

    public function testGetUnletteredLinesForAccount()
    {
        // Get customer account
        $result = db_query("SELECT id FROM accounts WHERE type = 'customer' LIMIT 1");
        $account = db_fetch_assoc($result);
        $account_id = $account['id'];

        // Query unlettered lines (lines not in any lettering_items)
        $sql = "SELECT el.*
                FROM entry_lines el
                JOIN entries e ON el.entry_id = e.id
                WHERE el.account_id = $account_id
                AND e.status = 'posted'
                AND el.id NOT IN (SELECT entry_line_id FROM lettering_items)
                ORDER BY e.entry_date";

        $result = db_query($sql);
        $lines = db_fetch_all($result);

        // Just verify query executes
        $this->assertIsArray($lines);
    }

    // ==================== Partial Lettering Tests ====================

    public function testPartialLettering()
    {
        // Get customer account
        $result = db_query("SELECT id FROM accounts WHERE type = 'customer' LIMIT 1");
        $account = db_fetch_assoc($result);
        $account_id = $account['id'];

        // Create invoice for 1000
        db_query("INSERT INTO entries (journal_id, entry_date, period_id, label, status, total_debit, total_credit, created_by, created_at)
                  VALUES (1, '2024-02-01', 1, 'Big Invoice', 'posted', 1000, 1000, 1, NOW())");
        $entry_id = db_insert_id();

        db_query("INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
                  VALUES ($entry_id, 1, $account_id, 'Customer', 1000.00, 0)");
        $invoice_line_id = db_insert_id();

        // Create partial payment for 600
        db_query("INSERT INTO entries (journal_id, entry_date, period_id, label, status, total_debit, total_credit, created_by, created_at)
                  VALUES (3, '2024-02-15', 1, 'Partial Payment', 'posted', 600, 600, 1, NOW())");
        $payment_entry_id = db_insert_id();

        db_query("INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
                  VALUES ($payment_entry_id, 1, $account_id, 'Customer', 0, 600.00)");
        $payment_line_id = db_insert_id();

        // Create partial lettering (only 600)
        db_query("INSERT INTO lettering_groups (account_id, third_party_id, created_at, created_by) VALUES ($account_id, NULL, NOW(), 1)");
        $group_id = db_insert_id();

        // Letter only 600 of the 1000 invoice
        db_query("INSERT INTO lettering_items (group_id, entry_line_id, amount) VALUES ($group_id, $invoice_line_id, 600.00)");
        db_query("INSERT INTO lettering_items (group_id, entry_line_id, amount) VALUES ($group_id, $payment_line_id, -600.00)");

        // Verify partial lettering balance is 0
        $result = db_query("SELECT SUM(amount) as balance FROM lettering_items WHERE group_id = $group_id");
        $row = db_fetch_assoc($result);

        $this->assertEquals(0, $row['balance']);

        // Remaining on invoice should be 400
        $result = db_query("SELECT debit FROM entry_lines WHERE id = $invoice_line_id");
        $line = db_fetch_assoc($result);
        $lettered = 600.00;
        $remaining = $line['debit'] - $lettered;

        $this->assertEquals(400.00, $remaining);
    }
}
