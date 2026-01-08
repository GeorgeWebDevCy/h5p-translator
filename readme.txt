=== H5P WPML Translator ===
Contributors: orionaselite
Donate link: 
Tags: h5p, wpml, translation, multilingual, string-translation, interactive-content
Requires at least: 5.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.2.12
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Translate H5P content strings with WPML String Translation without duplicating content.

== Description ==

H5P content stores text inside JSON, so WPML does not see it as normal post content.
This plugin reads the H5P semantics for each content type, registers text fields
with WPML String Translation, and swaps in translations at render time.

Key features:

* Registers text, textarea, and html fields in H5P content parameters.
* Supports nested libraries, groups, and lists based on H5P semantics.
* Keeps a single H5P item per language and renders the correct translation.
* Works for shortcode and embed rendering.
* Maps H5P image fields to WPML Media Translation for per-language images.

Update notes:

* This plugin uses plugin-update-checker to read updates from
  https://github.com/GeorgeWebDevCy/h5p-translator (branch: main).
* The Composer autoloader is required at runtime. Ensure the `vendor/` directory
  is present on the installed plugin, or run `composer install` before upload.

== Installation ==

Activation will be blocked unless H5P, WPML, and WPML String Translation are
installed and active.

1. Install and activate the H5P plugin.
2. Install and activate WPML and WPML String Translation.
3. Upload this plugin to `/wp-content/plugins/` and activate it.
4. Visit a page that renders an H5P item to register its strings.
5. Translate strings under WPML -> String Translation (context: `H5P Content {id}`).

== Frequently Asked Questions ==

= Where do the H5P strings appear in WPML? =

Open WPML -> String Translation and filter by context `H5P Content {id}`.

= I do not see any strings. What should I check? =

Make sure the H5P item is rendered at least once, WPML String Translation is
active, and caches are cleared. Re-open the page to trigger registration again.

= Does this modify the stored H5P content? =

No. It only replaces strings at render time.

= Are user answers or xAPI statements translated? =

No. Only content authoring strings are translated.

== Screenshots ==

1. H5P strings registered in WPML String Translation.
2. Translated H5P content rendered on the frontend.

== Changelog ==

= 1.2.12 =
* Use detected language when translating strings to support iframe requests.

= 1.2.11 =
* Run text fallback for all libraries even when semantics exist to catch missing fields.
* Align fallback paths with nested library parameters to keep string keys stable.

= 1.2.10 =
* Only register strings in the default language to prevent translation resets.

= 1.2.9 =
* Translate nested library strings even when sub-library semantics are missing.
* Allow HTML strings in text fields when the value includes markup.

= 1.2.8 =
* Run text fallback translation for any H5P library when semantics are missing.

= 1.2.7 =
* Add text fallback translation for H5P.GameMap when semantics are missing.

= 1.2.6 =
* Fix media translation mapping for uploads URLs on language-prefixed pages.

= 1.2.5 =
* Detect WPML language from URL path for /el/ style URLs.

= 1.2.4 =
* Bump version.

= 1.2.3 =
* Respect current WPML language when resolving translated media.

= 1.2.2 =
* Register H5P background images in Media Translation while editing content.

= 1.2.1 =
* Fix media translation registration for H5P background images with relative URLs.

= 1.2.0 =
* Translate H5P image fields using WPML Media Translation.

= 1.1.1 =
* Bump version.

= 1.1.0 =
* Add a Custom CSS settings page that loads styles inside H5P iframes.

= 1.0.1 =
* Bump version to test the updater.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.2.12 =
Use detected language when translating strings to support iframe requests.

= 1.2.11 =
Run text fallback for all libraries even when semantics exist to catch missing fields.

= 1.2.10 =
Only register strings in the default language to prevent translation resets.

= 1.2.9 =
Translate nested library strings even when sub-library semantics are missing.

= 1.2.8 =
Run text fallback translation for any H5P library when semantics are missing.

= 1.2.7 =
Add text fallback translation for H5P.GameMap when semantics are missing.

= 1.2.6 =
Fix media translation mapping for uploads URLs on language-prefixed pages.

= 1.2.5 =
Detect WPML language from URL path for /el/ style URLs.

= 1.2.4 =
Bump version.

= 1.2.3 =
Respect current WPML language when resolving translated media.

= 1.2.2 =
Register H5P background images in Media Translation while editing content.

= 1.2.1 =
Fix media translation registration for H5P background images with relative URLs.

= 1.2.0 =
Translate H5P image fields using WPML Media Translation.

= 1.1.1 =
Bump version.

= 1.1.0 =
Add a Custom CSS settings page that loads styles inside H5P iframes.

= 1.0.1 =
Bump version to test the updater.

= 1.0.0 =
Initial release.
