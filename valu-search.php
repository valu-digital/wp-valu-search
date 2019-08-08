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

const ROOT_TAG = '__ROOT';

//placeholder
const VALU_SEARCH_ENDPOINT = 'http://localhost:3000';

add_action( 'wp_head', function () {

	global $post;

	if ( ! $post ) {
		return;
	}

	$public = $post->post_status === 'publish';

	$show = true;

	if ( $show ) {
		// TODO check if manually hidden using ACF(?) field
		// if ( get_post_meta( $post->ID, 'show_in_search' ) === false ) {
		//     $show = false;
		// }
	}

	if ( is_multisite() ) {
		$details   = \get_blog_details();
		$blogname  = $details->blogname;
		$blog_path = trim( $details->path, '/' );
	} else {
		$blogname  = ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ? "https" : "http" ) . "://{$_SERVER['HTTP_HOST']}";
		$blog_path = $_SERVER['REQUEST_URI'];
	}


	if ( ! $blog_path ) {
		$blog_path = ROOT_TAG;
	}

	// Default tags for elasticsearch
	$tags = [
		'html',
		'wordpress',
		'wp_post_type/' . $post->post_type,
		'wp_blog_name/' . sanitize_title( $blogname ),
		'wp_blog_path/' . $blog_path,
		$public ? 'public' : 'private',
	];


	$meta = [
		'showInSearch'    => $show,
		'contentSelector' => apply_filters( 'valu_search_content_selector', '.main' ),
		'title'           => $post->post_title,
		'siteName'        => $blogname,
		'language'        => substr( get_locale(), 0, 2 ),
		'created'         => get_the_date( 'c', $post ),
		'modified'        => get_the_modified_date( 'c', $post ),
		'tags'            => $tags,
	];

	// Use the post language if using polylang instead of the blog locale.
	if ( function_exists( 'pll_get_post_language' ) ) {
		$meta['language'] = pll_get_post_language( $post->ID, 'slug' );
	}

	// Allow any custom modifications
	$meta = apply_filters( 'valu_search_meta', $meta, $post );

	$json = wp_json_encode( $meta );
	echo "<script type='text/json' id='valu-search'>$json</script>";

} );

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
				//'X-Valu-Search-Api-Key' => VALU_SEARCH_API_KEY,     // ?
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