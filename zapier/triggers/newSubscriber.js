// Polling trigger against GET /api/v1/subscriptions, filtered to ACTIVE.
// Filtering server-side rather than client-side matters here: a row is
// created the moment someone starts checkout (status INCOMPLETE) but this
// trigger must only fire once they actually finish it. Because the id only
// enters the ACTIVE-filtered result set at the moment of activation, the
// first time Zapier's poll ever sees that id is exactly when the
// subscription went live, so its ordinary "new id = new event" dedupe does
// the right thing without this app tracking any state of its own.
//
// Known gap: a RESUMED subscription (cancelled, then turned back on within
// its paid period) keeps its original id and createdAt. If that id has
// already scrolled past this trigger's page size in createdAt-sorted order,
// the resume will not surface here. New signups, which are what this
// trigger is for, are unaffected.

const listSubscribers = async (z, bundle) => {
  const response = await z.request({
    url: 'https://pay.mmocoin.pro/api/v1/subscriptions',
    params: {
      status: 'ACTIVE',
      limit: 25,
    },
  });

  return response.data.subscriptions;
};

module.exports = {
  key: 'new_subscriber',
  noun: 'Subscriber',
  display: {
    label: 'New Subscriber',
    description: 'Triggers when someone subscribes to one of your plans and their first payment settles.',
  },
  operation: {
    type: 'polling',
    perform: listSubscribers,
    // Mirrors subscriptionResource() in the MMO Pay API exactly -- see
    // src/lib/api/v1.ts on the main repo. manageUrl is null here on purpose:
    // the list endpoint never includes it, even for the subscriber's own
    // account, so a leaked API response can never hand out someone else's
    // manage link.
    sample: {
      object: 'subscription',
      id: 'clhj1w8o9000008l2f0z9y8x',
      planId: 'clhj0v1n8000008jx7h1a2b3',
      status: 'ACTIVE',
      subscriberAlias: 'swift-otter-482',
      currentPeriodStart: '2026-08-17T10:15:00.000Z',
      currentPeriodEnd: '2026-09-17T10:15:00.000Z',
      graceUntil: null,
      canceledAt: null,
      manageUrl: null,
      createdAt: '2026-08-17T10:15:00.000Z',
    },
  },
};
