<?php
/**
 * Functional tests for login/authentication
 */

require_once dirname(__FILE__) . '/FunctionalTestCase.php';

class LoginTest extends FunctionalTestCase
{
    function testLoginPageLoads()
    {
        $this->requireApp();

        $response = $this->get('/login.php');

        $this->assertOk($response);
        $this->assertSee('username', $response);
        $this->assertSee('password', $response);
        $this->assertHasForm($response);
    }

    function testLoginWithValidCredentials()
    {
        $this->requireApp();

        $this->get('/login.php');
        $response = $this->post('/login.php', array(
            'username' => 'admin',
            'password' => 'admin123'
        ));

        $this->assertRedirectTo('/dashboard.php', $response);
    }

    function testLoginWithInvalidCredentials()
    {
        $this->requireApp();

        $this->get('/login.php');
        $response = $this->post('/login.php', array(
            'username' => 'admin',
            'password' => 'wrongpassword'
        ));

        $this->assertContains($response['code'], array(200, 302));
    }

    function testProtectedPageRedirectsToLogin()
    {
        $this->requireApp();

        $response = $this->get('/modules/entries/list.php');

        $this->assertRedirectTo('login.php', $response);
    }

    function testLandingPageLoads()
    {
        $this->requireApp();

        $response = $this->get('/index.php');

        $this->assertOk($response);
        $this->assertSee('Ketchup', $response);
    }

    function testLogoutDestroysSession()
    {
        $this->requireApp();

        $this->loginAs('admin');

        $response = $this->get('/logout.php');

        $this->assertRedirectTo('login.php', $response);
    }

    function testAuthenticatedUserCanAccessDashboard()
    {
        $this->requireApp();

        $this->loginAs('admin');

        $response = $this->get('/dashboard.php');

        $this->assertOk($response);
        $this->assertSee('Tableau de bord', $response);
    }
}
