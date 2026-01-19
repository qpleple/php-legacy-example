<?php
/**
 * Integration tests for chart of accounts
 * Requires database connection
 */

require_once dirname(__DIR__) . '/bootstrap.php';

class AccountsTest extends PHPUnit\Framework\TestCase
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
    }

    // ==================== Account CRUD Tests ====================

    public function testCreateAccount()
    {
        $code = 'TEST001';
        $label = 'Test Account';
        $type = 'general';

        $sql = "INSERT INTO accounts (code, label, type, is_active)
                VALUES ('" . db_escape($code) . "', '" . db_escape($label) . "', '$type', 1)";
        db_query($sql);
        $id = db_insert_id();

        $this->assertGreaterThan(0, $id);

        // Verify
        $result = db_query("SELECT * FROM accounts WHERE id = $id");
        $account = db_fetch_assoc($result);

        $this->assertEquals($code, $account['code']);
        $this->assertEquals($label, $account['label']);
        $this->assertEquals($type, $account['type']);
    }

    public function testCreateAccountWithDuplicateCode()
    {
        // Try to create account with existing code
        $sql = "INSERT INTO accounts (code, label, type, is_active)
                VALUES ('401000', 'Duplicate Code Test', 'general', 1)";

        // This should fail due to UNIQUE constraint
        $this->expectException(\Exception::class);
        db_query($sql);
    }

    public function testUpdateAccount()
    {
        // Create account
        $sql = "INSERT INTO accounts (code, label, type, is_active) VALUES ('UPD001', 'Original', 'general', 1)";
        db_query($sql);
        $id = db_insert_id();

        // Update
        $new_label = 'Updated Label';
        $sql = "UPDATE accounts SET label = '" . db_escape($new_label) . "' WHERE id = $id";
        db_query($sql);

        // Verify
        $result = db_query("SELECT label FROM accounts WHERE id = $id");
        $account = db_fetch_assoc($result);

        $this->assertEquals($new_label, $account['label']);
    }

    public function testDeactivateAccount()
    {
        // Create account
        $sql = "INSERT INTO accounts (code, label, type, is_active) VALUES ('DEACT01', 'To Deactivate', 'general', 1)";
        db_query($sql);
        $id = db_insert_id();

        // Deactivate
        $sql = "UPDATE accounts SET is_active = 0 WHERE id = $id";
        db_query($sql);

        // Verify not returned by get_accounts
        $accounts = get_accounts(true);
        $found = false;
        foreach ($accounts as $acc) {
            if ($acc['id'] == $id) {
                $found = true;
                break;
            }
        }

        $this->assertFalse($found);
    }

    // ==================== Account Type Tests ====================

    public function testGetAccountsByType()
    {
        $customerAccounts = get_accounts(true, 'customer');

        foreach ($customerAccounts as $acc) {
            $this->assertEquals('customer', $acc['type']);
        }
    }

    public function testCreateCustomerAccount()
    {
        $code = '411TEST';
        $sql = "INSERT INTO accounts (code, label, type, is_active) VALUES ('$code', 'Test Customer', 'customer', 1)";
        db_query($sql);
        $id = db_insert_id();

        $result = db_query("SELECT type FROM accounts WHERE id = $id");
        $account = db_fetch_assoc($result);

        $this->assertEquals('customer', $account['type']);
    }

    public function testCreateVendorAccount()
    {
        $code = '401TEST';
        $sql = "INSERT INTO accounts (code, label, type, is_active) VALUES ('$code', 'Test Vendor', 'vendor', 1)";
        db_query($sql);
        $id = db_insert_id();

        $result = db_query("SELECT type FROM accounts WHERE id = $id");
        $account = db_fetch_assoc($result);

        $this->assertEquals('vendor', $account['type']);
    }

    // ==================== Account Search Tests ====================

    public function testSearchAccountsByCode()
    {
        $sql = "SELECT * FROM accounts WHERE code LIKE '401%' AND is_active = 1";
        $result = db_query($sql);
        $accounts = db_fetch_all($result);

        $this->assertGreaterThan(0, count($accounts));

        foreach ($accounts as $acc) {
            $this->assertStringStartsWith('401', $acc['code']);
        }
    }

    public function testSearchAccountsByLabel()
    {
        $search = 'Fournisseur';
        $sql = "SELECT * FROM accounts WHERE label LIKE '%" . db_escape($search) . "%' AND is_active = 1";
        $result = db_query($sql);
        $accounts = db_fetch_all($result);

        foreach ($accounts as $acc) {
            $this->assertStringContainsStringIgnoringCase($search, $acc['label']);
        }
    }

    // ==================== Get Functions Tests ====================

    public function testGetAccountsReturnsActiveOnly()
    {
        $accounts = get_accounts(true);

        foreach ($accounts as $acc) {
            $this->assertEquals(1, $acc['is_active']);
        }
    }

    public function testGetAccountsOrderedByCode()
    {
        $accounts = get_accounts();

        $previous_code = '';
        foreach ($accounts as $acc) {
            $this->assertGreaterThanOrEqual($previous_code, $acc['code']);
            $previous_code = $acc['code'];
        }
    }
}
