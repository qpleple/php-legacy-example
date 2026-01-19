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
        $this->assertSee('Parametrage', $response);
        $this->assertSee('Ketchup Compta', $response);
    }

    function testCompanyPageHasForm()
    {
        $this->loginAs('admin');
        $response = $this->get('/modules/setup/company.php');

        $this->assertHasForm($response);
        $this->assertSee('name="name"', $response);
        $this->assertSee('name="currency"', $response);
    }

    function testCompanyPageRequiresAdmin()
    {
        $this->loginAs('comptable');
        $response = $this->get('/modules/setup/company.php');

        // accountant cannot access company settings
        $this->assertContains($response['code'], array(302, 403));
    }

    // ==================== Periods ====================

    function testPeriodsPageLoads()
    {
        $this->loginAs('admin');
        $response = $this->get('/modules/setup/periods.php');

        $this->assertOk($response);
        $this->assertSee('Periodes', $response);
        $this->assertSee('01/01/2026', $response);
    }

    function testPeriodsPageShowsAllPeriods()
    {
        $this->loginAs('admin');
        $response = $this->get('/modules/setup/periods.php');

        $this->assertSee('01/01/2026', $response);
        $this->assertSee('31/12/2026', $response);
    }

    function testPeriodsPageHasGenerateButton()
    {
        $this->loginAs('admin');
        $response = $this->get('/modules/setup/periods.php');

        $this->assertSee('Generer periodes mensuelles', $response);
    }

    // ==================== Chart of Accounts ====================

    function testAccountsPageLoads()
    {
        $this->loginAs('comptable');
        $response = $this->get('/modules/setup/accounts.php');

        $this->assertOk($response);
        $this->assertSee('Plan Comptable', $response);
    }

    function testAccountsPageShowsAccounts()
    {
        $this->loginAs('comptable');
        $response = $this->get('/modules/setup/accounts.php');

        $this->assertSee('101000', $response);
        $this->assertSee('Capital social', $response);
        $this->assertSee('512001', $response);
        $this->assertSee('Banque BNP', $response);
    }

    function testAccountsPageHasCreateForm()
    {
        $this->loginAs('comptable');
        $response = $this->get('/modules/setup/accounts.php');

        $this->assertHasForm($response);
        $this->assertSee('name="code"', $response);
        $this->assertSee('name="label"', $response);
    }

    function testAccountsPageHasSearch()
    {
        $this->loginAs('comptable');
        $response = $this->get('/modules/setup/accounts.php?search=banque');

        $this->assertOk($response);
        $this->assertSee('512001', $response);
    }

    function testCreateAccount()
    {
        $this->loginAs('comptable');
        $this->get('/modules/setup/accounts.php');

        // Use unique code to avoid conflicts
        $code = '9' . rand(10000, 99999);
        $response = $this->post('/modules/setup/accounts.php', array(
            'action' => 'create',
            'code' => $code,
            'label' => 'Test Account',
            'type' => 'general',
            'is_active' => 1
        ));

        $this->assertRedirectTo('/modules/setup/accounts.php', $response);

        // Verify account appears in list
        $listResponse = $this->followRedirect($response);
        $this->assertSee($code, $listResponse);
    }

    function testCannotCreateDuplicateAccount()
    {
        $this->loginAs('comptable');
        $this->get('/modules/setup/accounts.php');

        $response = $this->post('/modules/setup/accounts.php', array(
            'action' => 'create',
            'code' => '101000',
            'label' => 'Duplicate',
            'type' => 'general'
        ));

        // Returns 200 with error on same page (no redirect on validation error)
        $this->assertOk($response);
        $this->assertSee('existe deja', $response);
    }

    function testAccountsPageHasImportForm()
    {
        $this->loginAs('comptable');
        $response = $this->get('/modules/setup/accounts.php');

        $this->assertSee('Import CSV', $response);
        $this->assertSee('type="file"', $response);
    }

    function testImportAccountsCsv()
    {
        $this->loginAs('comptable');
        $this->get('/modules/setup/accounts.php');

        $response = $this->postWithFile(
            '/modules/setup/accounts.php',
            array('action' => 'import'),
            'csv_file',
            $this->fixturePath('chart_of_accounts.csv')
        );

        $this->assertRedirectTo('/modules/setup/accounts.php', $response);

        $listResponse = $this->followRedirect($response);
        $this->assertSee('comptes importes', $listResponse);
    }

    function testImportedAccountsAppearInList()
    {
        $this->loginAs('comptable');
        $this->get('/modules/setup/accounts.php');

        $this->postWithFile(
            '/modules/setup/accounts.php',
            array('action' => 'import'),
            'csv_file',
            $this->fixturePath('chart_of_accounts.csv')
        );

        $listResponse = $this->get('/modules/setup/accounts.php?search=801');
        $this->assertSee('801000', $listResponse);
        $this->assertSee('Charges exceptionnelles', $listResponse);
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
        $this->assertSee('Journal des Ventes', $response);
        $this->assertSee('AC', $response);
        $this->assertSee('BK', $response);
        $this->assertSee('OD', $response);
    }

    function testJournalsPageRequiresAdmin()
    {
        $this->loginAs('comptable');
        $response = $this->get('/modules/setup/journals.php');

        $this->assertContains($response['code'], array(302, 403));
    }

    function testCreateJournal()
    {
        $this->loginAs('admin');
        $this->get('/modules/setup/journals.php');

        // Use unique 2-letter code
        $code = 'T' . chr(65 + (time() % 26));
        $response = $this->post('/modules/setup/journals.php', array(
            'action' => 'create',
            'code' => $code,
            'label' => 'Test Journal ' . $code,
            'sequence_prefix' => $code,
            'next_number' => 1,
            'is_active' => 1
        ));

        $this->assertRedirectTo('/modules/setup/journals.php', $response);

        // Verify journal appears
        $listResponse = $this->followRedirect($response);
        $this->assertSee('Test Journal', $listResponse);
    }

    // ==================== Third Parties ====================

    function testThirdPartiesPageLoads()
    {
        $this->loginAs('comptable');
        $response = $this->get('/modules/setup/third_parties.php');

        $this->assertOk($response);
        $this->assertSee('Tiers', $response);
    }

    function testThirdPartiesPageShowsThirdParties()
    {
        $this->loginAs('comptable');
        $response = $this->get('/modules/setup/third_parties.php');

        $this->assertSee('Client Dupont', $response);
        $this->assertSee('Client Martin', $response);
        $this->assertSee('Fournisseur ABC', $response);
        $this->assertSee('Fournisseur XYZ', $response);
    }

    function testThirdPartiesFilterByType()
    {
        $this->loginAs('comptable');
        $response = $this->get('/modules/setup/third_parties.php?type=customer');

        $this->assertOk($response);
        $this->assertSee('Client Dupont', $response);
        $this->assertSee('Client Martin', $response);
    }

    function testCreateThirdParty()
    {
        $this->loginAs('comptable');
        $this->get('/modules/setup/third_parties.php');

        $name = 'Client Test ' . rand(100, 999);
        $response = $this->post('/modules/setup/third_parties.php', array(
            'action' => 'create',
            'type' => 'customer',
            'name' => $name,
            'email' => 'test@example.com'
        ));

        $this->assertRedirectTo('/modules/setup/third_parties.php', $response);

        $listResponse = $this->followRedirect($response);
        $this->assertSee($name, $listResponse);
    }

    // ==================== VAT Rates ====================

    function testVatPageLoads()
    {
        $this->loginAs('admin');
        $response = $this->get('/modules/setup/vat.php');

        $this->assertOk($response);
        $this->assertSee('Taux de TVA', $response);
    }

    function testVatPageShowsRates()
    {
        $this->loginAs('admin');
        $response = $this->get('/modules/setup/vat.php');

        $this->assertSee('TVA 20%', $response);
        $this->assertSee('TVA 10%', $response);
        $this->assertSee('TVA 5.5%', $response);
        $this->assertSee('Exonere', $response);
    }

    function testVatPageRequiresAdmin()
    {
        $this->loginAs('comptable');
        $response = $this->get('/modules/setup/vat.php');

        $this->assertContains($response['code'], array(302, 403));
    }

    function testCreateVatRate()
    {
        $this->loginAs('admin');
        $this->get('/modules/setup/vat.php');

        $label = 'TVA Test ' . rand(100, 999);
        $response = $this->post('/modules/setup/vat.php', array(
            'action' => 'create',
            'label' => $label,
            'rate' => '8.50',
            'account_collected' => '44571',
            'account_deductible' => '44566',
            'is_active' => 1
        ));

        $this->assertRedirectTo('/modules/setup/vat.php', $response);

        $listResponse = $this->followRedirect($response);
        $this->assertSee($label, $listResponse);
    }

    // ==================== Access Control ====================

    function testViewerCannotAccessSetupPages()
    {
        $this->loginAs('lecteur');

        $response = $this->get('/modules/setup/accounts.php');
        $this->assertContains($response['code'], array(302, 403));
    }
}
