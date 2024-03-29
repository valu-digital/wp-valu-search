<?php

namespace ValuSearch;

function get_page_meta(\WP_post $post)
{
    $public = $post->post_status === 'publish';

    $bloginfo = get_blog_info_array();
    $blogname = $bloginfo['blogname'];

    $url_parts = parse_url(home_url());
    $domain = $url_parts['host'];

    if (\class_exists('\Headup\Headup') && \Headup\Headup::is_active()) {
        $domain = \Headup\Headup::get_public_host();
    }

    // Default tags for Valu Search index
    $tags = [
        'wordpress',
        'domain/' . $domain . '/' . 'wordpress',
        'wp_post_type/' . $post->post_type,
        'domain/' . $domain . '/' . 'wp_post_type/' . $post->post_type,
        'wp_blog_name/' . sanitize_title($blogname),
        'domain/' . $domain . '/' . 'wp_blog_name/' . sanitize_title($blogname),
        $public ? 'public' : 'private',
    ];

    $public_taxonomies = get_taxonomies(['public' => true], 'names');
    $post_taxonomies = get_the_taxonomies($post->ID);

    foreach ($post_taxonomies as $taxonomy_key => $taxonomy_value) {
        // only expose public taxonomies as tags
        if (in_array($taxonomy_key, $public_taxonomies)) {
            $terms = get_the_terms($post, $taxonomy_key);
            foreach ($terms as $term) {
                array_push(
                    $tags,
                    'domain/' .
                        $domain .
                        '/' .
                        'wp_taxonomy/' .
                        $taxonomy_key .
                        '/' .
                        $term->slug
                );
                array_push(
                    $tags,
                    'wp_taxonomy/' . $taxonomy_key . '/' . $term->slug
                );
            }
        }
    }

    $title = wp_specialchars_decode(
        is_archive() ? get_the_archive_title() : $post->post_title
    );
    $created = get_the_date('c', $post);
    $modified = get_the_modified_date('c', $post);

    $custom_fields = [];
    $custom_fields['date'] = apply_filters(
        'valu_search_custom_fields_date',
        [],
        $post
    );
    $custom_fields['keyword'] = apply_filters(
        'valu_search_custom_fields_keyword',
        [],
        $post
    );
    $custom_fields['number'] = apply_filters(
        'valu_search_custom_fields_number',
        [],
        $post
    );

    $meta = [
        'showInSearch' => apply_filters(
            'valu_search_show_in_search',
            is_archive() ? false : $public,
            $post
        ),
        'contentSelector' => apply_filters(
            'valu_search_content_selector',
            '',
            $post
        ),
        'contentNoHighlightSelector' => apply_filters(
            'valu_search_no_highlight_content_selector',
            '',
            $post
        ),
        'cleanupSelector' => apply_filters(
            'valu_search_cleanup_selector',
            '',
            $post
        ),
        'title' => apply_filters(
            'valu_search_title',
            html_entity_decode($title),
            $post
        ),
        'created' => apply_filters('valu_search_created', $created, $post),
        'modified' => apply_filters('valu_search_modified', $modified, $post),
        'tags' => apply_filters('valu_search_tags', $tags, $post),
        'superwords' => apply_filters('valu_search_superwords', [], $post),
        'customFields' => apply_filters(
            'valu_search_custom_fields',
            $custom_fields,
            $post
        ),
    ];

    // Use the post language if using polylang instead of the blog locale.
    if (function_exists('pll_get_post_language')) {
        $meta['language'] = pll_get_post_language($post->ID, 'slug');
    }

    if (empty($meta['language'])) {
        $meta['language'] = substr(get_bloginfo('language'), 0, 2);
    }

    // Allow any custom modifications
    $meta = apply_filters('valu_search_page_meta', $meta, $post);

    if (empty($meta)) {
        return;
    }

    return $meta;
}

function render_page_meta_tag()
{
    global $post;

    if (!$post) {
        return;
    }

    $json = wp_json_encode(get_page_meta($post));
    echo "<script type='application/json' id='valu-search'>$json</script>";
}

add_action('wp_head', __NAMESPACE__ . '\\render_page_meta_tag', 10);

function headup_tag($tags, $type, $post)
{
    // Suppport old headup version where the second arg is the post
    if ($type instanceof \WP_Post) {
        $post = $type;
        $type = 'post';
    }

    // This is only for post page. Eg. skip archives.
    if ('post' !== $type) {
        return $tags;
    }

    $meta = get_page_meta($post);

    $tags[] = [
        'tag' => 'script',
        'attrs' => [
            'id' => 'valu-search',
            'type' => 'application/json',
            // Magic attribute in headup. This will render the attribute value
            // as the children formatted using JSON.stringify()
            '__jsonChild' => $meta,

            // For backwards compatiblity for legacy headup versions
            'dangerouslySetInnerHTML' => [
                '__html' => wp_json_encode($meta),
            ],
        ],
    ];

    return $tags;
}

add_filter('headup_head_tags', __NAMESPACE__ . '\\headup_tag', 10, 3);
