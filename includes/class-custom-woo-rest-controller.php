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
            register_rest_route('custom-woo/v1', '/cart/(?P<cart_id>[a-zA-Z0-9_-]+)', [
                'methods' => 'GET',
                'callback' => [$this, 'get_cart'],
                'permission_callback' => [$this, 'permissions_check'],
            ]);
            // Add Product to Cart
            register_rest_route('custom-woo/v1', '/cart/(?P<cart_id>[a-zA-Z0-9_-]+)/add', [
                'methods' => 'POST',
                'callback' => [$this, 'add_to_cart'],
                'permission_callback' => [$this, 'permissions_check'],
            ]);
            // Update Cart Item Quantity
            register_rest_route('custom-woo/v1', '/cart/(?P<cart_id>[a-zA-Z0-9_-]+)/update', [
                'methods' => 'POST',
                'callback' => [$this, 'update_cart_item'],
                'permission_callback' => [$this, 'permissions_check'],
            ]);
            // Remove Product from Cart
            register_rest_route('custom-woo/v1', '/cart/(?P<cart_id>[a-zA-Z0-9_-]+)/remove', [
                'methods' => 'POST',
                'callback' => [$this, 'remove_from_cart'],
                'permission_callback' => [$this, 'permissions_check'],
            ]);
            // Apply Coupon
            register_rest_route('custom-woo/v1', '/cart/(?P<cart_id>[a-zA-Z0-9_-]+)/coupon', [
                'methods' => 'POST',
                'callback' => [$this, 'apply_coupon'],
                'permission_callback' => [$this, 'permissions_check'],
            ]);
            // Remove Coupon
            register_rest_route('custom-woo/v1', '/cart/(?P<cart_id>[a-zA-Z0-9_-]+)/coupon', [
                'methods' => 'DELETE',
                'callback' => [$this, 'remove_coupon'],
                'permission_callback' => [$this, 'permissions_check'],
            ]);
            // Create Order
            register_rest_route('custom-woo/v1', '/order', [
                'methods' => 'POST',
                'callback' => [$this, 'create_order'],
                'permission_callback' => [$this, 'permissions_check'],
            ]);
            //test logging
            register_rest_route('custom-woo/v1', '/log', [
                'methods' => 'POST',
                'callback' => [$this, 'log_test'],
            ]);
        }
    }

    // Permission check for WooCommerce REST API authentication (API key/secret via Basic Auth)
    public function permissions_check($request)
    {
        // Try to authenticate using WooCommerce's API key/secret (Basic Auth or query params)
        $consumer_key = '';
        $consumer_secret = '';

        // 1. Check for consumer_key/consumer_secret in query params
        if (!empty($_GET['consumer_key']) && !empty($_GET['consumer_secret'])) {
            $consumer_key = function_exists('sanitize_text_field') ? sanitize_text_field($_GET['consumer_key']) : $_GET['consumer_key'];
            $consumer_secret = function_exists('sanitize_text_field') ? sanitize_text_field($_GET['consumer_secret']) : $_GET['consumer_secret'];
        }
        // 2. Check for HTTP Basic Auth
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
        // Optionally, check permissions (read/write)
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
        // Set the current user for this request
        if (function_exists('wp_set_current_user')) {
            wp_set_current_user($user->user_id);
        }
        return true;
    }

    // Endpoint callbacks (stubs)
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
    public function add_to_cart($request)
    {
        if (class_exists('WP_REST_Response')) {
            return new WP_REST_Response(['message' => 'Add to cart stub'], 200);
        }
        return ['message' => 'Add to cart stub'];
    }
    public function update_cart_item($request)
    {
        if (class_exists('WP_REST_Response')) {
            return new WP_REST_Response(['message' => 'Update cart item stub'], 200);
        }
        return ['message' => 'Update cart item stub'];
    }
    public function remove_from_cart($request)
    {
        if (class_exists('WP_REST_Response')) {
            return new WP_REST_Response(['message' => 'Remove from cart stub'], 200);
        }
        return ['message' => 'Remove from cart stub'];
    }
    public function apply_coupon($request)
    {
        if (class_exists('WP_REST_Response')) {
            return new WP_REST_Response(['message' => 'Apply coupon stub'], 200);
        }
        return ['message' => 'Apply coupon stub'];
    }
    public function remove_coupon($request)
    {
        if (class_exists('WP_REST_Response')) {
            return new WP_REST_Response(['message' => 'Remove coupon stub'], 200);
        }
        return ['message' => 'Remove coupon stub'];
    }
    public function create_order($request)
    {
        if (class_exists('WP_REST_Response')) {
            return new WP_REST_Response(['message' => 'Create order stub'], 200);
        }
        return ['message' => 'Create order stub'];
    }

    public function log_test($request)
    {
        // if (WC()->cart) {
        //     wp_send_json_error(['message' => 'ha ha ha'], 500);
        //     return;
        // }
        $woo = new WooCommerce();
        $woo->session = new WC_Session_Handler();
        $woo->cart = new WC_Cart();
        $woo->init();
        // $woo->cart->set_quantity('7f39f8317fbdb1988ef4c628eba02591', 5); // Example usage, replace with actual cart item key

        if ($woo->cart === null) {
            wp_send_json_error(['message' => 'Cart is not initialized'], 500);
            return;
        }
        $cart = $woo->cart->get_cart();
        wp_send_json(['message' => 'Log test successful', 'cart' => $cart]);
    }
}
