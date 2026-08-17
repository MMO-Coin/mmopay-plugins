<?php
/*======================================================================*\
|| #################################################################### ||
|| # MMOCoin Pay Payment Gateway for vBulletin 5.x (v1.0.0)            # ||
|| # Platform: https://pay.mmocoin.pro                                 # ||
|| # Official Crypto & Subscription Payment Gateway for Solana/USDC/MMO# ||
|| # Compatible: vBulletin 5.x Connect / PHP 7.4 / 8.x                 # ||
|| #                                                                    # ||
|| # This is the vBulletin 5 build of this gateway. vBulletin 5 kept   # ||
|| # the vBulletin 4 Paid Subscriptions system (vB_PaidSubscriptionMethod,  ||
|| # the includes/paymentapi/class_*.php pattern, Payment API Manager) ||
|| # largely unchanged rather than rewriting it for the node-based     # ||
|| # front end -- only the install path moved under core/. The class  # ||
|| # logic below is otherwise identical to the vBulletin 4 build.     # ||
|| # It has been reviewed against vBulletin's own published Connect   # ||
|| # API documentation, not against a live vBulletin 5 install --     # ||
|| # test on a staging board before relying on it for real members.   # ||
|| #################################################################### ||
\*======================================================================*/

if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}

/**
* Class providing payment verification, checkout session creation, and
* mandatory HMAC-SHA256 webhook verification for MMOCoin Pay (pay.mmocoin.pro)
*
* @package	vBulletin
* @subpackage	paymentapi
* @version	1.0.0
*/
class vB_PaidSubscriptionMethod_mmocoin_pay extends vB_PaidSubscriptionMethod
{
	/**
	* The variable indicating if this payment provider supports recurring transactions
	*
	* @var	bool
	*/
	var $supports_recurring = false;

	/**
	* Display feedback via payment_gateway.php when the callback is made
	*
	* @var	bool
	*/
	var $display_feedback = false;

	/**
	* Logs debug messages if debug mode is enabled in gateway settings
	*
	* @param string $message
	*/
	protected function logDebug($message)
	{
		if (!empty($this->settings['debug']))
		{
			$log_file = DIR . '/mmocoin_pay_debug.log';
			@file_put_contents($log_file, date('Y-m-d H:i:s') . " - [MMOCoin Pay] " . $message . "\n", FILE_APPEND);
		}
	}

	/**
	* Helper to retrieve HTTP request headers in a server-agnostic way (Apache, Nginx, LiteSpeed, FastCGI)
	*
	* @return array
	*/
	protected function getRequestHeaders()
	{
		$headers = array();
		if (function_exists('getallheaders'))
		{
			$all = getallheaders();
			if (is_array($all))
			{
				foreach ($all as $key => $val)
				{
					$headers[strtolower($key)] = $val;
				}
			}
		}

		foreach ($_SERVER as $key => $value)
		{
			if (substr($key, 0, 5) === 'HTTP_')
			{
				$header_key = strtolower(str_replace('_', '-', substr($key, 5)));
				if (!isset($headers[$header_key]))
				{
					$headers[$header_key] = $value;
				}
			}
		}

		return $headers;
	}

	/**
	* Perform verification of the webhook callback from pay.mmocoin.pro.
	*
	* Verification is mandatory, not optional. Every field this method acts on
	* comes from a JSON body whose HMAC-SHA256 signature has already been
	* checked against the shared secret -- nothing here is ever taken on the
	* word of an unauthenticated request. A callback with no valid signature is
	* refused outright.
	*
	* @return bool Whether the payment is valid and verified
	*/
	function verify_payment()
	{
		$this->logDebug("Received incoming payment verification request.");

		$raw_body = file_get_contents('php://input');
		if (empty($raw_body) && !empty($_POST['callback']))
		{
			$raw_body = $_POST['callback'];
		}

		$headers   = $this->getRequestHeaders();
		$signature = isset($headers['x-mmopay-signature']) ? trim($headers['x-mmopay-signature']) : '';
		$timestamp = isset($headers['x-mmopay-timestamp']) ? trim($headers['x-mmopay-timestamp']) : '';

		$webhook_secret = trim($this->settings['webhook_secret']);
		$apikey         = trim($this->settings['apikey']);

		if (empty($apikey))
		{
			$this->logDebug("Error: API Key is not configured in AdminCP.");
			$this->error_code = 'payment_processor_not_configured';
			$this->error = 'MMOCoin Pay API key is not configured.';
			return false;
		}

		// The signing secret is the ONLY thing that proves this request came
		// from pay.mmocoin.pro rather than from anyone who read a subscription
		// hash out of their own browser URL bar. It is required, full stop.
		// Set the same value here and in the pay.mmocoin.pro dashboard's
		// webhook settings, and point that account's webhook URL at
		// https://yourforum.com/payment_gateway.php?method=mmocoin_pay
		if (empty($webhook_secret))
		{
			$this->logDebug("Error: Webhook Signing Secret is not configured. Refusing unverifiable callback.");
			$this->error_code = 'webhook_secret_not_configured';
			$this->error = 'MMOCoin Pay webhook signing secret is not configured. Set it in Payment API Manager before accepting payments.';
			return false;
		}

		if (empty($signature) || empty($timestamp))
		{
			$this->logDebug("Error: Request carries no X-MMOPay-Signature/X-MMOPay-Timestamp headers.");
			$this->error_code = 'signature_missing';
			$this->error = 'Missing webhook signature.';
			return false;
		}

		$expected_mac       = hash_hmac('sha256', "{$timestamp}.{$raw_body}", $webhook_secret);
		$expected_signature = "sha256={$expected_mac}";
		$is_valid = function_exists('hash_equals') ? hash_equals($expected_signature, $signature) : ($expected_signature === $signature);

		if (!$is_valid)
		{
			$this->logDebug("Error: HMAC signature mismatch.");
			$this->error_code = 'signature_mismatch';
			$this->error = 'HMAC signature authentication failed.';
			return false;
		}
		$this->logDebug("Success: Webhook HMAC signature verified.");

		// Everything below reads only from the payload that signature just
		// covered. pay.mmocoin.pro's payment.settled webhook already carries
		// paymentId, sessionId, clientReferenceId, amount, token and
		// settledAt directly, so there is nothing left to fetch separately.
		$payload = !empty($raw_body) ? json_decode($raw_body, true) : array();
		if (!is_array($payload))
		{
			$this->logDebug("Error: Signed payload was not valid JSON.");
			$this->error_code = 'invalid_payload';
			$this->error = 'Malformed webhook payload.';
			return false;
		}

		$event               = isset($payload['event']) ? $payload['event'] : '';
		$payment_id          = isset($payload['paymentId']) ? $payload['paymentId'] : '';
		$client_reference_id = isset($payload['clientReferenceId']) ? $payload['clientReferenceId'] : '';
		$settled_at          = isset($payload['settledAt']) ? $payload['settledAt'] : '';
		$paid_amount         = isset($payload['amount']) ? $payload['amount'] : '';
		$token               = isset($payload['token']) ? $payload['token'] : 'USDC';

		if (empty($client_reference_id))
		{
			$this->logDebug("Error: Missing clientReferenceId in payment verification.");
			$this->error_code = 'missing_client_reference';
			$this->error = 'Missing subscription hash correlation id.';
			return false;
		}

		// Lookup vBulletin Payment Order via Hash
		$this->paymentinfo = $this->registry->db->query_first("
			SELECT paymentinfo.*, user.username
			FROM " . TABLE_PREFIX . "paymentinfo AS paymentinfo
			INNER JOIN " . TABLE_PREFIX . "user AS user USING (userid)
			WHERE hash = '" . $this->registry->db->escape_string($client_reference_id) . "'
		");

		if (empty($this->paymentinfo))
		{
			$this->logDebug("Error: No subscription found for hash: {$client_reference_id}");
			$this->error_code = 'order_not_found';
			$this->error = 'Subscription order not found.';
			return false;
		}

		// Prevent Duplicate / Replay Transactions
		if (!empty($payment_id))
		{
			$this->transactioninfo = $this->registry->db->query_first("
				SELECT *
				FROM " . TABLE_PREFIX . "paymenttransaction
				WHERE transactionid = '" . $this->registry->db->escape_string($payment_id) . "'
			");

			if (!empty($this->transactioninfo))
			{
				$this->logDebug("Notice: Duplicate transaction ignored: {$payment_id}");
				$this->error_code = 'duplicate_transaction';
				$this->error = 'Duplicate transaction.';
				return false;
			}
		}

		// Populate Subscription Processing Fields
		$this->transaction_id   = $payment_id ?: 'TXN_' . $client_reference_id;
		$this->transaction_date = !empty($settled_at) ? strtotime($settled_at) : TIMENOW;
		$this->user_id          = $this->paymentinfo['userid'];

		if (!empty($paid_amount))
		{
			$this->paymentinfo['amount'] = $paid_amount;
		}
		if (!empty($token))
		{
			$this->paymentinfo['currency'] = strtolower($token);
		}

		// Verify Event Type & Dispatch Approval. An EMPTY event is never
		// treated as approval -- a signed payload always names its event
		// explicitly, so a blank one has nothing legitimate behind it.
		if ($event === 'payment.settled' || $event === 'subscription.created' || $event === 'subscription.charge_settled')
		{
			$this->type = 1; // 1 = Subscription Active / Payment Cleared

			$sapi_type = php_sapi_name();
			if (substr($sapi_type, 0, 3) == 'cgi')
			{
				@header("Status: 200 OK");
			}
			else
			{
				@header($_SERVER['SERVER_PROTOCOL'] . ' 200 OK');
			}

			$this->logDebug("Success: Payment verified & approved for User ID {$this->user_id} ({$this->paymentinfo['username']}) - Plan SubID: {$this->paymentinfo['subscriptionid']}");
			return true;
		}
		elseif ($event === 'subscription.canceled' || $event === 'subscription.charge_failed')
		{
			$this->type = 3; // 3 = Subscription Canceled
			$this->logDebug("Notice: Subscription cancellation event processed for User ID {$this->user_id}");
			return true;
		}
		else
		{
			$this->logDebug("Unhandled or empty event: '{$event}'");
			$this->error_code = 'unhandled_event';
			$this->error = "Unhandled event type: {$event}";
			return false;
		}
	}

	/**
	* Tests gateway connectivity and credentials against the MMOCoin Pay API
	*
	* @return bool
	*/
	function test()
	{
		$apikey   = trim($this->settings['apikey']);
		$endpoint = rtrim($this->settings['endpoint'] ?: 'https://pay.mmocoin.pro', '/');

		if (empty($apikey))
		{
			return false;
		}

		if (!function_exists('curl_init'))
		{
			return false;
		}

		$ch = curl_init("{$endpoint}/api/v1/me");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Authorization: Bearer ' . $apikey,
			'User-Agent: vBulletin MMOCoinPay/1.0'
		));
		curl_setopt($ch, CURLOPT_TIMEOUT, 8);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

		$response = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($http_code >= 200 && $http_code < 300)
		{
			return true;
		}

		return false;
	}

	/**
	* Generates the hosted checkout redirect session via MMOCoin Pay API
	*
	* @param string $hash Unique vBulletin payment identifier
	* @param float $cost Subscription cost
	* @param string $currency Currency code
	* @param array $subinfo Subscription plan details
	* @param array $userinfo User details
	* @param array $timeinfo Subscription duration info
	* @return array Form action & method
	*/
	function generate_form_html($hash, $cost, $currency, $subinfo, $userinfo, $timeinfo)
	{
		global $vbulletin;

		$this->logDebug("Creating checkout session for User {$userinfo['username']} (ID: {$userinfo['userid']}), Sub: {$subinfo['title']}, Cost: {$cost} {$currency}");

		$apikey   = trim($this->settings['apikey']);
		$endpoint = rtrim($this->settings['endpoint'] ?: 'https://pay.mmocoin.pro', '/');

		if (empty($apikey))
		{
			$this->logDebug("Error: Cannot create checkout session. API key is missing.");
			$form['action'] = $vbulletin->options['bburl'] . '/payments.php';
			$form['method'] = 'get';
			return $form;
		}

		if (empty(trim($this->settings['webhook_secret'])))
		{
			$this->logDebug("Error: Cannot create checkout session. Webhook Signing Secret is missing.");
			$form['action'] = $vbulletin->options['bburl'] . '/payments.php';
			$form['method'] = 'get';
			return $form;
		}

		// Format valid accepted tokens (USDC, USDT, MMO)
		$tokens_raw = !empty($this->settings['accepted_tokens']) ? $this->settings['accepted_tokens'] : 'USDC,USDT,MMO';
		$tokens = array_values(array_filter(array_map('trim', explode(',', strtoupper($tokens_raw)))));
		$valid_tokens = array('SOL', 'USDC', 'USDT', 'MMO');
		$filtered_tokens = array_values(array_intersect($tokens, $valid_tokens));

		$success_url = $vbulletin->options['bburl'] . '/payments.php?status=success&hash=' . urlencode($hash);

		$session_payload = array(
			'title'             => substr($subinfo['title'] . ' Subscription', 0, 80),
			'description'       => substr('User: ' . $userinfo['username'] . ' (ID: ' . $userinfo['userid'] . ') | Forum: ' . $vbulletin->options['bbtitle'], 0, 500),
			'type'              => 'FIXED',
			// Subscription costs are set in the forum's own currency, which is
			// a real-world currency, not a token count. Pricing in USD lets
			// each token convert at checkout -- without it a 15 USD plan was
			// billed as 15 MMO, which is a fraction of a cent.
			'pricing'           => 'USD',
			'amount'            => (float)$cost,
			'redirectUrl'       => $success_url,
			'clientReferenceId' => (string)$hash,
			'expiresInMinutes'  => 60,
			'metadata'          => array(
				'userid'         => (int)$userinfo['userid'],
				'username'       => (string)$userinfo['username'],
				'subscriptionid' => (int)$subinfo['subscriptionid'],
				'hash'           => (string)$hash
			)
		);

		if (!empty($filtered_tokens))
		{
			$session_payload['tokens'] = $filtered_tokens;
		}

		$body = json_encode($session_payload);

		// Call pay.mmocoin.pro API v1 checkout sessions
		$ch = curl_init("{$endpoint}/api/v1/checkout-sessions");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Authorization: Bearer ' . $apikey,
			'Content-Type: application/json',
			'User-Agent: vBulletin MMOCoinPay/1.0'
		));
		curl_setopt($ch, CURLOPT_TIMEOUT, 12);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

		$response_raw = curl_exec($ch);
		$http_code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curl_error   = curl_error($ch);
		curl_close($ch);

		$response = json_decode($response_raw, true);

		if ($http_code === 201 && !empty($response['session']['url']))
		{
			$checkout_url = $response['session']['url'];
			$this->logDebug("Checkout session generated successfully: {$checkout_url}");

			$form['action'] = $checkout_url;
			$form['method'] = 'get';
			return $form;
		}
		else
		{
			$err_msg = !empty($response['error']) ? $response['error'] : (!empty($response['message']) ? $response['message'] : ($curl_error ?: "HTTP {$http_code}"));
			$this->logDebug("Checkout session creation failed: {$err_msg} | Response: {$response_raw}");

			// If API error, fallback to payments page with notice
			$form['action'] = $vbulletin->options['bburl'] . '/payments.php';
			$form['method'] = 'get';
			return $form;
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # MMOCoin Pay Gateway Engine - v1.0.0 (vBulletin 5 build)
|| ####################################################################
\*======================================================================*/
?>
