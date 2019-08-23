<?php

namespace ValuSearch;

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

require_once( __DIR__ . '/page-meta.php');
require_once( __DIR__ . '/site-meta.php');

if ( can_enable_live_updates() ){
	require_once( __DIR__ . '/handle-post-change.php');
}