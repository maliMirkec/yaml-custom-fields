# Testing Guide

This document explains how to run and write tests for the YAML Custom Fields WordPress plugin.

## Table of Contents

- [Quick Start](#quick-start)
- [Overview](#overview)
- [Installation](#installation)
- [Running Tests](#running-tests)
- [Test Structure](#test-structure)
- [Writing Tests](#writing-tests)
- [Test Utilities](#test-utilities)
- [Troubleshooting](#troubleshooting)
- [Quick Regression Checklist](#quick-regression-checklist)
- [Manual Testing Guide](#manual-testing-guide)

## Quick Start

**Running all tests:**
```bash
composer test
```

**Current Coverage:** 120 tests, 258 assertions

**See also:** [MANUAL-TESTING.md](MANUAL-TESTING.md) for comprehensive manual test suite

## Overview

The plugin uses **PHPUnit 9** for testing with two types of tests:

1. **Unit Tests** - Test individual classes in isolation without WordPress (currently implemented)
2. **Integration Tests** - Test classes with real WordPress environment (planned for future)

### Current Test Coverage

We currently have **120 unit tests** covering:

- ✅ **Helpers** (3 files, 40 tests)
  - `MarkdownParser` - Markdown to HTML conversion
  - `HtmlHelper` - HTML attribute building and escaping
  - `RequestHelper` - Request parameter sanitization

- ✅ **Schema Components** (3 files, 45 tests)
  - `SchemaParser` - YAML parsing
  - `SchemaValidator` - Schema structure validation
  - `FieldNormalizer` - Field shorthand normalization

- ✅ **Form/Security** (2 files, 35 tests)
  - `DataSanitizer` - Data sanitization
  - `AttachmentValidator` - Attachment validation

## Installation

### Prerequisites

- PHP 7.4 or higher
- Composer (included as `composer.phar` in plugin directory)

### Install Test Dependencies

```bash
# From the plugin root directory
php composer.phar install
```

This installs:
- `phpunit/phpunit` (^9.0) - Testing framework
- `yoast/phpunit-polyfills` (^2.0) - WordPress compatibility layer

## Running Tests

### Run All Unit Tests

```bash
php composer.phar test:unit
```

### Run All Tests (Unit + Integration)

```bash
php composer.phar test
```

Note: Currently only unit tests are available. Integration tests will be added in the future.

### Run Specific Test File

```bash
vendor/bin/phpunit tests/Unit/Helpers/MarkdownParserTest.php
```

### Run Specific Test Method

```bash
vendor/bin/phpunit --filter testParsesBoldSyntax tests/Unit/Helpers/MarkdownParserTest.php
```

### Run Tests with Code Coverage (requires Xdebug)

```bash
vendor/bin/phpunit --coverage-html coverage/
```

## Test Structure

```
tests/
├── bootstrap-unit.php              # Bootstrap for unit tests (no WordPress)
├── bootstrap.php                   # Bootstrap for integration tests (requires WordPress)
├── TestCase.php                    # Base class for unit tests
├── WPTestCase.php                  # Base class for integration tests
├── Factories/
│   ├── SchemaFactory.php          # Generate test schemas
│   └── DataFactory.php            # Generate test data
└── Unit/
    ├── Helpers/                   # Tests for helper classes
    │   ├── MarkdownParserTest.php
    │   ├── HtmlHelperTest.php
    │   └── RequestHelperTest.php
    ├── Schema/                    # Tests for schema components
    │   ├── SchemaParserTest.php
    │   ├── SchemaValidatorTest.php
    │   └── FieldNormalizerTest.php
    └── Form/                      # Tests for form/security
        ├── DataSanitizerTest.php
        └── AttachmentValidatorTest.php
```

## Writing Tests

### Creating a New Test File

1. Create a new file in `tests/Unit/<Category>/`
2. Extend `YamlCF\Tests\TestCase`
3. Follow the naming convention: `ClassNameTest.php`

Example:

```php
<?php
namespace YamlCF\Tests\Unit\Helpers;

use YamlCF\Tests\TestCase;
use YamlCF\Helpers\MyHelper;

class MyHelperTest extends TestCase {
    private $helper;

    protected function setUp(): void {
        parent::setUp();
        $this->helper = new MyHelper();
    }

    /**
     * Test description
     */
    public function testSomething() {
        $result = $this->helper->doSomething('input');
        $this->assertEquals('expected', $result);
    }
}
```

### Test Method Naming

Use descriptive names that explain what's being tested:

- `testParsesBoldSyntax()` - Good
- `testParse()` - Too vague
- `test1()` - Bad

### Assertions

The `TestCase` base class provides helper methods:

```php
// String assertions
$this->assertStringContains('needle', 'haystack');
$this->assertStringNotContains('needle', 'haystack');

// Array assertions
$this->assertArrayStructure(['key1', 'key2'], $array);

// Standard PHPUnit assertions
$this->assertEquals($expected, $actual);
$this->assertTrue($value);
$this->assertIsArray($value);
$this->assertCount(5, $array);
```

### Using Test Factories

Generate test data using factories:

```php
use YamlCF\Tests\Factories\SchemaFactory;
use YamlCF\Tests\Factories\DataFactory;

// In your test
$schema = SchemaFactory::createSimpleSchema();
$data = DataFactory::createCompleteData();
```

Available factory methods:

**SchemaFactory:**
- `createSimpleSchema()` - Basic field schema
- `createCompleteSchema()` - Full schema with all field types
- `createBlockSchema()` - Block editor schema
- `createNestedSchema()` - Group/repeater fields
- `createInvalidSchema()` - Invalid YAML for error testing

**DataFactory:**
- `createSimpleData()` - Basic field data
- `createCompleteData()` - Complete data set
- `createHtmlData()` - Data with HTML (for sanitization tests)
- `createSpecialCharsData()` - Data with special characters
- `createExportData()` - Data for export testing
- `createImportData()` - Data for import testing

## Test Utilities

### Mocked WordPress Functions

Unit tests use mocked WordPress functions (see `tests/bootstrap-unit.php`):

- `esc_html()` - HTML entity encoding
- `esc_attr()` - Attribute escaping
- `esc_url()` - URL sanitization (blocks dangerous protocols)
- `sanitize_text_field()` - Text sanitization
- `sanitize_key()` - Key sanitization
- `wp_kses()` - HTML filtering
- `wp_kses_post()` - Post content filtering (basic mock)
- `do_action()` - Action hooks (no-op)
- `map_deep()` - Recursive array mapping
- `wp_unslash()` - Stripslashes

Note: These are simplified implementations for testing. Integration tests with real WordPress will use the actual functions.

### Test Organization

Group related tests together:

```php
/**
 * Test parsing bold syntax
 */
public function testParsesBoldSyntax() { }

/**
 * Test parsing italic syntax
 */
public function testParsesItalicSyntax() { }

/**
 * Test parsing combined markdown
 */
public function testParsesCombinedMarkdown() { }
```

### Testing Edge Cases

Always test edge cases:

```php
// Empty input
public function testHandlesEmptyInput() {
    $this->assertEquals('', MyClass::parse(''));
    $this->assertEquals('', MyClass::parse(null));
}

// Special characters
public function testHandlesSpecialCharacters() {
    $input = 'Text with & < > " \'';
    $result = MyClass::parse($input);
    // Assertions...
}

// Invalid input
public function testRejectsInvalidInput() {
    $result = MyClass::parse('invalid');
    $this->assertFalse($result);
}
```

## Troubleshooting

### Issue: Class not found

**Error:** `Class "YamlCF\SomeClass" not found`

**Solution:** Make sure the scoped vendor autoloader is loaded:
```php
// In tests/bootstrap-unit.php
require_once dirname(__DIR__) . '/build/vendor/scoper-autoload.php';
```

### Issue: WordPress function not found

**Error:** `Call to undefined function esc_html()`

**Solution:** Add the function to `tests/bootstrap-unit.php`:
```php
if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
```

### Issue: Deprecation warnings

**Issue:** Many deprecation warnings from `thecodingmachine/safe` library

**Solution:** These are harmless warnings from a dependency. They don't affect test results. You can filter them with:
```bash
php composer.phar test:unit 2>&1 | grep -v "Deprecated"
```

### Issue: Tests timing out

**Solution:** Increase the timeout in `phpunit.xml`:
```xml
<phpunit
    processIsolationTimeout="120"
    ...
>
```

### Issue: Memory limit

**Solution:** Increase PHP memory limit:
```bash
php -d memory_limit=512M composer.phar test:unit
```

## Future Plans

### Integration Tests

Integration tests will be added in a future phase and will require:

1. WordPress test environment setup:
```bash
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
```

2. Test database configuration
3. Extended test coverage for:
   - Data Repositories (database operations)
   - AJAX Handlers (endpoint testing)
   - Import/Export functionality
   - Admin Controllers
   - Field Renderers

### Continuous Integration

Automated testing on GitHub Actions/GitLab CI is planned for:
- Running tests on every commit
- Code coverage reporting
- Multi-version PHP testing (7.4, 8.0, 8.1, 8.2)

## Contributing

When adding new features:

1. Write tests first (TDD approach recommended)
2. Ensure all tests pass before committing
3. Add tests for edge cases and error conditions
4. Update this documentation if adding new test utilities

## Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [WordPress Plugin Unit Tests](https://make.wordpress.org/cli/handbook/misc/plugin-unit-tests/)
- [Yoast PHPUnit Polyfills](https://github.com/Yoast/PHPUnit-Polyfills)

## Summary

**Current Status:**
- ✅ 120 unit tests passing
- ✅ 258 assertions
- ✅ Core functionality covered (Helpers, Schema, Form/Security)
- ⏳ Integration tests (planned)
- ⏳ CI/CD pipeline (planned)

**Test Execution Time:** ~15ms
**Memory Usage:** ~12MB

Run tests frequently during development to catch issues early!

---

## Quick Regression Checklist

### Critical Path (15 minutes)

Use this checklist for quick regression testing before releases.

**Prerequisites:**
- [ ] WP_DEBUG = true in wp-config.php
- [ ] WordPress site running
- [ ] Plugin activated

#### 1. Plugin Activation/Deactivation
- [ ] Plugin activates without errors
- [ ] Plugin deactivates without errors
- [ ] No PHP errors in debug.log

#### 2. Admin Page Loads
- [ ] Main page loads: `/wp-admin/admin.php?page=yaml-custom-fields`
- [ ] No JavaScript errors in console (F12)
- [ ] Template list displays
- [ ] Refresh button functional

#### 3. Schema Editor
- [ ] Schema editor loads
- [ ] Can edit YAML
- [ ] Save button works
- [ ] Success message displays (green)
- [ ] Invalid YAML shows error message (red)

#### 4. Template Form Submission
- [ ] Edit template/partial data
- [ ] Unsaved changes warning works
- [ ] Form saves successfully
- [ ] No nonce errors

#### 5. Data Objects
- [ ] Data objects page loads
- [ ] Can create new data object type
- [ ] Can add entries
- [ ] Can edit/delete entries

#### 6. Export/Import
- [ ] Single post export works
- [ ] Settings export works
- [ ] JSON files download correctly
- [ ] No nonce security errors

#### 7. Clean Uninstall
- [ ] Deactivate and delete plugin
- [ ] Check database for leftover data (should be clean)
- [ ] Check options table (`SELECT * FROM wp_options WHERE option_name LIKE '%yaml_cf%'`)

### Debug Checks

**Browser Console (F12):**
```javascript
// Should not throw errors
typeof yamlCFPageInit !== 'undefined'
typeof YamlCF !== 'undefined'
```

**Debug Log:**
```bash
tail -100 wp-content/debug.log | grep -i "yaml\|fatal\|warning"
```

**Expected:** No fatal errors, no YAML Custom Fields warnings

---

## Manual Testing Guide

For comprehensive manual testing, see: **[MANUAL-TESTING.md](MANUAL-TESTING.md)**

The manual testing guide includes:
- Complete feature testing (675 lines)
- Browser compatibility matrix
- Accessibility testing
- Edge case scenarios
- Multi-site testing
- Conflict testing with other plugins

**Recommendation:** Run full manual testing suite before major releases and WordPress.org submissions.

---

**Last Updated:** 2025-12-28
**Plugin Version:** 1.2.2
**Test Coverage:** 120 tests, 258 assertions
