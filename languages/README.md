# Translation Files

This directory contains translation files for the GF Booking plugin.

## Files

- `gform-booking.pot` - Template file with all translatable strings

## Creating a Translation

To create a translation for a specific language:

1. Copy `gform-booking.pot` to a new file with the language code, e.g.:

   - German: `gform-booking-de_DE.po`
   - English (US): `gform-booking-en_US.po`

2. Edit the PO file and fill in the translations for each `msgstr ""` entry.

3. Use a tool like Poedit, Lokalize, or any text editor to translate the strings.

4. Save the file and WordPress will automatically load the translations when the language is set.

## Example German Translation

```po
msgid "Dashboard"
msgstr "Übersicht"

msgid "Services"
msgstr "Dienste"

msgid "Appointments"
msgstr "Termine"
```

## Compiling .mo Files

After editing a `.po` file, compile it to a `.mo` file using a tool like:

- Poedit (GUI)
- msgfmt (command line): `msgfmt gform-booking-de_DE.po -o gform-booking-de_DE.mo`

WordPress will automatically load `.mo` files placed in this directory.

## Automatically Generating .pot Files

To automatically generate the `.pot` file from the plugin code, run:

```bash
# Using WordPress i18n tools (recommended)
i18n make-pot languages gform-booking.pot --include="*.php" --headers=header.txt --domain=gform-booking

# Or using WP-CLI (if installed)
wp i18n make-pot languages gform-booking.pot --include="*.php" --headers=header.txt --domain=gform-booking
```

For easy access, you can add a npm script to your `package.json`:

```json
{
  "scripts": {
    "pot": "i18n make-pot languages gform-booking.pot --include=\"*.php\" --headers=header.txt --domain=gform-booking"
  }
}
```

Then run: `npm run pot`

## Language Locale Codes

Common locale codes for translations:

- `de_DE` - German (Germany)
- `de_AT` - German (Austria)
- `en_US` - English (United States)
- `en_GB` - English (United Kingdom)
- `fr_FR` - French (France)
- `es_ES` - Spanish (Spain)
- `it_IT` - Italian (Italy)

## Contributing Translations

If you create a translation, please consider sharing it with the plugin author for inclusion in future releases.
