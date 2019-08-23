<?php

namespace ValuSearch;

add_action( 'template_redirect', function(){

	if ( '/valu-search.json' !== $_SERVER['REQUEST_URI'] ) {
        return;
    }

    $bloginfo = get_blog_info_array();
    $config['siteName'] = $bloginfo['blogname'];
    $content = apply_filters( 'valu_search_site_config' , $config );
    echo json_encode( $content );
    die();

});