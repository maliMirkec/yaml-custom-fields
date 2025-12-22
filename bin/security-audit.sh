#!/bin/bash
# Security Audit Script for YAML Custom Fields Plugin
# Checks for common security issues before WordPress.org submission

echo "🔒 YAML Custom Fields - Security Audit"
echo "======================================"
echo ""

ERRORS=0
WARNINGS=0

# Colors for output
RED='\033[0;31m'
YELLOW='\033[1;33m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print results
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

echo "1. Checking Nonce Verification..."
echo "-----------------------------------"

# Check if AJAX actions have nonce verification
AJAX_ACTIONS=$(grep -rn "check_ajax_referer\|wp_verify_nonce" yaml-custom-fields.php | wc -l)
if [ "$AJAX_ACTIONS" -gt 10 ]; then
    print_check "AJAX actions have nonce verification ($AJAX_ACTIONS checks found)" 0
else
    print_check "Insufficient nonce checks found (only $AJAX_ACTIONS)" 1
fi

# Check if nonce is created
NONCE_CREATION=$(grep -rn "wp_create_nonce.*yaml_cf" src/ yaml-custom-fields.php | wc -l)
if [ "$NONCE_CREATION" -gt 0 ]; then
    print_check "Nonce creation found ($NONCE_CREATION instances)" 0
else
    print_check "No nonce creation found" 1
fi

echo ""
echo "2. Checking SQL Injection Prevention..."
echo "----------------------------------------"

# Check for unsafe SQL queries
UNSAFE_QUERIES=$(grep -rn "\$wpdb->query\|\$wpdb->get_\|\$wpdb->delete\|\$wpdb->update" src/ yaml-custom-fields.php --include="*.php" | grep -v "prepare" | grep -v "//")
if [ -z "$UNSAFE_QUERIES" ]; then
    print_check "No unsafe SQL queries found" 0
else
    print_warning "Potential unsafe SQL queries found:"
    echo "$UNSAFE_QUERIES"
fi

echo ""
echo "3. Checking Capability Checks..."
echo "---------------------------------"

# Check for current_user_can usage
CAP_CHECKS=$(grep -rn "current_user_can" src/ yaml-custom-fields.php --include="*.php" | wc -l)
if [ "$CAP_CHECKS" -gt 15 ]; then
    print_check "Capability checks found ($CAP_CHECKS instances)" 0
else
    print_warning "Few capability checks found (only $CAP_CHECKS)"
fi

echo ""
echo "4. Checking Output Escaping..."
echo "-------------------------------"

# Check for escaping functions
ESC_HTML=$(grep -rn "esc_html\|esc_attr\|esc_url" src/ yaml-custom-fields.php --include="*.php" | wc -l)
if [ "$ESC_HTML" -gt 50 ]; then
    print_check "Output escaping found ($ESC_HTML instances)" 0
else
    print_warning "Insufficient output escaping (only $ESC_HTML instances)"
fi

# Check for dangerous output (echo without escaping)
DANGEROUS_ECHO=$(grep -rn "echo.*\$_\|echo.*get_option" src/ yaml-custom-fields.php --include="*.php" | grep -v "esc_" | wc -l)
if [ "$DANGEROUS_ECHO" -gt 0 ]; then
    print_warning "Potential unescaped output found ($DANGEROUS_ECHO instances)"
else
    print_check "No obvious unescaped output" 0
fi

echo ""
echo "5. Checking Input Sanitization..."
echo "----------------------------------"

# Check for sanitization
SANITIZE=$(grep -rn "sanitize_text_field\|sanitize_key\|filter_input" src/ yaml-custom-fields.php --include="*.php" | wc -l)
if [ "$SANITIZE" -gt 30 ]; then
    print_check "Input sanitization found ($SANITIZE instances)" 0
else
    print_warning "Insufficient input sanitization (only $SANITIZE instances)"
fi

# Check for direct $_GET/$_POST usage
DIRECT_SUPERGLOBALS=$(grep -rn "\$_GET\['\|\$_POST\['" src/ yaml-custom-fields.php --include="*.php" | grep -v "isset\|sanitize\|wp_unslash" | wc -l)
if [ "$DIRECT_SUPERGLOBALS" -gt 0 ]; then
    print_warning "Direct superglobal access found ($DIRECT_SUPERGLOBALS instances)"
else
    print_check "No unsafe superglobal access" 0
fi

echo ""
echo "6. Checking File Operations..."
echo "-------------------------------"

# Check for file upload handling
FILE_UPLOADS=$(grep -rn "\$_FILES" src/ yaml-custom-fields.php --include="*.php" | wc -l)
if [ "$FILE_UPLOADS" -gt 0 ]; then
    print_info "File upload handling found ($FILE_UPLOADS instances)"
    # Check if file uploads are validated
    FILE_VALIDATION=$(grep -A 10 "\$_FILES" yaml-custom-fields.php | grep -c "wp_check_filetype\|mime\|size")
    if [ "$FILE_VALIDATION" -gt 0 ]; then
        print_check "File upload validation found" 0
    else
        print_warning "File uploads may lack proper validation"
    fi
fi

echo ""
echo "7. Checking for Dangerous Functions..."
echo "---------------------------------------"

# Check for eval, base64_decode, exec, system
DANGEROUS=$(grep -rn "eval(\|base64_decode(\|exec(\|system(\|shell_exec(" src/ yaml-custom-fields.php --include="*.php")
if [ -z "$DANGEROUS" ]; then
    print_check "No dangerous functions found" 0
else
    print_warning "Dangerous functions detected:"
    echo "$DANGEROUS"
fi

echo ""
echo "8. Checking for External Requests..."
echo "-------------------------------------"

# Check for wp_remote_get, curl, file_get_contents with URLs
EXTERNAL=$(grep -rn "wp_remote_\|curl_\|file_get_contents.*http" src/ yaml-custom-fields.php --include="*.php")
if [ -z "$EXTERNAL" ]; then
    print_check "No external requests found (good for plugin directory)" 0
else
    print_warning "External requests detected (may need review):"
    echo "$EXTERNAL"
fi

echo ""
echo "9. Checking Text Domain..."
echo "---------------------------"

# Check if text domain is set
TEXT_DOMAIN=$(grep -n "Text Domain:" yaml-custom-fields.php | wc -l)
if [ "$TEXT_DOMAIN" -gt 0 ]; then
    print_check "Text Domain declared" 0
else
    print_warning "Text Domain not found in plugin header"
fi

# Check for Domain Path header (required for WordPress.org auto-loading)
DOMAIN_PATH=$(grep -n "Domain Path:" yaml-custom-fields.php | wc -l)
if [ "$DOMAIN_PATH" -gt 0 ]; then
    print_check "Domain Path declared (WordPress.org auto-loads translations)" 0
else
    print_warning "Domain Path not declared in plugin header"
fi

echo ""
echo "10. Checking Required Files..."
echo "-------------------------------"

if [ -f "uninstall.php" ]; then
    print_check "uninstall.php exists" 0
else
    print_check "uninstall.php missing" 1
fi

if [ -f "readme.txt" ]; then
    print_check "readme.txt exists" 0
else
    print_check "readme.txt missing" 1
fi

if [ -f "LICENSE" ]; then
    print_check "LICENSE file exists" 0
else
    print_warning "LICENSE file missing (recommended)"
fi

echo ""
echo "11. Checking for Debug Code..."
echo "-------------------------------"

# Check for debugging code
DEBUG_CODE=$(grep -rn "var_dump\|print_r\|error_log\|console\.log" src/ yaml-custom-fields.php assets/ --include="*.php" --include="*.js" | grep -v "//.*var_dump\|//.*print_r" | wc -l)
if [ "$DEBUG_CODE" -eq 0 ]; then
    print_check "No debug code found" 0
else
    print_warning "Debug code found ($DEBUG_CODE instances) - remove before release"
fi

echo ""
echo "12. Checking Direct File Access Protection..."
echo "----------------------------------------------"

# Check if PHP files check for ABSPATH
TOTAL_PHP=$(find src/ -name "*.php" -type f | wc -l)
PROTECTED_PHP=$(grep -rl "ABSPATH\|WP_UNINSTALL_PLUGIN" src/ 2>/dev/null | wc -l)

if [ "$PROTECTED_PHP" -ge "$TOTAL_PHP" ]; then
    print_check "All PHP files protected ($PROTECTED_PHP/$TOTAL_PHP)" 0
elif [ "$PROTECTED_PHP" -gt $((TOTAL_PHP / 2)) ]; then
    print_check "Most PHP files protected ($PROTECTED_PHP/$TOTAL_PHP)" 0
else
    print_warning "Many PHP files lack direct access protection ($PROTECTED_PHP/$TOTAL_PHP)"
fi

echo ""
echo "======================================"
echo "📊 Security Audit Summary"
echo "======================================"
echo ""

if [ "$ERRORS" -eq 0 ] && [ "$WARNINGS" -eq 0 ]; then
    echo -e "${GREEN}✓ EXCELLENT!${NC} No security issues found!"
    echo "Your plugin is ready for WordPress.org submission."
    EXIT_CODE=0
elif [ "$ERRORS" -eq 0 ]; then
    echo -e "${YELLOW}⚠ GOOD${NC} with warnings: $WARNINGS warning(s)"
    echo "Address warnings before submission for best results."
    EXIT_CODE=0
else
    echo -e "${RED}✗ ISSUES FOUND${NC}: $ERRORS error(s), $WARNINGS warning(s)"
    echo "Please fix all errors before submitting to WordPress.org"
    EXIT_CODE=1
fi

echo ""
echo "💡 Next Steps:"
echo "  1. Review any errors or warnings above"
echo "  2. Run: bash bin/code-quality-check.sh"
echo "  3. Test on fresh WordPress install"
echo "  4. Validate readme.txt"
echo "  5. Build production ZIP"
echo ""

exit $EXIT_CODE
