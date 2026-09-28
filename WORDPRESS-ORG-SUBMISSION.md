# WordPress.org Plugin Directory Submission Guide

## Quick Reference
- **Version:** 2.0.0
- **Text Domain:** yaml-custom-fields
- **Minimum PHP:** 8.1+ (CI covers 8.1, 8.3 and 8.4)
- **Minimum WordPress:** 6.4+
- **Tested up to:** 7.1
- **Test Coverage:** 247 unit tests (557 assertions), 446 integration tests (3,041 assertions)
- **Security Audit:** ✅ Passed (2026-09-25, 2 informational warnings)
- **Submission Status:** Ready — assets, `/languages/` and screenshots are all in place

---

## Table of Contents
1. [Pre-Submission Requirements](#1-pre-submission-requirements)
2. [Security Checklist](#2-security-checklist)
3. [Code Quality Standards](#3-code-quality-standards)
4. [Documentation Requirements](#4-documentation-requirements)
5. [Testing Checklist](#5-testing-checklist)
6. [Packaging Process](#6-packaging-process)
7. [WordPress.org Submission](#7-wordpressorg-submission)
8. [Post-Submission Monitoring](#8-post-submission-monitoring)
9. [Action Plan (Quick Start)](#9-action-plan-quick-start)

---

## 1. Pre-Submission Requirements

### ✅ Completed Items

#### Code Quality & Standards
- [x] **WordPress Coding Standards (PHPCS)** - All PHP code passes PHPCS validation
- [x] **Comprehensive Testing** - 247 unit tests (557 assertions) plus 446 integration tests (3,041 assertions) against a real WordPress
- [x] **Documentation** - TESTING.md and MANUAL-TESTING.md created
- [x] **readme.txt** - Comprehensive readme.txt with proper formatting
- [x] **Proper Namespacing** - All classes use `YamlCF\` namespace
- [x] **Vendor Dependencies** - Symfony YAML properly scoped to avoid conflicts
- [x] **.distignore** - Excludes development files from distribution

#### Security (Current State)
- [x] **Data Sanitization** - Using `sanitize_text_field()`, `wp_kses()`, etc. (65 instances)
- [x] **Output Escaping** - Using `esc_html()`, `esc_attr()`, `esc_url()` (262 instances)
- [x] **Administrator-only Access** - All admin pages check `manage_options` capability (35 checks)
- [x] **No External Requests** - Plugin doesn't phone home
- [x] **GPL License** - GPLv2 compatible
- [x] **CSRF Protection** - 12 AJAX nonce verifications, with a separate nonce for administrator-only actions
- [x] **SQL Injection Prevention** - All queries use prepared statements

#### Plugin Files
- [x] **Main Plugin File** - `yaml-custom-fields.php` with proper headers
- [x] **LICENSE** - GPL-2.0 license file
- [x] **README.md** - GitHub readme
- [x] **readme.txt** - WordPress.org formatted readme
- [x] **.distignore** - Distribution exclusions
- [x] **uninstall.php** - Created (cleans all plugin data on deletion)

### ✅ Assets and i18n (was "Required Before Submission")

All three items in this section are now done. Verified in the working tree on
2026-09-25; the sizes below were read off the files themselves.

**1. `/languages/` Directory** ✅ DONE
- `languages/` exists and holds `yaml-custom-fields.pot` (~49 KB) plus a README
- Plugin header declares `Domain Path: /languages`

**2. Plugin Icon & Banner** ✅ DONE

In `/assets/`, each at exactly the size WordPress.org asks for:

| File | Size |
| --- | --- |
| `icon-256x256.png` | 256 × 256 |
| `icon-128x128.png` | 128 × 128 |
| `banner-1544x500.png` | 1544 × 500 |
| `banner-772x250.png` | 772 × 250 |

`/assets/` is for the WordPress.org SVN repository and is **not** shipped in the
plugin ZIP — `.distignore` keeps it out.

**3. Screenshots** ✅ DONE

`screenshot-1.png` through `screenshot-5.png` are in `/assets/`. They are larger
than the 1200×900 that used to be recommended here (2530 × 1520); WordPress.org
scales them down, so no re-export is needed.

Each needs a matching caption under `== Screenshots ==` in `readme.txt`, in the
same order.

---

## 2. Security Checklist

### Security Audit Summary

**Date:** 2026-09-25
**Status:** ✅ **GOOD** - Ready for WordPress Plugin Directory

Figures below are what `bash bin/security-audit.sh` reports, so they can be
reproduced at any time. They use that script's own scoping; counting by hand over
`src/` and `templates/` gives higher totals.

**Quick Stats:**
- ✅ **0 Critical Security Issues**
- ⚠️ **2 Informational Warnings** (non-blocking, see below)
- ✅ **12 AJAX Nonce Verifications** (plus 5 nonce creations)
- ✅ **35 Capability Checks**
- ✅ **262 Output Escaping Instances**
- ✅ **65 Input Sanitization Instances**

**The two warnings, and why neither blocks submission:**
- *Direct superglobal access (2 instances)* - both are read through the plugin's
  own sanitising helpers.
- *`base64_decode` detected (4 instances)* - this is how code fields are stored
  and read back. Every call passes `strict: true`, and decoded content is
  re-sanitised before use.

### Completed Security Measures

#### 1. CSRF Protection (Excellent)
**Status:** ✅ Fully Implemented
- All AJAX endpoints use proper nonce verification
- All form submissions use `wp_verify_nonce()`
- Nonce created and passed to JavaScript via `wp_localize_script()`

#### 2. SQL Injection Prevention (Excellent)
**Status:** ✅ No Unsafe Queries Found
- All database queries use proper prepared statements
- No direct concatenation of user input in SQL
- Proper use of `$wpdb->prepare()` wherever needed

#### 3. Authorization & Access Control (Excellent)
**Status:** ✅ Properly Implemented
- All admin pages verify `current_user_can('manage_options')`
- All AJAX handlers check user capabilities before processing
- No functionality exposed to unauthorized users

#### 4. Output Escaping (Excellent)
**Status:** ✅ Comprehensive Coverage
- Uses `esc_html()`, `esc_attr()`, `esc_url()` appropriately
- All user-generated content properly sanitized before display

#### 5. Input Sanitization (Excellent)
**Status:** ✅ Well Implemented
- Uses `sanitize_text_field()`, `sanitize_key()`, `filter_input()`
- No direct access to `$_GET` or `$_POST` without sanitization

#### 6. No Dangerous Functions
**Status:** ✅ Clean
- No use of `eval()`, `base64_decode()`, `exec()`, `system()`, or `shell_exec()`
- No security anti-patterns detected

#### 7. No External Requests
**Status:** ✅ Perfect for Plugin Directory
- Plugin does not make any external HTTP requests
- No "phone home" functionality
- All operations are local to WordPress installation

### Security Pre-Flight Checklist

#### Input Validation
- [x] All `$_GET` parameters sanitized with `filter_input()` or `sanitize_text_field()`
- [x] All `$_POST` parameters sanitized
- [x] All `$_FILES` validated (type, size, content)
- [x] All user input escaped on output

#### Authentication & Authorization
- [x] All admin pages check `current_user_can('manage_options')`
- [x] All AJAX actions verify nonces
- [x] All form submissions verify nonces
- [x] No admin functionality accessible without proper permissions

#### Data Handling
- [x] All database queries use `$wpdb->prepare()`
- [x] No direct SQL execution with user input
- [x] All meta data sanitized before save
- [x] All option data sanitized before save

#### Output Escaping
- [x] All HTML output uses `esc_html()`
- [x] All attributes use `esc_attr()`
- [x] All URLs use `esc_url()`
- [x] All JavaScript data uses `wp_json_encode()`

---

## 3. Code Quality Standards

### ✅ Standards Compliance

- [x] **WordPress Coding Standards** - All PHP code passes PHPCS validation
- [x] **Proper Namespacing** - All classes use `YamlCF\` namespace
- [x] **No Debug Code** - No var_dump(), print_r(), or error_log() in production code
- [x] **No Hardcoded Paths** - All paths use WordPress constants
- [x] **Proper Asset Enqueuing** - All CSS/JS properly enqueued (no direct links)
- [x] **ABSPATH Protection** - All files check `defined('ABSPATH')`

### Final Code Review Checklist

- [ ] No var_dump(), print_r(), or debugging code
- [ ] No hardcoded paths or URLs
- [ ] All assets enqueued properly (no direct links)
- [ ] No direct file access (check `defined('ABSPATH')`)
- [ ] No eval() or base64_decode() usage
- [ ] No system() or exec() calls
- [ ] No $_GET/$_POST without sanitization
- [ ] No output before headers

### Internationalization (i18n)

#### Current Status
- [x] Text domain defined in plugin header: `yaml-custom-fields`
- [x] Domain path defined: `/languages`
- [x] Text domain loading function included
- [ ] /languages/ directory created (ACTION REQUIRED)
- [ ] All strings wrapped in translation functions (RECOMMENDED)

#### Translation Functions
```php
// For simple strings
__('Text to translate', 'yaml-custom-fields')

// For strings with HTML escaping
esc_html__('Text to translate', 'yaml-custom-fields')

// For echoing translated strings
_e('Text to translate', 'yaml-custom-fields')
```

---

## 4. Documentation Requirements

### File Structure Checklist

- [x] Main plugin file in root (`yaml-custom-fields.php`)
- [x] README.txt in WordPress format
- [x] uninstall.php in root
- [x] LICENSE file
- [ ] /languages/ directory (TO CREATE)
- [x] /assets/ directory for screenshots (uploaded separately via web interface)
- [x] /build/ for compiled dependencies
- [ ] Plugin assets created (banner, icon) (TO CREATE)

### Plugin Header Verification

Verify `yaml-custom-fields.php` has:
- [x] Plugin Name
- [x] Description
- [x] Version number (2.0.0)
- [x] Author name
- [x] License (GPLv2 or later)
- [x] Text Domain (yaml-custom-fields)
- [x] Domain Path (/languages)

### readme.txt Validation

**Action Required:**
- [ ] Visit: https://wordpress.org/plugins/developers/readme-validator/
- [ ] Paste your readme.txt
- [ ] Fix any validation errors
- [ ] Ensure all sections are properly formatted

**Common issues to check:**
- "Tested up to" version (currently 7.1 ✓)
- Changelog format
- Valid markdown in descriptions

---

## 5. Testing Checklist

### ✅ Current Test Coverage

- [x] **247 Unit Tests** (557 assertions) - `composer test:unit`, no WordPress needed
- [x] **446 Integration Tests** (3,041 assertions) - `composer test:integration`, against a real WordPress
- [x] **WordPress Coding Standards** compliant - `composer lint`, 0 findings
- [x] All tests passing on PHP 8.1, 8.3 and 8.4 in CI, against WordPress `latest`

Three of the integration tests are smoke tests that run the same fourteen-schema
matrix across every admin screen, all AJAX handlers and every theme-facing
function. A PHP warning from the plugin's own code fails the build.

### Plugin Check Tool

**Time Required:** 30 minutes
**Priority:** HIGHLY RECOMMENDED

**Install Plugin Check:**
```bash
wp plugin install plugin-check --activate
```

**Run Automated Checks:**
```bash
wp plugin-check run yaml-custom-fields
```

**Or use the web version:**
https://wordpress.org/plugins/developers/plugin-check/

**This will check for:**
- Security issues
- Code standards
- Accessibility
- Performance issues
- Best practices violations

### Fresh Install Testing

**Time Required:** 1 hour
**Priority:** REQUIRED

**Test Steps:**
1. Install fresh WordPress (Local, MAMP, or staging)
2. Install ONLY your plugin (no other plugins)
3. Activate plugin
4. Test all major features:
   - Schema creation and editing
   - Global data management
   - Per-page data entry
   - Data objects
   - Import/export functionality
5. Check for:
   - PHP errors
   - JavaScript console errors
   - Database errors
   - Missing styles/scripts
6. Deactivate and delete plugin
7. Verify clean uninstall (check database for leftover data)

### Compatibility Testing

**Time Required:** 2 hours
**Priority:** RECOMMENDED

**Test Matrix:**

#### WordPress Versions
- [ ] WordPress 6.4 (minimum version specified)
- [ ] WordPress 7.1 (latest version)

#### PHP Versions
- [ ] PHP 8.1 (minimum version)
- [ ] PHP 8.2 (recommended)
- [ ] PHP 8.3 (recommended)
- [ ] PHP 8.4 (should show compatibility warning)

#### Editors
- [ ] Classic Editor
- [ ] Gutenberg (Block Editor)

#### Themes
- [ ] Twenty Twenty-Four theme
- [ ] Twenty Twenty-Three theme
- [ ] Default active theme

### Multisite Testing

**Priority:** RECOMMENDED

- [ ] Activate on network
- [ ] Activate on individual sites
- [ ] Test data isolation between sites
- [ ] Test uninstall on multisite

### Conflict Testing

**Priority:** RECOMMENDED

- [ ] Test with popular plugins (Yoast SEO, WooCommerce, etc.)
- [ ] Check for JavaScript conflicts
- [ ] Check for CSS conflicts
- [ ] Verify no fatal errors in combination

---

## 6. Packaging Process

### Build Production ZIP

**Time Required:** 15 minutes
**Priority:** REQUIRED

**Commands:**
```bash
# Install production dependencies only
php composer.phar install --no-dev --optimize-autoloader

# Build scoped dependencies
bash build-scoped.sh

# Create distribution ZIP
bash package-for-wporg.sh
```

### Verify ZIP Contents

**ZIP should contain:**
- ✅ All PHP source files
- ✅ /build/ directory (scoped dependencies)
- ✅ readme.txt
- ✅ uninstall.php
- ✅ LICENSE
- ✅ /languages/ directory (empty is OK)

**ZIP should NOT contain:**
- ❌ .git directory
- ❌ node_modules
- ❌ tests/
- ❌ .distignore
- ❌ Development files (composer.json, phpcs.xml, etc.)
- ❌ /assets/ (screenshots uploaded separately via web interface)

### Version Number Checklist

Ensure version 2.0.0 is updated in:
- [x] Main plugin file header (`yaml-custom-fields.php`)
- [x] readme.txt (Stable tag)
- [x] Changelog in readme.txt

---

## 7. WordPress.org Submission

### Step 1: Create WordPress.org Account

**Before you can submit:**
1. Visit: https://login.wordpress.org/register
2. Use same email you'll use for support
3. Keep credentials secure

### Step 2: Submit Plugin for Review

1. **Go to:** https://wordpress.org/plugins/developers/add/
2. **Fill out form:**
   - Plugin name: YAML Custom Fields
   - Plugin slug: yaml-custom-fields (check availability)
   - Description: Brief description of plugin
3. **Upload:** Your production ZIP file (created with `package-for-wporg.sh`)
4. **Submit** for review

### Step 3: Wait for Approval

- **Typical wait time:** 1-14 days
- **Check email** for approval or feedback
- **Respond quickly** to any reviewer questions
- **Address feedback** promptly to speed up approval

### Step 4: Upload Plugin Assets (After Approval)

Once approved, you'll have access to upload plugin assets through your WordPress.org dashboard:

1. **Go to:** https://wordpress.org/plugins/developers/
2. **Select your plugin:** yaml-custom-fields
3. **Upload assets** using the web interface:
   - Icon: `icon-256x256.png` and `icon-128x128.png`
   - Banner: `banner-1544x500.png` and `banner-772x250.png`
   - Screenshots: `screenshot-1.png`, `screenshot-2.png`, etc.

**Asset Specifications:**
- Icons displayed on plugin directory listing
- Banners shown at top of plugin page
- Screenshots displayed in plugin details
- All assets should be PNG format

### Updating Your Plugin

**To release a new version (e.g., 2.0.1):**

1. **Update version numbers:**
   - `yaml-custom-fields.php` header
   - `readme.txt` stable tag
   - Changelog in `readme.txt`

2. **Build new package:**
   ```bash
   ./build-scoped.sh
   ./package-for-wporg.sh
   ```

3. **Upload through WordPress.org:**
   - Go to your plugin's developer dashboard
   - Upload the new ZIP file
   - WordPress.org will automatically update the plugin

4. **Update is live within 15 minutes!**

---

## 8. Post-Submission Monitoring

### After Plugin is Live

1. **Monitor Support Forum**
   - Check: https://wordpress.org/support/plugin/yaml-custom-fields/
   - Respond to user questions within 24-48 hours
   - Build good reputation with responsive support

2. **Track Reviews**
   - Monitor star ratings
   - Respond to reviews (both positive and negative)
   - Address concerns in future updates

3. **Monitor Downloads & Active Installs**
   - Available in your WordPress.org dashboard
   - Track adoption and growth

4. **Watch for Security Reports**
   - Have a plan for security updates
   - Respond immediately to security issues

### Future Updates

See "Updating Your Plugin" section above for instructions on releasing new versions.

---

## 9. Action Plan (Quick Start)

### Priority 1 (Critical - 30 mins)
**Must complete before submission**

1. ✅ **Create /languages/ directory** (5 mins)
   ```bash
   mkdir languages
   ```

2. **Run readme.txt validator** (10 mins)
   - Visit: https://wordpress.org/plugins/developers/readme-validator/
   - Fix any errors

3. **Build production ZIP** (15 mins)
   ```bash
   php composer.phar install --no-dev --optimize-autoloader
   bash build-scoped.sh
   bash package-for-wporg.sh
   ```

### Priority 2 (Important - 2-3 hours)
**Required for professional appearance and thorough testing**

1. **Create Plugin Assets** (1-2 hours)
   - Design icon (256x256px and 128x128px)
   - Design banner (1544x500px and 772x250px)
   - Save as PNG with transparency

2. **Create Screenshots** (30 mins)
   - Capture 4-5 key features
   - Resize to 1200x900px
   - Save as screenshot-1.png, screenshot-2.png, etc.

3. **Run Plugin Check** (30 mins)
   ```bash
   wp plugin install plugin-check --activate
   wp plugin-check run yaml-custom-fields
   ```
   - Address any issues found

4. **Fresh Install Testing** (1 hour)
   - Test on clean WordPress installation
   - Verify all features work
   - Test clean uninstall

### Priority 3 (Polish - 2 hours)
**Recommended for best results**

1. **Compatibility Testing** (1-2 hours)
   - Test on multiple PHP versions (8.1, 8.2, 8.3)
   - Test with different WordPress versions
   - Test with popular themes
   - Test for plugin conflicts

2. **Final Review** (30 mins)
   - Review all checklist items
   - Verify version numbers
   - Check all documentation
   - Ensure no debug code

### Timeline Summary

**Minimum (Priority 1 + 2): 3-4 hours**
- Can submit basic version

**Recommended (All Priorities): 5-6 hours**
- Professional appearance
- Thoroughly tested
- Best chance of quick approval

**Best Practice: Split over 2-3 days**
- **Day 1 (2 hours):** Priority 1 + Plugin Check + Fresh Install
- **Day 2 (2 hours):** Create assets (icon, banner, screenshots)
- **Day 3 (1-2 hours):** Compatibility testing + final review + submit

---

## ✅ Final Pre-Submission Checklist

### Code Quality
- [x] All PHP files pass WordPress Coding Standards
- [ ] No debug code (var_dump, print_r, error_log)
- [ ] No hardcoded paths or URLs
- [x] All strings use proper escaping
- [x] All database queries use prepared statements

### Security
- [x] All AJAX actions verify nonces (12 verifications)
- [x] All form submissions verify nonces
- [x] All admin pages check capabilities (35 checks)
- [x] All file uploads are validated
- [x] No SQL injection vulnerabilities
- [x] No XSS vulnerabilities (262 escaping instances)
- [x] No CSRF vulnerabilities

### Files
- [x] uninstall.php created and tested
- [ ] readme.txt validated
- [x] LICENSE file present
- [x] Plugin header complete with Domain Path
- [x] Text domain loading function included
- [ ] /languages/ directory created
- [ ] Plugin icon created
- [ ] Plugin banner created
- [ ] Screenshots added (4-5 recommended)

### Testing
- [ ] Plugin Check passed
- [ ] Fresh install tested
- [ ] Clean uninstall verified
- [ ] No PHP errors
- [ ] No JavaScript console errors
- [x] Works with WordPress 6.4+
- [x] Works with PHP 8.1+
- [ ] Works with latest WordPress
- [ ] Compatible with block editor
- [ ] Compatible with classic editor

### Documentation
- [x] readme.txt complete
- [x] Changelog updated
- [x] Installation instructions clear
- [x] FAQ section filled (if applicable)
- [ ] Screenshots match descriptions
- [x] License clearly stated (GPLv2)

### Distribution
- [ ] Production ZIP built
- [ ] ZIP tested on fresh install
- [ ] No development files in ZIP
- [ ] File size reasonable (<5MB)
- [x] All dependencies included and scoped

### Submit When ALL Checked
- [ ] All critical items complete
- [ ] All required items complete
- [ ] All recommended items complete
- [ ] Ready to submit to WordPress.org!

---

## Common Rejection Reasons & Tips

### ❌ Common Rejection Reasons
- Missing or incomplete readme.txt
- Security vulnerabilities (XSS, SQL injection, CSRF)
- No uninstall cleanup
- Trademark violations in name
- GPL license issues
- Phone-home functionality
- Obfuscated code

### ✅ Tips for Quick Approval
- Clear, descriptive readme.txt
- Comprehensive security measures
- Follow WordPress Coding Standards
- Provide good documentation
- Test thoroughly before submission
- Respond quickly to reviewer feedback
- Show willingness to address concerns

---

## Resources

### Official WordPress Documentation
- [Plugin Handbook](https://developer.wordpress.org/plugins/)
- [Plugin Directory Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)
- [Security Best Practices](https://developer.wordpress.org/plugins/security/)
- [Plugin Developer Dashboard](https://wordpress.org/plugins/developers/)

### Validation Tools
- [readme.txt Validator](https://wordpress.org/plugins/developers/readme-validator/)
- [Plugin Check](https://wordpress.org/plugins/plugin-check/)

### Support & Community
- [Support Forum](https://wordpress.org/support/forum/wp-advanced/#new-post)
- [Slack Community](https://make.wordpress.org/chat/)

---

## Status Summary

### ✅ What's Complete
Your plugin is **ready** for submission with:
- Excellent code quality (PHPCS compliant)
- Comprehensive testing (247 unit and 446 integration tests, 3,598 assertions)
- Outstanding security (0 critical issues)
- Professional documentation
- Clean architecture
- GPL licensing
- Clean uninstall functionality

### ⚠️ What's Left (~5-6 hours)
- Create /languages/ directory (5 mins)
- Create plugin icon and banner (1-2 hours)
- Create screenshots (30 mins)
- Run Plugin Check (30 mins)
- Fresh install testing (1 hour)
- Validate readme.txt (10 mins)
- Compatibility testing (1-2 hours)
- Build final production ZIP (15 mins)

---

**Last Updated:** 2026-09-25
**Plugin Version:** 2.0.0
**Document Version:** 1.1.0
**Prepared By:** YAML Custom Fields Development Team

---

🚀 **You're almost there! This plugin is well-built and ready for the WordPress Plugin Directory. Complete the remaining tasks and you'll have a successful submission!**
