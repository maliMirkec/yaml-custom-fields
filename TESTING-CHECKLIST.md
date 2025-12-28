# Manual Testing Checklist - WordPress Plugin Review

## Prerequisites
- ✅ WP_DEBUG = true in wp-config.php
- ✅ WordPress site running
- ✅ Plugin activated

## Test 1: Admin Page Access
**URL:** `/wp-admin/admin.php?page=yaml-custom-fields`

**Expected:**
- ✅ Page loads without critical errors
- ✅ No JavaScript errors in browser console (F12)
- ✅ Template list displays
- ✅ Refresh button visible and functional

**How to Test:**
1. Open browser DevTools (F12)
2. Go to Console tab
3. Navigate to plugin admin page
4. Check for errors (should be none)

## Test 2: Success Message Display
**URL:** Create/edit a schema and save

**Expected:**
- ✅ Success message displays via JavaScript (not inline script)
- ✅ Message appears at top of page
- ✅ Message auto-dismisses or has close button

**How to Test:**
1. Go to any template schema editor
2. Make a small change to the YAML
3. Click "Save Schema"
4. Verify green success message displays

## Test 3: Error Message Display
**URL:** Edit schema with invalid YAML

**Expected:**
- ✅ Error message displays via JavaScript
- ✅ Invalid YAML is preserved in editor
- ✅ Error persists (doesn't auto-dismiss)

**How to Test:**
1. Go to schema editor
2. Add invalid YAML (e.g., `fields: [unclosed bracket`)
3. Click "Save Schema"
4. Verify red error message displays

## Test 4: Form Change Tracking
**URL:** `/wp-admin/admin.php?page=yaml-cf-edit-partial&template=header.php`

**Expected:**
- ✅ Edit partial data fields
- ✅ Browser warns "You have unsaved changes" when navigating away
- ✅ Warning does NOT appear after saving

**How to Test:**
1. Open any partial editor
2. Make a change to a field value
3. Try to navigate away (click browser back)
4. Verify browser shows "unsaved changes" warning
5. Save the form
6. Try navigating away again (should work without warning)

## Test 5: Refresh Templates Button
**URL:** `/wp-admin/admin.php?page=yaml-custom-fields`

**Expected:**
- ✅ Button shows loading spinner when clicked
- ✅ AJAX request completes successfully
- ✅ Page reloads automatically
- ✅ No console errors

**How to Test:**
1. Open browser DevTools Network tab
2. Click "Refresh Templates" button
3. Verify AJAX request to `admin-ajax.php` with action `yaml_cf_refresh_templates`
4. Verify page reloads
5. Check console for errors

## Test 6: Export Functionality (Nonce Security)
**URL:** Export single post data

**Expected:**
- ✅ Export works with valid nonce
- ✅ JSON file downloads
- ✅ No PHP warnings in debug.log

**How to Test:**
1. Go to Export/Import page
2. Select a post with YAML CF data
3. Click "Export This Post"
4. Verify JSON file downloads
5. Check `wp-content/debug.log` for errors

## Test 7: Settings Export (Nonce Security)
**URL:** Export settings

**Expected:**
- ✅ Settings export works
- ✅ JSON file contains schemas
- ✅ No nonce errors

**How to Test:**
1. Go to Export/Import page
2. Check boxes for what to export
3. Click "Export Settings"
4. Verify JSON file downloads
5. Check debug.log for errors

## Debug Log Check
**File:** `/wp-content/debug.log`

**Expected:**
- ✅ No PHP Fatal errors
- ✅ No PHP Warnings related to YAML Custom Fields
- ✅ No "Cannot redeclare class" errors

**How to Check:**
```bash
tail -100 wp-content/debug.log | grep -i "yaml\|fatal\|warning"
```

## Browser Console Check
**F12 → Console Tab**

**Expected:**
- ✅ No JavaScript errors
- ✅ No "404 Not Found" for admin-page-init.js
- ✅ yamlCFPageInit object exists (when applicable)

**How to Check in Console:**
```javascript
// Should see the script loaded
typeof yamlCFPageInit !== 'undefined'

// Should see main YamlCF object
typeof YamlCF !== 'undefined'
```

---

## ✅ All Tests Pass?

If all tests pass:
1. ✅ Commit all changes
2. ✅ Create plugin ZIP (exclude vendor/, .git, etc.)
3. ✅ Upload to WordPress.org plugin directory
4. ✅ Reply to review email: "All issues addressed. Plugin tested with WP_DEBUG=true."

If any test fails:
1. Check browser console for errors
2. Check wp-content/debug.log
3. Report the specific error for further debugging
