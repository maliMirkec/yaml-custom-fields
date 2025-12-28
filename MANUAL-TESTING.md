# Manual Testing Checklist for YAML Custom Fields Plugin

This guide provides comprehensive manual testing procedures for the admin JavaScript functionality.

## Table of Contents

- [Pre-Testing Setup](#pre-testing-setup)
- [Template Settings Page](#template-settings-page)
- [Schema Editor](#schema-editor)
- [Post/Page Editor Meta Box](#postpage-editor-meta-box)
- [Block Fields](#block-fields)
- [Media Fields](#media-fields)
- [Import/Export](#importexport)
- [Template Global Fields](#template-global-fields)
- [Code Snippets](#code-snippets)
- [Reset All Data](#reset-all-data)
- [Browser Compatibility](#browser-compatibility)
- [Error Scenarios](#error-scenarios)
- [Performance Testing](#performance-testing)
- [Accessibility Testing](#accessibility-testing)
- [Testing Sign-Off](#testing-sign-off)
- [Regression Testing Checklist](#regression-testing-checklist)
- [Known Limitations](#known-limitations)
- [Troubleshooting](#troubleshooting)
- [Next Steps](#next-steps)

---

## Pre-Testing Setup

### Required Environment
- [ ] WordPress 5.0+ installed
- [ ] Plugin activated
- [ ] At least one theme template with YAML schema defined
- [ ] Test post/page created

### Browser Testing Matrix
Test all critical features in:
- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)

### Test Data Preparation
- [ ] Create test images (JPG, PNG, GIF)
- [ ] Create test files (PDF, DOC, ZIP)
- [ ] Prepare sample JSON export file
- [ ] Have YAML schema samples ready

---

## Template Settings Page

**Location:** `wp-admin/admin.php?page=yaml-cf-settings`

### Enable/Disable YAML

#### Test Case: Enable YAML for Template
1. [ ] Navigate to Template Settings page
2. [ ] Find a template with YAML disabled
3. [ ] Check the "Enable YAML" checkbox
4. [ ] **Expected:**
   - Success message appears
   - "Add Schema" button appears in Schema column
   - Template Global columns update (for template tables)
   - "Add schema first" message appears in Data column (for partial tables)
5. [ ] Refresh page
6. [ ] **Expected:** Checkbox remains checked

#### Test Case: Disable YAML for Template
1. [ ] Navigate to Template Settings page
2. [ ] Find a template with YAML enabled
3. [ ] Uncheck the "Enable YAML" checkbox
4. [ ] **Expected:**
   - Success message appears
   - Schema, Template Global, and Data columns show "Enable YAML first"
5. [ ] Refresh page
6. [ ] **Expected:** Checkbox remains unchecked

### Toggle Use Global

#### Test Case: Enable Use Global
1. [ ] Navigate to Template Settings page
2. [ ] Find a template with "Use Global" unchecked
3. [ ] Check the "Use Global" checkbox
4. [ ] **Expected:**
   - "Global schema setting saved" message appears
   - No page reload required
5. [ ] Refresh page
6. [ ] **Expected:** Setting persists

---

## Schema Editor

**Location:** `wp-admin/admin.php?page=yaml-cf-edit-schema&template=<template-name>`

### Schema Editing
1. [ ] Navigate to Schema Editor
2. [ ] Edit YAML schema in textarea
3. [ ] Click "Save Schema"
4. [ ] **Expected:** Success message appears
5. [ ] Refresh page
6. [ ] **Expected:** Schema persists

### Schema Validation
1. [ ] Enter invalid YAML syntax (e.g., unmatched brackets)
2. [ ] Click "Save Schema"
3. [ ] **Expected:** Error message with details
4. [ ] Fix the syntax
5. [ ] Save again
6. [ ] **Expected:** Success message

---

## Post/Page Editor Meta Box

**Location:** Any post/page edit screen with YAML Custom Fields meta box

### Meta Box Collapse/Expand

#### Test Case: Collapse State Persists
1. [ ] Open post editor with YAML Custom Fields meta box
2. [ ] Click meta box header to collapse
3. [ ] Refresh page
4. [ ] **Expected:** Meta box remains collapsed
5. [ ] Click header to expand
6. [ ] Refresh page
7. [ ] **Expected:** Meta box remains expanded

### Change Tracking

#### Test Case: Unsaved Changes Warning
1. [ ] Open post editor
2. [ ] Modify any YAML custom field
3. [ ] **Expected:** Warning message appears: "You have unsaved changes in YAML Custom Fields"
4. [ ] Click "Update" or "Publish"
5. [ ] **Expected:** Warning disappears
6. [ ] Modify field again
7. [ ] Try to navigate away from page
8. [ ] **Expected:** Browser shows "unsaved changes" warning

### Field Types

#### String Field
1. [ ] Enter text in string field
2. [ ] **Expected:** Text appears normally
3. [ ] Enter special characters: `& < > " '`
4. [ ] Save post
5. [ ] Reload page
6. [ ] **Expected:** Special characters are properly escaped/preserved

#### Textarea Field
1. [ ] Enter multi-line text
2. [ ] **Expected:** Line breaks preserved
3. [ ] Save and reload
4. [ ] **Expected:** Text displays correctly

#### Number Field
1. [ ] Enter a valid number
2. [ ] **Expected:** Accepts number
3. [ ] Try entering text
4. [ ] **Expected:** Browser validation prevents non-numeric input
5. [ ] Test min/max constraints (if defined in schema)

#### Checkbox Field
1. [ ] Check checkbox
2. [ ] Save post
3. [ ] Reload
4. [ ] **Expected:** Checkbox remains checked
5. [ ] Uncheck and repeat
6. [ ] **Expected:** Checkbox remains unchecked

#### Select Field
1. [ ] Select an option
2. [ ] Save post
3. [ ] Reload
4. [ ] **Expected:** Selected option persists

#### Date Field
1. [ ] Click date field
2. [ ] **Expected:** Date picker appears
3. [ ] Select a date
4. [ ] Save and reload
5. [ ] **Expected:** Date persists

#### Rich Text Field (WordPress Editor)
1. [ ] Enter formatted text
2. [ ] Add bold, italic, links
3. [ ] Save post
4. [ ] Reload
5. [ ] **Expected:** Formatting preserved

---

## Block Fields

**Location:** Post/Page editor with block field type

### Add Block

#### Test Case: Add New Block
1. [ ] Locate a block field container
2. [ ] Select block type from dropdown
3. [ ] Click "Add Block" button
4. [ ] **Expected:**
   - New block appears with correct fields
   - Block header shows block type label
   - Move up/down/remove buttons present
5. [ ] Add multiple blocks of different types
6. [ ] **Expected:** Each block has unique fields

### Remove Block

#### Test Case: Remove Block
1. [ ] Add at least 2 blocks
2. [ ] Click "Remove" on middle block
3. [ ] **Expected:**
   - Block fades out and disappears
   - Warning message: "Block removed. Don't forget to save"
   - Remaining blocks re-index correctly
4. [ ] Save post
5. [ ] Reload
6. [ ] **Expected:** Removed block does not appear

### Move Block Up/Down

#### Test Case: Move Block Up
1. [ ] Add at least 3 blocks
2. [ ] Click "Move Up" on 3rd block
3. [ ] **Expected:**
   - Block moves to 2nd position
   - Block order visually updates
4. [ ] Save and reload
5. [ ] **Expected:** Block order persists

#### Test Case: Move Block Down
1. [ ] Add at least 3 blocks
2. [ ] Click "Move Down" on 1st block
3. [ ] **Expected:**
   - Block moves to 2nd position
   - Block order visually updates
4. [ ] Save and reload
5. [ ] **Expected:** Block order persists

#### Test Case: Move Up on First Block
1. [ ] Click "Move Up" on first block
2. [ ] **Expected:** Nothing happens (already at top)

#### Test Case: Move Down on Last Block
1. [ ] Click "Move Down" on last block
2. [ ] **Expected:** Nothing happens (already at bottom)

### Block Field Rendering

#### Test Case: Different Field Types in Blocks
Test each field type within a block:
1. [ ] String field renders as text input
2. [ ] Textarea field renders with 5 rows
3. [ ] Number field renders with min/max constraints
4. [ ] Boolean field renders as checkbox
5. [ ] Select field renders with options
6. [ ] Date field renders with date picker
7. [ ] Image field renders with upload button
8. [ ] File field renders with upload button
9. [ ] Code field renders as textarea with language attribute
10. [ ] Rich-text shows placeholder message (requires save)

---

## Media Fields

**Location:** Post/Page editor with image or file field

### Image Upload

#### Test Case: Upload New Image
1. [ ] Click "Upload Image" button
2. [ ] **Expected:** WordPress media library opens
3. [ ] Upload a new image or select existing
4. [ ] Click "Use This Image"
5. [ ] **Expected:**
   - Media library closes
   - Image preview appears (max 200px width)
   - "Clear" button appears
   - Hidden input stores attachment ID (not URL)
6. [ ] Save post
7. [ ] Reload
8. [ ] **Expected:** Image preview persists

#### Test Case: Change Image
1. [ ] Click "Upload Image" on field with existing image
2. [ ] **Expected:** Media library opens with current image selected
3. [ ] Select different image
4. [ ] Click "Use This Image"
5. [ ] **Expected:** Preview updates to new image
6. [ ] Save and reload
7. [ ] **Expected:** New image persists

#### Test Case: Clear Image
1. [ ] Click "Clear" button on image field
2. [ ] **Expected:**
   - Image preview disappears
   - "Clear" button disappears
   - Hidden input value cleared
   - Warning: "Don't forget to save"
3. [ ] Save post
4. [ ] Reload
5. [ ] **Expected:** Image remains cleared

### File Upload

#### Test Case: Upload File
1. [ ] Click "Upload File" button
2. [ ] **Expected:** WordPress media library opens
3. [ ] Upload a file (PDF, DOC, etc.)
4. [ ] Click "Use This File"
5. [ ] **Expected:**
   - Media library closes
   - File name displays
   - "Clear" button appears
   - Hidden input stores attachment ID
6. [ ] Save and reload
7. [ ] **Expected:** File selection persists

#### Test Case: Clear File
1. [ ] Click "Clear" button on file field
2. [ ] **Expected:**
   - File name display disappears
   - "Clear" button disappears
   - Hidden input cleared
3. [ ] Save and reload
4. [ ] **Expected:** File remains cleared

---

## Import/Export

**Location:** `wp-admin/admin.php?page=yaml-cf-import-export`

### Export Settings

#### Test Case: Export All Settings
1. [ ] Navigate to Import/Export page
2. [ ] Click "Export Settings"
3. [ ] **Expected:**
   - JSON file downloads
   - File name: `yaml-cf-settings-YYYY-MM-DD.json`
   - File contains schemas, template settings, global data

#### Test Case: Export Post Data
1. [ ] Navigate to post/page editor
2. [ ] Click "Export Post Data" (if available)
3. [ ] **Expected:**
   - JSON file downloads
   - File contains post custom field data and schema

### Import Settings

#### Test Case: Import Settings (Replace Mode)
1. [ ] Click "Import Settings" button
2. [ ] **Expected:** File picker opens
3. [ ] Select valid JSON file
4. [ ] **Expected:** Confirmation dialog appears
5. [ ] Confirm "Replace all settings"
6. [ ] **Expected:**
   - Success message shows
   - Page reloads after 1.5 seconds
   - Settings are updated
   - Import metadata displayed (from, date)

#### Test Case: Import Settings (Merge Mode)
1. [ ] Click "Import Settings"
2. [ ] Select JSON file
3. [ ] Click "Cancel" on first dialog (triggers merge option)
4. [ ] Confirm "Merge"
5. [ ] **Expected:**
   - Success message
   - Existing settings preserved, new settings added
   - Page reloads

#### Test Case: Import Invalid JSON
1. [ ] Click "Import Settings"
2. [ ] Select non-JSON file or malformed JSON
3. [ ] **Expected:** Error message: "Invalid JSON file"

#### Test Case: Import Wrong File Type
1. [ ] Click "Import Settings"
2. [ ] Select .txt or other file
3. [ ] **Expected:** Error message: "Please select a valid JSON file"

### Import Post Data

#### Test Case: Import Post Data
1. [ ] Navigate to post/page editor
2. [ ] Trigger post data import (click hidden file input)
3. [ ] Select valid post data JSON
4. [ ] Confirm warning dialog
5. [ ] **Expected:**
   - Data imports via AJAX
   - Page redirects with success parameter
   - All custom fields populated

---

## Template Global Fields

**Location:** Post/Page editor with template global fields enabled

### Use Template Global Toggle

#### Test Case: Enable Template Global
1. [ ] Find field with template global option
2. [ ] Check "Use Template Global" checkbox
3. [ ] **Expected:**
   - Local field becomes disabled (grayed out)
   - Container has `yaml-cf-container-disabled` class
   - Global value displays
4. [ ] Try to interact with disabled field
5. [ ] **Expected:** No interaction possible (CSS prevents it)

#### Test Case: Disable Template Global
1. [ ] Uncheck "Use Template Global" checkbox
2. [ ] **Expected:**
   - Local field becomes enabled
   - Container loses `yaml-cf-container-disabled` class
   - Can edit local value

### Override Template Global

#### Test Case: Enable Override
1. [ ] Find template global field with "Enable Override" button
2. [ ] Click "Enable Override"
3. [ ] **Expected:**
   - Indicator changes to "⚠️ OVERRIDDEN" (orange)
   - Button changes to "Reset to Global"
   - Field becomes editable
   - Hidden input added to mark override
   - Field names update to override namespace

#### Test Case: Reset Override
1. [ ] Click "Reset to Global" on overridden field
2. [ ] Confirm dialog
3. [ ] **Expected:**
   - Page reloads
   - Field resets to global value
   - Override indicator removed

---

## Code Snippets

**Location:** Various admin pages with code snippet copy buttons

### Copy Snippet

#### Test Case: Copy to Clipboard
1. [ ] Hover over "Copy" button next to code snippet
2. [ ] **Expected:** Popover appears with full code
3. [ ] Click "Copy" button
4. [ ] **Expected:**
   - Popover disappears immediately
   - "Copied!" tooltip appears
   - Button shows "copied" state (visual feedback)
   - Success message: "Code snippet copied to clipboard!"
   - Tooltip auto-hides after 2 seconds
5. [ ] Paste into text editor
6. [ ] **Expected:** Full code snippet pasted correctly

#### Test Case: Popover Hover Behavior
1. [ ] Hover over "Copy" button
2. [ ] **Expected:** Popover appears
3. [ ] Move mouse into popover
4. [ ] **Expected:** Popover stays visible
5. [ ] Move mouse out of popover
6. [ ] **Expected:** Popover disappears after 100ms delay

---

## Reset All Data

**Location:** Post/Page editor meta box

### Reset Local Data

#### Test Case: Reset All Fields
1. [ ] Fill in multiple custom fields (local, not template global)
2. [ ] Click "Reset All Data" button
3. [ ] Confirm warning dialog
4. [ ] **Expected:**
   - All LOCAL fields cleared (checkboxes unchecked, inputs empty, selects reset)
   - Image previews removed
   - File name displays removed
   - Blocks removed
   - Template global fields NOT affected
   - Alert confirms data cleared
   - Warning to save changes
5. [ ] Save post
6. [ ] Reload
7. [ ] **Expected:** All fields remain empty

#### Test Case: Cancel Reset
1. [ ] Click "Reset All Data"
2. [ ] Click "Cancel" on confirmation
3. [ ] **Expected:** No changes made, fields remain as they were

---

## Browser Compatibility

### Chrome Testing
- [ ] All features work
- [ ] Console shows no errors
- [ ] Media upload works
- [ ] Clipboard copy works

### Firefox Testing
- [ ] All features work
- [ ] Console shows no errors
- [ ] Media upload works
- [ ] Clipboard copy works

### Safari Testing
- [ ] All features work
- [ ] Console shows no errors
- [ ] Media upload works
- [ ] Clipboard copy works (may need permissions)

### Edge Testing
- [ ] All features work
- [ ] Console shows no errors
- [ ] Media upload works
- [ ] Clipboard copy works

---

## Error Scenarios

### Network Errors

#### Test Case: AJAX Timeout
1. [ ] Enable network throttling (Developer Tools)
2. [ ] Perform AJAX action (save template settings)
3. [ ] **Expected:** Error message appears if request times out

#### Test Case: 500 Server Error
1. [ ] Temporarily break PHP code to trigger 500 error
2. [ ] Perform AJAX action
3. [ ] **Expected:** Error message: "Error saving settings"
4. [ ] Fix PHP code

### Invalid Input

#### Test Case: Non-Numeric in Number Field
1. [ ] Try entering letters in number field
2. [ ] **Expected:** Browser validation prevents input

#### Test Case: Invalid Date Format
1. [ ] Manually edit date field with invalid date
2. [ ] **Expected:** Browser validation or field clears on blur

### WordPress Editor Issues

#### Test Case: TinyMCE Not Loaded
1. [ ] If TinyMCE fails to load
2. [ ] **Expected:** Fallback to textarea still works
3. [ ] Save still functions correctly

---

## Performance Testing

### Large Data Sets

#### Test Case: 50+ Blocks
1. [ ] Add 50+ blocks to a repeater field
2. [ ] **Expected:**
   - Page remains responsive
   - Block reordering works smoothly
   - Save completes successfully

#### Test Case: Large Text Content
1. [ ] Paste 10,000+ characters into textarea
2. [ ] Save post
3. [ ] **Expected:** No performance degradation

### Multiple Meta Boxes
1. [ ] Open post with multiple meta boxes
2. [ ] **Expected:**
   - All meta boxes load correctly
   - No duplicate nonce IDs
   - No JavaScript conflicts

---

## Accessibility Testing

### Keyboard Navigation
1. [ ] Tab through all form fields
2. [ ] **Expected:** Logical tab order, all fields accessible
3. [ ] Use Enter/Space on buttons
4. [ ] **Expected:** Buttons activate correctly

### Screen Reader Testing (Optional)
1. [ ] Use screen reader (NVDA, JAWS, VoiceOver)
2. [ ] Navigate fields
3. [ ] **Expected:** Labels read correctly, helpful descriptions provided

---

## Testing Sign-Off

Once all tests pass, sign off:

**Tested By:** ________________
**Date:** ________________
**Browser:** ________________
**WordPress Version:** ________________
**Plugin Version:** ________________

**Notes:**
_______________________________________
_______________________________________
_______________________________________

---

## Regression Testing Checklist

Run this shortened checklist after any JavaScript changes:

- [ ] Template settings enable/disable works
- [ ] Schema editor saves correctly
- [ ] Block add/remove/reorder works
- [ ] Image upload and clear works
- [ ] File upload and clear works
- [ ] Import/export completes successfully
- [ ] Template global toggle works
- [ ] Change tracking warns on unsaved changes
- [ ] Copy snippet works
- [ ] No console errors in Chrome

---

## Known Limitations

Document any known issues or browser-specific quirks:

1. Safari may require clipboard permissions for copy functionality
2. TinyMCE editors in dynamically added blocks require page reload
3. beforeunload warning may vary by browser
4. localStorage for meta box collapse state is per-browser

---

## Troubleshooting

### Common Issues

**Issue:** Media library doesn't open
**Solution:** Check WordPress media scripts are enqueued, check console for errors

**Issue:** AJAX requests fail
**Solution:** Verify nonce is valid, check network tab for response

**Issue:** Changes don't save
**Solution:** Check for JavaScript errors, verify form submission works

**Issue:** Blocks don't reindex properly
**Solution:** Check console for errors, verify jQuery is loaded

---

## Next Steps

After completing manual testing:

1. Document any bugs found in issue tracker
2. Retest after bug fixes
3. Update this checklist if new features added
4. Consider automated E2E tests for critical workflows (future enhancement)

**Happy Testing! 🧪**

---

**Last Updated:** 2025-12-28
**Plugin Version:** 1.2.2
**Maintained By:** Silvestar Bistrović
