<?php

namespace Valu\Search;

add_action( 'transition_post_status', __NAMESPACE__ . '\\handle_post_change', 10, 3 );
add_action( 'shutdown', __NAMESPACE__ . '\\send_request', 10 );

$url = "";

function send_request(){

	if(!$GLOBALS['url']){
		return;
	}

	$url = $GLOBALS['url'];

	$json = wp_json_encode( [
		'customerSlug'    => VALU_SEARCH_CUSTOMER_SLUG,
		'url'      => $url,
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
		$_SESSION['valu_search_sync_post'] = 1;
	} else {
		$_SESSION['valu_search_sync_post'] = $response;
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

	$GLOBALS['url'] = get_generic_permalink($post);
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
	if ( isset( $_SESSION['valu_search_sync_post'] ) ) {
		admin_notice_on_post_submit();
	}
}

function admin_notice_on_post_submit() {
	if ( 1 === $_SESSION['valu_search_sync_post'] ) :
		?>
		<div class="notice notice-success is-dismissible">
			<p>Success! The page was succesfully reindexed.</p>
		</div>
	<?php
	else :
		?>
		<div class="notice notice-error is-dismissible">
			<p>There was an error reindexing the page!
				<?php var_dump( $_SESSION['valu_search_sync_post'] ); ?>
			</p>
		</div>
	<?php
	endif;
	unset( $_SESSION['valu_search_sync_post'] );
}