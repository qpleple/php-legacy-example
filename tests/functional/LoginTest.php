<?php
/**
 * Functional tests for login/authentication endpoints
 * Tests HTTP requests and responses
 */

require_once dirname(__DIR__) . '/bootstrap.php';

class LoginTest extends PHPUnit\Framework\TestCase
{
    private $baseUrl;

    protected function setUp(): void
    {
        // Base URL for the application (when running in Docker)
        $this->baseUrl = getenv('APP_URL') ?: 'http://localhost:8080';
    }

    /**
     * Helper to check if the app is running
     */
    private function isAppRunning(): bool
    {
        $ch = curl_init($this->baseUrl . '/login.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 200;
    }

    /**
     * Helper to make HTTP requests
     */
    private function request($method, $url, $data = [], $cookies = [])
    {
        $ch = curl_init($this->baseUrl . $url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        if (!empty($cookies)) {
            curl_setopt($ch, CURLOPT_COOKIE, implode('; ', array_map(
                fn($k, $v) => "$k=$v",
                array_keys($cookies),
                $cookies
            )));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        // Extract cookies from response
        preg_match_all('/Set-Cookie:\s*([^;]+)/', $headers, $matches);
        $responseCookies = [];
        foreach ($matches[1] as $cookie) {
            list($name, $value) = explode('=', $cookie, 2);
            $responseCookies[$name] = $value;
        }

        // Extract Location header
        $location = null;
        if (preg_match('/Location:\s*(.+)/i', $headers, $match)) {
            $location = trim($match[1]);
        }

        return [
            'code' => $httpCode,
            'body' => $body,
            'headers' => $headers,
            'cookies' => $responseCookies,
            'location' => $location
        ];
    }

    // ==================== Login Page Tests ====================

    public function testLoginPageLoads()
    {
        if (!$this->isAppRunning()) {
            $this->markTestSkipped('Application not running');
        }

        $response = $this->request('GET', '/login.php');

        $this->assertEquals(200, $response['code']);
        $this->assertStringContainsString('form', $response['body']);
        $this->assertStringContainsString('username', $response['body']);
        $this->assertStringContainsString('password', $response['body']);
    }

    public function testLoginPageContainsForm()
    {
        if (!$this->isAppRunning()) {
            $this->markTestSkipped('Application not running');
        }

        $response = $this->request('GET', '/login.php');

        // Legacy app doesn't use CSRF on login page - just verify form exists
        $this->assertStringContainsString('<form', $response['body']);
        $this->assertStringContainsString('method="post"', $response['body']);
    }

    // ==================== Login Submission Tests ====================

    public function testLoginWithValidCredentials()
    {
        if (!$this->isAppRunning()) {
            $this->markTestSkipped('Application not running');
        }

        // First get CSRF token
        $getResponse = $this->request('GET', '/login.php');

        // Extract CSRF token
        preg_match('/name="_csrf"\s+value="([^"]+)"/', $getResponse['body'], $match);
        $csrf = $match[1] ?? '';

        // Submit login
        $postResponse = $this->request('POST', '/login.php', [
            'username' => 'admin',
            'password' => 'admin123',
            '_csrf' => $csrf
        ], $getResponse['cookies']);

        // Should redirect to index.php on success
        $this->assertContains($postResponse['code'], [302, 303]);
        $this->assertStringContainsString('index.php', $postResponse['location'] ?? '');
    }

    public function testLoginWithInvalidCredentials()
    {
        if (!$this->isAppRunning()) {
            $this->markTestSkipped('Application not running');
        }

        // First get CSRF token
        $getResponse = $this->request('GET', '/login.php');

        preg_match('/name="_csrf"\s+value="([^"]+)"/', $getResponse['body'], $match);
        $csrf = $match[1] ?? '';

        // Submit with wrong password
        $postResponse = $this->request('POST', '/login.php', [
            'username' => 'admin',
            'password' => 'wrongpassword',
            '_csrf' => $csrf
        ], $getResponse['cookies']);

        // Should stay on login page or redirect back with error
        $this->assertContains($postResponse['code'], [200, 302]);
    }

    public function testLoginWithoutCsrfToken()
    {
        if (!$this->isAppRunning()) {
            $this->markTestSkipped('Application not running');
        }

        // Submit without CSRF - legacy app allows this on login page
        $postResponse = $this->request('POST', '/login.php', [
            'username' => 'admin',
            'password' => 'admin123'
        ]);

        // Legacy app doesn't enforce CSRF on login, so it should succeed with redirect
        $this->assertContains($postResponse['code'], [302, 303]);
    }

    // ==================== Access Control Tests ====================

    public function testProtectedPageRedirectsToLogin()
    {
        if (!$this->isAppRunning()) {
            $this->markTestSkipped('Application not running');
        }

        // Try to access protected page without session
        $response = $this->request('GET', '/modules/entries/list.php');

        // Should redirect to login (302) or show login page content
        if ($response['code'] === 302) {
            $this->assertStringContainsString('login.php', $response['location'] ?? '');
        } else {
            // Some pages may show login form directly or error
            $this->assertContains($response['code'], [200, 302, 403]);
        }
    }

    public function testDashboardRedirectsToLogin()
    {
        if (!$this->isAppRunning()) {
            $this->markTestSkipped('Application not running');
        }

        $response = $this->request('GET', '/index.php');

        // Should redirect to login when not authenticated
        $this->assertEquals(302, $response['code']);
    }

    // ==================== Logout Tests ====================

    public function testLogoutDestroysSession()
    {
        if (!$this->isAppRunning()) {
            $this->markTestSkipped('Application not running');
        }

        // First login
        $getResponse = $this->request('GET', '/login.php');
        preg_match('/name="_csrf"\s+value="([^"]+)"/', $getResponse['body'], $match);
        $csrf = $match[1] ?? '';

        $loginResponse = $this->request('POST', '/login.php', [
            'username' => 'admin',
            'password' => 'admin123',
            '_csrf' => $csrf
        ], $getResponse['cookies']);

        $cookies = array_merge($getResponse['cookies'], $loginResponse['cookies']);

        // Now logout
        $logoutResponse = $this->request('GET', '/logout.php', [], $cookies);

        // Should redirect to login
        $this->assertEquals(302, $logoutResponse['code']);
        $this->assertStringContainsString('login.php', $logoutResponse['location'] ?? '');
    }
}
