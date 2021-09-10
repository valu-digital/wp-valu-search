# Valu Search for WordPress

WordPress plugin for [Valu Search](https://search.valu.pro).

This plugin has two features

-   Instructs the Valu Search Crawler on how to crawl and scrape the site
-   Sends [live updates](#live-updates) to the Valu Search Index as content
    creators add, update and delete pages

This plugin does not provide any UI. It just exposes some config options and
filters for developers.

## Installation

It's on [Packagist](https://packagist.org/packages/valu/wp-valu-search)

    composer require valu/wp-valu-search

If not using composer download a .zip from the [releases][] page and extract it
to `wp-content/plugins`.

[releases]: https://github.com/valu-digital/wp-valu-search/releases

## Hacking

Plugin adds a script tag to page when there is a `_vsid` query parameter in the request.
`_vsid` query parameter value is UUID for Valu Search crawler.

If you wish to test the plugin behaviour on your site after installing it, simply add `_vsid`
query parameter, refresh page, and look for script tag with `id="valu-search"`.

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

`$show` is false if post status is not public OR if the post is archive.

### `valu_search_title`

Parameters

-   `$title`(string) if the post being indexed is archive, the archive title otherwise the post title
-   `$post` (WP_Post) the post of the page being indexed

By default HTML entities in `$title` are decoded using [html_entity_decode](https://www.php.net/manual/en/function.html-entity-decode.php). To use HTML entities in titles filter `$title` through [htmlentities](https://www.php.net/manual/en/function.htmlentities.php).

### `valu_search_created`

Parameters

-   `$created` (date) the created date of page being indexed
-   `$post` (WP_Post) the post of the page being indexed

### `valu_search_modified`

Parameters

-   `$modified` (date) the modified date of page being indexed
-   `$post` (WP_Post) the post of the page being indexed

### `valu_search_tags`

Parameters

-   `$tags` (string[]) Array of tags
-   `$post` (WP_Post) the post of the page being indexed

List of tags the page gets indexed with. By default the post type, taxonomy
terms, static `wordpress` and `public` / `private` tags are added. These tags
can be used to build custom filtering UIs.

#### `valu_search_custom_fields`

Parameters

-   `$custom_fields_associative_array` (array("keyword"=>[], "date"=>[], "number"=>[]);)
    Associative array containing all custom fields associative arrays
-   `$post` (WP_Post) the post of the page being indexed

Custom fields related to the page.

#### `valu_search_custom_fields_date`

Parameters

-   `$custom_fields_date_associative_array` ([]) Associative_array containing custom date field key value pairs
-   `$post` (WP_Post) the post of the page being indexed

Custom date fields related to the page. e.g. eventStart, eventEnd

##### `valu_search_custom_fields_keyword`

Parameters

-   `$custom_fields_keyword_associative_array` ([]) Associative_array containing custom keyword field key value pairs
-   `$post` (WP_Post) the post of the page being indexed

Custom keyword fields related to the page. e.g. productPreview, productId

##### `valu_search_custom_fields_number`

Parameters

-   `$custom_fields_number_associative_array` ([]) Associative_array containing custom keyword field key value pairs
-   `$post` (WP_Post) the post of the page being indexed

Custom number fields related to the page. e.g. productPrice

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

There's a filter for controlling the update process.

In the event of changing posts/pages permalink the new url gets indexed, but
the old url remains in the index until the next full site crawl.

```
/old-url --> /new-url // new url is reindexed
```

In the event that the changed page was of a hierarchical post type, only the
updated page gets reindexed. Other pages that depend on slug of the changed page,
eg. child pages get updated during next crawl.

```
/old-url --> /new-url // new-url is reindexed
/old-url/sub-page --> /new-url/sub-page // new-url/sub-page is not reindexed.
```

### `valu_search_should_update`

Parameters

-   `$should_update` (boolean) defaults to value of `php_sapi_name() !== 'cli'`
-   `$post` (WP_Post) the post of the page being updated

Return false to prevent the page from being updated.

### 'valu_search_show_admin_notices'

-   `$show_notices` (boolean) defaults to false

Return true to enable notices about live updates in wp-admin

## Actions

### 'valu_search_live_update_result'

Action is fired everytime live update request is done

-   `$response` return type of `wp_remote_request()` (array|WP_Error)
