<?php

namespace ValuSearch;

require_once __DIR__ . '/flash-message.php';

function can_see_status_messages() {
	return is_super_admin();
}

function send_request() {
	global $pending_update;

	if ( ! $pending_update ) {
		return;
	}

	$should_update = apply_filters( 'valu_search_should_update' , true, $pending_update['post'] );

	if ( ! $should_update ) {
		return;
	}


	$json = wp_json_encode( [
		'url' => $pending_update['url'],
	] );

	$endpoint_url = VALU_SEARCH_ENDPOINT . "/customers/" . VALU_SEARCH_USERNAME . "/update-single-document";

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

add_action( 'shutdown', __NAMESPACE__ . '\\send_request', 10 );

function handle_post_change( $new_status, $old_status, $post ) {

	if ( $new_status !== 'publish' && $old_status !== 'publish') {
		return;
	}


	if ( ! $post ) {
		return;
	}

	if ( wp_is_post_revision( $post ) ){
		return;
	}

	global $pending_update;

	$pending_update = [
		'post' => $post,
		'url' => get_generic_permalink($post),
	];
}

add_action( 'transition_post_status', __NAMESPACE__ . '\\handle_post_change', 10, 3 );

function get_generic_permalink($post){

	//check first if post is trashed
	if ( preg_match( '/__trashed\/\z/', get_permalink( $post ) ) ) {
			$url = get_permalink( $post );
			return preg_replace('/__trashed\/\z/', '/', $url);
	} else {
		$my_post = clone $post;
		$my_post->post_status = 'publish';
		$my_post->post_name = sanitize_title(
			$my_post->post_name ? $my_post->post_name : $my_post->post_title,
			$my_post->ID
		);
		return get_permalink( $my_post );
	}
}

add_action( 'admin_notices', __NAMESPACE__ . '\\show_admin_message_about_valu_search_sync' );

/**
 *  Handles the messages to be shown by admin notice hook.
 */
function show_admin_message_about_valu_search_sync() {
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