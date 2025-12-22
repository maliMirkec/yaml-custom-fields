# WordPress Plugin Directory Submission Checklist

This document outlines everything needed to submit YAML Custom Fields to the WordPress Plugin Directory and ensure production-ready code quality.

## Table of Contents

- [✅ Completed Items](#-completed-items)
- [⚠️ Required Before Submission](#️-required-before-submission)
- [🔍 Recommended Improvements](#-recommended-improvements)
- [📋 Pre-Submission Checklist](#-pre-submission-checklist)
- [🚀 Submission Process](#-submission-process)

---

## ✅ Completed Items

### Code Quality & Standards
- [x] **WordPress Coding Standards (PHPCS)** - All PHP code passes PHPCS validation
- [x] **Comprehensive Testing** - 120 unit tests covering core functionality
- [x] **Documentation** - README-TESTING.md and MANUAL-TESTING.md created
- [x] **readme.txt** - Comprehensive readme.txt with proper formatting
- [x] **Proper Namespacing** - All classes use `YamlCF\` namespace
- [x] **Vendor Dependencies** - Symfony YAML properly scoped to avoid conflicts
- [x] **.distignore** - Excludes development files from distribution

### Security (Current State)
- [x] **Data Sanitization** - Using `sanitize_text_field()`, `wp_kses()`, etc.
- [x] **Output Escaping** - Using `esc_html()`, `esc_attr()`, `esc_url()`
- [x] **Administrator-only Access** - All admin pages check `manage_options` capability
- [x] **No External Requests** - Plugin doesn't phone home
- [x] **GPL License** - GPLv2 compatible

### Plugin Files
- [x] **Main Plugin File** - `yaml-custom-fields.php` with proper headers
- [x] **LICENSE** - GPL-2.0 license file
- [x] **README.md** - GitHub readme
- [x] **readme.txt** - WordPress.org formatted readme
- [x] **.distignore** - Distribution exclusions

---

## ⚠️ Required Before Submission

### Critical: Security Enhancements

#### 1. **Create uninstall.php** (REQUIRED)
**Status:** ❌ Missing
**Priority:** CRITICAL
**Why:** WordPress requires clean uninstall functionality

**Action Needed:**
Create `/uninstall.php` to remove all plugin data on deletion:
```php
<?php
// Prevent direct access
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete all options
delete_option('yaml_cf_schemas');
delete_option('yaml_cf_global_schema');
delete_option('yaml_cf_global_data');
delete_option('yaml_cf_partial_data');
delete_option('yaml_cf_template_settings');
delete_option('yaml_cf_template_global_schemas');
delete_option('yaml_cf_template_global_data');
delete_option('yaml_cf_data_object_types');

// Delete data object entries (dynamic keys)
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'yaml_cf_data_object_entries_%'");

// Delete all post meta
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_yaml_cf_%'");

// Clear any cached data
wp_cache_flush();
```

#### 2. **Nonce Verification Audit** (REQUIRED)
**Status:** ⚠️ Needs Review
**Priority:** CRITICAL
**Why:** All form submissions and AJAX requests must verify nonces

**Files to Audit:**
- `src/Ajax/*.php` - All AJAX handlers
- `src/Form/FormHandler.php` - Form submissions
- `src/Admin/Controllers/*.php` - Admin form handlers

**Action Needed:**
Verify every AJAX action and form submission has:
```php
// Check nonce
if (!wp_verify_nonce($_POST['nonce'], 'action_name')) {
    wp_die('Security check failed');
}

// Check capabilities
if (!current_user_can('manage_options')) {
    wp_die('Insufficient permissions');
}
```

#### 3. **SQL Injection Prevention** (VERIFY)
**Status:** ⚠️ Needs Review
**Priority:** HIGH
**Why:** Any direct database queries must use prepared statements

**Action Needed:**
Search for any direct `$wpdb` queries and verify they use `$wpdb->prepare()`:
```bash
grep -r "\$wpdb->query\|\$wpdb->get_" src/ --include="*.php"
```

If found, ensure all use prepared statements:
```php
// BAD
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name = '{$name}'");

// GOOD
$wpdb->query($wpdb->prepare(
    "DELETE FROM {$wpdb->options} WHERE option_name = %s",
    $name
));
```

#### 4. **File Upload Security** (VERIFY)
**Status:** ⚠️ Needs Review
**Priority:** HIGH
**Why:** File uploads must validate file types and prevent malicious uploads

**Files to Check:**
- Anywhere handling `$_FILES`
- Import functionality

**Action Needed:**
Verify file upload validation:
```php
// Check file type
$allowed_types = ['application/json'];
if (!in_array($_FILES['file']['type'], $allowed_types)) {
    wp_die('Invalid file type');
}

// Check file size
if ($_FILES['file']['size'] > 5000000) { // 5MB
    wp_die('File too large');
}

// Validate JSON content
$content = file_get_contents($_FILES['file']['tmp_name']);
json_decode($content); // Validate it's valid JSON
if (json_last_error() !== JSON_ERROR_NONE) {
    wp_die('Invalid JSON file');
}
```

---

## 🔍 Recommended Improvements

### Internationalization (i18n)

#### Add Text Domain Loading
**Priority:** MEDIUM
**Why:** Makes plugin translatable

**Action Needed:**
In main plugin file, add:
```php
function yaml_cf_load_textdomain() {
    load_plugin_textdomain('yaml-custom-fields', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'yaml_cf_load_textdomain');
```

#### Wrap All Strings
**Priority:** MEDIUM
**Example:**
```php
// Current
echo 'Schema saved successfully';

// Should be
echo __('Schema saved successfully', 'yaml-custom-fields');

// For escaping
echo esc_html__('Schema saved successfully', 'yaml-custom-fields');
```

Create `/languages/` directory for translation files.

### Performance Optimizations

#### Transient Caching
**Priority:** LOW
**Why:** Reduce database queries for frequently accessed data

**Example:**
```php
// Check transient before querying
$schemas = get_transient('yaml_cf_all_schemas');
if (false === $schemas) {
    $schemas = get_option('yaml_cf_schemas', []);
    set_transient('yaml_cf_all_schemas', $schemas, HOUR_IN_SECONDS);
}
```

#### Lazy Loading
**Priority:** LOW
**Why:** Only load heavy components when needed

Already good: Using `require_once` strategically.

### Code Documentation

#### Add PHPDoc Blocks
**Priority:** LOW
**Current:** Some functions have docs
**Recommended:** All public methods should have PHPDoc

**Example:**
```php
/**
 * Get custom field value
 *
 * @since 1.0.0
 * @param string $field_name Field name to retrieve
 * @param int|null $post_id Optional post ID, defaults to current post
 * @return mixed Field value or null
 */
function ycf_get_field($field_name, $post_id = null) {
    // ...
}
```

---

## 📋 Pre-Submission Checklist

### File Structure
- [x] Main plugin file in root (`yaml-custom-fields.php`)
- [x] README.txt in WordPress format
- [ ] uninstall.php in root (TO CREATE)
- [x] LICENSE file
- [ ] /languages/ directory (TO CREATE)
- [x] /assets/ directory for screenshots
- [x] /build/ for compiled dependencies
- [ ] /assets/ for plugin directory assets (banner, icon)

### Plugin Header
Verify `yaml-custom-fields.php` has:
- [x] Plugin Name
- [x] Description
- [x] Version number
- [x] Author name
- [x] License (GPLv2 or later)
- [x] Text Domain
- [ ] Domain Path (add: `Domain Path: /languages`)

### readme.txt Validation
Run through WordPress readme validator:
- [ ] Visit: https://wordpress.org/plugins/developers/readme-validator/
- [ ] Paste your readme.txt
- [ ] Fix any validation errors

### Screenshots
- [ ] Create /assets/ directory in SVN (not in plugin ZIP)
- [ ] Add screenshot-1.png (1200x900 recommended)
- [ ] Add screenshot-2.png
- [ ] Add screenshot-3.png
- [ ] Screenshots match descriptions in readme.txt

### Plugin Assets (For Plugin Directory)
- [ ] Create plugin icon: icon-128x128.png and icon-256x256.png
- [ ] Create plugin banner: banner-772x250.png and banner-1544x500.png
- [ ] Place in /assets/ directory in SVN

### Security Audit
- [ ] Run security scan with Plugin Check plugin
- [ ] Verify all nonces
- [ ] Check all capability checks
- [ ] Audit all database queries
- [ ] Test file upload security
- [ ] Check for XSS vulnerabilities
- [ ] Verify CSRF protection

### Compatibility Testing
- [ ] Test with WordPress 5.0
- [ ] Test with WordPress 6.9 (latest)
- [ ] Test with PHP 7.4
- [ ] Test with PHP 8.0
- [ ] Test with PHP 8.1
- [ ] Test with PHP 8.2
- [ ] Test with classic editor
- [ ] Test with Gutenberg editor
- [ ] Test with Twenty Twenty-Four theme
- [ ] Test with Twenty Twenty-Three theme

### Multisite Testing
- [ ] Activate on network
- [ ] Activate on individual sites
- [ ] Test data isolation between sites
- [ ] Test uninstall on multisite

### Conflict Testing
- [ ] Test with popular plugins (Yoast SEO, WooCommerce, etc.)
- [ ] Check for JavaScript conflicts
- [ ] Check for CSS conflicts
- [ ] Verify no fatal errors in combination

### Final Code Review
- [ ] No var_dump(), print_r(), or debugging code
- [ ] No hardcoded paths or URLs
- [ ] All assets enqueued properly (no direct links)
- [ ] No direct file access (check `defined('ABSPATH')`)
- [ ] No eval() or base64_decode() usage
- [ ] No system() or exec() calls
- [ ] No $_GET/$_POST without sanitization
- [ ] No output before headers

---

## 🚀 Submission Process

### Step 1: Create SVN Account
1. Visit: https://wordpress.org/plugins/developers/add/
2. Fill out plugin submission form
3. Provide plugin ZIP or GitHub URL
4. Wait for approval email (typically 1-14 days)

### Step 2: Prepare Plugin ZIP
```bash
# Build production version
php composer.phar install --no-dev
bash build-scoped.sh

# Create distribution ZIP (already have script)
bash package-for-wporg.sh
```

Verify ZIP contains:
- No test files
- No development files (.git, node_modules, etc.)
- Built /build/ directory
- All necessary PHP files

### Step 3: Setup SVN (After Approval)
```bash
# Checkout plugin repository
svn co https://plugins.svn.wordpress.org/yaml-custom-fields yaml-custom-fields-svn

cd yaml-custom-fields-svn

# Create directories
mkdir trunk tags assets

# Copy plugin files to trunk/
cp -r /path/to/plugin/* trunk/

# Copy screenshots and assets to assets/
cp /path/to/screenshots/* assets/

# Add files to SVN
svn add trunk/*
svn add assets/*

# Commit to SVN
svn ci -m "Initial commit of YAML Custom Fields v1.2.1"

# Create tag for version
svn cp trunk tags/1.2.1
svn ci -m "Tagging version 1.2.1"
```

### Step 4: Submit for Review
- Plugin automatically appears in directory after SVN commit
- WordPress.org runs automated checks
- Address any issues flagged by review team

---

## 🔒 Security Best Practices Checklist

### Input Validation
- [ ] All `$_GET` parameters sanitized with `filter_input()` or `sanitize_text_field()`
- [ ] All `$_POST` parameters sanitized
- [ ] All `$_FILES` validated (type, size, content)
- [ ] All user input escaped on output

### Authentication & Authorization
- [ ] All admin pages check `current_user_can('manage_options')`
- [ ] All AJAX actions verify nonces
- [ ] All form submissions verify nonces
- [ ] No admin functionality accessible without proper permissions

### Data Handling
- [ ] All database queries use `$wpdb->prepare()`
- [ ] No direct SQL execution with user input
- [ ] All meta data sanitized before save
- [ ] All option data sanitized before save

### Output Escaping
- [ ] All HTML output uses `esc_html()`
- [ ] All attributes use `esc_attr()`
- [ ] All URLs use `esc_url()`
- [ ] All JavaScript data uses `wp_json_encode()`

---

## 📊 Plugin Check Plugin

Before submission, install and run the Plugin Check plugin:

```bash
# Install Plugin Check
wp plugin install plugin-check --activate

# Run checks
wp plugin-check run yaml-custom-fields
```

This will check for:
- Code standards
- Security issues
- Accessibility
- Performance
- Best practices

---

## 🎯 Priority Action Items

### Before Submission (MUST DO)
1. **Create uninstall.php** - 30 minutes
2. **Audit nonce verification** - 2 hours
3. **Verify SQL query safety** - 1 hour
4. **Test file upload security** - 1 hour
5. **Add text domain loading** - 15 minutes
6. **Create plugin assets** (icon, banner) - 1 hour
7. **Run Plugin Check** - 30 minutes
8. **Test on fresh WordPress install** - 1 hour

**Total Estimated Time: ~7-8 hours**

### After Submission (NICE TO HAVE)
1. Add internationalization to all strings
2. Create translation .pot file
3. Performance optimization (transient caching)
4. Add more PHPDoc blocks
5. Create video tutorials

---

## 📝 Notes

### Common Rejection Reasons
- Missing or incomplete readme.txt
- Security vulnerabilities (XSS, SQL injection, CSRF)
- No uninstall cleanup
- Trademark violations in name
- GPL license issues
- Phone-home functionality
- Obfuscated code

### Tips for Approval
- Clear, descriptive readme.txt
- Comprehensive security measures
- Follow WordPress Coding Standards
- Provide good documentation
- Test thoroughly before submission
- Respond quickly to reviewer feedback

---

## ✅ Final Checklist Before Upload

- [ ] Version number updated in:
  - [ ] Main plugin file header
  - [ ] readme.txt (Stable tag)
  - [ ] Package.json (if exists)
- [ ] Changelog updated in readme.txt
- [ ] All files tested locally
- [ ] ZIP file created and tested on fresh install
- [ ] No development/test files in ZIP
- [ ] Screenshots added to /assets/
- [ ] Plugin icon and banner created
- [ ] uninstall.php created and tested
- [ ] All security checks passed
- [ ] Plugin Check passed
- [ ] readme.txt validated

---

## 🆘 Resources

- [Plugin Handbook](https://developer.wordpress.org/plugins/)
- [Plugin Directory Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)
- [readme.txt Validator](https://wordpress.org/plugins/developers/readme-validator/)
- [Plugin Check](https://wordpress.org/plugins/plugin-check/)
- [Security Best Practices](https://developer.wordpress.org/plugins/security/)
- [SVN Guide](https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/)

---

**Last Updated:** 2025-12-21
**Plugin Version:** 1.2.1
**Prepared By:** Claude Code Testing Suite
