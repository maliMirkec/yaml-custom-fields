#!/bin/bash
# Fix ABSPATH protection position - must come AFTER namespace declaration
# PHP requires namespace to be the first statement

echo "🔧 Fixing ABSPATH Protection Position"
echo "======================================"
echo ""

FIXED=0
ERRORS=0

# Find all PHP files in src/ with namespace
for file in $(find src/ -name "*.php" -type f); do
    if grep -q "^namespace " "$file"; then
        # Create backup
        cp "$file" "$file.bak"

        # Remove the ABSPATH block and add it after namespace
        python3 - "$file" <<'PYTHON'
import sys
import re

filename = sys.argv[1]

with open(filename, 'r') as f:
    content = f.read()

# Remove existing ABSPATH block (between opening tag and namespace)
content = re.sub(
    r'(<\?php\s*\n)\s*// Prevent direct access\s*\nif \(!defined\(["\']ABSPATH["\']\)\) \{\s*\n\s*exit;\s*\n\}\s*\n',
    r'\1',
    content
)

# Add ABSPATH after namespace declaration
content = re.sub(
    r'(namespace [^;]+;\s*\n)',
    r'\1\n// Prevent direct access\nif (!defined(\'ABSPATH\')) {\n    exit;\n}\n',
    content
)

with open(filename, 'w') as f:
    f.write(content)
PYTHON

        if [ $? -eq 0 ]; then
            echo "✅ Fixed: $file"
            ((FIXED++))
            rm "$file.bak"
        else
            echo "❌ Error: $file"
            ((ERRORS++))
            mv "$file.bak" "$file"
        fi
    fi
done

echo ""
echo "======================================"
echo "📊 Summary"
echo "======================================"
echo "Files fixed: $FIXED"
echo "Errors:      $ERRORS"
echo ""

if [ $ERRORS -eq 0 ]; then
    echo "✅ ABSPATH position fixed!"
    echo ""
    echo "Testing with PHPUnit..."
    php composer.phar test:unit 2>&1 | head -20
else
    echo "⚠️  Some files had errors."
    exit 1
fi
