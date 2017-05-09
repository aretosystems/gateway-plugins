<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

require_once dirname(__FILE__) . '/../vendor/autoload.php';

use Zend\Http\Client;

class WC_Gateway_Areto_Payment extends WC_Payment_Gateway {
	//
	/**
	 * Init
	 */
	public function __construct() {
		$this->id           = 'areto';
		$this->has_fields   = false;
		$this->method_title = __( 'AretoPay', 'woocommerce-gateway-areto' );
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
		$this->enabled            = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'no';
		$this->title              = isset( $this->settings['title'] ) ? $this->settings['title'] : '';
		$this->description        = isset( $this->settings['description'] ) ? $this->settings['description'] : '';
		$this->api_id             = isset( $this->settings['api_id'] ) ? $this->settings['api_id'] : '';
		$this->api_session        = isset( $this->settings['api_session'] ) ? $this->settings['api_session'] : '';
		$this->use_tokens         = isset( $this->settings['use_tokens'] ) ? $this->settings['use_tokens'] : 'no';
		$this->require_dob        = isset( $this->settings['require_dob'] ) ? $this->settings['require_dob'] : 'no';

		// Actions
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );

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
		$icon  = '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/visa.png' ) . '" alt="Visa" />';
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
			'enabled'            => array(
				'title'   => __( 'Enable/Disable', 'woocommerce-gateway-areto' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable plugin', 'woocommerce-gateway-areto' ),
				'default' => 'no'
			),
			'title'              => array(
				'title'       => __( 'Title', 'woocommerce-gateway-areto' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-areto' ),
				'default'     => __( 'Credit/Debit Card (AretoPay)', 'woocommerce-gateway-areto' )
			),
			'description'        => array(
				'title'       => __( 'Description', 'woocommerce-gateway-areto' ),
				'type'        => 'text',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-areto' ),
				'default'     => __( 'Credit/Debit Card (AretoPay)', 'woocommerce-gateway-areto' ),
			),
			'api_id'         => array(
				'title'       => __( 'API ID', 'woocommerce-gateway-areto' ),
				'type'        => 'text',
				'description' => __( 'Your API Id from panel', 'woocommerce-gateway-areto' ),
				'default'     => ''
			),
			'api_session'      => array(
				'title'       => __( 'API Session', 'woocommerce-gateway-areto' ),
				'type'        => 'text',
				'description' => __( 'Your API Session from panel', 'woocommerce-gateway-areto' ),
				'default'     => ''
			),
			//'use_tokens'           => array(
			//	'title'   => __( 'Use AretoTOKEN', 'woocommerce-gateway-areto' ),
			//	'type'    => 'checkbox',
			//	'label'   => __( 'Use AretoTOKEN', 'woocommerce-gateway-areto' ),
			//	'default' => 'no'
			//),
			'require_dob'           => array(
				'title'   => __( 'Require Date of birth', 'woocommerce-gateway-areto' ),
				'type'    => 'checkbox',
				'label'   => __( 'Require Date of birth', 'woocommerce-gateway-areto' ),
				'default' => 'no'
			),
		);
	}

	/**
	 * Credit Card Form
	 */
	public function payment_fields() {
		if (version_compare(WC()->version, '2.6', '>=')) {
			$cc_form = new WC_Payment_Gateway_CC;
			$cc_form->id       = $this->id;
			$cc_form->supports = $this->supports;
			$cc_form->form();
		} else {
			// Deprecated in WC 2.6
			$this->credit_card_form();
		}

		if ($this->require_dob === 'yes') {
			?>
			<div class="clear"></div>
			<fieldset>
				<label for="date_of_birth">
					<?php _e('Date of birth', 'woocommerce-gateway-areto'); ?>
					<span class="required">*</span>
				</label>
				<br />
				<input id="date_of_birth" class="input-text" type="text" autocomplete="off" placeholder="YYYY-MM-DD" name="date_of_birth" style="width: 100%;" />
			</fieldset>
			<?php
		}
	}

	/**
	 * Validate Frontend Fields
	 * @return bool|void
	 */
	public function validate_fields() {
		if ( $this->require_dob === 'yes' && empty( $_POST['date_of_birth'] ) ) {
			$this->add_message( __( 'Please enter date of birth.', 'woocommerce-gateway-areto' ), 'error' );
		} elseif ( ! empty( $_POST['date_of_birth'] ) ) {
			$value = $_POST['date_of_birth'];
			$date = strtotime( $value );
			if ( ! $date ) {
				$this->add_message( __( 'Invalid date of birth.', 'woocommerce-gateway-areto' ), 'error' );
			}
		}
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

		// Phone number is required
		if ( empty( $order->billing_phone ) ) {
			$this->add_message( __( 'Please enter your phone number.', 'woocommerce-gateway-areto' ), 'error' );
			return false;
		}

		// Validate phone number
		$matches = array();
		preg_match('/^00/\'', $order->billing_phone, $matches);
		if (!isset($matches[0])) {
			$this->add_message( __( 'Phone number should have 2 leading zeros - before international country code', 'woocommerce-gateway-areto' ), 'error' );
			return false;
		}

		$card_number    = isset( $_POST['areto-card-number'] ) ? wc_clean( $_POST['areto-card-number'] ) : '';
		$card_cvc       = isset( $_POST['areto-card-cvc'] ) ? wc_clean( $_POST['areto-card-cvc'] ) : '';
		$card_expiry    = isset( $_POST['areto-card-expiry'] ) ? wc_clean( $_POST['areto-card-expiry'] ) : '';
		$card_type      = isset( $_POST['areto-card-type'] ) ? wc_clean( $_POST['areto-card-type'] ) : '';

		// Format values
		$card_number    = str_replace( array( ' ', '-' ), '', $card_number );
		$card_expiry    = array_map( 'trim', explode( '/', $card_expiry ) );
		$card_exp_month = str_pad( $card_expiry[0], 2, '0', STR_PAD_LEFT );
		$card_exp_year  = $card_expiry[1];

		if ( strlen( $card_exp_year ) < 4 ) {
			$card_exp_year = $card_exp_year + 2000;
		}

		if (!empty($card_type)) {
			// Convert card type by WooCommerce
			$cards = array(
				'visaelectron' => 'VisaElectron',
				'maestro' => 'MAES',
				'visa' => 'VISA',
				'mastercard' => 'MC',
				'amex' => 'AMEX',
				'dinersclub' => 'DINER',
				'discover' => 'DISC',
				'unionpay' => 'CUP',
				'jcb' => 'JCB'
			);

			if (isset($cards[$card_type])) {
				$card_type = $cards[$card_type];
			}
		} else {
			// Try to detect card type
			$card_type = self::get_card_type($card_number);
		}

		// Return URL
		$return_url = html_entity_decode( $this->get_return_url( $order ) );

		// Short
		$client = new Client('http://tinyurl.com/api-create.php', array(
			'maxredirects' => 5,
			'timeout'      => 90
		));
		$client->setAdapter( 'Zend\Http\Client\Adapter\Curl' );
		$response = $client->setParameterGet( array( 'url' => $return_url ) )->send();
		if ( ! $response->isSuccess() ) {
			$this->add_message( __( 'TinyURL: Unable process request', 'woocommerce-gateway-areto' ), 'error' );
			return false;
		}
		$return_url = $response->getBody();

		// Do request
		$url = 'https://pay.aretosystems.com/api/sale/v1';
		$data = array(
			'Id' => $this->api_id,
			'Session' => $this->api_session,
			'OrderId' => $order->id,
			'Amount' => $order->order_total,
			'CurrencyCode' => $order->order_currency,
			'CCVC' => $card_cvc,
			'CCExpiryMonth'	 => $card_exp_month,
			'CCExpiryYear' => $card_exp_year,
			'CCName' => $order->billing_first_name,
			'CCSurname' => $order->billing_last_name,
			'CCNumber' => $card_number,
			'CCType' => $card_type,
			'CCAddress' => trim( $order->billing_address_1 . ' ' .  $order->billing_address_2 ),
			'ClientCity' => $order->billing_city,
			'ClientCountryCode' => $order->billing_country,
			'ClientZip' => $order->billing_postcode,
			'ClientState' => $order->billing_state,
			'ClientEmail' => $order->billing_email,
			'ClientExternalIdentifier' => is_user_logged_in() ? get_current_user_id() : 0,
			'ClientIP' => $_SERVER['REMOTE_ADDR'],
			'ClientForwardIP' => $_SERVER['HTTP_X_FORWARDED_FOR'],
			'ClientDOB' => isset( $_POST['date_of_birth'] ) ? $_POST['date_of_birth'] : '', //yyyy-mm-dd
			'ClientPhone' => preg_replace('/[^0-9]/', '', $order->billing_phone),
			'CCToken' => '',
			'CreateToken' => $this->use_tokens === 'yes' ? '1' : '0',
			'ReturnUrl' => $return_url,
		);

		//$fields = http_build_query($data, '', '&');
		$fields = '';
		foreach ($data as $key => $value) {
			$fields .= "{$key}={$value}&";
		}
		$fields = rtrim($fields, '&');


		$client = new Client($url, array(
			'maxredirects' => 5,
			'timeout'      => 90
		));
		$client->setAdapter( 'Zend\Http\Client\Adapter\Curl' );
		$response = $client->setMethod( 'POST' )->setRawBody( $fields )->setEncType( 'application/json' )->send();
		if ( ! $response->isSuccess() ) {
			$this->add_message( __( 'Unable process request', 'woocommerce-gateway-areto' ), 'error' );
			return false;
		}

		$result = @json_decode( $response->getBody(), true );
		if ( ! is_array($result) || ! isset($result['Result']) ) {
			$this->add_message( __( 'Unable process request: invalid response', 'woocommerce-gateway-areto' ), 'error' );
			return false;
		}

		// Set transaction status
		update_post_meta( $order->id, '_transaction_status', $result['Result']['Code']);

		switch ((int) $result['Result']['Code']) {
			case 0:
				// Payment is failed
				$order->update_status( 'failed', sprintf( __( 'Payment failed: %s. Code: %s.', 'woocommerce-gateway-areto' ), $result['Result']['Description'], $result['Result']['Code'] ) );
				$this->add_message( $result['Result']['Description'], 'error' );
				return false;
			case 1:
				// Payment is success
				$order->add_order_note( sprintf( __( 'Payment success: %s', 'woocommerce-gateway-areto' ), $result['Result']['Description'] ) );
				$order->payment_complete( $result['Body']['InternalOrderID'] );
				WC()->cart->empty_cart();

				// @todo Save fields Tokenisation fields
				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order )
				);
			case 4:
				// Payment require 3D-Secure
				$order->add_order_note( sprintf( __( 'Payment require 3D-Secure: %s.', 'woocommerce-gateway-areto' ), $result['Result']['Description'] ) );

				// Save Transaction Id
				update_post_meta( $order->id, '_transaction_id', $result['Body']['InternalOrderID'] );

				// 3D-Secure with params
				if ( count($result['Redirect']) > 0 ) {
					$method = !empty( $result['Redirect']['Method'] ) ? $result['Redirect']['Method'] : 'POST';
					$params = $result['Redirect']['Parameters'];

					return array(
						'result'     => 'success',
						'redirect'   => '#!areto',
						'is_areto'   => true,
						'url'        => urldecode( $result['Redirect']['RedirectLink'] ),
						'method'     => $method,
						'params'     => $params
					);
				}

				// 3D-Secure without params
				return array(
					'result'   => 'success',
					'redirect' => urldecode( $result['Redirect']['RedirectLink'] )
				);
			default:
				$this->add_message( $result['Result']['Description'], 'error' );
				return false;
		}
	}

	/**
	 * Thank you page
	 *
	 * @param $order_id
	 */
	public function thankyou_page( $order_id ) {
		//
	}

	/**
	 * Payment confirm action
	 */
	public function payment_confirm() {
		if ( empty( $_GET['key'] ) ) {
			return;
		}

		// Validate Payment Method
		$order_id = wc_get_order_id_by_order_key( $_GET['key'] );
		$order = wc_get_order( $order_id );
		if ($order && $order->payment_method !== $this->id) {
			return;
		}

		$transaction_status = get_post_meta( $order->id, '_transaction_status', true );
		if ($transaction_status == 1) {
			return;
		}

		$transaction_id = $order->get_transaction_id();
		if ( ! empty( $transaction_id ) ) {
			try {
				// Wait for order status != 4
				set_time_limit(0);
				$times = 0;

				do {
					$times++;
					if ($times > 6) {
						throw new Exception('Status check timeout');
					}
					sleep(10);

					$url = 'https://pay.aretosystems.com/api/status/v1/';

					$data = array(
						'Id' => $this->api_id,
						'Session' => $this->api_session,
						'InternalOrderID' => $transaction_id
					);

					$client = new Client($url, array(
						'maxredirects' => 5,
						'timeout'      => 90
					));
					$client->setAdapter( 'Zend\Http\Client\Adapter\Curl' );
					$response = $client->setParameterGet( $data )->setEncType( 'application/json' )->send();
					if ( ! $response->isSuccess() ) {
						throw new Exception( __( 'Unable process request', 'woocommerce-gateway-areto' ) );
					}

					$result = @json_decode( $response->getBody(), true );
					if ( ! is_array($result) || ! isset($result['Result'] ) ) {
						throw new Exception( __( 'Unable process request: invalid response', 'woocommerce-gateway-areto' ) );
					}

					// Check request is failed
					if ((int)$result['Result']['Code'] !== 1) {
						throw new Exception(sprintf('Status Check is failed: %s. Code: %s.', $result['Result']['Description'], $result['Result']['Code']));
					}

					$order_status = (int)$result['Body']['OrderStatus'];
				} while ($order_status === 4);
			} catch (Exception $e) {
				// Payment is failed
				$order->update_status( 'failed', $e->getMessage() );
				return false;
			}

			// Set transaction status
			update_post_meta( $order->id, '_transaction_status', $result['Result']['Code']);

			switch ((int) $result['Body']['OrderStatus']) {
				case 0:
					// Payment has been declined
					$order->update_status( 'failed', sprintf( __( 'Payment has been declined', 'woocommerce-gateway-areto' ) ) );
					break;
				case 1:
					// Payment is success
					$order->add_order_note( sprintf( __( 'Payment success: %s.', 'woocommerce-gateway-areto' ), $result['Body']['OrderDescription'] ) );
					$order->payment_complete( $result['Body']['InternalOrderID'] );
					break;
				default:
					$order->update_status( 'failed', sprintf( __( 'Unknown order status', 'woocommerce-gateway-areto' ) ) );
					break;
			}
		}
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
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		// Full Refund
		if ( is_null( $amount ) ) {
			$amount = $order->order_total;
		}

		// Do request
		$url = 'https://pay.aretosystems.com/pay-refund.ashx';
		$data = array(
			'Id' => $this->api_id,
			'Session' => $this->api_session,
			'InternalOrderId' => $order->get_transaction_id(),
			'Reason' => $reason,
			'Amount' => $amount,
		);

		$ch = curl_init();
		curl_setopt_array($ch, array(
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $data
		));
		$response = curl_exec($ch);
		curl_close($ch);

		$result = self::get_request($response);

		if ((int) $result['c-code'] === 6) {
			$order->add_order_note( sprintf( __( 'Refunded: %s. Reason: %s', 'woocommerce-gateway-areto' ), wc_price( $amount ), $reason ) );
			return true;
		} else {
			return new WP_Error( 'woocommerce-gateway-areto', $result['c-message'] );
		}
	}

	/**
	 * Transaction Callback
	 * Use as "?wc-api=WC_Gateway_Areto_Payment"
	 */
	public function transaction_callback() {
		@ob_clean();
		exit( 'OK' );
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

	/**
	 * Get Card Type by Card Number
	 * @param $number
	 *
	 * @return string
	 */
	public static function get_card_type($number) {
		$patterns = array(
			'MC' => '/^(5[0-5]|2[2-7])/',
			'AMEX' => '/^3[47]/',
			'Bankcard' => '/^56(?:10|022[1-5])/',
			'CUP' => '/^62/',
			'DINER' => '/(\d{1,4})(\d{1,6})?(\d{1,4})?/',
			'DISC' => '/^6(?:011|22(?:1(?:2[6-9]|[3-9])|[2-8]|9(?:[01]|2[0-5]))|4[4-9]|5)/',
			'InstaPayment' => '/^63[7-9]/',
			'JCB' => '/^35(?:(?:2[89])|[3-8])/',
			'LASER' => '/^(?:6304|6706|6771|6709)/',
			'MAES' => '/^(?:5018|5020|5038|6304|6759|676[1-3]|0604)/',
			'SOLO' => '/^(?:6334|6767)/',
			'SWCH' => '/^(?:49(?:03|05|11|36)|564182|633110|6333|6759)/',
			'VisaElectron' => '/^(?:4026|417500|4508|4844|491(?:3|7))/',
			'VISA' => '/^4/'
		);

		foreach ($patterns as $cardType=>$pattern) {
			if (preg_match($pattern, $number)) {
				return $cardType;
			}
		}

		return 'Unknown';
	}

	/**
	 * Parse Request
	 * @param $xml_body
	 *
	 * @return array|bool
	 */
	public static function get_request($xml_body) {
		// Load XML
		libxml_use_internal_errors(true);
		$doc = new DOMDocument();
		$status = @$doc->loadXML($xml_body);
		if ($status === false) {
			return false;
		}

		// Get Error section
		$result = array();
		$items = $doc->getElementsByTagName('c-result')->item(0)->getElementsByTagName('c-error')->item(0)->getElementsByTagName('*');
		foreach ($items as $item) {
			$key = $item->nodeName;
			$value = $item->nodeValue;
			$result[$key] = $value;
		}

		// Get Container section
		$items = $doc->getElementsByTagName('c-result')->item(0)->getElementsByTagName('c-container')->item(0)->getElementsByTagName('*');
		foreach ($items as $item) {
			$key = $item->nodeName;
			$value = $item->nodeValue;
			$result[$key] = $value;
		}
		return $result;
	}
}