# MMOCoin Pay for vBulletin 5.x (Official Payment Gateway)

**Accept instant MMO, USDT, and USDC crypto payments & paid subscriptions on your vBulletin 5 forum with zero chargebacks and automated usergroup promotion.**

> This is the **vBulletin 5** build. If your forum runs vBulletin 4.x, use the separate `MMOCoin_Pay_vBulletin_4.x` package instead — the files install to different paths and are not interchangeable.

---

## ⚠️ Before you install

This build was written against vBulletin's own published Connect API documentation and vBulletin 5 file-layout reports, not against a live vBulletin 5 install — none was available while building it. vBulletin 5 kept the vBulletin 4 Paid Subscriptions system (`vB_PaidSubscriptionMethod`, the Payment API Manager, the `includes/paymentapi/class_*.php` pattern) largely unchanged rather than rewriting it, so this should install the same way it does on vBulletin 4. Test it on a staging board with a real payment before turning it on for members.

---

## 🚀 Features

- **⚡ Zero-Chargeback Instant Crypto Payments**: Connects directly to [pay.mmocoin.pro](https://pay.mmocoin.pro) for instant on-chain settlement.
- **🪙 Supported Crypto Tokens**:
  - **MMO** (MMOCoin Native Token)
  - **USDT** (Tether on Solana)
  - **USDC** (USD Coin on Solana)
- **🔒 Mandatory Webhook Verification**: Every approval is gated on a valid cryptographic HMAC-SHA256 signature. No signature, no upgrade.
- **🛡️ Replay & Duplicate Attack Protection**: Built-in transaction idempotency ensures no double-crediting.
- **✨ Seamless Automated Promotion**: Users are upgraded to VIP/Premium usergroups instantly upon on-chain settlement.
- **🔄 Auto-Downgrade on Expiry**: Native vBulletin cron integration revokes VIP privileges automatically when the subscription period ends.
- **📊 Detailed Audit Logging**: Optional debug mode logs every session creation and webhook callback to `mmocoin_pay_debug.log`.

---

## 📦 Requirements

- **vBulletin**: 5.x (Connect)
- **PHP**: 7.4, 8.0, 8.1, 8.2, 8.3+ with `cURL` and `hash` extensions.
- **Merchant Account**: An active merchant account on [pay.mmocoin.pro](https://pay.mmocoin.pro) with an API Key (`mmo_live_...` or `mmo_sk_...`) generated from **Dashboard → Developers**.
- **Solana Rent Balance**: Your payout receiving wallet on Solana must hold at least **~0.01 SOL** minimum to support rent-exempt token account creation for incoming token settlements.

---

## 🛠️ Installation Guide

### Step 1: Upload Files
Upload the contents of the `upload/` folder to your vBulletin root directory:
```
core/includes/paymentapi/class_mmocoin_pay.php
```
If your install does not have a `core` folder at the web root, upload to `includes/paymentapi/` instead — vBulletin 5's file layout differs slightly by install method, and the gateway showing up under Payment API Manager confirms it landed in the right place.

### Step 2: Import Product in AdminCP
1. Log in to your vBulletin **AdminCP**.
2. In the left navigation, go to **Projects & Hooks → Manage Products** (this is the same feature vBulletin 4 calls "Plugins & Products", just relabelled).
3. Click **[Add / Import Product]**.
4. Browse and select `product-mmocoin_pay.xml`.
5. Click **Import**.

### Step 3: Configure Gateway Credentials
1. In AdminCP, navigate to **Paid Subscriptions → Payment API Manager**.
2. Locate **MMOCoin Pay (USDC, USDT, MMO)** and click **Edit**.
3. Fill in your merchant settings:
   - **Active**: `Yes`
   - **MMOCoin Pay API Key**: Enter your API key generated from [pay.mmocoin.pro/dashboard/developers](https://pay.mmocoin.pro/dashboard/developers).
   - **Webhook Signing Secret**: **Required.** Generate any random string, enter it here, then enter the *same* string on pay.mmocoin.pro under Dashboard → Developers → Webhooks.
   - **API Base URL**: `https://pay.mmocoin.pro`
   - **Accepted Crypto Tokens**: `USDC,USDT,MMO`
4. On pay.mmocoin.pro, set this merchant's **Webhook URL** to:
   ```
   https://yourforum.com/payment_gateway.php?method=mmocoin_pay
   ```
   Payments cannot be approved without this — the gateway will not activate a subscription it cannot cryptographically verify.
5. Click **Save**.

---

## 🎯 Testing & Verification

1. Go to your forum frontend as a regular member: `https://yourforum.com/payments.php`.
2. Select a Paid Subscription plan and choose **MMOCoin Pay**.
3. You will be redirected to the hosted checkout page on `pay.mmocoin.pro`.
4. Connect a Solana wallet (Phantom, Solflare, etc.) and pay with **MMO**, **USDT**, or **USDC**.
5. Once confirmed on Solana blockchain, the member will be upgraded to the VIP usergroup within moments of the webhook arriving.

---

## 📄 License & Support

- **Author**: MMOCoin Dev Team
- **Official Portal**: [https://pay.mmocoin.pro](https://pay.mmocoin.pro)
- **Support & Discussion**: [https://www.mmopro.org](https://www.mmopro.org)
- **License**: MIT License
