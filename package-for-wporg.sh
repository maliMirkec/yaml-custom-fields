#!/bin/bash

# Package Plugin for WordPress.org
# This creates a clean distribution ZIP without development files

set -e

PLUGIN_SLUG="yaml-custom-fields"
VERSION=$(grep "Version:" yaml-custom-fields.php | awk '{print $3}')
DIST_DIR="dist"
ZIP_NAME="${PLUGIN_SLUG}.zip"

echo "📦 Packaging YAML Custom Fields v${VERSION}"
echo "=========================================="

# Clean up previous builds
if [ -d "$DIST_DIR" ]; then
    echo "🧹 Cleaning up previous build..."
    rm -rf "$DIST_DIR"
fi

# Create dist directory
mkdir -p "$DIST_DIR/$PLUGIN_SLUG"

echo "📋 Copying files..."

# Copy all files except those in .distignore
rsync -av \
    --exclude-from='.distignore' \
    --exclude='dist/' \
    --exclude='*.sh' \
    ./ "$DIST_DIR/$PLUGIN_SLUG/"

# Ensure build/vendor is included (it's what users need!)
if [ ! -d "$DIST_DIR/$PLUGIN_SLUG/build/vendor" ]; then
    echo "❌ ERROR: build/vendor not found! Run ./build-scoped.sh first."
    exit 1
fi

# Create ZIP
echo "🗜️  Creating ZIP file..."
cd "$DIST_DIR"
zip -r "../$ZIP_NAME" "$PLUGIN_SLUG" -q

cd ..
FILE_SIZE=$(du -h "$ZIP_NAME" | cut -f1)

echo ""
echo "✅ Package created successfully!"
echo ""
echo "   File: $ZIP_NAME"
echo "   Size: $FILE_SIZE"
echo ""
echo "📤 Ready for upload to WordPress.org!"
echo ""
echo "Next steps:"
echo "  1. Test the ZIP by installing it on a fresh WordPress site"
echo "  2. Upload to WordPress.org via https://wordpress.org/plugins/developers/"
echo "  3. Upload assets (banners, icons) via the web interface"
echo ""
