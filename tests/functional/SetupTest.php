<?php
/**
 * Functional tests for Setup module
 */

require_once dirname(__FILE__) . '/FunctionalTestCase.php';

class SetupTest extends FunctionalTestCase
{
    function setUp(): void
    {
        parent::setUp();
        $this->requireApp();
    }

    // ==================== Company Settings ====================

    function testCompanyPageLoads()
    {
        $this->loginAs('admin');
        $response = $this->get('/modules/setup/company.php');

        $this->assertOk($response);
        $this->assertSee('Paramétrage', $response);
    }

    function testCompanyPageHasForm()
    {
        $this->loginAs('admin');
        $response = $this->get('/modules/setup/company.php');

        $this->assertHasForm($response);
        $this->assertSee('name="currency"', $response);
    }

    // ==================== Chart of Accounts ====================

    function testAccountsPageLoads()
    {
        $this->loginAs('admin');
        $response = $this->get('/modules/setup/accounts.php');

        $this->assertOk($response);
        $this->assertSee('Plan Comptable', $response);
    }

    function testAccountsPageShowsAccounts()
    {
        $this->loginAs('admin');
        $response = $this->get('/modules/setup/accounts.php');

        $this->assertSee('101000', $response);
        $this->assertSee('Capital', $response);
    }

    function testAccountsPageHasCreateForm()
    {
        $this->loginAs('admin');
        $response = $this->get('/modules/setup/accounts.php');

        $this->assertHasForm($response);
        $this->assertSee('name="code"', $response);
    }

    // ==================== Journals ====================

    function testJournalsPageLoads()
    {
        $this->loginAs('admin');
        $response = $this->get('/modules/setup/journals.php');

        $this->assertOk($response);
        $this->assertSee('Journaux', $response);
    }

    function testJournalsPageShowsJournals()
    {
        $this->loginAs('admin');
        $response = $this->get('/modules/setup/journals.php');

        $this->assertSee('VE', $response);
        $this->assertSee('Ventes', $response);
    }
}
