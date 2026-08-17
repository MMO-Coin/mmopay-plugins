// Polling trigger, not a REST Hook: it periodically calls the same
// GET /api/v1/payments a merchant's own dashboard uses, filtered to
// SETTLED, newest first. Zapier deduplicates on the `id` field itself, so
// this never needs its own webhook-subscription bookkeeping on the MMO Pay
// side -- the existing, already-hardened list endpoint is the whole
// integration surface.
//
// This single trigger also covers subscription charges: every successful
// recurring charge is written as a normal settled Payment row (see MMO
// Pay's own architecture notes), so a Zap built on "New Payment" already
// fires for subscription renewals too, with no separate trigger needed.

const listPayments = async (z, bundle) => {
  const response = await z.request({
    url: 'https://pay.mmocoin.pro/api/v1/payments',
    params: {
      status: 'SETTLED',
      limit: 25,
    },
  });

  return response.data.payments;
};

module.exports = {
  key: 'new_payment',
  noun: 'Payment',
  display: {
    label: 'New Payment (Settled)',
    description:
      'Triggers when a payment settles on-chain, including recurring subscription charges.',
  },
  operation: {
    type: 'polling',
    perform: listPayments,
    // Mirrors paymentResource() in the MMO Pay API exactly -- see
    // src/lib/api/v1.ts on the main repo -- so this sample never claims a
    // field the real endpoint doesn't actually return.
    sample: {
      object: 'payment',
      id: 'cljk2x9p0000108l3g1a2b3c',
      status: 'SETTLED',
      token: 'USDC',
      expectedAmount: '15.00',
      paidAmount: '15.00',
      signature: '5xy...examplesig',
      buyerAlias: 'swift-otter-482',
      sessionId: 'clhj1w8o9000008l2f0z9y8x',
      memo: null,
      receiptUrl: 'https://pay.mmocoin.pro/r/abc123examplereceipt',
      expiresAt: '2026-08-17T10:29:12.000Z',
      settledAt: '2026-08-17T10:15:00.000Z',
      createdAt: '2026-08-17T10:14:12.000Z',
    },
  },
};
