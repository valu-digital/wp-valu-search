<?php

namespace ValuSearch;

require_once __DIR__ . '/flash-message.php';

function can_see_status_messages() {
	$show_notices = apply_filters('valu_search_show_admin_notices', false);
	return $show_notices;
}


function handle_post_change( $new_status, $old_status, $post ) {
	if ( ! $post ) {
		return;
	}

	// We can bail out if the status is not publish or is not transitioning from or
	// to it eg. it's a draft or draft being moved to trash for example
	if ( 'publish' !== $new_status && 'publish' !== $old_status ) {
		return;
	}

	// Revision are not public
	if ( wp_is_post_revision( $post ) ) {
		return;
	}

	// The post data might not be actually saved to the database at this point.
	// Defer update sending using a global.
	global $valu_search_pending_update_array;

	$valu_search_pending_update_array[ get_public_permalink( $post ) ] = $post;

}

// This is called always when post is being saved even when the post status does
// not actually change.
add_action( 'transition_post_status', __NAMESPACE__ . '\\handle_post_change', 10, 3 );

function send_update() {
	global $valu_search_pending_update_array;

	if ( ! $valu_search_pending_update_array ) {
			return;
	}

	$url_array = array();
	foreach ( $valu_search_pending_update_array as $url => $post ) {
		$should_update = apply_filters( 'valu_search_should_update', true, $post );

		if ( $should_update && 10000 > count( $url_array ) ) {
			array_push( $url_array, $url );
		}

	}

	if(!$url_array){
		return;
	}

	$json = wp_json_encode( [ 'urls' => $url_array ] );

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
			'timeout' => 20,
		]
	);

	do_action('valu_search_live_update_result', $response);

	if ( ! can_see_status_messages() ) {
		return;
	}

	$status_code = wp_remote_retrieve_response_code( $response );

	if ( 200 === $status_code ) {
		$msg = 'Search index update success!';
		$status = 'success';
	} else {
		$msg = "There was an error, this error should be overwritten by the real error";
		if ( is_wp_error( $response ) ) {
			$msg = $response->get_error_message();
		} else if ( $status_code !== 200 ){
			$body = wp_remote_retrieve_body( $response );
			$msg = $status_code . '\n' . $body;
		}
		$status = 'error';
	}

	enqueue_flash_message( $msg, $status );
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
		return preg_replace( '/__trashed\/\z/', '/', $url );
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
		if ( 'success' === $message['type'] ) {
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
		<p><?php echo esc_html( $message ); ?></p>
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