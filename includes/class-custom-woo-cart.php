<?php
// Handles cart logic for custom REST API
if (!defined('ABSPATH'))
    exit;

class Custom_Woo_Cart
{
    // Methods for fetching, adding, deleting, updating cart items

    /**
     * Fetch cart contents for the current session/user.
     * @return array
     */
    public function get_cart()
    {
        return array();
    }

    /**
     * Add a product to the cart.
     * @param int $product_id
     * @param int $quantity
     * @param array $variation
     * @return array
     */
    public function add_to_cart($product_id, $quantity = 1, $variation = array())
    {
        if (!function_exists('WC'))
            return array('error' => 'WooCommerce not loaded');
        if (!WC()->cart)
            return array('error' => 'Cart not initialized');
        $cart_item_key = WC()->cart->add_to_cart($product_id, $quantity, 0, $variation);
        if (!$cart_item_key) {
            return array('error' => 'Could not add to cart');
        }
        return $this->get_cart();
    }

    /**
     * Update quantity for a cart item.
     * @param string $cart_item_key
     * @param int $quantity
     * @return array
     */
    public function update_cart_item($cart_item_key, $quantity)
    {
        if (!function_exists('WC'))
            return array('error' => 'WooCommerce not loaded');
        if (!WC()->cart)
            return array('error' => 'Cart not initialized');
        $updated = WC()->cart->set_quantity($cart_item_key, $quantity);
        if (!$updated) {
            return array('error' => 'Could not update cart item');
        }
        return $this->get_cart();
    }

    /**
     * Remove a product from the cart.
     * @param string $cart_item_key
     * @return array
     */
    public function remove_from_cart($cart_item_key)
    {
        if (!function_exists('WC'))
            return array('error' => 'WooCommerce not loaded');
        if (!WC()->cart)
            return array('error' => 'Cart not initialized');
        $removed = WC()->cart->remove_cart_item($cart_item_key);
        if (!$removed) {
            return array('error' => 'Could not remove cart item');
        }
        return $this->get_cart();
    }

    /**
     * Load cart data from WooCommerce session table by cart_id (session_key) and inject into WC()->cart.
     * @param string $cart_id
     * @return array|null
     */
    public function load_cart_by_id($cart_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'woocommerce_sessions';
        $session_value = $wpdb->get_var($wpdb->prepare(
            "SELECT session_value FROM $table WHERE session_key = %s",
            $cart_id
        ));
        if (!$session_value)
            return null;
        $session_data = function_exists('maybe_unserialize') ? maybe_unserialize($session_value) : unserialize($session_value);

        foreach ($session_data as $key => $value) {
            if (is_string($value) && strpos($value, 'a:') === 0) {
                $session_data[$key] = function_exists('maybe_unserialize') ? maybe_unserialize($value) : unserialize($value);
            }
        }

        return $session_data;
    }

    /**
     * Save the current cart to WooCommerce session table by cart_id (session_key).
     * @param string $cart_id
     * @return void
     */
    public function save_cart_by_id($cart_id)
    {
        global $wpdb;
        if (!function_exists('WC') || !WC()->cart)
            return;
        $table = $wpdb->prefix . 'woocommerce_sessions';
        $session_value = $wpdb->get_var($wpdb->prepare(
            "SELECT session_value FROM $table WHERE session_key = %s",
            $cart_id
        ));
        $session_data = $session_value ? (function_exists('maybe_unserialize') ? maybe_unserialize($session_value) : unserialize($session_value)) : array();
        $session_data['cart'] = WC()->cart->get_cart_contents();
        $session_data['applied_coupons'] = WC()->cart->get_applied_coupons();
        $new_value = function_exists('maybe_serialize') ? maybe_serialize($session_data) : serialize($session_data);
        $wpdb->update(
            $table,
            array('session_value' => $new_value),
            array('session_key' => $cart_id)
        );
    }

    /**
     * Fetch cart contents for a given cart_id (stateless REST API).
     * @param string $cart_id
     * @return array
     */
    public function get_cart_by_id($cart_id)
    {
        $cart_data = $this->load_cart_by_id($cart_id);
        if ($cart_data) {
            // Add product details to the response
            $cart_data['products'] = $this->get_products_details_from_cart_data($cart_data);
            $cart_data['cart_id'] = $cart_id; // Include cart ID in the response
            $shopify_cart = $this->get_shopify_formatted_cart($cart_data);
            return $shopify_cart;
        }
        return array('error' => 'Cart not found');
    }

    /**
     * Get product details (name, content, meta) for all products in a cart array.
     * @param array $cart_data
     * @return array
     */
    public function get_products_details_from_cart_data($cart_data)
    {
        global $wpdb;
        $products = array();
        if (!isset($cart_data['cart']) || !is_array($cart_data['cart'])) {
            return $products;
        }
        $product_ids = array();
        foreach ($cart_data['cart'] as $item) {
            if (isset($item['product_id'])) {
                $product_ids[] = (int) $item['product_id'];
            }
        }
        if (empty($product_ids)) {
            return $products;
        }
        $placeholders = implode(',', array_fill(0, count($product_ids), '%d'));
        $query = $wpdb->prepare(
            "SELECT ID, post_title, post_content FROM {$wpdb->posts} WHERE ID IN ($placeholders) AND post_type = 'product'",
            $product_ids
        );
        $results = $wpdb->get_results($query, 'ARRAY_A');
        foreach ($results as $row) {
            $product_id = (int) $row['ID'];
            $meta = array();
            $meta_rows = $wpdb->get_results($wpdb->prepare(
                "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d",
                $product_id
            ), 'ARRAY_A');
            foreach ($meta_rows as $meta_row) {
                $meta[$meta_row['meta_key']] = function_exists('maybe_unserialize') ? maybe_unserialize($meta_row['meta_value']) : @unserialize($meta_row['meta_value']);
            }
            $products[] = array(
                'ID' => $product_id,
                'name' => $row['post_title'],
                'content' => $row['post_content'],
                'meta' => $meta,
            );
        }
        return $products;
    }

    public function get_shopify_formatted_cart($cart_data)
    {
        $currency = get_woocommerce_currency();
        $cart_id = $cart_data['cart_id'] ?? uniqid('mock_cart_');
        $cart_lines = [];

        foreach ($cart_data['cart'] as $key => $item) {
            $product = null;
            foreach ($cart_data['products'] as $p) {
                if ($p['ID'] == $item['product_id']) {
                    $product = $p;
                    break;
                }
            }

            // $product = collect($cart_data['products'])->firstWhere('ID', $item['product_id']);
            $product_meta = $product['meta'] ?? [];

            $regular_price = floatval($product_meta['_regular_price'] ?? 0);
            $sale_price = floatval($product_meta['_price'] ?? 0);
            $thumbnail_id = $product_meta['_thumbnail_id'] ?? null;

            $discount = floatval($item['line_subtotal'] - $item['line_total']);
            $discount_allocations = $discount > 0 ? [
                [
                    'discountedAmount' => [
                        'amount' => number_format($discount, 2, '.', ''),
                        'currencyCode' => $currency
                    ]
                ]
            ] : [];

            $cart_lines[] = [
                'node' => [
                    'id' => 'gid://shopify/CartLine/' . $key . '?cart=' . $cart_id,
                    'quantity' => $item['quantity'],
                    'cost' => [
                        'amountPerQuantity' => [
                            'amount' => number_format($sale_price, 2, '.', ''),
                            'currencyCode' => $currency
                        ],
                        'compareAtAmountPerQuantity' => $regular_price > $sale_price ? [
                            'amount' => number_format($regular_price, 2, '.', ''),
                            'currencyCode' => $currency
                        ] : null,
                        'subtotalAmount' => [
                            'amount' => number_format($item['line_subtotal'], 2, '.', ''),
                            'currencyCode' => $currency
                        ],
                        'totalAmount' => [
                            'amount' => number_format($item['line_total'], 2, '.', ''),
                            'currencyCode' => $currency
                        ],
                    ],
                    'discountAllocations' => $discount_allocations,
                    'merchandise' => [
                        'id' => 'gid://shopify/ProductVariant/' . $item['product_id'],
                        'title' => $product['name'] ?? '',
                        'price' => [
                            'amount' => number_format($sale_price, 2, '.', ''),
                            'currencyCode' => $currency
                        ],
                        'compareAtPrice' => $regular_price > $sale_price ? [
                            'amount' => number_format($regular_price, 2, '.', ''),
                            'currencyCode' => $currency
                        ] : null,
                        'image' => [
                            'id' => 'gid://shopify/ProductImage/' . $thumbnail_id,
                            'url' => wp_get_attachment_url($thumbnail_id)
                        ],
                        'product' => [
                            'id' => 'gid://shopify/Product/' . $item['product_id'],
                            'title' => $product['name'] ?? '',
                            'featuredImage' => [
                                'id' => 'gid://shopify/ProductImage/' . $thumbnail_id,
                                'url' => wp_get_attachment_url($thumbnail_id)
                            ],
                            'description' => wp_strip_all_tags($product['content'] ?? ''),
                            'handle' => sanitize_title($product['name'] ?? '')
                        ],
                        'sku' => '', // Optional if you want to mock SKU
                        'requiresShipping' => true, // Mocked, adjust if needed
                        'selectedOptions' => [], // Optional
                    ],
                    'sellingPlanAllocation' => null
                ]
            ];
        }

        $totals = $cart_data['cart_totals'];
        $customer = $cart_data['customer'];

        return [
            'cart' => [
                'id' => "gid://shopify/Cart/{$cart_id}",
                'discountCodes' => $cart_data['applied_coupons'] ?? [],
                'attributes' => [],
                'buyerIdentity' => [
                    'countryCode' => $customer['country'] ?? 'IN',
                    'email' => $customer['email'] ?? null,
                    'phone' => $customer['phone'] ?? null
                ],
                'checkoutUrl' => wc_get_checkout_url(),
                'createdAt' => gmdate('c', time() - 600),
                'updatedAt' => gmdate('c'),
                'discountAllocations' => [],
                'cost' => [
                    'subtotalAmount' => [
                        'amount' => number_format($totals['subtotal'] ?? 0, 2, '.', ''),
                        'currencyCode' => $currency
                    ],
                    'totalAmount' => [
                        'amount' => number_format($totals['total'] ?? 0, 2, '.', ''),
                        'currencyCode' => $currency
                    ],
                    'totalTaxAmount' => null,
                    'totalDutyAmount' => null
                ],
                'note' => '',
                'lines' => [
                    'edges' => $cart_lines
                ]
            ]
        ];
    }


}
