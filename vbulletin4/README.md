# MMOCoin Pay for vBulletin 4.x (Official Payment Gateway)

**Accept instant MMO, USDT, and USDC crypto payments & paid subscriptions on your vBulletin 4 forum with zero chargebacks and automated usergroup promotion.**

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

- **vBulletin**: 4.0.0 through 4.2.5+
- **PHP**: 7.4, 8.0, 8.1, 8.2, 8.3+ with `cURL` and `hash` extensions.
- **Merchant Account**: An active merchant account on [pay.mmocoin.pro](https://pay.mmocoin.pro) with an API Key (`mmo_live_...` or `mmo_sk_...`) generated from **Dashboard -> Developers**.
- **Solana Rent Balance**: Your payout receiving wallet on Solana must hold at least **~0.01 SOL** minimum to support rent-exempt token account creation for incoming token settlements.

---

## 🛠️ Quick Installation Guide (2 Minutes)

### Step 1: Upload Files
Upload the contents of the `upload/` folder to your vBulletin root directory:
```
includes/paymentapi/class_mmocoin_pay.php
```

### Step 2: Import Product in AdminCP
1. Log in to your vBulletin **AdminCP**.
2. In the left navigation menu, go to **Plugins & Products** -> **Manage Products**.
3. Click **[Add / Import Product]**.
4. Browse and select `product-mmocoin_pay.xml`.
5. Click **Import**.

### Step 3: Configure Gateway Credentials
1. In AdminCP, navigate to **Paid Subscriptions** -> **Payment API Manager**.
2. Locate **MMOCoin Pay (USDC, USDT, MMO)** and click **Edit**.
3. Fill in your merchant settings:
   - **Active**: `Yes`
   - **MMOCoin Pay API Key**: Enter your API key generated from [pay.mmocoin.pro/dashboard/developers](https://pay.mmocoin.pro/dashboard/developers).
   - **Webhook Signing Secret**: **Required.** Generate any random string, enter it here, then enter the *same* string on pay.mmocoin.pro under Dashboard → Settings → Webhook secret.
   - **API Base URL**: `https://pay.mmocoin.pro`
   - **Accepted Crypto Tokens**: `USDC,USDT,MMO`
4. On pay.mmocoin.pro, set this merchant's **Webhook URL** to:
   ```
   https://yourforum.com/payment_gateway.php?method=mmocoin_pay
   ```
   Payments cannot be approved without this: the gateway will not activate a subscription it cannot cryptographically verify.
5. Click **Save**.

---

## 🎯 Testing & Verification

1. Go to your forum frontend as a regular member: `https://yourforum.com/payments.php`.
2. Select a Paid Subscription plan and choose **MMOCoin Pay**.
3. You will be redirected to the hosted checkout page on `pay.mmocoin.pro`.
4. Connect a Solana wallet (Phantom, Solflare, etc.) and pay with **MMO**, **USDT**, or **USDC**.
5. Once confirmed on Solana blockchain, the member will be upgraded to the VIP usergroup instantly!

---

## 📄 License & Support

- **Author**: MMOCoin Dev Team
- **Official Portal**: [https://pay.mmocoin.pro](https://pay.mmocoin.pro)
- **Support & Discussion**: [https://www.mmopro.org](https://www.mmopro.org)
- **License**: MIT License
