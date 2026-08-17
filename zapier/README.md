# MMO Pay for Zapier

Wires MMO Pay payment and subscription events into Zapier, so a Zap can react the moment something settles without a merchant writing any code.

---

## Why this is different from the other MMO Pay integrations

Every other platform here (vBulletin, XenForo, WooCommerce) is a file a merchant installs on their own server. Zapier isn't: it's a hosted platform, and an integration only becomes real once it's pushed to Zapier's own infrastructure through their CLI and, for public use, reviewed and published by Zapier.

That means two things:

1. **There is no zip to download.** This repo holds the source; deploying it is `zapier push`, not attaching a release asset.
2. **A Zapier Developer Platform account is required** to actually ship this, and that account has to belong to whoever runs it: `zapier login` ties a push to a specific Zapier account, so this cannot be deployed from source alone the way the other plugins can.

## Design: polling, not webhooks

This uses Zapier's polling trigger pattern against MMO Pay's existing `GET /api/v1/payments` and `GET /api/v1/subscriptions` endpoints, the same ones a merchant's own dashboard and any custom integration already use. Zapier calls them every 1 to 15 minutes (plan-dependent) and deduplicates on each item's `id`.

The alternative, a REST Hook trigger, would deliver events in near real time instead of on a poll interval, but it requires MMO Pay to support an arbitrary number of subscriber URLs per merchant (one per Zap someone builds), which is a real change to the webhook delivery system every other integration also depends on. Polling needed zero changes to that system: both endpoints already return exactly the sorted, `id`-bearing shape a polling trigger wants. If real-time delivery matters enough later, REST Hooks are a defined upgrade path, not a rewrite.

## Triggers

- **New Payment (Settled)**: fires on every settled payment, including subscription renewals (a recurring charge is written as an ordinary settled `Payment` row, so one trigger covers both).
- **New Subscriber**: fires when someone's subscription becomes active. Known gap, stated plainly: a *resumed* subscription (cancelled, then turned back on within its paid period) keeps its original id and may not resurface if it has scrolled past this trigger's page in creation order. New signups are unaffected.

## Files

- `authentication.js`: custom API key auth, tested against `GET /api/v1/me`.
- `index.js`: app definition: wires auth, injects the `Authorization: Bearer` header on every request, and turns MMO Pay's own `{ "error": "..." }` responses into readable Zapier errors instead of a bare status code.
- `triggers/newPayment.js`, `triggers/newSubscriber.js`: the two polling triggers above.

## Deploying (requires your own Zapier Developer account)

1. `npm install -g zapier-platform-cli`
2. `zapier login`
3. `zapier init mmocoin-pay --template minimal`: let Zapier's own scaffolding generate `package.json` with a correct, current `zapier-platform-core` version. This repo does not pin that version itself, on purpose: guessing it risks shipping against a stale one.
4. Replace the scaffolded `index.js` with this repo's `index.js`, and copy `authentication.js` and `triggers/` in alongside it.
5. `zapier test`: runs against a real MMO Pay account; you'll need a live API key.
6. `zapier push`: deploys a private version visible only to your Zapier account.
7. Invite testers with `zapier users:add <email> <version>`, or submit for public review with `zapier promote <version>` once you're satisfied.

## Security note

Every trigger authenticates with the merchant's own MMO Pay API key, sent as `Authorization: Bearer <key>` on every request, the same key every other MMO Pay integration uses. There is nothing Zapier-specific to secure separately: revoking the key from the MMO Pay dashboard disconnects every Zap using it immediately.
