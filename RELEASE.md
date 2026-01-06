# Releasing to WordPress.org

This guide explains how to package and release the plugin to WordPress.org.

## Pre-Release Checklist

- [ ] All changes committed and pushed to GitHub
- [ ] Version number updated in `yaml-custom-fields.php`
- [ ] Version number updated in `readme.txt`
- [ ] Changelog updated in `readme.txt`
- [ ] Scoped dependencies built (`./build-scoped.sh`)
- [ ] Tested on a clean WordPress installation
- [ ] All WordPress.org plugin checks pass

## Creating a Release Package

### Option 1: Using the Package Script (Recommended)

```bash
./package-for-wporg.sh
```

This creates a ZIP file: `yaml-custom-fields.{version}.zip`

The script:
- ✅ Includes scoped dependencies (`build/vendor/`)
- ❌ Excludes development files (vendor/, composer.json, build scripts)
- ❌ Excludes assets (banners/icons - uploaded separately)

### Option 2: Manual Packaging

1. Ensure scoped dependencies are built:
   ```bash
   ./build-scoped.sh
   ```

2. Create a clean copy:
   ```bash
   mkdir -p dist/yaml-custom-fields
   rsync -av --exclude-from='.distignore' ./ dist/yaml-custom-fields/
   ```

3. Create ZIP:
   ```bash
   cd dist
   zip -r ../yaml-custom-fields.zip yaml-custom-fields
   ```

## Testing the Package

Before uploading, test the ZIP file:

1. Install on a **fresh WordPress site** (not your dev environment)
2. Activate the plugin
3. Verify no errors in PHP error log
4. Test all functionality:
   - Creating schemas
   - Saving data
   - Data objects
   - Template partials
5. Check browser console for JavaScript errors
6. Deactivate and reactivate to test cleanup

## Uploading to WordPress.org

### First-Time Submission

1. Submit via: https://wordpress.org/plugins/developers/add/
2. Upload the ZIP file (created with `package-for-wporg.sh`)
3. Wait for review (can take 1-2 weeks)
4. Once approved, you can upload updates and assets through the web interface

### Updating Existing Plugin

Once your plugin is approved, update it by uploading new ZIP files:

1. **Update version numbers:**
   - `yaml-custom-fields.php` header: `Version: 1.2.4`
   - `readme.txt` stable tag: `Stable tag: 1.2.4`
   - Update changelog in `readme.txt`

2. **Build new package:**
   ```bash
   ./build-scoped.sh
   ./package-for-wporg.sh
   ```

3. **Upload to WordPress.org:**
   - Go to: https://wordpress.org/plugins/developers/
   - Select your plugin
   - Upload the new ZIP file

4. **Plugin update is live within 15 minutes!**

### Uploading Assets (Banners, Icons, Screenshots)

Upload assets through the WordPress.org web interface:

1. Go to: https://wordpress.org/plugins/developers/
2. Select your plugin: yaml-custom-fields
3. Navigate to the Assets section
4. Upload your assets:
   - `banner-772x250.png` - Plugin header banner (small)
   - `banner-1544x500.png` - Plugin header banner (large)
   - `icon-128x128.png` - Plugin icon (small)
   - `icon-256x256.png` - Plugin icon (large)
   - `screenshot-1.png`, `screenshot-2.png`, etc. - Screenshots

**Note:** Assets are uploaded separately from the plugin ZIP and are not included in the distribution package.

## Important Notes

### What Gets Distributed

✅ **Included in user downloads:**
- Plugin PHP files
- Templates
- Assets (CSS, JS)
- **Scoped dependencies** (`build/vendor/`)
- `readme.txt`
- License

❌ **Excluded (via .distignore):**
- `vendor/` (unscoped - dev only)
- `composer.json`, `composer.lock`
- Build scripts (`build-scoped.sh`)
- Development configs (`scoper.inc.php`)
- Version control files (`.git`, `.gitignore`)
- Screenshots/banners (uploaded separately)

### Users Don't Need Composer

Because we use PHP-Scoper:
- ✅ Users get pre-built, scoped dependencies in `build/vendor/`
- ✅ No `composer install` required
- ✅ No conflicts with other plugins
- ✅ Plug-and-play installation

### Version Numbering

WordPress.org requires:
- Version in `yaml-custom-fields.php`: `Version: 1.2.2`
- Version in `readme.txt`: `Stable tag: 1.2.2`
- Both must match for proper updates

## Troubleshooting

### "Dependencies not found" Error

**Cause**: Forgot to run build script before packaging

**Solution**:
```bash
./build-scoped.sh
./package-for-wporg.sh
```

### ZIP Too Large

**Cause**: Development files included

**Solution**: Check `.distignore` is properly configured and used by packaging script

### Plugin Not Updating

**Cause**: `readme.txt` stable tag doesn't match plugin version

**Solution**: Ensure `Stable tag: 1.2.2` in readme.txt matches the version in `yaml-custom-fields.php`

## Resources

- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [Plugin Assets](https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/)
- [Release Checklist](https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/)
- [Plugin Developer Dashboard](https://wordpress.org/plugins/developers/)

---

**Last Updated:** 2025-12-28
**Plugin Version:** 1.2.2
**Maintained By:** Silvestar Bistrović
