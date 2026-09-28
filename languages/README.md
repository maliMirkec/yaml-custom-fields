# Translations

This folder holds the plugin's translations.

## File names

- `yaml-custom-fields.pot`: the template. It lists every text to translate.
- `yaml-custom-fields-{locale}.po`: a translation you edit. Example: `yaml-custom-fields-de_DE.po`.
- `yaml-custom-fields-{locale}.mo`: the same translation, compiled for WordPress.
- `yaml-custom-fields-{locale}-{hash}.json`: translations for the admin scripts.

## Make a translation with Poedit

1. Install [Poedit](https://poedit.net/).
2. Open `yaml-custom-fields.pot`.
3. Pick your language.
4. Translate the texts.
5. Save. Poedit makes the `.po` and `.mo` files.
6. Put both files in this folder.

## Make a translation with WP-CLI

```bash
# Update the template
wp i18n make-pot /path/to/yaml-custom-fields /path/to/yaml-custom-fields/languages/yaml-custom-fields.pot --exclude=build,vendor,tests,examples

# Make a .po file for German
wp i18n make-po /path/to/yaml-custom-fields/languages/yaml-custom-fields.pot de_DE

# Compile .po into .mo
wp i18n make-mo /path/to/yaml-custom-fields/languages/

# Make the .json files for the admin scripts
wp i18n make-json /path/to/yaml-custom-fields/languages/ --no-purge
```

- Update the template every time you add or change a text.
- The admin scripts need the `.json` files. Without them, script texts stay in English.
- Language packs from translate.wordpress.org already include the `.json` files.

## Translate online

After the plugin is on WordPress.org, anyone can translate it here:
https://translate.wordpress.org/projects/wp-plugins/yaml-custom-fields/

## Text domain

Every text uses the text domain `yaml-custom-fields`.

## Questions?

Open an issue on GitHub:
https://github.com/maliMirkec/yaml-custom-fields/issues

---

**Last updated:** 2026-09-28
**Plugin version:** 2.0.0
**Maintained by:** [Studio Cita](https://www.cita.hr)
