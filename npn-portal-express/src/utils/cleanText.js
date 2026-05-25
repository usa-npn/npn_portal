module.exports = function cleanText(str) {
  if (!str) return str;
  return str.replace(/[^\x20-\x7F]/g, '');
};
