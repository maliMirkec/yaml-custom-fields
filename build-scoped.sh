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
PHP_BIN="${PHP_BIN:-php}"

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
    "platform-check": false
  }
}
EOF

# Dump autoload
$PHP_BIN ../composer.phar dump-autoload --working-dir="$BUILD_DIR" --no-dev --classmap-authoritative --quiet

# Remove dev bin stubs (these get auto-generated even with --no-dev)
echo "🧹 Removing dev bin stubs..."
if [ -d "$BUILD_DIR/vendor/bin" ]; then
    cd "$BUILD_DIR/vendor/bin"
    rm -f php-parse php-scoper phpunit
fi

cd "$PLUGIN_DIR"

echo "✅ Build complete! Scoped dependencies are in: $BUILD_DIR/vendor"
echo ""
echo "Next steps:"
echo "  1. Test the plugin with the scoped dependencies"
echo "  2. If everything works, commit the build directory"
echo "  3. Update .gitignore to include /build/vendor/"
echo ""
