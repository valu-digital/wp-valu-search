<?php

namespace Valu\Search;

require_once __DIR__ . '/flash-message.php';

add_action( 'transition_post_status', __NAMESPACE__ . '\\handle_post_change', 10, 3 );
add_action( 'shutdown', __NAMESPACE__ . '\\send_request', 10 );


function send_request(){
	$url = $GLOBALS['valu-search-url'];

	if ( ! $url ) {
		return;
	}


	$json = wp_json_encode( [
		'customerSlug' => VALU_SEARCH_CUSTOMER_SLUG,
		'url' => $url,
	] );

	$endpoint_url = VALU_SEARCH_ENDPOINT . "/customers/" . VALU_SEARCH_CUSTOMER_SLUG . "/update-single-document";

	$response = wp_remote_request(
		$endpoint_url,
		array(
			'headers' => [
				'Content-type' => 'application/json',
				'X-Valu-Search-Auth' => VALU_SEARCH_CUSTOMER_ADMIN_API_KEY,
			],
			'method'  => 'POST',
			'body'    => $json,
		)
	);
	if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
		enqueue_flash_message( "Search index update success!", 'success' );
	} else {
		enqueue_flash_message( $response, 'error' );
	}
}

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

	$GLOBALS['valu-search-url'] = get_generic_permalink($post);
}

function get_generic_permalink($post){

	//check first if post is trashed
	if( preg_match( '/__trashed\/\z/', get_permalink( $post ) ) ){
			$url = get_permalink( $post );
			return preg_replace('/__trashed\/\z/', '/', $url);
	}else{
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
	if ( ! is_super_admin() ) {
		return;
	}

	foreach ( get_flash_messages() as $message ) {
		if ( "success" === $message['type'] ) {
			success_message( $message['message'] );
		} else {
			error_message( $message['message'] );
		}
	}

	delete_transient( get_flash_message_key() );
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