<?php

use Automattic\WooCommerce\StoreApi\Utilities\CartController;

// Handles custom REST API endpoints registration for cart and order actions
if (!defined('ABSPATH'))
    exit;

// Ensure WordPress REST API functions and classes are available
if (!function_exists('register_rest_route')) {
    require_once ABSPATH . 'wp-includes/rest-api.php';
}

use WP_REST_Response;

class Custom_Woo_REST_Controller
{
    public function register_routes()
    {
        // Register endpoints only if function exists (WordPress context)
        if (function_exists('register_rest_route')) {
            // Fetch Cart
            //example: /wp-json/custom-woo/v1/cart/12345
            register_rest_route('custom-woo/v1', '/cart/(?P<cart_id>[a-zA-Z0-9_-]+)', [
                'methods' => 'GET',
                'callback' => [$this, 'get_cart'],
                'permission_callback' => [$this, 'permissions_check'],
            ]);
            // update Cart Item
            register_rest_route('custom-woo/v1', '/cart/update', [
                'methods' => 'POST',
                'callback' => [$this, 'update_cart_item'],
                'permission_callback' => [$this, 'permissions_check'],
            ]);
            //test logging
            //example: /wp-json/custom-woo/v1/log
            register_rest_route('custom-woo/v1', '/log', [
                'methods' => 'POST',
                'callback' => [$this, 'log_test'],
            ]);
        }
    }

    /**
     * Check permissions for the API request. checks if the consumer key and secret are valid, woocommerce API keys.
     *
     * @param WP_REST_Request $request The request object.
     * @return bool True if permissions are valid, false otherwise.
     */
    public function permissions_check($request)
    {
        $consumer_key = '';
        $consumer_secret = '';

        if (!empty($_GET['consumer_key']) && !empty($_GET['consumer_secret'])) {
            $consumer_key = function_exists('sanitize_text_field') ? sanitize_text_field($_GET['consumer_key']) : $_GET['consumer_key'];
            $consumer_secret = function_exists('sanitize_text_field') ? sanitize_text_field($_GET['consumer_secret']) : $_GET['consumer_secret'];
        }
        if (!$consumer_key && isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
            $consumer_key = $_SERVER['PHP_AUTH_USER'];
            $consumer_secret = $_SERVER['PHP_AUTH_PW'];
        }
        if (!$consumer_key || !$consumer_secret) {
            return false;
        }
        global $wpdb;
        $hashed_key = function_exists('wc_api_hash') ? wc_api_hash($consumer_key) : hash('sha256', $consumer_key);
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT key_id, user_id, permissions, consumer_key, consumer_secret FROM {$wpdb->prefix}woocommerce_api_keys WHERE consumer_key = %s",
            $hashed_key
        ));
        if (!$user) {
            return false;
        }
        if (!hash_equals($user->consumer_secret, $consumer_secret)) {
            return false;
        }
        $method = strtoupper($request->get_method());
        if (in_array($method, ['GET', 'HEAD'])) {
            if ($user->permissions !== 'read' && $user->permissions !== 'read_write') {
                return false;
            }
        } else {
            if ($user->permissions !== 'write' && $user->permissions !== 'read_write') {
                return false;
            }
        }
        if (function_exists('wp_set_current_user')) {
            wp_set_current_user($user->user_id);
        }
        return true;
    }

    public function get_cart($request)
    {
        if (!class_exists('Custom_Woo_Cart')) {
            require_once __DIR__ . '/class-custom-woo-cart.php';
        }
        $cart = new \Custom_Woo_Cart();
        $cart_id = $request->get_param('cart_id');
        $result = $cart->get_cart_by_id($cart_id);
        if (class_exists('WP_REST_Response')) {
            return new WP_REST_Response($result, 200);
        }
        return $result;
    }

    public function log_test($request)
    {
        $woo = new WooCommerce();
        $woo->session = new WC_Session_Handler();
        $woo->cart = new WC_Cart();
        $woo->init();

        if ($woo->cart === null) {
            wp_send_json_error(['message' => 'Cart is not initialized'], 500);
            return;
        }
        $cart = $woo->cart->get_cart();
        wp_send_json(['message' => 'Log test successful', 'cart' => $cart]);
    }

    public function update_cart_item($request)
    {
        if (!class_exists('Custom_Woo_Cart')) {
            require_once __DIR__ . '/class-custom-woo-cart.php';
        }
        $woo = new WooCommerce();
        $woo->session = new WC_Session_Handler();
        $woo->cart = new WC_Cart();
        $woo->init();
        $item_key = $request->get_param('item_key');
        $quantity = $request->get_param('quantity');

        if (!$item_key || !is_numeric($quantity)) {
            return new WP_REST_Response(['message' => 'Invalid parameters'], 400);
        }
        $cartController = new CartController();

        $cartController->load_cart();
        $cart = wc()->cart;
        try {
            $cart = $cartController->get_cart_instance();
            $item_key_content = $cartController->get_cart_item($item_key);
            $cartController->set_cart_item_quantity($item_key, $quantity);
        } catch (Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 404);
        }
        if (class_exists('WP_REST_Response')) {
            return new WP_REST_Response(['message' => 'updated successfully'], 200);
        }
        return ['message' => 'updated successfully', 'item_key' => $item_key, 'quantity' => $quantity];
    }
}
