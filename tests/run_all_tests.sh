#!/bin/bash
# Run all test suites in separate PHP processes to avoid function conflicts

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PHPUNIT="$SCRIPT_DIR/vendor/bin/phpunit"

# Install dependencies if needed
if [ ! -f "$PHPUNIT" ]; then
    echo "Installing test dependencies..."
    composer install --working-dir="$SCRIPT_DIR"
    echo ""
fi

echo "=== Running Unit Tests ==="
$PHPUNIT --configuration "$SCRIPT_DIR/phpunit.xml" --testsuite Unit
echo ""

echo "=== Running Functional Tests ==="
$PHPUNIT --configuration "$SCRIPT_DIR/phpunit.xml" --testsuite Functional
echo ""

echo "=== All Tests Complete ==="
