# Valu Search for WordPress

WordPress plugin for [Valu Search](https://search.valu.pro).

This plugin has two features

-   Instructs the Valu Search Crawler on how to crawl and scrape the site
-   Sends [live updates](#live-updates) to the Valu Search Index as content
    creators add, update and delete pages

This plugin does not provide any UI. It just exposes some config options and
filters for developers.

## Filters

### `valu_search_content_selector`

Parameters

-   `$selector` (string) CSS selector for picking the content elements
-   `$post` (WP_Post) the post of the page being indexed

Multiple selectors can be separated by comma. Ex. `.content,.main`

### `valu_search_cleanup_selector`

Parameters

-   `$selector` (string) CSS selector for removing elements
-   `$post` (WP_Post) The post of the page being indexed

Remove content from selected elements.

### `valu_search_show_in_search`

Parameters

-   `$show` (boolean) whether to index the page at all
-   `$post` (WP_Post) the post of the page being indexed

### `valu_search_tags`

Parameters

-   `$tags` (string[]) Array of tags
-   `$post` (WP_Post) the post of the page being indexed

List of tags the page gets indexed with. By default the post type, taxonomy
terms, static `wordpress` and `public` / `private` tags are added. These tags
can be used to build custom filtering UIs.

#### `valu_search_preview`

Parameters

-   `$preview`(string) preview string
-   `$post` (WP_Post) the post of the page being indexed

Custom preview content of page shown in search results.

#### `valu_search_superwords`

Parameters

-   `$superwords`(string[]) Array of superwords
-   `$post` (WP_Post) the post of the page being indexed

List of superwords indexed with the page. By default empty array.
Superwords can be used to mark page as an important search result for the
superwords given. Search results with matching superwords are shown first in results.

### `valu_search_page_meta`

-   `$meta` (assoc array)
-   `$post` (WP_Post) the post of the page being indexed

The full data rendered to the meta tag.

See all available fields on <https://search.valu.pro/page-meta>

### `valu_search_site_meta`

-   `$meta` (assoc array)
-   `$post` (WP_Post) the post of the page being indexed

Global options for the crawler exposed on `yoursite.example/valu-search.json`.

See all available fields on <https://search.valu.pro/site-meta>

# Live Updates

Live updates are sent optimistically whenever the plugin thinks the content
might have changed on a page. The cloud backend then does a scraping request
on the page to determine what the update actually was (add, update, delete) if
any.

To enable the real time updates add provided credentials to the wp-config:

```php
define( 'VALU_SEARCH_USERNAME', 'username' );
define( 'VALU_SEARCH_UPDATE_API_KEY', '****' );
define( 'VALU_SEARCH_ENABLE_LIVE_UPDATES', true );
```

There's a filter for controlling the update process

### `valu_search_should_update`

-   `$should_update` (boolean) defaults to true
-   `$post` (WP_Post) the post of the page being updated

Return false to prevent the page from being updated.
