<?php
/**
 * Functional tests for Bank module
 */

require_once dirname(__FILE__) . '/FunctionalTestCase.php';

class BankTest extends FunctionalTestCase
{
    function setUp(): void
    {
        parent::setUp();
        $this->requireApp();
        $this->loginAs('comptable');
    }

    // ==================== Bank Accounts ====================

    function testBankAccountsPageLoads()
    {
        $response = $this->get('/modules/bank/accounts.php');

        $this->assertOk($response);
        $this->assertSee('Comptes Bancaires', $response);
    }

    function testBankAccountsPageShowsAccounts()
    {
        $response = $this->get('/modules/bank/accounts.php');

        $this->assertSee('Compte BNP Principal', $response);
        $this->assertSee('512001', $response);
    }

    function testBankAccountsHasCreateForm()
    {
        $response = $this->get('/modules/bank/accounts.php');

        $this->assertHasForm($response);
        $this->assertSee('name="label"', $response);
    }

    function testCreateBankAccount()
    {
        $this->get('/modules/bank/accounts.php');

        $label = 'Test Bank ' . rand(100, 999);
        $response = $this->post('/modules/bank/accounts.php', array(
            'action' => 'create',
            'label' => $label,
            'account_id' => 12, // 512000 Banque
            'is_active' => 1
        ));

        $this->assertRedirectTo('/modules/bank/accounts.php', $response);

        $listResponse = $this->followRedirect($response);
        $this->assertSee($label, $listResponse);
    }

    // ==================== Import ====================

    function testImportPageLoads()
    {
        $response = $this->get('/modules/bank/import.php');

        $this->assertOk($response);
        $this->assertSee('Import', $response);
    }

    function testImportPageHasForm()
    {
        $response = $this->get('/modules/bank/import.php');

        $this->assertHasForm($response);
        $this->assertSee('type="file"', $response);
    }

    function testImportPageShowsBankAccountSelector()
    {
        $response = $this->get('/modules/bank/import.php');

        $this->assertSee('Compte BNP Principal', $response);
    }

    function testImportPageShowsFormatInfo()
    {
        $response = $this->get('/modules/bank/import.php');

        $this->assertSee('Format du fichier CSV', $response);
        $this->assertSee('date', $response);
        $this->assertSee('label', $response);
        $this->assertSee('amount', $response);
    }

    function testImportCsvCreatesStatement()
    {
        $this->get('/modules/bank/import.php');

        $response = $this->postWithFile(
            '/modules/bank/import.php',
            array('bank_account_id' => 1),
            'csv_file',
            $this->fixturePath('bank_statement.csv')
        );

        $this->assertOk($response);
        $this->assertSee('Import reussi', $response);
        $this->assertSee('Lignes importees', $response);
    }

    function testImportCsvShowsLineCount()
    {
        $this->get('/modules/bank/import.php');

        $response = $this->postWithFile(
            '/modules/bank/import.php',
            array('bank_account_id' => 1),
            'csv_file',
            $this->fixturePath('bank_statement.csv')
        );

        // Fixture has 7 data lines
        $this->assertSee('Lignes importees:</strong> 7', $response);
    }

    function testImportCsvShowsReconcileLink()
    {
        $this->get('/modules/bank/import.php');

        $response = $this->postWithFile(
            '/modules/bank/import.php',
            array('bank_account_id' => 1),
            'csv_file',
            $this->fixturePath('bank_statement.csv')
        );

        $this->assertSee('Passer au rapprochement', $response);
        $this->assertSee('reconcile.php?statement_id=', $response);
    }

    function testImportedStatementAppearsInList()
    {
        $this->get('/modules/bank/import.php');

        $this->postWithFile(
            '/modules/bank/import.php',
            array('bank_account_id' => 1),
            'csv_file',
            $this->fixturePath('bank_statement.csv')
        );

        $listResponse = $this->get('/modules/bank/import.php');
        $this->assertSee('bank_statement.csv', $listResponse);
    }

    function testImportRequiresBankAccount()
    {
        $this->get('/modules/bank/import.php');

        $response = $this->postWithFile(
            '/modules/bank/import.php',
            array('bank_account_id' => 0),
            'csv_file',
            $this->fixturePath('bank_statement.csv')
        );

        $this->assertOk($response);
        $this->assertSee('selectionner un compte bancaire', $response);
    }

    // ==================== Reconciliation ====================

    function testReconcilePageLoads()
    {
        $response = $this->get('/modules/bank/reconcile.php');

        $this->assertOk($response);
        $this->assertSee('Rapprochement Bancaire', $response);
    }

    function testReconcilePageShowsStatementSelector()
    {
        $response = $this->get('/modules/bank/reconcile.php');

        $this->assertSee('Choisir un releve', $response);
        $this->assertSee('releve_bnp_janvier_2026.csv', $response);
    }

    function testReconcilePageShowsStatementLines()
    {
        // Select statement 1
        $response = $this->get('/modules/bank/reconcile.php?statement_id=1');

        $this->assertOk($response);
        $this->assertSee('VIR RECU DUPONT', $response);
        $this->assertSee('VIR EMIS ABC', $response);
    }

    function testReconcilePageShowsMatchedLines()
    {
        $response = $this->get('/modules/bank/reconcile.php?statement_id=1');

        // Seeded data has matched lines
        $this->assertSee('matched', $response);
    }

    function testReconcilePageShowsUnmatchedLines()
    {
        $response = $this->get('/modules/bank/reconcile.php?statement_id=1');

        // Seeded data has unmatched lines
        $this->assertSee('unmatched', $response);
        $this->assertSee('PRLV ASSURANCE', $response);
    }

    function testReconcilePageShowsSummary()
    {
        $response = $this->get('/modules/bank/reconcile.php?statement_id=1');

        $this->assertSee('Total', $response);
    }

    // ==================== Access Control ====================

    function testViewerCannotAccessBankModule()
    {
        $this->cookies = array();
        $this->loginAs('lecteur');

        $response = $this->get('/modules/bank/accounts.php');

        $this->assertContains($response['code'], array(302, 403));
    }

    function testViewerCannotAccessReconciliation()
    {
        $this->cookies = array();
        $this->loginAs('lecteur');

        $response = $this->get('/modules/bank/reconcile.php');

        $this->assertContains($response['code'], array(302, 403));
    }
}
