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


add_action('wp_head', function() {

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
        $details = \get_blog_details();
        $blogname = $details->blogname;
        $blog_path = trim(  $details->path, '/' );
    } else {
        $blogname = 'fixme'; // XXX fixme
        $blog_path = '/';
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
        'showInSearch' => $show,
        // Theme may override the selector if needed
        'contentSelector' => apply_filters( 'valu_search_content_selector', '.content-page, .entry-content' ),
        'title' => $post->post_title,
        'siteName' => $blogname,
        'language' => substr( get_locale(), 0, 2 ),
        'created' => get_the_date( 'c', $post ),
        'modified' => get_the_modified_date( 'c', $post ),
        'tags' => $tags,
    ];

    // Use the post language if using polylang instead of the blog locale.
    if ( function_exists( 'pll_get_post_language' ) ) {
        $meta[ 'language' ] = pll_get_post_language( $post->ID, 'slug' );
    }

    // Allow any custom modifications
    $meta = apply_filters( 'valu_search_meta', $meta, $post );

    $json = wp_json_encode( $meta );
    echo "<script type='text/json' id='valu-search'>$json</script>";

});
