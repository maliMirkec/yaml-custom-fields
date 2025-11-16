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
2. Upload the ZIP file
3. Wait for review (can take 1-2 weeks)
4. Once approved, you'll get SVN access

### Updating Existing Plugin

You have two options: **SVN** or **GitHub Actions**.

#### Option A: Using SVN

1. Check out the plugin SVN repo:
   ```bash
   svn co https://plugins.svn.wordpress.org/yaml-custom-fields
   cd yaml-custom-fields
   ```

2. Extract your ZIP into the `trunk` folder:
   ```bash
   unzip ../yaml-custom-fields.1.2.0.zip
   rsync -av --delete yaml-custom-fields/ trunk/
   ```

3. Add new files:
   ```bash
   svn add trunk/* --force
   ```

4. Remove deleted files:
   ```bash
   svn status | grep '^!' | awk '{print $2}' | xargs svn delete
   ```

5. Review changes:
   ```bash
   svn status
   svn diff | less
   ```

6. Commit to trunk:
   ```bash
   svn ci -m "Updating to version 1.2.0"
   ```

7. Create a release tag:
   ```bash
   svn cp trunk tags/1.2.0
   svn ci -m "Tagging version 1.2.0"
   ```

8. The plugin will be live within 15 minutes!

#### Option B: Using GitHub Actions (If Set Up)

If you have the WordPress.org deployment action configured:

1. Push to GitHub:
   ```bash
   git push origin main
   ```

2. Create a release tag:
   ```bash
   git tag 1.2.0
   git push origin 1.2.0
   ```

3. GitHub Action automatically deploys to WordPress.org

### Uploading Assets (Banners, Icons, Screenshots)

Assets go in the `/assets` folder in SVN (not `/trunk`):

```bash
cd yaml-custom-fields
svn add assets/*
svn ci -m "Updating plugin assets"
```

Assets you can upload:
- `banner-772x250.png` - Plugin header banner (small)
- `banner-1544x500.png` - Plugin header banner (large)
- `icon-128x128.png` - Plugin icon (small)
- `icon-256x256.png` - Plugin icon (large)
- `screenshot-1.png`, `screenshot-2.png`, etc. - Screenshots

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
- Version in `yaml-custom-fields.php`: `Version: 1.2.0`
- Version in `readme.txt`: `Stable tag: 1.2.0`
- Must match the SVN tag: `tags/1.2.0`

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

### Plugin Not Appearing After Tag

**Cause**: `readme.txt` stable tag doesn't match SVN tag

**Solution**: Ensure `Stable tag: 1.2.0` in readme.txt matches your SVN tag

## Resources

- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [SVN Guide](https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/)
- [Plugin Assets](https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/)
- [Release Checklist](https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/)
