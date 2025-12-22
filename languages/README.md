# Translations

This directory contains language translation files for the YAML Custom Fields plugin.

## Translation Files

Translation files should be placed in this directory with the following naming convention:

- `yaml-custom-fields-{locale}.po` - Portable Object file (source)
- `yaml-custom-fields-{locale}.mo` - Machine Object file (compiled)

## Example Locales

- `yaml-custom-fields-de_DE.po/mo` - German
- `yaml-custom-fields-fr_FR.po/mo` - French
- `yaml-custom-fields-es_ES.po/mo` - Spanish
- `yaml-custom-fields-ja.po/mo` - Japanese

## Creating Translations

### Using Poedit

1. Download and install [Poedit](https://poedit.net/)
2. Create new translation from PHP sources
3. Point to the plugin root directory
4. Select target language
5. Translate strings
6. Save both .po and .mo files to this directory

### Using WP-CLI

```bash
# Generate POT file (template)
wp i18n make-pot /path/to/yaml-custom-fields /path/to/yaml-custom-fields/languages/yaml-custom-fields.pot

# Create PO file from POT
wp i18n make-po /path/to/yaml-custom-fields/languages/yaml-custom-fields.pot de_DE

# Create MO file from PO
wp i18n make-mo /path/to/yaml-custom-fields/languages/
```

## WordPress.org Translation Platform

Once the plugin is published on WordPress.org, translations can be contributed through:
https://translate.wordpress.org/projects/wp-plugins/yaml-custom-fields/

## Text Domain

All translatable strings in this plugin use the text domain: `yaml-custom-fields`

## Need Help?

For translation questions, please open an issue on GitHub:
https://github.com/maliMirkec/yaml-custom-fields/issues
