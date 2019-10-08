<?php

namespace ValuSearch;

function get_page_meta( \WP_post $post ) {

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

	$post_taxonomies = get_the_taxonomies();

	foreach ( $post_taxonomies as $taxonomy_key => $taxonomy_value ) {
		if ( 'post_translations' !== $taxonomy_key ) {
			$terms = get_the_terms( $post, $taxonomy_key );
			foreach ( $terms as $term ) {
				array_push( $tags, 'wp_taxonomy/' . $taxonomy_key . '/' . $term->slug );
			}
		}
	}

	$meta = [
		'showInSearch'    => apply_filters( 'valu_search_show_in_search', $public, $post ),
		'contentSelector' => apply_filters( 'valu_search_content_selector', '', $post ),
		'cleanupSelector' => apply_filters( 'valu_search_cleanup_selector', '', $post ),
		'title'           => $post->post_title,
		'created'         => get_the_date( 'c', $post ),
		'modified'        => get_the_modified_date( 'c', $post ),
		'tags'            => apply_filters( 'valu_search_tags', $tags, $post ),
	];

	// Use the post language if using polylang instead of the blog locale.
	if ( function_exists( 'pll_get_post_language' ) ) {
		$meta['language'] = pll_get_post_language( $post->ID, 'slug' );
	}

	if ( empty( $meta['language'] ) ) {
		$meta['language'] = get_bloginfo( 'language' );
	}

	// Allow any custom modifications
	$meta = apply_filters( 'valu_search_page_meta', $meta, $post );

	if ( empty( $meta ) ) {
		return;
	}

	return $meta;


}

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

	$json = wp_json_encode( get_page_meta( $post ) );
	echo "<script type='application/json' id='valu-search'>$json</script>";
}

add_action( 'wp_head', __NAMESPACE__ . '\\render_page_meta_tag', 10 );