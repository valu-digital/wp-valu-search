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

function handle_post_change( $post ) {

	global $post;

	if ( ! $post ) {
		return;
	}

	$slug = get_search_customer_name( $post );

	$url = ( isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ? 'https' : 'http' ) . "://{$_SERVER['HTTP_HOST']}" . '/' . $post->post_name;

	$json = wp_json_encode( [
		'index'    => $slug,
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
		echo "<script type='text/json' id='valu-search'>UPDATE TOIMI</script>";
	} else {
		echo "<script type='text/json' id='valu-search'>UPDATE EITOIMI</script>";
	}
}

function get_search_customer_name( $post ) {

	$slug = $post->post_name;

	if ( ! $slug ) {
		return;
	}

	if ( ! defined( 'WP_ENV' ) || WP_ENV !== 'production' ) {
		$slug = 'dev--' . $slug;
	}

	return $slug;
}