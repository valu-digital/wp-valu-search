<?php

namespace ValuSearch;

class JwtAuth
{
    private $init_error_notice = '';

    function __construct()
    {
        if (!$this->enabled()) {
            return;
        }

        $this->init_error_notice = $this->check_deps();

        add_action('admin_notices', [$this, '__action_admin_notices'], 10);

        // Bailout initilization if we have errors
        if ($this->init_error_notice) {
            return;
        }

        add_action('rest_api_init', [$this, '__action_rest_api_init'], 10);
        add_action('wp_head', [$this, '__action_wp_head'], 10);
    }

    function enabled(): bool
    {
        return defined('VALU_SEARCH_AUTHENTICATED_SEARCH') &&
            VALU_SEARCH_AUTHENTICATED_SEARCH;
    }

    /**
     * Return a html string for admin_notices if there's an issue in enabling
     * the jwt authentication
     */
    function check_deps()
    {
        ob_start();

        $jwt_url = 'https://github.com/firebase/php-jwt';

        if (!class_exists('\Firebase\JWT\JWT')) { ?>
            <div class="notice notice-error">
                <p>
                    <strong>Findkit (Valu Search)</strong> was unable to enable
                    JWT authentication because <a href='<?php echo esc_url(
                        $jwt_url
                    ); ?>'>PHP-JWT</a> is not available.
                </p>
            </div>
            <?php }

        if (!get_api_secret()) { ?>
            <div class="notice notice-error">
                <p>
                    <strong>Findkit (Valu Search)</strong> was unable to enable
                    JWT authentication because the API secret was not defined.
                </p>
            </div>
            <?php }

        return trim(ob_get_clean());
    }

    function __action_admin_notices()
    {
        if (is_super_admin() && $this->init_error_notice) {
            echo $this->init_error_notice;
        }
    }

    function __action_wp_head()
    {
        $fetch_url = wp_json_encode(rest_url() . 'findkit/v1/search-jwt');

        $headers = [];

        if (is_user_logged_in()) {
            $headers['x-wp-nonce'] = wp_create_nonce('wp_rest');
        }

        $fetch_init = wp_json_encode([
            'method' => 'post',
            'headers' => $headers,
        ]);

        // Inline intial token so first request can be made immediately without
        // fetching the token over the rest api
        $initial_token = wp_json_encode($this->generate_jwt_token());

        // @valu/react-valu-search uses FINDKIT_GET_JWT_TOKEN global to get the
        // JWT token when it is about to send a search request. It will call this
        // again when the token has expired.
        $script = "
        (function() {
            let initialToken = $initial_token;
            Object.assign(window, {
                async FINDKIT_GET_JWT_TOKEN() {
                    if (initialToken) {
                        const ret = initialToken;
                        initialToken = null;
                        return ret;
                    }
                    return fetch($fetch_url, $fetch_init).then(r => r.json());
                }
            });
        })();
        ";

        echo "<script id='findkit-jwt-auth'>$script</script>";
    }

    function __action_rest_api_init()
    {
        register_rest_route('findkit/v1', 'search-jwt', [
            'methods' => 'POST',
            'permission_callback' => '__return_true',
            'callback' => function (\WP_REST_Request $request) {
                return $this->generate_jwt_token();
            },
        ]);
    }

    function generate_jwt_token()
    {
        if (
            !apply_filters('valu_search_allow_search_jwt', is_user_logged_in())
        ) {
            return null;
        }

        $token = \Firebase\JWT\JWT::encode(
            [
                'iat' => time(),
                'exp' => time() + 120, // 2min in future
                'userId' => get_current_user_id(),
                'scope' => 'search',
                'hostname' => parse_url(get_home_url())['host'],
            ],
            get_api_secret(),
            'HS256'
        );

        return ['jwt' => $token];
    }
}
