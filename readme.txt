=== PB MEDIA ALL SEO ===
Contributors: pbmedia
Tags: seo, opengraph, schema, sitemap, robots, llms, bulk-edit
Requires at least: 6.6
Tested up to: 6.7
Requires PHP: 8.2
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Kompletny plugin SEO: meta tagi, OpenGraph, Schema JSON-LD, sitemapy XML jako fizyczne pliki, robots.txt, llm.txt, bulk edit, import/export, SEO score analyzer, auto OG image.

== Description ==

Funkcje:

* 3 meta boxy w edytorze (Classic + Gutenberg): Meta Tagi SEO, Tagi OpenGraph, Schema JSON-LD
* Per-post sitemap priority + changefreq dropdowns
* SEO Score Analyzer — live widget z oceną 0–100
* Auto OG Image Generator — 1200x630 PNG z featured image + tekst overlay (GD)
* Bulk Edit SEO — masowa edycja meta dla wielu wpisów
* Import / Export — JSON envelope
* Sitemapy XML jako FIZYCZNE PLIKI w document root (sitemapa.xml + sitemapa-image.xml)
* Współistnienie z wp-sitemap.xml WordPressa
* Custom robots.txt i llm.txt
* fb:app_id globalny

== Changelog ==

= 1.2.0 =
* CRITICAL FIX: Sitemapy generowane jako fizyczne pliki XML w document root, serwowane bezpośrednio przez serwer www. Eliminuje problem Content-Type: text/html powodowany przez inne pluginy/cache.
* NEW: Pole "Priorytet strony w sitemapie" (dropdown 1.0–0.0) w meta boksie SEO
* NEW: Pole "Częstotliwość dla strony w sitemapie" (dropdown always/hourly/daily/weekly/monthly/yearly/never)
* NEW: Strona ustawień pokazuje status fizycznych plików sitemap
* NEW: Przycisk "Wygeneruj sitemapy teraz" do ręcznej regeneracji
* NEW: Auto-flush rewrite rules po update przez init hook (versioned option)
* NEW: lastmod w formacie ISO 8601 z timezone offset
* NEW: Per-post priority + changefreq odczytywane z meta i wstawiane do XML

= 1.1.0 =
* Sitemapy zwracane z prawidłowym Content-Type application/xml
* Sitemap obrazów — grupowanie po stronie nadrzędnej
* SEO Score Analyzer
* Bulk Edit SEO submenu
* Import / Export JSON
* Auto OG Image Generator (GD)

= 1.0.0 =
* Pierwsza wersja produkcyjna.
