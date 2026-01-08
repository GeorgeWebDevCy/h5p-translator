# H5P WPML Translator

Translate H5P content strings with WPML String Translation without duplicating
H5P items. This plugin reads H5P semantics, registers translatable fields, and
swaps in translations at render time.

## What It Does

- Registers `text`, `textarea`, and `html` fields from H5P content parameters.
- Traverses nested libraries, groups, and lists using H5P semantics.
- Keeps a single H5P item per language while rendering translated strings.
- Works with shortcode and embed rendering.
- Adds an admin Custom CSS box injected into H5P iframes.
- Maps H5P image fields to WPML Media Translation for per-language images.

## Requirements

- WordPress 5.0+
- PHP 7.4+
- H5P plugin installed and active
- WPML + WPML String Translation installed and active

## Installation

Activation is blocked unless H5P, WPML, and WPML String Translation are active.

### Production

1. Place this plugin in `/wp-content/plugins/h5p-wpml-translator/`.
2. Ensure `vendor/` is present (this repo includes it by default).
3. Activate the plugin in WordPress.

### Development

```bash
composer install
```

Then activate the plugin as usual.

## Usage

1. Render any H5P content once (frontend or embed). This registers strings.
2. Go to WPML -> String Translation.
3. Filter by context `H5P Content {id}` and add translations.
4. Reload the page to see translated content.

### Custom CSS

Open Settings -> H5P Custom CSS to add CSS that should load inside H5P iframes.

### Media Translation for Images

When WPML Media Translation is enabled, H5P image fields (such as backgrounds)
are matched to attachments so you can translate them per language. Visit or
edit the H5P content as an admin once to register the images, then use WPML ->
Media Translation to provide localized files.

## Update Mechanism

This plugin uses `yahnis-elsts/plugin-update-checker` and checks:

- Repo: https://github.com/GeorgeWebDevCy/h5p-translator
- Branch: `main`

When releasing, keep the plugin header version in
`h5p-wpml-translator.php` in sync with your GitHub release/tag.

## Limitations

- Only authoring strings are translated; user answers/state are not.
- Library UI strings are handled by H5P language packs, not this plugin.
- Strings appear in WPML only after the content is rendered at least once.

## Repository Layout

- `h5p-wpml-translator.php` plugin bootstrap
- `public/` translator hook implementation
- `includes/` boilerplate loader and base classes
- `vendor/` Composer dependencies
- `readme.txt` WordPress.org readme

## License

GPLv2 or later. See `LICENSE.txt`.
