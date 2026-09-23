# Security Audit Report - YAML Custom Fields

**Date:** 2026-09-22
**Version audited:** 1.2.9 (fixes below are in the unreleased changes that follow it)
**Method:** Manual code review of `yaml-custom-fields.php`, `src/`, `templates/`, `admin-assets/`, build and CI files. Fixes are covered by unit tests and by integration tests in a WordPress test environment; a manual browser pass is still pending.

This replaces the earlier report for 1.2.1, which predates the issues below.

---

## Summary

| Severity | Found | Fixed | Open |
|---|---|---|---|
| High | 3 | 3 | 0 |
| Medium | 4 | 4 | 0 |
| Low | 3 | 3 | 0 |

The plugin's baseline is solid: every form, AJAX and export handler checks a nonce and a capability, redirects use `wp_safe_redirect`, and most output is escaped. The serious issues were in how field data was **sanitized and decoded**, and they were reachable by any user who can edit a post (Contributor and up), not only administrators.

---

## Findings

### High

**H1. Sanitization bypass via the code-field marker (fixed)**
- Before: any value starting with `__YAMLCF_B64__` was stored untouched regardless of field type, and the public API base64-decoded it on read. A Contributor could store raw HTML/JS in a `string` field.
- Fix: `YamlCF\Form\DataSanitizer` strips the marker from every non-code field. The public API only decodes fields that the matching schema defines as `code`.

**H2. Code fields ignored `unfiltered_html` (fixed)**
- Before: code fields were stored raw for every user, although the docs promised `wp_kses_post()` for non-admins.
- Fix: users without `unfiltered_html` get `wp_kses_post()` (HTML) or dangerous-pattern removal (CSS). This is the same model as the core Custom HTML block.

**H3. Per-post JSON import stored unsanitized data (fixed)**
- Before: the meta box import (`edit_post` capability) wrote the file's `data` and `schema` straight to post meta. When no post matched, it silently imported the first post in the file.
- Fix: data is sanitized against the site's own schema, the file's schema is ignored, and a missing match is an error.

### Medium

**M1. Admin imports stored unsanitized data (fixed)**
- Settings, page data and data object imports now validate imported schemas, sanitize all values against the effective schemas, and sanitize slugs and keys. Page-data import checks `edit_post` for each target post.

**M2. XSS sinks in admin JavaScript (fixed)**
- Export/import results inserted post titles and values from the import file (source URL, dates, errors) with `.html()`. They are now escaped or built as DOM nodes.

**M3. Read-only global fields were submitted with the post (fixed)**
- These fields shared `yaml_cf[...]` names with editable fields, so a site-global value could overwrite a local one, and template-global values were frozen as stale copies in post meta.
- Fix: read-only fields render inside a disabled `<fieldset>`. Stale copies are ignored on read and dropped on the next save.

**M4. Validation scan ran without a capability check (fixed)**
- `admin.php?page=yaml-cf-data-validation` ran an unbounded query on `admin_init` for any logged-in user. It now requires `manage_options`.

### Low

**L1. Arbitrary text in admin notices via `error_msg` (fixed)**: schema errors now travel in a per-user transient, not the URL.

**L2. JSON upload handling (fixed)**: the upload is read from PHP's temporary file, confirmed with `is_uploaded_file()`. It is never copied into the public uploads folder, and no global upload filters are added.

**L3. Request and storage handling (fixed)**:
- `RequestHelper` now reads `$_GET`/`$_POST` with a single `wp_unslash()`. Before, it used `filter_input()` plus `wp_unslash()`, which stripped real backslashes from submitted data, and `getParam()` HTML-encoded values twice.
- Post meta is saved with `wp_slash()`, because `update_post_meta()` unslashes its value. Without this, backslashes in non-code fields were lost on every save. This corrupted data rather than creating a security hole; the integration tests cover it.

---

## Other hardening in the same changes
- `uninstall.php` is the only uninstall path. It escapes its `LIKE` patterns, uses the options/meta/transient APIs, and no longer flushes the whole object cache.
- `build/vendor` ships only the production dependencies (symfony/yaml, polyfill-ctype, deprecation-contracts) with their licenses. It no longer ships symfony/console, other dev packages or a Windows `.exe`.
- The deploy workflow runs the unit tests first, pins actions to commit SHAs, and uses read-only permissions.
- Data object type schemas are validated when saved in the admin.
- The Data Validation page now actually detects missing attachments. It passed the whole stored schema where the validator expected its field list, so it never found any.
- All AJAX handlers live in `src/Ajax/Handlers`. They share one nonce and capability check, and use the same import/export services as the rest of the plugin.

## Residual risks and notes
- **Theme output is the theme's responsibility.** Code fields are meant to be echoed raw. Every other field should be escaped in templates (`esc_html`, `esc_url`, `wp_kses_post`), as the docs now show.
- **Existing data** stored before these changes is not rewritten. The read-side checks cover top-level fields. Values nested inside blocks or objects are re-sanitized the next time the post, entry or settings are saved.
- When a user without `unfiltered_html` saves a post, code previously entered by an administrator is filtered.
- The Export/Import post list and the Data Validation page are paginated. Cache clearing after a global change still collects the IDs of every post with field data (IDs only).

## Verification
- Unit tests: `composer test:unit`. `tests/Unit/Form/DataSanitizerTest.php` covers the marker bypass, the `unfiltered_html` gate, nested names, blocks and lists.
- Integration tests: `composer test:integration` (see `tests/Integration/README.md`). They cover the meta box save, AJAX nonce and capability checks, import sanitization, pagination, the validation page, export/import round trips and uninstall. CI runs both suites on PHP 8.1, 8.3 and 8.4.
- Manual checks, still pending in a browser:
  1. As a Contributor, a marker-prefixed string field value comes back as plain text.
  2. As a Contributor, a crafted JSON import is sanitized.
  3. A post titled `<img src=x onerror=alert(1)>` shows as text on the Export/Import page.
  4. Template-global edits reach posts saved earlier.
