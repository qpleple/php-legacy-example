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
        $this->loginAs('admin');
    }

    // ==================== List Page ====================

    function testEntriesListPageLoads()
    {
        $response = $this->get('/modules/entries/list.php');

        $this->assertOk($response);
        $this->assertSee('critures', $response);
    }

    function testEntriesListShowsEntries()
    {
        $response = $this->get('/modules/entries/list.php');

        $this->assertSee('VE2026-', $response);
    }

    function testEntriesListFilterByJournal()
    {
        $response = $this->get('/modules/entries/list.php?journal_id=1');

        $this->assertOk($response);
        $this->assertSee('VE2026-', $response);
    }

    function testEntriesListHasNewEntryButton()
    {
        $response = $this->get('/modules/entries/list.php');

        $this->assertSee('href="/modules/entries/edit.php"', $response);
    }

    // ==================== Edit Page ====================

    function testNewEntryPageLoads()
    {
        $response = $this->get('/modules/entries/edit.php');

        $this->assertOk($response);
        $this->assertSee('criture', $response);
        $this->assertHasForm($response);
    }

    function testViewPostedEntry()
    {
        $response = $this->get('/modules/entries/edit.php?id=2');

        $this->assertOk($response);
        $this->assertSee('VE2026-000001', $response);
    }
}
