=== PB MEDIA ALL SEO ===
Contributors: pbmedia
Tags: seo, opengraph, schema, sitemap, robots, llms, bulk-edit
Requires at least: 6.6
Tested up to: 6.7
Requires PHP: 8.2
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Kompletny plugin SEO: meta tagi, OpenGraph, Schema JSON-LD, sitemapy XML, robots.txt, llm.txt, bulk edit, import/export, SEO score analyzer i auto-generator OG image.

== Description ==

Funkcje:

* 3 meta boxy w edytorze (Classic + Gutenberg): Meta Tagi SEO, Tagi OpenGraph, Schema JSON-LD
* SEO Score Analyzer — live widget z oceną 0–100 (długość title/description, gęstość frazy, word count)
* Auto OG Image Generator — 1200x630 PNG z featured image + tekst overlay (GD)
* Bulk Edit SEO — masowa edycja meta dla wielu wpisów naraz
* Import / Export — JSON envelope z opcjami i postmeta, migracja między instalacjami
* Sitemapy XML współistniejące obok wp-sitemap.xml: /sitemapa.xml i /sitemapa-image.xml
* Custom robots.txt i llm.txt serwowane z bazy danych
* fb:app_id globalny

== Changelog ==

= 1.1.0 =
* FIX: Sitemapy zwracane z prawidłowym Content-Type application/xml (parse_request hook + buffer cleanup)
* FIX: Sitemap obrazów grupuje obrazki po stronie nadrzędnej (zalecany format Google)
* NEW: SEO Score Analyzer (live, REST endpoint, Classic + Gutenberg)
* NEW: Bulk Edit SEO submenu
* NEW: Import / Export JSON
* NEW: Auto OG Image Generator (GD)

= 1.0.0 =
* Pierwsza wersja produkcyjna.
