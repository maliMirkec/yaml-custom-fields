# Releasing to WordPress.org

How to ship a new version.

## Before you release

- [ ] Version is the same in both places:
  - `yaml-custom-fields.php`: `Version: 2.0.0` (the code reads the version from here)
  - `readme.txt`: `Stable tag: 2.0.0`
- [ ] Changelog and upgrade notice in `readme.txt` are written.
- [ ] `Tested up to` in `readme.txt` is the newest WordPress version.
- [ ] `composer check:release` passes. It checks syntax and coding style, builds the libraries, runs the unit tests and the audit scripts.
- [ ] `composer test:integration` passes (see `tests/Integration/README.md`).
- [ ] You tested by hand with `MANUAL-TESTING.md`.
- [ ] You did the release checks in `RELEASE-TESTING-<version>.md` (if there is one).
- [ ] You tested on a fresh site **and** on a site updated from the old version.
- [ ] Everything is committed and pushed.

## Release the easy way (GitHub)

1. Push a tag with the version number:

   ```bash
   git tag 2.0.0
   git push origin 2.0.0
   ```

2. GitHub (`.github/workflows/deploy-to-wporg.yml`) then:
   - runs the unit tests;
   - builds the libraries;
   - sends the plugin to WordPress.org (SVN).

- It needs `SVN_USERNAME` and `SVN_PASSWORD` in the GitHub repo secrets.
- The update shows up on WordPress.org in about 15 minutes.

## Make a ZIP by hand

```bash
./build-scoped.sh
./package-for-wporg.sh
```

- This makes `yaml-custom-fields.zip`.
- It includes `build/vendor/` (users need it).
- It leaves out developer files. The list is in `.distignore`.

## Test the ZIP

1. Install the ZIP on a **fresh** WordPress site. Not your dev site.
2. Turn the plugin on.
3. Check the PHP error log. It should be empty.
4. Quick test:
   - Turn on fields for a template and add fields.
   - Fill them in on a page and save.
   - Fill in Site details and Shared content.
   - Add a collection and an item.
   - Check the values show on the website.
5. Check the browser console. No errors.
6. Turn the plugin off and on again.

## First time on WordPress.org

1. Go to https://wordpress.org/plugins/developers/add/
2. Upload the ZIP.
3. Wait for the review (1–2 weeks, sometimes more).
4. After approval, you get an SVN folder. The GitHub workflow uses it.

## Pictures (banners, icons, screenshots)

These go to the SVN `assets/` folder, not into the ZIP:

- `banner-772x250.png` and `banner-1544x500.png`: the header picture
- `icon-128x128.png` and `icon-256x256.png`: the plugin icon
- `screenshot-1.png`, `screenshot-2.png`, …: screenshots. Their captions are in `readme.txt` under `== Screenshots ==`.

## What ships and what doesn't

**In the ZIP:**
- The plugin PHP files, `src/`, `templates/`
- `admin-assets/` (CSS, JS)
- `build/vendor/` (the libraries)
- `languages/`
- `readme.txt`, `LICENSE.txt`, `uninstall.php`

**Not in the ZIP** (see `.distignore`):
- `vendor/` (developer tools)
- `composer.json`, `composer.lock`
- Build scripts, `bin/`, `scoper.inc.php`
- Tests and lint settings
- All `.md` files (they are for GitHub only)
- `.git` files

Users never need Composer. Everything is already built.

## Problems

**"Dependencies not found" after install**
- You forgot to build before packing.
- Fix: `./build-scoped.sh`, then `./package-for-wporg.sh`.

**The ZIP is too big**
- Developer files got in.
- Fix: check `.distignore`.

**Sites don't get the update**
- `Stable tag` in `readme.txt` doesn't match `Version` in `yaml-custom-fields.php`.
- Fix: make them the same.

## Links

- [Plugin Handbook](https://developer.wordpress.org/plugins/)
- [Plugin assets](https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/)
- [Plugin developer FAQ](https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/)

---

**Last updated:** 2026-09-28
**Plugin version:** 2.0.0
**Maintained by:** Silvestar Bistrović
