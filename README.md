# WP Page for post type

Select a page to act as the archive of a custom post type. The post type's
archive slug is rewritten to match the selected page's permalink.

## Multilingual (Polylang) support

When the [Polylang](https://polylang.pro/) plugin is active, the archive
rewrite rules are generated **per language**:

* Active languages are enumerated via `pll_languages_list()`.
* For each language, the translated page is resolved via
  `pll_get_post($pageForPostType, $lang)`. Languages without a translation
  are skipped.
* The slug is built from the translated page's permalink (with the
  language context switched via `pll_switch_language`) so URLs like
  `/en/books/` and `/sv/bocker/` are produced for their respective
  languages.
* Each rewrite rule includes the `lang` query variable so Polylang can
  resolve the correct language content on the archive request.

In single-language installations (Polylang inactive) the original
behaviour is preserved.

> **Note:** After activating/configuring Polylang or changing the
> "page for post type" setting, flush rewrite rules (Settings → Permalinks →
> Save, or `wp rewrite flush`).
