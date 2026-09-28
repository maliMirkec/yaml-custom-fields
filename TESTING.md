# Testing Guide

How to run the tests and how to write new ones.

## Quick start

```bash
composer install
composer check             # syntax + coding style + unit tests
composer test:integration  # tests in a real WordPress
```

- **Unit tests:** 263 tests. No WordPress needed. Very fast.
- **Integration tests:** 579 tests. They run in a real WordPress with a database. Setup: [tests/Integration/README.md](tests/Integration/README.md).
- **By hand:** [MANUAL-TESTING.md](MANUAL-TESTING.md), step by step for every feature.
- All tests pass right now.

## Two kinds of tests

**Unit tests** (`tests/Unit/`)
- Test one class at a time.
- WordPress is not loaded. Some WordPress functions are faked in `tests/bootstrap-unit.php`.
- Run with `composer test:unit` (or just `composer test`).

**Integration tests** (`tests/Integration/`)
- Test the plugin in a real WordPress: saving posts, AJAX, admin screens, REST, uninstall.
- Need a WordPress copy and an **empty** test database. The tests wipe it.
- Run with `composer test:integration`.

**GitHub** runs both on every push and pull request, on PHP 8.1, 8.3 and 8.4 (`.github/workflows/tests.yml`).

## What you need

- PHP 8.1 or newer
- Composer
- For integration tests: MySQL and a WordPress copy

`composer install` installs PHPUnit 9, the Yoast polyfills, the WordPress test library and the coding style tools.

## Run tests

```bash
# All unit tests
composer test:unit

# All integration tests (set the WP_* variables first, see tests/Integration/README.md)
composer test:integration

# One file
vendor/bin/phpunit tests/Unit/Helpers/MarkdownParserTest.php

# One test
vendor/bin/phpunit --filter testParsesBoldSyntax tests/Unit/Helpers/MarkdownParserTest.php

# One integration test file
vendor/bin/phpunit -c phpunit-integration.xml.dist tests/Integration/SharedOnlyTemplateTest.php
```

## Other checks

```bash
composer lint           # coding style. Must show zero findings.
composer lint:fix       # fix what can be fixed on its own
composer lint:syntax    # php -l on every file
composer check:release  # everything, before a release
```

- No `phpcs:ignore` comments. Fix the code instead.
- `check:release` builds `build/vendor` first, so the tests run on the build you ship.

## Folders

```
tests/
├── bootstrap-unit.php         # Starts unit tests (fake WordPress functions)
├── bootstrap.php              # Starts integration tests (real WordPress)
├── TestCase.php               # Base class for unit tests
├── WPTestCase.php             # Base class for integration tests
├── FailsOnPluginWarnings.php  # Makes a test fail on any PHP warning from the plugin
├── wp-tests-config.php        # Database settings for integration tests
├── Factories/                 # Ready-made field lists and values
├── themes/                    # Small test themes (block, child, parent, singular)
├── Unit/
│   ├── Form/                  # Cleaning values, checking attachments
│   ├── Helpers/               # Markdown, HTML, request helpers
│   ├── ImportExport/          # Export, import, old file formats
│   ├── Rendering/             # How fields are drawn
│   ├── Schema/                # Reading and checking field lists
│   ├── Template/              # Sorting templates
│   ├── NoticeConsistencyTest.php  # Every message uses the one notice style
│   └── ScopedAutoloaderTest.php   # The build knows every class in src/
└── Integration/               # See tests/Integration/README.md
```

## Two tests that catch common mistakes

- **`ScopedAutoloaderTest`** fails if you add a class to `src/` and forget `./build-scoped.sh`. The built autoloader only knows the classes it saw. A missing one breaks real sites.
- **`NoticeConsistencyTest`** fails if you show a message the old way (toasts, callouts, tooltips, or a notice pushed in at the top of the page). Use the one notice style:
  - Server side: `NotificationManager`.
  - In scripts, next to a form: `YamlCF.notify(…, { target })`.
  - For a button that works in place: `YamlCF.status(slot, text, type)`.

## Write a unit test

1. Add a file in `tests/Unit/<Folder>/`.
2. Name it after the class: `MyHelperTest.php`.
3. Extend `YamlCF\Tests\TestCase`.

```php
<?php
namespace YamlCF\Tests\Unit\Helpers;

use YamlCF\Tests\TestCase;
use YamlCF\Helpers\MyHelper;

class MyHelperTest extends TestCase {
  public function testTurnsEmptyInputIntoEmptyText() {
    $this->assertSame('', MyHelper::parse(''));
  }
}
```

**Extra checks in `TestCase`:**
- `assertStringContains($needle, $haystack)`
- `assertStringNotContains($needle, $haystack)`
- `assertArrayStructure(['key1', 'key2'], $array)`

## Write an integration test

1. Add a file in `tests/Integration/`.
2. Extend `YamlCF\Tests\WPTestCase`.
3. Add a short comment on top: what broke, and what the test proves.

```php
<?php
namespace YamlCF\Tests\Integration;

use YamlCF\Tests\WPTestCase;

class MyFeatureTest extends WPTestCase {
  public function testEditorCanSaveAField() {
    $this->actingAs($this->createTestUser('editor'));
    $post_id = $this->createTestPost(['post_type' => 'page'], ['title' => 'Hello']);

    $this->assertSame('Hello', yaml_cf_get_field('title', $post_id));
  }
}
```

**Helpers in `WPTestCase`:**
- `createTestUser($role)`: makes a user.
- `actingAs($user_id)`: logs that user in.
- `createTestPost($args, $yaml_data)`: makes a post with field values.
- Plugin options and posts are cleaned up after each test.

## Test data

```php
use YamlCF\Tests\Factories\SchemaFactory;
use YamlCF\Tests\Factories\DataFactory;

$schema = SchemaFactory::createSimpleSchema();
$data   = DataFactory::createCompleteData();
```

**SchemaFactory:** `createSimpleSchema()`, `createCompleteSchema()`, `createBlockSchema()`, `createNestedSchema()`, `createInvalidSchema()`, `createDataObjectSchema()`, `createFromArray($fields)`

**DataFactory:** `createSimpleData()`, `createCompleteData()`, `createBlockData()`, `createNestedData()`, `createHtmlData()`, `createSpecialCharsData()`, `createEmptyData()`, `createIncompleteData()`, `createExportData()`, `createImportData()`, `createFromArray($fields)`, `randomString()`, `randomEmail()`

## Tips

- Name tests by what they prove: `testClearedFieldStaysEmpty()`, not `testSave()`.
- Test empty input, odd characters and bad input.
- One idea per test.
- Fix a bug? Write the test first. See it fail. Then fix.

## Problems

**`Class "YamlCF\…" not found`**
- Run `./build-scoped.sh`. The build doesn't know the class yet.

**`Call to undefined function esc_html()` in a unit test**
- Add a fake version to `tests/bootstrap-unit.php`. Or make it an integration test.

**Integration tests can't connect to the database**
- Check the `WP_TESTS_DB_*` variables. Is MySQL running?

**A test fails with a warning from the plugin**
- That's on purpose (`FailsOnPluginWarnings`). Fix the warning.

**Out of memory**
- `php -d memory_limit=512M vendor/bin/phpunit`

---

## Quick check before a release (about 15 minutes)

**Before you start**
- [ ] `WP_DEBUG` and `WP_DEBUG_LOG` are `true` in `wp-config.php`.
- [ ] The plugin is active.
- [ ] The browser console is open (F12).

**1. Turn on and off**
- [ ] Turn the plugin on. No errors.
- [ ] Turn it off. No errors.
- [ ] `debug.log` has no plugin errors.

**2. Main screen (YAML CF)**
- [ ] It opens. No errors in the console.
- [ ] The template list shows.
- [ ] **Refresh list** works.
- [ ] Switching **Custom fields** on and off doesn't make the table jump.

**3. Field list (schema)**
- [ ] The editor opens.
- [ ] Save works. A green message shows.
- [ ] Broken YAML shows a red message. Your text is kept.

**4. Page editor**
- [ ] The Custom fields box shows, one field per row.
- [ ] Change a field. The "not saved yet" line shows.
- [ ] Save. The value is kept.
- [ ] Shared values and site details show read-only. Their Copy buttons work.

**5. Collections**
- [ ] Add a collection.
- [ ] Add an item. **Save and add another** opens an empty item.
- [ ] Edit and delete items.

**6. Export and import**
- [ ] Export one page. The JSON file downloads.
- [ ] Export settings.
- [ ] Import them on another site.

**7. Settings**
- [ ] All three choices save.

**8. Delete the plugin**
- [ ] Untick **Keep the content if the plugin is deleted**.
- [ ] Delete the plugin.
- [ ] Nothing is left:
  ```sql
  SELECT option_name FROM wp_options WHERE option_name LIKE '%yaml_cf%';
  SELECT meta_key FROM wp_postmeta WHERE meta_key LIKE '_yaml_cf%';
  ```

**Console check**
```javascript
typeof YamlCF !== 'undefined'   // true on plugin screens
```

**Log check**
```bash
tail -100 wp-content/debug.log | grep -i "yaml\|fatal\|warning"
```
Nothing from this plugin should show.

---

## Full manual test

See **[MANUAL-TESTING.md](MANUAL-TESTING.md)**. It has short, step-by-step checks for every feature, messages, accessibility and deleting the plugin.

Do it before big releases and before sending the plugin to WordPress.org.

---

**Last updated:** 2026-09-28
**Plugin version:** 2.0.0
**Tests:** 263 unit, 579 integration
