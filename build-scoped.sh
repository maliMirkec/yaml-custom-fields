#!/bin/bash

# PHP-Scoper Build Script for YAML Custom Fields
# This script scopes the vendor dependencies to avoid conflicts with other plugins

set -e  # Exit on error

echo "🔧 YAML Custom Fields - Building Scoped Dependencies"
echo "=================================================="

# Define paths
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BUILD_DIR="$PLUGIN_DIR/build"
VENDOR_DIR="$PLUGIN_DIR/vendor"
# Production-only dependencies are installed here and scoped (dev tools such as
# php-scoper's own symfony/console, finder, etc. must never be shipped)
SCOPE_SOURCE_DIR="$PLUGIN_DIR/build-src"
PHP_BIN="${PHP_BIN:-php}"

# Use composer.phar if it exists, otherwise use global composer command
if [ -f "$PLUGIN_DIR/composer.phar" ]; then
    COMPOSER_CMD="$PHP_BIN $PLUGIN_DIR/composer.phar"
else
    COMPOSER_CMD="composer"
fi

# Always remove the temporary production install
trap 'rm -rf "$SCOPE_SOURCE_DIR"' EXIT

# Check if PHP-Scoper is installed
if [ ! -f "$VENDOR_DIR/bin/php-scoper" ]; then
    echo "❌ Error: PHP-Scoper not found. Run 'composer install' first."
    exit 1
fi

# Clean up previous build
echo "🧹 Cleaning previous build..."
if [ -d "$BUILD_DIR" ]; then
    rm -rf "$BUILD_DIR"
fi

# Create build directory
mkdir -p "$BUILD_DIR"

# Install production dependencies only (no dev packages) as the scoping source
echo "📥 Installing production dependencies..."
rm -rf "$SCOPE_SOURCE_DIR"
mkdir -p "$SCOPE_SOURCE_DIR"
cp "$PLUGIN_DIR/composer.json" "$SCOPE_SOURCE_DIR/composer.json"
$COMPOSER_CMD install --working-dir="$SCOPE_SOURCE_DIR" --no-dev --no-scripts --no-interaction --quiet

# Run PHP-Scoper (suppress deprecation warnings for PHP 8.3 compatibility)
echo "🔄 Running PHP-Scoper..."
$PHP_BIN -d error_reporting=E_ALL\&~E_DEPRECATED "$VENDOR_DIR/bin/php-scoper" add-prefix \
    --config="$PLUGIN_DIR/scoper.inc.php" \
    --force \
    --quiet

# Check if scoping was successful
if [ ! -d "$BUILD_DIR/vendor" ]; then
    echo "❌ Error: Scoping failed. Build directory not created."
    exit 1
fi

# Remove symfony/yaml's CLI linter (needs symfony/console, which is not shipped)
rm -rf "$BUILD_DIR/vendor/symfony/yaml/Command" "$BUILD_DIR/vendor/symfony/yaml/Resources/bin"
rmdir "$BUILD_DIR/vendor/symfony/yaml/Resources" 2>/dev/null || true

# Dump the autoloader for the scoped dependencies
echo "📦 Generating autoloader for scoped dependencies..."
cd "$BUILD_DIR"

# Create a temporary composer.json for the scoped build
cat > composer.json <<'EOF'
{
  "autoload": {
    "psr-4": {
      "YamlCF\\": "../src/",
      "YamlCF\\Vendor\\Symfony\\Component\\Yaml\\": "vendor/symfony/yaml/",
      "YamlCF\\Vendor\\Symfony\\Component\\DeprecationContracts\\": "vendor/symfony/deprecation-contracts/",
      "YamlCF\\Vendor\\Symfony\\Polyfill\\Ctype\\": "vendor/symfony/polyfill-ctype/"
    },
    "files": [
      "vendor/symfony/polyfill-ctype/bootstrap.php",
      "vendor/symfony/deprecation-contracts/function.php"
    ]
  },
  "config": {
    "platform-check": false,
    "autoloader-suffix": "YamlCustomFieldsScoped"
  }
}
EOF

# Dump autoload
$COMPOSER_CMD dump-autoload --working-dir="$BUILD_DIR" --no-dev --classmap-authoritative --quiet

# Remove dev bin stubs (these get auto-generated even with --no-dev)
echo "🧹 Removing dev bin stubs..."
if [ -d "$BUILD_DIR/vendor/bin" ]; then
    cd "$BUILD_DIR/vendor/bin"
    rm -f php-parse php-scoper phpunit yaml-lint var-dump-server
fi

cd "$PLUGIN_DIR"

echo "✅ Build complete! Scoped dependencies are in: $BUILD_DIR/vendor"
echo ""
echo "Next steps:"
echo "  1. Test the plugin with the scoped dependencies"
echo "  2. If everything works, commit the build directory"
echo "  3. Update .gitignore to include /build/vendor/"
echo ""
