<?php

namespace ValuSearch;

function render_page_meta_tag() {

	// Valu Search Crawler adds ?_vsid=timestamp query string for all requests. We
	// can bail out if it is not present.
	if ( ! isset( $_GET['_vsid'] ) ) {
		return;
	}

	global $post;

	if ( ! $post ) {
		return;
	}

	$public = $post->post_status === 'publish';

	$bloginfo = get_blog_info_array();
	$blogname = $bloginfo['blogname'];

	// Default tags for Valu Search index
	$tags = [
		'wordpress',
		'wp_post_type/' . $post->post_type,
		'wp_blog_name/' . sanitize_title( $blogname ),
		$public ? 'public' : 'private',
	];


	$meta = [
		'showInSearch'    => apply_filters( 'valu_search_show_in_search', $public, $post ),
		'contentSelector' => apply_filters( 'valu_search_content_selector', '.main', $post ),
		'cleanupSelector' => apply_filters( 'valu_search_cleanup_selector', '', $post ),
		'title'           => $post->post_title,
		'language'        => get_bloginfo( 'language' ),
		'created'         => get_the_date( 'c', $post ),
		'modified'        => get_the_modified_date( 'c', $post ),
		'tags'            => apply_filters( 'valu_search_tags', $tags, $post ),
	];

	// Use the post language if using polylang instead of the blog locale.
	if ( function_exists( 'pll_get_post_language' ) ) {
		$meta['language'] = pll_get_post_language( $post->ID, 'slug' );
	}

	// Allow any custom modifications
	$meta = apply_filters( 'valu_search_page_meta', $meta, $post );

	if ( empty( $meta ) ) {
		return;
	}

	$json = wp_json_encode( $meta );
	echo "<script type='text/json' id='valu-search'>$json</script>";

}

add_action( 'wp_head', __NAMESPACE__ . '\\render_page_meta_tag', 10 );