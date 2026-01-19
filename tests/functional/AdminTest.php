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

    function testUsersPageShowsUsers()
    {
        $response = $this->get('/modules/admin/users.php');

        $this->assertSee('admin', $response);
        $this->assertSee('comptable', $response);
        $this->assertSee('lecteur', $response);
    }

    function testUsersPageShowsRoles()
    {
        $response = $this->get('/modules/admin/users.php');

        // Shows role types
        $body = $response['body'];
        $hasRoles = (strpos($body, 'admin') !== false) &&
                   (strpos($body, 'accountant') !== false);
        $this->assertTrue($hasRoles, 'Expected user roles');
    }

    function testUsersPageHasCreateForm()
    {
        $response = $this->get('/modules/admin/users.php');

        $this->assertHasForm($response);
        $this->assertSee('name="username"', $response);
        $this->assertSee('name="password"', $response);
        $this->assertSee('name="role"', $response);
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
            'password' => 'test123',
            'role' => 'viewer'
        ));

        $this->assertRedirectTo('/modules/admin/users.php', $response);

        $listResponse = $this->followRedirect($response);
        $this->assertSee($username, $listResponse);
    }

    function testCannotCreateDuplicateUser()
    {
        $this->get('/modules/admin/users.php');

        $response = $this->post('/modules/admin/users.php', array(
            'action' => 'create',
            'username' => 'admin',
            'password' => 'test123',
            'role' => 'admin'
        ));

        // Returns 200 with error (no redirect on validation error)
        $this->assertOk($response);
        $this->assertSee('existe deja', $response);
    }

    function testCannotCreateUserWithoutPassword()
    {
        $this->get('/modules/admin/users.php');

        $response = $this->post('/modules/admin/users.php', array(
            'action' => 'create',
            'username' => 'newuser',
            'password' => '',
            'role' => 'viewer'
        ));

        $this->assertOk($response);
        $this->assertSee('mot de passe est obligatoire', $response);
    }

    // ==================== Access Control ====================

    function testAccountantCannotAccessAdmin()
    {
        $this->cookies = array();
        $this->loginAs('comptable');

        $response = $this->get('/modules/admin/users.php');

        $this->assertContains($response['code'], array(302, 403));
    }

    function testViewerCannotAccessAdmin()
    {
        $this->cookies = array();
        $this->loginAs('lecteur');

        $response = $this->get('/modules/admin/users.php');

        $this->assertContains($response['code'], array(302, 403));
    }

    // ==================== Self-Protection ====================

    function testCannotDeleteOwnAccount()
    {
        $this->get('/modules/admin/users.php');

        // Admin user is id=1
        $response = $this->post('/modules/admin/users.php', array(
            'action' => 'delete',
            'id' => 1
        ));

        $listResponse = $this->followRedirect($response);
        $this->assertSee('supprimer votre propre compte', $listResponse);
    }
}
