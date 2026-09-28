# Integration Tests

These tests run inside a real WordPress. They use a real database, real users and real hooks.

> **Warning:** the tests **wipe their database** on every run. Use an empty database made just for tests. Never use a real site's database.

## How to run them

1. Install the tools and build the plugin's libraries:

   ```bash
   composer install
   ./build-scoped.sh
   ```

2. Download WordPress into a folder outside the plugin:

   ```bash
   mkdir -p /path/to/wordpress
   curl -sL https://wordpress.org/latest.tar.gz | tar -xz --strip-components=1 -C /path/to/wordpress
   ```

3. Make an empty MySQL database. Then run:

   ```bash
   WP_CORE_DIR=/path/to/wordpress \
   WP_TESTS_DB_NAME=wordpress_test \
   WP_TESTS_DB_USER=root \
   WP_TESTS_DB_PASSWORD='' \
   WP_TESTS_DB_HOST=127.0.0.1:3306 \
   composer test:integration
   ```

- Unit tests (`composer test:unit`) need none of this.
- GitHub runs both test sets on every push and pull request (`.github/workflows/tests.yml`).
- Right now: **579 tests, all passing.**

## What the tests check

| File | What it checks |
|---|---|
| `AdminScreenSmokeTest.php` | Every admin screen opens without errors |
| `AjaxHandlersTest.php` | The 10 AJAX actions: security checks, imports, the post list |
| `AjaxHardeningTest.php` | AJAX actions refuse bad or missing input |
| `AjaxSmokeTest.php` | Every AJAX action answers |
| `BlockNestedFieldsTest.php` | Fields inside sections save and load |
| `BundleCatalogTest.php` | The import preview matches what the import really does |
| `CollectionItemSaveTest.php` | Saving a collection item, and "Save and add another" |
| `DashboardTest.php` | The main screen |
| `DataObjectTypeSaveTest.php` | Saving a collection's fields |
| `EntryStoreTest.php` | Each collection item is stored on its own |
| `ExportImportRoundTripTest.php` | Export, then import, gives back the same data |
| `FieldResolutionTest.php` | Which value wins: page, shared or site details |
| `FieldValueFilterTest.php` | The `yaml_cf_field_value` filter runs for every value |
| `GalleryFieldTest.php` | Image lists (galleries) |
| `HookWiringTest.php` | Every hook is connected |
| `MetaBoxPlacementTest.php` | Where the Custom fields box appears |
| `MetaBoxRenderTest.php` | What the Custom fields box shows |
| `MetaBoxSaveTest.php` | Saving a post: security checks, code fields, backslashes, rich text |
| `NotificationsTest.php` | Messages after saving |
| `OptionAutoloadTest.php` | Big settings are not loaded on every page |
| `OrphanCleanupTest.php` | Finding and removing values whose field is gone |
| `PickerTest.php` | The search box that pickers show when there are many choices |
| `PluginBootTest.php` | The plugin starts |
| `PostSchemaLocatorTest.php` | Which fields a post gets |
| `PublicApiSmokeTest.php` | Theme functions like `yaml_cf_get_field()` |
| `RemovedFieldRetentionTest.php`, `RemovedFieldRetentionEverywhereTest.php` | Values of removed fields are kept, not lost |
| `RenderBlockItemTest.php` | The "Add section" button |
| `RestApiTest.php` | Fields in the REST API |
| `SchemaSourceTest.php` | Which fields each screen edits |
| `SchemaTextTest.php` | Field lists (YAML) survive saving and importing unchanged |
| `SettingsScreenTest.php` | The Settings screen |
| `SharedFieldsShorthandTest.php` | Shortcuts like `- info:` and `- section:` in shared fields |
| `SharedOnlyTemplateTest.php` | A template with only shared fields; Copy buttons on read-only fields |
| `ShortcodeTest.php` | The `[ycf]` shortcode (for block themes) |
| `SiteSaveCacheClearingTest.php` | Page caches are cleared after site-wide changes |
| `StaleFormTest.php` | A form sent after its security token ran out says so, instead of losing the input |
| `TemplateHierarchyResolutionTest.php`, `ThemeTemplateDiscoveryTest.php` | Finding the theme's templates |
| `UninstallTest.php` | Deleting the plugin removes its data, and only its data |
| `UnknownFieldTypeTest.php` | A misspelled field type is caught, and its content is kept |
| `UpgradeTriggerTest.php` | The one-time data update waits for an administrator |
| `ValidationPageTest.php` | The Data Validation page |

## Not tested here

- Importing a JSON file from the Custom fields box. PHP only accepts real browser uploads. The import code itself is tested by the export/import tests.
- File downloads. They stop PHP when done, so a test can't check them.
