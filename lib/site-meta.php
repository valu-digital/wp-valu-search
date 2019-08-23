<?php

namespace ValuSearch;


function render_valu_search_json() {

    // XXX does not work for sites mounted on sub urls
	if ( '/valu-search.json' !== $_SERVER['REQUEST_URI'] ) {
        return;
    }

    $bloginfo = get_blog_info_array();

    $config = [
        'siteName' => $bloginfo['blogname'],
    ];

    $config = apply_filters( 'valu_search_site_config' , $config );

    header( 'Content-type: application/json' );
    echo json_encode( $config );
    die();

}

add_action( 'template_redirect', __NAMESPACE__ . '\\render_valu_search_json', 10 );