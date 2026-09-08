# Cache Warmup for TYPO3

This extension provides a simple command line tool for warming up
certain caches.

This extension is especially useful for large installations that deploy and
then flush caches.

Supported TYPO3 versions: **v12 LTS, v13 LTS and v14 LTS**.

## Rootline Cache
One of the main issues here is a large installation with lots of
pages. A `cache:flush` will empty the information on the rootline cache.
When a visitor visits the page again after flushing the cache, and the page
has 100 links to other pages, the rootline for each of the page will be
built. This could take several seconds. A second visitor could visit the
page and then see the nice "Page is being generated" screen. This can be
improved!

A command line script runs directly after `cache:flush` and warms
up all caches. This is mostly `cache_core` (by running the script
itself) and `cache_rootline`.

```
vendor/bin/typo3 cache:warmupPages
```

The command takes an optional argument to limit the work:

```
vendor/bin/typo3 cache:warmupPages all       # default: rootline + pages
vendor/bin/typo3 cache:warmupPages rootline  # only the rootline cache
vendor/bin/typo3 cache:warmupPages pages     # only render the pages
```

Running the script multiple times does not matter, as it solely
acts as a wrapper for fetching the rootline for a page. If it is
already in the cache, the command runs smoothly.

Pages are requested for every combination of frontend user groups that is
relevant for them (`fe_group` of the page itself plus all `fe_group` values
inherited via `extendToSubpages` in the rootline), so access-restricted
pages get a warm cache as well.

## Installation

Install the extension via composer with `composer req b13/warmup`.

In classic (non-composer) installations, extract the contents of this folder
into `typo3conf/ext/warmup` and activate the extension in the Extension
Manager. Note that TYPO3 v14 requires a valid `composer.json` in every
extension, also in classic mode.

## Notes

Note that this extension does not take workspaces or mount points
into account currently! Contributions are welcome.

## Credits

* Benni Mack

## License

GPL2.0+, see LICENSE.txt for more details.
