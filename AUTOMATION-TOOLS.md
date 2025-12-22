# Automated Testing & Security Tools

This document describes the automated tools created for the YAML Custom Fields plugin to ensure code quality and security before WordPress.org submission.

---

## 🛠️ Available Tools

### 1. Security Audit Script
**File:** `bin/security-audit.sh`
**Purpose:** Automated security vulnerability scanning

**Usage:**
```bash
bash bin/security-audit.sh
```

**What It Checks:**
- ✅ Nonce verification in AJAX handlers
- ✅ SQL injection prevention (prepared statements)
- ✅ Capability checks (authorization)
- ✅ Output escaping (XSS prevention)
- ✅ Input sanitization
- ✅ File upload security
- ✅ Dangerous functions (eval, exec, etc.)
- ✅ External requests (phone-home check)
- ✅ Text domain configuration
- ✅ Required files (uninstall.php, readme.txt)
- ✅ Debug code detection
- ✅ Direct file access protection

**Exit Codes:**
- `0` = Success (no errors)
- `1` = Errors found (must fix before submission)

---

### 2. Code Quality Check Script
**File:** `bin/code-quality-check.sh`
**Purpose:** WordPress plugin directory compliance validation

**Usage:**
```bash
bash bin/code-quality-check.sh
```

**What It Checks:**
- ✅ File structure correctness
- ✅ Plugin header completeness
- ✅ readme.txt validation
- ✅ WordPress Coding Standards (if PHPCS installed)
- ✅ Test coverage
- ✅ Documentation completeness
- ✅ Asset requirements
- ✅ Unwanted files detection
- ✅ Namespace usage
- ✅ Build status

**Exit Codes:**
- `0` = Success
- `1` = Errors found

---

### 3. PHPUnit Tests
**File:** `phpunit.xml`
**Purpose:** Automated unit testing

**Usage:**
```bash
# Run all tests
php composer.phar test

# Run only unit tests
php composer.phar test:unit

# Run specific test file
vendor/bin/phpunit tests/Unit/Helpers/MarkdownParserTest.php
```

**Coverage:**
- 120 tests
- 258 assertions
- Helpers (40 tests)
- Schema components (45 tests)
- Form/Security (35 tests)

---

## 📊 Latest Audit Results

### Security Audit
```
Status: ✅ GOOD with 4 warnings
- 25 nonce verifications found
- 31 capability checks found
- 169 output escaping instances
- 48 input sanitization instances
- 0 critical security issues
```

**Warnings:**
1. File uploads may need enhanced validation
2. Text domain not loaded (easy fix)
3. LICENSE file missing (optional)
4. Direct file access protection recommended

### Code Quality Check
```
Status: ✅ GOOD with 3 warnings
- All required files present
- readme.txt validated
- 8 test files found
- All classes use namespaces
```

**Warnings:**
1. Domain Path missing in header (i18n)
2. .git in directory (excluded via .distignore)
3. .DS_Store in directory (excluded via .distignore)

---

## 🔄 Continuous Testing Workflow

### Before Every Commit
```bash
# 1. Run unit tests
php composer.phar test:unit

# 2. Check for PHP syntax errors
find src/ -name "*.php" -exec php -l {} \;

# 3. Format check (if you have prettier/beautify)
# npm run format:check
```

### Before Creating Release
```bash
# 1. Run full test suite
php composer.phar test

# 2. Run security audit
bash bin/security-audit.sh

# 3. Run code quality check
bash bin/code-quality-check.sh

# 4. Build production version
bash build-scoped.sh

# 5. Create distribution ZIP
bash package-for-wporg.sh

# 6. Test ZIP on fresh WordPress install
```

---

## 🚀 Quick Commands Reference

### Testing
```bash
# All tests
php composer.phar test

# Unit tests only
php composer.phar test:unit

# Integration tests only
php composer.phar test:integration

# Specific test
vendor/bin/phpunit tests/Unit/Helpers/MarkdownParserTest.php

# With coverage (requires Xdebug)
vendor/bin/phpunit --coverage-html coverage/
```

### Security & Quality
```bash
# Security scan
bash bin/security-audit.sh

# Code quality
bash bin/code-quality-check.sh

# WordPress Coding Standards (if installed)
phpcs --standard=WordPress src/

# PHP syntax check
find . -name "*.php" -not -path "./vendor/*" -not -path "./build/*" -exec php -l {} \;
```

### Building
```bash
# Install dev dependencies
php composer.phar install

# Install production only
php composer.phar install --no-dev

# Build scoped dependencies
bash build-scoped.sh

# Create distribution ZIP
bash package-for-wporg.sh
```

---

## 📝 Adding New Tests

### 1. Create Test File

**Location:** `tests/Unit/<Category>/<ClassName>Test.php`

**Template:**
```php
<?php
namespace YamlCF\Tests\Unit\Category;

use YamlCF\Tests\TestCase;
use YamlCF\Category\ClassName;

class ClassNameTest extends TestCase {
    private $instance;

    protected function setUp(): void {
        parent::setUp();
        $this->instance = new ClassName();
    }

    /**
     * Test description
     */
    public function testSomething() {
        $result = $this->instance->method();
        $this->assertEquals('expected', $result);
    }
}
```

### 2. Run New Test
```bash
vendor/bin/phpunit tests/Unit/Category/ClassNameTest.php
```

### 3. Update Test Count
Update documentation when adding tests.

---

## 🔍 Integration with CI/CD (Future)

### GitHub Actions Example
```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '7.4'

      - name: Install dependencies
        run: php composer.phar install

      - name: Run tests
        run: php composer.phar test

      - name: Security audit
        run: bash bin/security-audit.sh

      - name: Code quality
        run: bash bin/code-quality-check.sh
```

---

## 📊 Test Coverage Goals

### Current Coverage
- ✅ Helpers: 100% (all 3 classes)
- ✅ Schema: 100% (all 3 classes)
- ✅ Form: 100% (all 2 classes)
- ⏳ Integration: 0% (planned)

### Coverage Targets
- Unit Tests: 80%+ ✅ Achieved
- Integration Tests: 50%+ (future)
- E2E Tests: Critical paths (future)

---

## 🛡️ Security Testing Checklist

### Automated (via bin/security-audit.sh)
- [x] Nonce verification
- [x] SQL injection prevention
- [x] XSS prevention (output escaping)
- [x] CSRF protection
- [x] Authorization checks
- [x] Input sanitization
- [x] File upload security
- [x] Dangerous function detection

### Manual Testing
- [ ] Test file uploads with malicious files
- [ ] Test with different user roles
- [ ] Test in multisite environment
- [ ] Penetration testing (optional)
- [ ] Third-party security scan (optional)

---

## 💡 Tips for Maintaining Quality

### Before Every Commit
1. Run unit tests: `php composer.phar test:unit`
2. Check for PHP errors: `find src/ -name "*.php" -exec php -l {} \;`
3. Review changed files for security issues

### Weekly
1. Run full security audit: `bash bin/security-audit.sh`
2. Run code quality check: `bash bin/code-quality-check.sh`
3. Update dependencies: `php composer.phar update`

### Before Each Release
1. Run ALL tests
2. Run ALL audits
3. Test on fresh WordPress install
4. Validate readme.txt
5. Build and test distribution ZIP
6. Tag release in git

---

## 📚 Testing Resources

### Documentation
- PHPUnit: https://phpunit.de/
- WordPress Testing: https://make.wordpress.org/core/handbook/testing/
- Plugin Testing: https://developer.wordpress.org/plugins/wordpress-org/plugin-security/

### Tools
- Plugin Check: https://wordpress.org/plugins/plugin-check/
- readme.txt Validator: https://wordpress.org/plugins/developers/readme-validator/
- PHPCS: https://github.com/squizlabs/PHP_CodeSniffer

---

## 🎯 Quick Reference

| Task | Command |
|------|---------|
| Run all tests | `php composer.phar test` |
| Security audit | `bash bin/security-audit.sh` |
| Code quality | `bash bin/code-quality-check.sh` |
| Build plugin | `bash build-scoped.sh` |
| Create ZIP | `bash package-for-wporg.sh` |
| Check syntax | `find src/ -name "*.php" -exec php -l {} \;` |

---

**Last Updated:** 2025-12-21
**Plugin Version:** 1.2.1
**Test Coverage:** 120 tests, 258 assertions
