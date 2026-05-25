/**
 * Returns true if the request is protected (i.e. NOT over HTTPS when HTTPS is required).
 * Call this at the top of protected handlers; if it returns true, respond with an error.
 */
function isNotSecure(req) {
  if (process.env.REQUIRE_HTTPS !== '1') return false;
  const host = (req.headers.host || '').split(':')[0];
  if (host === 'localhost' || host === '127.0.0.1') return false;
  const proto = req.headers['x-forwarded-proto'] || (req.secure ? 'https' : 'http');
  return proto !== 'https';
}

module.exports = { isNotSecure };
