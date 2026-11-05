# Search demand (`OOBJ_SEARCH_Vocabulary::DEMAND`)

How much shoppers search for each material, fitting, colour, theme and gauge. It's a constant in
`inc/query/class-oobj-search-vocabulary.php`, written by hand from `tools/demand.php`'s output; nothing updates it
by itself.

## What it does

It only orders things; it never changes which products a search finds.

- Suggestions (`OOBJ_SEARCH_Suggestions`): a value's score is multiplied by `1 + ln(1 + demand) / 2`, so what people
  ask for wins over what they don't (titanium over surgical steel). A value not listed counts 0 (weight 1).
- "Search by" (the panel and the results page): materials, fittings, colours and themes go most searched first,
  then most products. Gauges and sizes go smallest first and don't use it.

With it empty, everything still works: suggestions are picked on how distinctive a value is for the results and the
share of them it keeps; the buttons go most products first.

## Rebuilding it

1. **Collect real searches** into one text file, in sections (`## name`), one query per line as
   `count<TAB>query` (or a bare query, counting 1). Lines starting `#` are comments.
   - `## site-search-ga4`: Google Analytics, event `view_search_results`, dimension `searchTerm`, metric
     `eventCount`, the longest range GA has. Weight 1 per search.
   - `## site-search-logs`: `?s=` searches from the server's access logs (through `bin/prod.sh`; they only go back a
     few days), one line per search. Weight 1.
   - `## google-search-console`: Search Console queries for `sc-domain:outofbody.com.au`, the longest range (16
     months), the top queries by impressions, `impressions<TAB>query`. Weight 1/50 per impression: an impression is
     a weaker signal than a search on the site.
2. **Run** `php tools/demand.php queries.txt > demand.php`. It reads each query with the current
   `OOBJ_SEARCH_Query_Parser`, adds its weight to every value understood, rounds, and prints the constant (most
   searched first); stderr says how many values each facet got.
3. **Paste** it over `DEMAND`, run `composer test:query` and `composer test` (suggestion tests may need new
   expectations), and release as usual.

Rebuild after the vocabulary learns new words (so old searches count towards them) or every few months.

The queries are the shop's own data: keep the file out of this repo. November 2026's (GA4 300 terms, server logs 39,
Search Console 400 queries) is in the private `oobj-local-env` repo, `search-demand/2026-11-queries.txt`; running the
script on it gives the current constant exactly.
