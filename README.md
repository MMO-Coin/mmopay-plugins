# MMO Pay Plugins

Official plugin source for accepting [MMO Pay](https://pay.mmocoin.pro) (instant Solana crypto payments in USDC, USDT, and MMO) on the platforms below.

Source lives here so every change is a normal commit, not a hand-copied zip. **Releases are the actual distribution mechanism**: pay.mmocoin.pro's dashboard links to `releases/latest/download/<name>`, which always resolves to whatever this repo's most recent release has attached. Shipping an update is just: commit the change, cut a new release, attach the current zip for every platform (see below), done. Nothing on pay.mmocoin.pro itself needs to change.

## Platforms

| Platform | Folder | Status | Install path |
|---|---|---|---|
| vBulletin 4.x | [`vbulletin4/`](vbulletin4/) | Live | `includes/paymentapi/` |
| vBulletin 5.x | [`vbulletin5/`](vbulletin5/) | Built, untested on a live install | `core/includes/paymentapi/` |
| vBulletin 6.x | [`vbulletin6/`](vbulletin6/) | Built, untested on a live install | `core/includes/paymentapi/` |
| XenForo 2.x | [`xenforo/`](xenforo/) | Built, untested on a live install | `src/addons/MMOCoinPay/` |
| WooCommerce (WordPress) | [`wordpress/`](wordpress/) | Built, untested on a live install | `wp-content/plugins/` |
| Zapier | [`zapier/`](zapier/) | Built, not yet pushed to Zapier | Deployed via `zapier push`, not a zip |

Each platform folder is self-contained: its own `README.md` and (except Zapier, which has no install-time secret to configure until it's deployed) `INSTALL.txt` with exact setup steps.

Zapier is structurally different from the rest: there is no file a merchant installs, and shipping it requires a Zapier Developer account to run `zapier push`/`zapier promote`, not just a GitHub release. See `zapier/README.md` for why, and for what a REST Hook trigger (real-time instead of polling) would require of the webhook system every other platform here depends on.

## Cutting a release

1. Zip the contents of a platform folder's `upload/` directory (plus its `README.md`/`INSTALL.txt` if the platform's install process expects them alongside) into a **stable, version-less filename**, the name it already has in the dashboard's download link:
   - `MMOCoin_Pay_vBulletin_4.x.zip`
   - `MMOCoin_Pay_vBulletin_5.x.zip`
   - `MMOCoin_Pay_vBulletin_6.x.zip`
   - `MMOCoin_Pay_XenForo_2.x.zip`
   - `MMOCoin_Pay_WooCommerce.zip`
2. Create a new GitHub Release (any tag, e.g. the date or a version number) and attach **every** platform's zip to it, not just the one that changed. `releases/latest/download/<name>` only looks at the *latest* release's own assets. An older release having a file doesn't help once a newer release exists without it, so a partial release quietly breaks every download link it left out.
3. That's it. `pay.mmocoin.pro/downloads/plugins/<slug>` (currently wired for `xenforo` and `wordpress`; vBulletin intentionally still points at its forum announcement thread instead) starts serving the new zip immediately.

## Security note

Every provider in this repo requires a webhook signing secret and refuses to activate anything it cannot verify with a valid HMAC-SHA256 signature over the exact payload pay.mmocoin.pro sent. There is no fallback path that trusts an unsigned request. That was a real vulnerability in an early build of the vBulletin plugin, fixed before it ever reached a live install with real payments, and every platform here follows the same rule from the start.
