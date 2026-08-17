# MMOCoin Pay for XenForo 2.x (Official Payment Provider)

**Accept instant MMO, USDT, and USDC crypto payments for XenForo User Upgrades, with zero chargebacks and automated activation.**

---

## Before you install

This build was written against XenForo's own developer documentation and a real, working open-source XF2 payment provider used as a structural reference (the class extends `XF\Payment\AbstractProvider`, the same base every XF payment integration uses). It was not tested against a live XenForo install: none was available while building it. Test it on a staging board with a real payment before turning it on for members.

---

## Features

- **Zero-chargeback instant crypto payments**: connects directly to [pay.mmocoin.pro](https://pay.mmocoin.pro) for instant on-chain settlement.
- **Supported tokens**: MMO, USDT (Solana), USDC (Solana).
- **Mandatory webhook verification**: every approval is gated on a valid HMAC-SHA256 signature. No signature, no upgrade.
- **Priced in dollars, paid in any token**: set your User Upgrade's cost in USD once; MMOCoin Pay converts it into whichever token the buyer picks, at the live rate, when they check out.
- **Replay protection**: duplicate webhook deliveries are ignored, matching XenForo's own dedupe convention.

---

## Requirements

- **XenForo**: 2.1 or later
- **PHP**: 7.4, 8.0, 8.1, 8.2, 8.3+ with `cURL` and `hash` extensions
- **Merchant account**: an active account on [pay.mmocoin.pro](https://pay.mmocoin.pro) with an API key (`mmo_live_...` or `mmo_sk_...`) from Dashboard → Developers
- **Solana rent balance**: your payout wallet needs at least ~0.01 SOL for rent-exempt token account creation

---

## Installation

1. **Upload files** — the contents of `upload/` to your forum root:
   ```
   src/addons/MMOCoinPay/
   ```
2. **Install the add-on** — AdminCP → Add-ons → Install/Upgrade → select MMOCoinPay → Install.
3. **Create a Payment Profile** — AdminCP → User Upgrades → Payment Profiles → Create New Payment Profile, provider **MMOCoin Pay (USDC, USDT, MMO)**:
   - **API Key** — from [pay.mmocoin.pro/dashboard/developers](https://pay.mmocoin.pro/dashboard/developers)
   - **Webhook Signing Secret** — **required.** Generate any random string, enter it here, then enter the *same* string on pay.mmocoin.pro under Dashboard → Developers → Webhooks.
   - **API Base URL** — `https://pay.mmocoin.pro`
   - **Accepted Crypto Tokens** — `USDC,USDT,MMO`
4. **Set the webhook URL** on pay.mmocoin.pro to:
   ```
   https://yourforum.com/payment_callback.php?_xfProvider=mmocoinpay
   ```
   Payments cannot be approved without this.
5. **Attach the profile** to a User Upgrade priced in USD.

---

## License & Support

- **Author**: MMOCoin Dev Team
- **Official portal**: [https://pay.mmocoin.pro](https://pay.mmocoin.pro)
- **Support & discussion**: [https://www.mmopro.org](https://www.mmopro.org)
- **License**: MIT License
