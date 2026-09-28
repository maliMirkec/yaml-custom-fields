# Building the Libraries

The plugin uses one outside library: **Symfony YAML** (version 6.4). It reads the field lists.

We copy the library into `build/vendor/` and give it our own name. The tool that does this is [PHP-Scoper](https://github.com/humbug/php-scoper).

## Why?

- Other plugins may use Symfony YAML too, maybe a different version.
- PHP can load only one of them. The other plugin (or ours) may break.
- So we rename ours: `Symfony\Component\Yaml` becomes `YamlCF\Vendor\Symfony\Component\Yaml`.
- Now both copies can live side by side.

## First time

```bash
composer install
./build-scoped.sh
```

## When to build again

Run `./build-scoped.sh` again when you:

- change `composer.json` or update a library (`composer update`);
- **add, remove or rename a class in `src/`.**

Why the second one? The built autoloader only knows the classes it saw at build time. A new class it doesn't know about won't load.

`composer check:release` builds first and then runs the tests, so the tests always use the build you ship.

## Folders

```
vendor/          - Tools for developers only. Not in git. Not shipped.
build/vendor/    - The renamed library and the autoloader. In git. Shipped.
scoper.inc.php   - PHP-Scoper settings
build-scoped.sh  - The build script
```

## What ships

- `build/vendor/` holds only what the plugin needs to run: symfony/yaml, polyfill-ctype and deprecation-contracts, with their licenses.
- No developer tools ship.
- Users never run Composer. Everything is already in the ZIP.

## Settings (`scoper.inc.php`)

- **Prefix:** `YamlCF\Vendor`
- **Output:** `build/vendor/`
- WordPress functions and classes are never renamed.

## Problems

**"Dependencies not found" message in the admin**
- `build/vendor/scoper-autoload.php` is missing.
- Fix: run `./build-scoped.sh`.

**A class "not found" error after you added a class**
- The build doesn't know the new class yet.
- Fix: run `./build-scoped.sh`.

**Warnings during the build**
- PHP-Scoper prints some PHP 8.3 deprecation warnings.
- They are harmless. The build is fine.

**Check that there are no conflicts**
1. Install another plugin that uses Symfony YAML.
2. Turn on both plugins.
3. Both should work.

## Links

- [PHP-Scoper docs](https://github.com/humbug/php-scoper)

---

**Last updated:** 2026-09-28
**Plugin version:** 2.0.0
**Maintained by:** Silvestar Bistrović
