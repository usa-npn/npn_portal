module.exports = function checkProperty(obj, prop, allowsLiteralZero = false) {
  if (!obj || !Object.prototype.hasOwnProperty.call(obj, prop)) return false;
  const val = obj[prop];
  if (val === undefined || val === null || val === '') return false;
  if (typeof val === 'string' && val.trim() === '') return false;
  if (Array.isArray(val) && (val.length === 0 || val.every(v => v === '' || v === null || v === undefined))) return false;
  if (!allowsLiteralZero && val === 0) return false;
  if (!allowsLiteralZero && val === '0') return false;
  return true;
};
