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

// Include required files
add_action('plugins_loaded', function () {
    require_once CUSTOM_WOO_PATH . 'includes/class-custom-woo-rest-controller.php';
    require_once CUSTOM_WOO_PATH . 'includes/class-custom-woo-cart.php';
    require_once CUSTOM_WOO_PATH . 'includes/class-custom-woo-coupons.php';
    require_once CUSTOM_WOO_PATH . 'includes/class-custom-woo-order.php';
});

// Register REST API routes
add_action('rest_api_init', function () {
    $controller = new Custom_Woo_REST_Controller();
    $controller->register_routes();
});


add_action('template_redirect', 'redirect_to_external_checkout_with_token');
function redirect_to_external_checkout_with_token()
{
    if (is_checkout() && !is_wc_endpoint_url()) {
        // $cart = WC()->cart->get_cart();
        // if (empty($cart)) {
        //     return;
        // }
        // $items = [];
        // foreach ($cart as $item) {
        //     $product = $item['data'];
        //     $items[] = [
        //         'product_id' => $product->get_id(),
        //         'name' => $product->get_name(),
        //         'quantity' => $item['quantity'],
        //         'price' => $product->get_price(),
        //         'image_url' => wp_get_attachment_url($product->get_image_id()),
        //         'sku' => $product->get_sku(),
        //         'description' => $product->get_short_description(),
        //     ];
        // }
        // $token = bin2hex(random_bytes(16));
        // set_transient('external_cart_' . $token, $items, 60 * 15); // Store for 15 mins

        //get session data from headers
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

        // Example: Access WooCommerce session
        $wooSessionKey = null;
        foreach ($parsedCookies as $key => $value) {
            if (strpos($key, 'wp_woocommerce_session_') === 0) {
                $wooSessionKey = $key;
                break;
            }
        }
        //$parsedCookies[$wooSessionKey] ::::> "t_8613bd46ed62f3bfadbc7b6a4b90a3||1748567377||1748563777||55fcc59ee556ffd8e4d528bc0560de2a"
        $session_key = isset($parsedCookies[$wooSessionKey]) ? explode('||', $parsedCookies[$wooSessionKey])[0] : '';
        $redirect_url = 'http://localhost:3001?store=' . $hostHeader . '&cart=' . $session_key;
        // echo "<script>window.open('$redirect_url', '_blank');</script>";
        wp_redirect($redirect_url);
        exit;
    }
}
// add_action('rest_api_init', function () {
//     register_rest_route('custom-api/v1', '/cart/(?P<token>[a-zA-Z0-9]+)', [
//         'methods' => 'GET',
//         'callback' => 'get_cart_by_token',
//         'permission_callback' => '__return_true',
//     ]);
// // });
// function get_cart_by_token($request)
// {
//     $token = sanitize_text_field($request['token']);
//     $cart_data = get_transient('external_cart_' . $token);
//     if (!$cart_data) {
//         return new WP_Error('invalid_token', 'Cart not found or expired', ['status' => 404]);
//     }
//     return [
//         'cart_items' => $cart_data,
//     ];
// }


add_action('shutdown', 'run_before_shutdown', 0); // Priority 0 ensures it's one of the first

function run_before_shutdown()
{
    WC()->cart = new WC_Cart();
}
