<?php

namespace ValuSearch;

function render_page_meta_tag() {

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

	// Default tags for Valu Search index
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

}

add_action( 'wp_head', __NAMESPACE__ . '\\render_page_meta_tag', 10 );