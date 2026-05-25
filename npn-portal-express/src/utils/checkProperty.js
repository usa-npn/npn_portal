module.exports = function checkProperty(obj, prop, allowsLiteralZero = false) {
  if (!obj || !Object.prototype.hasOwnProperty.call(obj, prop)) return false;
  const val = obj[prop];
  if (val === undefined || val === null || val === '') return false;
  if (!allowsLiteralZero && val === 0) return false;
  if (!allowsLiteralZero && val === '0') return false;
  return true;
};
