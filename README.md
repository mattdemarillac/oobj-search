# Out Of Body Search

A WooCommerce plugin for outofbody.com.au: the shop's product search understands what shoppers type. "cheap titanium threadless septum 16g under $10" finds titanium, threadless, 16 gauge septum jewellery up to $10, cheapest first, and the results page says what it understood, as links to search again without each part.

## How it works

- **The query reader** (`inc/query/class-oobj-search-query-parser.php`, plain PHP) turns a query into facets (category, material, fitting, colour, theme, gauge, size), a price range, an order and the words left over. What words mean is in the vocabulary (`inc/query/class-oobj-search-vocabulary.php`), built from what shoppers really type: the site's searches and 16 months of Google queries that found the shop (November 2026).
- **Product facts** (`inc/query/class-oobj-search-product-facts.php`, plain PHP) say what each product is, from its name, tags, attributes, labelled description lines ("Material:", "Gauge approx:") and categories. Precision first: a coating over steel isn't titanium. They're kept as a hidden taxonomy (`oobj_search_fact`, `inc/class-oobj-search-facts.php`), one row per fact per product (about 19,000 rows, a few MB), worked out again when a product or variation is saved, and for every product in the background (Action Scheduler) when the vocabulary or the extraction changes.
- **The search** (`inc/class-oobj-search-search.php`) narrows the main query of a product search (`?s=…&post_type=product`): every category named, the facts (alternatives within a facet, all facets), the price from WooCommerce's product lookup table (a variable product counts when any variation is in range), price order for "cheap". Products hidden from search stay hidden. When nothing matches, parts are let go in order (words, theme, colour, size, gauge, fitting, material, order, price, category) until something does, then each is put back if results remain. A query that's only a product number goes to that product. Searches that still find nothing are logged (WooCommerce → Status → Logs, source `oobj-search`).
- **The notice** (`inc/class-oobj-search-notice.php`) puts what was understood in front of the product collection; the theme styles it (the `oob` theme's `OOB_Oobj_Search` adapter). A theme can place it itself with `oobj_search( OOBJ_SEARCH_Notice::class )->html()` and the `oobj_search_show_notice` filter.

- **Searches to try next** (`inc/class-oobj-search-suggestions.php`), in the notice, for plain searches too: each adds something of a different kind (a material, a price, a colour, a fitting, a theme, a gauge, cheapest first) that the found products have, preferring one that keeps about a third of them, with how many products it finds. "nose ring" offers "titanium nose ring", "nose ring under $5", "rose gold nose ring", "threadless nose ring", "floral nose ring". Two queries when the page is built.
- **Example searches** (`inc/class-oobj-search-examples.php`) inside every product search form (the Search block set to products), hidden: eight that between them use everything the search understands. The theme shows them while the field has focus (the `oob` theme: four, in a different order on each page). `wp oobj-search examples` counts what each finds.

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

To teach it a word, add the phrase to the vocabulary and a real query to the fixtures; product facts are worked out again by themselves when the vocabulary changes.

## Releases and updates

Every push to `main` publishes a GitHub release named after `Version` in `oobj-search.php` (and `OOBJ_SEARCH_VERSION`), once lint and the tests pass with 100% coverage. Sites update from those releases like any plugin (`OOBJ_SEARCH_Updates`; a private repo needs `OOBJ_SEARCH_GITHUB_TOKEN` in `wp-config.php`). Activating the plugin works out every product's facts in the background.
