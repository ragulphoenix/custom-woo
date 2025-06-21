<?php
/*
Plugin Name: Custom Woo REST API
Description: Extends WooCommerce REST API for cart and order management.
Version: 1.0.0
Author: Your Name
*/

if (!defined('ABSPATH'))
    exit;

define('CUSTOM_WOO_PATH', plugin_dir_path(__FILE__));

define('CUSTOM_WOO_VERSION', '1.0.0');

add_action('plugins_loaded', function () {
    require_once CUSTOM_WOO_PATH . 'includes/class-custom-woo-rest-controller.php';
    require_once CUSTOM_WOO_PATH . 'includes/class-custom-woo-cart.php';
    require_once CUSTOM_WOO_PATH . 'includes/class-custom-woo-coupons.php';
    require_once CUSTOM_WOO_PATH . 'includes/class-custom-woo-order.php';
});

add_action('rest_api_init', function () {
    $controller = new Custom_Woo_REST_Controller();
    $controller->register_routes();
});


add_action('template_redirect', 'redirect_to_external_checkout_with_token');
function redirect_to_external_checkout_with_token()
{
    if (is_checkout() && !is_wc_endpoint_url()) {
        $cookieHeader = isset($_SERVER['HTTP_COOKIE']) ? ($_SERVER['HTTP_COOKIE']) : '';
        $hostHeader = isset($_SERVER['HTTP_HOST']) ? ($_SERVER['HTTP_HOST']) : '';

        $parsedCookies = [];
        foreach (explode(';', $cookieHeader) as $cookie) {
            $parts = explode('=', $cookie, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $value = urldecode(trim($parts[1]));
                $parsedCookies[$key] = $value;
            }
        }

        $wooSessionKey = null;
        foreach ($parsedCookies as $key => $value) {
            if (strpos($key, 'wp_woocommerce_session_') === 0) {
                $wooSessionKey = $key;
                break;
            }
        }
        $session_key = isset($parsedCookies[$wooSessionKey]) ? explode('||', $parsedCookies[$wooSessionKey])[0] : '';
        $redirect_url = 'http://localhost:3001?store=' . $hostHeader . '&cart=' . $session_key;
        wp_redirect($redirect_url);
        exit;
    }
}


add_action('shutdown', 'run_before_shutdown', 0);

function run_before_shutdown()
{
    WC()->cart = new WC_Cart();
}
