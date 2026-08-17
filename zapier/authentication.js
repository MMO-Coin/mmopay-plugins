// Custom (API key) authentication. The key is whatever the merchant
// generated on pay.mmocoin.pro under Dashboard -> Developers -- the same
// key every other MMO Pay integration uses, nothing Zapier-specific about
// it. test() is what Zapier calls the moment a user pastes a key into the
// "Connect Account" screen, so a wrong key fails there instead of on the
// first real Zap run.

const testAuth = async (z, bundle) => {
  const response = await z.request({
    url: 'https://pay.mmocoin.pro/api/v1/me',
  });
  // A non-2xx here throws automatically (see afterResponse in index.js),
  // so reaching this line already means the key works.
  return response.data;
};

module.exports = {
  type: 'custom',
  fields: [
    {
      key: 'apiKey',
      label: 'API Key',
      type: 'string',
      required: true,
      helpText:
        'From pay.mmocoin.pro: Dashboard -> Developers -> API keys. Starts with mmo_live_ or mmo_sk_.',
    },
  ],
  test: testAuth,
  // Deliberately no connectionLabel override here: getting its data source
  // wrong (test() output vs. raw auth fields, which differs by platform-core
  // version) would ship untested guesswork for a purely cosmetic field.
  // Zapier's default label works fine without it.
};
