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

const VALU_SEARCH_CUSTOMER_SLUG = "dev--valufielokuu.json";

add_action( 'transition_post_status', __NAMESPACE__ . '\\handle_post_change', 10, 3 );

function handle_post_change( $post ) {

	global $post;

	if ( ! $post ) {
		return;
	}

	$url = ( isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ? 'https' : 'http' ) . "://{$_SERVER['HTTP_HOST']}" . '/' . $post->post_name;

	$json = wp_json_encode( [
		'customerSlug'    => VALU_SEARCH_CUSTOMER_SLUG,
		'url'      => $url,
	] );

	$url = VALU_SEARCH_ENDPOINT . "/trigger-scrape-site";

	$response = wp_remote_request(
		$url,
		array(
			'headers' => [
				'Content-type' => 'application/json',
				'X-Valu-Search-Api-Key' => VALU_SEARCH_API_KEY,
			],
			'method'  => 'POST',
			'body'    => $json,
		)
	);
	if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
		echo "Placeholder: OK";
	} else {
		echo "Placeholder: NOT OK";
	}
}