#!/bin/bash
# Script to add ABSPATH protection to all PHP files in src/
# Prevents direct file access for defense in depth

echo "🔒 Adding ABSPATH Protection to PHP Files"
echo "=========================================="
echo ""

PROTECTED=0
SKIPPED=0
ERRORS=0

# Find all PHP files in src/
for file in $(find src/ -name "*.php" -type f); do
    # Check if file already has ABSPATH protection
    if grep -q "defined('ABSPATH')\|defined(\"ABSPATH\")" "$file"; then
        echo "⏭  Skipping (already protected): $file"
        ((SKIPPED++))
    else
        # Create backup
        cp "$file" "$file.bak"

        # Use sed to insert ABSPATH check after the first <?php line
        sed '1 a\
\
// Prevent direct access\
if (!defined('\''ABSPATH'\'')) {\
    exit;\
}' "$file" > "$file.tmp"

        # Check if sed succeeded and file is not empty
        if [ $? -eq 0 ] && [ -s "$file.tmp" ]; then
            mv "$file.tmp" "$file"
            echo "✅ Protected: $file"
            ((PROTECTED++))
            rm "$file.bak"
        else
            echo "❌ Error processing: $file"
            ((ERRORS++))
            # Restore from backup
            if [ -f "$file.bak" ]; then
                mv "$file.bak" "$file"
            fi
            rm -f "$file.tmp"
        fi
    fi
done

echo ""
echo "=========================================="
echo "📊 Summary"
echo "=========================================="
echo "Files protected: $PROTECTED"
echo "Files skipped:   $SKIPPED"
echo "Errors:          $ERRORS"
echo ""

if [ $ERRORS -eq 0 ]; then
    echo "✅ ABSPATH protection complete!"
    echo ""
    echo "Running security audit to verify..."
    echo ""
    bash bin/security-audit.sh | grep -A 2 "Direct File Access"
else
    echo "⚠️  Some files had errors. Check output above."
    exit 1
fi
