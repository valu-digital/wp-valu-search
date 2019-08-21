<?php

namespace Valu\Search;

/*
Plugin Name: Valu Search
Version: 0.1.0
Plugin URI: https://www.valu.fi
Description: Expose page metadata for the Search crawler
Author: Valu Digital
Author URI: https://bitbucket.org/valudigital/valu-search
*/

add_action( 'transition_post_status', __NAMESPACE__ . '\\handle_post_change', 10, 3 );

function handle_post_change( $new_status, $old_status, $post ) {

	if ( $new_status !== 'publish' && $old_status !== 'publish') {
		return;
	}

	if ( ! $post ) {
		return;
	}
	$url = get_generic_permalink($post);

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
				'X-Valu-Search-Api-Key' => VALU_SEARCH_API_KEY,
				'X-Customer-Admin-Api-Key' => VALU_SEARCH_CUSTOMER_ADMIN_API_KEY,
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

function get_generic_permalink($post){
	$permalink_array = get_sample_permalink( $post->ID, $post->post_title, '' );
	return str_replace( '%pagename%', $permalink_array[1], $permalink_array[0] );
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