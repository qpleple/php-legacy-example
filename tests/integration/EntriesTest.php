<?php
/**
 * Integration tests for accounting entries
 */

require_once dirname(__DIR__) . '/bootstrap.php';

class EntriesTest extends PHPUnit\Framework\TestCase
{
    private static $dbAvailable = false;

    public static function setUpBeforeClass(): void
    {
        self::$dbAvailable = connectTestDatabase();

        if (self::$dbAvailable) {
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

        // Mock admin session
        $_SESSION['user_id'] = 1;
        $_SESSION['username'] = 'admin';
        $_SESSION['role'] = 'admin';
    }

    protected function tearDown(): void
    {
        clearSession();
    }

    // ==================== Entry Creation Tests ====================

    public function testCreateDraftEntry()
    {
        $journal_id = 1; // VE journal
        $entry_date = '2024-01-15';
        $label = 'Test Invoice';

        // Get period for date
        $period = get_period_for_date($entry_date);
        $period_id = $period ? $period['id'] : 1;

        $sql = "INSERT INTO entries (journal_id, entry_date, period_id, label, status, total_debit, total_credit, created_by, created_at)
                VALUES ($journal_id, '$entry_date', $period_id, '" . db_escape($label) . "', 'draft', 0, 0, 1, NOW())";
        db_query($sql);
        $entry_id = db_insert_id();

        $this->assertGreaterThan(0, $entry_id);

        // Verify entry exists
        $result = db_query("SELECT * FROM entries WHERE id = $entry_id");
        $entry = db_fetch_assoc($result);

        $this->assertEquals('draft', $entry['status']);
        $this->assertEquals($label, $entry['label']);
        $this->assertNull($entry['piece_number']);
    }

    public function testAddEntryLines()
    {
        // Create entry first
        $sql = "INSERT INTO entries (journal_id, entry_date, period_id, label, status, total_debit, total_credit, created_by, created_at)
                VALUES (1, '2024-01-15', 1, 'Test Entry', 'draft', 0, 0, 1, NOW())";
        db_query($sql);
        $entry_id = db_insert_id();

        // Add debit line (411 - Customer)
        $sql = "INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
                VALUES ($entry_id, 1, 2, 'Customer payment', 120.00, 0)";
        db_query($sql);

        // Add credit line (707 - Sales)
        $sql = "INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
                VALUES ($entry_id, 2, 5, 'Sales revenue', 0, 100.00)";
        db_query($sql);

        // Add credit line (44571 - VAT)
        $sql = "INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
                VALUES ($entry_id, 3, 7, 'VAT collected', 0, 20.00)";
        db_query($sql);

        // Verify lines
        $result = db_query("SELECT SUM(debit) as total_debit, SUM(credit) as total_credit FROM entry_lines WHERE entry_id = $entry_id");
        $totals = db_fetch_assoc($result);

        $this->assertEquals(120.00, $totals['total_debit']);
        $this->assertEquals(120.00, $totals['total_credit']);
    }

    public function testValidateBalancedEntry()
    {
        $this->assertTrue(validate_double_entry(100.00, 100.00));
    }

    public function testValidateUnbalancedEntry()
    {
        $this->assertFalse(validate_double_entry(100.00, 80.00));
    }

    public function testValidateWithinTolerance()
    {
        // 0.01 tolerance should pass (use 99.995 to avoid floating point precision issues)
        $this->assertTrue(validate_double_entry(100.00, 99.995));
    }

    // ==================== Entry Posting Tests ====================

    public function testPostEntryGeneratesPieceNumber()
    {
        // Create a balanced draft entry
        $sql = "INSERT INTO entries (journal_id, entry_date, period_id, label, status, total_debit, total_credit, created_by, created_at)
                VALUES (1, '2024-01-15', 1, 'Post Test', 'draft', 100.00, 100.00, 1, NOW())";
        db_query($sql);
        $entry_id = db_insert_id();

        // Add balanced lines
        db_query("INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit) VALUES ($entry_id, 1, 2, 'Debit', 100.00, 0)");
        db_query("INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit) VALUES ($entry_id, 2, 5, 'Credit', 0, 100.00)");

        // Generate piece number
        $piece_number = generate_piece_number(1);

        // Update entry
        $sql = "UPDATE entries SET status = 'posted', piece_number = '" . db_escape($piece_number) . "', posted_at = NOW() WHERE id = $entry_id";
        db_query($sql);

        // Verify
        $result = db_query("SELECT * FROM entries WHERE id = $entry_id");
        $entry = db_fetch_assoc($result);

        $this->assertEquals('posted', $entry['status']);
        $this->assertNotNull($entry['piece_number']);
        $this->assertStringStartsWith('VE', $entry['piece_number']);
    }

    public function testPieceNumberFormat()
    {
        $piece_number = generate_piece_number(1); // VE journal

        // Format: PREFIX + YEAR + '-' + 6-digit number
        $this->assertMatchesRegularExpression('/^VE\d{4}-\d{6}$/', $piece_number);
    }

    public function testPieceNumberIncrementsSequence()
    {
        $piece1 = generate_piece_number(1);
        $piece2 = generate_piece_number(1);

        // Extract the sequence numbers
        preg_match('/(\d{6})$/', $piece1, $m1);
        preg_match('/(\d{6})$/', $piece2, $m2);

        $this->assertEquals(intval($m1[1]) + 1, intval($m2[1]));
    }

    // ==================== Period Validation Tests ====================

    public function testEntryInOpenPeriod()
    {
        $period = get_period_for_date('2024-01-15');

        $this->assertNotNull($period);
        $this->assertEquals('open', $period['status']);
    }

    public function testIsPeriodOpen()
    {
        // Period 1 should be open by default
        $this->assertTrue(is_period_open(1));
    }

    // ==================== Draft Entry Modification Tests ====================

    public function testModifyDraftEntry()
    {
        // Create draft
        $sql = "INSERT INTO entries (journal_id, entry_date, period_id, label, status, total_debit, total_credit, created_by, created_at)
                VALUES (1, '2024-01-15', 1, 'Original Label', 'draft', 0, 0, 1, NOW())";
        db_query($sql);
        $entry_id = db_insert_id();

        // Modify
        $new_label = 'Modified Label';
        $sql = "UPDATE entries SET label = '" . db_escape($new_label) . "' WHERE id = $entry_id AND status = 'draft'";
        db_query($sql);

        // Verify
        $result = db_query("SELECT label FROM entries WHERE id = $entry_id");
        $entry = db_fetch_assoc($result);

        $this->assertEquals($new_label, $entry['label']);
    }

    public function testDeleteDraftEntryLines()
    {
        // Create entry with lines
        $sql = "INSERT INTO entries (journal_id, entry_date, period_id, label, status, total_debit, total_credit, created_by, created_at)
                VALUES (1, '2024-01-15', 1, 'Delete Test', 'draft', 0, 0, 1, NOW())";
        db_query($sql);
        $entry_id = db_insert_id();

        db_query("INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit) VALUES ($entry_id, 1, 2, 'Line 1', 100, 0)");
        db_query("INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit) VALUES ($entry_id, 2, 5, 'Line 2', 0, 100)");

        // Delete all lines (legacy pattern: delete and reinsert)
        db_query("DELETE FROM entry_lines WHERE entry_id = $entry_id");

        // Verify lines deleted
        $result = db_query("SELECT COUNT(*) as cnt FROM entry_lines WHERE entry_id = $entry_id");
        $row = db_fetch_assoc($result);

        $this->assertEquals(0, $row['cnt']);
    }

    // ==================== Entry Duplication Tests ====================

    public function testDuplicateEntry()
    {
        // Create original entry
        $sql = "INSERT INTO entries (journal_id, entry_date, period_id, label, status, total_debit, total_credit, created_by, created_at)
                VALUES (1, '2024-01-15', 1, 'Original Entry', 'posted', 100.00, 100.00, 1, NOW())";
        db_query($sql);
        $original_id = db_insert_id();

        db_query("INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit) VALUES ($original_id, 1, 2, 'Debit', 100, 0)");
        db_query("INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit) VALUES ($original_id, 2, 5, 'Credit', 0, 100)");

        // Duplicate (create new draft from original)
        $result = db_query("SELECT * FROM entries WHERE id = $original_id");
        $original = db_fetch_assoc($result);

        $sql = "INSERT INTO entries (journal_id, entry_date, period_id, label, status, total_debit, total_credit, created_by, created_at)
                VALUES ({$original['journal_id']}, '{$original['entry_date']}', {$original['period_id']},
                        'Copy of " . db_escape($original['label']) . "', 'draft', {$original['total_debit']}, {$original['total_credit']}, 1, NOW())";
        db_query($sql);
        $copy_id = db_insert_id();

        // Copy lines
        $lines = db_fetch_all(db_query("SELECT * FROM entry_lines WHERE entry_id = $original_id"));
        foreach ($lines as $line) {
            $sql = "INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
                    VALUES ($copy_id, {$line['line_no']}, {$line['account_id']}, '" . db_escape($line['label']) . "', {$line['debit']}, {$line['credit']})";
            db_query($sql);
        }

        // Verify copy
        $result = db_query("SELECT * FROM entries WHERE id = $copy_id");
        $copy = db_fetch_assoc($result);

        $this->assertEquals('draft', $copy['status']);
        $this->assertNull($copy['piece_number']);
        $this->assertStringContainsString('Copy of', $copy['label']);
    }
}
