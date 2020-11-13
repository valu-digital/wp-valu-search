<?php

namespace ValuSearch;

/*
Plugin Name: Valu Search
Version: 0.5.5
Plugin URI: https://www.valu.fi
Description: Expose page metadata for the Search crawler
Author: Valu Digital
Author URI: https://github.com/valu-digital/wp-valu-search
*/

if ( ! defined( 'VALU_SEARCH_ENDPOINT' ) ) {
	define( 'VALU_SEARCH_ENDPOINT', 'https://api.search.valu.pro/v1-production' );
}

function can_enable_live_updates() {

	if ( ! defined( 'VALU_SEARCH_ENABLE_LIVE_UPDATES' ) ) {
		return false;
	} else if ( ! VALU_SEARCH_ENABLE_LIVE_UPDATES ) {
		return false;
	}

	if ( ! defined( 'VALU_SEARCH_USERNAME' ) ){
		error_log( 'Valu Search - Cannot enable live updates: VALU_SEARCH_USERNAME missing' );
		return false;
	}

	if ( ! defined( 'VALU_SEARCH_UPDATE_API_KEY' ) ){
		error_log( 'Valu Search - Cannot enable live updates:  VALU_SEARCH_UPDATE_API_KEY missing' );
		return false;
	}

	return true;
}


function get_blog_info_array(){
	$bloginfo = [];

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

require_once( __DIR__ . '/lib/page-meta.php');
require_once( __DIR__ . '/lib/site-meta.php');

if ( can_enable_live_updates() ){
	require_once( __DIR__ . '/lib/handle-post-change.php');
}
