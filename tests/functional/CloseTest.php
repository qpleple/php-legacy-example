<?php
/**
 * Functional tests for Close (Period Locking / Year End) module
 */

require_once dirname(__FILE__) . '/FunctionalTestCase.php';

class CloseTest extends FunctionalTestCase
{
    function setUp(): void
    {
        parent::setUp();
        $this->requireApp();
        $this->loginAs('admin');
    }

    // ==================== Period Locking ====================

    function testLockPeriodPageLoads()
    {
        $response = $this->get('/modules/close/lock_period.php');

        $this->assertOk($response);
        $this->assertSee('Verrouillage', $response);
    }

    function testLockPeriodPageShowsPeriods()
    {
        $response = $this->get('/modules/close/lock_period.php');

        $this->assertSee('01/01/2026', $response);
        $this->assertSee('31/12/2026', $response);
    }

    function testLockPeriodPageShowsStatistics()
    {
        $response = $this->get('/modules/close/lock_period.php');

        $this->assertSee('Periodes ouvertes', $response);
        $this->assertSee('Periodes verroullees', $response);
    }

    function testLockPeriodPageShowsEntryCount()
    {
        $response = $this->get('/modules/close/lock_period.php');

        // Should show entry counts per period
        $this->assertSee('Brouillons', $response);
        $this->assertSee('Validees', $response);
    }

    function testLockPeriodPageHasLockButtons()
    {
        $response = $this->get('/modules/close/lock_period.php');

        $this->assertSee('Verrouiller', $response);
    }

    function testLockPeriodRequiresAdmin()
    {
        $this->cookies = array();
        $this->loginAs('comptable');

        $response = $this->get('/modules/close/lock_period.php');

        $this->assertContains($response['code'], array(302, 403));
    }

    // ==================== Year End ====================

    function testYearEndPageLoads()
    {
        $response = $this->get('/modules/close/year_end.php');

        $this->assertOk($response);
        $this->assertSee('Cloture', $response);
    }

    function testYearEndPageShowsPreconditions()
    {
        $response = $this->get('/modules/close/year_end.php');

        // Should show closing conditions
        $this->assertSee('periode', $response);
    }

    function testYearEndPageShowsCompanyInfo()
    {
        $response = $this->get('/modules/close/year_end.php');

        $this->assertSee('Ketchup Compta', $response);
    }

    function testYearEndPageShowsErrors()
    {
        $response = $this->get('/modules/close/year_end.php');

        // Should show errors because periods are open and there are drafts
        $body = $response['body'];
        $hasErrors = (strpos($body, 'non verrouillee') !== false) ||
                    (strpos($body, 'brouillon') !== false) ||
                    (strpos($body, 'Conditions') !== false);
        $this->assertTrue($hasErrors, 'Expected precondition errors');
    }

    function testYearEndPageShowsCarryForwardAccount()
    {
        $response = $this->get('/modules/close/year_end.php');

        $this->assertSee('110000', $response);
    }

    function testYearEndRequiresAdmin()
    {
        $this->cookies = array();
        $this->loginAs('comptable');

        $response = $this->get('/modules/close/year_end.php');

        $this->assertContains($response['code'], array(302, 403));
    }

    // ==================== Viewer Access ====================

    function testViewerCannotAccessLockPeriod()
    {
        $this->cookies = array();
        $this->loginAs('lecteur');

        $response = $this->get('/modules/close/lock_period.php');

        $this->assertContains($response['code'], array(302, 403));
    }

    function testViewerCannotAccessYearEnd()
    {
        $this->cookies = array();
        $this->loginAs('lecteur');

        $response = $this->get('/modules/close/year_end.php');

        $this->assertContains($response['code'], array(302, 403));
    }
}
