=== PB MEDIA ALL SEO ===
Contributors: pbmedia
Tags: seo, opengraph, schema, sitemap, robots, llms
Requires at least: 6.6
Tested up to: 6.7
Requires PHP: 8.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Kompletny plugin SEO dla wszystkich post_type: meta tagi, OpenGraph, Schema JSON-LD, sitemapy XML, robots.txt oraz llm.txt.

== Description ==

PB MEDIA ALL SEO to produkcyjny plugin SEO dla WordPressa, który obsługuje wszystkie zarejestrowane post_type (domyślne i customowe).

**Funkcje:**

* 3 meta boxy w edytorze wpisu (Classic Editor + Gutenberg):
  * Meta Tagi SEO (title, description, keywords, author, canonical, robots, custom meta)
  * Tagi OpenGraph (og:title, og:description, og:image, og:image:alt, og:locale, og:type, og:url, og:site_name, custom OG)
  * Schema JSON-LD (z walidacją JSON przed zapisem)
* Strona ustawień "PB MEDIA SEO" w sidebar menu z konfiguracją:
  * Facebook App ID (fb:app_id)
  * Sitemap XML stron — `/sitemapa.xml`
  * Sitemap XML obrazów — `/sitemapa-image.xml`
  * Edytor robots.txt serwowanego pod `/robots.txt`
  * Edytor llm.txt serwowanego pod `/llm.txt`
* Sitemapy pluginu współistnieją niezależnie obok wbudowanej `wp-sitemap.xml` WordPressa.
* Automatyczne odświeżanie sitemap przy publikacji/aktualizacji/usunięciu wpisów.
* Cache w transientach dla wydajności.
* Pełna zgodność z PHP 8.2+, WordPress Coding Standards, sanityzacja i escapowanie wszystkich danych.

== Installation ==

1. Wgraj folder `pb-media-all-seo` do `/wp-content/plugins/`.
2. Aktywuj plugin w menu "Wtyczki".
3. Przejdź do "PB MEDIA SEO" w lewym menu admina, aby skonfigurować ustawienia globalne.
4. Edytuj poszczególne wpisy/strony, aby uzupełnić meta tagi SEO, OpenGraph i Schema JSON.

== Changelog ==

= 1.0.0 =
* Pierwsza wersja produkcyjna.
