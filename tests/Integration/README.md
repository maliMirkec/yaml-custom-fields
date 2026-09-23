# Integration Tests

These tests run inside a real WordPress test environment (database, hooks, users, capabilities).
They use the WordPress test library from the `wp-phpunit/wp-phpunit` dev dependency and the
settings in `tests/wp-tests-config.php`.

> The test suite **empties its database** on every run. Always use a dedicated database, never a real site's.

## Running locally

1. Install dependencies and build the scoped vendor directory:

   ```bash
   composer install
   ./build-scoped.sh
   ```

2. Download WordPress core somewhere outside the plugin:

   ```bash
   mkdir -p /path/to/wordpress
   curl -sL https://wordpress.org/latest.tar.gz | tar -xz --strip-components=1 -C /path/to/wordpress
   ```

3. Create an empty MySQL database, then run the suite with environment variables:

   ```bash
   WP_CORE_DIR=/path/to/wordpress \
   WP_TESTS_DB_NAME=wordpress_test \
   WP_TESTS_DB_USER=root \
   WP_TESTS_DB_PASSWORD='' \
   WP_TESTS_DB_HOST=127.0.0.1:3306 \
   composer test:integration
   ```

Unit tests (`composer test:unit`) need none of this.

CI runs both suites on every push and pull request (`.github/workflows/tests.yml`).

## Coverage

| File | What it checks |
|---|---|
| `PluginBootTest.php` | Plugin and `src/` container load; AJAX actions registered by `HookManager` |
| `MetaBoxSaveTest.php` | Post editor save: code-field marker bypass, `unfiltered_html` gate, backslashes, rich text, stale template-global copies, nonce and capability checks |
| `AjaxHandlersTest.php` | AJAX handlers through admin-ajax: nonce and `manage_options` checks, imports sanitized against the site's schema, invalid schemas skipped, paginated post list |
| `ValidationPageTest.php` | Data Validation page: missing attachments detected, pagination, permission check |
| `ExportImportRoundTripTest.php` | Page data, settings and data object exports re-import unchanged |
| `UninstallTest.php` | `uninstall.php` removes all plugin data and leaves similarly named data alone |

Not covered: the meta box JSON file import (`is_uploaded_file()` only accepts real HTTP uploads;
the importer it calls is covered by the round-trip tests) and the file download handlers (they `exit`).
