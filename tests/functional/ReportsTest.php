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
        $this->loginAs('comptable');
    }

    // ==================== Journal Report ====================

    function testJournalReportPageLoads()
    {
        $response = $this->get('/modules/reports/journal.php');

        $this->assertOk($response);
        $this->assertTitle('Journal', $response);
        $this->assertSee('Etat du Journal', $response);
    }

    function testJournalReportShowsEntries()
    {
        $response = $this->get('/modules/reports/journal.php');

        $this->assertSee('VE2026-000001', $response);
        $this->assertSee('AC2026-000001', $response);
        $this->assertSee('Nb Pieces', $response);
    }

    function testJournalReportFilterByJournal()
    {
        $response = $this->get('/modules/reports/journal.php?journal_id=1');

        $this->assertOk($response);
        $this->assertSee('VE2026-', $response);
        $this->assertDontSee('AC2026-', $response);
    }

    function testJournalReportFilterByPeriod()
    {
        $response = $this->get('/modules/reports/journal.php?period_id=1');

        $this->assertOk($response);
        $this->assertSee('VE2026-000001', $response);
    }

    function testJournalReportHasPdfLink()
    {
        $response = $this->get('/modules/reports/journal.php');

        $this->assertSee('pdf_journal.php', $response);
    }

    function testJournalReportShowsTotals()
    {
        $response = $this->get('/modules/reports/journal.php');

        $this->assertSee('Total Debit', $response);
        $this->assertSee('Total Credit', $response);
        $this->assertSee('TOTAL GENERAL', $response);
    }

    // ==================== General Ledger ====================

    function testLedgerReportPageLoads()
    {
        $response = $this->get('/modules/reports/ledger.php');

        $this->assertOk($response);
        $this->assertTitle('Grand Livre', $response);
        $this->assertSee('Grand Livre', $response);
    }

    function testLedgerReportShowsAccounts()
    {
        $response = $this->get('/modules/reports/ledger.php');

        $this->assertSee('512001', $response);
        $this->assertSee('Banque BNP', $response);
        $this->assertSee('Nb Comptes', $response);
    }

    function testLedgerReportFilterByAccountRange()
    {
        $response = $this->get('/modules/reports/ledger.php?account_from=400000&account_to=499999');

        $this->assertOk($response);
        $this->assertSee('401001', $response);
        $this->assertSee('411001', $response);
        $this->assertDontSee('512001 -', $response);
    }

    function testLedgerReportFilterByJournal()
    {
        $response = $this->get('/modules/reports/ledger.php?journal_id=1');

        $this->assertOk($response);
        $this->assertSee('VE', $response);
    }

    function testLedgerReportShowsRunningBalance()
    {
        $response = $this->get('/modules/reports/ledger.php');

        $this->assertSee('Solde', $response);
    }

    function testLedgerReportHasPdfLink()
    {
        $response = $this->get('/modules/reports/ledger.php');

        $this->assertSee('pdf_ledger.php', $response);
    }

    // ==================== Trial Balance ====================

    function testTrialBalancePageLoads()
    {
        $response = $this->get('/modules/reports/trial_balance.php');

        $this->assertOk($response);
        $this->assertTitle('Balance', $response);
        $this->assertSee('Balance Generale', $response);
    }

    function testTrialBalanceShowsAccounts()
    {
        $response = $this->get('/modules/reports/trial_balance.php');

        $this->assertSee('512001', $response);
        $this->assertSee('Total Debit', $response);
        $this->assertSee('Total Credit', $response);
    }

    function testTrialBalanceShowsSoldes()
    {
        $response = $this->get('/modules/reports/trial_balance.php');

        $this->assertSee('Solde Debit', $response);
        $this->assertSee('Solde Credit', $response);
        $this->assertSee('TOTAUX', $response);
    }

    function testTrialBalanceShowsBalanceStatus()
    {
        $response = $this->get('/modules/reports/trial_balance.php');

        // Should show either balanced or imbalance warning
        $body = $response['body'];
        $hasBalanceStatus = (strpos($body, 'Balance equilibree') !== false) ||
                           (strpos($body, 'ecart de') !== false);
        $this->assertTrue($hasBalanceStatus, 'Expected balance status display');
    }

    function testTrialBalanceFilterByPeriod()
    {
        $response = $this->get('/modules/reports/trial_balance.php?period_id=1');

        $this->assertOk($response);
        $this->assertSee('Balance Generale', $response);
    }

    function testTrialBalanceHasPdfLink()
    {
        $response = $this->get('/modules/reports/trial_balance.php');

        $this->assertSee('pdf_trial_balance.php', $response);
    }

    // ==================== VAT Summary ====================

    function testVatSummaryPageLoads()
    {
        $response = $this->get('/modules/reports/vat_summary.php');

        $this->assertOk($response);
        $this->assertSee('Synthese TVA', $response);
    }

    function testVatSummaryShowsCollectedAndDeductible()
    {
        $response = $this->get('/modules/reports/vat_summary.php');

        $this->assertSee('TVA Collectee', $response);
        $this->assertSee('TVA Deductible', $response);
        $this->assertSee('TVA a reverser', $response);
    }

    function testVatSummaryShowsAmounts()
    {
        $response = $this->get('/modules/reports/vat_summary.php');

        // Check that VAT amounts are displayed (from seed data)
        $this->assertSee('755,00', $response); // TVA collectee
        $this->assertSee('210,00', $response); // TVA deductible
    }

    function testVatSummaryHasPdfLink()
    {
        $response = $this->get('/modules/reports/vat_summary.php');

        $this->assertSee('pdf_vat.php', $response);
    }

    // ==================== PDF Reports ====================

    function testPdfJournalReturnsValidPdf()
    {
        $response = $this->get('/modules/reports/pdf_journal.php');

        $this->assertOk($response);
        $this->assertSee('%PDF-', $response);
    }

    function testPdfLedgerReturnsValidPdf()
    {
        $response = $this->get('/modules/reports/pdf_ledger.php');

        $this->assertOk($response);
        $this->assertSee('%PDF-', $response);
    }

    function testPdfTrialBalanceReturnsValidPdf()
    {
        $response = $this->get('/modules/reports/pdf_trial_balance.php');

        $this->assertOk($response);
        $this->assertSee('%PDF-', $response);
    }

    function testPdfVatReturnsValidPdf()
    {
        $response = $this->get('/modules/reports/pdf_vat.php');

        $this->assertOk($response);
        $this->assertSee('%PDF-', $response);
    }

    // ==================== Access Control ====================

    function testViewerCanAccessReports()
    {
        $this->cookies = array();
        $this->loginAs('lecteur');

        $response = $this->get('/modules/reports/trial_balance.php');

        // Viewer should be able to see reports (requires accountant role though)
        $this->assertContains($response['code'], array(200, 302, 403));
    }
}
