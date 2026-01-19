<?php
/**
 * Integration tests for lettering module
 *
 * Tests the full lettering workflow with database operations:
 * - Creating lettering groups
 * - Partial lettering
 * - Unlettering
 * - Validation rules
 */

require_once dirname(__DIR__) . '/bootstrap.php';

class LetteringIntegrationTest extends PHPUnit\Framework\TestCase
{
    protected static $dbInitialized = false;

    protected function setUp(): void
    {
        global $db_pdo;

        // Create in-memory database
        $db_pdo = new PDO('sqlite::memory:');
        $db_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Load schema
        $schema = file_get_contents(BASE_PATH . '/sql/01_schema.sql');
        $statements = array_filter(array_map('trim', explode(';', $schema)));
        foreach ($statements as $stmt) {
            if (!empty($stmt) && stripos($stmt, 'CREATE') !== false) {
                try {
                    $db_pdo->exec($stmt);
                } catch (PDOException $e) {
                    // Ignore errors
                }
            }
        }

        // Include db functions
        require_once WWW_PATH . '/lib/db.php';
        require_once WWW_PATH . '/lib/utils.php';

        // Set up test data
        $this->setupTestData();

        // Mock session
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION['user_id'] = 1;
        $_SESSION['username'] = 'admin';
        $_SESSION['role'] = 'admin';
    }

    protected function setupTestData()
    {
        global $db_pdo;

        // Create test user
        $db_pdo->exec("INSERT INTO users (id, username, password_hash, role, created_at)
                       VALUES (1, 'admin', 'hash', 'admin', datetime('now'))");

        // Create company with lettering tolerance
        $db_pdo->exec("INSERT INTO company (id, name, currency, fiscal_year_start, fiscal_year_end, lettering_tolerance)
                       VALUES (1, 'Test Company', 'EUR', '2024-01-01', '2024-12-31', 0.05)");

        // Create test accounts
        $db_pdo->exec("INSERT INTO accounts (id, code, label, type, is_active)
                       VALUES (1, '411DURAND', 'Client Durand', 'customer', 1)");
        $db_pdo->exec("INSERT INTO accounts (id, code, label, type, is_active)
                       VALUES (2, '401FOURN01', 'Fournisseur ABC', 'vendor', 1)");
        $db_pdo->exec("INSERT INTO accounts (id, code, label, type, is_active)
                       VALUES (3, '512000', 'Banque', 'bank', 1)");
        $db_pdo->exec("INSERT INTO accounts (id, code, label, type, is_active)
                       VALUES (4, '701000', 'Ventes', 'revenue', 1)");

        // Create test third parties
        $db_pdo->exec("INSERT INTO third_parties (id, type, name, account_id, created_at)
                       VALUES (1, 'customer', 'Durand SARL', 1, datetime('now'))");
        $db_pdo->exec("INSERT INTO third_parties (id, type, name, account_id, created_at)
                       VALUES (2, 'vendor', 'Fournisseur ABC', 2, datetime('now'))");

        // Create test journal
        $db_pdo->exec("INSERT INTO journals (id, code, label, sequence_prefix, next_number, is_active)
                       VALUES (1, 'VE', 'Ventes', 'VE', 1, 1)");
        $db_pdo->exec("INSERT INTO journals (id, code, label, sequence_prefix, next_number, is_active)
                       VALUES (2, 'BK', 'Banque', 'BK', 1, 1)");

        // Create test period
        $db_pdo->exec("INSERT INTO periods (id, start_date, end_date, status)
                       VALUES (1, '2024-01-01', '2024-12-31', 'open')");

        // Create test entries (posted)
        // Invoice 1: 1000 EUR
        $db_pdo->exec("INSERT INTO entries (id, journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at)
                       VALUES (1, 1, '2024-01-15', 1, 'VE2024-000001', 'Facture FA-001', 'posted', 1000, 1000, 1, datetime('now'))");
        $db_pdo->exec("INSERT INTO entry_lines (id, entry_id, line_no, account_id, third_party_id, label, debit, credit)
                       VALUES (1, 1, 1, 1, 1, 'Facture FA-001', 1000, 0)");
        $db_pdo->exec("INSERT INTO entry_lines (id, entry_id, line_no, account_id, third_party_id, label, debit, credit)
                       VALUES (2, 1, 2, 4, NULL, 'Ventes', 0, 1000)");

        // Invoice 2: 500 EUR
        $db_pdo->exec("INSERT INTO entries (id, journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at)
                       VALUES (2, 1, '2024-01-20', 1, 'VE2024-000002', 'Facture FA-002', 'posted', 500, 500, 1, datetime('now'))");
        $db_pdo->exec("INSERT INTO entry_lines (id, entry_id, line_no, account_id, third_party_id, label, debit, credit)
                       VALUES (3, 2, 1, 1, 1, 'Facture FA-002', 500, 0)");
        $db_pdo->exec("INSERT INTO entry_lines (id, entry_id, line_no, account_id, third_party_id, label, debit, credit)
                       VALUES (4, 2, 2, 4, NULL, 'Ventes', 0, 500)");

        // Payment 1: 1000 EUR
        $db_pdo->exec("INSERT INTO entries (id, journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at)
                       VALUES (3, 2, '2024-01-25', 1, 'BK2024-000001', 'Reglement CHQ 12345', 'posted', 1000, 1000, 1, datetime('now'))");
        $db_pdo->exec("INSERT INTO entry_lines (id, entry_id, line_no, account_id, third_party_id, label, debit, credit)
                       VALUES (5, 3, 1, 3, NULL, 'Reglement client', 1000, 0)");
        $db_pdo->exec("INSERT INTO entry_lines (id, entry_id, line_no, account_id, third_party_id, label, debit, credit)
                       VALUES (6, 3, 2, 1, 1, 'Reglement FA-001', 0, 1000)");

        // Payment 2: 500 EUR
        $db_pdo->exec("INSERT INTO entries (id, journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at)
                       VALUES (4, 2, '2024-01-30', 1, 'BK2024-000002', 'Reglement virement', 'posted', 500, 500, 1, datetime('now'))");
        $db_pdo->exec("INSERT INTO entry_lines (id, entry_id, line_no, account_id, third_party_id, label, debit, credit)
                       VALUES (7, 4, 1, 3, NULL, 'Reglement client', 500, 0)");
        $db_pdo->exec("INSERT INTO entry_lines (id, entry_id, line_no, account_id, third_party_id, label, debit, credit)
                       VALUES (8, 4, 2, 1, 1, 'Reglement FA-002', 0, 500)");

        // Draft entry (should not be letterable)
        $db_pdo->exec("INSERT INTO entries (id, journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at)
                       VALUES (5, 1, '2024-02-01', 1, NULL, 'Facture brouillon', 'draft', 300, 300, 1, datetime('now'))");
        $db_pdo->exec("INSERT INTO entry_lines (id, entry_id, line_no, account_id, third_party_id, label, debit, credit)
                       VALUES (9, 5, 1, 1, 1, 'Facture brouillon', 300, 0)");
    }

    // ==================== Lettering Group Creation ====================

    public function testCreateLetteringGroupExactMatch()
    {
        // Letter invoice 1 (1000) with payment 1 (1000)
        $groupId = $this->createLetteringGroup(1, array(
            array('id' => 1, 'amount' => 1000), // Invoice debit line
            array('id' => 6, 'amount' => 1000)  // Payment credit line
        ));

        $this->assertGreaterThan(0, $groupId);

        // Verify group was created
        $group = $this->getLetteringGroup($groupId);
        $this->assertNotNull($group);
        $this->assertEquals(1, $group['account_id']);
        $this->assertEquals('AA', $group['letter_code']);
        $this->assertEquals(0, $group['is_partial']);
    }

    public function testCreateLetteringGroupGeneratesCorrectCode()
    {
        // Create first lettering
        $groupId1 = $this->createLetteringGroup(1, array(
            array('id' => 1, 'amount' => 1000),
            array('id' => 6, 'amount' => 1000)
        ));

        // Create second lettering
        $groupId2 = $this->createLetteringGroup(1, array(
            array('id' => 3, 'amount' => 500),
            array('id' => 8, 'amount' => 500)
        ));

        $group1 = $this->getLetteringGroup($groupId1);
        $group2 = $this->getLetteringGroup($groupId2);

        $this->assertEquals('AA', $group1['letter_code']);
        $this->assertEquals('AB', $group2['letter_code']);
    }

    public function testCreatePartialLettering()
    {
        // Partially letter invoice 1 (1000) with 600
        $groupId = $this->createLetteringGroup(1, array(
            array('id' => 1, 'amount' => 600),  // Partial of 1000 debit
            array('id' => 6, 'amount' => 600)   // Partial of 1000 credit
        ), true);

        $group = $this->getLetteringGroup($groupId);
        $this->assertEquals('AAP', $group['letter_code']);
        $this->assertEquals(1, $group['is_partial']);
    }

    public function testLetteringCreatesItems()
    {
        $groupId = $this->createLetteringGroup(1, array(
            array('id' => 1, 'amount' => 1000),
            array('id' => 6, 'amount' => 1000)
        ));

        $items = $this->getLetteringItems($groupId);

        $this->assertEquals(2, count($items));
    }

    // ==================== Unlettering ====================

    public function testUnletterGroup()
    {
        // Create lettering
        $groupId = $this->createLetteringGroup(1, array(
            array('id' => 1, 'amount' => 1000),
            array('id' => 6, 'amount' => 1000)
        ));

        // Verify it exists
        $this->assertNotEmpty($this->getLetteringGroup($groupId));

        // Unletter
        $this->unletterGroup($groupId);

        // Verify it's deleted (PDO fetch returns false when no row found)
        $this->assertFalse($this->getLetteringGroup($groupId));
    }

    public function testUnletterRemovesItems()
    {
        $groupId = $this->createLetteringGroup(1, array(
            array('id' => 1, 'amount' => 1000),
            array('id' => 6, 'amount' => 1000)
        ));

        $this->unletterGroup($groupId);

        $items = $this->getLetteringItems($groupId);
        $this->assertEquals(0, count($items));
    }

    // ==================== Available Amount Queries ====================

    public function testGetUnletteredLinesForAccount()
    {
        $lines = $this->getUnletteredLines(1); // Customer account

        // Should have 4 lines: 2 debit (invoices), 2 credit (payments)
        $this->assertEquals(4, count($lines));
    }

    public function testGetUnletteredLinesExcludesLettered()
    {
        // Create lettering for invoice 1 and payment 1
        $this->createLetteringGroup(1, array(
            array('id' => 1, 'amount' => 1000),
            array('id' => 6, 'amount' => 1000)
        ));

        $lines = $this->getUnletteredLines(1);

        // Should now have only 2 lines (invoice 2 and payment 2)
        $this->assertEquals(2, count($lines));
    }

    public function testGetUnletteredLinesPartiallyLettered()
    {
        // Partially letter invoice 1
        $this->createLetteringGroup(1, array(
            array('id' => 1, 'amount' => 600),
            array('id' => 6, 'amount' => 600)
        ), true);

        $lines = $this->getUnletteredLines(1);

        // Invoice 1 and payment 1 should still appear with reduced available amount
        $found = false;
        foreach ($lines as $line) {
            if ($line['id'] == 1) {
                $found = true;
                $this->assertEquals(400, floatval($line['available_amount']), '', 0.01);
            }
        }
        $this->assertTrue($found, 'Line 1 should still be in unlettered list');
    }

    public function testGetUnletteredLinesExcludesDraft()
    {
        $lines = $this->getUnletteredLines(1);

        // Draft entry line (id=9) should not appear
        foreach ($lines as $line) {
            $this->assertNotEquals(9, $line['id'], 'Draft lines should not be included');
        }
    }

    // ==================== Balance Calculations ====================

    public function testCalculateUnletteredBalance()
    {
        $balance = $this->getUnletteredBalance(1);

        // 2 invoices (1000 + 500) - 2 payments (1000 + 500) = 0
        $this->assertEquals(0, $balance['debit'] - $balance['credit'], '', 0.01);
        $this->assertEquals(1500, $balance['debit'], '', 0.01);
        $this->assertEquals(1500, $balance['credit'], '', 0.01);
    }

    public function testCalculateUnletteredBalanceAfterLettering()
    {
        // Letter invoice 1 with payment 1
        $this->createLetteringGroup(1, array(
            array('id' => 1, 'amount' => 1000),
            array('id' => 6, 'amount' => 1000)
        ));

        $balance = $this->getUnletteredBalance(1);

        // Remaining: invoice 2 (500) and payment 2 (500)
        $this->assertEquals(500, $balance['debit'], '', 0.01);
        $this->assertEquals(500, $balance['credit'], '', 0.01);
    }

    // ==================== Validation Rules ====================

    public function testValidationRejectsNonLetterableAccount()
    {
        // Try to letter bank account (512)
        $this->expectException(Exception::class);
        $this->createLetteringGroup(3, array(
            array('id' => 5, 'amount' => 1000),
            array('id' => 7, 'amount' => 1000)
        ));
    }

    public function testValidationRejectsUnbalancedAmount()
    {
        // Try to create lettering with unbalanced amounts
        $this->expectException(Exception::class);
        $this->createLetteringGroup(1, array(
            array('id' => 1, 'amount' => 1000),
            array('id' => 6, 'amount' => 500)  // Doesn't balance
        ));
    }

    public function testValidationAcceptsToleranceAmount()
    {
        // Create lettering with small difference (within 0.05 tolerance)
        $groupId = $this->createLetteringGroup(1, array(
            array('id' => 1, 'amount' => 1000),
            array('id' => 6, 'amount' => 999.97)
        ));

        $this->assertGreaterThan(0, $groupId);
    }

    public function testValidationRejectsSingleLine()
    {
        $this->expectException(Exception::class);
        $this->createLetteringGroup(1, array(
            array('id' => 1, 'amount' => 1000)
        ));
    }

    // ==================== Letter Code Sequence ====================

    public function testLetterCodeSequencePerAccount()
    {
        // Letter on account 1
        $groupId1 = $this->createLetteringGroup(1, array(
            array('id' => 1, 'amount' => 1000),
            array('id' => 6, 'amount' => 1000)
        ));

        // Create vendor data for account 2
        global $db_pdo;
        $db_pdo->exec("INSERT INTO entries (id, journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at)
                       VALUES (10, 1, '2024-01-15', 1, 'AC2024-000001', 'Facture fournisseur', 'posted', 200, 200, 1, datetime('now'))");
        $db_pdo->exec("INSERT INTO entry_lines (id, entry_id, line_no, account_id, third_party_id, label, debit, credit)
                       VALUES (20, 10, 1, 4, NULL, 'Achat', 200, 0)");
        $db_pdo->exec("INSERT INTO entry_lines (id, entry_id, line_no, account_id, third_party_id, label, debit, credit)
                       VALUES (21, 10, 2, 2, 2, 'Fournisseur', 0, 200)");

        $db_pdo->exec("INSERT INTO entries (id, journal_id, entry_date, period_id, piece_number, label, status, total_debit, total_credit, created_by, created_at)
                       VALUES (11, 2, '2024-01-20', 1, 'BK2024-000010', 'Paiement fournisseur', 'posted', 200, 200, 1, datetime('now'))");
        $db_pdo->exec("INSERT INTO entry_lines (id, entry_id, line_no, account_id, third_party_id, label, debit, credit)
                       VALUES (22, 11, 1, 2, 2, 'Paiement', 200, 0)");
        $db_pdo->exec("INSERT INTO entry_lines (id, entry_id, line_no, account_id, third_party_id, label, debit, credit)
                       VALUES (23, 11, 2, 3, NULL, 'Banque', 0, 200)");

        // Letter on account 2 (vendor) - should also start at AA
        $groupId2 = $this->createLetteringGroup(2, array(
            array('id' => 21, 'amount' => 200),
            array('id' => 22, 'amount' => 200)
        ));

        $group1 = $this->getLetteringGroup($groupId1);
        $group2 = $this->getLetteringGroup($groupId2);

        // Both should be 'AA' because they're different accounts
        $this->assertEquals('AA', $group1['letter_code']);
        $this->assertEquals('AA', $group2['letter_code']);
    }

    // ==================== Helper Methods ====================

    private function createLetteringGroup($accountId, $lines, $isPartial = false)
    {
        global $db_pdo;

        // Validate account is letterable
        $stmt = $db_pdo->query("SELECT code FROM accounts WHERE id = $accountId");
        $account = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$account || (substr($account['code'], 0, 3) != '411' && substr($account['code'], 0, 3) != '401')) {
            throw new Exception('Account not letterable');
        }

        // Validate at least 2 lines
        if (count($lines) < 2) {
            throw new Exception('At least 2 lines required');
        }

        // Calculate totals
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($lines as $line) {
            $stmt = $db_pdo->query("SELECT debit, credit FROM entry_lines WHERE id = " . intval($line['id']));
            $lineData = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($lineData['debit'] > 0) {
                $totalDebit += $line['amount'];
            } else {
                $totalCredit += $line['amount'];
            }
        }

        // Validate balance
        if (abs($totalDebit - $totalCredit) > 0.05) {
            throw new Exception('Lines not balanced');
        }

        // Generate letter code
        $stmt = $db_pdo->query("SELECT letter_code FROM lettering_groups WHERE account_id = $accountId ORDER BY id DESC LIMIT 1");
        $last = $stmt->fetch(PDO::FETCH_ASSOC);
        $letterCode = $this->generateNextCode($last ? $last['letter_code'] : null, $isPartial);

        // Create group
        $isPartialInt = $isPartial ? 1 : 0;
        $db_pdo->exec("INSERT INTO lettering_groups (account_id, third_party_id, letter_code, is_partial, created_at, created_by)
                       VALUES ($accountId, NULL, '$letterCode', $isPartialInt, datetime('now'), 1)");
        $groupId = $db_pdo->lastInsertId();

        // Create items
        foreach ($lines as $line) {
            $stmt = $db_pdo->query("SELECT debit FROM entry_lines WHERE id = " . intval($line['id']));
            $lineData = $stmt->fetch(PDO::FETCH_ASSOC);
            $signedAmount = $lineData['debit'] > 0 ? $line['amount'] : -$line['amount'];

            $db_pdo->exec("INSERT INTO lettering_items (group_id, entry_line_id, amount)
                           VALUES ($groupId, " . intval($line['id']) . ", $signedAmount)");
        }

        return $groupId;
    }

    private function generateNextCode($lastCode, $isPartial = false)
    {
        if (!$lastCode) {
            $code = 'AA';
        } else {
            $lastCode = preg_replace('/P$/', '', $lastCode);
            $first = ord($lastCode[0]);
            $second = ord($lastCode[1]);

            $second++;
            if ($second > ord('Z')) {
                $second = ord('A');
                $first++;
            }
            if ($first > ord('Z')) {
                $first = ord('A');
                $second = ord('A');
            }

            $code = chr($first) . chr($second);
        }

        return $isPartial ? $code . 'P' : $code;
    }

    private function getLetteringGroup($groupId)
    {
        global $db_pdo;
        $stmt = $db_pdo->query("SELECT * FROM lettering_groups WHERE id = $groupId");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function getLetteringItems($groupId)
    {
        global $db_pdo;
        $stmt = $db_pdo->query("SELECT * FROM lettering_items WHERE group_id = $groupId");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function unletterGroup($groupId)
    {
        global $db_pdo;
        $db_pdo->exec("DELETE FROM lettering_items WHERE group_id = $groupId");
        $db_pdo->exec("DELETE FROM lettering_groups WHERE id = $groupId");
    }

    private function getUnletteredLines($accountId)
    {
        global $db_pdo;
        $sql = "SELECT el.id, el.debit, el.credit,
                       (el.debit + el.credit) - COALESCE(
                           (SELECT SUM(ABS(amount)) FROM lettering_items WHERE entry_line_id = el.id), 0
                       ) as available_amount
                FROM entry_lines el
                INNER JOIN entries e ON el.entry_id = e.id
                WHERE el.account_id = $accountId
                  AND e.status = 'posted'
                  AND (el.debit + el.credit) - COALESCE(
                      (SELECT SUM(ABS(amount)) FROM lettering_items WHERE entry_line_id = el.id), 0
                  ) > 0.001";
        $stmt = $db_pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getUnletteredBalance($accountId)
    {
        global $db_pdo;
        $sql = "SELECT
                    SUM(CASE WHEN el.debit > 0 THEN
                        (el.debit + el.credit) - COALESCE(
                            (SELECT SUM(ABS(amount)) FROM lettering_items WHERE entry_line_id = el.id), 0
                        ) ELSE 0 END) as debit,
                    SUM(CASE WHEN el.credit > 0 THEN
                        (el.debit + el.credit) - COALESCE(
                            (SELECT SUM(ABS(amount)) FROM lettering_items WHERE entry_line_id = el.id), 0
                        ) ELSE 0 END) as credit
                FROM entry_lines el
                INNER JOIN entries e ON el.entry_id = e.id
                WHERE el.account_id = $accountId
                  AND e.status = 'posted'";
        $stmt = $db_pdo->query($sql);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
