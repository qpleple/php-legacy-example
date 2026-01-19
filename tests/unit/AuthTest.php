<?php
/**
 * Unit tests for auth.php functions
 * Tests functions that don't require database connection
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Define mock db functions before including auth.php
if (!function_exists('db_escape')) {
    function db_escape($value) {
        return addslashes($value);
    }
}

if (!function_exists('db_query')) {
    function db_query($sql) {
        return false;
    }
}

if (!function_exists('db_num_rows')) {
    function db_num_rows($result) {
        return 0;
    }
}

if (!function_exists('db_fetch_assoc')) {
    function db_fetch_assoc($result) {
        return null;
    }
}

// Include auth after mocks
require_once WWW_PATH . '/lib/auth.php';

class AuthTest extends PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        clearSession();
    }

    protected function tearDown(): void
    {
        clearSession();
    }

    // ==================== auth_hash_password() tests ====================

    public function testHashPasswordReturnsString()
    {
        $hash = auth_hash_password('password123');
        $this->assertIsString($hash);
    }

    public function testHashPasswordConsistent()
    {
        $hash1 = auth_hash_password('password123');
        $hash2 = auth_hash_password('password123');
        $this->assertEquals($hash1, $hash2);
    }

    public function testHashPasswordDifferentForDifferentPasswords()
    {
        $hash1 = auth_hash_password('password123');
        $hash2 = auth_hash_password('password456');
        $this->assertNotEquals($hash1, $hash2);
    }

    public function testHashPasswordIsMd5Length()
    {
        $hash = auth_hash_password('test');
        $this->assertEquals(32, strlen($hash)); // MD5 is 32 hex chars
    }

    // ==================== auth_verify_password() tests ====================

    public function testVerifyPasswordCorrect()
    {
        $hash = auth_hash_password('mypassword');
        $this->assertTrue(auth_verify_password('mypassword', $hash));
    }

    public function testVerifyPasswordIncorrect()
    {
        $hash = auth_hash_password('mypassword');
        $this->assertFalse(auth_verify_password('wrongpassword', $hash));
    }

    public function testVerifyPasswordEmptyPassword()
    {
        $hash = auth_hash_password('');
        $this->assertTrue(auth_verify_password('', $hash));
    }

    // ==================== auth_is_logged_in() tests ====================

    public function testIsLoggedInTrue()
    {
        $_SESSION['user_id'] = 1;
        $this->assertTrue(auth_is_logged_in());
    }

    public function testIsLoggedInFalse()
    {
        unset($_SESSION['user_id']);
        $this->assertFalse(auth_is_logged_in());
    }

    // ==================== auth_user_id() tests ====================

    public function testUserIdReturnsId()
    {
        $_SESSION['user_id'] = 42;
        $this->assertEquals(42, auth_user_id());
    }

    public function testUserIdReturnsNullWhenNotLoggedIn()
    {
        unset($_SESSION['user_id']);
        $this->assertNull(auth_user_id());
    }

    // ==================== auth_username() tests ====================

    public function testUsernameReturnsUsername()
    {
        $_SESSION['username'] = 'admin';
        $this->assertEquals('admin', auth_username());
    }

    public function testUsernameReturnsNullWhenNotLoggedIn()
    {
        unset($_SESSION['username']);
        $this->assertNull(auth_username());
    }

    // ==================== auth_role() tests ====================

    public function testRoleReturnsRole()
    {
        $_SESSION['role'] = 'admin';
        $this->assertEquals('admin', auth_role());
    }

    public function testRoleReturnsNullWhenNotLoggedIn()
    {
        unset($_SESSION['role']);
        $this->assertNull(auth_role());
    }

    // ==================== auth_has_role() tests ====================

    public function testHasRoleAdminHasAllRoles()
    {
        $_SESSION['role'] = 'admin';

        $this->assertTrue(auth_has_role('admin'));
        $this->assertTrue(auth_has_role('accountant'));
        $this->assertTrue(auth_has_role('viewer'));
    }

    public function testHasRoleAccountantHasAccountantAndViewer()
    {
        $_SESSION['role'] = 'accountant';

        $this->assertFalse(auth_has_role('admin'));
        $this->assertTrue(auth_has_role('accountant'));
        $this->assertTrue(auth_has_role('viewer'));
    }

    public function testHasRoleViewerOnlyHasViewer()
    {
        $_SESSION['role'] = 'viewer';

        $this->assertFalse(auth_has_role('admin'));
        $this->assertFalse(auth_has_role('accountant'));
        $this->assertTrue(auth_has_role('viewer'));
    }

    public function testHasRoleNoRoleHasNothing()
    {
        unset($_SESSION['role']);

        $this->assertFalse(auth_has_role('admin'));
        $this->assertFalse(auth_has_role('accountant'));
        $this->assertFalse(auth_has_role('viewer'));
    }

    public function testHasRoleUnknownRoleRequired()
    {
        $_SESSION['role'] = 'admin';
        // Unknown required role has level 0, so any logged-in user should pass
        $this->assertTrue(auth_has_role('unknown'));
    }

    // ==================== csrf_token() tests ====================

    public function testCsrfTokenGeneratesToken()
    {
        $token = csrf_token();

        $this->assertIsString($token);
        $this->assertEquals(32, strlen($token)); // 16 bytes = 32 hex chars
    }

    public function testCsrfTokenConsistentInSameSession()
    {
        $token1 = csrf_token();
        $token2 = csrf_token();

        $this->assertEquals($token1, $token2);
    }

    public function testCsrfTokenStoredInSession()
    {
        $token = csrf_token();

        $this->assertEquals($token, $_SESSION['csrf_token']);
    }

    // ==================== csrf_field() tests ====================

    public function testCsrfFieldGeneratesHiddenInput()
    {
        $field = csrf_field();

        $this->assertStringContainsString('type="hidden"', $field);
        $this->assertStringContainsString('name="_csrf"', $field);
        $this->assertStringContainsString('value="', $field);
    }

    // ==================== csrf_verify() tests ====================

    public function testCsrfVerifyValidToken()
    {
        $token = csrf_token();
        $_POST['_csrf'] = $token;

        $this->assertTrue(csrf_verify());
    }

    public function testCsrfVerifyInvalidToken()
    {
        $_SESSION['csrf_token'] = 'valid_token_12345678901234567890';
        $_POST['_csrf'] = 'invalid_token';

        $this->assertFalse(csrf_verify());
    }

    public function testCsrfVerifyMissingPostToken()
    {
        $_SESSION['csrf_token'] = 'valid_token_12345678901234567890';
        unset($_POST['_csrf']);

        $this->assertFalse(csrf_verify());
    }

    public function testCsrfVerifyMissingSessionToken()
    {
        unset($_SESSION['csrf_token']);
        $_POST['_csrf'] = 'some_token';

        $this->assertFalse(csrf_verify());
    }

    // ==================== auth_start_session() tests ====================

    public function testAuthStartSessionStartsSession()
    {
        auth_start_session();
        $this->assertNotEquals(PHP_SESSION_NONE, session_status());
    }
}
