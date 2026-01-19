<?php
/**
 * Functional tests for Letters (Lettering) module
 *
 * Tests the full user workflow through HTTP:
 * - Selection page with account lists
 * - Lettering page with line selection
 * - Creating letterings
 * - History page
 * - Auto-suggestion AJAX
 */

require_once dirname(__FILE__) . '/FunctionalTestCase.php';

class LettersTest extends FunctionalTestCase
{
    function setUp(): void
    {
        parent::setUp();
        $this->requireApp();
        $this->loginAs('comptable');
    }

    // ==================== Selection Page ====================

    function testSelectPageLoads()
    {
        $response = $this->get('/modules/letters/select.php');

        $this->assertOk($response);
        $this->assertSee('Lettrage', $response);
    }

    function testSelectPageShowsStatistics()
    {
        $response = $this->get('/modules/letters/select.php');

        $this->assertSee('Comptes clients', $response);
        $this->assertSee('Comptes fournisseurs', $response);
        $this->assertSee('Lignes non lettrees', $response);
    }

    function testSelectPageShowsAccountTable()
    {
        $response = $this->get('/modules/letters/select.php');

        $this->assertSee('Selection par compte', $response);
        $this->assertSee('Compte', $response);
        $this->assertSee('Lignes', $response);
        $this->assertSee('Solde', $response);
    }

    function testSelectPageShowsThirdPartyTable()
    {
        $response = $this->get('/modules/letters/select.php');

        $this->assertSee('Selection par tiers', $response);
    }

    function testSelectPageHasHistoryLink()
    {
        $response = $this->get('/modules/letters/select.php');

        $this->assertSee('Historique des lettrages', $response);
        $this->assertSee('history.php', $response);
    }

    function testSelectPageHasFilters()
    {
        $response = $this->get('/modules/letters/select.php');

        $this->assertSee('Clients (411)', $response);
        $this->assertSee('Fournisseurs (401)', $response);
    }

    function testSelectPageFilterByCustomer()
    {
        $response = $this->get('/modules/letters/select.php?type=customer');

        $this->assertOk($response);
        $this->assertSee('Client', $response);
    }

    function testSelectPageFilterByVendor()
    {
        $response = $this->get('/modules/letters/select.php?type=vendor');

        $this->assertOk($response);
    }

    function testSelectPageShowLetterCodes()
    {
        $response = $this->get('/modules/letters/select.php');

        // Column header for letter codes
        $this->assertSee('Dernier code', $response);
    }

    function testSelectPageShowsCustomerAndVendor()
    {
        $response = $this->get('/modules/letters/select.php');

        // Should show customer or vendor types
        $body = $response['body'];
        $hasTypes = (strpos($body, 'Client') !== false) ||
                   (strpos($body, 'Fournisseur') !== false);
        $this->assertTrue($hasTypes, 'Expected customer or vendor types');
    }

    function testSelectPageShowsTotals()
    {
        $response = $this->get('/modules/letters/select.php');

        // Shows debit/credit columns
        $this->assertSee('Debit', $response);
        $this->assertSee('Credit', $response);
        $this->assertSee('Solde', $response);
    }

    function testSelectPageHasLetterLinks()
    {
        $response = $this->get('/modules/letters/select.php');

        $this->assertSee('letter.php', $response);
    }

    // ==================== Letter Page ====================

    function testLetterPageLoadsForAccount()
    {
        // Get account list first to find a valid account
        $selectResponse = $this->get('/modules/letters/select.php');

        // Extract an account ID from the page
        $accountId = $this->extractFromBody('/letter\.php\?account_id=(\d+)/', $selectResponse);

        if ($accountId) {
            $response = $this->get('/modules/letters/letter.php?account_id=' . $accountId);
            $this->assertOk($response);
            $this->assertSee('Lettrage', $response);
        } else {
            $this->markTestSkipped('No letterable accounts found');
        }
    }

    function testLetterPageShowsAccountInfo()
    {
        $selectResponse = $this->get('/modules/letters/select.php');
        $accountId = $this->extractFromBody('/letter\.php\?account_id=(\d+)/', $selectResponse);

        if ($accountId) {
            $response = $this->get('/modules/letters/letter.php?account_id=' . $accountId);

            $this->assertSee('Compte:', $response);
            $this->assertSee('Solde non lettre:', $response);
            $this->assertSee('Tolerance:', $response);
        }
    }

    function testLetterPageShowsUnletteredLines()
    {
        $selectResponse = $this->get('/modules/letters/select.php');
        $accountId = $this->extractFromBody('/letter\.php\?account_id=(\d+)/', $selectResponse);

        if ($accountId) {
            $response = $this->get('/modules/letters/letter.php?account_id=' . $accountId);

            $this->assertSee('Lignes a lettrer', $response);
            $this->assertSee('Date', $response);
            $this->assertSee('Piece', $response);
            $this->assertSee('Debit', $response);
            $this->assertSee('Credit', $response);
            $this->assertSee('Disponible', $response);
        }
    }

    function testLetterPageHasAutoSuggestButton()
    {
        $selectResponse = $this->get('/modules/letters/select.php');
        $accountId = $this->extractFromBody('/letter\.php\?account_id=(\d+)/', $selectResponse);

        if ($accountId) {
            $response = $this->get('/modules/letters/letter.php?account_id=' . $accountId);

            $this->assertSee('Suggestion automatique', $response);
            $this->assertSee('autoSuggest()', $response);
        }
    }

    function testLetterPageHasSelectionSummary()
    {
        $selectResponse = $this->get('/modules/letters/select.php');
        $accountId = $this->extractFromBody('/letter\.php\?account_id=(\d+)/', $selectResponse);

        if ($accountId) {
            $response = $this->get('/modules/letters/letter.php?account_id=' . $accountId);

            $this->assertSee('Selection:', $response);
            $this->assertSee('Ecart:', $response);
            $this->assertSee('Creer le lettrage', $response);
        }
    }

    function testLetterPageShowsExistingLetterings()
    {
        $selectResponse = $this->get('/modules/letters/select.php');
        $accountId = $this->extractFromBody('/letter\.php\?account_id=(\d+)/', $selectResponse);

        if ($accountId) {
            $response = $this->get('/modules/letters/letter.php?account_id=' . $accountId);

            $this->assertSee('Lettrages existants', $response);
        }
    }

    function testLetterPageHasAmountInputs()
    {
        $selectResponse = $this->get('/modules/letters/select.php');
        $accountId = $this->extractFromBody('/letter\.php\?account_id=(\d+)/', $selectResponse);

        if ($accountId) {
            $response = $this->get('/modules/letters/letter.php?account_id=' . $accountId);

            // Should have amount inputs for partial lettering
            $this->assertSee('Montant a lettrer', $response);
        }
    }

    function testLetterPageHasCheckboxes()
    {
        $selectResponse = $this->get('/modules/letters/select.php');
        $accountId = $this->extractFromBody('/letter\.php\?account_id=(\d+)/', $selectResponse);

        if ($accountId) {
            $response = $this->get('/modules/letters/letter.php?account_id=' . $accountId);

            $this->assertSee('line-checkbox', $response);
            $this->assertSee('select-all', $response);
        }
    }

    function testLetterPageHasReturnLink()
    {
        $selectResponse = $this->get('/modules/letters/select.php');
        $accountId = $this->extractFromBody('/letter\.php\?account_id=(\d+)/', $selectResponse);

        if ($accountId) {
            $response = $this->get('/modules/letters/letter.php?account_id=' . $accountId);

            $this->assertSee('Retour a la selection', $response);
            $this->assertSee('select.php', $response);
        }
    }

    function testLetterPageHasHistoryLink()
    {
        $selectResponse = $this->get('/modules/letters/select.php');
        $accountId = $this->extractFromBody('/letter\.php\?account_id=(\d+)/', $selectResponse);

        if ($accountId) {
            $response = $this->get('/modules/letters/letter.php?account_id=' . $accountId);

            $this->assertSee('Historique complet', $response);
        }
    }

    function testLetterPageRedirectsForInvalidAccount()
    {
        $response = $this->get('/modules/letters/letter.php?account_id=0');

        // Should redirect to select page
        $this->assertContains($response['code'], array(302, 303));
        $this->assertStringContainsString('select.php', $response['location']);
    }

    function testLetterPageShowsEntryLines()
    {
        $selectResponse = $this->get('/modules/letters/select.php');
        $accountId = $this->extractFromBody('/letter\.php\?account_id=(\d+)/', $selectResponse);

        if ($accountId) {
            $response = $this->get('/modules/letters/letter.php?account_id=' . $accountId);

            // Should show entry lines for the account
            $this->assertSee('Date', $response);
            $this->assertSee('Debit', $response);
            $this->assertSee('Credit', $response);
        }
    }

    function testLetterPageHasForm()
    {
        $selectResponse = $this->get('/modules/letters/select.php');
        $accountId = $this->extractFromBody('/letter\.php\?account_id=(\d+)/', $selectResponse);

        if ($accountId) {
            $response = $this->get('/modules/letters/letter.php?account_id=' . $accountId);
            $this->assertHasForm($response);
        }
    }

    function testLetterPageShowsBalance()
    {
        $selectResponse = $this->get('/modules/letters/select.php');
        $accountId = $this->extractFromBody('/letter\.php\?account_id=(\d+)/', $selectResponse);

        if ($accountId) {
            $response = $this->get('/modules/letters/letter.php?account_id=' . $accountId);

            // Should show selection balance
            $this->assertSee('Selection', $response);
        }
    }

    // ==================== History Page ====================

    function testHistoryPageLoads()
    {
        $response = $this->get('/modules/letters/history.php');

        $this->assertOk($response);
        $this->assertSee('Historique des lettrages', $response);
    }

    function testHistoryPageHasFilters()
    {
        $response = $this->get('/modules/letters/history.php');

        $this->assertSee('Compte:', $response);
        $this->assertSee('Du:', $response);
        $this->assertSee('Au:', $response);
        $this->assertSee('Filtrer', $response);
    }

    function testHistoryPageShowsTable()
    {
        $response = $this->get('/modules/letters/history.php');

        $this->assertSee('Code', $response);
        $this->assertSee('Date', $response);
        $this->assertSee('Compte', $response);
        $this->assertSee('Lignes', $response);
        $this->assertSee('Debit', $response);
        $this->assertSee('Credit', $response);
        $this->assertSee('Type', $response);
    }

    function testHistoryPageFilterByAccount()
    {
        // Get an account with letterings
        $selectResponse = $this->get('/modules/letters/select.php');
        $accountId = $this->extractFromBody('/history\.php\?account_id=(\d+)/', $selectResponse);

        if ($accountId) {
            $response = $this->get('/modules/letters/history.php?account_id=' . $accountId);
            $this->assertOk($response);
        }
    }

    function testHistoryPageHasReturnLink()
    {
        $response = $this->get('/modules/letters/history.php');

        $this->assertSee('Retour a la selection', $response);
    }

    // ==================== AJAX Suggestion ====================

    function testAjaxSuggestReturnsJson()
    {
        $selectResponse = $this->get('/modules/letters/select.php');
        $accountId = $this->extractFromBody('/letter\.php\?account_id=(\d+)/', $selectResponse);

        if ($accountId) {
            $response = $this->get('/modules/letters/ajax_suggest.php?account_id=' . $accountId);

            $this->assertOk($response);

            // Should be valid JSON
            $data = json_decode($response['body'], true);
            $this->assertNotNull($data, 'Response should be valid JSON');
            $this->assertArrayHasKey('success', $data);
        }
    }

    function testAjaxSuggestRequiresAccountId()
    {
        $response = $this->get('/modules/letters/ajax_suggest.php');

        $this->assertOk($response);
        $data = json_decode($response['body'], true);
        $this->assertFalse($data['success']);
        $this->assertArrayHasKey('error', $data);
    }

    function testAjaxSuggestReturnsSuggestions()
    {
        $selectResponse = $this->get('/modules/letters/select.php');
        $accountId = $this->extractFromBody('/letter\.php\?account_id=(\d+)/', $selectResponse);

        if ($accountId) {
            $response = $this->get('/modules/letters/ajax_suggest.php?account_id=' . $accountId);

            $data = json_decode($response['body'], true);
            $this->assertTrue($data['success']);
            $this->assertArrayHasKey('suggestions', $data);
            $this->assertArrayHasKey('tolerance', $data);
        }
    }

    // ==================== Lettering Creation ====================

    function testCreateLetteringRequiresCsrf()
    {
        $selectResponse = $this->get('/modules/letters/select.php');
        $accountId = $this->extractFromBody('/letter\.php\?account_id=(\d+)/', $selectResponse);

        if ($accountId) {
            // Try to POST without CSRF
            $this->csrfToken = null;
            $response = $this->request('POST', '/modules/letters/letter.php?account_id=' . $accountId, array(
                'action' => 'create_lettering',
                'lines' => array(1, 2)
            ));

            // Should fail or redirect (CSRF protection)
            $this->assertNotEquals(200, $response['code']);
        }
    }

    function testCreateLetteringRequiresMinTwoLines()
    {
        $selectResponse = $this->get('/modules/letters/select.php');
        $accountId = $this->extractFromBody('/letter\.php\?account_id=(\d+)/', $selectResponse);

        if ($accountId) {
            // Get the letter page to get CSRF token
            $letterResponse = $this->get('/modules/letters/letter.php?account_id=' . $accountId);

            // Try to create lettering with only 1 line
            $response = $this->post('/modules/letters/letter.php?account_id=' . $accountId, array(
                'action' => 'create_lettering',
                'lines' => array(1)
            ));

            // Should redirect with error
            $response = $this->followRedirect($response);
            $this->assertSee('au moins 2 lignes', $response);
        }
    }

    // ==================== Access Control ====================

    function testViewerCannotAccessLettering()
    {
        $this->cookies = array();
        $this->loginAs('lecteur');

        $response = $this->get('/modules/letters/select.php');

        // Viewer gets redirected (no access - requires accountant role)
        $this->assertContains($response['code'], array(302, 403));
    }

    function testAccountantCanAccessLettering()
    {
        $this->cookies = array();
        $this->loginAs('comptable');

        $response = $this->get('/modules/letters/select.php');

        $this->assertOk($response);
        $this->assertSee('Lettrage', $response);
    }

    function testAdminCanAccessLettering()
    {
        $this->cookies = array();
        $this->loginAs('admin');

        $response = $this->get('/modules/letters/select.php');

        $this->assertOk($response);
        $this->assertSee('Lettrage', $response);
    }

    // ==================== JavaScript Integration ====================

    function testLetterPageHasJavaScript()
    {
        $selectResponse = $this->get('/modules/letters/select.php');
        $accountId = $this->extractFromBody('/letter\.php\?account_id=(\d+)/', $selectResponse);

        if ($accountId) {
            $response = $this->get('/modules/letters/letter.php?account_id=' . $accountId);

            // Should have inline JavaScript for lettering
            $this->assertSee('updateSelection()', $response);
            $this->assertSee('clearSelection()', $response);
            $this->assertSee('autoSuggest()', $response);
        }
    }

    function testLetterPageHasToleranceVariable()
    {
        $selectResponse = $this->get('/modules/letters/select.php');
        $accountId = $this->extractFromBody('/letter\.php\?account_id=(\d+)/', $selectResponse);

        if ($accountId) {
            $response = $this->get('/modules/letters/letter.php?account_id=' . $accountId);

            // Should have tolerance variable in JavaScript
            $this->assertSee('var tolerance =', $response);
        }
    }

    // ==================== Edge Cases ====================

    function testSelectPageWithShowAllFilter()
    {
        $response = $this->get('/modules/letters/select.php?show_all=1');

        $this->assertOk($response);
        $this->assertSee('Afficher les comptes soldes', $response);
    }
}
