#!/bin/bash
set -euo pipefail

echo "=== ProcessWireMcp Test Runner ==="

# Step 1: Install ProcessWire with default profile via wirecli
if [ ! -f /var/www/html/wire/core/ProcessWire.php ]; then
    echo "[1/4] Installing ProcessWire (default profile)..."

    wirecli new /var/www/html \
        --dbUser="$PW_DB_USER" \
        --dbPass="$PW_DB_PASS" \
        --dbName="$PW_DB_NAME" \
        --dbHost="$PW_DB_HOST" \
        --dbPort="$PW_DB_PORT" \
        --username="$PW_ADMIN_USER" \
        --userpass="$PW_ADMIN_PASS" \
        --useremail="admin@test.local" \
        --httpHosts=localhost \
        --timezone=UTC
else
    echo "[1/4] ProcessWire already installed, skipping"
fi

# Step 2: Create composer.json with path repository for the package
echo "[2/4] Setting up Composer..."
cat > /var/www/html/composer.json << 'EOF'
{
    "require": {
        "elabx/processwire-mcp": "@dev"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.0"
    },
    "autoload-dev": {
        "psr-4": {
            "Elabx\\ProcessWireMcp\\Tests\\": "vendor/elabx/processwire-mcp/tests/"
        }
    },
    "repositories": [
        {
            "type": "path",
            "url": "/package",
            "options": { "symlink": true }
        }
    ],
    "minimum-stability": "dev",
    "prefer-stable": true
}
EOF

# Step 3: Install dependencies
echo "[3/4] Running composer install..."
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction --prefer-dist --quiet \
    --working-dir=/var/www/html

# Step 4: Run PHPUnit
echo "[4/4] Running tests..."
# ProcessWire's shutdown handler can override PHPUnit's exit code (exit 255),
# so we capture the output and derive the result from PHPUnit's summary line.
set +e
/var/www/html/vendor/bin/phpunit \
    --configuration /var/www/html/vendor/elabx/processwire-mcp/phpunit.xml \
    "$@" 2>&1 | tee /tmp/phpunit-output.txt
set -e

# PHPUnit prints "OK (...)" on success — trust that over the PHP exit code.
# Strip ANSI color codes first since phpunit.xml has colors="true" which
# adds escape sequences even when output is piped (e.g., \e[30;42mOK...).
if sed 's/\x1b\[[0-9;]*m//g' /tmp/phpunit-output.txt | grep -q "^OK "; then
    exit 0
fi
exit 1
