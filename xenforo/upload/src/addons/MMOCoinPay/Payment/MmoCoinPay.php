<?php

/*======================================================================*\
|| ####################################################################
|| # MMOCoin Pay Payment Provider for XenForo 2.x (v1.0.0)
|| # Platform: https://pay.mmocoin.pro
|| # Official Crypto & Subscription Payment Gateway for Solana/USDC/MMO
|| # Compatible: XenForo 2.1+ / PHP 7.4+ / PHP 8.x
|| #
|| # Modelled on XF\Payment\AbstractProvider the same way XF's own core
|| # providers (Stripe, PayPal) and community providers implement it.
|| # Not verified against a live XenForo install -- no install was
|| # available while building it. Test on a staging board before
|| # relying on it for real members. See INSTALL.txt.
|| ####################################################################
\*======================================================================*/

namespace MMOCoinPay\Payment;

use XF\Entity\PaymentProfile;
use XF\Entity\PurchaseRequest;
use XF\Mvc\Controller;
use XF\Payment\AbstractProvider;
use XF\Payment\CallbackState;
use XF\Purchasable\Purchase;

class MmoCoinPay extends AbstractProvider
{
	/** Amounts here are always US dollars, regardless of which crypto token
	 *  the buyer eventually pays with -- see initiatePayment(). A User
	 *  Upgrade priced in anything else cannot be represented, so we refuse
	 *  it explicitly rather than quietly billing the wrong number. */
	protected $supportedCurrencies = ['USD'];

	/** Raw request body and headers, captured in setupCallback() and read
	 *  back in validateCallback(). Kept on this object rather than on
	 *  CallbackState, which is core's type and not ours to extend. */
	protected $rawBody = '';
	protected $signature = '';
	protected $timestamp = '';

	public function getTitle()
	{
		return 'MMOCoin Pay (USDC, USDT, MMO)';
	}

	/**
	 * Called when the admin saves the Payment Profile in the ACP. An API key
	 * and a webhook signing secret are both mandatory: without the first
	 * nothing can be charged, and without the second nothing that gets
	 * charged can ever be verified as real -- see validateCallback().
	 */
	public function verifyConfig(array &$options, &$errors = [])
	{
		$options['api_key'] = trim($options['api_key'] ?? '');
		$options['webhook_secret'] = trim($options['webhook_secret'] ?? '');
		$options['endpoint'] = trim($options['endpoint'] ?? '') ?: 'https://pay.mmocoin.pro';
		$options['accepted_tokens'] = trim($options['accepted_tokens'] ?? '') ?: 'USDC,USDT,MMO';

		if (!$options['api_key'])
		{
			$errors[] = \XF::phrase('mmocoinpay_you_must_enter_an_api_key');
			return false;
		}

		if (!$options['webhook_secret'])
		{
			$errors[] = \XF::phrase('mmocoinpay_you_must_enter_a_webhook_secret');
			return false;
		}

		return true;
	}

	/**
	 * Build the hosted checkout session and hand the buyer a page that sends
	 * them there. Equivalent of the vBulletin build's generate_form_html().
	 */
	public function initiatePayment(Controller $controller, PurchaseRequest $purchaseRequest, Purchase $purchase)
	{
		$paymentProfile = $purchase->paymentProfile;
		$options = $paymentProfile->options;

		$viewParams = [
			'purchase' => $purchase,
			'mmoPay' => [
				'status' => false,
				'message' => \XF::phrase('mmocoinpay_could_not_start_checkout')->render(),
			],
		];

		if (!$this->verifyCurrency($paymentProfile, $purchase->currency))
		{
			$viewParams['mmoPay']['message'] = \XF::phrase('mmocoinpay_upgrade_must_be_priced_in_usd')->render();
			return $controller->view('MMOCoinPay:Payment\Initiate', 'payment_initiate_mmocoinpay', $viewParams);
		}

		$apiKey = trim($options['api_key'] ?? '');
		$endpoint = rtrim($options['endpoint'] ?? '', '/') ?: 'https://pay.mmocoin.pro';
		if (!$apiKey)
		{
			return $controller->view('MMOCoinPay:Payment\Initiate', 'payment_initiate_mmocoinpay', $viewParams);
		}

		$tokensRaw = !empty($options['accepted_tokens']) ? $options['accepted_tokens'] : 'USDC,USDT,MMO';
		$tokens = array_values(array_filter(array_map('trim', explode(',', strtoupper($tokensRaw)))));
		$validTokens = ['SOL', 'USDC', 'USDT', 'MMO'];
		$filteredTokens = array_values(array_intersect($tokens, $validTokens));

		$sessionPayload = [
			'title' => substr((string)$purchase->title, 0, 80),
			'description' => substr(
				'User: ' . $purchase->purchaser->username . ' (ID: ' . $purchase->purchaser->user_id . ')',
				0,
				500
			),
			'type' => 'FIXED',
			'amount' => (float)$purchase->cost,
			// The one number XF gives us is a dollar figure; pricing tells
			// pay.mmocoin.pro to convert it into whichever token the buyer
			// actually picks, at the live rate, rather than billing "15" of
			// a token that might be worth a fraction of a cent.
			'pricing' => 'USD',
			'redirectUrl' => (string)$purchase->returnUrl,
			// request_key is what setupCallback() looks the purchase back up
			// by, so it has to survive the round trip unchanged.
			'clientReferenceId' => (string)$purchaseRequest->request_key,
			'expiresInMinutes' => 60,
			'metadata' => [
				'userId' => (int)$purchase->purchaser->user_id,
				'username' => (string)$purchase->purchaser->username,
				'purchaseRequestId' => (string)$purchaseRequest->purchase_request_id,
			],
		];
		if (!empty($filteredTokens))
		{
			$sessionPayload['tokens'] = $filteredTokens;
		}

		$ch = curl_init(rtrim($endpoint, '/') . '/api/v1/checkout-sessions');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($sessionPayload));
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Authorization: Bearer ' . $apiKey,
			'Content-Type: application/json',
			'User-Agent: XenForo MMOCoinPay/1.0',
		]);
		curl_setopt($ch, CURLOPT_TIMEOUT, 12);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

		$responseRaw = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curlError = curl_error($ch);
		curl_close($ch);

		$response = json_decode((string)$responseRaw, true);

		if ($httpCode === 201 && !empty($response['session']['url']))
		{
			$viewParams['mmoPay']['status'] = true;
			$viewParams['mmoPay']['message'] = $response['session']['url'];
		}
		else
		{
			$errMsg = !empty($response['error'])
				? $response['error']
				: (!empty($response['message']) ? $response['message'] : ($curlError ?: "HTTP {$httpCode}"));
			$viewParams['mmoPay']['message'] = $errMsg;
		}

		return $controller->view('MMOCoinPay:Payment\Initiate', 'payment_initiate_mmocoinpay', $viewParams);
	}

	/** Redirect-only provider: nothing happens inline in the forum. */
	public function processPayment(Controller $controller, PurchaseRequest $purchaseRequest, PaymentProfile $paymentProfile, Purchase $purchase)
	{
	}

	/** Each charge is its own one-time checkout session; XenForo's own
	 *  renewal reminder re-invokes initiatePayment() for the next period,
	 *  the same non-recurring pattern the vBulletin build uses. */
	public function supportsRecurring(PaymentProfile $paymentProfile, $unit, $amount, &$result = self::ERR_NO_RECURRING)
	{
		return false;
	}

	protected function getRequestHeaders()
	{
		$headers = [];
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
				$headerKey = strtolower(str_replace('_', '-', substr($key, 5)));
				if (!isset($headers[$headerKey]))
				{
					$headers[$headerKey] = $value;
				}
			}
		}
		return $headers;
	}

	/**
	 * Parse the incoming webhook into a CallbackState. Nothing here is
	 * trusted yet -- that happens in validateCallback() -- this only reads
	 * the payload and looks up which purchase it claims to be about.
	 */
	public function setupCallback(\XF\Http\Request $request)
	{
		$this->rawBody = file_get_contents('php://input') ?: '';
		$headers = $this->getRequestHeaders();
		$this->signature = isset($headers['x-mmopay-signature']) ? trim($headers['x-mmopay-signature']) : '';
		$this->timestamp = isset($headers['x-mmopay-timestamp']) ? trim($headers['x-mmopay-timestamp']) : '';

		$payload = $this->rawBody ? json_decode($this->rawBody, true) : null;
		if (!is_array($payload))
		{
			return false;
		}

		$state = new CallbackState();
		$state->requestKey = isset($payload['clientReferenceId']) ? (string)$payload['clientReferenceId'] : null;
		$state->transactionId = isset($payload['paymentId']) ? (string)$payload['paymentId'] : null;
		// Deliberately not used for amount verification -- see validateCost().
		$state->costAmount = isset($payload['amount']) ? (string)$payload['amount'] : null;
		$state->costCurrency = isset($payload['token']) ? (string)$payload['token'] : 'USDC';
		$state->paymentStatus = isset($payload['event']) ? (string)$payload['event'] : '';
		$state->ip = $request->getIp();

		if (!$state->requestKey)
		{
			return false;
		}

		return $state;
	}

	/**
	 * The only thing standing between "anyone who can guess a request key"
	 * and a free upgrade: the HMAC signature over the exact bytes that were
	 * sent, checked against the secret set in the ACP. No signature, no
	 * secret configured, or a mismatch all refuse outright -- there is no
	 * weaker fallback path, on purpose.
	 */
	public function validateCallback(CallbackState $state)
	{
		$paymentProfile = $state->getPaymentProfile();
		if (!$paymentProfile)
		{
			$state->logType = 'error';
			$state->logMessage = 'Payment profile not found.';
			return false;
		}

		$webhookSecret = trim($paymentProfile->options['webhook_secret'] ?? '');
		if (!$webhookSecret)
		{
			$state->logType = 'error';
			$state->logMessage = 'Webhook signing secret is not configured.';
			$state->httpCode = 400;
			return false;
		}

		if (!$this->signature || !$this->timestamp)
		{
			$state->logType = 'error';
			$state->logMessage = 'Missing webhook signature.';
			$state->httpCode = 400;
			return false;
		}

		$expectedMac = hash_hmac('sha256', "{$this->timestamp}.{$this->rawBody}", $webhookSecret);
		$expectedSignature = "sha256={$expectedMac}";
		$isValid = function_exists('hash_equals')
			? hash_equals($expectedSignature, $this->signature)
			: ($expectedSignature === $this->signature);

		if (!$isValid)
		{
			$state->logType = 'error';
			$state->logMessage = 'HMAC signature authentication failed.';
			$state->httpCode = 400;
			return false;
		}

		$purchaseRequest = $state->getPurchaseRequest();
		if (!$purchaseRequest)
		{
			$state->logType = 'error';
			$state->logMessage = 'Purchase request not found for this signed callback.';
			return false;
		}

		return $state;
	}

	/** Dedupe by payment id, matching XF's own replay-protection convention
	 *  for gateway callbacks (a delivery retried by our worker must not
	 *  credit the account twice). */
	public function validateTransaction(CallbackState $state)
	{
		if (!$state->transactionId)
		{
			$state->logType = 'info';
			$state->logMessage = 'No payment id on this event; nothing to deduplicate.';
			return false;
		}

		$paymentRepo = \XF::repository('XF:Payment');
		$matchingLogsFinder = $paymentRepo->findLogsByTransactionId($state->transactionId);
		if ($matchingLogsFinder->total())
		{
			$state->logType = 'info';
			$state->logMessage = 'Duplicate delivery of an already-processed payment.';
			return false;
		}

		return parent::validateTransaction($state);
	}

	/**
	 * Always true, deliberately. costAmount on a settled event is the amount
	 * of whatever crypto token the buyer paid with (e.g. "15.007979" USDC),
	 * not the USD price the User Upgrade was configured with -- comparing
	 * the two would be comparing different currencies and would always
	 * "fail". The real amount check already happened once, in
	 * pay.mmocoin.pro's own settlement logic, before this webhook was ever
	 * signed and sent.
	 */
	public function validateCost(CallbackState $state)
	{
		return true;
	}

	/**
	 * Only payment.settled (or the subscription equivalents) are ever
	 * treated as a completed payment. Everything else, cancellations
	 * included, is logged and left alone rather than guessed at: XenForo's
	 * own upgrade-expiry already ends access when nothing renews it, the
	 * same way the vBulletin build relies on dunning rather than a
	 * cancellation webhook to end access.
	 */
	public function getPaymentResult(CallbackState $state)
	{
		switch ($state->paymentStatus)
		{
			case 'payment.settled':
			case 'subscription.created':
			case 'subscription.charge_settled':
				$state->paymentResult = CallbackState::PAYMENT_RECEIVED;
				break;
		}
	}

	public function completeTransaction(CallbackState $state)
	{
		parent::completeTransaction($state);
	}

	public function prepareLogData(CallbackState $state)
	{
		$state->logDetails = [
			'event' => $state->paymentStatus,
			'paymentId' => $state->transactionId,
			'requestKey' => $state->requestKey,
		];
	}

	public function verifyCurrency(PaymentProfile $paymentProfile, $currencyCode)
	{
		return in_array($currencyCode, $this->supportedCurrencies, true);
	}
}
