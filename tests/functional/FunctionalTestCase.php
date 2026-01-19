<?php
/**
 * Base class for functional tests with HTTP helpers
 *
 * Style: PHP 5.1 / 2006 era
 */

require_once dirname(__FILE__) . '/../bootstrap.php';

class FunctionalTestCase extends PHPUnit\Framework\TestCase
{
    var $baseUrl;
    var $cookies;
    var $csrfToken;

    protected function setUp(): void
    {
        $this->baseUrl = getenv('APP_URL') ? getenv('APP_URL') : 'http://localhost:8080';
        $this->cookies = array();
        $this->csrfToken = null;
    }

    /**
     * Skip test if app is not running
     */
    function requireApp()
    {
        static $running = null;
        if ($running === null) {
            $ch = curl_init($this->baseUrl . '/login.php');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $running = ($code === 200);
        }
        if (!$running) {
            $this->markTestSkipped('Application not running');
        }
    }

    /**
     * Make HTTP request and return response
     */
    function request($method, $url, $data = array())
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

        if (count($this->cookies) > 0) {
            $cookie_str = '';
            foreach ($this->cookies as $k => $v) {
                $cookie_str .= $k . '=' . $v . '; ';
            }
            curl_setopt($ch, CURLOPT_COOKIE, rtrim($cookie_str, '; '));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        // Extract and store cookies
        preg_match_all('/Set-Cookie:\s*([^;]+)/', $headers, $matches);
        foreach ($matches[1] as $cookie) {
            $parts = explode('=', $cookie, 2);
            $this->cookies[$parts[0]] = $parts[1];
        }

        // Extract Location header
        $location = null;
        if (preg_match('/Location:\s*(.+)/i', $headers, $match)) {
            $location = trim($match[1]);
        }

        // Extract CSRF token if present
        if (preg_match('/name="_csrf"\s+value="([^"]+)"/', $body, $match)) {
            $this->csrfToken = $match[1];
        }

        return array(
            'code' => $httpCode,
            'body' => $body,
            'headers' => $headers,
            'location' => $location
        );
    }

    /**
     * Shorthand for GET request
     */
    function get($url)
    {
        return $this->request('GET', $url);
    }

    /**
     * Shorthand for POST request (auto-includes CSRF token)
     */
    function post($url, $data = array())
    {
        if ($this->csrfToken && !isset($data['_csrf'])) {
            $data['_csrf'] = $this->csrfToken;
        }
        return $this->request('POST', $url, $data);
    }

    /**
     * Login as user and maintain session
     */
    function loginAs($username, $password = null)
    {
        $passwords = array(
            'admin' => 'admin123',
            'comptable' => 'comptable123',
            'lecteur' => 'lecteur123'
        );
        if ($password === null) {
            $password = isset($passwords[$username]) ? $passwords[$username] : $username;
        }

        $this->get('/login.php');
        $response = $this->post('/login.php', array(
            'username' => $username,
            'password' => $password
        ));

        $this->assertRedirectTo('/index.php', $response, 'Login failed for ' . $username);
    }

    /**
     * Follow redirect and return response
     */
    function followRedirect($response)
    {
        $this->assertNotNull($response['location'], 'No redirect location');
        $url = parse_url($response['location'], PHP_URL_PATH);
        $query = parse_url($response['location'], PHP_URL_QUERY);
        if ($query) {
            $url .= '?' . $query;
        }
        return $this->get($url);
    }

    /**
     * Assert response redirects to URL
     */
    function assertRedirectTo($expected, $response, $message = '')
    {
        if ($message === '') {
            $message = 'Expected redirect';
        }
        $this->assertContains($response['code'], array(302, 303), $message);
        $location = isset($response['location']) ? $response['location'] : '';
        $this->assertStringContainsString($expected, $location, $message);
    }

    /**
     * Assert response is OK (200)
     */
    function assertOk($response, $message = '')
    {
        if ($message === '') {
            $message = 'Expected 200 OK';
        }
        $this->assertEquals(200, $response['code'], $message);
    }

    /**
     * Assert body contains string
     */
    function assertSee($needle, $response, $message = '')
    {
        if ($message === '') {
            $message = 'Expected to see: ' . $needle;
        }
        $this->assertStringContainsString($needle, $response['body'], $message);
    }

    /**
     * Assert body does not contain string
     */
    function assertDontSee($needle, $response, $message = '')
    {
        if ($message === '') {
            $message = 'Expected NOT to see: ' . $needle;
        }
        $this->assertStringNotContainsString($needle, $response['body'], $message);
    }

    /**
     * Assert page has form
     */
    function assertHasForm($response, $action = null)
    {
        $this->assertSee('<form', $response);
        if ($action !== null) {
            $this->assertSee('action="' . $action . '"', $response);
        }
    }

    /**
     * Assert page title
     */
    function assertTitle($title, $response)
    {
        $this->assertSee('<title>' . $title, $response);
    }

    /**
     * Assert flash message
     */
    function assertFlash($type, $message, $response)
    {
        $this->assertSee('class="flash ' . $type . '"', $response);
        $this->assertSee($message, $response);
    }

    /**
     * Extract value from HTML by regex pattern
     */
    function extractFromBody($pattern, $response, $group = 1)
    {
        if (preg_match($pattern, $response['body'], $match)) {
            return $match[$group];
        }
        return null;
    }

    /**
     * Extract all matches from HTML
     */
    function extractAllFromBody($pattern, $response, $group = 1)
    {
        preg_match_all($pattern, $response['body'], $matches);
        return isset($matches[$group]) ? $matches[$group] : array();
    }

    /**
     * POST with file upload
     */
    function postWithFile($url, $data, $fileField, $filePath)
    {
        if ($this->csrfToken && !isset($data['_csrf'])) {
            $data['_csrf'] = $this->csrfToken;
        }

        $ch = curl_init($this->baseUrl . $url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_POST, true);

        // Add file to data
        $data[$fileField] = new CURLFile($filePath, 'text/csv', basename($filePath));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        if (count($this->cookies) > 0) {
            $cookie_str = '';
            foreach ($this->cookies as $k => $v) {
                $cookie_str .= $k . '=' . $v . '; ';
            }
            curl_setopt($ch, CURLOPT_COOKIE, rtrim($cookie_str, '; '));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        // Extract and store cookies
        preg_match_all('/Set-Cookie:\s*([^;]+)/', $headers, $matches);
        foreach ($matches[1] as $cookie) {
            $parts = explode('=', $cookie, 2);
            $this->cookies[$parts[0]] = $parts[1];
        }

        // Extract Location header
        $location = null;
        if (preg_match('/Location:\s*(.+)/i', $headers, $match)) {
            $location = trim($match[1]);
        }

        // Extract CSRF token if present
        if (preg_match('/name="_csrf"\s+value="([^"]+)"/', $body, $match)) {
            $this->csrfToken = $match[1];
        }

        return array(
            'code' => $httpCode,
            'body' => $body,
            'headers' => $headers,
            'location' => $location
        );
    }

    /**
     * Get path to test fixture file
     */
    function fixturePath($filename)
    {
        return dirname(__FILE__) . '/../fixtures/' . $filename;
    }
}
