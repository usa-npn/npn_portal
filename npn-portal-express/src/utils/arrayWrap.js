module.exports = function arrayWrap(val) {
  if (Array.isArray(val)) return val;
  if (typeof val === 'string' && val.includes(',')) return val.split(',').map(v => v.trim());
  return [val];
};
