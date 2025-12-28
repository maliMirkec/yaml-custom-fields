# Building Scoped Dependencies

This plugin uses [PHP-Scoper](https://github.com/humbug/php-scoper) to prevent dependency conflicts with other WordPress plugins.

## Why Scoping?

The plugin uses Symfony YAML (v5.4), which is a common dependency in WordPress plugins. Without scoping:
- If another plugin uses a different version of Symfony YAML, only one version loads
- This causes conflicts and can break one or both plugins
- It's a "race condition" - whichever plugin loads first wins

With PHP-Scoper:
- All Symfony namespaces are prefixed with `YamlCF\Vendor\`
- Example: `Symfony\Component\Yaml` becomes `YamlCF\Vendor\Symfony\Component\Yaml`
- Both versions can coexist without conflicts

## Development Workflow

### Initial Setup

1. Install dependencies (including PHP-Scoper):
   ```bash
   composer install
   ```

2. Build scoped dependencies:
   ```bash
   ./build-scoped.sh
   ```

### Making Changes

If you update `composer.json` or add new dependencies:

1. Update dependencies:
   ```bash
   composer update
   ```

2. Rebuild scoped dependencies:
   ```bash
   ./build-scoped.sh
   ```

3. Test the plugin to ensure everything works

### Directory Structure

```
/vendor/           - Unscoped dependencies (gitignored, dev only)
/build/vendor/     - Scoped dependencies (committed to repo)
scoper.inc.php     - PHP-Scoper configuration
build-scoped.sh    - Build script
```

## Testing Scoped Dependencies

Run the test script to verify scoping works:

```bash
php test-scoped.php
```

Expected output:
```
✅ Test 1 PASSED: Simple YAML parsing works
✅ Test 2 PASSED: Namespace is scoped
✅ All tests passed! Scoped dependencies are working correctly.
```

## Configuration

The scoping configuration is in `scoper.inc.php`:

- **Prefix**: `YamlCF\Vendor` - All namespaces get this prefix
- **Output**: `build/vendor/` - Where scoped files are generated
- **Exclusions**: WordPress functions/classes are never scoped
- **Patchers**: Custom transformations if needed

## Distribution

When distributing the plugin:

1. Ensure `build/vendor/` is committed to git
2. **Do NOT** commit the `vendor/` directory
3. Users don't need to run Composer - scoped dependencies are included

## Troubleshooting

### "Dependencies not found" error

The plugin shows this if `build/vendor/scoper-autoload.php` doesn't exist.

**Solution**: Run `./build-scoped.sh`

### PHP deprecation warnings during build

The build script suppresses PHP 8.3 deprecation warnings from PHP-Scoper dependencies.
This is normal and doesn't affect the scoped output.

### Testing for conflicts

To verify there are no conflicts:

1. Install another plugin that uses Symfony YAML
2. Activate both plugins
3. Both should work without errors

## Resources

- [PHP-Scoper Documentation](https://github.com/humbug/php-scoper)
- [Delicious Brains Article](https://deliciousbrains.com/php-scoper-namespace-composer-depencies/)

---

**Last Updated:** 2025-12-28
**Plugin Version:** 1.2.2
**Maintained By:** Silvestar Bistrović
