<?php
/**
 * Functional tests for Entries module
 */

require_once dirname(__FILE__) . '/FunctionalTestCase.php';

class EntriesTest extends FunctionalTestCase
{
    function setUp(): void
    {
        parent::setUp();
        $this->requireApp();
        $this->loginAs('comptable');
    }

    // ==================== List Page ====================

    function testEntriesListPageLoads()
    {
        $response = $this->get('/modules/entries/list.php');

        $this->assertOk($response);
        $this->assertTitle('Liste des Pieces', $response);
        $this->assertSee('Liste des Pieces Comptables', $response);
    }

    function testEntriesListShowsEntries()
    {
        $response = $this->get('/modules/entries/list.php');

        $this->assertSee('VE2026-000001', $response);
        $this->assertSee('OD2026-000001', $response);
    }

    function testEntriesListShowsDraftEntry()
    {
        $response = $this->get('/modules/entries/list.php?status=draft');

        $this->assertSee('Brouillon', $response);
        $this->assertSee('(brouillon)', $response);
    }

    function testEntriesListFilterByJournal()
    {
        $response = $this->get('/modules/entries/list.php?journal_id=1');

        $this->assertOk($response);
        $this->assertSee('VE2026-', $response);
        $this->assertDontSee('AC2026-', $response);
        $this->assertDontSee('BK2026-', $response);
    }

    function testEntriesListFilterByStatus()
    {
        $response = $this->get('/modules/entries/list.php?status=posted');

        $this->assertOk($response);
        $this->assertSee('Valide', $response);
        $this->assertDontSee('Facture en attente validation', $response);
    }

    function testEntriesListHasNewEntryButton()
    {
        $response = $this->get('/modules/entries/list.php');

        $this->assertSee('href="/modules/entries/edit.php"', $response);
        $this->assertSee('Nouvelle piece', $response);
    }

    // ==================== Access Control ====================

    function testViewerCannotAccessEntriesList()
    {
        $this->cookies = array();
        $this->loginAs('lecteur');

        $response = $this->get('/modules/entries/list.php');

        $this->assertContains($response['code'], array(302, 403));
    }

    // ==================== Duplicate Action ====================

    function testDuplicateEntryCreatesNewDraft()
    {
        $this->get('/modules/entries/list.php');

        $response = $this->post('/modules/entries/list.php', array(
            'action' => 'duplicate',
            'id' => 2
        ));

        $this->assertRedirectTo('/modules/entries/edit.php?id=', $response);
    }

    // ==================== Delete Draft ====================

    function testDeleteDraftEntry()
    {
        // Create draft by duplicating
        $this->get('/modules/entries/list.php');
        $dupResponse = $this->post('/modules/entries/list.php', array(
            'action' => 'duplicate',
            'id' => 2
        ));

        // Extract new entry ID
        preg_match('/id=(\d+)/', $dupResponse['location'], $match);
        $newId = $match[1];

        // Delete the draft
        $this->get('/modules/entries/list.php');
        $response = $this->post('/modules/entries/list.php', array(
            'action' => 'delete',
            'id' => $newId
        ));

        $this->assertRedirectTo('/modules/entries/list.php', $response);

        $listResponse = $this->followRedirect($response);
        $this->assertSee('Brouillon supprime', $listResponse);
    }

    function testCannotDeletePostedEntry()
    {
        $this->get('/modules/entries/list.php');

        $response = $this->post('/modules/entries/list.php', array(
            'action' => 'delete',
            'id' => 2
        ));

        $listResponse = $this->followRedirect($response);
        $this->assertSee('Impossible de supprimer une piece validee', $listResponse);
    }

    // ==================== Edit Page ====================

    function testNewEntryPageLoads()
    {
        $response = $this->get('/modules/entries/edit.php');

        $this->assertOk($response);
        $this->assertSee('Saisie Ecriture', $response);
        $this->assertHasForm($response);
    }

    function testViewPostedEntry()
    {
        $response = $this->get('/modules/entries/edit.php?id=2');

        $this->assertOk($response);
        $this->assertSee('VE2026-000001', $response);
        $this->assertSee('Facture FA-2026-001 Client Dupont', $response);
    }

    function testEditDraftEntry()
    {
        $response = $this->get('/modules/entries/edit.php?id=12');

        $this->assertOk($response);
        $this->assertSee('Facture en attente validation', $response);
        $this->assertSee('Enregistrer', $response);
    }

    // ==================== Import Page ====================

    function testImportPageLoads()
    {
        $response = $this->get('/modules/entries/import.php');

        $this->assertOk($response);
        $this->assertSee('Import CSV Ecritures', $response);
    }

    function testImportPageHasForm()
    {
        $response = $this->get('/modules/entries/import.php');

        $this->assertHasForm($response);
        $this->assertSee('name="csv_file"', $response);
        $this->assertSee('enctype="multipart/form-data"', $response);
    }

    function testImportPageShowsFormatInfo()
    {
        $response = $this->get('/modules/entries/import.php');

        $this->assertSee('Format du fichier CSV', $response);
        $this->assertSee('journal_code', $response);
        $this->assertSee('account_code', $response);
    }

    function testImportCsvCreatesEntries()
    {
        $this->get('/modules/entries/import.php');

        $response = $this->postWithFile(
            '/modules/entries/import.php',
            array(),
            'csv_file',
            $this->fixturePath('entries_import.csv')
        );

        $this->assertOk($response);
        $this->assertSee('Resultat de l\'import', $response);
        $this->assertSee('Pieces creees', $response);
    }

    function testImportCsvShowsCreatedCount()
    {
        $this->get('/modules/entries/import.php');

        $response = $this->postWithFile(
            '/modules/entries/import.php',
            array(),
            'csv_file',
            $this->fixturePath('entries_import.csv')
        );

        // Should create 1 entry (3 lines grouped by piece_ref)
        $this->assertSee('Pieces creees:</strong> 1', $response);
    }

    function testImportedEntryAppearsInList()
    {
        $this->get('/modules/entries/import.php');

        $this->postWithFile(
            '/modules/entries/import.php',
            array(),
            'csv_file',
            $this->fixturePath('entries_import.csv')
        );

        $listResponse = $this->get('/modules/entries/list.php?status=draft');
        $this->assertSee('Facture Client Import', $listResponse);
    }

    function testViewerCannotAccessImport()
    {
        $this->cookies = array();
        $this->loginAs('lecteur');

        $response = $this->get('/modules/entries/import.php');

        $this->assertContains($response['code'], array(302, 403));
    }
}
