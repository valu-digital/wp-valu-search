<?php

namespace ValuSearch;

/*
Plugin Name: Valu Search
Version: 0.8.0
Plugin URI: https://www.valu.fi
Description: Expose page metadata for the Search crawler
Author: Valu Digital
Author URI: https://github.com/valu-digital/wp-valu-search
*/

function get_fetch_endpoint()
{
    if (defined('FINDKIT_FETCH_ENDPOINT')) {
        return FINDKIT_FETCH_ENDPOINT;
    }

    return get_option('findkit_fetch_endpoint');
}

function get_live_update_endpoint()
{
    if (defined('FINDKIT_LIVE_UPDATE_ENDPOINT')) {
        return FINDKIT_LIVE_UPDATE_ENDPOINT;
    }

    $option = get_option('findkit_live_update_endpoint');

    if ($option) {
        return $option;
    }

    $legacy_vs_endpoint = defined('VALU_SEARCH_ENDPOINT')
        ? VALU_SEARCH_ENDPOINT
        : 'https://api.search.valu.pro/v1-production';

    return $legacy_vs_endpoint .
        '/customers/' .
        VALU_SEARCH_USERNAME .
        '/update-documents';
}

add_action(
    'wp_head',
    __NAMESPACE__ . '\\add_findkit_fetch_endpoint_to_head',
    1
);

function add_findkit_fetch_endpoint_to_head()
{
    $endpoint = get_fetch_endpoint();
    if (!$endpoint) {
        return;
    }

    // echo endpoint for search UI usage
    $json = wp_json_encode(['FINDKIT_FETCH_ENDPOINT' => esc_url($endpoint)]);
    echo '<script type="text/javascript">Object.assign(window, ' .
        $json .
        ')</script>';
}

function can_live_update()
{
    if (!defined('VALU_SEARCH_USERNAME')) {
        error_log(
            'Valu Search - Cannot enable live updates: VALU_SEARCH_USERNAME missing'
        );
        return false;
    }

    if (!get_api_secret()) {
        error_log(
            'Valu Search - Cannot enable live updates:  FINDKIT_API_SECRET missing'
        );
        return false;
    }

    return true;
}

function get_api_secret()
{
    if (defined('VALU_SEARCH_API_SECRET')) {
        return VALU_SEARCH_API_SECRET;
    }

    if (defined('VALU_SEARCH_UPDATE_API_KEY')) {
        return VALU_SEARCH_UPDATE_API_KEY;
    }

    return null;
}

function get_blog_info_array()
{
    $bloginfo = [];

    if (is_multisite()) {
        $details = \get_blog_details();
        $bloginfo['blogname'] = $details->blogname;
        $bloginfo['blog_path'] = trim($details->path, '/');
    } else {
        $bloginfo['blogname'] = get_bloginfo();
        $bloginfo['blog_path'] = $_SERVER['REQUEST_URI'];
    }

    return $bloginfo;
}

require_once __DIR__ . '/lib/flash-message.php';
require_once __DIR__ . '/lib/page-meta.php';
require_once __DIR__ . '/lib/site-meta.php';
require_once __DIR__ . '/lib/JwtAuth.php';

function can_see_status_messages()
{
    $show_notices = apply_filters('valu_search_show_admin_notices', false);
    return $show_notices;
}

function handle_post_status_transition($new_status, $old_status, $post)
{
    if (!$post) {
        return;
    }

    $enabled =
        defined('VALU_SEARCH_ENABLE_LIVE_UPDATES') &&
        VALU_SEARCH_ENABLE_LIVE_UPDATES;

    if (!$enabled) {
        return;
    }

    if (!can_live_update()) {
        return;
    }

    // We can bail out if the status is not publish or is not transitioning from or
    // to it eg. it's a draft or draft being moved to trash for example
    if ('publish' !== $new_status && 'publish' !== $old_status) {
        return;
    }

    // Revision are not public
    if (wp_is_post_revision($post)) {
        return;
    }

    enqueue_live_update($post);
}

// This is called always when post is being saved even when the post status does
// not actually change.
add_action(
    'transition_post_status',
    __NAMESPACE__ . '\\handle_post_status_transition',
    10,
    3
);

/**
 * Flush any pending updates enqueued with enqueue_live_update()
 */
function flush_live_updates()
{
    global $valu_search_pending_updates;

    if (!$valu_search_pending_updates) {
        return;
    }

    $error = live_update($valu_search_pending_updates);

    $valu_search_pending_updates = [];

    if (!can_see_status_messages()) {
        return;
    }

    if (is_wp_error($error)) {
        enqueue_flash_message('error', $error->get_error_message());
    } else {
        enqueue_flash_message('success', 'Search index update success!');
    }
}

// Send updates on shutdown when we can be sure that post changes have been saved
add_action('shutdown', __NAMESPACE__ . '\\flush_live_updates', 10);

/**
 * Trashed, draft and private posts have different permalinks than the public
 * one. This function gets the permalink as if the post were public.
 */
function get_public_permalink($post)
{
    // trashed just has a __trashed suffix
    if (preg_match('/__trashed\/\z/', get_permalink($post))) {
        $url = get_permalink($post);
        return preg_replace('/__trashed\/\z/', '/', $url);
    }

    // create public clone
    $clone = clone $post;
    $clone->post_status = 'publish';
    // post_name might not be available yet
    $clone->post_name = sanitize_title(
        $clone->post_name ? $clone->post_name : $clone->post_title,
        $clone->ID
    );

    return get_permalink($clone);
}

/**
 * Live update given posts. Returns \WP_Error instance on failure, null on
 * success.
 */
function live_update(array $targets)
{
    if (!can_live_update()) {
        return new \WP_Error(
            'valu_search_live_update_failed',
            'Credentials not configured'
        );
    }

    $urls = [];

    foreach ($targets as $post) {
        if (is_numeric($post)) {
            $post = get_post($post);
        }

        if (!$post) {
            continue;
        }

        $should_update = apply_filters(
            'valu_search_should_update',
            php_sapi_name() !== 'cli',
            $post
        );

        if ($should_update && 10000 > count($urls)) {
            if (is_string($post)) {
                // Assume plain url
                $urls[] = $post;
            } else {
                $urls[] = get_public_permalink($post);
            }
        }
    }

    if (count($urls) === 0) {
        return;
    }

    $json = wp_json_encode(['urls' => $urls]);

    $response = wp_remote_request(get_live_update_endpoint(), [
        'headers' => [
            'Content-type' => 'application/json',
            'X-Valu-Search-Auth' => get_api_secret(),
        ],
        'method' => 'POST',
        'body' => $json,
        'timeout' => 20,
    ]);

    do_action('valu_search_live_update_result', $response);

    if (is_wp_error($response)) {
        error_log(
            'Failed to send live update api request: ' .
                $response->get_error_message()
        );
        return new \WP_Error(
            'valu_search_live_update_failed',
            'Update request failed: ' . $response->get_error_message()
        );
    }

    $status_code = wp_remote_retrieve_response_code($response);

    if (200 !== $status_code) {
        $body = wp_remote_retrieve_body($response);
        error_log(
            "Failed to send live update api request: Bad api response status code $status_code. Body: $body"
        );
        return new \WP_Error(
            'valu_search_live_update_failed',
            "Bad api response status code $status_code. Body: $body"
        );
    }
}

/**
 * Enqueue live update to be sent on the shutdown hook
 */
function enqueue_live_update(\WP_Post $post)
{
    global $valu_search_pending_updates;

    if (!$valu_search_pending_updates) {
        $valu_search_pending_updates = [];
    }

    $valu_search_pending_updates[] = $post;
}

/**
 *  Handles the messages to be shown by admin notice hook.
 */
function show_sync_notice()
{
    if (!can_see_status_messages()) {
        return;
    }

    foreach (get_flash_messages() as $message) {
        if ('success' === $message['type']) {
            success_message($message['message']);
        } else {
            error_message($message['message']);
        }
    }

    clear_flash_messages();
}

add_action('admin_notices', __NAMESPACE__ . '\\show_sync_notice');

function success_message($message)
{
    ?>
	<div class="notice notice-success is-dismissible">
		<p><?php echo esc_html($message); ?></p>
	</div>
	<?php
}

function error_message($error)
{
    ?>
	<div class="notice notice-error is-dismissible">
		<p>There was an error reindexing the page!
			<?php var_dump($error); ?>
		</p>
	</div>
	<?php
}

new JwtAuth();
