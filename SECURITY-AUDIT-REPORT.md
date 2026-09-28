# Security Audit Report

**Plugin:** YAML Custom Fields
**Last checked:** 2026-09-28
**Version:** 2.0.0 (not released yet). The problems below were found in 1.2.9 and are fixed in 2.0.0.
**How:** We read all the code by hand: `yaml-custom-fields.php`, `src/`, `templates/`, `admin-assets/`, `uninstall.php`, and the build and GitHub files. Every fix has a test.

---

## Summary

| How bad | Found | Fixed | Still open |
|---|---|---|---|
| High | 3 | 3 | 0 |
| Medium | 4 | 4 | 0 |
| Low | 3 | 3 | 0 |

**The good parts, even before:**
- Every form, AJAX action and download checks a security token (nonce) and the user's rights.
- Redirects use `wp_safe_redirect()`.
- Most output was escaped.

**The bad parts:** how field values were **cleaned and decoded**. Anyone who can write a post (Contributor and up) could reach them, not only admins.

---

## What we found

### High

**H1. A trick to skip cleaning (fixed)**
- Before: a value starting with `__YAMLCF_B64__` was saved as-is, in any field. A Contributor could put raw HTML or JavaScript in a text field.
- Now: the plugin removes that marker from every field that is not a code field. Theme functions only decode code fields.

**H2. Code fields let anyone save raw code (fixed)**
- Before: code fields saved raw code for every user.
- Now: users without the `unfiltered_html` right get cleaned code. HTML goes through `wp_kses_post()`; CSS loses dangerous parts. WordPress's own Custom HTML block works the same way.

**H3. Importing a JSON file in the Custom fields box saved anything (fixed)**
- Before: the import saved the file's values and fields as they were. If no post matched, it quietly imported the first post in the file.
- Now: values are cleaned using the site's own fields. The file's fields are ignored. No match means an error message.

### Medium

**M1. Admin imports saved values without cleaning (fixed)**
- Imports for settings, page data and collections now check the fields, clean every value, and clean names and slugs.
- Page data import checks that you may edit each post.

**M2. Admin scripts could run code from an import file (fixed)**
- Post titles and values from the file were inserted as HTML.
- Now they are escaped, or built safely.

**M3. Read-only values were sent with the post (fixed)**
- Site details and shared values had the same field names as the page's own fields. A site value could overwrite a page value, and old copies of shared values got stuck in the post.
- Now read-only values sit in a disabled `<fieldset>`, so the browser doesn't send them. Their Copy buttons stay outside it, so they still work.
- Old stuck copies are ignored and removed on the next save.

**M4. The Data Validation page had no rights check (fixed)**
- Any logged-in user could start a big scan.
- Now it needs `manage_options` (admins only).

### Low

**L1. Any text could be put in an admin message (fixed)**
- Errors traveled in the web address (`error_msg`).
- Now they are stored for that one user for a short time (a transient).

**L2. Uploaded JSON files (fixed)**
- The plugin reads the file from PHP's temporary folder and checks it with `is_uploaded_file()`.
- It never copies it to the public uploads folder.

**L3. Backslashes lost when saving (fixed)**
- The plugin read form values in a way that removed real backslashes, and escaped some values twice.
- Post meta is now saved with `wp_slash()`, because WordPress removes one level of backslashes when it saves.
- This was a data bug, not a security hole. Tests cover it.

---

## Other safety work in 2.0.0

- `uninstall.php` is the only way data is removed. It uses the WordPress APIs, escapes its search patterns, and no longer empties the whole object cache.
- `build/vendor` ships only what the plugin needs to run (symfony/yaml, polyfill-ctype, deprecation-contracts) and their licenses. No developer tools, no `.exe`.
- The GitHub release runs the unit tests first, pins every action to an exact version, and has read-only rights.
- Collection fields are checked when you save them.
- The Data Validation page really finds missing images and files now. Before, it never found any.
- All 10 AJAX actions live in `src/Ajax/Handlers`. They share one security check. Actions only admins may do use a separate admin-only token.
- Messages the plugin shows are escaped. Scripts build them as text, not HTML.

## Things to keep in mind

- **The theme must escape its output.** Code fields are meant to be printed raw. Every other field should be escaped in the theme (`esc_html`, `esc_url`, `wp_kses_post`). The docs show how.
- **Old data is not rewritten.** Top-level values are checked when read. Values inside sections are cleaned again the next time someone saves.
- If a user without `unfiltered_html` saves a post, code an admin put there earlier gets cleaned.
- After a site-wide change, clearing page caches still collects the IDs of all posts with field data (IDs only).

## How to check

- Unit tests: `composer test:unit` (263 tests). `tests/Unit/Form/DataSanitizerTest.php` covers the marker trick, the `unfiltered_html` rule, sections and lists.
- Integration tests: `composer test:integration` (579 tests). See `tests/Integration/README.md`. They cover saving posts, AJAX security checks, import cleaning, the validation page, export and import, and uninstall. GitHub runs both on PHP 8.1, 8.3 and 8.4.
- By hand, in a browser:
  1. As a Contributor, put `__YAMLCF_B64__` + some code in a text field. It comes back as plain text.
  2. As a Contributor, import a JSON file with HTML in it. The HTML is cleaned.
  3. Make a post titled `<img src=x onerror=alert(1)>`. The Export/Import page shows it as text, and nothing pops up.
  4. Change a shared value. Posts saved earlier show the new value.
