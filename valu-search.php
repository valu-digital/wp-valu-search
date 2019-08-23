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

function can_enable_live_updates() {
	if ( ! defined( 'VALU_SEARCH_ENDPOINT' ) ) {
		return false;
	}

	if ( ! defined( 'VALU_SEARCH_CUSTOMER_SLUG' ) ){
		return false;
	}

	if ( ! defined( 'VALU_SEARCH_CUSTOMER_ADMIN_API_KEY' ) ){
		return false;
	}

	if ( ! defined( 'VALU_SEARCH_ENABLE_LIVE_UPDATES' ) ) {
		return false;
	} else if ( ! VALU_SEARCH_ENABLE_LIVE_UPDATES ) {
		return false;
	}

	return true;
}

if( can_enable_live_updates() ){
	require('handle-post-change.php');
}


add_action( 'wp_head', function () {

	global $post;

	if ( ! $post ) {
		return;
	}

	$public = $post->post_status === 'publish';

	$bloginfo = get_blog_info_array();
	$blogname = $bloginfo['blogname'];
	$blog_path = $bloginfo['blog_path'];

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
		'showInSearch'    => apply_filters( 'valu_search_show_in_search', $post, $public ),
		'contentSelector' => apply_filters( 'valu_search_content_selector', '.main' ),
		'cleanupSelector' => apply_filters( 'valu_search_cleanup_selector', '' ),
		'title'           => $post->post_title,
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

function get_blog_info_array(){
	if ( is_multisite() ) {
		$details   = \get_blog_details();
		$bloginfo['blogname']  = $details->blogname;
		$bloginfo['blog_path'] = trim( $details->path, '/' );
	} else {
		$bloginfo['blogname']  = get_bloginfo();
		$bloginfo['blog_path'] = $_SERVER['REQUEST_URI'];
	}
	return $bloginfo;
}


add_action( 'template_redirect', function(){

	if ( '/valu-search.json' === $_SERVER['REQUEST_URI'] ) {
		$bloginfo = get_blog_info_array();
		$config['siteName'] = $bloginfo['blogname'];
		$content = apply_filters( 'valu_search_site_config' , $config );
		echo json_encode( $content );
		die();
	}

});