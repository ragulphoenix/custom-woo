<?php

use Automattic\WooCommerce\StoreApi\Utilities\CartController;


class CartUpdateItem
{
    private $cartController;

    public function __construct()
    {
        $this->cartController = CartController::get_instance();
    }
}
