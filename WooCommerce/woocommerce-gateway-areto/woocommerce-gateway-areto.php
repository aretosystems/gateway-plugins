<?php
/*
Plugin Name: WooCommerce AretoPay Payment Gateway
Plugin URI: http://aretosystems.com/
Description: Provides a Credit Card Payment Gateway through AretoPay for WooCommerce.
Version: 1.3.0
Author: Areto Systems Limited
Author URI: #
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Requires at least: 3.1
*/

if ( ! defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly

class WC_AretoSystems {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Actions
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
		add_action( 'plugins_loaded', array( $this, 'init' ), 0 );
		add_action( 'wp_enqueue_scripts', array( $this, 'add_scripts' ) );
		add_filter( 'woocommerce_payment_gateways', array( $this, 'register_gateway' ) );
	}

	/**
	 * Add relevant links to plugins page
	 *
	 * @param  array $links
	 *
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$plugin_links = array(
			'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_gateway_areto_payment' ) . '">' . __( 'Settings', 'woocommerce-gateway-areto' ) . '</a>'
		);

		return array_merge( $plugin_links, $links );
	}

	/**
	 * Init localisations and files
	 */
	public function init() {
		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			return;
		}

		// Localization
		load_plugin_textdomain( 'woocommerce-gateway-areto', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		// Includes
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-areto-payment.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-areto-quickpay.php' );
	}

	/**
	 * Add Scripts
	 */
	public function add_scripts() {
		wp_enqueue_script( 'wc-checkout-areto', plugins_url( '/assets/js/checkout.js', __FILE__ ), array('jquery'), false, true );
		wp_enqueue_script( 'wc-checkout-areto-quickpay', plugins_url( '/assets/js/quickpay.js', __FILE__ ), array('jquery'), false, true );
	}

	/**
	 * Register the gateways for use
	 */
	public function register_gateway( $methods ) {
		$methods[] = 'WC_Gateway_Areto_Payment';
		$methods[] = 'WC_Gateway_Areto_Quickpay';

		return $methods;
	}
}

new WC_AretoSystems();