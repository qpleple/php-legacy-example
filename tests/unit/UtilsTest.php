<?php
/**
 * Unit tests for utils.php functions
 */

// Include required files
require_once dirname(__DIR__) . '/bootstrap.php';

// Define mock functions for dependencies
if (!function_exists('auth_start_session')) {
    function auth_start_session() {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
    }
}

if (!function_exists('db_escape')) {
    function db_escape($value) {
        return addslashes($value);
    }
}

if (!function_exists('db_query')) {
    function db_query($sql) {
        return false; // Mock - won't be used in pure unit tests
    }
}

if (!function_exists('db_fetch_all')) {
    function db_fetch_all($result) {
        return [];
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

// Include utils after mocks
require_once WWW_PATH . '/lib/utils.php';

class UtilsTest extends PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        clearSession();
        clearPost();
    }

    // ==================== format_money() tests ====================

    public function testFormatMoneyPositive()
    {
        $this->assertEquals('1 234,56 EUR', format_money(1234.56));
    }

    public function testFormatMoneyZero()
    {
        $this->assertEquals('0,00 EUR', format_money(0));
    }

    public function testFormatMoneyNegative()
    {
        $this->assertEquals('-1 234,56 EUR', format_money(-1234.56));
    }

    public function testFormatMoneySmallAmount()
    {
        $this->assertEquals('0,99 EUR', format_money(0.99));
    }

    public function testFormatMoneyLargeAmount()
    {
        $this->assertEquals('1 000 000,00 EUR', format_money(1000000));
    }

    public function testFormatMoneyCustomCurrency()
    {
        $this->assertEquals('100,00 USD', format_money(100, 'USD'));
    }

    // ==================== format_date() tests ====================

    public function testFormatDateValid()
    {
        $this->assertEquals('15/03/2024', format_date('2024-03-15'));
    }

    public function testFormatDateEmpty()
    {
        $this->assertEquals('', format_date(''));
    }

    public function testFormatDateNull()
    {
        $this->assertEquals('', format_date(null));
    }

    // ==================== format_datetime() tests ====================

    public function testFormatDatetimeValid()
    {
        $this->assertEquals('15/03/2024 14:30', format_datetime('2024-03-15 14:30:00'));
    }

    public function testFormatDatetimeEmpty()
    {
        $this->assertEquals('', format_datetime(''));
    }

    // ==================== parse_date() tests ====================

    public function testParseDateFrenchFormat()
    {
        $this->assertEquals('2024-03-15', parse_date('15/03/2024'));
    }

    public function testParseDateSqlFormat()
    {
        $this->assertEquals('2024-03-15', parse_date('2024-03-15'));
    }

    public function testParseDateEmpty()
    {
        $this->assertNull(parse_date(''));
    }

    public function testParseDateNull()
    {
        $this->assertNull(parse_date(null));
    }

    public function testParseDateInvalidFormat()
    {
        $this->assertNull(parse_date('March 15, 2024'));
    }

    public function testParseDatePartialFormat()
    {
        $this->assertNull(parse_date('15/03'));
    }

    // ==================== parse_number() tests ====================

    public function testParseNumberWithDot()
    {
        $this->assertEquals(1234.56, parse_number('1234.56'));
    }

    public function testParseNumberWithComma()
    {
        $this->assertEquals(1234.56, parse_number('1234,56'));
    }

    public function testParseNumberWithSpaces()
    {
        $this->assertEquals(1234.56, parse_number('1 234,56'));
    }

    public function testParseNumberEmpty()
    {
        $this->assertEquals(0, parse_number(''));
    }

    public function testParseNumberInteger()
    {
        $this->assertEquals(100, parse_number('100'));
    }

    public function testParseNumberNegative()
    {
        $this->assertEquals(-50.25, parse_number('-50,25'));
    }

    // ==================== h() tests (HTML escaping) ====================

    public function testHEscapesHtml()
    {
        $this->assertEquals('&lt;script&gt;alert(1)&lt;/script&gt;', h('<script>alert(1)</script>'));
    }

    public function testHEscapesQuotes()
    {
        $this->assertEquals('&quot;quoted&quot;', h('"quoted"'));
    }

    public function testHEscapesSingleQuotes()
    {
        $this->assertEquals('&#039;quoted&#039;', h("'quoted'"));
    }

    public function testHEscapesAmpersand()
    {
        $this->assertEquals('foo &amp; bar', h('foo & bar'));
    }

    public function testHPreservesNormalText()
    {
        $this->assertEquals('Normal text', h('Normal text'));
    }

    // ==================== post() tests ====================

    public function testPostExistingKey()
    {
        $_POST['test'] = 'value';
        $this->assertEquals('value', post('test'));
    }

    public function testPostMissingKey()
    {
        $this->assertEquals('', post('nonexistent'));
    }

    public function testPostWithDefault()
    {
        $this->assertEquals('default', post('nonexistent', 'default'));
    }

    // ==================== get() tests ====================

    public function testGetExistingKey()
    {
        $_GET['test'] = 'value';
        $this->assertEquals('value', get('test'));
        unset($_GET['test']);
    }

    public function testGetMissingKey()
    {
        $this->assertEquals('', get('nonexistent'));
    }

    public function testGetWithDefault()
    {
        $this->assertEquals('default', get('nonexistent', 'default'));
    }

    // ==================== is_post() tests ====================

    public function testIsPostTrue()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->assertTrue(is_post());
    }

    public function testIsPostFalse()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->assertFalse(is_post());
    }

    // ==================== paginate() tests ====================

    public function testPaginateFirstPage()
    {
        $result = paginate(100, 1, 20);

        $this->assertEquals(100, $result['total']);
        $this->assertEquals(1, $result['page']);
        $this->assertEquals(20, $result['per_page']);
        $this->assertEquals(5, $result['total_pages']);
        $this->assertEquals(0, $result['offset']);
        $this->assertFalse($result['has_prev']);
        $this->assertTrue($result['has_next']);
    }

    public function testPaginateMiddlePage()
    {
        $result = paginate(100, 3, 20);

        $this->assertEquals(3, $result['page']);
        $this->assertEquals(40, $result['offset']);
        $this->assertTrue($result['has_prev']);
        $this->assertTrue($result['has_next']);
    }

    public function testPaginateLastPage()
    {
        $result = paginate(100, 5, 20);

        $this->assertEquals(5, $result['page']);
        $this->assertEquals(80, $result['offset']);
        $this->assertTrue($result['has_prev']);
        $this->assertFalse($result['has_next']);
    }

    public function testPaginatePageZeroBecomesOne()
    {
        $result = paginate(100, 0, 20);
        $this->assertEquals(1, $result['page']);
    }

    public function testPaginatePageExceedsTotalBecomesMax()
    {
        $result = paginate(100, 10, 20);
        $this->assertEquals(5, $result['page']);
    }

    public function testPaginateSinglePage()
    {
        $result = paginate(15, 1, 20);

        $this->assertEquals(1, $result['total_pages']);
        $this->assertFalse($result['has_prev']);
        $this->assertFalse($result['has_next']);
    }

    public function testPaginateEmptyResults()
    {
        $result = paginate(0, 1, 20);

        $this->assertEquals(0, $result['total']);
        $this->assertEquals(0, $result['total_pages']);
    }

    // ==================== validate_double_entry() tests ====================

    public function testValidateDoubleEntryBalanced()
    {
        $this->assertTrue(validate_double_entry(100.00, 100.00));
    }

    public function testValidateDoubleEntryUnbalanced()
    {
        $this->assertFalse(validate_double_entry(100.00, 50.00));
    }

    public function testValidateDoubleEntryWithinTolerance()
    {
        // Small differences within 0.01 tolerance should pass
        // Note: Use values that work with floating point arithmetic
        $this->assertTrue(validate_double_entry(100.00, 99.995));
        $this->assertTrue(validate_double_entry(100.005, 100.00));
    }

    public function testValidateDoubleEntryOutsideTolerance()
    {
        // Differences > 0.01 should fail
        $this->assertFalse(validate_double_entry(100.00, 99.98));
        $this->assertFalse(validate_double_entry(100.00, 100.02));
    }

    public function testValidateDoubleEntryZero()
    {
        $this->assertTrue(validate_double_entry(0, 0));
    }

    public function testValidateDoubleEntryLargeAmounts()
    {
        $this->assertTrue(validate_double_entry(1000000.50, 1000000.50));
    }

    // ==================== set_flash() and get_flash() tests ====================

    public function testSetAndGetFlash()
    {
        set_flash('success', 'Test message');
        $flash = get_flash();

        $this->assertNotNull($flash);
        $this->assertEquals('success', $flash['type']);
        $this->assertEquals('Test message', $flash['msg']);
    }

    public function testFlashIsClearedAfterGet()
    {
        set_flash('error', 'Error message');
        get_flash(); // First get
        $flash = get_flash(); // Second get

        $this->assertNull($flash);
    }

    public function testGetFlashWhenNoneSet()
    {
        clearSession();
        $flash = get_flash();
        $this->assertNull($flash);
    }

    // ==================== pagination_links() tests ====================

    public function testPaginationLinksMultiplePages()
    {
        $pagination = paginate(100, 2, 20);
        $html = pagination_links($pagination, '/test?foo=bar');

        $this->assertStringContainsString('pagination', $html);
        $this->assertStringContainsString('Precedent', $html);
        $this->assertStringContainsString('Suivant', $html);
        $this->assertStringContainsString('page=1', $html);
        $this->assertStringContainsString('page=3', $html);
    }

    public function testPaginationLinksSinglePage()
    {
        $pagination = paginate(10, 1, 20);
        $html = pagination_links($pagination, '/test');

        $this->assertEquals('', $html);
    }
}
