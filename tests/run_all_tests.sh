#!/bin/bash
# Run all test suites
# Unit and Integration tests are run in separate PHP processes to avoid function conflicts

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
PHPUNIT="$PROJECT_DIR/vendor/bin/phpunit"

echo "=== Running Unit Tests ==="
$PHPUNIT --configuration "$SCRIPT_DIR/phpunit.xml" --testsuite Unit
echo ""

echo "=== Running Integration Tests ==="
$PHPUNIT --configuration "$SCRIPT_DIR/phpunit.xml" --testsuite Integration
echo ""

echo "=== Running Functional Tests ==="
$PHPUNIT --configuration "$SCRIPT_DIR/phpunit.xml" --testsuite Functional
echo ""

echo "=== All Tests Complete ==="
