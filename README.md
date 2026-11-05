# Out Of Body Search

[![Build and Release](https://github.com/mattdemarillac/oobj-search/actions/workflows/release.yml/badge.svg?branch=main)](https://github.com/mattdemarillac/oobj-search/actions/workflows/release.yml) [![Security](https://github.com/mattdemarillac/oobj-search/actions/workflows/security.yml/badge.svg?branch=main)](https://github.com/mattdemarillac/oobj-search/actions/workflows/security.yml)

By [Out Of Body Jewellery](https://outofbody.com.au/about-us/).

A WooCommerce plugin for outofbody.com.au: the shop's product search understands what shoppers type. "cheap titanium threadless septum 16g under $10" finds titanium, threadless, 16 gauge septum jewellery up to $10, lowest price first, and the results page says what it understood, as links to search again without each part.

## How it works

- **The query reader** (`inc/query/class-oobj-search-query-parser.php`, plain PHP) turns a query into facets (category, material, fitting, colour, theme, gauge, size), a price range, an order and the words left over. What words mean is in the vocabulary (`inc/query/class-oobj-search-vocabulary.php`), built from what shoppers really type: the site's searches and 16 months of Google queries that found the shop (November 2026).
- **Product facts** (`inc/query/class-oobj-search-product-facts.php`, plain PHP) say what each product is, from its name, tags, attributes, labelled description lines ("Material:", "Gauge approx:") and categories. Precision first: a coating over steel isn't titanium. They're kept as a hidden taxonomy (`oobj_search_fact`, `inc/class-oobj-search-facts.php`), one row per fact per product (about 19,000 rows, a few MB), worked out again when a product or variation is saved, and for every product in the background (Action Scheduler) when the vocabulary or the extraction changes.
- **The search** (`inc/class-oobj-search-search.php`) narrows the main query of a product search (`?s=…&post_type=product`): every category named, the facts (alternatives within a facet, all facets), the price from WooCommerce's product lookup table (a variable product counts when any variation is in range), price order for "cheap". Products hidden from search stay hidden. When nothing matches, parts are let go in order (words, theme, colour, size, gauge, fitting, material, order, price, category) until something does, then each is put back if results remain. A query that's only a product number goes to that product. Searches that still find nothing are logged (WooCommerce → Status → Logs, source `oobj-search`).
- **The notice** (`inc/class-oobj-search-notice.php`) puts what was understood in front of the product collection; the theme styles it (the `oob` theme's `OOB_Oobj_Search` adapter). A theme can place it itself with `oobj_search( OOBJ_SEARCH_Notice::class )->html()` and the `oobj_search_show_notice` filter.
- **Searches to try next** (`inc/class-oobj-search-suggestions.php`), in the notice, for plain searches too: every fact value the results have is a candidate, scored by how distinctive it is (much more common among these results than in the whole shop, `inc/class-oobj-search-catalogue.php`), how much shoppers search for it (`OOBJ_SEARCH_Vocabulary::DEMAND`, from the same real searches; how to rebuild it: `docs/demand.md`), whether it keeps a useful share of the results, and the season. A price limit, lowest price first and, in season, a seasonal category ("christmas earrings") are candidates too. The best are offered, at most one of each kind and five in all, worded naturally ("titanium nose ring", "septum clicker", "pink belly bars under $10"). "nose ring" offers "threadless nose ring", "18g nose ring", "titanium nose ring", "rose gold nose ring", "celestial nose ring".
- **The search panel** (`inc/class-oobj-search-examples.php`) inside every product search form (the Search block set to products), hidden for the theme to show: example searches that between them use everything the search understands, and "Search by" buttons for the materials, fittings, gauges (with their millimetres, "16g 1.2mm"), sizes, colours and themes the shop has (at least 10 products each; gauges and sizes the most common, smallest first; the rest most searched first) and price limits, which add their words to the search. The `oob` theme shows it while the search is in use, three examples at a time. `wp oobj-search examples` counts what each example finds.
- **"Search by" on the results page** (in the notice): the same groups for the results, as links: what the search has is shown on (a link to search without it); the values among the results that would narrow them (at least 3 products, not all) and price limits each link to the search with them added. Folded; the `oob` theme opens it on wider screens.
- **Seasons** (`OOBJ_SEARCH_Vocabulary::SEASONS`): spooky things from 15 September to 31 October, Christmas things from 1 November to 25 December. In season they're favoured in suggestions, and their examples and buttons are shown first in the panel, by the visitor's date (so a cached page changes on time).

Nothing is stored per search, and search pages stay the same URLs, so the page cache treats them as before.

## WP-CLI

```
wp oobj-search explain "cheap titanium nose ring"   # what it means and how many products it finds
wp oobj-search index                                # work out every product's facts now
wp oobj-search coverage                             # products with each kind of fact, facts kept
wp oobj-search examples                             # what each example search finds
```

## Development

- `composer test:query`: the plain-PHP part (query reader, vocabulary, product facts) without WordPress, on the host, in a second. Includes 185 real queries read by hand (`tests/query/fixtures/real-queries.php`) and 272 real products (`tests/query/fixtures/products.json`).
- `composer test`: everything, with WordPress and WooCommerce, in the local stack's `litespeed` container (database `oobj_search_tests`).
- `composer test:coverage`: fails below 100% of lines.
- `composer lint`: WordPress Coding Standards.

How to tune it (what each setting does, finding what isn't understood, checking a change): `docs/tuning.md`. To teach it a word, add the phrase to the vocabulary and a real query to the fixtures; product facts are worked out again by themselves when the vocabulary changes.

## Releases and updates

Every push to `main` publishes a GitHub release named after `Version` in `oobj-search.php` (and `OOBJ_SEARCH_VERSION`), once lint and the tests pass with 100% coverage. Sites update from those releases like any plugin (`OOBJ_SEARCH_Updates`; a private repo needs `OOBJ_SEARCH_GITHUB_TOKEN` in `wp-config.php`). Activating the plugin works out every product's facts in the background.
