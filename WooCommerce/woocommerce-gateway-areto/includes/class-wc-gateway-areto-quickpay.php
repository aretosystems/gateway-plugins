<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class WC_Gateway_Areto_Quickpay extends WC_Payment_Gateway {
	/**
	 * Init
	 */
	public function __construct() {
		$this->id           = 'areto_quickpay';
		$this->has_fields   = FALSE;
		$this->method_title = __( 'AretoPay QuickPay', 'woocommerce-gateway-areto' );
		$this->icon         = apply_filters( 'woocommerce_areto_credit_card_icon', plugins_url( '/assets/images/aretosystems_logo.png', dirname( __FILE__ ) ) );
		$this->supports     = array(
			'default_credit_card_form',
			'products',
			'refunds',
		);

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define user set variables
		$this->enabled     = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'no';
		$this->title       = isset( $this->settings['title'] ) ? $this->settings['title'] : '';
		$this->description = isset( $this->settings['description'] ) ? $this->settings['description'] : '';
		$this->api_id      = isset( $this->settings['api_id'] ) ? $this->settings['api_id'] : '';
		$this->api_session = isset( $this->settings['api_session'] ) ? $this->settings['api_session'] : '';

		// Actions
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
			$this,
			'process_admin_options'
		) );
		add_action( 'woocommerce_thankyou_' . $this->id, array(
			$this,
			'thankyou_page'
		) );

		// Payment listener/API hook
		//add_action( 'woocommerce_api_wc_gateway_' . $this->id, array( $this, 'transaction_callback' ) );

		// Payment confirmation
		add_action( 'the_post', array( &$this, 'payment_confirm' ) );


		// Test Credit Cards:
		// 4200000000000000
		// 5100000000000511
		// 4273726216266199
		// 5523931234568897
	}

	/**
	 * get_icon function.
	 *
	 * @return string
	 */
	public function get_icon() {
		//$icon = '<img src="' . plugins_url( '/assets/images/cards.png', dirname( __FILE__ ) ) . '" />';
		$icon = '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/visa.png' ) . '" alt="Visa" />';
		$icon .= '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/mastercard.png' ) . '" alt="Mastercard" />';
		$icon .= '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/amex.png' ) . '" alt="Amex" />';

		if ( 'USD' === get_woocommerce_currency() ) {
			$icon .= '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/discover.png' ) . '" alt="Discover" />';
			$icon .= '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/jcb.png' ) . '" alt="JCB" />';
			$icon .= '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/diners.png' ) . '" alt="Diners" />';
		}

		$icon .= '<a rel="external" href="http://www.aretosystems.com/"><img width="68" src="' . WC_HTTPS::force_https_url( $this->icon ) . '" alt="Online payment services - Areto Systems"  /></a>';

		return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
	}

	/**
	 * Initialise Settings Form Fields
	 * @return string|void
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'     => array(
				'title'   => __( 'Enable/Disable', 'woocommerce-gateway-areto' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable plugin', 'woocommerce-gateway-areto' ),
				'default' => 'no'
			),
			'title'       => array(
				'title'       => __( 'Title', 'woocommerce-gateway-areto' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-areto' ),
				'default'     => __( 'Credit/Debit Card (AretoPay)', 'woocommerce-gateway-areto' )
			),
			'description' => array(
				'title'       => __( 'Description', 'woocommerce-gateway-areto' ),
				'type'        => 'text',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-areto' ),
				'default'     => __( 'Credit/Debit Card (AretoPay)', 'woocommerce-gateway-areto' ),
			),
			'api_id'      => array(
				'title'       => __( 'API ID', 'woocommerce-gateway-areto' ),
				'type'        => 'text',
				'description' => __( 'Your API Id from panel', 'woocommerce-gateway-areto' ),
				'default'     => ''
			),
			'api_session' => array(
				'title'       => __( 'API Session', 'woocommerce-gateway-areto' ),
				'type'        => 'text',
				'description' => __( 'Your API Session from panel', 'woocommerce-gateway-areto' ),
				'default'     => ''
			),
		);
	}

	/**
	 * Payment Fields
	 */
	public function payment_fields() {
		echo sprintf( __( 'You will be redirected to <a target="_blank" href="%s">Areto QuickPay</a> website when you place an order.', 'woocommerce-gateway-areto' ), 'http://www.aretosystems.com' );
	}

	/**
	 * Process Payment
	 *
	 * @param int $order_id
	 *
	 * @return array|void
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		// When Order amount is empty
		if ( $order->get_total() == 0 ) {
			$order->payment_complete();
			WC()->cart->empty_cart();

			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order )
			);
		}

		return array(
			'result'            => 'success',
			'redirect'          => '#!areto',
			'is_areto_quickpay' => TRUE,
			'Id'                => $this->api_id,
			'Session'           => $this->api_session,
			'ExternalOrderId'   => $order->id,
			'Amount'            => number_format( $order->order_total, 2, '.', '' ),
			'CurrencyCode'      => $order->get_order_currency(),
			'ReturnUrl'         => html_entity_decode( $this->get_return_url( $order ) )
		);
	}

	/**
	 * Payment confirm action
	 */
	public function payment_confirm() {
		$gateway = new WC_Gateway_Areto_Payment();
		$gateway->api_id = $this->api_id;
		$gateway->api_session = $this->api_session;
		$gateway->payment_confirm();
	}

	/**
	 * Process Refund
	 *
	 * If the gateway declares 'refunds' support, this will allow it to refund
	 * a passed in amount.
	 *
	 * @param  int    $order_id
	 * @param  float  $amount
	 * @param  string $reason
	 *
	 * @return  bool|wp_error True or false based on success, or a WP_Error object
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$gateway = new WC_Gateway_Areto_Payment();
		$gateway->api_id = $this->api_id;
		$gateway->api_session = $this->api_session;
		$gateway->process_refund($order_id, $amount, $reason);
	}


	/**
	 * Add message
	 *
	 * @param string $message
	 *
	 * @param string $notice_type
	 */
	public function add_message( $message = '', $notice_type = 'error' ) {
		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( $message, $notice_type );
		} else { // WC < 2.1
			global $woocommerce;
			if ( 'error' === $notice_type ) {
				$woocommerce->add_error( $message );
			} else {
				$woocommerce->add_message( $message );
			}

			$woocommerce->set_messages();
		}
	}
}
