# MMOCoin Pay for WooCommerce

**Accept instant MMO, USDT, and USDC crypto payments at WooCommerce checkout, with zero chargebacks and automated order settlement.**

---

## Before you install

This build was written against WooCommerce's own official developer documentation and a real, working open-source crypto payment gateway for WooCommerce (CoinGate) used as a structural reference. It extends `WC_Payment_Gateway` the standard, documented way. It has not been tested against a live WordPress/WooCommerce install: none was available while building it. Test it on a staging store with a real order before relying on it for real customers.

---

## Features

- **Zero-chargeback instant crypto payments**: connects directly to [pay.mmocoin.pro](https://pay.mmocoin.pro) for instant on-chain settlement.
- **Supported tokens**: MMO, USDT (Solana), USDC (Solana).
- **Mandatory webhook verification**: every order is marked paid only after a valid HMAC-SHA256 signature. No signature, no settlement.
- **HPOS-compatible**: order lookups go through `wc_get_orders()`, so it works whether the store is on legacy post-meta storage or WooCommerce's newer custom order tables.
- **Priced in dollars, paid in any token**: your WooCommerce order total (in your store's currency) is converted into whichever token the customer picks, at the live rate, at checkout.
- **Replay protection**: a duplicate webhook delivery for an already-settled order is a no-op, not a second charge.

---

## Requirements

- WordPress with **WooCommerce 6.0+** active
- **PHP** 7.4, 8.0, 8.1, 8.2, 8.3+ with `cURL` and `hash` extensions
- A merchant account on [pay.mmocoin.pro](https://pay.mmocoin.pro) with an API key (`mmo_live_...` or `mmo_sk_...`) from Dashboard → Developers
- Payout wallet with at least ~0.01 SOL for rent-exempt token account creation

---

## Installation

1. **Upload** `mmocoin-pay-woocommerce/` (inside `upload/`) to `wp-content/plugins/`.
2. **Activate** it under WP Admin → Plugins.
3. **Configure** under WP Admin → WooCommerce → Settings → Payments → MMOCoin Pay:
   - **API Key**: from [pay.mmocoin.pro/dashboard/developers](https://pay.mmocoin.pro/dashboard/developers)
   - **Webhook Signing Secret**: **required.** Generate any random string, enter it here, then enter the *same* string on pay.mmocoin.pro under Dashboard → Developers → Webhooks.
   - **API Base URL**: `https://pay.mmocoin.pro`
   - **Accepted Crypto Tokens**: `USDC,USDT,MMO`
4. **Set the webhook URL** on pay.mmocoin.pro to the URL shown next to the Webhook Signing Secret field (your site's home URL + `?wc-api=wc_gateway_mmocoinpay`). Payments cannot be approved without this.

---

## License & Support

- **Author**: MMOCoin Dev Team
- **Official portal**: [https://pay.mmocoin.pro](https://pay.mmocoin.pro)
- **Support & discussion**: [https://www.mmopro.org](https://www.mmopro.org)
- **License**: MIT License
