module.exports = {
  verifyUser: (accessToken, consumerKey) => {
    // Mock logic — replace with real DB validation
    if (accessToken === 'valid_token' && consumerKey === 'valid_key') {
      return 123;
    }
    return null;
  }
};