# WordPress Plugin Directory Submission - Action Items

## 🎯 Quick Summary

Your plugin is **80% ready** for WordPress Plugin Directory submission!

**Already Complete:**
- ✅ WordPress Coding Standards compliance
- ✅ 120 comprehensive unit tests
- ✅ Proper GPL licensing
- ✅ Comprehensive readme.txt
- ✅ Security best practices (escaping, sanitization)
- ✅ No external dependencies/phone-home
- ✅ Professional documentation

**What's Left:** ~7-8 hours of work

---

## 🚨 Critical Items (MUST DO)

### 1. uninstall.php ✅ CREATED
**Status:** ✅ Complete
**File:** `/uninstall.php`
**What it does:** Cleanly removes all plugin data when deleted

### 2. Security Audit - Nonce Verification ⚠️ REVIEW NEEDED
**Time Required:** 2 hours
**Priority:** CRITICAL

**Action:** Verify all AJAX handlers and form submissions have proper nonce verification.

**Files to Audit:**
```bash
# Check all AJAX handlers
ls src/Ajax/*.php

# Check all form handlers
ls src/Admin/Controllers/*.php
ls src/Form/*.php
```

**What to verify:**
```php
// Every AJAX action should have:
if (!wp_verify_nonce($_POST['nonce'], 'yaml_cf_action_name')) {
    wp_send_json_error('Security check failed');
}

if (!current_user_can('manage_options')) {
    wp_send_json_error('Insufficient permissions');
}
```

**Quick Test:**
```bash
# Search for AJAX actions without nonce checks
grep -r "wp_ajax" src/ -A 10 | grep -v "wp_verify_nonce"
```

### 3. SQL Injection Prevention ⚠️ VERIFY
**Time Required:** 1 hour
**Priority:** HIGH

**Action:** Verify all database queries use prepared statements.

**Quick Test:**
```bash
# Find all direct database queries
grep -rn "\$wpdb->query\|\$wpdb->get_\|\$wpdb->delete" src/ --include="*.php"
```

**What to look for:**
```php
// ❌ BAD (SQL Injection risk)
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name = '{$name}'");

// ✅ GOOD (Safe)
$wpdb->query($wpdb->prepare(
    "DELETE FROM {$wpdb->options} WHERE option_name = %s",
    $name
));
```

### 4. File Upload Validation ⚠️ VERIFY
**Time Required:** 1 hour
**Priority:** HIGH

**Action:** Verify file upload security in import functionality.

**Files to Check:**
- Any file that handles `$_FILES`
- Import/export controllers

**What to verify:**
- File type validation (only allow .json)
- File size limits
- JSON content validation
- Proper error handling

---

## 📦 Plugin Assets (Required for Directory)

### 5. Create Plugin Icon & Banner
**Time Required:** 1-2 hours
**Priority:** REQUIRED for professional appearance

**Specifications:**
- **Icon:** 256x256px PNG (also provide 128x128px version)
- **Banner:** 1544x500px PNG (also provide 772x250px version)
- **Format:** PNG with transparency
- **Style:** Match your plugin's branding

**Tools:**
- Canva (free templates available)
- Photoshop
- GIMP (free alternative)

**Where to place:**
- Create `/assets/` directory (NOT in plugin ZIP, only in SVN)
- Files: `icon-256x256.png`, `icon-128x128.png`, `banner-1544x500.png`, `banner-772x250.png`

### 6. Screenshots
**Time Required:** 30 minutes
**Priority:** REQUIRED

**Action:**
1. Take screenshots of your plugin's key features
2. Resize to 1200x900px (recommended)
3. Save as PNG
4. Place in `/assets/` directory as:
   - `screenshot-1.png`
   - `screenshot-2.png`
   - `screenshot-3.png`
   - etc.

**What to screenshot:**
- Main settings page
- Schema editor
- Post/page editor meta box
- Import/export page
- Data objects page

---

## 🧪 Final Testing

### 7. Plugin Check
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

This will automatically check for:
- Security issues
- Code standards
- Accessibility
- Performance issues
- Best practices violations

### 8. Fresh Install Testing
**Time Required:** 1 hour
**Priority:** REQUIRED

**Test Steps:**
1. Install fresh WordPress (Local, MAMP, or staging)
2. Install ONLY your plugin (no other plugins)
3. Activate plugin
4. Test all major features
5. Check for:
   - PHP errors
   - JavaScript console errors
   - Database errors
   - Missing styles/scripts
6. Deactivate and delete plugin
7. Verify clean uninstall (check database for leftover data)

### 9. Compatibility Testing
**Time Required:** 2 hours
**Priority:** RECOMMENDED

**Test Matrix:**
- [ ] WordPress 5.0 (minimum version)
- [ ] WordPress 6.9 (latest version)
- [ ] PHP 7.4 (minimum version)
- [ ] PHP 8.0
- [ ] PHP 8.1
- [ ] PHP 8.2 (recommended)
- [ ] Classic Editor
- [ ] Gutenberg (Block Editor)
- [ ] Default themes (Twenty Twenty-Four, Twenty Twenty-Three)

---

## 📝 Documentation Updates

### 10. Add Text Domain Loading
**Time Required:** 15 minutes
**Priority:** RECOMMENDED for i18n

**File:** `yaml-custom-fields.php`

**Add this function:**
```php
/**
 * Load plugin text domain for translations
 */
function yaml_cf_load_textdomain() {
    load_plugin_textdomain(
        'yaml-custom-fields',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
}
add_action('plugins_loaded', 'yaml_cf_load_textdomain');
```

**Create directory:**
```bash
mkdir languages
```

### 11. Update Plugin Header
**File:** `yaml-custom-fields.php`

**Add missing line:**
```php
/*
 * Plugin Name: YAML Custom Fields
 * Description: A WordPress plugin for managing YAML frontmatter schemas
 * Version: 1.2.1
 * Author: Silvestar Bistrovic
 * License: GPL-2.0-or-later
 * Text Domain: yaml-custom-fields
 * Domain Path: /languages    ← ADD THIS LINE
 */
```

---

## 🔍 Pre-Submission Validation

### 12. readme.txt Validation
**Time Required:** 10 minutes
**Priority:** REQUIRED

**Steps:**
1. Visit: https://wordpress.org/plugins/developers/readme-validator/
2. Paste your entire `readme.txt` file
3. Fix any validation errors or warnings
4. Ensure all sections are properly formatted

**Common issues:**
- Missing "Tested up to" version
- Incorrect changelog format
- Invalid markdown in descriptions

### 13. Build Production ZIP
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

**Verify ZIP contains:**
- ✅ All PHP source files
- ✅ /build/ directory (scoped dependencies)
- ✅ readme.txt
- ✅ uninstall.php
- ✅ LICENSE
- ❌ NO .git directory
- ❌ NO node_modules
- ❌ NO tests/
- ❌ NO development files

---

## 📊 Estimated Timeline

| Task | Time | Priority |
|------|------|----------|
| Security Audit (Nonces) | 2 hours | Critical |
| SQL Injection Verify | 1 hour | High |
| File Upload Security | 1 hour | High |
| Plugin Icon & Banner | 1-2 hours | Required |
| Screenshots | 30 min | Required |
| Plugin Check | 30 min | High |
| Fresh Install Test | 1 hour | Required |
| Compatibility Testing | 2 hours | Medium |
| Text Domain Setup | 15 min | Medium |
| readme.txt Validation | 10 min | Required |
| Build Production ZIP | 15 min | Required |

**Total: 9-10 hours**

**Can be done over 2-3 days:**
- **Day 1 (4-5 hours):** Security audit, SQL verification, file upload security, Plugin Check
- **Day 2 (3-4 hours):** Create assets (icon, banner, screenshots), fresh install testing
- **Day 3 (2 hours):** Final validation, compatibility testing, build production ZIP

---

## ✅ Ready to Submit Checklist

Before submitting, ensure ALL of these are checked:

### Code Quality
- [ ] All PHP files pass WordPress Coding Standards
- [ ] No debug code (var_dump, print_r, error_log)
- [ ] No hardcoded paths or URLs
- [ ] All strings use proper escaping
- [ ] All database queries use prepared statements

### Security
- [ ] All AJAX actions verify nonces
- [ ] All form submissions verify nonces
- [ ] All admin pages check capabilities
- [ ] All file uploads are validated
- [ ] No SQL injection vulnerabilities
- [ ] No XSS vulnerabilities
- [ ] No CSRF vulnerabilities

### Files
- [ ] uninstall.php created and tested
- [ ] readme.txt validated
- [ ] LICENSE file present
- [ ] Plugin header complete
- [ ] Text domain loaded
- [ ] /languages/ directory created
- [ ] Plugin icon created
- [ ] Plugin banner created
- [ ] Screenshots added

### Testing
- [ ] Plugin Check passed
- [ ] Fresh install tested
- [ ] Clean uninstall verified
- [ ] No PHP errors
- [ ] No JavaScript console errors
- [ ] Works with WordPress 5.0+
- [ ] Works with PHP 7.4+
- [ ] Works with latest WordPress
- [ ] Compatible with block editor
- [ ] Compatible with classic editor

### Documentation
- [ ] readme.txt complete
- [ ] Changelog updated
- [ ] Installation instructions clear
- [ ] FAQ section filled
- [ ] Screenshots match descriptions
- [ ] License clearly stated

### Distribution
- [ ] Production ZIP built
- [ ] ZIP tested on fresh install
- [ ] No development files in ZIP
- [ ] File size reasonable (<5MB)
- [ ] All dependencies included

---

## 🚀 Submission Steps

Once ALL checkboxes are ✅:

1. **Create WordPress.org Account**
   - Visit: https://login.wordpress.org/register
   - Use same email you'll use for support

2. **Submit Plugin**
   - Go to: https://wordpress.org/plugins/developers/add/
   - Fill out form
   - Upload your production ZIP
   - Submit for review

3. **Wait for Approval**
   - Typically 1-14 days
   - Check email for approval or feedback
   - Address any feedback quickly

4. **Setup SVN** (after approval)
   - You'll receive SVN credentials
   - Follow the SVN guide in PLUGIN-DIRECTORY-CHECKLIST.md

---

## 💡 Pro Tips

1. **Respond quickly to reviewers** - They appreciate it
2. **Test thoroughly** - Saves review cycles
3. **Read the guidelines** - https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/
4. **Be patient** - Review queue can be long
5. **Join Slack** - WordPress.org has a Slack for plugin developers

---

## 📞 Support Resources

- **Plugin Handbook:** https://developer.wordpress.org/plugins/
- **Guidelines:** https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/
- **Support Forum:** https://wordpress.org/support/forum/wp-advanced/#new-post
- **Slack:** https://make.wordpress.org/chat/

---

## 🎉 You're Almost There!

Your plugin is well-built with:
- ✅ Excellent code quality (PHPCS compliant)
- ✅ Comprehensive testing (120 unit tests)
- ✅ Professional documentation
- ✅ Solid architecture

Just need to complete the items above and you'll be ready for submission!

**Good luck! 🚀**
