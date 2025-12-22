#!/bin/bash
# Code Quality Check Script for YAML Custom Fields Plugin
# Pre-submission validation for WordPress.org

echo "📋 YAML Custom Fields - Code Quality Check"
echo "==========================================="
echo ""

ERRORS=0
WARNINGS=0

# Colors
RED='\033[0;31m'
YELLOW='\033[1;33m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m'

print_check() {
    if [ $2 -eq 0 ]; then
        echo -e "${GREEN}✓${NC} $1"
    else
        echo -e "${RED}✗${NC} $1"
        ((ERRORS++))
    fi
}

print_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
    ((WARNINGS++))
}

print_info() {
    echo -e "${BLUE}ℹ${NC} $1"
}

echo "1. Checking File Structure..."
echo "------------------------------"

if [ -f "yaml-custom-fields.php" ]; then
    print_check "Main plugin file exists" 0
else
    print_check "Main plugin file missing" 1
fi

if [ -d "src" ]; then
    print_check "Source directory exists" 0
else
    print_check "Source directory missing" 1
fi

if [ -d "build/vendor" ]; then
    print_check "Built dependencies exist" 0
else
    print_warning "Dependencies not built (run: bash build-scoped.sh)"
fi

if [ -f ".distignore" ]; then
    print_check ".distignore exists" 0
else
    print_warning ".distignore missing"
fi

echo ""
echo "2. Checking Plugin Header..."
echo "-----------------------------"

# Extract plugin header
HEADER_VERSION=$(grep "Version:" yaml-custom-fields.php | head -1)
HEADER_TEXT_DOMAIN=$(grep "Text Domain:" yaml-custom-fields.php | head -1)
HEADER_LICENSE=$(grep "License:" yaml-custom-fields.php | head -1)

if [ -n "$HEADER_VERSION" ]; then
    print_check "Version declared" 0
else
    print_check "Version missing in header" 1
fi

if [ -n "$HEADER_TEXT_DOMAIN" ]; then
    print_check "Text Domain declared" 0
else
    print_warning "Text Domain missing in header"
fi

if [ -n "$HEADER_LICENSE" ]; then
    print_check "License declared" 0
else
    print_check "License missing in header" 1
fi

# Check for Domain Path
DOMAIN_PATH=$(grep "Domain Path:" yaml-custom-fields.php)
if [ -n "$DOMAIN_PATH" ]; then
    print_check "Domain Path declared" 0
else
    print_warning "Domain Path missing (recommended for i18n)"
fi

echo ""
echo "3. Checking readme.txt..."
echo "-------------------------"

if [ -f "readme.txt" ]; then
    # Check stable tag
    STABLE_TAG=$(grep "Stable tag:" readme.txt)
    if [ -n "$STABLE_TAG" ]; then
        print_check "Stable tag present" 0
    else
        print_check "Stable tag missing" 1
    fi

    # Check tested up to
    TESTED_UP_TO=$(grep "Tested up to:" readme.txt)
    if [ -n "$TESTED_UP_TO" ]; then
        print_check "Tested up to version present" 0
    else
        print_check "Tested up to version missing" 1
    fi

    # Check requires PHP
    REQUIRES_PHP=$(grep "Requires PHP:" readme.txt)
    if [ -n "$REQUIRES_PHP" ]; then
        print_check "Requires PHP version present" 0
    else
        print_warning "Requires PHP version missing"
    fi

    # Check changelog
    CHANGELOG=$(grep "== Changelog ==" readme.txt)
    if [ -n "$CHANGELOG" ]; then
        print_check "Changelog section present" 0
    else
        print_warning "Changelog section missing"
    fi
else
    print_check "readme.txt missing" 1
fi

echo ""
echo "4. Checking Code Standards..."
echo "------------------------------"

# Check if phpcs is available
if command -v phpcs &> /dev/null; then
    print_info "Running WordPress Coding Standards check..."

    # Run PHPCS if config exists
    if [ -f "phpcs.xml.dist" ] || [ -f "phpcs.xml" ]; then
        PHPCS_ERRORS=$(phpcs --report=summary 2>&1 | grep "FOUND" | awk '{print $2}')
        if [ -z "$PHPCS_ERRORS" ] || [ "$PHPCS_ERRORS" == "0" ]; then
            print_check "WordPress Coding Standards passed" 0
        else
            print_warning "PHPCS found $PHPCS_ERRORS issues"
        fi
    else
        print_info "phpcs.xml not found, skipping PHPCS check"
    fi
else
    print_info "phpcs not installed, skipping code standards check"
fi

echo ""
echo "5. Checking Tests..."
echo "---------------------"

if [ -d "tests" ]; then
    print_check "Tests directory exists" 0

    # Count test files
    TEST_COUNT=$(find tests/ -name "*Test.php" | wc -l)
    if [ "$TEST_COUNT" -gt 0 ]; then
        print_check "Test files found ($TEST_COUNT tests)" 0
    fi

    # Check if PHPUnit is configured
    if [ -f "phpunit.xml" ]; then
        print_check "PHPUnit configuration exists" 0
    else
        print_warning "phpunit.xml missing"
    fi
else
    print_info "No tests directory (tests are optional but recommended)"
fi

echo ""
echo "6. Checking Documentation..."
echo "-----------------------------"

if [ -f "README.md" ]; then
    print_check "README.md exists" 0
fi

# Check for documentation in readme.txt
FAQ=$(grep "== Frequently Asked Questions ==" readme.txt)
if [ -n "$FAQ" ]; then
    print_check "FAQ section present" 0
else
    print_warning "FAQ section recommended"
fi

INSTALLATION=$(grep "== Installation ==" readme.txt)
if [ -n "$INSTALLATION" ]; then
    print_check "Installation section present" 0
else
    print_warning "Installation section recommended"
fi

echo ""
echo "7. Checking Asset Requirements..."
echo "----------------------------------"

if [ -d "assets" ]; then
    print_info "Assets directory found"

    # Note: For plugin directory, these go in SVN assets/, not plugin ZIP
    print_info "For WordPress.org, create in SVN /assets/:"
    print_info "  - icon-256x256.png"
    print_info "  - banner-1544x500.png"
    print_info "  - screenshot-1.png, screenshot-2.png, etc."
fi

echo ""
echo "8. Checking for Unwanted Files..."
echo "----------------------------------"

# Check for files that shouldn't be in distribution
UNWANTED_PATTERNS=(.git node_modules .DS_Store Thumbs.db .env .phpcs .vscode .idea)
FOUND_UNWANTED=0

for pattern in "${UNWANTED_PATTERNS[@]}"; do
    if [ -e "$pattern" ] || [ -d "$pattern" ]; then
        print_warning "Found: $pattern (should be in .distignore)"
        ((FOUND_UNWANTED++))
    fi
done

if [ "$FOUND_UNWANTED" -eq 0 ]; then
    print_check "No obvious unwanted files" 0
fi

echo ""
echo "9. Checking Namespace Usage..."
echo "-------------------------------"

# Check if classes use namespaces
NAMESPACED_CLASSES=$(grep -r "^namespace " src/ --include="*.php" | wc -l)
TOTAL_CLASSES=$(find src/ -name "*.php" | wc -l)

if [ "$NAMESPACED_CLASSES" -eq "$TOTAL_CLASSES" ]; then
    print_check "All classes use namespaces" 0
elif [ "$NAMESPACED_CLASSES" -gt $((TOTAL_CLASSES / 2)) ]; then
    print_check "Most classes use namespaces ($NAMESPACED_CLASSES/$TOTAL_CLASSES)" 0
else
    print_warning "Few classes use namespaces ($NAMESPACED_CLASSES/$TOTAL_CLASSES)"
fi

echo ""
echo "10. Checking Build Status..."
echo "-----------------------------"

if [ -d "vendor" ]; then
    print_info "Dev dependencies present (will be excluded from ZIP)"
fi

if [ -d "build/vendor" ]; then
    print_check "Production dependencies built" 0
else
    print_warning "Production build missing - run: bash build-scoped.sh"
fi

# Check if package script exists
if [ -f "package-for-wporg.sh" ]; then
    print_check "Package script exists" 0
else
    print_warning "package-for-wporg.sh missing"
fi

echo ""
echo "==========================================="
echo "📊 Code Quality Summary"
echo "==========================================="
echo ""

if [ "$ERRORS" -eq 0 ] && [ "$WARNINGS" -eq 0 ]; then
    echo -e "${GREEN}✓ EXCELLENT!${NC} Code quality checks passed!"
    EXIT_CODE=0
elif [ "$ERRORS" -eq 0 ]; then
    echo -e "${YELLOW}⚠ GOOD${NC}: $WARNINGS warning(s)"
    echo "Address warnings for best results."
    EXIT_CODE=0
else
    echo -e "${RED}✗ ISSUES FOUND${NC}: $ERRORS error(s), $WARNINGS warning(s)"
    EXIT_CODE=1
fi

echo ""
echo "💡 Recommended Next Steps:"
echo "  1. Fix any errors or warnings above"
echo "  2. Run: bash bin/security-audit.sh"
echo "  3. Run: php composer.phar test"
echo "  4. Validate readme.txt: https://wordpress.org/plugins/developers/readme-validator/"
echo "  5. Build production ZIP: bash package-for-wporg.sh"
echo ""

exit $EXIT_CODE
