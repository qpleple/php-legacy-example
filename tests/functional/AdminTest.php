<?php
/**
 * Functional tests for Admin module (User Management)
 */

require_once dirname(__FILE__) . '/FunctionalTestCase.php';

class AdminTest extends FunctionalTestCase
{
    function setUp(): void
    {
        parent::setUp();
        $this->requireApp();
        $this->loginAs('admin');
    }

    // ==================== Users Page ====================

    function testUsersPageLoads()
    {
        $response = $this->get('/modules/admin/users.php');

        $this->assertOk($response);
        $this->assertSee('Utilisateurs', $response);
    }

    function testUsersPageShowsAdmin()
    {
        $response = $this->get('/modules/admin/users.php');

        $this->assertSee('admin', $response);
    }

    function testUsersPageHasCreateForm()
    {
        $response = $this->get('/modules/admin/users.php');

        $this->assertHasForm($response);
        $this->assertSee('name="username"', $response);
        $this->assertSee('name="password"', $response);
    }

    function testUsersPageHasEditButtons()
    {
        $response = $this->get('/modules/admin/users.php');

        $this->assertSee('Modifier', $response);
    }

    function testCreateUser()
    {
        $this->get('/modules/admin/users.php');

        $username = 'testuser' . rand(100, 999);
        $response = $this->post('/modules/admin/users.php', array(
            'action' => 'create',
            'username' => $username,
            'password' => 'test123'
        ));

        $this->assertRedirectTo('/modules/admin/users.php', $response);

        $listResponse = $this->followRedirect($response);
        $this->assertSee($username, $listResponse);
    }
}
