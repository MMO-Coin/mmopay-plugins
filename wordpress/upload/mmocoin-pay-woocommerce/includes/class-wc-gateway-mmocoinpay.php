<?php

defined( 'ABSPATH' ) || exit;

/**
 * MMOCoin Pay payment gateway for WooCommerce.
 *
 * Modelled on WC_Payment_Gateway the way WooCommerce's own developer
 * documentation and real crypto-gateway extensions (e.g. CoinGate for
 * WooCommerce) implement it: a hosted-checkout redirect via process_payment(),
 * settled by a webhook delivered through the WC-API callback pattern.
 */
class WC_Gateway_MMOCoinPay extends WC_Payment_Gateway {

	/** The wc-api query value this gateway's callback answers to -- forms
	 *  the webhook URL merchants set on pay.mmocoin.pro. Chosen explicitly
	 *  rather than derived from the class name, since WooCommerce dispatches
	 *  woocommerce_api_* hooks off the raw wc-api value, not the class. */
	const CALLBACK_ID = 'wc_gateway_mmocoinpay';

	/** Order meta key correlating a WooCommerce order back to the checkout
	 *  session's clientReferenceId, since order IDs alone are guessable and
	 *  must never be trusted as a lookup key for an unauthenticated callback. */
	const ORDER_KEY_META = '_mmocoinpay_order_key';

	public $api_key;
	public $webhook_secret;
	public $endpoint;
	public $accepted_tokens;

	public function __construct() {
		$this->id                 = 'mmocoinpay';
		$this->has_fields         = false;
		$this->method_title       = 'MMOCoin Pay';
		$this->method_description = 'Accept instant Solana crypto payments in USDC, USDT, and MMO via pay.mmocoin.pro.';

		$this->init_form_fields();
		$this->init_settings();

		$this->title           = $this->get_option( 'title', 'Pay with crypto (USDC, USDT, MMO)' );
		$this->description     = $this->get_option( 'description', 'You will be redirected to complete your payment securely.' );
		$this->api_key         = trim( $this->get_option( 'api_key' ) );
		$this->webhook_secret  = trim( $this->get_option( 'webhook_secret' ) );
		$this->endpoint        = rtrim( $this->get_option( 'endpoint', 'https://pay.mmocoin.pro' ), '/' );
		$this->accepted_tokens = $this->get_option( 'accepted_tokens', 'USDC,USDT,MMO' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_api_' . self::CALLBACK_ID, array( $this, 'handle_callback' ) );
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'         => array(
				'title'   => 'Enable/Disable',
				'type'    => 'checkbox',
				'label'   => 'Enable MMOCoin Pay',
				'default' => 'no',
			),
			'title'           => array(
				'title'       => 'Title',
				'type'        => 'text',
				'description' => 'Shown to the customer at checkout.',
				'default'     => 'Pay with crypto (USDC, USDT, MMO)',
			),
			'description'     => array(
				'title'   => 'Description',
				'type'    => 'textarea',
				'default' => 'You will be redirected to complete your payment securely.',
			),
			'api_key'         => array(
				'title'       => 'API Key',
				'type'        => 'password',
				'description' => 'From pay.mmocoin.pro -> Dashboard -> Developers.',
			),
			'webhook_secret'  => array(
				'title'       => 'Webhook Signing Secret (Required)',
				'type'        => 'password',
				'description' => 'Required. Must match the webhook secret set on pay.mmocoin.pro under Dashboard -> Developers -> Webhooks, with the webhook URL there pointed at: '
					. esc_html( $this->callback_url() )
					. '. Payments cannot be approved without it.',
			),
			'endpoint'        => array(
				'title'   => 'API Base URL',
				'type'    => 'text',
				'default' => 'https://pay.mmocoin.pro',
			),
			'accepted_tokens' => array(
				'title'       => 'Accepted Crypto Tokens',
				'type'        => 'text',
				'default'     => 'USDC,USDT,MMO',
				'description' => 'Comma-separated (USDC, USDT, MMO). Ensure your payout wallet holds at least ~0.01 SOL for rent.',
			),
		);
	}

	/** The URL to hand pay.mmocoin.pro as this store's webhook URL. */
	public function callback_url() {
		return home_url( '/?wc-api=' . self::CALLBACK_ID );
	}

	/**
	 * Build the hosted checkout session and send the customer there.
	 * WooCommerce order totals are always in the store's real currency, so
	 * this is the one integration in the whole MMO Pay plugin family that
	 * never has to think about the USD-pricing distinction -- it always
	 * applies.
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( empty( $this->api_key ) || empty( $this->webhook_secret ) ) {
			wc_add_notice( 'MMOCoin Pay is not fully configured. Please contact the store owner.', 'error' );
			return array( 'result' => 'fail' );
		}

		$tokens_raw       = ! empty( $this->accepted_tokens ) ? $this->accepted_tokens : 'USDC,USDT,MMO';
		$tokens           = array_values( array_filter( array_map( 'trim', explode( ',', strtoupper( $tokens_raw ) ) ) ) );
		$valid_tokens     = array( 'SOL', 'USDC', 'USDT', 'MMO' );
		$filtered_tokens  = array_values( array_intersect( $tokens, $valid_tokens ) );

		// Correlate this order to the checkout session with our own opaque
		// key -- never the raw order id, which is sequential and guessable,
		// and must not be usable to forge a callback for someone else's order.
		$order_key = $order->get_order_key();
		$order->update_meta_data( self::ORDER_KEY_META, $order_key );
		$order->save();

		$session_payload = array(
			'title'             => substr( get_bloginfo( 'name' ) . ' Order #' . $order->get_order_number(), 0, 80 ),
			'description'       => substr( 'Order #' . $order->get_order_number(), 0, 500 ),
			'type'              => 'FIXED',
			'amount'            => (float) $order->get_total(),
			'pricing'           => 'USD',
			'redirectUrl'       => $this->get_return_url( $order ),
			'clientReferenceId' => (string) $order_key,
			'expiresInMinutes'  => 60,
			'metadata'          => array(
				'orderId'  => $order->get_id(),
				'orderKey' => $order_key,
			),
		);
		if ( ! empty( $filtered_tokens ) ) {
			$session_payload['tokens'] = $filtered_tokens;
		}

		$response = wp_remote_post(
			$this->endpoint . '/api/v1/checkout-sessions',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
					'User-Agent'    => 'WooCommerce MMOCoinPay/1.0',
				),
				'body'    => wp_json_encode( $session_payload ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			wc_add_notice( 'Could not start the checkout session: ' . $response->get_error_message(), 'error' );
			return array( 'result' => 'fail' );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 201 === (int) $code && ! empty( $body['session']['url'] ) ) {
			$order->update_status( 'pending', 'Awaiting MMOCoin Pay checkout.' );

			return array(
				'result'   => 'success',
				'redirect' => $body['session']['url'],
			);
		}

		$err = ! empty( $body['error'] ) ? $body['error'] : 'Could not start the checkout session.';
		wc_add_notice( $err, 'error' );
		return array( 'result' => 'fail' );
	}

	protected function get_request_headers() {
		$headers = array();
		if ( function_exists( 'getallheaders' ) ) {
			$all = getallheaders();
			if ( is_array( $all ) ) {
				foreach ( $all as $key => $val ) {
					$headers[ strtolower( $key ) ] = $val;
				}
			}
		}
		foreach ( $_SERVER as $key => $value ) {
			if ( substr( $key, 0, 5 ) === 'HTTP_' ) {
				$header_key = strtolower( str_replace( '_', '-', substr( $key, 5 ) ) );
				if ( ! isset( $headers[ $header_key ] ) ) {
					$headers[ $header_key ] = $value;
				}
			}
		}
		return $headers;
	}

	/**
	 * Webhook receiver. Same rule as every other MMO Pay plugin: no valid
	 * HMAC-SHA256 signature over the exact payload, no order gets marked
	 * paid. There is no fallback path that trusts an unsigned request.
	 */
	public function handle_callback() {
		if ( empty( $this->webhook_secret ) ) {
			status_header( 400 );
			exit;
		}

		$raw_body  = file_get_contents( 'php://input' );
		$headers   = $this->get_request_headers();
		$signature = isset( $headers['x-mmopay-signature'] ) ? trim( $headers['x-mmopay-signature'] ) : '';
		$timestamp = isset( $headers['x-mmopay-timestamp'] ) ? trim( $headers['x-mmopay-timestamp'] ) : '';

		if ( empty( $signature ) || empty( $timestamp ) ) {
			status_header( 400 );
			exit;
		}

		$expected_mac       = hash_hmac( 'sha256', "{$timestamp}.{$raw_body}", $this->webhook_secret );
		$expected_signature = "sha256={$expected_mac}";
		if ( ! hash_equals( $expected_signature, $signature ) ) {
			status_header( 400 );
			exit;
		}

		$payload = json_decode( $raw_body, true );
		if ( ! is_array( $payload ) ) {
			status_header( 400 );
			exit;
		}

		$event      = isset( $payload['event'] ) ? $payload['event'] : '';
		$order_key  = isset( $payload['clientReferenceId'] ) ? (string) $payload['clientReferenceId'] : '';
		$payment_id = isset( $payload['paymentId'] ) ? (string) $payload['paymentId'] : '';

		if ( empty( $order_key ) ) {
			status_header( 400 );
			exit;
		}

		// wc_get_orders(), not a direct meta-table query -- this has to keep
		// working whether the store is on legacy post-meta storage or HPOS
		// (WooCommerce's custom order tables), and only wc_get_orders()
		// abstracts over both.
		$orders = wc_get_orders(
			array(
				'limit'      => 1,
				'meta_key'   => self::ORDER_KEY_META,
				'meta_value' => $order_key,
			)
		);
		$order = ! empty( $orders ) ? $orders[0] : null;

		if ( ! $order ) {
			status_header( 404 );
			exit;
		}

		// Replay protection: this exact payment has already been recorded
		// against this order.
		if ( $payment_id && $order->get_transaction_id() === $payment_id ) {
			status_header( 200 );
			exit;
		}

		if (
			'payment.settled' === $event ||
			'subscription.created' === $event ||
			'subscription.charge_settled' === $event
		) {
			if ( $payment_id ) {
				$order->set_transaction_id( $payment_id );
				$order->save();
			}
			// Idempotent: WooCommerce core no-ops payment_complete() on an
			// order that has already moved past a "needs payment" status.
			$order->payment_complete( $payment_id );
			$order->add_order_note( 'MMOCoin Pay: payment confirmed on-chain.' );
		}
		// Anything else (including cancellations) is deliberately left
		// alone rather than guessed at, the same choice every other MMO Pay
		// plugin makes -- see each platform's own README for why.

		status_header( 200 );
		exit;
	}
}
