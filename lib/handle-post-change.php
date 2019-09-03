<?php

namespace ValuSearch;

require_once __DIR__ . '/flash-message.php';

$GLOBALS['valu_search_pending_update_array'] = array();

function can_see_status_messages() {
	return is_super_admin();
}


function handle_post_change( $new_status, $old_status, $post ) {
	if ( ! $post ) {
		return;
	}

	// We can bail out if the status is not publish or is not transitioning from or
	// to it eg. it's a draft or draft being moved to trash for example
	if ( $new_status !== 'publish' && $old_status !== 'publish') {
		return;
	}

	// Revision are not public
	if ( wp_is_post_revision( $post ) ) {
		return;
	}

	// The post data might not be actually saved to the database at this point.
	// Defer update sending using a global.

	$valu_search_pending_update = [
		'post' => $post,
		'url' => get_public_permalink( $post ),
	];

	$GLOBALS['valu_search_pending_update_array'][ $valu_search_pending_update['url'] ] = $valu_search_pending_update['post'];

}

// This is called always when post is being saved even when the post status does
// not actually change.
add_action( 'transition_post_status', __NAMESPACE__ . '\\handle_post_change', 10, 3 );

function send_update() {

	$valu_search_pending_update_array = $GLOBALS['valu_search_pending_update_array'];

	if ( ! $valu_search_pending_update_array ) {
			return;
	}

	$url_array = array();

	foreach ( $valu_search_pending_update_array as $url => $post ) {
		$should_update = apply_filters( 'valu_search_should_update', true, $post );

		if ( $should_update ) {
			array_push( $url_array, $url );
		}
	}

	$json = wp_json_encode( $url_array );

	$endpoint_url = VALU_SEARCH_ENDPOINT . '/customers/' . VALU_SEARCH_USERNAME . '/update-documents';

	$response = wp_remote_request(
		$endpoint_url,
		[
			'headers' => [
				'Content-type' => 'application/json',
				'X-Valu-Search-Auth' => VALU_SEARCH_UPDATE_API_KEY,
			],
			'method'  => 'POST',
			'body'    => $json,
			// No need to wait for response when not showing the status
			'blocking' => can_see_status_messages(),
			'timeout' => 20,
		]
	);

	if ( ! can_see_status_messages() ) {
		return;
	}

	if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
		enqueue_flash_message( "Search index update success!", 'success' );
	} else {
		enqueue_flash_message( $response, 'error' );
	}
}

// Send updates on shutdown when we can be sure that post changes have been saved
add_action( 'shutdown', __NAMESPACE__ . '\\send_update', 10 );

/**
 * Trashed, draft and private posts have different permalinks than the public
 * one. This function gets the permalink as if the post were public.
 */
function get_public_permalink( $post ) {

	// trashed just has a __trashed suffix
	if ( preg_match( '/__trashed\/\z/', get_permalink( $post ) ) ) {
		$url = get_permalink( $post );
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

	return get_permalink( $clone );
}


/**
 *  Handles the messages to be shown by admin notice hook.
 */
function show_sync_notice() {
	if ( ! can_see_status_messages() ) {
		return;
	}

	foreach ( get_flash_messages() as $message ) {
		if ( "success" === $message['type'] ) {
			success_message( $message['message'] );
		} else {
			error_message( $message['message'] );
		}
	}

	clear_flash_messages();
}

add_action( 'admin_notices', __NAMESPACE__ . '\\show_sync_notice' );

function success_message( $message ) {
	?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( $message ) ?></p>
		</div>
	<?php
}

function error_message( $error ) {
	?>
		<div class="notice notice-error is-dismissible">
			<p>There was an error reindexing the page!
				<?php var_dump( $error ); ?>
			</p>
		</div>
	<?php
}