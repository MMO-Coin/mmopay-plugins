<?php
/**
 * Plugin Name: MMOCoin Pay for WooCommerce
 * Plugin URI: https://pay.mmocoin.pro
 * Description: Accept instant Solana crypto payments (USDC, USDT, MMO) at WooCommerce checkout via pay.mmocoin.pro, with mandatory HMAC-SHA256 webhook verification.
 * Version: 1.0.0
 * Author: MMOCoin
 * Author URI: https://pay.mmocoin.pro
 * License: MIT
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 9.0
 *
 * Not verified against a live WooCommerce install -- no install was
 * available while building it. Built against WooCommerce's own developer
 * documentation and a real, working open source crypto payment gateway
 * (CoinGate for WooCommerce) used as a structural reference. Test on a
 * staging store before relying on it for real orders. See INSTALL.txt.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'plugins_loaded', 'mmocoinpay_woocommerce_init', 0 );

/**
 * WooCommerce may not be active, or may not have loaded yet -- both are
 * true at the moment this file is first parsed, since plugin load order is
 * not guaranteed. Only register anything once WC_Payment_Gateway actually
 * exists, so a store without WooCommerce just sees this plugin do nothing
 * rather than fail to activate.
 */
function mmocoinpay_woocommerce_init() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	require_once __DIR__ . '/includes/class-wc-gateway-mmocoinpay.php';

	add_filter( 'woocommerce_payment_gateways', 'mmocoinpay_add_gateway' );
}

function mmocoinpay_add_gateway( $methods ) {
	$methods[] = 'WC_Gateway_MMOCoinPay';
	return $methods;
}
