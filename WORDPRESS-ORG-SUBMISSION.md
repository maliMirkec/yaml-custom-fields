# WordPress.org Checklist

What must be true before a version goes to WordPress.org.

## At a glance

- **Version:** 2.0.0 (not released yet; 1.2.9 is live)
- **Text domain:** `yaml-custom-fields`
- **Needs:** WordPress 6.4+, PHP 8.1+
- **Tested up to:** WordPress 7.1
- **Tests:** 263 unit, 579 integration. All pass on PHP 8.1, 8.3 and 8.4.
- **Coding style:** zero findings
- **Security scan:** good, 3 expected warnings (see [AUTOMATION-TOOLS.md](AUTOMATION-TOOLS.md))
- **Security review:** [SECURITY-AUDIT-REPORT.md](SECURITY-AUDIT-REPORT.md). 10 problems found, 10 fixed.

---

## Still to do for 2.0.0

- [ ] **New screenshots.** The current ones are from January 2026. The screens have changed a lot since then (icons, one field per row, Settings screen, new messages). Retake all five. Keep the captions in `readme.txt` in the same order.
- [ ] Run the readme validator: https://wordpress.org/plugins/developers/readme-validator/
- [ ] Run the Plugin Check plugin (see below).
- [ ] Do [RELEASE-TESTING-2.0.0.md](RELEASE-TESTING-2.0.0.md).
- [ ] Test the ZIP on a fresh site.

---

## 1. Files

**Already done**
- [x] `yaml-custom-fields.php` with a full header: name, description, version, needs, author, license, text domain, domain path.
- [x] `readme.txt` in the WordPress.org format.
- [x] `uninstall.php`. Removes all plugin data, unless the admin chose to keep it in **Settings**.
- [x] `LICENSE` and `LICENSE.txt` (GPL v2 or later).
- [x] `languages/yaml-custom-fields.pot`.
- [x] `.distignore` keeps developer files out of the ZIP.
- [x] Icons and banners in `assets/`, at the exact sizes:

| File | Size |
|---|---|
| `icon-128x128.png` | 128 × 128 |
| `icon-256x256.png` | 256 × 256 |
| `banner-772x250.png` | 772 × 250 |
| `banner-1544x500.png` | 1544 × 500 |

`assets/` goes to the WordPress.org SVN `assets/` folder. It is **not** in the ZIP.

---

## 2. Security

All of these are true. Check them again after big changes.

- [x] Every form and AJAX action checks a security token (nonce).
- [x] Admin-only actions use a second, admin-only token.
- [x] Every admin screen and action checks the user's rights. Plugin screens need `manage_options`. Writers only get the Custom fields box.
- [x] Every value is cleaned before saving. Code fields respect `unfiltered_html`.
- [x] Every output is escaped. Scripts build messages as text, not HTML.
- [x] Database queries use `$wpdb->prepare()`.
- [x] Uploaded files are checked with `is_uploaded_file()` and never copied to the public uploads folder.
- [x] No calls to outside servers.
- [x] No `eval`, `exec` or similar. `base64_decode` is only used for code fields.
- [x] Every PHP file stops if WordPress isn't loaded.
- [x] No debug code.

Run the scan: `bash bin/security-audit.sh`

---

## 3. Code quality

- [x] `composer lint` shows zero findings. No `phpcs:ignore` comments.
- [x] All classes use the `YamlCF\` namespace.
- [x] CSS and JS are loaded with `wp_enqueue_*`. No inline scripts.
- [x] Scripts only load on screens that need them.
- [x] The YAML library is renamed (`YamlCF\Vendor\…`), so it can't clash with other plugins.
- [x] Every text can be translated. Scripts use `wp.i18n`.

Run the scan: `bash bin/code-quality-check.sh`

---

## 4. Tests

- [x] `composer test:unit`: 263 tests
- [x] `composer test:integration`: 579 tests, in a real WordPress
- [x] GitHub runs both, plus lint, on every push.
- [ ] Plugin Check plugin:
  ```bash
  wp plugin install plugin-check --activate
  wp plugin check yaml-custom-fields
  ```
  Or use it from **Tools → Plugin Check** in the admin.

### Try it by hand

**Fresh site, only this plugin**
1. Install and turn on the plugin.
2. Try the main features: fields for a template, a page with fields, shared content, site details, a collection, export and import, settings.
3. Look for PHP errors, console errors and missing styles.
4. Delete the plugin. Check nothing is left in the database.

**Different setups**
- [ ] WordPress 6.4 (the oldest we support) and 7.1 (the newest).
- [ ] PHP 8.1 and 8.4.
- [ ] Classic editor and block editor.
- [ ] A classic theme, a child theme and a block theme (Twenty Twenty-Five).
- [ ] Multisite: turn on for the network and for one site. Data stays per site. Deleting cleans every site.
- [ ] With popular plugins (Yoast SEO, WooCommerce). No errors, no style clashes.

---

## 5. Make the ZIP

```bash
./build-scoped.sh
./package-for-wporg.sh
```

**In the ZIP**
- The plugin PHP files, `src/`, `templates/`, `admin-assets/`
- `build/vendor/`
- `languages/`
- `readme.txt`, `LICENSE.txt`, `uninstall.php`

**Not in the ZIP**
- `.git`, `vendor/`, `tests/`, `bin/`
- `composer.json`, `composer.lock`, `phpcs.xml.dist`, `scoper.inc.php`
- `.md` files
- `assets/` (goes to SVN separately)

Check the ZIP is small (under 5 MB) and works on a fresh site.

### Version number

Must match in:
- [ ] `yaml-custom-fields.php` → `Version: 2.0.0`
- [ ] `readme.txt` → `Stable tag: 2.0.0`
- [ ] `readme.txt` → changelog and upgrade notice for 2.0.0

---

## 6. Publish

The plugin is already on WordPress.org, so:

1. Push a version tag: `git tag 2.0.0 && git push origin 2.0.0`
2. GitHub tests, builds and sends it to SVN.
3. Upload new screenshots to the SVN `assets/` folder.

Full steps: [RELEASE.md](RELEASE.md).

**For a brand-new plugin (first time only):**
1. Make a WordPress.org account.
2. Go to https://wordpress.org/plugins/developers/add/ and upload the ZIP.
3. Wait for the review (1–14 days). Answer the reviewer quickly.
4. After approval, you get SVN access.

---

## 7. After release

- Check the support forum: https://wordpress.org/support/plugin/yaml-custom-fields/ Answer within a day or two.
- Read and answer reviews.
- Watch active installs in the WordPress.org dashboard.
- Fix security reports first, and fast.

---

## Why plugins get rejected

- Missing or broken `readme.txt`
- Security holes (XSS, SQL injection, missing nonces)
- No uninstall cleanup
- A trademark in the name
- Not GPL
- Calling outside servers without asking
- Hidden (obfuscated) code

None of these apply to this plugin.

## Links

- [Plugin Handbook](https://developer.wordpress.org/plugins/)
- [Plugin guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)
- [Security handbook](https://developer.wordpress.org/plugins/security/)
- [Developer dashboard](https://wordpress.org/plugins/developers/)
- [readme validator](https://wordpress.org/plugins/developers/readme-validator/)
- [Plugin Check](https://wordpress.org/plugins/plugin-check/)

---

**Last updated:** 2026-09-28
**Plugin version:** 2.0.0
