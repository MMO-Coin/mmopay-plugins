const authentication = require('./authentication');
const newPayment = require('./triggers/newPayment');
const newSubscriber = require('./triggers/newSubscriber');

const addApiKeyHeader = (request, z, bundle) => {
  if (bundle.authData && bundle.authData.apiKey) {
    request.headers = request.headers || {};
    request.headers.Authorization = `Bearer ${bundle.authData.apiKey}`;
  }
  return request;
};

// MMO Pay's own API already returns { "error": "message" } on failure (see
// errorResponse() in the main repo's src/lib/http.ts) -- surface that
// message instead of a bare "Request failed with status 4xx", since that
// message is usually the actual, actionable reason (e.g. "Missing or
// malformed API key", "Too many requests").
const surfaceApiErrors = (response, z, bundle) => {
  if (response.status >= 400) {
    // response.data is the already-JSON-parsed body (the documented,
    // verified access pattern -- see the trigger perform() functions, which
    // read response.data the same way). MMO Pay's API always returns
    // { "error": "message" } on failure, so this is almost always the real,
    // actionable reason rather than a bare status code.
    const body = response.data;
    const message =
      body && body.error ? body.error : `Unexpected status ${response.status}`;
    throw new z.errors.Error(message, 'MMOPayApiError', response.status);
  }
  return response;
};

module.exports = {
  version: require('./package.json').version,
  platformVersion: require('zapier-platform-core').version,

  authentication,

  beforeRequest: [addApiKeyHeader],
  afterResponse: [surfaceApiErrors],

  triggers: {
    [newPayment.key]: newPayment,
    [newSubscriber.key]: newSubscriber,
  },

  searches: {},
  creates: {},
};
