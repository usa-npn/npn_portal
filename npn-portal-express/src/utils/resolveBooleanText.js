module.exports = function resolveBooleanText(params, varName, defaultValue = false) {
  if (!params || !Object.prototype.hasOwnProperty.call(params, varName)) return defaultValue;
  const v = params[varName];
  if (v === true || v === 1 || v === '1' || v === 'true') return true;
  if (v === false || v === 0 || v === '0' || v === 'false') return false;
  return defaultValue;
};
