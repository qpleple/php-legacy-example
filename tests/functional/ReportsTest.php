<?php
/**
 * Functional tests for Reports module
 */

require_once dirname(__FILE__) . '/FunctionalTestCase.php';

class ReportsTest extends FunctionalTestCase
{
    function setUp(): void
    {
        parent::setUp();
        $this->requireApp();
        $this->loginAs('admin');
    }

    // ==================== Journal Report ====================

    function testJournalReportPageLoads()
    {
        $response = $this->get('/modules/reports/journal.php');

        $this->assertOk($response);
        $this->assertSee('Journal', $response);
    }

    function testJournalReportShowsEntries()
    {
        $response = $this->get('/modules/reports/journal.php');

        $this->assertSee('VE2026-', $response);
    }

    function testJournalReportFilterByJournal()
    {
        $response = $this->get('/modules/reports/journal.php?journal_id=1');

        $this->assertOk($response);
        $this->assertSee('VE2026-', $response);
    }

    // ==================== General Ledger ====================

    function testLedgerReportPageLoads()
    {
        $response = $this->get('/modules/reports/ledger.php');

        $this->assertOk($response);
        $this->assertSee('Grand Livre', $response);
    }

    function testLedgerReportShowsAccounts()
    {
        $response = $this->get('/modules/reports/ledger.php');

        $this->assertSee('512000', $response);
    }

    // ==================== Trial Balance ====================

    function testTrialBalancePageLoads()
    {
        $response = $this->get('/modules/reports/trial_balance.php');

        $this->assertOk($response);
        $this->assertSee('Balance', $response);
    }

    function testTrialBalanceShowsAccounts()
    {
        $response = $this->get('/modules/reports/trial_balance.php');

        $this->assertSee('512000', $response);
    }
}
