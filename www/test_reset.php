<?php
/**
 * Test database reset endpoint
 * Only works when X-Test-Mode header is present
 * Resets the test database to a clean state with fixtures
 */

// Only allow in test mode
if (!isset($_SERVER['HTTP_X_TEST_MODE']) || $_SERVER['HTTP_X_TEST_MODE'] !== '1') {
    http_response_code(403);
    die(json_encode(['error' => 'Test mode required']));
}

header('Content-Type: application/json');

$base_path = getenv('SQLITE_DB') ?: '/var/www/html/data/compta.db';
$test_db_path = dirname($base_path) . '/compta_test.db';
$sql_dir = '/var/www/sql';

try {
    // Close any existing connection
    global $db_pdo;
    $db_pdo = null;

    // Delete existing test database
    if (file_exists($test_db_path)) {
        unlink($test_db_path);
    }

    // Create new test database
    $pdo = new PDO('sqlite:' . $test_db_path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON');

    // Load SQL files in order
    $sql_files = [
        '01_schema.sql',
        '02_seed.sql',
        '03_triggers.sql',
        '04_lettering_samples.sql'
    ];

    // Check for test-specific fixtures first
    $test_fixtures = $sql_dir . '/test_fixtures.sql';

    foreach ($sql_files as $file) {
        $path = $sql_dir . '/' . $file;
        if (file_exists($path)) {
            $sql = file_get_contents($path);
            $pdo->exec($sql);
        }
    }

    // Load test-specific fixtures if they exist
    if (file_exists($test_fixtures)) {
        $sql = file_get_contents($test_fixtures);
        $pdo->exec($sql);
    }

    // Set permissions
    chmod($test_db_path, 0666);

    echo json_encode([
        'success' => true,
        'message' => 'Test database reset successfully',
        'db_path' => $test_db_path
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
